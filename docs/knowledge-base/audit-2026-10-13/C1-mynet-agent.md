# C1 轨审计报告 — mynet_agent 仓自身（2026-10-13，只读审计）

范围：/Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_agent 全仓（cmd/、mynet/、mynetd/、gateway/、shared/、packaging/、deploy/、scripts/、docs/、Makefile、.github/、.githooks/、testdata/、secure-backup/）。
判定基准：SSOT 硬约束（mynetd 纯通信层、产物前缀、8096/8710、WebUI 三态构建、安全红线、禁不必要 fallback）。
口径：gnb 的 route.conf 是正式配置不列包袱；back = mynet_back 合法简写。本轮不修改任何仓库文件。

## 发现清单

[错漏|S1] 私钥经 argv 进执行器并回显到本机 API 响应 — 证据：mynetd/localapi/config_api.go:185（`argv = {"config","keys","set","--json","--private", body.PrivateHex}`，私钥上命令行）；mynetd/localapi/wizard.go:331-333（sanitizeWizardOut 显式放行 argv：「argv 里不带口令（走 stdin）」——该前提被 config keys set 打破）；mynetd/localapi/handlers.go:143-151（失败路径 `payload["argv"] = v` **不经 sanitizeValue**）；mynetd/localapi/handlers.go:34-52 + :96-107（`POST /api/v1/command` 直通浏览器 argv，allowedSubcommand 白名单含 "config"，故上述回显可达）— 影响：节点私钥可出现在 HTTP 响应体与同机 `ps` 中，违反「私钥不进响应/日志」与本仓自述「口令不走 argv（会进 ps）」（handlers.go:90）两条红线 — 处置建议（不执行）：keys set 改 stdin/0600 临时文件传递（对齐 auth login --password-stdin）；argv 一并走 redactSecrets 且对 `--private`/secret 关键字行强制遮蔽；补一条「失败响应不含 argv 秘密」的回归测试。

[违反设计|S2] mynetd 二进制链入整个执行器包（纯通信层失守，链接层面）— 证据：`go list -deps ./cmd/mynetd` 实证依赖 mynet（119 个文件的执行器包）、mynet/detector、mynet/driver、mynet/keys、mynet/upgrade；cmd/mynetd/main.go:23 import mynet，:145 调 mynet.SyncPluginProxyFromPolicy；mynetd/localapi/wizard.go:142,184-196 用 mynet.DeviceFingerprint / mynet.Credential / mynet.SaveCredential — 影响：常驻进程携带 install/apply/防火墙/驱动/升级全部能力，违反 mynetd/doc.go:7-8「不做系统能力」；且 cmd/mynetd/main.go:143 的注释「B2：…localapi 不 import shared/config / mynet」与现状不符（wizard.go 仍 import mynet）— 处置建议：把 SyncPluginProxyFromPolicy、DeviceFingerprint、credential 存取下沉为注入接口或独立小包（对齐 ProxyLoad/ProxySave 的注入做法），cmd/mynetd 纳入 guard B2 扫描面。

[违反设计|S2] mynetd/localapi 行为越界：gnb_ctl 解析、OS 路由查询、主机指标/进程探测 — 证据：mynetd/localapi/node_status.go:121-135（gnbPeerLinksOf → driver.GnbPeerLinks，gnb_ctl 对端解析跑在 mynetd 进程内）、:155-200（ifaceStats 读 /sys/class/net 计数器 + 解析 ifconfig = 主机指标采集）、:264（procUptimeSec 用 `ps -o etime=`）、mynetd/localapi/route_query_unix.go:26-64（ip route show / route -n get）、route_query_windows.go:24-58（route print -4 解析）、cmd/mynetd/tunnel_restore.go:72-105（读 gnb.pid + `kill -0` 判进程、决定拉起隧道 = 进程生命周期判据）— 对照 mynetd/doc.go:7-8 明列禁止项 — 影响：状态机判据在通信层自算一套，与执行器 detector/metrics 双源漂移（维度 2）— 处置建议：状态判据统一走 Ops（如 `mynet status --json`）或把 node_status/route_query 移出 mynetd 归执行器。

[违反设计|S2] guard B2 守护面缺口，上述越界全部静默通过 — 证据：.githooks/guard.sh:286-300 —— 只扫 `mynetd/*`（不扫 cmd/mynetd/*），模式 `"[^"]*mynet/(detector|tools|apply|metrics|service|keys)` 不含 `mynet` 根包（wizard.go:13 的 `"…/mynet"`）、不含 `mynet/driver`（node_status.go:13）、不含 cmd/mynetd/nodeface.go:16 的 shared/config；且 `added_in_file` 只看新增行，存量违规永不复扫 — 影响：README.md:12-15 与 mynetd/doc.go 的架构红线实际无守护 — 处置建议：扫描面扩到 cmd/mynetd/**、模式补 `mynet/driver` 与根包 `mynet"`，并对存量做一次性全量复扫（改 9 份 guard 须同步，见 CPI-36 维护说明）。

[违反设计|S2] make dist 的 openwrt 腿给 mynetd 带 embed，与 SSOT/文档/CI 三方矛盾 — 证据：Makefile:131（tags 非空时 `-tags "$$tags embed"`，而 openwrt 腿 tags=openwrt，见 Makefile:90）；对照 mynetd/doc.go:13-15（OpenWrt 无 embed）、README.md:31（none=OpenWrt 瘦构建）、.github/workflows/build_openwrt.yml「Build Go Go binaries」段（`-tags openwrt`，注释「mynetd 瘦，无 embed」）— 影响：make dist 产出的 mynet_agent_openwrt_*.tgz 里的 mynetd 违反「OpenWrt 不带 embed」；且与 CI 的 ipk 形态不一致（两套 OpenWrt 发行路径各说各话）— 处置建议：openwrt 腿去掉 embed，或按 build.yml:8-9 的口径直接砍掉 openwrt tgz 腿（OpenWrt 只出 ipk）。

[错漏|S3] 托盘启停打在 install 从不注册的服务标识上（macOS + Windows）— 证据：cmd/mynet_tray/control.go:39（launchd label `club.mynet.mynetd`）vs mynet/cmd_install_launchd.go:40（LabelMynetd=`com.mynet.mynetd`，:68-72 用它 bootstrap）；cmd/mynet_tray/control.go:44,181-191（Windows 走 `schtasks /run MyNetd`）vs mynet/cmd_install_win.go:174-181（SCM 服务 `mynetd` 为唯一常驻，且卸载时把 schtasks mynetd/mynet-gnb 当旧物删除）— 影响：CLI 安装后托盘「启动/停止」按钮对 launchd 而言目标不存在（no-op/报未注册），对 Windows 而言打的是已被取代的计划任务机制 —— 托盘启停即摆设（cmd_install_win.go:152 自己都注释过「托盘的启停按钮就是摆设」的历史坑）— 处置建议：服务标识常量收敛到 shared（与 install 共用），Windows 托盘改 SCM；本条是 CPI-73/CPI-74 的 agent 侧内证，可并账跟踪。

[错漏|S3] 产物仍带 webui.html，安装文档称已无此物 — 证据：Makefile:148（非 openwrt 平台 `cp mynetd/localapi/webui/index.html $$out/webui.html`）vs packaging/darwin/INSTALL.md:39 与 packaging/linux/INSTALL.md:28（「WebUI 已 embed，无 webui.html」）；mynet/upgrade/install.go:34（webui.html 不参与替换，「由 embed 烘进去」）— 影响：包里多一份 133030 字节的重复静态页（与 embed 内容重复），文档与产物不一致误导排障 — 处置建议：删 Makefile:148（embed 后无消费者；scripts/dev-push-0.32.sh:93 还在拷它，同批清）。

[错漏|S3] deploy/README.md 两处与实现漂移 — 证据：①「mynet_gateway 就在常规产物包里（make dist …3 二进制之一）」vs Makefile dist 循环只编 ./cmd/mynet、./cmd/mynetd（+ darwin/windows 的 mynet_tray），scripts/pack.sh:53 注释明写「客户端包不含 mynet_gateway」——gateway 无任何产物构建路径，照文档装必然缺件；②「NewRedisPresence 目前只被测试用」vs cmd/mynet_gateway/main.go:90 已按 env 装配（降级内存）——文档把活代码记成死代码 — 影响：生产主机部署（H8）会卡在第一步；读者误删 presence_redis.go — 处置建议：文档改为「gateway 需单独 go build ./cmd/mynet_gateway」（或给 Makefile 加 dist-gateway），presence 段更新为已装配+内存降级语义。

[错漏|S3] scripts/security_audit.sh 指向已改名的 mynet_back 仓 → 假绿与假红并存 — 证据：scripts/security_audit.sh:6（`BACK="$CLUB/mynet_back"`，workspace 现名 mynet_api）；A 节 `grep … "$AGENT" "$BACK" … 2>/dev/null | grep -q .` 在目录不存在时被吞、照样 ok「无明文密码」（假绿）；B/D 节对缺失文件 `grep -q` 直接 bad()（恒红）；F 节还要在 $BACK 里跑 guard — 影响：安全复检能力失效，且它不在 make test（Makefile:24-28 只跑 check-route-writers/check-no-back-url），无人发现 — 处置建议：改指 mynet_api 或把跨仓安全复检移出 agent 仓（归统一入口），并让脚本在目标仓缺失时 fail 而不是 ok。

[错漏|S3] 自更新链两个平台洞（CPI-76 现状确认，未修）— 证据：mynet/upgrade/install.go:39（agentBinaryNames = mynet/mynetd/mynet_gateway 裸名）+ :389-397（locateBinaries 逐个 `base == want` 精确比对）→ Windows 包内全是 *.exe 会判「产物里没有 …」；mynet/upgrade/install.go:39 同时列 mynet_gateway，而产物包根本不含它（见上条）；mynet/cmd_update.go:87-96（pickLatestMynetForPlatform 用 runtime.GOOS，OpenWrt 上选 "linux/mipsel" 通用包，不认 openwrt 平台/ipk）— 影响：Windows 自更新不可用；OpenWrt 会被塞通用 Linux 二进制（不带 openwrt 装配后端）— 处置建议：按 GOOS 补 .exe 名单（并去掉包内没有的 mynet_gateway 或把它打进包）；OpenWrt 更新走 opkg 协调（H4）。

[错漏|S3] shared/utils/paths.go HomeSource 判据退化 — 证据：shared/utils/paths.go:63-73，`if filepath.Base(filepath.Dir(exePath)) == "bin" { return "exe" }; return "exe"` 两分支同返回值，注释承诺的 env|exe|cwd 三态里 exe 分支恒等 — 影响：诊断信息误导（小）— 处置建议：删冗余分支或补 cwd 判据。

[冗余|S4] shared/errors 整包零消费 — 证据：shared/errors/errors.go（AppError/New/Newf/Wrapf/WithComponent/IsAppError/ErrorCode + 5 个 Err* 哨兵，121 行）仅被自身 errors_test.go 引用；`grep -r mynet_agent/shared/errors` 全仓除包自身外 0 处（代码里清一色用标准库 errors.New/fmt.Errorf）— 影响：死抽象 + 与实际错误风格两套 — 处置建议：整包删除，或让真实调用点先迁移（后者成本高、收益低）。

[冗余|S4] OS 路由查询/解析双实现 — 证据：mynetd/localapi/route_query_unix.go:26-64（`ip route show` / `route -n get` 解析）与 route_query_windows.go:17-58（`route print -4` 扫列）↔ mynet/apply_osroute_unix.go:20-90（bsdRouteGet 等）、mynet/apply_osroute_windows.go:53-79（routesOnDevice/defaultGateway 同样解析 route print）— 影响：同一能力两处实现、口径漂移风险，且其中一份落在 mynetd（叠加 S2）— 处置建议：查询收敛到执行器/共享探测层，localapi 经 Ops 取。

[垃圾|S4] 死代码 4 处 + 实验工具零引用 — 证据：shared/utils/paths.go:362 dirExists、cmd/mynet_tray/control.go:122 shellJoin、mynetd/localapi/handlers.go:214 systemJSONPath、cmd/mynetd/main.go:401 waitSignal —— 全仓 grep 各自仅剩声明行 1 处（含测试在内 0 调用）；cmd/keyprove/main.go 头注释自述「实验」，全仓（脚本/文档/Makefile）零引用 — 处置建议：删 4 个死函数；keyprove 与 mynet/keys/genvec 判存废（genvec 若保留应在 docs 注明「向量生成器」）。

[垃圾|S4] 死分支与冻结测试 — 证据：mynet/commands.go:137 `case "refresh":` 不可达（:93 `strings.HasPrefix(cmd,"refresh")` 已先 return cmdRefresh）；testdata/proxy-permission-loop/kernel_test.go.txt（测试改名 .txt 冻结，LOOP-3 已知账）— 处置建议：删死分支；.txt 测试给结论或挂独立 gate（不进基线是已裁定，但应有归属说明）。

[包袱|S5] 旧仓名/旧布局残留（注释与脚本）— 证据：scripts/e2e/relay_smoke.sh:2、cmd/mynetd/backclient.go:197、shared/domain/vpn_bundle.go:10、mynet/cmd_auth.go:126,254 注释引用 mynet_back（现 mynet_api）；scripts/security_audit.sh:6 见 S3 条；cmd/mynetd/main.go:132 注释「本机 8096/8097」含旧第二端口说法 — 影响：检索与排障指错方向（`back` 简写合法，问题在仓名与端口叙述）— 处置建议：注释统一现仓名；8097 说法核实后删除。

[文档|S5] README 漂移 3 处 — 证据：README.md:12-15 硬约束清单（detector/tools//infrastructure/coordinator/node//service//gopsutil）与 guard B2 实际清单（detector|tools|apply|metrics|service|keys，guard.sh:290）互不一致；README.md:27 「Makefile `pack` 就是这个」——Makefile 无 pack target（实际 `make dist`，pack 是 scripts/pack.sh）；README.md:31 「none=OpenWrt 瘦构建」与 make dist openwrt 腿带 embed 矛盾（见 S2 条）— 处置建议：README 按 guard 与 Makefile 现状重写那三行。

[文档|S5] docs/install-chain-status-20261002.md 引用已迁移路径 — 证据：该文「packaging/darwin/scripts/postinstall」——packaging/darwin 现仅剩 INSTALL.md（postinstall 已随 H2 迁 mynet_desktop）；文中其余改动表仍写 packaging/darwin 四平台 INSTALL「已改写」属历史快照 — 影响：按文索引找不到文件 — 处置建议：文首标注「历史快照（2026-10-02）」并把待办段与现状对齐，或移 docs/releases/。（docs/MYNET_V1_PLAYBOOK.md 归 B2，本轮未审。）

[文档|S5] mynet/driver/gnb.go:42 注释指向不存在的单元文件 — 证据：「DefaultGNBServiceUnit deploy/systemd/mynet_gnb.service 装出来的单元名」，而 deploy/systemd/ 现只有 mynet_gateway.service；mynet_gnb.service 的唯一源是 mynet/units/mynet_gnb.service（units_systemd.go:13 embed）— 处置建议：改注释指向 mynet/units/。

[核查通过|S5] packaging/darwin 退役残留清点 — 现状：packaging/darwin/ 只剩 INSTALL.md，无打包脚本/postinstall/plist 残留；且它仍被 Makefile:145-150 作为 darwin 产物的 INSTALL.md 消费，**不能整目录删**；packaging/darwin/INSTALL.md:58,163 的 club.mynet.tray 与 mynet/cmd_install_launchd.go:43 LabelTray 一致，无漂移（托盘 label 本身合法，问题见 S3 托盘条）。产物前缀 mynet_agent_*、本机 8096、gateway 8710（cmd/mynet_gateway/main.go:31）均与 SSOT 一致；`.gitignore` 覆盖 secure-backup/、*.private、credential.json；未见硬编码凭据（关键词扫描全仓仅 shared/utils/constants.go:58 的目录名常量）。

[过期|S5] usage 与实现不符的子命令 — 证据：mynet/commands.go:35 宣称「monitor 持续监控输出」，:155 `case "monitor": return cmdStub`（:171-176 打印「尚未实现」）；mynet/dns_system_windows.go:21 `listNetworkServices Windows 未实现` → `mynet dns` 在 Windows 恒报错（M4「DNS 保险丝」平台缺口）— 处置建议：usage 标注平台/实现状态，或按 dev-plan 补实现。

[冗余|S5] fixture 双源 + 跨仓相对路径 → CI 静默跳过（D2 现状）— 证据：mynet/driver/intent_fixtures_test.go:15-24 与 a8_dual_verify_test.go:28-38 只找 `../../.. /mynet_ctl/docs/knowledge-base/spec/fixtures/bundle-envelope-v2`，找不到 t.Skip；shared/domain/bundle_envelope_test.go:13-29 则 testdata 优先 + 找不到 Fatal —— 仓内已有 testdata/bundle-envelope-v2（git 已跟踪）却被 driver 测试无视；.github/workflows/build.yml 的 `go test ./...` 单仓 checkout 时这些用例全 skip（CI 绿不含 A5/A8 fixture 断言）— 处置建议：driver 测试改用 bundle_envelope_test 同款解析顺序（testdata 优先、缺失 Fatal）。

[测试|S5] 环境性 t.Skip 共 18 处 — 证据：`grep -rn t.Skip --include='*_test.go'` 18 行/14 文件；其中 fixture/向量缺失静默型 3 处（上条 + mynet/keys/node_face_vector_test.go:16）、平台/工具缺失条件型 15 处（darwin-only、/sbin/route 缺失、读不到接口等，属可接受但让「全绿」含水）— 本轮 `go test ./...` 全绿（24 包 0 fail，2026-10-13 实测），未见断言错误状态的测试（D4 未命中）— 处置建议：CI 输出补 `-run` 全量 + skip 计数可见化。

## 已知账命中

| 已知账 | 本轮结论 | 证据 |
|---|---|---|
| CPI-71/R1（Lua Ops 不满足合同） | 仍在：MYNET_EXECUTOR=lua 仍整套替换 Ops，Lua 侧只实现部分域 | cmd/mynetd/main.go:135-139、cmd/mynetd/executor_lua.go:27-39,251-291 |
| CPI-72/R2（UCI 兜底不幂等） | 代码仍在，本轮未重跑 fixture 复现 | mynet/cmd_install_openwrt.go（见疑点） |
| CPI-73/R3、CPI-74/R4（安装器与 CLI 机制/label 分叉） | 仍在，且补 agent 侧内证：托盘 vs install 的 label/机制分叉 | 见 S3 托盘条 |
| CPI-75/R5 | desktop 侧，不重复审 | — |
| CPI-76/R6（update 不识别 exe / OpenWrt 选 Linux 路径） | 确认未修 | 见 S3 update 条 |
| CPI-77/R7 | desktop/CI 侧；agent 的 build.yml 触发面已收窄（只 tag+手动+PR），未见 ref 浮动 fallback | .github/workflows/build.yml:14-23 |
| CPI-79/R9（init 按 PPID=1 杀 gnb_es） | **已修**：归属判据改「exe=本部署 gnb_es 且父进程是本实例 gnb」，孤儿显式 skip | mynet/units/openwrt_mynet.init:216-243 |
| CPI-81/R11（installer 全编入三平台） | **已修**：go list 三平台+openwrt 实证按 tag 分层 | cmd_install_win.go(windows)/cmd_install_launchd.go(darwin)/cmd_install_systemd.go(linux&&!openwrt)/cmd_install_openwrt.go(linux&&openwrt)/cmd_install_unsupported.go；对照 workspace-review R11 原描述已不成立 |
| CPI-82/R12（install 先 requireHome） | 有修复迹象（注释与 resolveInstallHome），本轮未 dry-run 复核 | mynet/cmd_install.go:91,143（见疑点） |
| CPI-45（WebUI 非 Vue 构建） | 机制仍在，但证据需更新：webui/index.html 现为 133030 字节手写页（含 IA 壳层），非旧述 5401 字节；mynet_gui SPA 另经 /gui/ 嵌入 | mynetd/localapi/gui.go:1-22、webui/ 三文件 |
| LOOP-3（kernel_test.go.txt 未进基线） | 仍在 | testdata/proxy-permission-loop/kernel_test.go.txt |
| O1（init 单一源码方向） | agent 侧只有一套模板：mynet/units/{mynetd.service, mynet_gnb.service, openwrt_mynetd.init, openwrt_mynet.init}，units_systemd.go/units_openwrt.go 按 tag 各 embed；无 rc.mynet 残留、无 Lua 启停脚本残留在 agent 仓（Lua Ops 执行器属 CPI-71 另账） | mynet/units.go:1-9、units_systemd.go:10-13、units_openwrt.go:14-17 |
| packaging/darwin 已退役（H2） | 残留仅 INSTALL.md 一份且仍被产物消费 | 见「核查通过」条 |

## 疑点待验证

1. CPI-72（cmd_install_openwrt.go:243-274 UCI 事务/幂等）本轮只做静态确认，未重跑隔离 fixture 复现，状态以 R2 为准。
2. CPI-82（`mynet install --home` 解析次序）本轮未跑 dry-run 实证；resolveInstallHome 的存在与注释表明确已改，需一次 dry-run 验收后销账。
3. secure-backup/ 下 10 个密钥材料（含 *.private、back_face_priv）明文在仓**工作树**（git ls-files 确认未入库，.gitignore 已排除、guard A3 拦提交）；建议迁出仓树，但是否清理属用户资产，未动。
4. mynetd/localapi/conf/credential.json（0600、gitignored）为本机会话残留（token 为样值形状），未入库；注意勿误提交。
5. `strings.SplitN(cmd,"-",2)[0]` 前缀分派（mynet/commands.go:98）使 "monitor-x"/"check-x"/"config-foo" 会落进基础命令分支且丢失原词——未找到现存调用方受影响，低优先级。
6. mynet/units/openwrt_mynet.init 与 mynet_openwrt 仓包内 Lua/init 是否存在跨仓双份（O1 的另一半）——跨仓比对归 B1 轨。
7. gateway 的 X-Node-Id 冒充修复（b7aceeb）与 events 合并竞态修复本轮未复核行为，仅确认代码在位。

## 覆盖说明

- 文件面：mynet_agent 全仓 322 个 .go（含测试）/448 个文件全量检索；重点逐读 cmd/mynetd、mynetd/localapi、mynet/{commands,cmd_install*,cmd_update,units*,upgrade}、cmd/mynet_tray、shared/utils、Makefile、guard.sh、packaging/、deploy/、scripts/、docs/、.github/。
- 零调用判定复核面：`go list -deps ./cmd/mynetd`（依赖树）、三平台 + `-tags openwrt` 的 go list 文件清单（build tag 互引）、全仓符号 grep（含 _test）、命令注册面（mynet/commands.go switch、mynetd/commands/commands.go Op 映射表、localapi allowedSubcommand 白名单、HandleFunc 路由表）、Makefile/两个 workflow 构建目标、脚本引用。
- 只读实测：`go test ./...` 全绿（24 包，0 fail/skip 计数未展开，2026-10-13）；`go list` 交叉核对；字符串检索为静态检查已注明。
- 未做：实机安装/升级/隧道验收、Windows/真机 OpenWrt、生产 gateway 部署；仓间契约（B1）与知识库文档、V1_PLAYBOOK（B2）不重复审。
- 统计：S1×1、S2×4、S3×6、S4×4、S5×8（含 1 条核查通过），共 23 条；已知账命中 12 项（其中 CPI-79、CPI-81 两项本轮证实已修）。
