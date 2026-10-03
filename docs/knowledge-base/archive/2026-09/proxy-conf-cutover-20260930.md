# proxy.conf 新管理方案切换 + 0.2↔188 排障实录

> 2026-09-30 · 192.168.0.2（ImmortalWrt，node `8283289760467957`）↔ xiawo-gnb-e1（`7676068715291109`，18.141.199.14）
> 关联：`node-connectivity-checklist.md` A–E · `spec/proxy-split.md` · `mynet_gnb/packages/1.6.8-c411f14ee647/p2-route-proxy/`

---

## 0. 叙事转向（本次最重要的结论）

**旧方案（已废弃）**：靠脚本把「几百条公网 `/8` + `/16`」**注入 gnb `route.conf`**，
用 gnb 的 route 表做代理分流。`proxy.lua` 的 `route_inject()` / 旧 fresh 就是干这个的。

**新方案（现行）**：

| 层 | 职责 | 落点 |
|---|---|---|
| **P2 数据面** | gnb 对**未命中 `route.conf`** 的目的，按 `proxy.conf` 选出口 | `conf/<nodeid>/proxy.conf` |
| **L6 策略路由** | 系统决定哪些流量进隧道；`nft set` + fwmark + `mynet_proxy` 表 | `route_policy.sh` |
| **`route.conf`** | **只做地址宣告**（overlay `/32` + 真实对等网段），**不再塞代理公网段** | bundle 渲染 |

**因此各端配置生成的核心目标只有一个：准确写出 `proxy.conf`（client/server/both + `[default]` 出口与权重）**。
旧的「往 route.conf 注入」逻辑要**跟着退场**——不是修修补补，是叙事变了：

- `route_inject()` → **只清不注**（剥离历史 marker 段，不再追加）
- `append_public_routes()` → 可删（或仅留测试用）
- `proxy.sh` 生成 `proxy_route.conf` / `# Gateway:` → 与 P2 并存的 L6 路径，也要对齐「不写 gnb route.conf」
- 验收标准：`route.conf` 行数 ≈ 节点数（overlay /32 + 少量真实网段），**无 `#----proxy` marker 段**

`proxy.conf` 语义（P2 补丁，见 `p2-route-proxy/README.md`）：

```ini
role client          # 必填：client|server|both
proxy on             # 准出总开关
# ingress_open on    # 仅 server/both；client 默认 off

[default]            # 无 target 命中时的兜底
exit <uuid> w=10     # 同层加权；order=n 为故障转移层
exit <uuid> w=10

[group name]         # 可选：按目的地区域分流
target 8.8.8.0/24    # /0 拒载
exit <uuid> w=20 order=1
```

匹配：组内最长前缀 → 组间最长前缀 → `[default]`。`route.conf` 命中**优先于** proxy 策略。

---

## 1. 本次落地配置（0.2 为 client，双出口均权）

```ini
# /etc/mynet/driver/gnb/conf/8283289760467957/proxy.conf
role client
proxy on

[default]
exit 4997342800601732 w=10    # 新加坡 180
exit 7676068715291109 w=10    # xiawo-gnb-e1 188
```

188 侧（出口机）：

```ini
role server
proxy on
ingress_open on
```

验收：`0.2 → 10.182.236.188` 稳定 **83ms**，`0.2 → 10.182.236.180` **84ms**，双向通。

---

## 2. 中间问题全录（按类别）

### 2.1 查错现场（最耗时）

| # | 症状 | 根因 | 处置 |
|---|---|---|---|
| 1 | `gnb_ctl -b conf/…/gnb.map` 只有 17 节点，188/658289 不在表 | **`--systemd` 忽略 `ctl-block`**，live map 在 `/tmp/gnb.9001.map`；conf 目录 `gnb.map` 是旧残留，mtime 不再更新 | 一律 `gnb_ctl -b /tmp/gnb.9001.map`。真相：**19 节点、188 一直在表里** |
| 2 | `strings gnb.map \| grep <nodeid>` 恒 0 | nodeid 是 uint64 二进制，不是 ASCII | 只信 `gnb_ctl -s` 的 `^node ` 行 |
| 3 | back 查 `nodes.id` / `App\Models\Node` 报错 | 列名是 `node_id/node_name/node_ip(int)`；`node_ip` 存整数（`179760316`→10.182.236.188） | `DB::table('nodes')` + `long2ip` |

### 2.2 配置链路断裂

| # | 症状 | 根因 | 处置 |
|---|---|---|---|
| 4 | 188 `address.conf` 始终 `p\|`，back 代码已是 `'role'=>'n'` | `ConfigRevision.envelope_json` **落库快照没重建**；改代码 ≠ 改已发布修订 | `BundleInvalidator::republishMany([...])` 重建后 role 才变 `n` |
| 5 | 188 `refresh-*` 报 `bundle.json not found`；mynetd 刷 `system.json 缺失` | 单元 `MYNET_HOME=/opt/mynet`，手动 `mynet` 默认落 `/root/.mynet` | 一律 `sudo MYNET_HOME=/opt/mynet /opt/mynet/bin/mynet …` |
| 6 | `route.conf` 1461 行，`#----proxy start----` 灌了两个 peer 各 700+ 条 `/8` | **旧 fresh 的 `route_inject`**：用 route.conf 做 L6 分流（见 §0 已废弃） | 剥 marker 段（1461→28）；`route_inject` 改为只清不注 |
| 7 | `proxy.sh` 的 `# Gateway:` 只写 first_peer | `proxy.sh:51-53` 取 `PROXY_PEERS%%,*` | **未修**（手改 env 顶着） |

### 2.3 接口名 / 路由

| # | 症状 | 根因 | 处置 |
|---|---|---|---|
| 8 | ifname 改成 `gnb_tun_9016` 被纠正 | **旧配置才是权威**：备份 `mynet-home.tgz` 里是 `ifname gnb_tun_16`（0.2 上 `gnb_tun` 另有用途） | 统一 `gnb_tun_16`：`node.conf` / `mynet.conf VPN_INTERFACE` / uci `network.gnb_tun.device`+`network.mynet.device` / `route.conf` / `route_policy.sh` / `firewall.mynet*` |
| 9 | 改名后 180/188 **都**不通，`ip route get` 指到 `br-lan` | `mynet apply` 部分失败 + `route.mynet` 缺 `MYNET_HOME` FATAL，27 条 overlay 路由没装 | `route.mynet start --config … --interface gnb_tun_16 --mynet-home /etc/mynet` |
| 10 | 0.2↔188 全尺寸 ping 丢；gnb 层却 Direct P2P / 85ms | **188 回程缺失**（A–E 的 E）：188 上无 `10.182.236.0` overlay 路由 | 188 上 `ip route replace 10.182.236.182/32 dev gnb_tun` → 双向 83ms |

### 2.4 防火墙 / 策略路由

| # | 症状 | 根因 | 处置 |
|---|---|---|---|
| 11 | `mynet_proxy` 集合 `17.235.168.0-23.133.187.255` **覆盖 18.141.199.14** | `exclude_ips` 建了但从不灌元素；代理出口公网 IP 落在 CNIP 大段，会被 mark 0xc8 塞回隧道 | `route_policy.sh` 支持 `PROXY_PUBLIC_IPS`；`proxy.lua` 增 `collect_proxy_public_ips()`；现场 `nft add element exclude_ips { 18.141.199.14, 52.221.22.241 }` |
| 12 | 怀疑 gnb 底层 UDP 被 mark 打环 | 实测 conntrack `mark=0`；mark 只在 PREROUTING，本机 gnb UDP 不进。**不是** ping 不通主因，但 LAN 转发仍要 exclude | 同上（防御性） |

### 2.5 脚本 / 运行环境

| # | 症状 | 根因 | 处置 |
|---|---|---|---|
| 13 | `route_policy.sh` `line 116: unexpected redirection` | `<<<` 是 bashism；OpenWrt `sh`=ash 不认 | 必须 `bash route_policy.sh` |
| 14 | `xxd`/`timeout`/`comm`/`python3`/`stat -c` not found | BusyBox 精简 | `od` / `awk` / `cat /proc/…` |
| 15 | 188 mynetd `fork/exec mynet: exec format error` | 二进制本身合法 x86-64；疑似启动时旧文件/路径状态 | `MYNET_HOME` 对齐后 detector/metrics 正常；mynetd 需观察是否复发 |
| 16 | `gnb.log` 被 `option '--pf-route' requires an argument` 刷屏 | 有脚本带残缺 `--pf-route` 拉起 gnb | **未修**，待定位调用方 |

### 2.6 SSH / 登录

| 项 | 答案 |
|---|---|
| 188 登录 | `ssh -i ~/.ssh/Aws.pem admin@18.141.199.14`（root publickey 拒） |
| 0600 私钥/配置 | admin 读不了，要 `sudo cat` |
| 180 SSH | 走 `52.221.22.241`，别走 `10.182.236.180` |
| GitHub | `export https_proxy=http://127.0.0.1:7890` |

---

## 3. 判断失误（自纠，勿重复）

1. **「188 未上线」错** —— `last_seen=null` 是 back 心跳/上报未写，不是节点不在。本机 ping 188 稳定 90ms 是对的。
2. **「188 不在 gnb 节点表」错** —— 查了 stale map；live map 里 19 节点都在。
3. **「密钥交换有问题」错** —— 两端 pub 一致（`1acf8dcc…` / `bbd610cb…`），B 一直 PASS。
4. **「ifname 叫 gnb_tun_9016」错** —— 必须以旧配置为准：`gnb_tun_16`。

---

## 4. A–E 五步终态（0.2 → 188）

| 步 | 状态 | 证据 |
|---|---|---|
| **A 软件+配置** | ✅ | conf 齐：`node/address/route/proxy/security/ed25519` |
| **B 密钥** | ✅ | `ed25519/767606…public = bbd610cb…`，与 188 自身 pub 一致 |
| **C 邻接宣告** | ✅ | `address.conf: n\|7676068715291109\|18.141.199.14\|9016`（role 已是 `n`） |
| **D 系统路由** | ✅ | `ip route get 10.182.236.188 → dev gnb_tun_16` |
| **E 防火墙+回程** | ✅ | ping 2/2，83.1ms；188 侧补 `/32` 回程 |

---

## 5. 欠账清单（按优先级）

| # | 项 | 优先级 | 说明 |
|---|---|---|---|
| 1 | **0.2 同步新 `proxy.lua`**（`route_inject` 只清不注）+ post_start 强制 bash | **高** | 否则旧 fresh/post_start 会再灌 route.conf |
| 2 | 各端配置生成器收敛到 **`proxy.conf` 准确生成** | **高** | §0 叙事转向的落地；client 双出口/分组、server `ingress_open` |
| 3 | `proxy.sh` 多网关 `# Gateway:` 只写 first_peer | 中 | 与 P2 并存的 L6 路径 |
| 4 | 188 overlay 路由持久化 | 中 | 现在是手 `ip route replace`，要进 `route.mynet`/bundle 渲染 |
| 5 | BundleInvalidator 补「新建节点/IP 变更」触发 | 中 | 现只在密钥变更时重建 envelope |
| 6 | `--pf-route` 调用方定位 | 低 | gnb.log 噪声 |
| 7 | 188 mynetd `exec format error` 观察 | 低 | 复发再查 |

---

## 6. 快速命令备忘

```bash
# live map（不是 conf 目录那份）
gnb_ctl -b /tmp/gnb.9001.map -s

# 188
ssh -i ~/.ssh/Aws.pem admin@18.141.199.14
sudo MYNET_HOME=/opt/mynet /opt/mynet/bin/mynet detector --force

# 0.2 装 overlay 路由（必须带 MYNET_HOME）
sh /etc/mynet/scripts/route.mynet start \
  --config /etc/mynet/conf/route.conf \
  --interface gnb_tun_16 --mynet-home /etc/mynet

# L6 策略路由（必须 bash）
bash /etc/mynet/scripts/proxy/route_policy.sh start

# 剥 route.conf 里的旧注入段
awk '/^#----proxy start----/,/^#----proxy end----/{next}
     /^#----proxy-server start----/,/^#----proxy-server end----/{next}
     {print}' route.conf | grep -v '^# Peer: ' | grep -v '^# Injected'
```
