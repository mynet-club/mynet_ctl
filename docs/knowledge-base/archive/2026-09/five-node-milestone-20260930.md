# 五节点全平台联调里程碑（2026-09-30）

> **地位**：阶段性汇总。回答「现在网里有什么、验过什么、坑在哪」。
> **不替代**踩坑明细（`session-pitfalls-20260930.md` / `p2-route-proxy/PITFALLS.md`）与
> 连通模型（`node-connectivity-checklist.md`）——本文只做**归纳与索引**。
>
> 关联：`prod-cutover-20260926.md` · `proxy-conf-cutover-20260930.md` ·
> `p2-route-proxy/{FULL-FLOW,UPGRADE-180,TEST-REPORT}.md` · `research/linux-openwrt-fw-route/`

---

## 1. 五节点矩阵（实测在运）

| VPN IP | node_id | 系统 | 角色 | 备注 |
|---|---|---|---|---|
| `10.182.236.180` | `4997342800601732` | **Linux**（Debian / Lightsail `Mynet`） | 生产出口 + index + proxy server | api.mynet.club 共置机；SSH 走公网 `52.221.22.241` |
| `10.182.236.181` | `3764021915150630` | **macOS** | 在运节点 | 本机；私钥在 `/usr/local/opt/mynet/.../security/` |
| `10.182.236.182` | `8283289760467957` | **OpenWrt**（ImmortalWrt 24.10 @ `192.168.0.2`） | 网关/分流 | ifname **`gnb_tun_16`**；旧 L6 `route_policy.sh` 与 P2 并存 |
| `10.182.236.183` | `8107260975133902` | **Windows**（10 LTSC 19044） | 在运节点 | wintun；SSH 用 `powershell -NoProfile -File` |
| `10.182.236.188` | `7676068715291109` | **Linux**（Lightsail `xiawo` / `18.141.199.14`） | proxy 出口 + 测试床 | claim 流程实测机 |

**覆盖面**：Windows / macOS / Linux / OpenWrt 四类系统（Linux 双节点：生产 + 出口）。
**拓扑验收**：三节点 mesh 全通（181 / 180 / 188）；0.2↔188 双向 83ms（`proxy-conf-cutover-20260930.md` §1、§4）。

---

## 2. 本轮验过的能力

### 2.1 P1 热重启（`p1-hot-restart-v2`）

| 项 | 状态 | 证据 |
|---|---|---|
| `gnb_ctl -w core.flag=2` / `SIGHUP` | ✅ 补丁已进 packages | `packages/.../p1-hot-restart-v2/`（5 补丁） |
| PID 不变、继承 TUN + UDP socket | ✅ 实测 | `spec/driver-abstraction.md` D1 |
| 前置：`gnb`/`gnb_ctl`/`gnb_es` **同一构建** | 硬约束 | 版本混用 = 热重启语义不可信 |

### 2.2 P2 proxy 插件（`p2-route-proxy` / `proxy.conf` v3）

| 语义 | 判据 | 结果 |
|---|---|---|
| `route.conf` 命中优先 | off 时登记目的仍通 | ✅ CASE2 |
| 未登记目的走 `[default]`/`[group]` exit | NAT 1.1.1.1 4/4 · 87ms | ✅ CASE6 |
| 同层 `w=` 加权 | 15 次 6:9 | ✅ |
| `order=` 降层 / 回切 | block + PONG 过期 ~75s | ✅ |
| flow 粘性 / TCP 跨热更 | 连接不断 | ✅ CASE3 |
| 分片冲表 | 不崩 | ✅ CASE5 |
| 跨平台构建 | Darwin / MinGW / Linux / OpenWrt×3 | ✅ 全过 |
| 单测 | 52/52 | ✅ |

**叙事已转向**（勿回退）：代理分流在 **`proxy.conf`（P2 数据面）**，不再往 `route.conf` 注入公网段。
`route.conf` **只做地址宣告**（overlay `/32` + 真实对等网段）。详见 `proxy-conf-cutover-20260930.md` §0。

### 2.3 节点身份（正确流程，已跑通）

```
auth login → 取身份 → 本地 keygen → 只上传 public → bundle/publish → 渲染驱动配置
```
不是「back 生成私钥下发」。实现：`mynet auth login` + `mynet node claim <id>`（`FULL-FLOW.md` §5）。

---

## 3. 坑的归纳（按你点名的四域）

> 明细 45+ 条见 `session-pitfalls-20260930.md` 与 `p2-route-proxy/PITFALLS.md`。
> 此处只抽**结构性根因**——同类症状会换皮再来。

### 3.1 整体构建

| 根因 | 症状 | 规矩 |
|---|---|---|
| 入口搞错 | `go build ./mynet` 产出 ar 库 | 一律 `go build ./cmd/mynet`（PITFALLS #7） |
| 平台类型泄漏进跨平台文件 | Windows 构建直接炸（`factory_other.go` 引用 Darwin 专用类型） | 平台类型藏在同 build tag 的注册函数后 |
| `syscall.Kill` / `os.Geteuid` 平台差异 | Windows 恒 -1 / 符号不存在 | `pid_unix.go`/`pid_windows.go` 拆分；不拿 euid 当 root |
| Windows 无 `.exe` 后缀查找 | mynetd 永远找不到 mynet → 500 | 同目录查找必须带 `.exe`（且 `api.Ops` 覆盖处两处同改） |
| CI 跨私有仓 | `GITHUB_TOKEN` 不能跨仓 checkout | `secrets.GH_PAT` |
| strip 后 `go tool nm` 失效 | 校验失败误报 | `strings \| grep <symbol>` |
| Makefile 吃 `$()` | `$(basename …)` 被 GNU make 解析 | 用 `sed` 截路径 |
| 产物与 ctl 索引脱节 | `PRODUCTS` 白名单曾无 `agent` | 分发链两端契约同改；测试打真端点（D2） |

**构建矩阵（2026-09-30 实测）**：Darwin arm64 / MinGW x86_64 / Linux glibc / OpenWrt x86-64·armv8·mipsel —— gnb 四程序全过（`TEST-REPORT.md` §5）。

### 3.2 安装 OpenWrt / Linux 防火墙脚本

| 根因 | 症状 | 规矩 |
|---|---|---|
| **两层防火墙** | Lightsail 开了仍不通 / tcpdump 0 包 | 云安全组 **和** 机内 nft/iptables **都要放**；对照 `node.conf` 的 **listen** 开，别只开测试口（#21–23、UPGRADE #5/6/13） |
| OpenWrt = **fw4/nft**，无 iptables | 自定义 iptables 静默失效 | `nft insert rule inet fw4 …` / `/etc/nftables.d`；`firewall.user` 要 `fw4_compatible 1` |
| 装 iptables 拉到 legacy | 与 fw4 双栈冲突 | 要装也是 `iptables-nft`；**不可混用 nft 与 legacy xtables** |
| `firewall.mynet install` 要 `MYNET_HOME` | zone 没建上 | 调脚本必带 env；OpenWrt 服务前置走 `setupOpenWrtNetworkZone`（`cmd_install_openwrt.go:169-181`） |
| `route_policy.sh` 含 bashism（`<<<`） | OpenWrt `sh`=ash 报 `unexpected redirection` | **`bash route_policy.sh`**，或去 bashism |
| BusyBox 精简 | `xxd`/`timeout`/`comm`/`python3`/`stat -c` not found | `od`/`awk`/`cat /proc/…`；BSD 回退 `stat -f` |
| `fw4 reload` 可挂死（QEMU） | 阻塞 stop/HTTP | 同步 reload 不得进关键路径；能不 reload 就不 reload |
| 回程不对称 | 单向通；握手有、ping 不通 | E 步：对端 route.conf / OS 路由对称（188 曾缺 `/32` 回程，`proxy-conf-cutover` #10） |
| fwmark + strict `rp_filter` | 策略路由莫名断流 | `rp_filter=2` 或 `src_valid_mark=1`（research REPORT §2） |
| 代理出口 IP 落在 CNIP 大段 | 被 mark 塞回隧道打环 | `PROXY_PUBLIC_IPS` / `exclude_ips` 要**灌元素**，不是建了就完（#11） |
| `create_vpn_zone` 写死 `masq=1` | 抹掉真实源 IP | 默认 masq=0，按方向性 SNAT（research REPORT §8） |
| 插在 `inet fw4` 链的规则 | fw4 重启/升级即丢 | 落 `/etc/nftables.d` 或 `fw4_compatible` 持久化 |

### 3.3 配置同步

| 根因 | 症状 | 规矩 |
|---|---|---|
| **bundle 是快照** | 改了 `nodes.node_ip` / 公钥 / role，节点仍旧值 | 改完必须 **`POST /nodes/{id}/bundle/publish`**；claim 流程强制 publish（#13/#14） |
| 改代码 ≠ 改已发布修订 | 188 `address.conf` 一直 `p\|` | `BundleInvalidator::republishMany` 重建 envelope（`proxy-conf-cutover` #4） |
| `refresh-config` 不拉新 | 只渲染本地旧 bundle | **`mynet config pull`** 才下载（#12） |
| 渲染器写死 | 生产 `listen 9180`/`gnb_tun_180` 被盖成 9016/`gnb_tun` | 渲染读 `node_configs.config.{ifname,listen}`（#9，已修） |
| 云端 bundle **不下发 zip/crypto** | 大包/TCP 静默丢 | 对等项靠本地约定 / `mynet config parity`；两端 `zip auto`+`zip-level -1` |
| 密钥路径租户级 vs 节点级 | `node.private: no such file` | `utils.ResolveKeysDir(src, nodeID)`（#6，已修） |
| 两套 conf 树并存 | `/opt/mynet`、`/usr/local/opt/mynet`、`/var/lib/mynet` | `mode.json` 指 active source；**私钥在 `var/lib/mynet`** |
| `MYNET_HOME` 不一致 | 单元读 `/opt/mynet`，手敲落到 `~/.mynet` | 一律 `sudo MYNET_HOME=… /path/to/mynet …` |
| 配置生成漏键 | 「旧版一直好的」被破坏 | 先 diff `node.conf` 与历史可用，再怀疑二进制 |
| IP 池双占 | 184 双占 | 分配器不校验 `nodes.node_ip` 实占（CPI）；改 IP 要 `nodes` + `node_ips` + publish 三处 |

**配置链五步**（claim/sync 的正规顺序，`FULL-FLOW.md` §5）：
`back-face-key → 本地 keygen → PUT keys(public) → bundle/publish → download → 渲染 driver 配置`。

### 3.4 更新联动

| 根因 | 症状 | 规矩 |
|---|---|---|
| **`MYNET_GATEWAYS` 只认环境变量** | 心跳/WS/自动拉 bundle **全不启动** | systemd unit / launchd plist / `mynetd.env` 必须写入；无回退（#16） |
| 设计不对称 | proxy 回写装上了、心跳没起 | proxy 回写回退读 `agent.json.gateway`，心跳不回退——统一以 env 为准 |
| procd `env` 未整份加载 | HOME/GATEWAYS 丢 | `mynetd.init` 要整份加载 `mynetd.env` |
| status 不靠直连 DB | 停在 1 不到 2 | 走心跳 API；写库绕过审计与刷新 |
| 响应性联动曾断裂 | 节点上线不触发对端 `route.refresh` | `RelatedNodeRouteRefresher` 状态跃迁入队 `mynet.route.update`（`spec/control-plane.md` §6.3） |
| BundleInvalidator 触发面窄 | 新建节点/IP 变更不重建 | 现只在密钥变更触发（`proxy-conf-cutover` 欠账 #5） |
| `apps_index_url` 历史分裂 | Go `download.mynet.club` vs OpenWrt `ctl.mynet.club` | 统一 **`ctl.mynet.club`**（E-9）；两端契约同改 |
| token 短效 | `config pull` / refresh 401 | 关键操作前现发 token；或 0600 `credential.json` |
| 升级替换自更新 | 主程序不能替换自身 | `mynet-updater --full-update` 原子替换 + 失败回滚（`mynet.md` §upgrade） |
| `mynet install` 不拷二进制 | 与包管理打架 | 安装包（ipk/tgz）放文件；`install` 只装服务（SESSION_NOTES §1.2） |
| `--gnb-bin` 不进 `bin/` | 驱动二进制缺失 | 另放驱动二进制（FULL-FLOW #18） |

---

## 4. 红线（汇总，先于一切操作）

1. **工作节点动配置前必须备份**（192.168.0.2、10.182.236.180）。
2. **私钥绝不重生成**——只导入；公钥已扩散全网 `ed25519/`。
3. **私钥备份必须含 `/var/lib/mynet`**（生产私钥不在 `opt/mynet`）。
4. **直连 DB 只诊断**；写走 API。
5. **禁止按名字杀进程**（pidfile + cmdline）。
6. **A–E 五步**是互通唯一判据；密钥一致 ≠ 数据通。

---

## 5. 排障速查（五步 → 症状）

| 先查 | 对应 |
|---|---|
| A–E（软件 → 密钥 → 宣告 → 路由 → 防火墙/回程） | `node-connectivity-checklist.md` |
| 密钥五方比对 | 生产原件 / mynetd 树 / 对端 ed25519 / back / bundle |
| 配置被覆盖 | 渲染器写死？bundle 旧修订？`config pull` 了吗？ |
| 心跳/WS 不起 | `MYNET_GATEWAYS` 在不在 env？ |
| 端口不通 | 云安全组 **和** 机内 nft 两层；对照 listen |
| ping 通 SSH 不通 | zip 对等（`config parity`） |
| 路由走错 | `ip route get` + `apply --auto` |
| 测试判据 | flow 粘性换目的 IP；降层等 PONG ~75s |

---

## 6. 未闭环（诚实清单）

| # | 项 | 优先级 |
|---|---|---|
| 1 | 0.2 同步新 `proxy.lua`（`route_inject` 只清不注） | 高 |
| 2 | 各端配置生成器收敛到 **`proxy.conf` 准确生成** | 高 |
| 3 | BundleInvalidator 补「新建节点 / IP 变更」触发 | 中 |
| 4 | 188 overlay 路由持久化（现为手 `ip route replace`） | 中 |
| 5 | `proxy.sh` 多网关 `# Gateway:` 只写 first_peer | 中 |
| 6 | `firewall.sh` `masq=1` 写死 → 方向性 SNAT | 中 |
| 7 | `route_policy.sh` 对 `inet fw4` 的持久化（25.12 路径未验） | 中 |
| 8 | Windows 服务注册 / 防火墙探测仍 stub；无 .msi | 中 |
| 9 | 真 wg 隧道实测（架构已支持） | 中 |
| 10 | `--pf-route` 残缺参数调用方定位；188 `exec format error` 观察 | 低 |

---

## 7. 文档索引

| 要什么 | 去哪 |
|---|---|
| 45 条踩坑明细 | `session-pitfalls-20260930.md`（知识库）· `p2-route-proxy/PITFALLS.md`（包内副本） |
| proxy 切换叙事 + 0.2↔188 实录 | `proxy-conf-cutover-20260930.md` |
| 180 升级手册 | `p2-route-proxy/UPGRADE-180.md` |
| 全量 runbook | `p2-route-proxy/FULL-FLOW.md` |
| proxy 测试矩阵 | `p2-route-proxy/TEST-REPORT.md` |
| 连通模型 / Q&A | `node-connectivity-checklist.md` |
| 防火墙/策略路由研究 | `research/linux-openwrt-fw-route/REPORT.md` |
| 0.2 升级与 OpenWrt 集成 | `mynet_openwrt/docs/SESSION_NOTES_20260930.md` |
| 正式发布切换 | `prod-cutover-20260926.md` |
