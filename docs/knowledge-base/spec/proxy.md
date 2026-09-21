# SPEC: 代理分流（Proxy）

> ⚠️ **本文件描述的是已冻结的旧线（`mynet_lua` / `mynet_client`），不是当前的实现依据。**
> 冻结策略见 [`../integration-plan-v2.md`](../integration-plan-v2.md) §2.2；
> 本目录（`spec/README.md`）声明的唯一实现是 **`mynet_agent`（`mynetd`）**。
> **目标线对应**：**proxy 在目标线整体下架**：能力归宿见 [`control-plane.md`](control-plane.md) §7


> 更新：2026-09-21 | 适用：mynet_lua / mynet_client
> 状态：**已冻结（旧线）** —— mynet_lua / mynet_client 线不再演进
>
> ⚠️ **新线口径见 [`control-plane.md`](control-plane.md) §7**。
> `mynet_proxy` CLI **坚决下架**：其最重的职责（往 route.conf 灌海量路由）已被
> GNB 原生 `p2-route-proxy`（`node.conf` 的 `proxy on`）取代。剩余能力的归宿是
> **L2 TransportPolicy → L3 BundleEnvelope → L5 Driver（gnb / wireguard 各自落地）
> + L6 NetworkBackend（OS 策略路由，驱动无关）**，而不是节点上手写配置的 CLI。
>
> 本文档保留原实现细节，仅供旧线维护与历史对照。

---

## 架构概述

代理流量**不走内核主路由表**，而是通过 fwmark + 策略路由 + GNB 隧道实现分流：

```
LAN 客户端请求
  → nftables/iptables PREROUTING mangle
    → 命中 IP 集合（国内/海外）→ 打标记 fwmark 0xc8
      → ip rule: fwmark 0xc8 → 查 table 200
        → table 200: default via {peer_vip} dev {tun_iface}
          → GNB 隧道 → 对端节点出口
```

**四个操作概念：**
- `enable/disable`：持久化配置 + 修改 route.conf（需重启 GNB 生效）
- `start/stop`：运行时应用/清除 policy routing（不重启 GNB）

---

## 数据模型（参见 data-models.md `proxy_role.conf`）

| 参数 | 取值 | 说明 |
|------|------|------|
| `PROXY_ENABLED` | `0`/`1` | 是否随 GNB 自启 |
| `PROXY_MODE` | `client`/`server`/`both` | 分流角色 |
| `NODE_REGION` | `domestic`/`international`/`non_domestic` | 节点所在区域 |
| `DNS_MODE` | `none`/`redirect`/`resolv`/`split` | DNS 策略 |
| `DNS_SERVER` | IPv4 | 国外 DNS（redirect/resolv 目标，split 的国外 DNS） |
| `DNS_DOMESTIC_SERVER` | 逗号分隔 IPv4 | split 模式的国内 DNS，默认 `223.5.5.5,119.29.29.29` |
| `PROXY_PEERS` | 逗号分隔 node_id | 出站对端节点 |

---

## OPERATION: proxy.enable(opts)

**效果：** 持久化配置 + 在 route.conf 中注入 pipe 路由段（GNB 数据层知道哪些 IP 走隧道）。  
**注意：** GNB 无热加载，route.conf 修改后必须重启 GNB 才生效。

**STEPS:**
1. 验证参数（`validate_params(opts)`）
2. 持久化参数到 `proxy_role.conf`，设置 `PROXY_ENABLED=1`
3. `route_inject(node_id, peers)`：
   - 在 route.conf 末尾、`#----proxy begin----` / `#----proxy end----` 标记之间写入：
     - 每个 peer 的 `/8` 大段路由（`peer_nid|x.0.0.0|255.0.0.0`）
     - 跳过私有段（172.x/16、192.168.x/16）
     - 约 714 条/peer

**POSTCONDITION:**
- `proxy_role.conf` 已更新
- route.conf 包含 proxy pipe 路由段（含注入时间戳 `T_inject`）
- 下次 GNB 启动时自动应用

---

## OPERATION: proxy.disable()

**STEPS:**
1. `route_restore()`：删除 route.conf 中 `#----proxy begin----` / `#----proxy end----` 之间的全部内容
2. 持久化 `PROXY_ENABLED=0` 到 `proxy_role.conf`
3. 若 proxy policy 正在运行 → 调用 `proxy.stop()`

---

## OPERATION: proxy.start(opts?) — post-start hook / 独立启动

**PRECONDITION:**
- GNB 进程正在运行，TUN 接口可用
- `PROXY_ENABLED=1`（独立调用时也需满足）

**STEPS:**
1. 若 `_running` → 返回 `already_running`
2. 验证参数（`validate_params()`），失败 → 返回 `skipped: not_configured`
3. 检查 GNB 是否运行中 + TUN 接口可用，否 → 返回 `skipped: gnb_not_running`
4. 检查 route.conf 注入时间戳（`T_inject`）是否早于 GNB 启动时间（`T_gnb_start`）：
   - 是（路由已加载）→ 仅应用 policy routing，不重启 GNB（`restarted=false`）
   - 否（路由未加载）→ 先停止服务，再完整重启（`restarted=true`）
5. 生成运行参数文件 `proxy_policy_params.env`（参见 data-models.md）
6. **若 role == client 或 both：**
   - 生成 IP 列表（**由 `NODE_REGION` + `PROXY_POLICY` 共同决定数据源**，见下方矩阵）：
     - `domestic + whitelist` → `interip.txt`，`MATCH_MODE=normal`
     - `domestic + blacklist` → `chinaip.txt`，`MATCH_MODE=inverted`
     - `international + whitelist` → `chinaip.txt`，`MATCH_MODE=normal`
     - `international + blacklist` → `interip.txt`，`MATCH_MODE=inverted`
   - 创建 IP 集合（nft set 或 ipset）并加载
   - 应用 client policy routing：
     - `ip route add default via {peer_vip} dev {tun_iface} table 200`
     - `ip rule add fwmark 0xc8 lookup table 200 prio 31800`
     - WAN 网关 `/32` 修复（防止 GNB /24 覆盖 WAN 直连）
     - nft/iptables PREROUTING mangle 根据 `MATCH_MODE` 打标记：
       - `normal`：命中 IP 集合 → mark 0xc8
       - `inverted`：**未**命中 IP 集合 → mark 0xc8
     - **LAN 转发场景额外规则**（当有 LAN 客户端经 VM 转发时必须）：
       - mangle_prerouting 须显式处理 VPN 接口入方向的 `established/related` 包（`iifname {tun} ct state established,related return`），否则回包被反转规则重打 mark，路由回 VPN 死循环
       - postrouting 须在高于 fw4 srcnat 的优先级（`srcnat - 5`）对 `{tun}→{lan_bridge}` 方向 return，跳过 fw4 的 srcnat_lan masquerade，否则回包源 IP 被改为路由器 IP，客户端 TCP 丢弃
7. **若 role == server 或 both：**
   - 探测 WAN 接口名
   - `sysctl net.ipv4.ip_forward=1`
   - iptables POSTROUTING MASQUERADE（从 TUN 接口来的流量做 NAT）
   - iptables FORWARD accept（TUN ↔ WAN 双向）
8. **DNS 策略**（`DNS_MODE`）：
   - `none`：不修改 DNS 配置
   - `redirect`：nft/iptables NAT prerouting DNAT br-lan:53 → peer_vip:53
   - `resolv`：修改 `/etc/resolv.conf`（或等价文件）nameserver → peer_vip
   - `split`：调用 `dns_split setup {domestic_dns} {foreign_dns}`（参见下方 DNS Split 规范）
9. **无条件执行**（不受任何参数分支影响）：`route_foreign_dns()`（参见下方）
10. 注册防火墙 include（系统防火墙重启后自动恢复 policy routing）
11. 写入 `proxy_state.json`（`start_ts`、`mode`、`region`、`dns_mode`）

**POSTCONDITION:**
- Policy routing 运行中
- `proxy_state.json` 存在

---

## OPERATION: proxy.stop()

**STEPS:**
1. **若 role == client：** 清除 IP 集合，删除 ip rule / ip route（table 200），清除 nft/iptables mangle 规则
2. **若 role == server：** 清除 iptables MASQUERADE + FORWARD 规则
3. **DNS 策略清理（与 start 对应）：**
   - `redirect`：删除 DNAT 规则
   - `resolv`：恢复 resolv.conf
   - `split`：调用 `dns_split stop`
4. `unroute_foreign_dns()`：删除全部国外 DNS `/32` 路由
5. 删除 `proxy_state.json`
6. 设置 `_running = false`

---

## OPERATION: route_foreign_dns()

**目的：** 强制公共国外 DNS 服务器的查询通过 GNB 隧道，防止 ISP DNS 污染/拦截。

**关键设计：这些 /32 路由写入系统主路由表（main table），而非策略路由表 200。**  
原因：策略路由只对被 fwmark 标记的包生效；DNS 查询本身需要先能到达 DNS 服务器才能获得 IP 再打标记，因此必须在主表强制 /32 路由，绕过可能被 ISP 污染的 DNS 路径。`/32` 精度高于 GNB 注入的 `/8` pipe 路由，优先匹配。

**固定服务器列表（此处为 SSOT，两端必须一致）：**
```
8.8.8.8        (Google)
8.8.4.4        (Google)
1.1.1.1        (Cloudflare)
1.0.0.1        (Cloudflare)
9.9.9.9        (Quad9)
208.67.222.222 (OpenDNS)
208.67.220.220 (OpenDNS)
```

**兜底 DNS（split 模式的最后一道保障）：**

`dns.mynet.club` 是 MyNet 平台自建的 DNS 解析服务，当上述公共 DNS 均不可达时作为兜底。  
在 `split` 模式下，dnsmasq 的 `server=` 指令应包含：
```
server=dns.mynet.club
```
仅作为 fallback（优先级低于用户配置的 `DNS_SERVER`），不强制覆盖。

**STEPS:**
1. 对每个 IP 执行：`ip route replace {ip}/32 dev {tun_iface}`（写入主路由表，无 `table` 参数）
2. 验证每条路由：`ip route get {ip}` 输出中 `dev` 必须是 TUN 接口
3. 返回 `{ routed: N, verified: N, total: 7 }`

**关键约束：**
- 写入**主路由表**（`ip route replace`，不加 `table 200`），对所有进程生效
- `/32` 精度优先于 GNB 注入的 `/8` pipe 路由，两者共存不冲突
- **无条件执行**：`proxy.start()` 的最后步骤，不受 `dns_mode`/`region`/`mode` 任何分支保护
- `proxy.stop()` 中由 `unroute_foreign_dns()` 清理（`ip route del {ip}/32 dev {tun_iface}`）

---

## OPERATION: dns_split setup(domestic_dns, foreign_dns)

**STEPS:**
1. 配置 dnsmasq 上游：`server={domestic_dns}`（国内默认）
2. 读取 GFW 域名列表（`gfwlist.conf`），写入 dnsmasq 配置：
   ```
   server=/{domain}/{foreign_dns}
   ```
3. 读取额外国外域名列表（npm、docker、openai 等），同样写入
4. 执行 `dnsmasq reload`（**不是 restart**，避免中断 in-flight 查询）

**接口规范：**
```sh
dns_split setup [domestic_dns] [foreign_dns]
dns_split update [foreign_dns]    # 仅更新国外 DNS 服务器，不重建域名列表
dns_split status
dns_split stop                    # 恢复 dnsmasq 到默认配置
```

---

## Region × Policy × MATCH_MODE 矩阵

> **CRITICAL**：IP 列表数据源由 `NODE_REGION` **和** `PROXY_POLICY` 共同决定，实现侧不得仅依赖 region 选择数据源。

| NODE_REGION | PROXY_POLICY | IP 集合数据源 | MATCH_MODE | 语义 |
|-------------|--------------|--------------|------------|------|
| `domestic` | `whitelist` | interip.txt（海外 IP） | `normal` | 命中海外 IP → 走代理（国内直连，海外加速） |
| `domestic` | `blacklist` | **chinaip.txt（国内 IP）** | `inverted` | **未命中**国内 IP → 走代理（等效全局代理，国内直连）|
| `international` | `whitelist` | chinaip.txt（国内 IP） | `normal` | 命中国内 IP → 走代理（海外节点回国加速） |
| `international` | `blacklist` | interip.txt（海外 IP） | `inverted` | **未命中**海外 IP → 走代理 |
| `non_domestic`（已废弃） | — | chinaip.txt | `inverted` | 兼容旧版，等同于 `domestic + blacklist` |

**常见错误（已修复）：** 旧版实现仅按 `region` 选择数据源（`domestic→interip.txt`，其他→`chinaip.txt`），
导致 `domestic + blacklist` 时加载 interip.txt 后反转匹配，结果境外 IP 不走代理，被 ISP 封锁。

**PLATFORM DIVERGENCE:**
- **防火墙工具**：nftables 或 iptables 均可，**取决于系统内核版本**，与项目（lua/client）无关。两端代码应运行时探测可用工具，不得硬编码。
  - nftables 优先（Linux 4.8+，OpenWrt 22+）：`nft add rule ... ip daddr @mynet_proxy mark set 0xc8`
  - iptables fallback：`iptables -t mangle -A PREROUTING -m set --match-set mynet_proxy dst -j MARK --set-mark 0xc8`
- **macOS**：无 iptables/nftables，改用 pf + pfctl：`pass out route-to {tun_iface} inet from any to <mynet_proxy>`
- **分流脚本接口统一**：`route_policy.sh start|stop`，通过 `proxy_policy_params.env` 传参

**STATUS 字段（参见 data-models.md proxy_state.json）：**
```json
{
  "layers": {
    "route_inject":       true,
    "policy_routing":     true,
    "dns_intercept":      true,
    "foreign_dns_routed": true
  }
}
```

---

## LAN 客户端透明接入：proxy_arp

### 场景说明

当 LAN 客户端（如 Mac）将 OpenWrt VM 作为**透明网关**、但自身路由表走的是接口 clone 路由（而非配置了 `ip route add default via <VM_LAN_IP>`）时，客户端会对所有目标 IP 直接发 ARP 广播。

OpenWrt 默认 `proxy_arp = 0`，VM 只响应自己拥有的 IP 的 ARP 请求。客户端 ARP 境外 IP（如 142.250.4.93）时 VM 沉默 → 客户端内核生成 `RTF_REJECT` 路由 → 后续包被直接丢弃（`EHOSTUNREACH`，`time_connect=0.000`），流量从未到达 VM。

### 解决方案

```bash
# 运行时（立即生效）
echo 1 > /proc/sys/net/ipv4/conf/br-lan/proxy_arp
echo 1 > /proc/sys/net/ipv4/conf/all/proxy_arp

# 持久化
cat > /etc/sysctl.d/99-mynet-proxy-arp.conf << 'SYSCTL'
net.ipv4.conf.br-lan.proxy_arp = 1
net.ipv4.conf.all.proxy_arp = 1
SYSCTL
```

开启后 VM 代替所有 IP 响应 ARP（返回自身 MAC），客户端将报文送达 VM，nftables prerouting 正常打 fwmark → GNB 转发。

### 何时不需要 proxy_arp

| 客户端路由方式 | 是否需要 |
|---------------|---------|
| 配置了 `ip route add default via <VM_LAN_IP>` | **不需要**（ARP 的是 VM LAN IP） |
| `debug/vm` `/8` 路由模式（`route add -net X.0.0.0/8 <VM_LAN_IP>`） | **不需要**（ARP 的是 VM LAN IP，同上） |
| 接口 clone 路由（`default link#N`，未指定网关） | **需要** |

**生产 OpenWrt 路由器不受影响**：普通 LAN 客户端通过 DHCP 获得网关地址，不会对境外 IP 直接 ARP。仅影响将 VM 作为透明代理的测试/开发场景。
