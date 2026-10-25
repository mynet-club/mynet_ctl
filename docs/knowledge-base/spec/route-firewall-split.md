# 路由 / 防火墙分工 —— L5 驱动 vs L6 系统

> **地位**：路由与防火墙写入权的唯一事实源。脚本、驱动、文档一律引本文件。
> **动机**：181 缺 180 主机路由走 LAN 旁路、188 发往 181 走 AWS 网关、
> 「Lightsail 开了仍不通」——都是 L5/L6 边界不清。
>
> 关联：`spec/paths.md` · `archive/2026-09/core-remediation-plan-20260930.md` 主线③ ·
> `node-connectivity-checklist.md` A–E · `spec/proxy-conf-design.md`

---

## 1. 一句话边界

| 层 | 谁 | 只写 | 绝不写 |
|---|---|---|---|
| **L5 驱动** | gnb / wg 自己 | 地址宣告、邻接、对端密钥、准出策略 | OS 路由、防火墙 |
| **L6 系统** | `mynet apply`（唯一写入者） | OS 路由、防火墙、NAT/MASQ、策略路由 | 驱动 conf 原生文件 |
| **云/机外** | 人 / 云 API | 安全组 / Lightsail UDP | — |

**包从哪来，谁决定**：

```text
系统 ip route        L6：进不进隧道
gnb route.conf       L5：进了隧道后目的归属谁（命中则普通转发，优先于 proxy）
gnb proxy.conf (P2)  L5：未登记目的走哪个 exit
wg AllowedIPs        L5：cryptokey 范围（只放对端 TUN /32）
OS 回程路由          L6：对端怎么把回包送回（必须对称）
防火墙 UDP/FORWARD   L6 + 云：放不放行
```

---

## 2. L5 驱动各写什么

### 2.1 gnb

| 文件 | 语义 | 写入者 |
|---|---|---|
| `node.conf` | 本节点开关（listen/ifname/mtu/proxy on/zip…） | driver 投影 |
| `route.conf` | **地址宣告**：`<uuid>\|<ip>\|<mask>` 谁拥有哪些 VIP/网段。**不是**递归路由表，**不装 OS** | driver 投影 |
| `address.conf` | 邻接：`<attrib>\|<uuid>\|<host>\|<port>`（`i`=index `n`=`p`节点） | driver 投影 |
| `proxy.conf` | P2 准出：role/exit/w/order（**不是** route.conf 注入） | 意图→投影 |
| `security/` `ed25519/` | 密钥 | claim/keys sync |
| `gnb.map` `gnb.pid` | 运行态 | 进程 |

⚠️ **禁止**再往 `route.conf` 注入公网 `/8` `/16` 段（旧 L6 叙事已废弃）。
验收：`route.conf` 行数 ≈ 节点数（overlay /32 + 少量真实网段）。

### 2.2 wireguard

| 文件 | 语义 | 写入者 |
|---|---|---|
| `wg0.conf` | PrivateKey/Address/ListenPort/Peer | driver 投影 |
| `AllowedIPs` | **只放对端 TUN /32**，不塞网段 | driver 投影 |
| `Endpoint` | 对端 `ip:port` | driver 投影 |

---

## 3. L6 系统写什么（`mynet apply`）

| 能力 | 命令面 | 实现 |
|---|---|---|
| OS 路由 | `apply --auto`（从 bundle）/ `apply <plan>` | `apply_plan.go` `RouteOp` |
| 防火墙（UDP listen、FORWARD） | 同上 | `FirewallOp` |
| NAT / MASQ | 同上 | `FirewallOp` |
| 策略路由/fwmark（旧 L6 proxy） | 同上 / `proxy_apply.go` | 独立，与 P2 并存时边界见 proxy-split |
| 清理 | `apply --clear` | 只清**隧道口**上的自有路由（所有权闸） |

**驱动投影**：`apply --active`（mode.json / intent / bundle → `driver/**`）。

### 3.1 三面语义（CLI 铁律）

| 面 | 命令 | 干什么 | 不干什么 |
|---|---|---|---|
| **投影** | `mynet apply --active` | 写 `driver/**` 配置 | 不装 OS 路由 |
| **系统** | `mynet apply --auto` | 装 OS 路由/防火墙/NAT | 不改 driver conf |
| **清理** | `mynet apply --clear` | 删自己装的隧道路由 | 不盲删目的地 |

`mynet service start|reload` 默认两者：**先 `--active` 再 `--auto`**（起隧道后必须装路由，否则「起来了但 ping 不通」）。

---

## 4. 判据表（症状 → 归谁修）

| 症状 | 归属 | 说明 |
|---|---|---|
| `ping VIP` 走 LAN 旁路 / 物理网关 | **L6** | `ip route get` 看 dev；缺 `/32` → `apply --auto` |
| 对端 `ip route get 本节点` 走 AWS/默认网关 | **L6 回程** | 188→181 实测 |
| ping 通、SSH/HTTP 不通 | **L5 对等** | 先 `config parity`（zip/crypto），再查 MTU |
| Lightsail 开了仍不通 | **云 + L6** | 云侧 UDP + 机内 nft/iptables 两层 |
| 隧道 up 但 in/out 皆 0 | **L5 地址/密钥** 或 **L6 路由** | 对照 A–E |
| 同机双实例 NAT 互抢 | **测试环境** | netns 或真双机 |
| `route.conf` 几百条公网段 | **L5 违规** | 只做地址宣告 |
| 手改 `driver/**` 后又被覆盖 | **流程** | 只准改 `conf/**` 再 Apply |
| 183 全网 ping 不进 | **L6 + 系统防火墙** | Windows 入站/回程 |

---

## 5. gnb vs wireguard 对称表

| 能力 | gnb | wireguard | 写入者 |
|---|---|---|---|
| 对端地址 | `address.conf` | `Endpoint` | L5 |
| 目的归属 | `route.conf` | `AllowedIPs`（/32） | L5 |
| 准出/代理 | `proxy on` + `proxy.conf` | 无（策略路由） | 意图→L5 |
| OS 路由 | `apply --auto` | `apply --auto`（或 Table=off） | **L6 only** |
| 防火墙 UDP | L6 + 云 | L6 + 云 | L6 |
| NAT/MASQ | L6 | L6 | L6 |
| 热重载 | P1 热重启 | `wg syncconf`（语义不同，不可混） | driver |

---

## 6. 回程对称（E 步，高频翻车）

请求通了回程不对称 = 单向通。每对 peer 必须：

1. 本机 `ip route get <peer_vip>` → `dev gnb_tun*` / `wg0`
2. 对端 `ip route get <本机 vip>` → 隧道口
3. gnb：两端 `route.conf` 都有对方 VIP/网段
4. wg：`AllowedIPs` 含回程源
5. 防火墙：FORWARD/NAT 方向对称

**工具**：`mynet route doctor --json`（N4 落地后）自动跑十对矩阵。

---

## 7. 写入权门禁

| 规则 | 机制 |
|---|---|
| OS 路由/防火墙只有 `apply_plan.go` 写 | `scripts/check-route-writers.sh` |
| L5 文件只有 driver 投影写 | Apply diff 告警（C5） |
| 不按名字杀进程 | pidfile + cmdline（既有红线） |
| 云侧防火墙只人/脚本开 | 清单化，不进 agent |

---

**关联**：`spec/paths.md` §5 双层配置 · `spec/proxy-conf-design.md` ·
`research/linux-openwrt-fw-route/REPORT.md` · `node-connectivity-checklist.md`

## 8. Linux / OpenWrt 外部防火墙 reload 合同（2026-10-18）

目标是其他 OP / 软件执行防火墙服务的 reload/restart 后，由系统钩子自动重放 MyNet 规则。不是依赖操作方先调用 MyNet，也不通过周期性 apply 抢回规则。规则与系统操作归 Go 执行器，mynetd 不参与。

| 外部入口 | MyNet 恢复入口 |
|---|---|
| OpenWrt firewall / fw4 reload、restart | UCI script include，enabled=1、fw4_compatible=1，加载同一套产物 |
| Linux nftables.service start、restart、reload | 系统配置确实 include mynet.nft 时使用 drop-in；否则登记 ExecStartPost / 追加 ExecReload，保留系统原命令 |
| ufw start、reload、force-reload、restart | after.init 包装钩子，先执行原钩子，再重放；stop 保留配置；卸载恢复原文件 |
| firewalld.service start、restart、reload | 服务后置钩子重放；不主动 reload firewalld |
| firewall-cmd --reload / D-Bus reload | 执行器 mynet firewall watch 订阅 Reloaded 信号；独立 systemd 服务，无轮询；先订阅再初始重放 |
| netfilter-persistent start、restart、reload | 90-mynet 插件重放 nft 自有表，不保存整机 iptables 快照 |

每个入口只加载 scripts/*.nft，不调用完整 apply 或 firewall sync，避免递归 reload。基础规则、proxy 集合/规则与出口 WAN 边界用单次 nft include 事务重放；文件原子替换，失败向调用方返回。MyNet 停止保留配置与重放登记，卸载清理本实例登记/事件服务并还原 ufw 原钩子。没有可接入的系统加载面时明确告警。

### CPI-84：授权边界与 LAN 互访

- 未授权、撤销或 client-only 状态均保留隧道→WAN 的拒绝专表/链；不再靠删除专表表达“禁用”。平台即使保留宽 forwarding、NAT 或 conntrack accept，也不能绕过该拒绝。
- 开放许可允许 WAN 转发，白名单只允许解算到的源 CIDR；其余丢弃。IPv4/IPv6 的 nft 规则共用 inet forward 边界；iptables 路径仍按既有 IPv4 合同，不在本批扩展 ip6tables。
- WAN 来源为 detector 的默认接口及路由表中全部 IPv4/IPv6 默认出口。Linux detector 补采 IPv6 路由，避免独立 IPv6 出口漏出边界。缺少有效 WAN 事实或 WAN=本隧道时报错，不猜接口。
- LAN 例外只来自非本隧道的内核直连网络（scope link / proto kernel、无网关、非默认路由）。它们在授权边界内 return，交还平台原有 LAN 规则；不把经网关的远端路由或本隧道网络当作 LAN。因此单网卡旁路由同接口上的 LAN 互访也保留。
- OpenWrt 首装仅建立 LAN↔MyNet；mynet→wan forwarding 随有效 server 许可增删，重复同步幂等。常驻拒绝专表仍防止第三方恢复旧宽 forwarding。
- firewall sync 自身也生成当前授权/拒绝产物；无对端/无普通路由的 apply 仍安装授权边界，service start 先 apply 再同步钩子。禁用 client 分类器会删除旧集合/规则产物，WAN 拒绝产物保留。

### 验证与边界

隔离 Linux 容器内用真实 nft/iptables 与 netns/veth 发包：未授权、开放、白名单命中/拒绝、撤销、独立 LAN 与同网卡 LAN 均验证；故意保留平台宽 ACCEPT/NAT/established 规则。清空 nft ruleset 后执行真实 include，WAN 拒绝及 LAN 互访恢复。另用隔离 D-Bus 服务发 firewalld Reloaded 信号，验证独立 watcher 重新装入已删的表。

Go 行为回归覆盖钩子顺序、原 ufw 钩子恢复、错误传播、默认出口与直连网段解算；OpenWrt 构建标签下在 Linux 容器实际运行 UCI 替身验证授权 forwarding 增删/幂等。它不等于实际 OpenWrt 固件或完整 Linux 发行版的服务验收；生产未部署，M4/M6 不勾。

本合同不要求对绕过服务、单独裸 flush 后不触发任何加载事件的行为轮询自愈；下一次正常服务加载仍由以上钩子恢复。真实发行版升级覆盖钩子、策略路由的额外出口表、IPv6 数据流量和 iptables-legacy/固件版本仍须按部署环境验收。doctor 的基础专表检查不替代逐项 ACL/集合与流量验收。
