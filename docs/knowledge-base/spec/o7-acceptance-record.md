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
| **并发护序（localapi）** | 两个写请求**同时**打 `/api/v1/config/apply` → 一个 **200**、一个 **409** `operation_in_progress` | — | ✅ **进程内 configMutation 挡得住**（注：仅进程内；跨进程事务未被证明） |
| 会话态 ACL 正/负 | 16 方法 `access=true`；越权 `-32002` | — | ✅（O2 记录） |
| 浏览器 UI 走桥 | Dashboard + Restart→「VPN restarted」 | — | ✅（O5 记录） |

**踩坑**：OpenWrt 上覆盖**正在运行**的可执行文件会 `Text file busy`（ETXTBSY）。升级/回滚要**写临时文件再 `mv`**（换 inode），不能 `cat >` 原地覆盖 —— 与本包 `mynet install`/opkg 的落盘路径需同口径。

## 3. 未做（O7 剩余，需更多时间/窗口/实机）

- **数据面允许/拒绝**：授权流量走隧道、未授权拒绝、**撤销后 fail-closed**（不赖回滚旧 bundle 重开）—— 需出口侧（`.180`，生产 proxy server）配合
- **并发**：LuCI 写 + CLI + 后台配置通知 + 启动恢复同时进行的护序
- **升级回滚（opkg 级）**：装**新 ipk** → `opkg` 回滚旧 ipk（本轮只到**二进制级**，未过 opkg 事务）
- **WG 独立验收**（借 GNB 通过不算）
- **MIPS 实机资源证据**（本轮搁置）

> O7 门禁要求：以上每项须有**实际证据**，不能用「ping 通 / 服务 running / 规则里搜到字符串」代替（playbook §7 G3）。
