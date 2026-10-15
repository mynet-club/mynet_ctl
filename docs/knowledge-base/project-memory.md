# 项目记忆（2026-10-10 刷新，上一次 10-08）

## 持续有效的约定

- workspace：`/Users/arthur/Devel/WorkSpaceAI/mynet-club`。业务后端为 `mynet_api/`，旧目录 `mynet_back/` 已改名。
- `mynet_utc` 暂时搁置，不纳入当前实现、验证或交付范围（用户 2026-10-08 明示）。
- 现网库名仍为 `mynet_back`，引擎 MariaDB 10.11.18；源码更名不构成数据库更名授权。历史归档、备份和数据库引用保留原名。
- 知识库在 `mynet_ctl/docs/knowledge-base/`；v1 执行基线在 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md`。冲突时按最新明确裁定与代码取证判定，不能按旧勾选推定完成。
- `mynet_gnb/` 是裸仓；工作树为 `.wt/gnb-actions`。上游源码只读，改动走补丁包。
- 实际用户前端为 `mynet_gui`；`mynet_web` 定位在旧文档中有冲突，不据其 README 发起合并。
- mynetd 纯通信、系统能力归执行器；gateway 不鉴权；凭证走 crt/crtvault，不读凭证库。
- 2026-10-10 用户确认部署身份：.178 为公司节点、用户当前网络的公司主路由；.182 为家里的旁路由。两者不属同一 LAN，不自动将 .182 指定为当前网络试验机；本轮未升级或重启设备。

## 当前基线（本地提交取证，非本轮测试或线上验收）

**2026-10-13 刷新（治理批 G0–G6、G4、G5 可审阅面；G7 收口）**：

| 项目 | HEAD | 未推送 | 工作树 | 最新事实 |
|---|---|---|---|---|
| mynet_agent | 49340c0（10-13） | 13 | **11 项他人未提交**（托盘/服务名/paths 等） | G3 localapi B2 CLEAN；B2 变异 10/0；nodeface sign；向量硬失败 |
| mynet_api | 5f1baae（10-13） | 22 | **1 项他人未提交（TestCase）** | Default **511/4874/1skip**；CommandVocabulary；BootstrapMirror；NodeFace 向量 |
| mynet_ctl | 428fdfa（10-13） | 17 | 干净 | Release **82/467/1skip**；双件+kind 唯一键；deploy plan；G4/D4-A/B |
| mynet_gui | 4fd64b9（10-13） | 16 | 干净 | G2 鉴权/per-key/history；vitest 58/58 |
| mynet_desktop | 34a6ce5（10-13） | 2 | 干净 | N-01 guard |
| mynet_openwrt | b627819（10-13） | 6 | 干净 | G5.a 心跳 C-2 委托 nodeface sign；heartbeat.sh 已删 |
| mynet_gnb | 8734260（10-04） | **2** | 裸仓 `.wt/gnb-actions` | guard 模板同步未推 |
| mynet_utc | 79443b6（10-04） | 1 | 他人 gateway_session 进行中 | 搁置 |
| mynet_web | dc3ad08（10-04） | 1 | 他人 guard.sh | 不动 |

未推送 = `git log --branches --not --remotes`。他人未提交件不得覆盖。

**本机测试（2026-10-13）**：api Default **511 tests / 4874 assertions / 1 skip**；ctl **82/467/1skip**（skip = sqlite 并发，需 MySQL 双连接）、`make test-mysql` **172 passed /
765 assertions**；agent `go build ./...` + `go test ./...` 19 包全绿；ctl 54 tests / 426 assertions /
1 skip；gui vitest 20 passed。
⚠️ 裸跑 `vendor/bin/phpunit`（不带 `--testsuite`）只出 **454** —— `phpunit.xml` 套件定义有重叠
目录、默认套件才是 `make test` 用的那个，**计数一律以 `make test` 为准**。

P3 已裁定收口：自家节点自动组网、不限制；不建 planner、不加容量闸门；重复护栏保持代码判断，不加唯一索引。
P4/M4 未收口：OpenWrt 未接统一 Proxy 合同、回程缺口、离线与错误出口证据不足、console 公共出口线未开。
GUI 的 20 测试通过与类型基线 202 错误/39 文件来自既有实施记录，不是“类型零错误”。

## 路径定稿（2026-10-14，用户裁定）

- **unix 系（linux/mac/bsd）prefix = home = `/opt/mynet`**；OpenWrt prefix=`/usr`、home=`/etc/mynet`；Windows 维持 `C:\Program Files\MyNet` / `C:\ProgramData\MyNet`。
- **系统级唯一，无用户级模式**（节点守护要 TUN/路由/密钥）。
- **`/var` 只放运行时**：目标运行投影 `/var/run/mynet/<nodeId>/`（gnb `-c`、map/pid）；**不是 home**。
- **用词**：conf 里 apply 渲染出的驱动文件 = **原生配置**；**投影**专指 `/var/run` 运行副本。
- 代码已改默认值：`paths.go` `PlatformDefaultHome/Prefix`、`prefix_unix.go`、tray/install 解析；`GetMyNetHome` 不再猜 exe/cwd（env > 平台默认）。
- **运行投影已实施（同日）**：`projectToRun` 把原生配置投到 `/var/run/mynet/<nodeId>/`；`gnbConfPath` → gnb `-c` 指投影；`service start` 先 `ApplyFromActive` 再起进程；`MYNET_RUN_ROOT` 可覆盖（测试无 root）。Windows 无独立投影，仍指 conf 节点目录。
- 装机现场若仍在 `/var/lib/mynet` 等旧树，视为 legacy，待迁移。

## 下一次开工

**跨平台规划入口（2026-10-10，整体 workspace review）**：读 [审查报告](workspace-review-20261010.md) 与 [安装/运行/构建规划](spec/platform-lifecycle-build-playbook.md)。发现安装器/CLI 的 Windows 常驻机制、macOS label/path/home、Linux权限/env分叉，OpenWrt UCI兜底与Ops合同断层、条件编译/自更新/发行解析缺口；均未修复。下一步 H0→H1/H2，发行 H4 可准备，OpenWrt O1/O6 遵循这些共同合同。最新本机验证：agent make check通过、API471/4690/1skip、ctl54/426/1skip、GUI41 tests与类型棘轮194条/38文件；desktop39项、GNB主通道52项包内单测、UTC实际测试入口通过；web单测缺依赖跳过、OpenWrt目标Lua5.1未验。UTC工作树另有gateway_session进行中变更，本轮只读review与测试，不改其工作；旧“搁置”描述不代表其当前代码无进展。

**H0 生命周期合同（2026-10-10 本轮，只开发 / 不实施 H6 现网迁移）**：
- 合同文档已落 [spec/lifecycle-contract.md](spec/lifecycle-contract.md)：A–F 动作分层、逐动作合同、默认路径/服务名矩阵、原生包与更新归档矩阵、现状差距表 **G-1…G-14**（对齐审查 R1–R12）。
- agent fixture 已落 `mynet_agent/mynet/lifecycle_contract_test.go`（动作矩阵 + 差距台账 + 当前平台登记名/prefix 比对）；`go build ./...` / `go test ./...` 全绿、`make guard` 通过。
- 本轮新增**未提交**件：ctl 合同/索引文档（`spec/lifecycle-contract.md` 及 README §1.2、spec/README、paths.md、service.md、playbook §5、本文件）与 agent `mynet/lifecycle_contract_test.go`；两仓另有**前轮遗留**未提交的规划/导航文档（ctl `workspace-review-20261010.md`、`platform-lifecycle-build-playbook.md` 等；agent `docs/MYNET_V1_PLAYBOOK.md`）。均未 push。
- **用户 2026-10-10 明示**：已脱离 .178 环境，本轮**只开发**、不做 .178 升级测试（即 H6 不实施）。下一工作包 = **H1 编译与平台入口**（平台文件 build tag 互斥 + 共享入口 + go list 固定文件集合），入口见 playbook §5。

**H1 编译与平台入口（2026-10-10 本轮，agent 仓）**：
- 平台安装器文件按 build tag 互斥：新增 `cmd_install_systemd.go`(`linux && !openwrt`) / `cmd_install_unsupported.go`；`cmd_install_launchd.go`→`darwin`、`cmd_install_win.go`→`windows`、`cmd_install_openwrt.go`→**`linux && openwrt`**；`isOpenWrt()` 移到共享 `openwrt_detect.go`(`linux`)。
- **收严（2026-10-10）**：OpenWrt 与通用 Linux 装配**不共享**（目录/服务/防火墙都不同），OpenWrt 后端不再夹带进通用 Linux 二进制；通用 Linux 包在 OpenWrt 上**拒绝装配并给指引**。补上漏掉的接线：OpenWrt 产物构建改用 `-tags openwrt`（`build_openwrt.yml`），并加 fixture `TestOpenWrtBuildUsesOpenWrtTag` 锁住。
- 共享入口 `cmd_install.go` 收敛为「解析 + 校验 + 委派」`platformInstall/platformUninstall`；新增 `platform_descriptor.go` 的 `PlatformDescriptor` 并接入 `mynet platform`（多打 platform/pkg/service/capab 行）。
- 新增 fixture `mynet/platform_filetags_test.go`：`go list` 断言每平台恰一个入口文件、共享入口恒在。测试按平台拆到 `cmd_install_{systemd,launchd}_test.go`，openwrt 测试加 `linux` tag。
- 验证（本机 darwin）：`go build ./...` + `go test ./...` 全绿；`make guard` 通过；gofmt 干净；**四平台交叉编译与测试面编译**（linux/linux+openwrt/darwin/windows）通过。**未实机**：linux/Windows 测试仅交叉编译未运行；未动 Windows/macOS 安装器接线。
- 合同差距表：**G-2 关闭**，新增 **G-15**（通用 linux 包在 OpenWrt 卸载遗留 init/zone，待 H2）。
- 未提交、未 push。下一工作包 = **H2 主机准备一致性**（单一服务/路径来源、安装器调用 install、`--home` 无环境可用、各平台权限/env 收敛）。

**H2 主机准备一致性（部分，2026-10-10 本轮，agent 仓）**：
- **home 单一口径**：新增 `resolveInstallHome`（`--home` > `MYNET_HOME`；冲突或都缺即拒绝；不回退 prefix/exe/cwd），install 与 uninstall 共用（修 R12/G-1）。
- **默认 home 对齐合同**：`utils.PlatformDefaultHome()` 改返回真实 home（`/var/lib/mynet`、`C:\ProgramData\MyNet`），与 prefix（`/opt/mynet`、`C:\Program Files\MyNet`）分离；`cmd/mynetd/service_windows.go` 缺省 home 同步改 ProgramData（G-14）。
- **OpenWrt 装配独立**：`uninstallOpenWrt` 在 `cmd_install_openwrt.go`（`linux && openwrt`）；通用 linux 的 `platformInstall/platformUninstall` 探测到 OpenWrt 即 `refuseOpenWrt()` 拒绝并给 opkg 指引（不再走 systemd 半装配）→ 关 G-15。
- 测试：`cmd_install_test.go` 改为四例（gateway 必需 / `--home` 无环境可用 / 都缺拒绝 / 冲突拒绝）；合同 fixture 加 home 断言、删已关闭的 G-1/G-14/G-15。
- 验证（本机 darwin）：`go test ./mynet/ ./shared/... ./cmd/...` 全绿；四平台交叉编译与测试面编译通过；`make guard` 通过。**未实机**：linux/Windows 仅交叉编译。
- 合同差距：**G-1 / G-14（CLI 侧）/ G-15 关闭**。**H2 剩余**：安装器调用 install、Windows SCM 单常驻、macOS label/path/home、Linux `User=mynet`/权限、OpenWrt UCI 首装与幂等（G-3/G-4/G-5/G-11）。
- 未提交、未 push。下一工作包 = **H2 剩余**（安装器与 CLI 收敛）或按需先做 **G-11 OpenWrt UCI**。

**H2 — 安装器「薄壳委托」（2026-10-10 本轮，mynet_desktop 仓）**：用户裁定**安装器归 `mynet_desktop`、保留图形化安装形态**（降低门槛），但必须是薄壳：**系统登记一律委托 `mynet install`**。已落 macOS：`macos/scripts/postinstall` 改为调 `mynet install`（含 legacy `club.mynet.mynetd` bootout+删 plist）、`preinstall` 停 legacy+新 label、`build-pkg.sh` 不再往 payload 放 plist、根 `launchd/club.mynet.mynetd.plist` 退役、`check.sh` 的 `lint_launchd` 换成 `lint_macos`（断言"pkg 不自带 plist / postinstall 委托 install / preinstall 停两 label"）。验证：`sh scripts/check.sh lint` **29 通过 / 0 失败**。**Windows**：NSIS 不再 `schtasks`→委托 `mynet install`(SCM)/`mynet uninstall`，`service.ps1` 删除。**Linux**：`systemd/mynetd.service` 删除、`install-linux.sh` 改委托。**退役** `mynet_agent/packaging/darwin`（`make pkg-darwin` 移除，`INSTALL.md` 改指 desktop）。desktop `check.sh` 加 `lint_macos`/`lint_nsis`/`lint_linux` → **`check.sh all` 36 通过 / 0 失败**；两仓 Go 测试/guard 绿。合同差距 **G-3/G-4/G-5 关闭**。**H2 dev 面完成**（仅剩 macOS 旧 label 的现网迁移验证，需真机）。未提交、未 push。

**H2 — G-11 OpenWrt UCI（2026-10-10 本轮，agent 仓）**：
- `openWrtUciSteps` 重写：① zone 步骤**捕获** `uci add firewall zone` 的 section 名（旧实现丢进 `/dev/null` 再按 `name='mynet'` 反查 → 首装必 `exit 1`）；② forwarding 幂等改为逐 section 读 `src`/`dest` 比对（旧单行 `grep "src=.*dest="` 对 `uci show` 分行输出永不命中 → 每次重复添加）；③ section 名统一按 `awk -F"[.=]"` 取（`-F.` 会把 `=类型` 粘进字段 → 伪 id）。
- 本地验证：**stub uci** 探针六场景（空配置首装成功 / 重跑幂等 / 具名 zone 复用 / 匿名 `@zone[0]` 复用 / 已有 forwarding 不重复 / 带 dev 两处写入）。
- 测试：`cmd_install_openwrt_test.go` 加 `TestOpenWrtUciStepsFirstInstallThenIdempotent`（真跑步骤序列）+ 结构防线 `TestOpenWrtUciZoneStepCapturesSectionID`。**只能在 linux 跑** → 新增 `make test-openwrt`（非 linux 只编译校验）并把 `go test -tags openwrt ./mynet/` 接进 `build_openwrt.yml` CI。
- 合同差距 **G-11 关闭**；fixture 台账同步删 G-8/G-11。未提交、未 push。

**H4 发行（部分，2026-10-10 本轮，agent 仓）**：
- `Makefile` `DIST_MATRIX` 改为五元组 `platform:goos:goarch:ctlarch:tags`，新增 **openwrt 归档变体** `openwrt:linux:mipsle:mipsel:openwrt` → 产物 `mynet_agent_openwrt_mipsel_<ver>.tgz`（`-tags openwrt`，无 embed）。
- dist 循环改为**不依赖词分割**的显式 if/else（探针曾在 zsh 下暴露 `-tags embed` 被当单参数）；openwrt 变体不拷 `webui.html`；INSTALL.md 按 `packaging/<platform>/`。
- `packaging/openwrt/INSTALL.md`：手工落位改指 `mynet_agent_openwrt_mipsel_*`，并写明通用 linux 包在 OpenWrt 上会拒绝装配。
- fixture：`TestOpenWrtBuildUsesOpenWrtTag`（build_openwrt.yml 带 `-tags openwrt`）、`TestDistMatrixHasOpenWrtVariant`。
- 验证：`/bin/sh` 端到端探针产出 4 变体（tgz/zip），后端取证 linux=systemd / openwrt=procd / darwin·win=无；`go test ./...` 全绿、`make guard` 通过。
- **H4 剩余**：ctl `ReleaseAdminController` 上传白名单缺 `mynet_agent`（`in:tui,gnb,utc,client`）、`variant` 被当 `ext` 传参、`parseFilename` 不收 exe/pkg/dmg（R8/G-8）；desktop workflow 输入（G-12）。**ctl 侧未改**。

**H4 发行 — ctl 侧（2026-10-10 本轮，mynet_ctl 仓）**：
- `ReleaseService::upload`：**不再把 `variant` 当 `ext`**（`generateFilename` 的 ext 传空取平台默认）——variant 是并入 arch 的限定符（`ManifestService.php:263` 输出 `arch-variant`），非容器格式。
- `ReleaseDomainService::parseFilename`：扩展名白名单加 `exe/dmg/pkg`（原生安装器与归档是不同 kind，都要能反解析）。
- `ReleaseAdminController`：`store` 的产品/平台白名单改由 domain SSOT 派生（`Rule::in(array_keys(PRODUCTS/PLATFORMS))`），`create` 传 `$products`/`$platforms`；blade 同步改遍历——原硬编码漏掉 `mynet_agent`/`openwrt-sdk`，传不上去。
- 测试：`ReleaseFilenameTest` +2 例（原生安装器扩展名可解析、SSOT 清单自洽）。`make test` **56 tests / 455 assertions / 1 skip** 全过；`make guard` 通过；`php artisan view:cache` 通过。
- **仍开放**：同一 `(product,platform,arch,version)` 下「安装器 vs 归档」缺独立 kind 维度。未提交、未 push。

**ctl 生产迁移已执行（2026-10-10，用户授权「大胆跑」）**：`.180` 上 `/data/app/mynet.club/ctl` —— **非 git 工作树、代码停在 2026-03**（与仓库 10 月有 7 个月漂移）；故**只跑 schema 迁移、未做整仓代码部署**。投递 `database/migrations/2026_10_10_000001_add_kind_to_releases_table.php`（owner/perm 对齐既有迁移 502:staff）后 `php artisan migrate --force` → DONE（157ms）；`migrate:status` Ran；`releases` 现有 21 列，含 `kind varchar(16) nullable` + `releases_kind_index`。**该列对 3 月代码无影响**（可空、无人引用）；待 ctl 代码正式发布后上传才会写入 kind。⚠️ 另注意到 ctl 根目录有一个**误创建的垃圾文件**（名字是一段 shell 代码 `1 : "NO MATCH"; echo PHP_EOL; var_dump($m); `，Mar 25），未擅自删。⚠️ ctl 整仓代码部署需单独的 3 月→10 月 diff 发布，不能整文件覆盖。

**H5 入口（2026-10-10，第一批 = O0 + O1）**：OpenWrt 侧 playbook 定「第一实施批次只做 O0 操作/合同表 与 O1 装配一致性」。O1 硬要求「**init 单一源码，ipk 与 installer 生成物一致**」，取证发现**同一 `/etc/init.d/mynet` 有两份互不相同的实现**：`mynet_agent/mynet/units/openwrt_mynet.init`（10890B，`mynet install` 写；procd×2 / gnb_es 清理×6 / VPN_TYPE×10 / firewall.mynet×2） vs `mynet_openwrt/scripts/runtime/rc.mynet`（9079B，**ipk 装到 /etc/init.d/mynet**，Makefile:56；procd×1 / gnb_es×4 / VPN_TYPE×5 / firewall.mynet×4）；`mynetd.init` 亦两份（agent 999B vs openwrt 751B）。且 ipk 那份把 **gnb 停止委托给 Lua**（`rc.mynet:155-162` → `luci.model.mynet.node.stop_gnb`）→ **同一生命周期三处实现**（agent shell init / ipk rc.mynet / Lua node）。收敛方向：让 ipk 装**同一份** agent 内嵌 init，退役 `rc.mynet` 与 Lua 的启停路径。（注：G-9 修的是 agent 那份的 PPID=1 判据；Lua 侧的注释称「PPID 精确匹配」，未见同一缺陷，但**重复实现**本身即 O1 要收敛的。）

**今晚升级 Runbook（2026-10-10 夜）**：[spec/node-upgrade-runbook-180-182.md](spec/node-upgrade-runbook-180-182.md) —— 含开工前决策 P-1…P-5、commit 锚点与待提交清单、CI 出包与校验、S0 取证、备份（含私钥）、`.180`/`.182` 步骤、验证 A/B、回滚、Go/No-Go、窗口记录表。⚠️ 两条硬提醒：**改动未提交/未推送 → CI 出包不含 G-11 等修复**；`.182` 的 **LuCI 入口层今晚不验**（H5 另补）。

**节点升级部署计划（2026-10-10，H6 仅计划，**口径已定稿**）**：`.180`(api.mynet.club / proxy server / Lightsail) 与 `.182`(192.168.0.2 / 本地旁路网关 / proxy client / ImmortalWrt x86_64) → [spec/node-upgrade-plan-180-182.md](spec/node-upgrade-plan-180-182.md)。裁定：**D1** `.182`=本地(home)旁路网关（旧「公司整网网关」为误读，**已回改**各仓 guard.sh / AGENTS.md / 一处 PHP 注释）·**D2** 本次**不轮换密钥**（继续挂风险）·**D3** 走 **CI 产物**·**D4** `.182` 用 **ipk**·**D5** `.182` 已非活路径（主网关切到 `.1`）窗口压力低。产物：`.180`←`mynet_agent_linux_amd64`(build.yml)、`.182`←`openwrt-ipk-amd64`(build_openwrt.yml，矩阵已有 x86/64)。**未执行**。新发现：`build.yml` 的 `openwrt:true` 条目未带 `-tags openwrt`、产物名 `linux_*` → 收严后在 OpenWrt 会被拒装 → **已按 H4 构建输入修**：linux 条目去 `openwrt:true`、mynetd 统一 `-tags embed`（与 `make dist` 一致），OpenWrt 归 `build_openwrt.yml` 的 ipk；fixture `TestBuildWorkflowDoesNotFakeOpenWrt` 锁住。**H4 desktop 发行 workflow（2026-10-10，G-12 关闭）**：`mynet_desktop/.github/workflows/release.yml` 的 `build-binaries` 新增 **mynet_gui checkout + setup-node + `npm ci` + embed 构建**（`.env.embed` → `.env.prod`/`.env.local.prod`，断言 `dist/spa/index.html` 含 `/gui/assets/`）**再** `make dist`；**删除「agent ref 失败退默认分支」兜底**，改为明确失败并给指引（合同 §4.3「锁两仓 SHA」）；新增 `gui_ref` 输入。⚠️ **`GUI_REPO` = `mynet-club/mynet_gui`**（用户 2026-10-10 确认，**不带 `-V2`**，见 `mynet_desktop/.github/workflows/release.yml:48`）。⚠️ 行为变更：**tag-only 发布若 agent/gui 无同名 tag 会失败**，须用 workflow_dispatch 显式 pin 两仓 ref。YAML 已校验。

**H4 artifact kind（2026-10-10，mynet_ctl）**：新增**声明式 `kind`** —— 域 `ReleaseDomainService::ARTIFACT_KINDS`（`installer`=exe/dmg/pkg/ipk、`archive`=tgz/zip/tar.gz）为唯一 SSOT；`ReleaseService::upload` 登记时映射一次并落库；**迁移 `2026_10_10_000001_add_kind_to_releases_table.php`（生产部署需跑）**；`Release` fillable + `kind`；`ManifestService` artifact 行输出 `kind`（legacy 行兜底按 ext 推导）；`parseFilename` 亦返回 `kind`。测试 +2（58/467/1skip 全过）。**仍开放**：`/d/` 取文件不带 kind（同 tuple 两件会取错）→ 归运行升级选择器（已搁置）。

**H3 驱动与网络生命周期（部分，2026-10-10）**：**G-9 关闭** —— `mynet/units/openwrt_mynet.init` 的 `stop_gnb_instance` 改可验证归属（`/proc/<gnb>/cmdline` 含本实例 confdir 锁定本实例 gnb pid；只清 exe=本部署 gnb_es 且父进程 ∈ 本实例 gnb 者；归属不可证**不杀只记**），删 PPID=1 判据；fixture `TestOpenWrtInitGnbEsCleanupUsesVerifiableOwnership`。`sh -n` + `go test ./mynet/` 绿、openwrt 测试面编译通过。**G-10 已关闭（2026-10-10，用户裁定方案 B：agent 安装器带 win_tun、不带 gnb，不改 gnb `src/`）**：`wintun.dll` 作**资源文件入库** `mynet_desktop/nsis/vendor/wintun/wintun.dll`（取自测试机 192.168.0.5；WireGuard LLC 官方签名 amd64、427552B、SHA256 `e5da8447…`）；NSIS payload 打进安装器、落 **gnb.exe 同目录** `$INSTDIR\driver\gnb\bin`（`File` 无 `/nonfatal` → 缺则编译即失败）；`guard` C4 新增**持久白名单** `.guard-c4-allow`（模板 + desktop 同版，环境变量不能替代）；`build-nsis.sh` 出包校验 SHA；provenance 记于 `nsis/wintun-provenance.md`。验证：`make guard` 通过（含新 DLL）、guard 自测 45/0、`check.sh all` 40/0/0。

**OpenWrt 规划入口（2026-10-10，用户要求先摸底再制定规划）**：读 [平台接入规划](../../../mynet_openwrt/docs/AGENT_INTEGRATION_PLAYBOOK.md)。本轮核对 HEAD 为 agent `50c1b58`、OpenWrt `f7c956e`、ctl `084496c`，不据上方旧 SHA 判断当前状态。已查明 Lua config/apply 分派不满足新 connect 合同、ubus 桥为 stub、包内与安装器 init 不一致、发行配方只交付 artifact；三包 Go 测试和打包缺件守卫通过，未新做设备/SDK/资源验收。下一实施批次为 O0 全量操作合同与 O1 装配一致性；主路由 .178 的既有未提交计划保留，未执行切换。本指针仅增加 OpenWrt 工作入口，P4 的实机验收仍保留。

先读 [小闭环 playbook](spec/proxy-permission-loop-playbook.md)。2026-10-08 **L0–L2 代码结构闭环已闭合**（用户确认主链成立）；下一步是 §10 上线前实机验收（E/O/C/U 流量、gateway 通知、出口离线 fail-closed、现存连接终止、并发 apply），不是再改控制面。
特别核对：DELETE 后 `resolvePermission()` 为 null，`resolveForExit()` 却返回 open；不能把 ACL 默认开放当成出口已启用。
状态刷新时记录 HEAD、未提交变更和实测命令；已完成项必须有行为证据。

## 2026-10-08 执行验证刷新

API 全量 458 tests / 4626 assertions；隔离 MySQL 8.4.8 的组 159 tests / 692 assertions（19/33 Feature 文件）；agent make check 通过。ctl 54 tests 中 1 个首页测试因未构建 manifest 跳过。全部是本地验证，未动生产；改动未提交。
L2 独立 gate 两项失败：出口 NAT/ACL 无清理、兜底 DROP 被当 optional。先修这条规则归属/替换/回滚链，再做隔离 Linux L3；不勾 M4/M6。

## 出口规则专项更新（2026-10-08，后续优化）

出口 NAT/ACL 两个阻断已落码修复：接口独立 nft 专表/iptables 链、替换/幂等/撤销、故障回退、精确历史规则迁移。独立门禁7例通过，Docker 隔离 Linux 中 nft1.0.9 / iptables1.8.10(nf_tables) 内核测试通过；不再沿用“两项仍失败”的旧状态。当前规则集撤销已验，不代表 conntrack 旧连接、出口离线或 API/gateway 真实流量矩阵已验。完整 L2/L3 与 M4/M6 仍未达成。具体以 playbook §9 为准。

（历史注：2026-10-08 前后 agent `3e3d464` 一段的「未推送/未提交」状态已被 10-10 之后的基线取代，以上方「当前基线」表为准。）

## 2026-10-08 L1/L2 收尾（代码结构闭环）

补齐跨租户 fail-closed（节点查询限 tenant）、双连接并发行锁验证（隔离 MySQL）、旧 generation 重放拒绝（`GenerationRevision` + `ApplyCloud` 单调门禁）。API 全量 460 tests（1 skip=sqlite 并发）；`test-mysql` 161 passed；agent `go test ./...` 全绿。**代码结构小闭环成立**（授权→发布→应用→撤销→失败回退有测试）；上线前实机项见 playbook §10，M4/M6 仍不勾。

## 2026-10-10 与 HEAD 对齐（本次刷新）

- **三份文档都曾落后于代码**：上位基线 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md`（10-06）、本文件（10-08）、
  `dev-plan.md` §3（10-05）。本次按 10-09 HEAD 对齐，各文件的落地记录见其新增段。
- **10-07→10-09 落地面**：出口授权小闭环 L0–L2 代码结构闭合（agent `3e3d464`/`9f20e45`、
  api `5eb08d0`、ctl `1208561`）；出口 NAT/ACL 由「无清理」改为接口独立 nft 专表/iptables 链 +
  替换/幂等/撤销/故障回退；旧 generation 重放门禁（`GenerationRevision` 解析 + `ApplyCloud` 单调）；
  首次创建设备自动分配地址池与默认配置（api `76e1214`）；GUI 简化设备与连接流程 + 生产 API 代理
  回环与 WS 域名修正 + 首建/首载引导区分（gui `3a1b53d`/`5896fe4`/`26d8562`）；agent 一键接入设备
  与 v0.1.1/v0.1.2 发布绑定（`2212e47`/`48b84f6`/`db398d2`）。
- **`dev-plan.md` §3 两处状态列依据已过期，不按旧记号读**（不据此翻勾，待实测）：
  - **#30 C-6**「撤勾」的依据是「死代码 + gateway 二进制不存在」⇒ 实测已不成立：`cmd/mynet_gateway/main.go`
    存在、`NewRedisPresence` 被 `:90` 真接线、有跨实例测试 `gateway/cross_instance_test.go`。
  - **#16 F-4**「Windows 是纸面」⇒ 已失效：windows 平台文件齐全，且 `mynet_agent/Makefile:76` 的
    `DIST_MATRIX` 含 `windows:amd64`。仍保留「部分」——实现存在 ≠ 真机跑过。
  - 另：**#75 状态列 ◐ → ☑**（该格正文早已写「P0 两件全部收口 ✅」，标记漏跟）。
- **仍未收口（不变）**：M4/M6 不勾；OpenWrt 未接统一 Proxy 合同、回程缺口、离线与错误出口证据不足、
  console 公共出口线未开；**上线前七项实机验收**（E/O/C/U 流量矩阵、真 gateway 通知与拉取、出口离线
  fail-closed、撤销后 conntrack 旧连接、并发 apply、iptables-legacy/OpenWrt 兼容、实机乱序重放）
  见 `spec/proxy-permission-loop-playbook.md` §10。
- **口令**：读状态前先核 HEAD —— 本文件与 §3 的勾选都可能滞后于代码，冲突时按最新裁定与代码取证判定。
