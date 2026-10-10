# 跨平台安装、运行与构建实施规划

> 2026-10-10。用户要求整体规划考虑动作分层、mynet 平台条件编译，并重新 review 整个 workspace。
> 状态：架构与工作包规划；尚未改运行时代码。现状及验证见 [workspace 审查报告](../workspace-review-20261010.md)。
> 承接 dev-plan 的 E 平台/发行轨和 F 执行器轨；OpenWrt 具体步骤正本仍在 [平台接入规划](../../../../mynet_openwrt/docs/AGENT_INTEGRATION_PLAYBOOK.md)。本文不取代业务、密钥与 bundle SSOT。

## 1. 设备身份与产品边界

用户本轮澄清是当前权威：

| 节点 | 环境 | 网络职责 | 本轮限制 |
|---|---|---|---|
| .178 / 10.182.236.178 | 公司，用户当前所在网络；路由器 LAN 192.168.8.1 | 公司主路由；已有报告记录其 PVE 宿主默认路由也经它 | 不在 review 中升级、重启或写网络；最后单独安排迁移 |
| .182 / 10.182.236.182 | 家里；已有计划关联 LAN 192.168.0.2 | 家庭旁路由 | 不是公司节点，不视为当前 LAN 可直接管理的测试机，也不因是旁路由就当作可随意中断 |

节点尾号不能替代身份模型；身份仍为字符串 NodeID 与既有节点密钥，地点/主路由角色属于部署事实，不增加云端 tenant 或节点 schema 字段。

产品目标：一套节点身份、配置与操作语义，多套平台实现和安装交付；桌面入口、LuCI 入口和远程命令复用同一操作 owner。首个场景先在隔离机器证明安装→接入→启动→应用→停用→升级→恢复，再转现网。

## 2. 动作分层：安装接口究竟归谁

“安装网络接口”必须拆分：依赖交付、系统登记、运行时设备创建。OpenWrt 的 UCI 逻辑 interface 与内核 TUN device 不是同一对象；Windows 的 Wintun DLL/内核驱动与运行时 adapter 也不是同一对象。

| 层 | 唯一职责 owner | OpenWrt | Windows | Linux/macOS |
|---|---|---|---|---|
| A 文件与依赖交付 | 安装包/平台包管理器 | ipk 落 mynet/mynetd/runtime/LuCI；声明 kmod-tun、工具及适用驱动依赖 | 安装器落 exe、GNB 配套文件、匹配架构的签名 Wintun DLL；如选用独立驱动安装方案，由安装器执行其官方安装事务 | 包/解压交付工具与驱动程序；系统已有 TUN/utun；缺依赖按平台安装方案处理 |
| B 主机准备与服务登记 | `mynet install` 的平台实现 | 创建必要配置/状态目录、env/procd；按固定归属登记 netifd 逻辑网络对象。没有节点配置时不猜 device，不开放 forwarding/NAT | 写机器服务配置、SCM 与用户托盘登记；检查 Wintun 依赖和权限。不预建一个虚构节点 adapter | systemd/launchd、目录/权限/日志/可选托盘；检查平台隧道能力 |
| C 节点身份与配置 | `mynet setup/auth/node/config` | 绑定节点、密钥与配置；尚不改变 LAN/WAN 角色 | 同左 | 同左 |
| D 隧道生命周期 | `mynet service` → 平台/驱动适配 | procd 唯一控制 GNB；GNB 创建 TUN；WG 走明确的内核接口/netifd 适配 | 驱动启动时创建/打开 adapter；已有 GNB 的 `WintunCreateAdapter` 负责运行时创建 | GNB/WG/utun 按平台驱动生命周期创建与关闭 |
| E 系统策略应用 | `mynet apply --auto/--clear` → 平台网络后端 | 根据有效节点/许可绑定实际 device，写 MyNet 自有 route/fw/NAT；netifd/fw4 参与持久化与事件回放 | 解析实际 adapter identity/LUID 后应用路由/平台策略 | 根据实际接口与能力应用 route/PF/nft 等 |
| F 更新、维修与卸载 | 平台升级协调器 + package/service owner | opkg 管包；先校验兼容与空间，再服务切换与恢复；移除自有登记，不擦 LAN/WAN | 安装器/受控更新进程处理占用文件与 SCM；保留用户数据；不得删除其他程序使用的 Wintun 驱动 | 包管理器或 tar 更新策略，明确服务切换、回滚和保留状态 |

**实施选择：系统登记归 `mynet install`，安装器是它的调用方，不再复制登记逻辑。** 包管理器交付固定文件/元数据和依赖；postinstall/postinst 调用同一安装实现。服务模板可以由 agent 生成供包使用，但只能有一份语义来源。

OpenWrt 空 netifd 对象是否在安装期必需，由 H0 给出平台验收证据；若需要则只创建 `proto none` 的自有逻辑登记。活动接口、Cloud 许可、forwarding/masq 不在无配置安装阶段决定。安装后不要求虚拟网卡已出现，要求“依赖和服务准备就绪”；接入成功则必须验证实际接口与流量。

Wintun 的官方 API 可能在 CreateAdapter 时部署内核驱动，因此不规定额外再跑一次“驱动安装”。要求包交付正确依赖、install 预检、驱动启动时使用其正式 API，并验证首次创建/重复启动/停止/升级；不增加独立第二条网卡创建路径。缺 DLL 时明确报依赖缺失，不把 GNB 崩溃当作普通未连接。

## 3. 冻结操作与资源合同

- `install` 的输入为已交付二进制/依赖、prefix/home、服务模式与 gateway 配置。先解析 `--home`，结合显式环境解析唯一 home；两者冲突须按冻结口径明确拒绝或统一，不能调用前先 requireHome 而忽略参数。
- `install --dry-run` 只能检查并打印，不停止服务、不写配置、不创建接口、不切换节点。真实 install 先完成全部预检，再执行有失败恢复的服务装配；重跑幂等。
- 服务 install 与运行 install 不同：新装可以起未绑定 daemon，但不自动起隧道；已绑定升级保留 desired state，经协调流程恢复，不靠 postinst 顺手 restart 旧隧道。
- 默认 `uninstall` 停服务并清除自身注册，保留身份/配置；是否清数据必须是明确选项。包文件归包管理器删除，runtime adapter 归驱动关闭，逻辑网络对象按所有权删除，共享系统驱动不能全局删除。
- 资源以 scope 区分：主机级服务/驱动依赖、节点级接口/运行目录、配置 generation 级路由/规则。沿用已有 node/interface/generation 标识，不用裸名字或 PPID=1 证明归属。
- 单节点先沿用既有稳定登记名；多节点/多网络场景需要逐对象引用关系，不允许再次 install 抢占别人的 interface。对象冲突中止并报告，不覆写未知 owner。
- C/D/E 各阶段复用既有密钥、bundle/checksum/version、Cloud/Local 权威。失败回滚不得恢复已经撤销的旧出口许可。
- API 只表达业务意图与授权；GUI/LuCI 只请求操作；gateway 透传；OS 细节归执行器与平台后端。UI 不向用户暴露编译标签，也不要求用户手工选 nft/systemd/procd。

## 4. mynet 条件编译与能力装配

当前已有 detector/driver/route/DNS 的条件文件，但 installer 三套实现都进三平台构建；必须收敛。

### 4.1 编译期选择

- Windows 文件明确 `_windows.go` 或 `//go:build windows`；Darwin `_darwin.go`；Linux `_linux.go`。`_win`、`_launchd`、`_openwrt` 不是 Go 的 GOOS 后缀，不能拿文件名当约束。
- OpenWrt 仍是 GOOS=linux，专用产物采用显式 `openwrt` 标签；OS 后端 `linux && openwrt` 与 `linux && !openwrt` 互斥。通用 Linux 路由命令、bundle/身份与纯 planner 仍可共用。
- CLI 命令、输入校验、共享合同与纯计划生成留在公共代码；服务登记、系统准备、生命周期、网络执行、系统 DNS 与更新应用后端按平台编译。现存文件先通过接口搬移，不新造一套业务层。
- 入口统一调用平台接口，不保留一个公共函数引用被排除的平台实现。平台函数签名一致；不支持的 backend 明确返回 unsupported，禁止 error 桩后默认成功。
- 本机模拟测试不等于目标执行；公开命令名称保持兼容，`platform/capabilities` 返回实际支持信息。

建议最小内部边界（是设计目标，不声称当前存在）：

```go
type HostPlatform interface {
    Descriptor() PlatformDescriptor
    PlanInstall(InstallRequest) (InstallPlan, error)
    ApplyInstall(InstallPlan) (InstallResult, error)
    Uninstall(UninstallRequest) (UninstallResult, error)
}
// 复用既有 driver lifecycle、apply executor 与 upgrade 接缝；
// 不把所有能力塞进 HostPlatform，不改 wire domain 为 OS 对象。
```

PlatformDescriptor 最少区分：goos/goarch、发行平台（linux/openwrt/windows/darwin）、包管理方式、服务 manager、已编译能力；ABI/SDK target 在发行元数据中声明。InstallPlan 描述 owner、动作、预检、旧状态和回滚，不含原始私钥/用户密码；实际返回类型先在 H0 锁定。

### 4.2 运行期判断

条件编译决定“这个产物有哪些平台实现”，探测决定“当前系统具有哪些能力”。OpenWrt 标签不能证明 fw4 存在、WG 可用或 rootfs 够大；GOOS=linux 也不能替代 OpenWrt 发行识别。需要区分 unsupported、missing dependency、permission denied、conflict 和执行失败，不用静默 fallback 抹平差异。

正式 OpenWrt 包用专用标签构建并检查运行环境。旧通用 Linux 包在 OpenWrt 的安装暂保持兼容到迁移完成，但明确标为 legacy，不把它用于新 OpenWrt 自动更新；未知安装模式先拒绝写入并输出修复指引。

### 4.3 发行与更新

- WebUI embed 与 OS 编译选择是两个独立维度；Windows/Darwin/Linux 常规发行 embed，OpenWrt 无 embed。OpenWrt 的文件不进入桌面安装事务，SCM/launchd 不进入 OpenWrt 后端。
- 原生安装包和自更新归档各自有 artifact kind，不只按扩展名猜。ctl 的 product/platform/arch/version/variant/ext 与 SDK package metadata 分开登记。
- Linux tar 包允许受控原子文件更新；opkg 管理安装必须走原生包更新，不能解压 Linux 包绕过数据库；Windows 要考虑 exe 锁定、SCM stop/start、外部 helper 或下一次启动替换。
- 安装器/驱动组件用固定 SHA、版本/通道、签名/sha256 与来源 manifest；四个 GNB 程序来自同批构建，Wintun 明确架构与许可来源。未知 ref、缺 GUI/托盘/依赖产物直接失败，不用默认分支兜成同名 release。

## 5. 工作包与集成顺序

默认单执行者；可并行性只是工作边界，不启动额外聊天或子代理。

| 包 | 责任与文件边界 | 输出与验收 | 前置/禁止 |
|---|---|---|---|
| H0 生命周期合同 | ctl spec/paths/service/driver 文档；agent 测试 fixture | 锁定 A–F 的每个动作、owner、默认路径/服务名、错误、dry-run/重跑/清理/回滚；原生包与更新归档矩阵 | 当前为规划；全量操作 fixture 尚未交付。不改节点/租户/密钥/wire 模型 |
| H1 编译与平台入口 | agent mynet installer/OS backends、构建 workflow | 平台文件互斥、共享入口、Descriptor；go list 固定文件集合；darwin/windows/linux/openwrt 交叉编译。保留命令与迁移兼容 | H0；不删除纯 planner 的跨平台 fixture，不使用 runtime.GOOS 代替发行平台 |
| H2 主机准备一致性 | agent install/units；desktop/OpenWrt 安装器 | 单一服务与路径来源；安装器调用 install；OpenWrt UCI 首次/重复/失败回滚；Windows SCM、macOS launchd、Linux 权限/环境正确；--home 无环境可用 | H1；不未经验证复制 root/非root策略，不能先写全局 forwarding，不能在 review 时启动现网服务 |
| H3 驱动与网络生命周期 | agent driver/apply/OpenWrt 后端；GNB 只通过补丁包改 | 实际接口创建、归属、重启/停止、FW reload/hotplug、自有规则撤销；Wintun 缺失失败可解释、进程精确清理。GNB/WG 分开验收 | H2 与现有 P4 合同；禁第二个网卡创建者、改上游 src、改变驱动密钥布局 |
| H4 发行与升级 | desktop workflow、agent upgrade/OpenWrt SDK、ctl release | 锁两仓 SHA/GUI 版本；ctl 真实上传与解析全部 artifact kind；Windows exe识别/占用回滚、OpenWrt opkg路径、版本/架构选择正确。发布 provenance fixture | H0后可修构建输入，运行升级等 H1/H2。禁浮动 fallback、普通 Linux 包覆盖 opkg文件、混用四件套 |
| H5 原生入口和验收 | OpenWrt O2…O7；GUI 用户流程/相关 tests | LuCI 到统一 Ops、登录权限、接入/诊断/恢复，资源测量和网络角色证据；至少 reference 环境行为通过 | H2/H3/H4；不把 API/GUI 单测绿当作实际接口/数据面已通 |
| H6 现网迁移 | 各设备计划、部署清单与 project-memory | .178 公司主路由与 .182 家庭旁路由分别制定前置、窗口、备份/本地控制台/网络回程/恢复；固定版本、保身份、可恢复 | H5；本轮不实施。地理与角色不决定自动选谁当第一台试验设备 |
顺序：H0→H1→H2→H3；H4 的合同与构建输入可在 H0 后准备，升级收口等 H2；H2/H3/H4→H5→H6。OpenWrt O1 受 H1/H2 约束，O6 受 H4 约束；O0/O2 的接口设计可以先做。

> **H0 进度（2026-10-10）**：合同文档已落 → [`lifecycle-contract.md`](lifecycle-contract.md)（A–F 分层 + 逐动作合同 + 默认路径/服务名矩阵 + 原生包/归档矩阵 + 现状差距表 G-1…G-15）。`mynet_agent` 全量操作 fixture 已落 `mynet/lifecycle_contract_test.go`。本轮只做开发，不实施 H6 现网迁移。
>
> **H1 进度（2026-10-10）**：平台安装器文件已按 build tag 互斥 —— `cmd_install_{systemd(linux&&!openwrt),openwrt(linux&&openwrt),launchd(darwin),win(windows),unsupported}.go`；共享入口 `cmd_install.go` 委派 `platformInstall/platformUninstall`；新增 `PlatformDescriptor` 并接入 `mynet platform`；`mynet/platform_filetags_test.go` 用 `go list` 断言每平台恰一个入口文件。四平台（linux/linux+openwrt/darwin/windows）**交叉编译与测试面编译**通过；G-2 关闭。
>
> ⚠️ **H1 收严（2026-10-10，用户质疑后自纠）**：OpenWrt 与通用 Linux 装配路径**不共享**（目录/服务/防火墙都不同）。OpenWrt 后端改 `linux && openwrt`、**不再夹带进通用 Linux**；通用 Linux 包在 OpenWrt 上**拒绝装配并给指引**（收严于 §4.2 的"暂保持兼容"）。同时补上被漏掉的接线：OpenWrt 产物构建改用 `-tags openwrt`（`build_openwrt.yml`），并加 fixture 锁住。local `Makefile` 的 dist 仍只出通用 Linux mipsel（systemd），OpenWrt 归档变体归 H4。
>
> **H2 进度（部分，2026-10-10）**：① home 口径统一 —— `resolveInstallHome`（`--home` > `MYNET_HOME`，冲突/都缺即拒绝，install/uninstall 共用）；`PlatformDefaultHome()` 改为真实 home（`/var/lib/mynet`、`C:\ProgramData\MyNet`），与 prefix 分离；`mynetd` windows 服务缺省 home 同步 → 关 G-1/G-14(CLI 侧)/G-15。② OpenWrt UCI 兜底修复 —— zone 捕获 section 名、forwarding 逐 section 比对、section 名按 `[.=]` 切分；stub-uci 回归测试（首装/重跑/具名·匿名 zone）→ 关 **G-11**。③ **安装器薄壳委托**（用户裁定：安装器归 `mynet_desktop`、保留图形形态，登记只归 `mynet install`）——macOS pkg 不再自带 plist/自 bootstrap、Windows NSIS 不再 `schtasks`、Linux 不再自拷 unit，三处全改委托；`service.ps1`(Win 并行实现)、`systemd/mynetd.service`、根 `launchd/` plist 均退役；`mynet_agent/packaging/darwin` 重复实现退役（`make pkg-darwin` 移除）。desktop `check.sh` 新增 `lint_macos`/`lint_nsis`/`lint_linux` 断言 → **36 通过 / 0 失败**。**关 G-3/G-4/G-5**。**H2 剩余（仅真机）**：macOS 旧 label `club.mynet.mynetd` 的现网迁移验证。
>
> **H4 进度（部分，2026-10-10）**：构建侧 —— `Makefile` `DIST_MATRIX` 增 **openwrt 归档变体**（`openwrt:linux:mipsle:mipsel:openwrt` → `mynet_agent_openwrt_mipsel_<ver>.tgz`，`-tags openwrt`、无 embed），矩阵改为 `platform:goos:goarch:ctlarch:tags` 五元组；`packaging/openwrt/INSTALL.md` 手工落位改指该变体。ctl 侧 —— `parseFilename` 加 `exe/dmg/pkg`；upload 不再把 `variant` 当 `ext`（variant 是并入 arch 的限定符）；管理端产品/平台白名单改由 domain SSOT 派生（原漏 `mynet_agent`/`openwrt-sdk`）。**H4 dev 面收口**：artifact kind 已落（声明式 `kind` + `releases.kind` 迁移 + manifest 输出）；desktop workflow 输入已补（GUI checkout+embed 构建、去默认分支兜底、`gui_ref`）→ **关 G-12**。剩运行升级选择器（`mynet update`/opkg）按用户指示搁置。
>
> **H3 进度（部分，2026-10-10）**：**G-9 关闭** —— OpenWrt `stop_gnb_instance` 改**可验证归属**（本实例 gnb pid ← cmdline 含 confdir；只清 exe+父进程都指向本实例的 gnb_es；不可证者不杀只记），删除 PPID=1 判据；fixture 已加。**H3 剩余（G-10）**：用户裁定**不改 gnb `src/`**（原拟补丁已撤回）。改为**安装器侧**并已落地：Windows 安装器 payload 带 `wintun.dll` 到 gnb.exe 同目录、缺 DLL 编译即失败；`scripts/fetch-wintun.sh` 按钉死 SHA 取件；provenance = WireGuard LLC 官方签名 amd64 件。**剩**：CI 取件来源（ctl 托管 / 直链）待定。
>
> **O2 进度（2026-10-11）**：本地管理桥已交付并**设备实证**（`.182` ImmortalWrt x86_64）。rpcd ucode 桥（`/usr/share/rpcd/ucode/mynet.uc`，标准脚本 + `return {mynet:...}`；固定方法表 16 项，argv 逐动作写死，不 relaying/不开放 system.json/不回私钥）；ACL 读 10/写 6。**收口实测**：① 入参类型显示 `String` = rpcd ucode 标准渲染（`ddns.uc`/`luci.wireguard` 同此），非缺陷；② 会话态 ACL 正/负测试通过（`session access`：16 方法 `access=true`，未授权方法 `false`；警示 `ubus` CLI 以 uid0 直连**绕过** ACL，须走 `session access`）；③ 静态门禁 `scripts/tools/check_bridge_acl.sh`（桥⇄ACL 逐项对齐；不用 GNU sed 扩展——BSD sed 的 `\?` 会静默零匹配）已挂 `mynet_openwrt` 的 `make -f Makefile.guard test`。方法映射冻结表见 [`../../../../mynet_openwrt/docs/O2-BRIDGE-METHOD-MAP.md`](../../../../mynet_openwrt/docs/O2-BRIDGE-METHOD-MAP.md)。
>
> **O3 进度（2026-10-11）**：**执行器「成功却回 exit 1」已修** —— `luci/model/mynet/util.lua:M.exec_status` 此前要求 marker 在输出**末尾**，而 init 在 post-start 后台输出写在 marker 之后 → 判 -1 假失败；改为取**最后一次** marker。设备实测通过。
>
> **O4 进度（部分，2026-10-11）**：核心机制**已存在**（非新写）：双轨意图 `ProxyIntentState`（Cloud=出口许可 / Local=客户端分流，`Effective()` 按职责合成，见 `shared/domain/proxy_intent.go`）；单调门禁 `ApplyCloud`（旧 generation 拒绝、同代改写拒绝，`proxy_intent_test.go` 覆盖）；云端许可只来自统一 bundle（`StampCloudIntent` ← `bundle.network.proxy`）；`proxy_unify.go` 把 Effective 投影到插件壳。**本轮补的缺口**：旧 Lua 权威 `conf/proxy/proxy_role.conf`（Go 侧此前**零引用**）→ 统一 Local 意图的**单向折叠迁移** `mynet/proxy_migrate.go`（幂等 marker + 源 sha256、Local 非空不覆盖、**未知键 fail-closed 不猜**、dry-run 零写），挂 `mynet proxy migrate [--dry-run]` 且随 `mynet install` best-effort 跑一次；6 项测试过。**O4 剩余**：LuCI 页面（`luci/model/mynet/proxy.lua`）仍自持 `proxy_role.conf` 第二权威，退役它属 **O5**（页面改走桥）。
>
> **H6 进度（2026-10-10，仅计划）**：用户要求为 `.180`(api.mynet.club / proxy server) 与 `.182`(192.168.0.2 / 旁路网关 / proxy client) 制定升级部署计划 → 已落 [`node-upgrade-plan-180-182.md`](node-upgrade-plan-180-182.md)（身份/取证、产物、步骤、验证、回滚、Go-NoGo、待裁定 D1–D5）。**仍未执行、未连节点**。关键缺口：dist 缺 **openwrt/amd64** 腿（`.182` 是 x86_64）、ipk 未进 ctl、`mynet update` 选不到 openwrt 包。

## 6. 验收、数据与回滚

必须覆盖：新装/升级/重装/维修/卸载；未绑定/已绑定；依赖缺失/权限不足/名称冲突；dry-run 零写入；重复登记幂等；安装途中失败恢复旧服务；跨进程竞争；新旧 generation/撤销；设备未就绪/冷启动/断网/FW reload；副作用归属；日志和错误无秘密。

安装/升级检查元数据并变换配置文件，不访问用户流量 payload。凭证只走 crt/crtvault；证据记录提交、平台、包哈希、动作与结果、脱敏指纹，不保存私钥/token/cookie/原始 bundle。跟踪返回步骤和失败点，不因服务程序存在就报全部安装成功。

先在隔离环境影子比较动作计划，再单目标 enforce；失败保留 last-known-good 并恢复自身文件/登记，避免覆盖其他程序的变更。原生驱动和包管理失败按其正式事务恢复，不用无限重试；撤销后的 Cloud 权限不能靠回滚旧 bundle 重新开放。

完成条件：同平台安装器与 CLI 装配结果一致；平台文件集合互斥；接口层次明确且只有一个创建者；发行和升级方式一致；真实平台行为与恢复验证有证据。交叉编译和 mock 只记部分完成，不据此勾 E/F/P5/M4。
