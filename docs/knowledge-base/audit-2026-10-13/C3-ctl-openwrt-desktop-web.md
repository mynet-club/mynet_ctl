# C3 轨审计：mynet_ctl / mynet_openwrt / mynet_desktop / mynet_web（2026-10-13，只读）

> 范围：四仓的代码与脚本（ctl 的 docs/knowledge-base/** 归 B2，本报告只审 ctl 非知识库部分）。
> 判定口径：gnb 仓 route.conf 本体不可动（仅「安装器 route.conf 注入」路线可列包袱）；`back`/`mynet_back` 为合法命名。
> 七类：1 错漏 · 2 冗余 · 3 历史包袱 · 4 垃圾 · 5 不必要 fallback · 6 违反设计 · 7 过期/无人调用

## 发现清单

### mynet_ctl

[ctl|错漏|S3] manifest 下载 URL 丢失扩展名，非平台默认 ext 的产物 URL 恒 404 — 证据 `mynet_ctl/app/Services/Manifest/ManifestService.php:271`（`generateFilename($internalProduct, $release->platform, $release->arch, $release->version)` 不传 ext → 落平台默认 tgz/zip；对照 `app/Domain/Release/Services/ReleaseDomainService.php:197-203`）；同病 `app/Http/Controllers/Api/V1/ProxyController.php:45` — 影响：ipk/exe/dmg/pkg 登记成功后 manifest `url` 指向 `.tgz/.zip` 假文件名（`file` 字段是真名），updater/下载面取 url 即 404；desktop 首发已因路由层同类漏项实测 404（`routes/web.php:56-58` 注释自证）— 建议：url 直接用 `$release->filename` 拼，或给 generateFilename 补 ext；补 exe/ipk 用例到 ManifestServiceTest。

[ctl|错漏|S3] /d/{product}/{filename} 下载按四元组 first() 匹配，忽略 filename/ext/variant — 证据 `app/Services/Release/ReleaseService.php:128-141`（`downloadByFilename` 只 where product/platform/arch/version）+ `app/Http/Controllers/Api/V1/ManifestController.php:100-115`（`response()->download($absolutePath, $filename)`）— 影响：同版本多容器（如 windows 同时有 zip 归档与 exe 安装器、openwrt 有 ipk+tgz）时会把 A 文件套 B 文件名发给用户，下载计数/日志也记错行 — 建议：按 `filename` 精确匹配（parseFilename 已给出 ext/kind）。

[ctl|不必要 fallback|S4] buildDownloadUrl 的无前缀 fallback 生成永不可达 URL，且上传不校验扩展名 — 证据 `app/Services/Release/ReleaseService.php:214-215`（解析失败时返回 `/d/{filename}`，而路由是 `/d/{product}/{filename}` 两段，`routes/web.php:59-60`）；`validateUpload`（:153-170）不查 ext，`artifactKindForExt` 可返回 `''`（`ReleaseDomainService.php:147-158`）— 影响：任意扩展名可登记成功、下载 404，fallback 把「登记错」洗成「正常 URL」— 建议：上传时拒绝 `kind==''` 的扩展名；删该 fallback（解析失败应 500 而非发死链）。

[ctl|错漏|S3] apps.json 的 latest 用 SQL MAX(version) 字符串比较 — 证据 `app/Services/Manifest/ManifestService.php:76-80`（`selectRaw('product, MAX(version)')`）— 影响：v10 永远小于 v9、v1.6.0.a vs v1.6.0 序错，索引层 latest 可指错 — 建议：取回后用 `compareVersions`（`ReleaseDomainService.php:279`）在 PHP 排序。

[ctl|错漏|S3] 随仓 install.sh / upgrade.sh 打的是 /api/v1/* 旧 URL 形态，与仓内路由全不对 — 证据 `scripts/install.sh:22,102,117,137`、`scripts/upgrade.sh:18,54,94,109`（`/api/v1/{mynet/manifest,detect/openwrt,releases/latest}`、注释里 `.../api/v1/scripts/.../raw/install.sh`）vs 现行路由：API 全在 `routes/api.php:23`（prefix v2）、manifest 在 `routes/web.php:29`（/d/{product}/manifest.json）— 影响：仓库 HEAD 一旦部署，curl|sh 一键安装/升级链全 404；反之在 2026-03 生产代码上「能跑」，正是 7 个月漂移的双面性（reference-ctl-production-deploy）— 建议：脚本改打 /d/ + /api/v2，并与「整仓发布」口径同批处置。

[ctl|过期|S4] verify-endpoints.php 校验的全是已下线 URL — 证据 `scripts/verify-endpoints.php:8-25`（`http://127.0.0.1:8002/api/v1/apps`、`/api/v1/mynet/manifest`、`/api/v1/releases?product=tui`，端点早已迁 /d/ 与 /api/v2，`ManifestController.php:23` 注释自证）— 影响：验旧面工具，跑即全红或被弃用 — 建议：更新端点表或删除该一次性工具。

[ctl|过期/无人调用|S4] ReleaseDomainService::buildDistUrl 零调用且 $variant 参数未用 — 证据 `app/Domain/Release/Services/ReleaseDomainService.php:267-274`；全仓（含 tests）grep 仅 `app/Services/Script/ScriptService.php:173` 的同名异物（不同类）— 影响：死 API 留着会误导「URL 生成有第二个真源」— 建议：删或并入 buildDownloadUrl 并补调用。

[ctl|历史包袱|S4] 旧线产品面仍在对外清单里：tui「旧版」+ client「计划中」+ @mynet/* 插件面，且 tui 的 successor 指向已冻结的 client — 证据 `app/Domain/Release/Services/ReleaseDomainService.php:21,24`（desc 自注「旧版」「计划中」）、`app/Services/Manifest/ManifestService.php:34-60,106-116,148-215`（CLIENT_PLUGINS、`successor='client'`）— 影响：apps.json 对外持续宣传冻结线与其「后继」，误导集成方 — 建议：产品面收敛随 zone-removal/产品裁定执行，至少改 successor 指向真实后继。

[ctl|错漏|S3] 公开下载页硬编码 ['tui','gnb','utc']，mynet_agent/lua/proxy 产物不上下载面 — 证据 `app/Http/Controllers/PageController.php:23-24`（home）与 `:34-35`（downloads allowedProducts）vs admin 已改 SSOT 派生（`app/Http/Controllers/Admin/ReleaseAdminController.php:40-51`，`tests/Unit/ReleaseFilenameTest.php:193-204` 也只护住了发布面）— 影响：发行面「上传得了、页面看不到」，正是本仓既有测试判据点名过的旧硬编码残留类 — 建议：页面白名单同样取 `ReleaseDomainService::PRODUCTS`（或显式产品线矩阵）。

[ctl|错漏|S3] 测试建表与迁移分叉：CreatesCtlTables 的 releases 无 kind 列，kind 落库路径零测试 — 证据 `tests/CreatesCtlTables.php:15-35`（CREATE TABLE releases 无 kind）vs `database/migrations/2026_10_10_000001_add_kind_to_releases_table.php:17`；全 tests 无一处调 `ReleaseService::upload`（grep 'upload(' 零命中）— 影响：`ReleaseService.php:44,66` 的 kind 落库与 `ManifestService.php:280` 的读回从未在真 schema 上验过；同 CPI-47「测试态/生产态 schema 静默分叉」家族 — 建议：测试建表跟随迁移（或改用迁移建表），补 upload→manifest kind 往返用例。

[ctl|违反设计|S3] deploy.sh 是「rsync --delete 部署即覆盖」，与生产 7 个月漂移、非 git 的事实（reference-ctl-production-deploy）冲突，且无漂移检测 — 证据 `scripts/deploy/deploy.sh:162-167`（`rsync -avz --delete`）、备份仅留 5 份 tar（:127-141）、无任何「生产领先于仓」检查；对照口径「整仓部署需单独发布」— 影响：下一次全量部署会无提示覆盖生产侧 2026-03 以来的手改，并连带删掉生产独有的垃圾文件（那颗 shell 码名文件按 --delete 规则会被清，属副作用而非修复）— 建议：发布前置 diff/清单核对步骤 + 脚本头注明「整仓覆盖需单独发布审批」。

[ctl|错漏|S3] db-setup.sh 远程导入段口令上命令行（ps 可见）— 证据 `scripts/deploy/db-setup.sh:256-266`（`mysql -u "$DB_USER" -p"$DB_PASS"` ×2，口令取自远端 .env）— 影响：CPI-43 只修了本机侧（:37-46、:150-161 用 --defaults-extra-file），远端同病遗留，生产主机任意用户可读 — 建议：远端同样走 option-file。

[ctl|垃圾|S4] docs/ 三件错位/垃圾内容 — 证据 `docs/xuqiu.md:1`（「AI+IP 创作助手小程序」微信小程序需求，与 MyNet 无关）；`docs/mynet_api-hotfix-tasks.md:3-5`（2026-04 面向旧路径 `/Users/arthur/Devel/WorkSpacePHP/lyt.com/mynet_back/` 的 mynet_api 任务单，放错仓）；`docs/todo.md:1-15`（快配脑暴稿，QuickConfigService 已实现）— 影响：文档门面噪音、误导检索 — 建议：xuqiu 删、hotfix-tasks 移交 mynet_api 或归档、todo 归档。

[ctl|历史包袱|S4] 一次性导入脚本与已退役下载域默认值 — 证据 `scripts/import_gnb_v1.6.0.a.sh`（版本锁死；`docs/knowledge-base/deployment-registry.md:85` 只引 v1.6.4）；`app/Console/Commands/ImportGnbFromDownload.php:36,76`（默认 `https://download.mynet.club/gnb`，该域已收敛到 ctl.mynet.club/d）— 影响：旧脚本/旧默认值被顺手跑会打错源 — 建议：v1.6.0.a 归档前先问（repo-migration.md:283 有「别顺手改 GH_BASE」的叮嘱），默认 base-url 改现域。

[ctl|过期|S5] README 是 Laravel 脚手架原文 — 证据 `README.md:1-60`（About Laravel/Sponsors 全文，无一句 ctl 职责；正本在 `docs/knowledge-base/projects/mynet_ctl.md`）— 建议：换成分发中心一页纸 + 指针。

### mynet_openwrt

[openwrt|历史包袱|S2] 已废弃的「route.conf 路由注入」路线仍在生产链上（违反 O1-③）— 证据 `luasrc/model/mynet/node.lua:204-215`（写 route 后调 `proxy_m.route_inject()` 注入策略路由进 GNB route.conf）、`luasrc/model/mynet/proxy.lua:280-334,374-437`（INJECT_MARKER/route_inject/route_restore/route_inject_status/strip_route_injections）、`node.lua:1128-1132`（生成 OS 路由前反向剥离注入段）；对照 `Makefile:109`「路由唯一路径 = 桥 → Go apply --auto（O1-③）」与 `scripts/runtime/README.md:40-42` — 影响：Lua 与 Go 双写路由面（CHANGELOG 自述曾抢 0xc8/200 对象），marker 段把 /8 /16 大段塞进 route.conf 的历史事故（`docs/FLOWS.md:176`、CHANGELOG.md:56「714 条/peer」）随时可复发 — 建议：按 O1 裁定退役注入/剥离对与 proxy.htm 的 Route Inject 徽标（`luasrc/view/mynet/proxy.htm:215,483`）；不涉 gnb 仓 route.conf 本体。

[openwrt|错漏|S3] Lua 面 API 目标仍是 /api/v2（back 只有 v1）— 证据 `root/etc/mynet/conf/config.json:4`（`api_base_url: https://api.mynet.club/api/v2`）、`luasrc/model/mynet/api.lua:152-159,215-220`（versioned_base 升 v2、config-bundle/heartbeat 走 v2）、`luasrc/model/mynet/system.lua:700,805,818`（POST /api/v2/monitor/heartbeat）；mynet_api 全仓无 v2 路由（grep 实证）— 影响：在线拉配置/心跳/命令回执链路整体 404（命令回执另打 `http://127.0.0.1:8096/api/v1/command-result`，`system.lua:475`，两套口径并存）— 建议：随 CPI-69 契约翻转同批把 Lua 面改 v1/tenants（属 O1 迁移面）。

[openwrt|冗余|S4] heartbeat.sh 装进包却无人调度，注释口径与 postinst 相反 — 证据 `scripts/shell/heartbeat.sh:8-9`（自称「cron（postinst 自动写入）: */5…」）vs `Makefile:104-106`（装 /usr/bin/mynet-heartbeat）与 `Makefile:226-228、158-161`（postinst/prerm 只 `sed -i '/mynet-heartbeat/d'`，从不写入）— 影响：死件随包分发；真被手工加 cron 还会与 mynetd 双心跳并打到 /api/v2 死端点 — 建议：确认无人手工使用后连安装行一起退役，或改注释说明「仅手动排障用」。

[openwrt|垃圾|S4] 跟踪在库的备份文件 `luasrc/view/mynet/guest.htm.bak` — 证据 `git ls-files`（已跟踪；文件名扫描四仓唯一 .bak）；`Makefile:50` 的 `*.htm` glob 不会装它 — 影响：库垃圾、易被误改 — 建议：删除。

[openwrt|无人调用|S4] 13 个 LuCI api_* 端点零调用 — 证据 `luasrc/controller/mynet.lua` entry 表：`auto_repair、gnb_start、gnb_stop、gnb_restart、mynetd_start、mynetd_stop、mynetd_restart、svc_start、svc_stop、svc_restart、service_detail、validate_config、guest_nodes`；复核面：`luasrc/view/`、`htdocs/`、`scripts/`、`mynet_agent/`（全 grep 无引用，仅 `mynet.js:644` 的 `svc_state` 是数据键非端点）— 影响：旧启停/自愈面残留路由，与「桥 = 页面唯一写入口」（`luasrc/model/mynet/bridge.lua:3-5`）并存，绕过 ACL/桥审计面 — 建议：逐个裁定删除或接线（guest_nodes 与 guest 家族一并核；删前见疑点 2）。

[openwrt|过期|S5] `.github/copilot-instructions.md` 仍写 rc.mynet / route.mynet 为现行布局 — 证据 `.github/copilot-instructions.md:169,254-255,267`（4 处）vs `Makefile:64,208`（init 不随包、route.mynet 已退役）— 影响：按文档实现会重建已退役件 — 建议：改写或加横幅指向 O1 现状。

[openwrt|过期|S5] docs/ 陈旧件一批 — 证据 `docs/SESSION_NOTES_20260930.md`、`docs/UPGRADE_PLAN_192_168_0_2.md`、`docs/UPGRADE_PLAN_192_168_8_1.md`、`docs/TEST_REPORT_E2E_20260419.md`、`docs/TEST_REPORT_PROXY_20260503.md`、`docs/TODO_key_import_verification.md`、`docs/MACOS_GATEWAY_PLAN.md`/`MACOS_QEMU_SPLIT_ROUTING.md`（CHANGELOG.md:50 自己判定「与 OpenWrt 插件无关」）；`README.md:34` 自述「包名待改…注释仍可能写着 mynet_lua」而实际包名已是 luci-app-mynet（`Makefile:3`）— 建议：归档到 docs/archive 并给 README 一句话现状。

### mynet_desktop

[desktop|过期|S5] INSTALL.md/README 残留「Windows 计划任务 MyNetd」旧机制口径，与自身 SCM 裁定矛盾 — 证据 `docs/INSTALL.md:204`（「完成后计划任务 MyNetd 已注册并启动」）、`:339`（托盘表「走 launchd（mac）或计划任务 MyNetd（win）」）vs 同文 `:217-230`（「计划任务 ≠ SCM 服务」「service.ps1 已废弃」）；`README.md:9`（「Windows 计划任务」）；NSIS 实际正确（`nsis/mynet-agent.nsi:10-16,145`：sc create mynetd，旧 schtasks 由 `mynet install` 清）— 影响：按 :204/:339 排障会去找不存在的计划任务 — 建议：两处改「SCM 服务 mynetd」。

[desktop|过期|S5] README 标题仍是旧仓名 mynet_desktop-V2 — 证据 `README.md:1` vs workflow 已按 2026-10-10 裁定写「不带 -V2」（`.github/workflows/release.yml:48`）— 建议：标题同步。

### mynet_web

[web|垃圾|S4] 社区域 4 页近空壳占位却在路由里 — 证据 `src/pages/social/Routes.vue`（8 行）、`Services.vue`（8 行）、`Friends.vue`（10 行）、`Nodes.vue`（20 行）+ `src/router/index.ts:9-12` 全挂载；check.sh 只验文件存在不验内容（`scripts/check.sh` lint_router）— 影响：用户可导航到空页；与官网定位双不符（既非官网内容也非可用 WebUI）— 建议：随 web 定位裁定（B2/产品）删除或补齐。

[web|过期|S5] README 旧仓名 + 旧定位描述；Settings 占位符仍写 /api/v2 — 证据 `README.md:1`（「# mynet_web-V2」）、`:3-12`（按「本机域/社区域 WebUI」描述，与官网定位漂移，workspace-review-20261010 §3 已记）；`src/pages/local/Settings.vue:13`（placeholder `https://127.0.0.1:8096/api/v2`）— 建议：定位段重写、占位符改 v1。

[web|错漏|S5] check.sh 的已知缺陷编号写错（CPI-40 ≠ 实际 CPI-45）— 证据 `scripts/check.sh`（打印「已知缺陷（CPI-40）：build 脚本不调打包器…」）vs 台账 `cross-project-issues.md:158`（web 无真构建管线是 **CPI-45**；CPI-40 是 mynet_api 迁移撞表）— 影响：按编号查账会指错条目 — 建议：改编号为 CPI-45。

## 已知账命中

- **CPI-69**（ctl 向导打 /api/v2，back 只有 v1）：仍开放；实测范围比登记更广——`MynetBackClient.php:35-154` 除 `getBundleEnvelope`(:166，已用 v1) 外 10 个方法全 /api/v2（含 login、configuration、route/node、keys、router-keys、config-bundle），原登记仅 3 处。且其「等 zone→tenant 契约翻转」前置已满足（mynet_api `Routes/api.php:251,276,310` 只留 /user/tenants），可排期处置。openwrt 侧同族见新账 [openwrt|S3]。
- **CPI-78 / R8**（parseFilename 拒 exe/dmg/pkg、variant/ext 混用）：已修——`ReleaseDomainService.php:228`（白名单含 exe/dmg/pkg）、`:55-58`（ARTIFACT_KINDS）、`2026_10_10_000001_add_kind_to_releases_table.php`、`ReleaseService.php:32-41`（ext 以上传件为准）；路由层也补了白名单（`routes/web.php:59-60`）。残留旧硬编码=发现 9；衍生缺口=发现 1/2。
- **CPI-73/74/75**（桌面双服务机制/label 分叉）：已按 H2 薄壳收敛——`nsis/mynet-agent.nsi:10-16,145-158`（SCM 委托 mynet install）、`macos/scripts/postinstall`（全文：清 legacy `club.mynet.mynetd`、委托 `mynet install` 写 `com.mynet.mynetd`）；残留仅文档（发现 23）。
- **CPI-77**（desktop CI 缺 GUI embed 输入、ref 浮动 fallback）：已修——`.github/workflows/release.yml:117-142`（checkout mynet_gui + `.env.embed` 构建 + 校验 `/gui/assets/`）、`:103-108`（agent ref 取不到即失败）。
- **CPI-45**（web 无真构建管线）：仍开放，`scripts/check.sh` 每次打印为已知缺陷（编号错写 CPI-40，见发现）。
- **O1**（openwrt init 双份 / Proxy 旧 Lua 路径）：init 已收敛（`Makefile:64,208`「init 不再随包，由 mynet install 从内嵌模板写出」，postinst 委托 `mynet install`）；Proxy 的 route.conf 注入与 Lua 写面残留=发现 16（O0-OPERATION-MATRIX 标「迁移」中）。
- **reference-ctl-production-deploy**（ctl 生产 .180 非 git、停 2026-03、整仓部署需单独发布）：deploy.sh 口径冲突=发现 11；install.sh/upgrade.sh 双面漂移=发现 5。
- **垃圾文件（shell 码名，生产未删）**：仓库侧干净——四仓文件名全扫（`$ ; && 空格 .bak .orig .old ~`）仅 `guest.htm.bak`（发现 19），ctl 根无同类。
- **R1/CPI-71、O2 rpcd 三宿主差异**：openwrt 本轮只核到桥面静态一致（`docs/O2-BRIDGE-METHOD-MAP.md` 记有 check_bridge_acl.sh 三方对齐门禁），未复评合同断层，不重复立账。

## 疑点待验证

1. [ctl] `PageController.php:23` home 只推 tui/gnb/utc 是否产品有意为之（下载面只推三件）——若是，发现 9 降级为口径文档化；若否，按发现 9 处理。
2. [openwrt] 13 个零调用 api_* 端点是字符串静态结论；四仓内无调用方（已含 mynet_agent），但若有仓外脚本/旧页面直呼 HTTP（设备侧遗留）则非死码——删除前建议看真机 uhttpd access log 一轮。
3. [openwrt] heartbeat.sh 若有现场手工加 cron 在用（Makefile 从不登记，但注释误导），退役前先确认无人使用。
4. [ctl] `tests/CreatesCtlTables.php` 与迁移的差异本轮只逐列核了 `releases.kind`；plugins/scripts/api_tokens 等其余表未逐列比对，可能还有同族分叉。
5. [ctl] `scripts/import_gnb_v1.6.0.a.sh` 是否随 gnb 版本推进已可归档——`repo-migration.md:283` 有「import_gnb_*.sh 的 GH_BASE 仍指个人仓，别顺手改」的叮嘱，动前需裁定。
6. [desktop] `dist/` 本地产物（v0.0.0-dirty/localtest 的 dmg/pkg/exe）未跟踪、被 .gitignore 吸收，不算仓垃圾；建议本地清理（未列发现）。
7. [web] 社区域/官网定位重定（B2/产品面）超出代码审计裁定范围，发现 26/27 的处置随该裁定。

## 覆盖说明

- **mynet_ctl**：app/ 全部控制器/服务/Domain、routes/{api,web,console}、tests 全部、scripts/ 全部（含 deploy/ 与 proxy-lists 指针）、Makefile/README/phpunit.xml/.gitignore、docs 非知识库文件全部开卷或 grep；vendor/、resources/views 只做引用面 grep（views 链接面核过 /d/、/api/v2 形态）。实测 `make test`：**60 tests / 483 assertions / 1 skip**（skip=Feature/ExampleTest「前端未构建」）。nginx.conf 核过无 v1→v2 rewrite。
- **mynet_openwrt**：415 个跟踪文件名级清点；Makefile（包/文件清单比对，install 清单与仓库文件核对）、13 个 Lua 模型 + 2482 行 controller + mynet.uc 桥结构与引用面 grep、heartbeat/firewall 脚本、docs 粗扫。未跑 Makefile.guard、未上真机（ubus/rpcd 行为未实测）。
- **mynet_desktop**：25 个跟踪文件全清点；`sh scripts/check.sh all` 实测 **40 通过 / 0 失败**；wintun.dll 三处 SHA256 一致（`e5da8447…`：provenance.md、build-nsis.sh:48、实际文件 shasum）；退役件（systemd/、launchd/、packaging/、service.ps1）实测不存在且 check.sh 有负向守卫（:60-85）；.github/workflows 与 CPI-77 对照。
- **mynet_web**：14 个代码文件全读或引用面 grep；`sh scripts/check.sh all` 实测 **7 通过 / 0 失败 / 1 已知缺陷 / 2 跳过**（eslint/vitest 缺 node_modules 跳过，跳过不算通过）。
- **零引用判定的复核面**：openwrt 端点查了 luasrc/view、htdocs、scripts、mynet_agent 全部消费者；ctl buildDistUrl 查了全仓含 tests；无 @/ 别名体系（PHP 按命名空间 use 语句、Lua 按 require 路径核对）。字符串 grep 属静态检查，标注处已进疑点。
- **只读约束**：本轮未改任何仓库文件；仅跑 `make test`（ctl，内存 sqlite）、`scripts/check.sh`（desktop/web，只读校验）与 grep/find/git 取证；未触生产。
