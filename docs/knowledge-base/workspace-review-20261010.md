# Workspace 现状与跨平台审查（2026-10-10）

> 范围：9 个仓的职责、构建/安装/运行/升级链、当前测试与关键跨仓接口。不是逐行穷尽审计，也不是实机/生产验收。
> 本轮只修改规划、报告与导航；未修运行时、提交/推送、部署、安装驱动或修改目标设备。
> 行为处置见 [跨平台规划](spec/platform-lifecycle-build-playbook.md)，OpenWrt 详见 [平台规划](../../../mynet_openwrt/docs/AGENT_INTEGRATION_PLAYBOOK.md)。
>
> **R 项处置状态（2026-10-13 归位）**：R2–R5、R7–R12 **已关闭**（对应 `spec/lifecycle-contract.md`
> G 表 G-1/2/3/4/5/8/9/10/11/12/14/15 与 `cross-project-issues.md`「已修复」表 CPI-72~82 行，附证据）；
> **仍开放：R1**（CPI-71，OpenWrt Lua Ops 不满足新 connect/config 合同，归 O1）与
> **R6**（CPI-76，update 不识别 exe、OpenWrt 仍选 Linux 裸更新路径）。本报告是 2026-10-10 快照，
> 下文「均未修复」的判定已被本注取代。

## 1. 当前基线与用户澄清

用户确认：.178 是公司节点、当前网络的公司主路由；.182 是家里的旁路由。两者不是同一 LAN 的主/旁路组合。不能把家庭 .182 当作当前公司的随手测试设备。review 不授权中断任何一台。

| 仓 | HEAD | 本轮看到的工作树与关注范围 |
|---|---|---|
| agent | 50c1b58 | 前轮 P5 规划文档修改；本轮审 installer、driver、apply、upgrade、mynetd 装配与构建 |
| OpenWrt | f7c956e | 既有 README 修改和未跟踪 .178 升级计划；前轮规划/导航。审 LuCI/Lua/ubus/包/procd/fw |
| desktop | 4ed21f2 | 干净；审三平台安装器、系统单元、发行 workflow 与检查脚本 |
| GUI | 26d8562 | 干净；核用户前端/嵌入构建、API 接入、引导 tests 与类型基线 |
| API | 76e1214 | 干净；核统一 bundle/Proxy 授权/版本发布责任与默认测试套件 |
| ctl | 084496c | 前轮状态与项目卡指针修改；核发行命名/上传、知识库与测试 |
| GNB | 8734260 | bare 仓，真实工作树 `.wt/gnb-actions` 干净；审补丁链、Windows Wintun 与发行责任 |
| UTC | 79443b6 | 正在进行的 gateway_session、测试和 milestone 修改，含未跟踪文件；只读纳入审查与测试，不改其任务和范围 |
| web | dc3ad08 | 干净；核职责/构建/检查，保留既有 CPI-40 |

## 2. 优先问题（2026-10-10 快照；处置状态见文首归位注 —— R2–R5/R7–R12 已关闭，R1/R6 仍开放）

P1 为会阻断安装/接入/发行或造成错误副作用的项；P2 为结构与验收债。静态结论、隔离 fixture 和实机验证严格分开。

### R1 [P1] OpenWrt 实际 Ops 不满足新 connect/config 合同

`MYNET_EXECUTOR=lua` 替换整套 localapi Ops（`mynet_agent/cmd/mynetd/main.go:126-129`）；Lua config 只返回简单状态且忽略子命令（`cmd/mynetd/executor_lua.go:271-285`）。connect 要求 initialized/mode/active 及 keys ensure/sync/pull（`mynetd/localapi/connect_api.go:48-88`），Lua apply 也忽略 active 与 auto 的区别（`executor_lua.go:180-193`）。因此直接桥接桌面接入入口不会闭环。既有 TestConnectFlow 用 fake Ops，不会发现此断层。静态确认，未做真实 LuCI/Lua复测。归 O1/H1–H2。

### R2 [P1] OpenWrt UCI 兜底新装失败，重跑 forwarding 不幂等

无 firewall.mynet 脚本时，先 `uci add firewall zone`，却丢弃其 section ID；下一步只找 name=mynet 的 section，新增未命名 zone 因而找不到并退出（`mynet_agent/mynet/cmd_install_openwrt.go:243-266`）。两个 forwarding 检查把 src 与 dest 放在同一行 grep，而 `uci show` 分行输出，已有规则也再次添加（`:267-274`）。

本轮提取原命令，在临时目录用符合 UCI 输出形状的 stub 执行：新 zone 创建 rc=0、配置 rc=1；已有 lan→mynet forwarding 又增加 1 个 section。这是隔离输出 fixture 复现，不冒充 OpenWrt 真机测试。归 H2；修复须同时覆盖事务/幂等/冲突，不能只让首次返回 0。

### R3 [P1] Windows 安装器与 CLI 注册两种常驻机制

NSIS 注册 MyNetd 计划任务并用 ProgramData（`mynet_desktop/nsis/mynet-agent.nsi:83-111`）；CLI 注册 SCM mynetd，未传 home 时用 prefix（`mynet_agent/mynet/cmd_install_win.go:26-28,170-206`）。既有安装后再运行 CLI 可留下计划任务与 SCM 两条启动链、不同数据上下文；卸载器也只按各自机制清理。归 H2，迁移旧任务、home 与托盘入口须同批收敛。静态确认；未安装 Windows。

### R4 [P1] macOS 安装器与 CLI 的 label/path/home 不一致

pkg 交付 `/opt/mynet`，注册 `club.mynet.mynetd`、home=/var/lib/mynet（`mynet_desktop/macos/build-pkg.sh:49-56`、`launchd/club.mynet.mynetd.plist:6-17`、postinstall）；CLI 默认 prefix=/usr/local/opt/mynet，注册 `com.mynet.mynetd`（`mynet_agent/mynet/prefix_unix.go:8-13`、`cmd_install_launchd.go:39,72-87`）。显式 prefix 正确也不会清 club label，两个服务可能争同一 localapi；默认 prefix 则先找错二进制。归 H2，服务迁移/数据路径先冻结。静态确认；未调用宿主 launchctl。

### R5 [P1] desktop Linux 单元缺乏节点模式所需身份、环境与权限路径

desktop 单元 User=mynet、NoNewPrivileges=true、无 MYNET_HOME env（`mynet_desktop/systemd/mynetd.service:10-18`），安装脚本没有创建该用户或设置状态目录 owner（`scripts/install-linux.sh:18-49`）。它声称按需 sudo，但当前执行器并无这条授权链，NoNewPrivileges 也不允许子进程借 sudo 获取特权。agent 单元明确节点模式需特权且加载 env（`mynet_agent/mynet/units/mynetd.service:34-62`）。于是两套服务安装语义冲突：用户缺失时起不来，预先有用户也未证明能起 TUN/写路由。归 H2；保留现有节点模式策略，权限隔离若另立项必须有真实子进程能力合同。静态确认，未跑 Linux systemd。

### R6 [P1] update 仍未覆盖 Windows 文件名和 OpenWrt 原生包模式

更新器可选 windows/amd64，但 locateBinaries 只匹配 mynet/mynetd/mynet_gateway，无法识别包里的 `.exe`（`mynet_agent/mynet/upgrade/install.go:33,349-358`）。OpenWrt 的选择仍按 runtime.GOOS=linux（`mynet/cmd_update.go:87-96`），安装器默认直接替换文件，没有 opkg协调；新 OpenWrt 产物发布后不能沿此链更新。归 H4，需包种类/平台 variant/占用文件/服务回滚，而不是只在索引加 openwrt。

### R7 [P1] desktop 发行 workflow 输入未跟上 GUI embed 构建

workflow 只 checkout desktop/agent 后跑 make dist；agent make dist 已要求兄弟 GUI dist/spa 且固定 /gui/assets base（`mynet_desktop/.github/workflows/release.yml:84-117` 对照 `mynet_agent/Makefile:80,101-112`）。全新 runner 未准备 GUI 构建产物，静态判定构建前置缺失；macOS job 下载的是 dist，不是 agent 源码，却尝试 go build ./cmd/mynet_tray（workflow package-macos）。另同名 agent ref 失败会退到默认分支却仍给发行版本同名（`:89-105`），版本来源不可靠。归 H4；本轮未触发 CI，不能声称最近一次线上 job已失败。

### R8 [P1] ctl 实际不能解析已规划的原生安装器格式

parseFilename 正则只接受 tgz/zip/tar.gz/ipk（`mynet_ctl/app/Domain/Release/Services/ReleaseDomainService.php:198`），与 desktop 文档“exe/dmg/pkg 已纳入解析”不符。直接调用纯 domain：exe/pkg/dmg 均返回 null，ipk/zip 正常。另 upload 把 variant 作为 generateFilename 的 ext 参数（`app/Services/Release/ReleaseService.php:30-32`），说明“变体”和“容器格式”混用，未来 OpenWrt/安装器必须先锁语义。归 H4，真上传→索引→下载测试补齐，现有 filename 单元测试不足。

### R9 [P1] OpenWrt gnb_es 清理可能命中其他实例孤儿

内嵌 init 遍历所有同名 gnb_es，满足可执行路径匹配 **或** PPID=1 就杀（`mynet_agent/mynet/units/openwrt_mynet.init:137-146`）。PPID=1 只表示孤儿，不能说明属于当前节点，停止一个实例可能杀另一节点孤儿。归 H3/O3；用本实例路径、父进程与运行目录等可验证归属。静态确认，未真实杀进程。

### R10 [P1] GNB Wintun DLL 缺失路径会调用未初始化函数指针

InitializeWintun 在 LoadLibraryExW 失败时直接返回 NULL（`.wt/gnb-actions/src/mingw/gnb_drv_wintun.c:69-76`）；caller 的 NULL 分支却调用尚未解析的静态 WintunDeleteDriver（`:118-123`）。缺 DLL 的首次启动会走空函数指针调用，不能得到普通依赖失败。归 H3 的依赖预检/错误恢复；需要上游修复或既有补丁包，不直接改 src。静态确认，Windows 实机复现未做。

### R11 [P2] installer 文件尚未按平台条件编译

三平台 go list ./mynet 的实际结果一致：cmd_install.go、cmd_install_launchd.go、cmd_install_openwrt.go、cmd_install_win.go 全部编入。`_win` 不是合法 GOOS 后缀，无 build tag；运行时分支在公共文件分派（`mynet_agent/mynet/cmd_install.go:84-104`）。三平台构建成功只证明可编译，不能证明包内动作分层。归 H1；OpenWrt用 linux+openwrt 标签，运行期能力探测仍保留。

### R12 [P1] install --home 不能替代环境变量

cmdInstall 在解析 --home 前调用 requireHome（`mynet_agent/mynet/cmd_install.go:30-50`）。本轮不设置 MYNET_HOME，执行 `mynet install --home /tmp/mynet-review-no-write --dry-run`，rc=1 报 MYNET_HOME 未设置，未写目标目录。参数明明提供了路径也无法进入安装，与安装指南/调用方合同不符。归 H0/H2，解析次序与环境冲突口径先定。

## 3. 其余仓的状态与限制

- API 的 Proxy 权限、事务/版本发布和 wire 权威已有实现，仍应复用；默认套件绿不证明真实多节点流量与 OpenWrt规则兼容，不扩造第二套鉴权。
- GUI 测试与类型棘轮可运行；41 tests 已超过旧记忆中的20。仍有194条类型错误/38文件，只是未增加；本轮没有声称类型零错或生产浏览器流程全部通过。
- GNB 主通道补丁可在纯上游导出树应用、幂等、包内52项测试通过。此结果不覆盖 Windows Wintun错误路径、全部 target、真实隧道或四程序发行兼容。
- UTC gateway_session 正在其他工作中，PHASE7 明确只冻结会话内核，未实现 TCP/TLS/HTTP/WebSocket、gateway/后端实际接入和生产认证。测试通过不构成 agent↔UTC集成完成；本轮不扩大UTC实施任务。
- web 的构建占位为已知 CPI-40，且缺 node_modules 导致单测跳过；其代码里仍有历史 local/social页面，与官网定位不一致。本轮不合并/删除，保持既有职责待裁定。

## 4. 本轮验证

全部是本机/隔离验证，不是线上验收。只列实际执行范围，缓存结果不冒充新实机。

| 仓/范围 | 命令与结果 | 限制 |
|---|---|---|
| agent | `make check` 通过；Go 包测试绿；darwin/linux/windows amd64 的 `go build ./cmd/mynet` 分别通过 | guard 主要扫描工作区本轮修改；不等于全历史安全审计；交叉编译不运行目标程序 |
| API | `make test`：471 tests /4690 assertions /1 skip | 默认套件，本轮没重跑 MySQL并发组；skip不算通过 |
| ctl | `make test`：54 tests /426 assertions /1 skip | 一项跳过；并无原生安装器全链上传验证 |
| GUI | `npm run test:unit`：4文件/41 tests通过；`npm run check:types`：194错误/38文件，基线194，棘轮通过 | 有类型债；未做浏览器实测 |
| desktop | `sh scripts/check.sh all`：39通过/0失败/0跳过 | 主要语法/安装脚本fixture/模板字段；不能拦跨仓服务合同分叉 |
| OpenWrt | `make -f Makefile.guard lint test` 退出0；打包缺件守卫通过 | 本机Lua5.4，目标Lua5.1未验；ubus/rpcd/SDK和接口没有实际运行 |
| GNB | 真工作树 `make -f Makefile.guard test`：main通道应用/幂等、52项包内单测通过 | 未跑目标Windows/OpenWrt实机 |
| UTC | `make test`：19门禁项通过，实际 tests/Makefile 默认run执行15个测试程序（含当前gateway_session） | wrapper摘要计数是检查项，不能当作所有断言数或实际网络接入证据 |
| web | `make test` 退出0，报告2检查通过/1已知构建缺陷/1跳过 | 单测未运行；不是测试通过 |
| 小范围复现 | ctl格式解析，三平台go list，install --home dry-run，UCI原命令输出fixture | 无生产状态写入；UCI fixture不是实机 |

## 5. 下一步

先锁 H0 的分层与平台矩阵，再做 H1/H2；发行输入与解析 H4可先准备。OpenWrt原生入口在稳定的安装/执行合同上推进。现有测试绿保留为基线，同时增加能检测服务分叉、真实执行器合同、首次/重复系统登记、缺DLL、包种类与恢复的反证测试。

不据本报告勾平台完成、不自动升级.178/.182、不改变UTC当前未提交工作、不清理历史目录或凭证。修复逐项记录证据后再更新对应台账。（2026-10-13 归位：R2–R5、R7–R12 已修复并移入 `cross-project-issues.md`「已修复」表；R1/R6 仍开放，见文首注。）
