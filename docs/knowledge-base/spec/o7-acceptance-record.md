# O7 验收记录（隔离平台 · 资源与行为）

> 2026-10-11。工作包 O7（playbook H5）。**范围**：可复现出包外的运行面验收 —— 资源采集 + 行为探测。
> **两个平台面**（MT7621/mipsle 弱设备按用户指示**本轮搁置**）：
> - **amd64**：`.182` ImmortalWrt 24.10 **x86_64**（1.0 GB RAM）
> - **arm64**：本机 QEMU VM，OpenWrt **24.10.1** armsr-armv8（**233 MB RAM**，2 vCPU，HVF）
>
> ⚠️ **QEMU 结果只证明该环境**，不能代替 MIPS 实机资源证据（playbook §7 G5）。

## 1. 资源采集（idle）

| 指标 | amd64 `.182` | arm64 VM |
|---|---|---|
| mynetd 常驻 RSS | **12.9 MB** | **~7.0 MB** |
| gnb（P2）常驻 RSS | **9.3 MB** | **11.3 MB** |
| idle CPU（2s Δ） | gnb 0.1% / mynetd 0.0% | ≈0% |
| mynetd 重启 → localapi 就绪 | **50 ms** | **40 ms** |
| /etc/mynet 占用 | 11.9 MB | 5.4 MB |
| rootfs | 364.6 M / 1.3 G（28%） | **74.5 M / 98.3 M（77%，仅 21.7 M 余）** |
| RAM 总量 | 1000 MB | 233 MB |

**读法**：单节点常驻（mynetd+gnb）≈ 17–19 MB RSS；idle CPU ≈ 0；mynetd 冷启到 localapi 就绪 =**数十毫秒**。
**关键约束**：arm64 小固件（98 MB rootfs）**仅剩 21.7 MB** —— 升级/迁移的**临时空间余量小**，装 ipk + 解包必须留量（O7 要求采集的「安装空间/临时空间」即此）。

## 2. 行为探测

| 项 | amd64 `.182` | arm64 VM | 结论 |
|---|---|---|---|
| gnb 运行 + tun UP | gnb_tun_16 UP | gnb_tun UP | ✅ |
| localapi `/api/v1/status` | 200 | 200 | ✅ |
| **崩溃→procd 恢复** | `kill -9 mynetd` → **1s 内新 PID**，≈2s localapi 200 | 同，≈0.5s localapi 200 | ✅ **procd 唯一控制者生效**，无需 Lua 兜底 |
| 桥读方法（ubus） | `mynet.status`/`runtime` 真实 data | — | ✅ |
| **fw4 reload 回放** | — | `/etc/init.d/firewall reload` 后：zone `mynet` 保留、include 重新应用、nft `forward` mynet 行在、tun 路由保留、gnb 在、SSH 会话未断 | ✅ **回放不丢自身规则** |
| **O1 修复（arm64 复核）** | — | 升级前 `config/runtime` = 旧 Lua 桩（`{interface,node_id,running,vpn_type}`）→ 装新 mynetd 后 = **真实 Go 形状**（`address_conf`/`gnb_conf`/…） | ✅ **config 委派在 arm64 同样生效** |
| **升级 → 回滚（二进制级）** | — | arm64 VM：装新 `mynet/mynetd`（+新 init/env）→ 验证 → 回滚旧件 → md5 复原、`nodeid=3764021915150630`/`ifname=gnb_tun`/`listen=9002` **不变**、`gnb_tun` UP | ✅ **身份/接口不因换件漂移** |
| **并发护序（localapi 写面）** | 两个写请求**同时**打 `/api/v1/config/apply` → 一个 **200**、一个 **409** `operation_in_progress` | arm64 VM：慢写（`service start`）进行中 → `config/apply` 写=**409**；**`plugin/proxy` 写（修复前）=500（照跑，未加锁）→ 修复后 =409**，同刻 proxy **读=200** | ✅ **全部写面已归一到 `configMutation`**（进程内；跨进程事务未被证明） |
| 会话态 ACL 正/负 | 16 方法 `access=true`；越权 `-32002` | — | ✅（O2 记录） |
| 浏览器 UI 走桥 | Dashboard + Restart→「VPN restarted」 | — | ✅（O5 记录） |
| **opkg 事务下配置/身份不漂移** | — | arm64 VM：`opkg install --force-reinstall mynet_agent_openwrt_arm64_v0.0.0-6537ac7.c495ff6.ipk`（走 prerm `mynet uninstall` → postinst `mynet install`）→ `/etc/mynet/conf/config.json` sha256 **前后一致**（`79c14f2e…`）、`confdir`/nodeid `3764021915150630` 不变 | ✅ **opkg 事务不改节点身份/配置**（conffiles 生效） |
| **gnb 就位检查误报（已修）** | — | 每次 install/opkg 升级都报 `/usr/local/bin/gnb 不存在 —— mynet 服务会起不来`，而 ipk 的 gnb 在 `$home/driver/gnb/bin`；改为 `gnbPresentOpenWrt`（同 init 解析序）→ `mynet_agent 4a85820` | ✅ 修复（假告警，非功能故障） |

**踩坑**：OpenWrt 上覆盖**正在运行**的可执行文件会 `Text file busy`（ETXTBSY）。升级/回滚要**写临时文件再 `mv`**（换 inode），不能 `cat >` 原地覆盖 —— 与本包 `mynet install`/opkg 的落盘路径需同口径。

**并发护序审计（2026-10-11，`mynet_agent a5ba1d0`）**：逐端点核过全部写面 —— `config/*`（mode/active/keys/connect/prepare/apply）、`nodes/switch`、`plugin/proxy` 走 `withConfigMutation`；`/api/v1/command`（CLI/LuCI 经桥）走 `dispatch` 的 `isContextMutation`。**发现并修两处漏网**：① `POST /api/v1/plugin/proxy` 直调 `s.Ops`（不经 dispatch），此前**完全未加锁** —— VM 实测慢写进行中它照跑（`500`）；改 `withConfigMutation` 后 =`409`。② **启动隧道恢复**（`cmd/mynetd restoreTunnelIfNeeded` 拉起 gnb|wg）未加锁，与用户写并发会同抢驱动 → 新增 `Server.TryRunExclusive`（`TryLock`，拿不到即跳过、以用户操作为准）纳入互斥。回归测试 `TestPluginProxyPostSharesConfigMutationLock`（已反向验证会失败）/`TestTryRunExclusiveSkipsWhenMutationInFlight`。**注**：设备证据来自本机交叉编译的 arm64 件（未发布提交），验后 VM 已用 ipk 还原到 r175（`mynetd` sha `a2538371…` 与 ctl 件一致）。

## 3. 未做（O7 剩余，需更多时间/窗口/实机）

- **数据面允许/拒绝**：授权流量走隧道、未授权拒绝、**撤销后 fail-closed**（不赖回滚旧 bundle 重开）—— 需出口侧（`.180`，生产 proxy server）配合
- **升级回滚（opkg 级）**：装**新 ipk** → `opkg` 回滚旧 ipk。**现状**：opkg **事务**面已验（reinstall 下 config/身份不漂移，见 §2）；**纯降级回滚**（装旧版本 ipk 回到旧件）仍缺 —— 现网无「旧版本 arm64 ipk」可直接装（VM 原为 `-dirty` 本机构建，ctl 上无对应件）。
- **WG 独立验收**（借 GNB 通过不算）
- **MIPS 实机资源证据**（本轮搁置）
- ~~并发护序~~（2026-10-11 完成，见 §2 + 审计）

> O7 门禁要求：以上每项须有**实际证据**，不能用「ping 通 / 服务 running / 规则里搜到字符串」代替（playbook §7 G3）。
