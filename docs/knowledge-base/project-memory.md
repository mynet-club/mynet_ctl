> 2026-10-18 PC proxy/DNS：白名单小集生命周期已落码，自动化通过，未部署/未做真机数据面验收。见 [PC 生命周期合同](spec/pc-proxy-lifecycle.md) 及本文同日记录。

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

## 2026-10-15 交付收口入口

用户要求「做playbook，然后持续开发」。当前整体编排：[v1交付收口Playbook](spec/v1-delivery-closure-playbook.md)。承接H/O/L原有任务，先DC-1路径与DC-2发行一致性，之后DC-3来源、DC-4/5实机验收；旧快照不视作当前状态。
本机基线：agent make check通过；API519/4908/1skip、ctl82/467/1skip、GUI58tests与183条/31文件类型存量错误、desktop40项通过。无线上验收或部署。
2026-10-15开发结果：DC-1路径与DC-2发行GUI一致性已实施，DC-3归档来源/完整性已实施；新增行为测试、make check、embed HTTP校验、三目标交叉编译通过。具体文件与边界见playbook执行账。agent代码已提交：`03b8d92`（路径）与`92b06b6`（GUI/发行来源）；尚未push。用户已明确实机验收暂缓。
2026-10-15第二批：DC-3安装器来源记录与复核已在mynet_desktop实施——build-nsis/build-pkg/build-dmg 嵌入SOURCES+sidecar、`check-installer-sources.sh` 复核、缺来源/kind错配拒绝不产半包、release.yml 已存在版本先复核再SKIP。desktop make check 41 lint+25 test 全绿；agent make check、六腿交叉编译、workflow run块bash -n 通过。已提交并推送：desktop `75c670b`、ctl `be0497c`、agent `03b8d92`+`92b06b6`。上传/实机仍暂缓，不据此勾M4/M6。下一包=DC-4/5可本地部分，实机项继续保留未验。

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

## 2026-10-18 PC proxy / DNS 生命周期实现

基线：agent `b77793f`；ctl `3617d99`。本批代码与文档未提交；ctl 原有 `scripts/proxy-lists/proxy-domains.txt` 未提交改动保持原样，未修改或发布该清单。

- PC 改为 dns-hook 会话唯一管理动态路由，按查询建立有界域名/IP 租约（各 512），引用共享、到期回收、精确 CIDR 合并/拆分；apply 不再预解析整份域名表。
- 缓存命中核对真实 FIB，路由丢失/接口更换自动恢复；白名单或出口上游变化清理旧缓存与租约。去掉旧 PC 磁盘解析缓存无限沿用过期 IP 的 fallback。
- ctl 两份基线在后台整体下载、按小时刷新；按配置 base 独立存储，本机只保存自定义叠加，不用旧基础快照遮住更新；失败保留此前成功基线并报日志。
- 开启但无隧道时保存意图并等待，托盘支持取消；正常停与 worker 异常退出恢复 DNS/释放自有路由，watcher 按意图与隧道状态重启。
- DNS 逐接口备份、写前落盘、失败回滚、按当前所有权恢复；无备份不重置 DHCP，用户后来改过的设置不覆盖。
- Windows 路由读写统一 ifIndex，补齐进程与控制锁的平台实现。检查发现的既有托盘测试替身 reqs 数据竞争已加锁；基线 `config_schema.go` 的两行 gofmt 对齐随门禁修正。

实测：`make check` 全通过；`go test -race ./mynet ./shared/config ./mynetd/localapi ./cmd/mynet_tray` 全通过；`GOOS=windows GOARCH=amd64 go build ./...` 与 Linux amd64 执行器交叉编译通过。新增专门行为测试 32 项（不含既有用例）。宿主只读核对了 macOS netstat 路由表形状，没有安装、启停现有服务或修改真实 DNS/路由。

边界：仍为 IPv4/A 记录路径，未扩展 TCP DNS、IPv6、应用 DoH；首次旧清单迁移、Windows/macOS 实机流量、休眠/网络切换、watcher 自身被强杀后的整机恢复仍需真机验收。自动化通过不等于生产验收，不勾 M4/M6。合同与事件矩阵见 `spec/pc-proxy-lifecycle.md`。

## 2026-10-18 Linux/BSD PC/GW 与嵌入 WebUI 追加

本批继续使用 agent `b77793f` / ctl `3617d99` 基线，代码文档未提交。

- 新增独立运行形态 `config form pc|gw`、`setup --runtime-mode pc|gw` 与 WebUI 选择/PC 加速开关。Linux/BSD 缺省 GW 保持旧行为，macOS/Windows 固定 PC，OpenWrt（含构建标签）固定 GW。local/online 身份不受形态选择影响。
- Linux/BSD PC 复用白名单 DNS hook 与主表目的路由会话；Linux FIB 用 ip JSON，BSD 用 netstat/route。PC 不装 GW 分类器、大集合与网关 DNS 产物。旧自动 DNS-plan sidecar 退役，系统 DNS 只按 PC hook 意图接管。
- Linux/BSD 原子替换 resolv.conf 本身，完整备份原文件/符号链接与权限，不写穿管理器目标；退出与失败回滚仅恢复仍属于本次接管的内容。用户/管理器后来改写的设置保留。
- 切换先释放旧 PC 会话或 GW 自有规则/产物，再持久化新形态并在隧道在线时重应用。WebUI 保存域名叠加不再清空隐藏的 DNS/域名高级字段。
- Linux/BSD 均嵌入 WebUI；Makefile/CI 加入 FreeBSD/OpenBSD amd64、arm64。补齐 BSD 包手册与已有 metrics 编译缺失。OpenWrt 维持 GW 精简构建。

验证：最终 `make check` 和 `go test -race ./mynet ./shared/config ./mynetd/localapi ./cmd/mynet_tray` 全通过，原生 WebUI JS 语法检查通过。以独立临时 GUI 源码副本按 .env.embed 构建 SPA，HTTP 探测 `/ui/`、`/gui/`、JS/CSS 资源均 200；含完整 GUI 的 FreeBSD/OpenBSD amd64/arm64、Linux amd64、Windows amd64 执行器与 daemon 交叉编译通过，四个 BSD 测试 tgz 组包通过，OpenWrt mipsle 精简二进制及测试交叉编译通过。临时嵌入资源已清理，原 GUI 仓与其 dist 未修改。

边界：BSD PF 网关代理后端与原生 rc 服务安装器未实现；BSD 现有 GW 代理请求明确报不支持。交叉编译/HTTP 资源探测不代替 Linux/BSD 真机网络切换、resolver 管理器兼容及流量验收。本批没有安装/发布/启停真实服务、修改宿主 DNS/路由，不勾 M4/M6；ctl 既有 proxy-domains.txt 改动未触碰。

## 2026-10-18 Linux/OpenWrt 防火墙启停与外部重启复核

本批延续工作树，未提交/发布。统一检查了 Go apply、firewall sync、Linux nftables/netfilter-persistent、OpenWrt fw4 include 与 ipk firewall.mynet 启停路径。

修复：Linux drop-in 从仅基础规则改为引用全部 nft 产物；netfilter-persistent 用 90-mynet 插件恢复 nft 自有表，取代不能保存这些表的全机 iptables save；OpenWrt start 委托 Go sync，不再覆盖 include/另加 fw4 NAT，移除旧重复生成函数；UCI include 补 fw4_compatible，写入/同步错误直接返回。Go/include 用一次 nft include 事务重放，取消重复加载，所有重放产物原子替换；Linux 接口同步不再 reload 整机防火墙。proxy 禁用删除旧分类器产物，出口 ACL/NAT 成功应用后按接口持久化、撤销后删除，Linux 卸载撤本实例重放登记。doctor 的持久化判据随新承载物更新，基础规则检查不再从全 ruleset 混认别人的 masquerade。

验证：最终 make check 通过，go test -race ./mynet ./shared/config ./mynetd/localapi 通过；5 个新增 Go 行为测试覆盖重复/失败重放、Go 与 shell 同载入路径、Linux 插件动作与错误传播、实际 include 匹配、禁用产物清理和出口 ACL 替换撤销。OpenWrt tests/firewall_lifecycle_test.sh 真实执行 shell+假工具链，证明 start 单次委托、不覆盖 include、stop 保留配置、sync 失败透传；shell 语法检查通过。Linux amd64 与 OpenWrt mipsle 执行器/daemon 交叉编译通过，OpenWrt 测试交叉编译通过。测试没有操作宿主防火墙/服务或 DNS/路由；没有真机 firewall restart 与数据流量验收，不勾 M4/M6。

仍开：CPI-83（ufw/firewalld 重启面未接、直接 flush 无即时恢复）、CPI-84（平台 mynet→wan forwarding/基础 NAT 与出口授权独立，撤销专表不等于整机未授权 WAN 转发已阻断）。现有 LAN/NAT 语义未擅自更改。详细事件矩阵与边界见 spec/route-firewall-split.md §8。

## 2026-10-18 外部 reload 钩子与 CPI-84 处理完成

用户明确目标为其他 OP/软件执行系统防火墙 reload/restart 时自动重放，不要求裸 flush 后轮询。按此修正并继续 CPI-84；本段替代上段 CPI-83/84“仍开”的当前状态，历史记录保留。

- nftables 原生 include 不存在时登记 systemd ExecStartPost/追加 ExecReload，保留系统原加载命令；ufw after.init 串联原钩子、透传错误、卸载还原；firewalld 服务钩子覆盖 start/restart/reload，执行器 mynet firewall watch 用 D-Bus Reloaded 覆盖 firewall-cmd reload。监听归执行器独立 systemd 服务，不进 mynetd、不轮询、不递归调用 apply/sync。原 fw4 include/netfilter 插件保持。
- 无授权/撤销/client-only 仍保留隧道→WAN 拒绝，不再删专表后让宽平台转发接管；授权白名单只约束 WAN。WAN 集合来自 detector 默认出口，IPv6 默认/直连路由补采；直连 LAN 前置 return，保留不同接口及单网卡旁路由同接口 LAN 访问。缺 WAN 事实时报错，不猜接口。
- OpenWrt 首装仅 LAN 双向转发，清理旧宽 WAN forwarding；WAN forwarding 由有效 server 授权增删。firewall sync 自身生成拒绝/授权产物；无普通路由的 apply 也应用边界，service start 不再先加载基础 NAT 再装授权过滤。

最终 make check、go test -race ./mynet ./mynet/detector ./shared/config ./mynetd/localapi 通过；Linux amd64、OpenWrt mipsle、FreeBSD amd64、Windows amd64 编译通过。OpenWrt 标签测试编译为 Linux arm64 二进制，在隔离 Linux 容器执行 UCI 替身/安装器测试通过，含 WAN forwarding 授权增删和重复同步。shell 生命周期测试与语法检查通过。

真实隔离内核：MYNET_KERNEL_PLATFORM=linux/amd64 sh scripts/check-proxy-exit-kernel.sh 通过；内核 7.2.9-orbstack-00391-g2b298d05fbd4、nft 1.0.9、iptables 1.8.10 nf_tables。nft/iptables 均保留平台宽 ACCEPT/NAT/established 规则，实际 netns/veth IPv4 流量验证未授权/开放/白名单允许拒绝/撤销、独立 LAN、旁路由同接口 LAN。另验证清空 nft 后真实 include 重放恢复 WAN 拒绝，以及隔离 D-Bus 服务发送真实 Reloaded 信号后 watcher 重新加载已删 nft 表。容器均使用独立网络空间，无 host network；测试清理临时容器及生成测试源码。为测试启动本机 OrbStack，未操作宿主防火墙、生产 MyNet 服务或 DNS/路由。

CPI-83/84 已移入已修复（代码与隔离 IPv4 数据面证据）；实际 Linux 发行版/固件服务、IPv6 数据面、额外策略路由出口表与 iptables-legacy 仍按部署环境验收，未新增 ip6tables 后端。代码文档未提交、未发布，M4/M6 不勾，ctl 既有 proxy-domains.txt 改动未触碰。当前合同见 spec/route-firewall-split.md §8。

## 2026-10-18 托盘隧道 / PC 加速启停收口

用户选择：启动隧道恢复上次加速开关；显式开启加速统一准备 proxy 与 DNS hook。延续 agent `b77793f` 工作树，未提交、未安装或发布。

修复：PC 客户端 proxy enabled 与 hook 偏好统一；旧版独立开关按用户最后选择迁移，镜像文件原子替换。在线开启先保存策略/生成驱动投影，再启动 hook；面板 POST 在执行器调用前保存策略并报告投影错误，proxy apply 协调 PC hook。GNB 禁用显式写 proxy off/ingress_open off，覆盖旧启用文件。proxy apply 与 hook start/stop 加入 configMutation 互斥，status 不锁。status 增加 running/disabled/waiting_tunnel/failed，托盘轮询刷新、操作失败展示清洗后的原因，WebUI 区分等待与异常。并发测试替身将加速状态读请求与隧道启动分开计数，保留原启停门禁。

验证：最终 make check、go test -race ./mynet ./mynetd/localapi ./cmd/mynet_tray、Windows amd64 全包交叉编译、git diff --check 通过。新增行为测试覆盖统一开关、过期旧开关不复活、旧选择迁移、无隧道开启/取消、失败与等待状态、持久化先于执行及保存失败不执行、API 写互斥分类、关闭覆盖旧驱动配置。本机 localapi 只读核对当前旧程序 hook alive=true/wanted=true、1 域名/2 路由；没有启停真实服务或写宿主 DNS/路由，不代表新代码已部署或端到端流量已验收。当前合同见 spec/pc-proxy-lifecycle.md。

## 2026-10-18 本机 macOS 更新与实机启停验证

用户明确授权更新本机。22:40（Asia/Shanghai）构建并安装 embed 版 mynet/mynetd/mynet_tray，builder 为 arthur@local_2026-10-18T14:40:31Z，保留现有 .env.embed SPA；原二进制备份于 /opt/mynet/bin/backup-20261018-224047。安装前由旧执行器正常 service stop（恢复 DNS/释放路由），原子替换二进制后重启 launchd daemon 和托盘。托盘首次重启遭 OS_REASON_CODESIGNING，重新 ad-hoc 签名后已运行（launchctl job state=running）。构建临时嵌入目录已清理，GUI 源码/dist 未修改，未发布云端。

实机验证：加速 stop 后 alive=false/wanted=false/state=disabled，DNS applied=false、自有路由为零；start 后 state=running。隧道 stop 后 alive=false/wanted=true/state=waiting_tunnel、自有路由为零；service start 后自动恢复 alive=true/wanted=true/state=running。/ui/、/gui/ 均 HTTP 200。127.0.0.1 的 github.com/baidu.com A 查询成功，GitHub 解析结果 20.205.243.166 主机路由指向 utun4；curl --noproxy '*' --resolve 固定该地址访问 https://github.com 返回 HTTP/2 200。最后保持隧道、加速、daemon、托盘运行。

新发现边界：dns-hook 日志显示 ctl proxy-domains.txt 下载 HTTP 404，保留现有清单；未改分发端。本次仅为本机 macOS 证据，不替代其他平台/网络切换验收。

## 2026-10-18 域名清单 404 修复与发布

用户要求解决本机清单下载 404，并核对是否改名。根因已取证：agent URL 缺 /d 前缀；生产 ctl 的路由正则与 iplistFile allowlist 只接 interip/chinaip，scripts 与 storage 均无域名清单。名称未变。另发现 parser 拒绝 *.cn/.中国 这类顶级域后缀，已支持显式后缀形式，裸无点单词仍拒绝。

客户端改为 https://ctl.mynet.club/d/plugins/mp/{proxy-domains,direct-domains}.txt，HTTP 替身测试覆盖实际 URL 构造、双清单下载和失败不替换基线。ctl 增加公开文本端点测试；手工清单原子发布移至 GeoIP 网络操作前，修复零条目 meta count 可能产生无效 JSON；shell 测试覆盖 GeoIP 失败不阻塞清单、不覆盖原 IP 表，以及空清单元信息。shell 测试加入 make test。

生产只定点修改 routes/web.php 一处正则、ProxyController.php 一处 allowlist、清单更新脚本，部署两份已提交版本模板及其发布产物，合并 meta 两项而不覆盖旧 IP 元信息；没有整仓同步。替换前比对下载快照 SHA256 并备份，备份路径 /data/backup/mynet/ctl/domain-lists-20261018-145554（服务器时间），清路由缓存后验证公开 URL 两份均 200、内容 SHA 匹配。chinaip/interip 的内容 SHA 与更新前一致。本地尚未提交的扩充 proxy-domains.txt 没有修改或发布。

本机仅替换 mynet 执行器并正常停/启 hook，隧道保留。builder arthur@local_2026-10-18T14:56:06Z；旧执行器备份 /opt/mynet/bin/backup-domain-url-20261018-225632。本机后台实际下载落盘版本 bc9b4c6bd930725445c9096005a1a93009ef3b7797f6c556c058b3da5b5e8bba，代理155（源156行去重）、直连98，含 cn 后缀，当前 hook state=running；GitHub 本地 DNS A 查询成功。agent make check、ctl PHPUnit 84 tests/477 assertions（1个既有 skip）、清单发布 shell 行为测试通过。代码文档未提交，未发布新的客户端分发包。

## 2026-10-18 PC 单加速白名单收口及 Google 超时复核

用户明确 PC 是白名单加速，未命中默认直连，基础直连表无须作为第二份分流依据。客户端改为仅下载 proxy-domains.txt，缓存新版本带 pc-whitelist-v1 语义，成功刷新清除旧基线 Direct；hook 构造时也忽略旧基础 Direct（包括断网无法刷新时），保留已有 CustomDirect 为本机可选白名单排除。旧 upstream_clean 不再使未在加速白名单中的域名进入隧道，移除 hook 冗余 clean 匹配集合。ctl direct-domains.txt 端点为旧客户端保留，不删其他平台合同。

Google 超时只记录证据：开始诊断时 hook 正常，google.com DNS A 成功，命令行 google.com 跳转至 www.google.com 后 HTTP 200；ego-browser 新页面实际加载 Google 搜索界面；系统 HTTP/HTTPS/SOCKS/PAC 均未启用。用户随后确认原页面刷新后能打开。没有复现原超时，不能认定是 DNS/浏览器缓存或路由 TTL 导致；未以猜测改变 TTL/租约策略，未清浏览器全局缓存或 cookie。浏览器诊断 task space 已正常关闭。

agent make check 与 go test -race ./mynet ./shared/config 通过；新增/更新测试验证仅请求加速白名单、失败保留/更新/空表撤销，以及旧基础 Direct 不覆盖白名单、未命中及旧 clean-only 域名默认直连、本机显式排除保留。已更新本机 mynet 执行器并正常停启 hook，隧道保留；builder arthur@local_2026-10-18T15:05:03Z，原执行器备份 /opt/mynet/bin/backup-single-whitelist-20261018-230525。本机真实后台刷新为代理155条、基础直连0条，版本 4cb5f873cfe06703034718cf42dcc274d531994c6c3ff6faba203d14f538fe68；更新后 Google 跟随跳转 HTTP 200、hook state=running。未修改或发布 ctl 本地尚未提交的扩充清单，代码文档未提交，未发布客户端分发包。

## 2026-10-18 用户指定完整白名单发布、同步与提交

用户明确要求将当前 scripts/proxy-lists/proxy-domains.txt 上传 ctl、更新本机并 git commit。冻结原文件快照后校验886个唯一域名，未改写条目或注释；原文 SHA256 d38a170e6dcb9fbc82e9bd5dddd6a47e904af7925a02a5fea587ee1715ce0916，14613字节。生产同步维护模板与 storage 下载文件，并仅更新 meta 的 proxy_domains 项及域名更新时间；旧文件备份 /data/backup/mynet/ctl/domains-publish-20261018-151829（服务器时间）。公开 /d/plugins/mp/proxy-domains.txt 返回200，内容SHA与指定源文件一致。

正常停启本机DNS hook触发下载，未停隧道。实际缓存逐条集合比对与源文件一致：886代理规则、0基础直连规则，基线版本0fa08914263814c4bd35b93b60275f00b4b0eeb5b323c54a76354c4f30c9e1e8，hook state=running。ctl make check通过（84 tests/477 assertions，1既有skip；发布shell行为测试通过）。只提交用户指定清单：ctl main 98492a9（更新常用网站及辅助域名加速白名单），未推送。其他既有代码/文档工作树改动未并入该提交。

## 2026-10-18 行数核对及断开后托盘暂停状态

用户反馈清单942行与886条差异，并认为托盘断开后hook未停止。只读核对源文件为942物理行：886域名+32注释+24空行，无末尾换行，故wc -l为941。条目886且唯一886，不是漏传。

诊断时本机已断开：gnb running=false，hook pid=0/alive=false/wanted=true/state=waiting_tunnel，自有路由0；无hook/watcher进程、无TCP/UDP53监听，DNS备份applied=false并记录已恢复。没有复现运行进程残留。发现托盘等待分支仍用“关闭加速”，容易把保存偏好误解为进程事实。

改为根菜单“加速：已暂停”、操作“取消自动恢复加速”、DNS行“已停止·等待隧道连接”；运行/禁用/失败各有独立文案，去掉原先未使用的title。新增四态行为测试，agent make check、go test -race ./cmd/mynet_tray通过。已备份并安装重启本机托盘，备份 /opt/mynet/bin/backup-tray-pause-20261018-233343，构建时间2026-10-18T15:33:16Z。保持用户断开选择，没有为测试启动隧道或加速。

托盘安装后再次遇到macOS签名缓存拒绝；对最终安装路径重新ad-hoc签名并kickstart后，launchctl确认state=running。该过程不涉及隧道与hook启动。

## 2026-10-19 托盘复用本机面板标签页

用户要求托盘反复打开网页时复用已有标签页。macOS 改为先查询系统默认浏览器，用面板 origin+/ui/ 为固定标识（忽略 query/hash），同实例概览/诊断/域名管理共用一个标签；找到则激活并按需切换hash，当前URL相同不重新加载，不同API实例不串页。浏览器打开操作串行，App点击异步执行避免阻塞菜单；单实例入口也复用同一逻辑。Chrome/Edge/Safari等原生脚本分支已实现；不支持的浏览器、自动化不可用及非macOS保留系统打开回退，不开启浏览器远程调试。

实测默认浏览器Chrome：临时HTTP测试页首次created，随后#diag/#config/proxy均reused，清理核对总共只有1个测试标签。已安装托盘的单实例入口另做重复打开测试。agent make check、托盘race、Windows托盘交叉编译通过，临时Windows exe已清理。本机托盘已备份并更新（backup-tray-tabs-20261019-060111，builder UTC 2026-10-18T22:00:56Z）；首次launchd启动遇签名缓存拒绝，最终路径再次ad-hoc签名后state=running。只更换托盘，未启停隧道/加速；用户当时加速运行状态保持。Safari/Edge未作真机验收，未提交新代码。


### 2026-10-19 DNS 外部修改监控与本机更新

- 执行器新增只读所有权监测：macOS scutil 事件+3秒兜底、活动接口 DNS/默认解析器核对；失败/过期反馈 unknown。外部修改持久化中断并停止 hook，保留外部 DNS，禁止自动抢回；明确重新开启才恢复。托盘/WebUI 同步显示中断，托盘提示一次。
- 验证：make check、go test -race ./mynet ./cmd/mynet_tray ./mynetd/localapi 通过；Windows/Linux amd64 go build ./... 通过；伪系统操作及临时 resolver 文件测试覆盖覆盖检测、保留外部配置、停止/恢复与状态。未通过切换真实 Wi-Fi 制造故障。
- 本机只读原生探测确认 active services=[Wi-Fi], owned=false，但旧程序仍 alive=true/state=running。安装三程序后以维护停止保留意愿，实际状态 alive=false wanted=true state=dns_overridden active=false，routes=0；Wi-Fi 仍使用 DHCP DNS（无手动 DNS），隧道 gnb 存活。备份 /opt/mynet/bin/backup-dns-monitor-20261019-064826；不自动重新接管。


### 2026-10-20 AI/YouTube 默认云端域名补充

用户授权补全并发布默认清单。OpenAI新增9、Claude新增5、Google AI新增9、YouTube新增164（主要地区入口）；886→1073条。已有父域覆盖的辅助域不重复添加，保留旧规则，第三方统计/不明归属项未新增。来源、逐项新增、上游blob SHA与验证边界见 scripts/proxy-lists/domain-audit-20261020.md。生产仅原子更新源模板/公开产物与meta中proxy_domains、domains_updated_at；公开下载逐字节一致，SHA256 cd265449d75728351b098f1b17f79175138109e10fe879ee1ad59fbd6b90a320；备份 /data/backup/mynet/ctl/domains-ai-20261019-225821（服务器时间）。未重启本机hook/解除外部DNS接管中断，未整仓部署。


2026-10-20 用户收敛 YouTube：仅 .com 与新加坡地区入口，保留播放辅助资源。撤销本轮161条，最终912条；新保留youtube.sg、youtube.com.sg、yt.be，其他AI补充不变。已同步云端模板/产物/meta，公开下载逐字节一致，SHA256 86885bb90565c4f09de787edb7cd5f0cd48d1494dd68006030a822744c610ba2；备份 /data/backup/mynet/ctl/domains-youtube-trim-20261019-230246。审计文档已标明旧1073条发布被替代。


2026-10-20 用户要求完善Pornhub辅助资源：新增phncdn.com、phprcdn.com（含子域），912→914条；未扩大到其他站点。云端模板/下载产物/meta同步，公开下载与源文件逐字节一致。SHA256 f1a4ad429fd066643f94f49103fb447bbc7e216ce30c5d2cfefe5270342dfd59；备份 /data/backup/mynet/ctl/domains-phcdn-20261019-234836。第三方规则对照，未实测播放，未更改本机DNS状态。


2026-10-20 用户追加t66y及同类主要资源：新增t66y.com以及XVideos/XNXX/xHamster/SpankBang主域和CDN，共10条，914→924；ty66.com并非t66y。来源与边界见domain-audit-20261020.md。云端源模板/公开产物/meta同步，下载逐字节一致；SHA256 6cc07b50d13eb994ecfef769332e129227337995274e540771c01845f822e47d。备份 /data/backup/mynet/ctl/domains-adult-resources-20261019-235035；未实测浏览播放，未改本机DNS。


### 2026-10-20 DNS hook ad-block实现与本机安装

用户要求常见广告/恶意域名返回127.0.0.1。新增内置35条广告后缀、418条URLhaus精确恶意主机快照，不扩大恶意主机到共享父域；名单随程序更新，与云端加速白名单独立。不可变哈希逐级后缀匹配；优先于上游/缓存/加速，CNAME也检查，A回环127.0.0.1、AAAA回环::1、其他空答案，TTL60。配置enabled/block/allow、CLI与WebUI开关，10秒重载与缓存/路由对账，非法配置保留旧运行态。修复DNS压缩指针递归循环风险（有限迭代）。

make check、go test -race ./mynet ./mynetd/localapi通过；Windows/Linux amd64构建通过。基准500/100000条两次匹配约117.7/115.3ns，0B/0alloc。新构建隔离UDP worker（临时home/自选非53端口，无宿主DNS修改）实测广告A/AAAA、HTTPS空结果与URLhaus主机A均符合预期。

本机安装mynet/mynetd，builder arthur@local_2026-10-19T23:56:31Z；备份/opt/mynet/bin/backup-adblock-20261020-075657。实测ad-block enabled=true domains=453；原加速为wanted=false/alive=false，安装后仍disabled、routes=0，Wi-Fi仍DHCP DNS，隧道gnb存活。daemon运行，/ui/提供新开关。未替换托盘/未发布客户端分发包/未提交整个既有工作树。


### 2026-10-20 ad-block名单迁至ctl管理

用户要求与加速域名一样交ctl维护。增加ad-domains.txt（35广告后缀）、malware-domains.txt（418精确恶意主机）维护源/公开端点/发布脚本/meta两项。生产仅锚点修改route/controller允许名与发布脚本，并新增两份源与产物，保留其他代码及IP/加速清单；备份/data/backup/mynet/ctl/dns-block-lists-20261020-003146。公开下载逐字节一致，加速白名单SHA仍6cc07b50d13eb994ecfef769332e129227337995274e540771c01845f822e47d。

客户端hook启动后台/每小时独立拉取两表，<=1MiB/份，完全校验后原子保存dns_block_baseline.json；部分失败不覆盖，空表有效撤回，已缓存则不混回内置。内置只作首次离线兜底，本机enabled/block/allow保留。10秒刷新执行规则，手动ad-block refresh不启动hook。status/WebUI显示来源。

验证：agent make check、race mynet/localapi通过，Windows/Linux amd64构建通过；ctl make check通过（84测试485断言，1既有skip），发布脚本行为测试包含新名单。HTTP替身覆盖真实路径、成组替换/去重、缓存重载、本机叠加、空表撤回、404/非法/超限/取消保持旧快照。本机mynet/mynetd已安装，builder arthur@local_2026-10-20T00:31:45Z，备份/opt/mynet/bin/backup-cloud-adblock-20261020-083211；手动刷新实测source=ctl domains=453，version=6e5b5390dc9978fa1d197db521b780c0b1d34a1acaa35b46cb6e2de7cb31de24，缓存两表逐条与源一致。原wanted=false/alive=false保持disabled，routes=0，Wi-Fi仍DHCP DNS；未启动加速/未发布客户端安装包/未提交整个工作树。


### 2026-10-20 DNS hook干净DNS优先于peer

用户指定112.124.22.220为首选干净DNS、peer备用。修改defaultForeignUpstreams与空上游兜底为固定干净IP优先，然后TunnelServer/ExitDNS/启用出口VIP；移除平台公共UDP域名隐式回退。上游SERVFAIL/REFUSED也向后回退，保留有效NXDOMAIN。新增顺序、无peer、成功不探测peer及失败回退行为测试。make check、go test -race ./mynet通过。

本机mynet已原子更新，builder arthur@local_2026-10-20T02:08:19Z，备份/opt/mynet/bin/backup-clean-dns-20261020-100835；安装时加速已wanted=false/alive=false，保持disabled未接管DNS。已安装程序在隔离临时home/非53端口实测foreign=[112.124.22.220]，广告拦截仍返回回环。连通性实测当前本机对112.124.22.220的UDP53和TCP53均超时，未宣称解析或Google访问恢复；需服务端/网络进一步核查，未擅自更换该指定IP。


2026-10-20 用户最终指定221.216.138.69为DNS hook首选干净DNS，peer仍备用，替代112.124.22.220。执行器常量与顺序测试同步，go test ./mynet通过；本机已更新，builder arthur@local_2026-10-20T02:13:12Z，备份/opt/mynet/bin/backup-clean-dns-221-20261020-101325。直接UDP查询google.com返回74.125.*，chatgpt.com返回172.64.155.209；先前也测得TCP53拒绝，当前hook使用UDP。加速原wanted=false/alive=false，保持关闭，未宣称已开启后的端到端访问验收。


### 2026-10-20 DNS租户下发+本机最高优先级

用户要求恢复租户DNS下发并允许本机自主配置，后者最高优先级。新增设备级conf/local/dns_hook.json clean_dns（独立云同步）；本机→在线profile.TenantDNS→策略PlatformDNS→peer，前三级皆无时221.216.138.69作默认兜底。主机名/IP保留原始优先顺序，修复此前IP优先排序使peer越过租户域名的问题。CLI dns set/clear/status --json，WebUI本机干净DNS输入/保存/恢复跟随租户/来源与顺序反馈；10秒热刷新清缓存/旧租约，非法配置不替换运行规则。

验证：make check、go test -race ./mynet ./mynetd/localapi通过，Windows/Linux amd64构建通过，WebUI JS语法检查通过；测试覆盖主备顺序、域名bootstrap前后不降序、配置持久化、租户profile更新不覆盖本机、clear跟随更新后的租户、离开在线租户不读旧租户、非法输入不破坏配置、热刷新清旧租约及坏配置保留。

本机mynet/mynetd安装，builder arthur@local_2026-10-20T02:55:12Z，备份/opt/mynet/bin/backup-dns-config-20261020-105534；仅当本机尚无配置时以CLI保存此前用户选定221.216.138.69。实际status source=custom upstreams=[221.216.138.69,10.182.236.180]；当前无显式租户DNS。加速原wanted=false/alive=false，继续disabled/routes=0；Wi-Fi仍DHCP，daemon运行，/ui/已包含新输入与恢复按钮（未声明浏览器点击验收）。未发布全客户端分发包，未整仓提交。

### 2026-10-24 自定义加速域相关补齐与 .180 慢速取证

用户要求完善自定义加速域相关域名，并排查 10.182.236.180 ping 稳但加速慢。
清单 924→929（gmail.com/googlemail.com/googlegroups.com/gsuite.com/zhanghao.today），
SHA256 c9182f879cd29a1a86ce89e6b2be6fcb6ed0004a0fc17850d5fc52a28f6696d6；
云端模板/产物/meta 已同步，公开下载逐字节一致，备份 /data/backup/mynet/ctl/domains-custom-related-20261024-043358。
清单 shell 测试通过；本机 hook 保持 running，未启停隧道。

.180 只读取证：t3.micro、swap 722MiB、双 gnb（旧 9016 + 新 9180）、ens5 rx_dropped 26590、
IpFragFails 582303；隧道回环 5MB≈8Mbps，外网 HTTPS 间歇停顿；两端 zip/mtu parity 正确。
未改生产主机配置、未停旧 gnb、未升级规格；修复建议见会话报告。

### 2026-10-24 端口钉死（用户裁定）

- **mynetd = 8096**（本机 API/WebUI/`/api/v1/relay`，全节点统一）
- **mynet_gateway = 8097**（绑回环；**不用 8710**）
- **对外 WS/relay = 443**（nginx `gw.mynet.club` 反代，证书在 nginx）
- stage gateway 8098（与主网关相邻）
- 180 已对调：原 gateway@8096 / mynetd@8097 → 现 mynetd@8096 / gateway@8097；nginx 上游改 8097
- 证据：CLI `auth status` 后端公钥可取（原 404 消失）；`gw.mynet.club/healthz` 200；WS 进 gateway
- 后续：各节点按此表逐步更新；agent `DefaultListen` 已改 `:8097`


### 2026-10-28 PC 锁单出口 + 加速出口 15s 探测

用户裁定：**PC 形态只支持一个加速出口**（多节点/OS 加权 ECMP 仅 Linux GW 的 `ip route nexthop` 语义，mac/win 无；多出口仍属 GW + gnb `proxy.conf w=/order=`）。合同见 [pc-proxy-lifecycle.md](spec/pc-proxy-lifecycle.md) §PC 单出口。

加速启用（dns-hook 运行）时，执行器每 **15s** TCP 拨测出口 overlay VIP:53（connect/refused=可达），结果落 `var/proxy/dns-hook-exit-probe.json`；`dns-hook status` 第二行 `exit_probe:`，`/api/v1/acceleration/status.runtime.exit_probe`，托盘 DNS 行追加「出口可达 Nms / 出口不可达」。停 hook 清结果。探测在执行器，mynetd 只透传。

验证：`go test ./mynet ./mynetd/localapi ./cmd/mynet_tray` 全绿（含 record/落盘/连续失败/状态解析/托盘文案）。未做真隧道 15s 周期与丢包实测；未整仓提交。

### 2026-10-28 出口探测改驱动探活（用户纠正）

先前 TCP 拨 VIP:53 **已废**。探测改为读驱动自身探活：gnb 经 `gnb_ctl -b <map> -c -s`（`GnbPeerLinks`）取出口 peer 的 **lastping**（`ping_ts_sec`，`parseGNBStatus` 现已解析）与 `addr4_ping_latency_usec`；wg 取 `wg show` latest-handshake。可达判据：gnb 链路 direct/relay 且 lastping ≤75s；wg handshake ≤2min。合同见 pc-proxy-lifecycle.md 出口探测节。

### 2026-10-30 框架 3.10.3 升级、S2 账号契约适配与生产发布

用户令「升级框架到最新，测试后发布」，并裁定「saas 全盘采纳新契约」+「提交推送 + 生产部署」。

- **框架**：四包同批升 root e02594a→f42858d（v3.10.3）/auth →f64eb13/infra →f3e1d95/operator →58f7257；根包别名 `as 2.7.0`→`as 3.10.3`（新模块 `requires_core >=2.13`）；连带 15 个传递依赖；symfony/mime v8.1.7→v8.1.10（CVE-2026-45070）。10-29 实测的 65 失败 + 4 错误逐项消解，`make check` 535/4955/1skip 全绿，`scripts/devtools/` 选测引擎随包生效。
- **S2 账号契约适配**（mynet_api `757b630`）：`users.account_scope` 派生键（`App\Models\User` creating 钩子按 DeploymentPolicy 同规则派生）；登录/注册须显式点名区（X-Tenant-ID / 码上解析），无区登录 fail-closed 403；「无码开注册产出无归属用户」退役；console 不再从成员关系任取租户（夹具 `actingAsConsoleOperator` 显式点名），query 点名未授权区 = 整单拒绝（7 条 widen 用例改断「拒绝」）；`SessionAwareUserAuthController` 构造 3→7 依赖透传；测试 schema 镜像生产终态（account_scope NOT NULL、复合唯一、membership_operations、auth_action_tokens rekey 后唯一键）。
- **现网执行件** `database/sql/2026_10_30_s2_account_identity_scope.sql`：MySQL 8.4.8 与 **MariaDB 10.11.19**（现网引擎）双隔离实例往返验证（首跑→幂等→回灌）。生产已执行：18 行 users 全部回填 `t:%`，复合唯一/两表就位，备份 `/data/backup/mynet/mynet_back-pre-s2-20261029_185030.sql.gz`。
- **发布**（三步，用户确认）：① S2 SQL → ② API `manage-prod.sh deploy`（commit `d757a3d`，健康 200，备份 `/data/backup/mynet/api/20261030_025106`）→ ③ GUI `deployment.sh`（commit `f60d8c0`，线上 200、LoginPage 含区码字段）。发布后 `POST /api/v1/auth/login` 无区 → 403「无法识别租户上下文」（新契约生效）。
- **GUI 登录**（mynet_gui `158af34`）：登录表单新增「区码 / 区 ID」（数字→X-Tenant-ID 头，非数字→body invite_code），首登记住免填；拦截器对显式 X-Tenant-ID 不覆盖。
- ⚠️ **遗留给用户**：mynet_gui 的 `.env.prod` 被 `f60d8c0` 收敛成 `.env.embed` 同款（`/gui/` 基路径，embed 构建口径）——web 部署（my.mynet.club 根路径 + `/api/` 代理）需要同源口径；本次构建**临时**换回 `158af34:.env.prod` 完成发布（构建后已复原提交原样）。两套口径需拆清（web=.env.prod / embed=.env.embed），否则下次 `deployment.sh` 直跑会打出 `/gui/` 基路径包、站点路由全坏。
- 契约变更点（API 面）：登录/注册须带区（X-Tenant-ID 或 invite_code）；同邮箱可多区各一号（全局唯一已摘）；不带区的开注册被拒。
