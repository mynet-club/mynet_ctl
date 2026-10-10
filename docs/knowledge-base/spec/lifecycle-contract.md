# 生命周期合同（Lifecycle Contract）—— H0

> **地位**：跨平台「安装 → 接入 → 启动 → 应用 → 停用 → 升级 → 卸载」的**操作与资源合同 SSOT**。
> 上位规划见 [platform-lifecycle-build-playbook.md](platform-lifecycle-build-playbook.md)（§2 分层 / §3 冻结口径 / §5 H0）。
> 现状审查与证据见 [workspace-review-20261010.md](../workspace-review-20261010.md)（R1–R12）。
>
> **本合同锁定"目标形态"**：每节先写**合同**（必须成立的不变量），再写**现状与差距**（实测 file:line，标为未修复）。
> 本合同**不改** node / tenant / 密钥 / wire domain 模型；不改 gnb / wg 协议与目录结构。
> 落地由 H1（条件编译）· H2（主机准备一致性）· H3（驱动与网络生命周期）· H4（发行与升级）执行。
>
> 状态：**现行**（目标合同）。H0 交付物 = 本文 + `mynet_agent` 操作 fixture。

---

## 0. 术语与 scope

| 词 | 含义 |
|---|---|
| **动作（action）** | `install` / `uninstall` / `service start\|stop\|restart\|status` / `apply --auto\|--clear` / `update`（自更新）/ `driver install\|upgrade` |
| **owner** | 该动作在某平台上**唯一**负责写系统状态的主体（进程/包管理器/安装器），不允许第二方平行写同一对象 |
| **resource scope** | 资源归属层级：**host**（主机级服务/驱动依赖）、**node**（节点级接口/运行目录）、**generation**（配置代际级路由/规则） |
| **native pkg** | 平台原生安装事务（opkg ipk / NSIS exe / macOS pkg / Linux 发行包） |
| **update archive** | 自更新用的归档（tgz / zip），由 `mynet update` 或 `driver upgrade` 应用 |

**证据纪律**：本合同所有"现状"项须带 `file:line`；无实测输出的记为「静态确认，未实机」。

---

## 1. 动作分层：安装接口归谁（A–F）

来源：playbook §2。**每个动作层只有唯一 owner。**

| 层 | 唯一职责 owner | OpenWrt | Windows | Linux / macOS |
|---|---|---|---|---|
| **A 文件与依赖交付** | 平台包管理器 / 安装包 | ipk 落 mynet/mynetd/runtime/LuCI；声明 `kmod-tun`、工具及适用驱动依赖 | 安装器落 exe、GNB 配套文件、匹配架构的签名 Wintun DLL；独立驱动安装由安装器执行其官方事务 | 包/解压交付工具与驱动；系统已有 TUN/utun；缺依赖按平台方案处理 |
| **B 主机准备与服务登记** | `mynet install` 的平台实现 | 建配置/状态目录、env/procd；按固定归属登记 netifd 逻辑网络对象。无节点配置时不猜 device，不开 forwarding/NAT | 写机器服务配置、SCM 与用户托盘登记；检查 Wintun 依赖与权限。不预建虚构节点 adapter | systemd/launchd、目录/权限/日志/可选托盘；检查平台隧道能力 |
| **C 节点身份与配置** | `mynet setup/auth/node/config` | 绑定节点、密钥与配置；**尚不改变** LAN/WAN 角色 | 同左 | 同左 |
| **D 隧道生命周期** | `mynet service` → 平台/驱动适配 | procd 唯一控制 GNB；GNB 创建 TUN；WG 走明确内核接口/netifd 适配 | 驱动启动时创建/打开 adapter；已有 GNB 的 `WintunCreateAdapter` 负责运行时创建 | GNB/WG/utun 按平台驱动生命周期创建与关闭 |
| **E 系统策略应用** | `mynet apply --auto/--clear` → 平台网络后端 | 按有效节点/许可绑定实际 device，写 MyNet 自有 route/fw/NAT；netifd/fw4 参与持久化与事件回放 | 解析实际 adapter identity/LUID 后应用路由/平台策略 | 按实际接口与能力应用 route/PF/nft |
| **F 更新、维修与卸载** | 平台升级协调器 + package/service owner | opkg 管包；先校验兼容与空间，再服务切换与恢复；移除自有登记，不擦 LAN/WAN | 安装器/受控更新进程处理占用文件与 SCM；保留用户数据；**不删**其他程序使用的 Wintun 驱动 | 包管理器或 tar 更新策略，明确服务切换、回滚与保留状态 |

**冻结选择（playbook §2 实施选择）**：系统登记归 `mynet install`，**安装器是它的调用方**，不再复制登记逻辑。包管理器交付固定文件/元数据和依赖；postinstall/postinst 调**同一**安装实现。服务模板可由 agent 生成供包使用，但**只能有一份语义来源**。

**Wintun**：官方 API 可能在 CreateAdapter 时部署内核驱动，故**不额外规定**再跑一次"驱动安装"。要求：包交付正确依赖 + install 预检 + 驱动启动用其正式 API，并验证首次创建/重复启动/停止/升级。**不增加独立第二条网卡创建路径**。缺 DLL 时**明确报依赖缺失**，不把 GNB 崩溃当普通未连接。

**安装器归属与「薄壳」原则（2026-10-10 裁定）**：安装器（macOS pkg/dmg、Windows NSIS exe、Linux 脚本）**归 `mynet_desktop`**，并**保留图形化交互形态**（降低用户门槛）。但安装器是**薄壳**：只落文件，**系统登记一律委托 `mynet install`**（plist/env/unit/SCM/detector/enable 都归执行器）—— 即 §1 的「系统登记只有一个 owner」。安装器不得自建 schtasks / 自 bootstrap launchd / 自拷 unit；迁移期须清除 legacy 登记（如 macOS 旧 label `club.mynet.mynetd`）。`mynet_agent/packaging/darwin` 是**重复实现**，待退役。

**OpenWrt 与通用 Linux 不共享装配路径（2026-10-10 裁定，收严于 playbook §4.2 的"暂保持兼容"）**：
目录（`/etc/mynet`+`/sbin` vs `/var/lib/mynet`+`/opt/mynet/bin`）、服务（procd vs systemd）、防火墙（fw4/UCI zone vs nft/iptables）差异过大 —— 把 OpenWrt 装配夹带进通用 Linux 二进制是错误分层。故：
- OpenWrt 装配后端**编译期只进 `-tags openwrt` 产物**（`cmd_install_openwrt.go`，`linux && openwrt`）；通用 Linux 后端 `linux && !openwrt`。
- **通用 Linux 包在 OpenWrt 上不再装配**：运行期探测到 OpenWrt 即**拒绝写入并给指引**（opkg / 专用包），符合 §4.2"未知安装模式先拒绝写入并输出修复指引"，避免写 systemd 单元或半装配。
- OpenWrt 产物构建**必须**带 `-tags openwrt`（`.github/workflows/build_openwrt.yml` 已接线，`platform_filetags_test.go` 锁住）。

---

## 2. 冻结的操作合同（逐动作）

以下为 playbook §3 的展开。每条为**不变量**，实现必须满足。

### 2.1 公共合同

- **输入**：`install` 的输入 = 已交付二进制/依赖、`prefix`/`home`、服务模式、gateway 配置。
- **`--home` 优先解析（冻结口径：拒绝，不统一）**：解析优先级 `--home` > `MYNET_HOME`；两者都缺 → 拒绝；两者都给且不同 → **拒绝**（不让私有密钥/配置落在哪产生歧义）。install 与 uninstall **共用**同一解析（`resolveInstallHome`）。**禁止**「调用前先 `requireHome` 再忽略参数」的次序，也不回退 prefix/exe/cwd。
- **`install --dry-run` 零写入**：只检查并打印；**不**停服务、**不**写配置、**不**创建接口、**不**切换节点。但**参数校验与前置检查仍在 dry-run 前生效**（缺 gateway、home 不可解析等照常报错）。
- **真实 install**：先完成**全部预检**，再执行**带失败恢复**的服务装配；**重跑幂等**。
- **服务安装 ≠ 运行安装**：新装可起**未绑定** daemon，但**不自动起隧道**；已绑定升级**保留 desired state**，经协调流程恢复，**不靠 postinst 顺手 restart 旧隧道**。
- **资源以 scope 区分**：host = 主机级服务/驱动依赖；node = 节点级接口/运行目录；generation = 配置代际级路由/规则。沿用既有 **node / interface / generation** 标识，**不用裸名字、不用 PPID=1 证明归属**。
- **单节点先用既有稳定登记名**；多节点/多网络需**逐对象引用关系**，不允许再次 install 抢占别人的 interface。**对象冲突 → 中止并报告**，不覆写未知 owner。
- **C/D/E 复用既有权威**：密钥、bundle/checksum/version、Cloud/Local 权威。**失败回滚不得恢复已撤销的旧出口许可**。
- **分层不越界**：API 只表达业务意图与授权；GUI/LuCI 只请求操作；gateway 透传；OS 细节归执行器与平台后端。**UI 不暴露编译标签**，不要求用户手工选 nft/systemd/procd。

### 2.2 OPERATION: install

```
PRECONDITION:
  - 二进制/依赖已由 A 层交付（或 install 自行置备 driver 四件套）
  - home 可解析（--home / 显式 env / prefix 默认），冲突按 §2.1 处置
  - Linux/OpenWrt 等需要 root 的平台已取得 root

STEPS:
  1. 解析 home（唯一）→ 校验运行环境（端口闸门、依赖）
  2. 预检（全部通过才装配）：二进制存在、目录可写、驱动依赖可用
  3. 建目录：home/{conf,var}（0700）
  4. 写 env（0600，不含明文凭据）
  5. 置备驱动四件套 → home/driver/<driver>/bin（同构建配套）
  6. 平台登记（B 层唯一 owner）：
     - linux: systemd unit + daemon-reload + enable --now
     - darwin: launchd plist + bootstrap
     - windows: SCM 服务（+ 托盘 Run 键）；**不再并存计划任务**
     - openwrt: init 脚本 + procd + 逻辑网络对象/zone（仅自有、且 proto none）
  7. （可选）跑 detector 写 var/system.json
  8. 不自动起隧道（起未绑定 daemon 或按 desired state）

POSTCONDITION:
  - 依赖与服务**准备就绪**；接入成功时方可验证实际接口与流量
  - 重跑：登记幂等，不重复 add、不覆写未知 owner

ERROR PATHS:
  - 缺二进制/依赖 → 依赖缺失（明确报，非"未连接"）
  - 权限不足 → permission denied（不静默提权）
  - 对象冲突 → 中止，报告冲突对象，不覆盖
  - 装配中途失败 → 回滚到旧服务状态

PLATFORM DIVERGENCE:
  - OpenWrt: 仅创建 proto none 的自有逻辑登记；活动接口/Cloud 许可/forwarding/masq 在无配置安装阶段不决定
  - Windows: SCM 为唯一常驻机制；老旧计划任务须在迁移中间批次收敛并移除
```

### 2.3 OPERATION: uninstall

```
PRECONDITION: 已安装（存在登记）

STEPS:
  1. 停服务（按各自机制，精确停）
  2. 清除**自身**注册（unit/plist/SCM/init/Run 键）
  3. 删自身程序文件（默认；可 --keep-bin 保留）
  4. **保留**身份/配置（conf/）；数据是否清除必须是**显式选项**，默认不清
  5. 包文件归包管理器删除；runtime adapter 归驱动关闭；逻辑网络对象按**所有权**删

POSTCONDITION:
  - 自身登记与运行态清理干净
  - 不擦 LAN/WAN；不删其他程序使用的共享系统驱动（如 Wintun）

ERROR PATHS:
  - 有未知 owner 的对象 → 不动并报告
```

### 2.4 OPERATION: service

```
service <start|stop|restart|status> [gnb|wireguard]

合同:
  - 精确停：按 pidfile + cmdline / init 系统；**禁** pkill/killall/pidof 取第一个
  - 归属可验证：本实例路径 / 父进程 / 运行目录，**不靠 PPID=1**（孤儿≠本实例）
  - 停成功后**删 pidfile**；无 pid 显示 STOPPED（不显示"启动失败"）
  - systemd/procd/launchd/SCM 托管时优先走托管机制
```

### 2.5 OPERATION: apply

```
mynet apply --auto | --clear

合同:
  - 唯一写入者 = mynet apply；驱动层不感知系统路由/防火墙
  - 作用域 = generation 级；撤销只清**本代**规则（接口独立专表/链）
  - --clear 与撤销必须可回滚、幂等；失败保持 last-known-good
  - 不恢复已撤销的出口许可
```

### 2.6 OPERATION: update / driver upgrade

```
合同:
  - artifact kind 与平台/架构必须**显式识别**，不靠扩展名猜、不给浮动 fallback
  - 版本/通道/签名/sha256 与来源 manifest 冻结；未知 ref / 缺产物 → 直接失败
  - Linux tar：允许受控**原子**文件更新
  - OpenWrt：**必须走 opkg** 原生包更新，**禁止**解压 Linux 包绕过数据库
  - Windows：处理 exe 占用（SCM stop/start 或下次启动替换）；四件套同批构建
  - 升级保留 desired state 与身份；回滚**不得**重开已撤销的 Cloud 权限
```

---

## 3. 默认路径与服务名矩阵（B 层登记目标值）

**平台默认 Store Root**（详见 [paths.md](paths.md) §3，install 时写入，之后以 env 为准）：

| 平台 | 默认 prefix | 目标默认 home |
|---|---|---|
| Linux | `/opt/mynet` | `/var/lib/mynet` |
| OpenWrt | `/opt/mynet`（bin 在 `sbin`） | `/etc/mynet` |
| macOS | `/usr/local/opt/mynet` | `/var/lib/mynet` |
| Windows | `C:\Program Files\MyNet` | `C:\ProgramData\MyNet` |

**服务登记目标矩阵**（同名对象只能有一个 owner/一条启动链）：

| 平台 | 服务/label 名 | 落盘 | 备注 |
|---|---|---|---|
| Linux (systemd) | `mynetd`（+ `mynet_gnb`） | `/etc/systemd/system/*.service` | 需配套创建运行用户与目录 owner |
| macOS (launchd) | **唯一** label（CLI 与 pkg 必须一致） | `/Library/LaunchDaemons/<label>.plist` | 托盘为 LaunchAgent |
| Windows | SCM 服务（**唯一**常驻机制） | SCM；env `home\mynetd.env`(0600) | 托盘为 Run 键；旧计划任务须移除 |
| OpenWrt | init 脚本 `mynetd` / `mynet` | `/etc/init.d/*`；env `/etc/mynet/mynetd.env` | procd 唯一控制 GNB |

**env 文件**：每平台一份（0600），仅含非明文凭据项（如 `MYNET_HOME`、listen、gateway 地址）；**私钥/token/cookie 不进 env、不进日志**。

---

## 4. 原生包与更新归档矩阵

`ctl` 发行侧必须把 **product / platform / arch / version / variant / ext** 作**独立维度**登记（playbook §4.3），与 SDK package metadata 分开。

| 平台 | native pkg（安装事务） | update archive（自更新） | 更新通道 |
|---|---|---|---|
| Linux | 发行包（systemd unit 交付） | `tgz` / `tar.gz` 受控原子替换 | 包管理器或 tar |
| macOS | `.pkg`（`/opt/mynet` + LaunchDaemon） | `tgz` / `zip`（Mach-O） | tar |
| Windows | `.exe`（NSIS 安装器） | `zip`（内含 `mynet.exe` 等） | 下次启动/受控替换 |
| OpenWrt | `.ipk`（opkg + procd） | **无自更新归档**：只能 opkg | opkg 原生 |

**WebUI embed 与 OS 编译选择正交**：Windows/Darwin/Linux 常规发行 embed；**OpenWrt 无 embed**（UI 由 LuCI 托管）。OpenWrt 文件不进桌面安装事务；SCM/launchd 不进 OpenWrt 后端。

**OpenWrt 归档变体（2026-10-10 增）**：除 ipk（原生）外，发行面另出 **`mynet_agent_openwrt_<arch>_<ver>.tgz`**（`-tags openwrt` 产物，手工落位/中间件用）——它是**分发归档（distribution archive）**，不是自更新归档；OpenWrt 更新仍只走 opkg。ctl 侧 `PLATFORMS.openwrt`（ext=tgz）已支持该 kind；构建侧由 `Makefile` 的 `DIST_MATRIX` 产出（`platform_filetags_test.go::TestDistMatrixHasOpenWrtVariant` 锁住）。

---

## 5. 现状与差距（H0 冻结的事实，H1–H4 关闭）

> 均为**静态确认**（隔离 fixture / go list / 代码阅读），**未实机**。这些是 H1–H4 的输入，**不是已完成**。

| # | 合同条 | 现状（file:line） | 关闭于 |
|---|---|---|---|
| G-1 | §2.1 `--home` 优先解析 | ✅ **已关闭（H2）**：`resolveInstallHome`（`cmd_install.go`）按 `--home > MYNET_HOME` 解析，冲突或都缺即拒绝；install/uninstall 共用，不再先 `requireHome` 而忽略参数（R12） | H0/H2 |
| G-2 | §2.1 UI 不暴露编译标签 / 平台文件互斥 | ✅ **已关闭（H1）**：`cmd_install_{systemd,openwrt_only,launchd,win,unsupported}.go` 按 build tag 互斥，共享入口 `cmd_install.go` 委派 `platformInstall/platformUninstall`；`go list` fixture 断言每平台恰一个入口文件 | H1 |
| G-3 | §3 服务名一致（B 层单 owner） | ✅ **已关闭（H2）**：NSIS 不再 `schtasks`，改为调 `mynet install`（SCM 单常驻）+ 卸载委托 `mynet uninstall`；`nsis/service.ps1`（并行实现）删除。`check.sh::lint_nsis` 断言（R3） | H2 |
| G-4 | §3 服务名一致 | ✅ **已关闭（H2）**：macOS pkg 不再自带 plist，postinstall 调 `mynet install`（label `com.mynet.mynetd`）；legacy `club.mynet.mynetd` 由 preinstall/postinstall 清除；根 `launchd/` plist 退役。`check.sh::lint_macos` 断言（R4） | H2 |
| G-5 | §3 Linux 需配运行用户/owner | ✅ **已关闭（H2）**：desktop 的 `systemd/mynetd.service` 删除，`install-linux.sh` 改为委托 `mynet install`（unit/env 由 agent 内嵌写出，语义归执行器）。`check.sh::lint_linux` 断言（R5） | H2 |
| G-6 | §2.6 Windows 文件名识别 | `locateBinaries` 只认 `mynet`/`mynetd`/`mynet_gateway`，不认 `.exe`（`mynet/upgrade/install.go:33,349`）（R6） | H4 |
| G-7 | §2.6 OpenWrt 走 opkg | 选择按 `runtime.GOOS=linux`（`mynet/cmd_update.go:87`）；安装器默认直接替换文件（R6） | H4 |
| G-8 | §4 artifact kind 显式 | ✅ **已关闭（H4，2026-10-10）**：`parseFilename` 扩展名白名单加 `exe/dmg/pkg`；upload **不再把 variant 当 ext**（variant 是并入 arch 的限定符，见 `ManifestService`）；管理端产品/平台白名单改由 domain SSOT 派生（原硬编码漏 `mynet_agent`/`openwrt-sdk`）。**残余已补**：新增**声明式 `kind`**（`installer`=exe/dmg/pkg/ipk，`archive`=tgz/zip/tar.gz）—— 域内 `ARTIFACT_KINDS` 是唯一 SSOT，**登记时映射一次并落库**（`releases.kind` + 迁移 `2026_10_10_000001`），manifest 的 artifact 行输出 `kind`（读取端不再运行时猜）。**仍开放**：`/d/` 按 (product,platform,arch,version) 取文件时不带 kind（同 tuple 有 installer+archive 两件时会取错）—— 归运行升级选择器（已搁置） | H4 |
| G-9 | §2.4 精确清理 | ✅ **已关闭（H3）**：`stop_gnb_instance` 改可验证归属 —— 先按「gnb cmdline 含本实例 confdir」锁定本实例 gnb 的 pid，再只清「exe=本部署 gnb_es **且** 父进程 ∈ 本实例 gnb」者；归属不可证者**不杀只记**；PPID=1 判据删除。fixture `TestOpenWrtInitGnbEsCleanupUsesVerifiableOwnership`（R9） | H3 |
| G-10 | §2.2 install 依赖缺失可解释 | ✅ **已关闭（2026-10-10，用户裁定）**：**不改 gnb `src/`**；`wintun.dll` 作**资源文件入库**（`mynet_desktop/nsis/vendor/wintun/`，WireGuard LLC 官方签名 amd64，SHA256 `e5da8447…`），NSIS payload 打进安装器、落到 **gnb.exe 同目录** `$INSTDIR\driver\gnb\bin`（`File` 无 `/nonfatal` → 缺则编译即失败）；`guard` C4 新增**持久白名单** `.guard-c4-allow` 放行该路径；`build-nsis.sh` 每次出包校验 SHA；`check.sh::lint_nsis` 断言（R10） | 安装器（H2） |
| G-11 | §2.2 OpenWrt UCI 首装成功 + 幂等 | ✅ **已关闭（H2，2026-10-10）**：zone 步骤改为**捕获** `uci add firewall zone` 打印的 section 名（旧实现丢进 /dev/null 再按 name 反查 → 首装必 `exit 1`）；forwarding 幂等改为逐 section 读 `src`/`dest` 比对（旧的单行 `grep "src=.*dest="` 对 `uci show` 的分行输出永不命中）；section 名一律按 `awk -F"[.=]"` 取（`-F.` 会粘上 `=类型`）。stub-uci 回归测试覆盖首装/重跑/具名·匿名 zone（R2） | H2 |
| G-12 | §2.6 发行 workflow 输入完整 | ✅ **已关闭（H4，2026-10-10）**：`release.yml` 新增 **mynet_gui checkout + `npm ci` + embed 构建**（`.env.embed`→`.env.prod`/`.env.local.prod`，并断言 `dist/spa/index.html` 含 `/gui/assets/`）再跑 `make dist`；**去掉默认分支兜底**（agent ref 取不到 → 明确失败，附指引），新增 `gui_ref` 输入；见 §4.3「锁两仓 SHA」。（R7） | H4 |
| G-13 | §1 B 层唯一登记 owner | OpenWrt 实际 Ops 不满足新 connect/config 合同（`mynetd/main.go:126-129`、`executor_lua.go:271-285`、`connect_api.go:48-88`）（R1） | H1–H2 / O1 |
| G-14 | §3 默认 home 与 prefix 一致 | ✅ **已关闭（H2）**：`PlatformDefaultHome()` 改为返回 **home**（`/var/lib/mynet`、`C:\ProgramData\MyNet`），与 prefix（`/opt/mynet`、`C:\Program Files\MyNet`）分开；`mynetd` windows 服务缺省 home 同步。CLI 与 desktop 安装器仍各写一棵树的**真正收敛**（安装器改调 `mynet install`）留 H2 剩余项（R4/R5） | H2 |
| G-15 | §2.3 uninstall 清自身登记 | ✅ **已关闭（H2）**：OpenWrt 卸载归 `uninstallOpenWrt`（`cmd_install_openwrt.go`，`linux && openwrt`）；通用 Linux 包在 OpenWrt 上**不再装配/卸载**（探测即拒绝并给指引，见 §1 裁定） | H2 |

---

## 6. 验收要求（H0 出口判据）

必须覆盖（playbook §5 H0 / §6）：

- 新装 / 升级 / 重装 / 维修 / 卸载；未绑定 / 已绑定
- 依赖缺失 / 权限不足 / 名称冲突
- **dry-run 零写入**（对目标目录做写前/写后指纹比对）
- **重复登记幂等**
- **安装途中失败恢复旧服务**
- 跨进程竞争
- 新旧 generation / 撤销
- 设备未就绪 / 冷启动 / 断网 / FW reload
- 副作用归属；日志与错误**无秘密**

**证据纪律**：记录提交、平台、包哈希、动作与结果、脱敏指纹；**不保存**私钥/token/cookie/原始 bundle。交叉编译与 mock 只记**部分完成**，不据此勾 E/F/P5/M4。

**H0 交付物**：
1. 本文（合同 + 默认矩阵 + 差距表）。
2. `mynet_agent` 全量操作 fixture（install/uninstall/service/dry-run/重跑/清理/回滚，表驱动）。

---

**关联**：[paths.md](paths.md)（路径 SSOT）· [service.md](service.md)（旧线启停，已冻结）· [driver-abstraction.md](driver-abstraction.md)（驱动接口）· [platform-lifecycle-build-playbook.md](platform-lifecycle-build-playbook.md) · [workspace-review-20261010.md](../workspace-review-20261010.md)
