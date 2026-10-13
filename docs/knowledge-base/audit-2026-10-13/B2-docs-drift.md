# B2 轨审计 — 文档漂移对账（2026-10-13，只读审计，未改任何仓库文件）

范围：`mynet_ctl/docs/knowledge-base/**`（含 spec/、templates/）与 `mynet_agent/docs/**`；代码仅作验证引用。
口径基线（用户裁定）：gnb 的 `route.conf` 是正式配置不可动（废弃的只是安装器「route.conf 路由注入」）；`back` 是 `mynet_back` 合法简写、库名不动。

## 发现清单

### 断言漂移

[断言漂移|S3] guard 自测用例数三处口径不一（45 / 35 / 实际 43） — 文档 `mynet_ctl/docs/knowledge-base/AGENTS.md:191,244`（「45 用例」）、`mynet_agent/docs/MYNET_V1_PLAYBOOK.md:231`（「35 用例」）+ 事实 `mynet_ctl/docs/knowledge-base/templates/.githooks/guard_test.sh:72-…`（`expect "` 用例调用恰 43 条；grep "expect " 出 45 是含 :42 注释与 :49 函数定义） — 守护自测的数字口径不可信，三个版本互相矛盾 — 处置建议：以脚本输出的 `guard 自测：N 通过` 为准统一两处引用（或让脚本回显用例总数后引用）。

[断言漂移|S3] project-memory「当前基线」表 HEAD/未推送已滞后三天，与「唯一权威」身份不符 — 文档 `mynet_ctl/docs/knowledge-base/project-memory.md:20-29`（agent `db398d2`、ctl `8fc5e6e`、openwrt `f7c956e` 未推送 10、gnb 未推送 2）+ `AGENTS.md:17`（称其为「HEAD / 未推送 / 测试计数的唯一权威」）+ 事实：`git rev-parse HEAD` 各仓为 agent `ceb1a5f`(10-12)、api `5a1f4b5`(10-13)、ctl `f148b0c`(10-12)、gui `0da3881`、desktop `786d9ca`、openwrt `c495ff6`(10-11)，`git log --not --remotes` 显示 openwrt 已 0 未推送、反而是 mynet_web 有 1 条未推送 — 10-11~10-13 又有约 15+ 提交，权威表失真，按表推状态会错判 — 建议刷新表或降级为「2026-10-10 快照」并加「已过期」横幅。

[断言漂移|S3] README 称「V1-8/V1-9 仍 ☐」，V1-9 早已 ✅ — 文档 `mynet_ctl/docs/knowledge-base/README.md:127` + 事实 `long-range-plan-deep.md:309`（V1-9 ✅ 2026-09-24）、`:310`（V1-10 亦 ✅），仅 V1-8（:308）仍 ☐ — 导航层对底座线进度判断错误 — 建议改「V1-8 仍 ☐」。

[断言漂移|S3] spec/paths.md 平台默认 home 与代码/合同冲突（Windows `C:\mynet`、macOS `/usr/local/opt/mynet`） — 文档 `mynet_ctl/docs/knowledge-base/spec/paths.md:137-141`（§3 表）+ 事实 `mynet_agent/shared/utils/paths.go:406-414`（`PlatformDefaultHome()` = darwin/linux `/var/lib/mynet`、windows `C:\ProgramData\MyNet`）与 `spec/lifecycle-contract.md:241`（G-14 关闭记录同一口径） — 路径 SSOT 与生命周期合同给出两套默认树，安装/排障会指错 — 建议 paths.md §3 补一行「默认 home 以 `PlatformDefaultHome()`/lifecycle-contract §3 为准，本表是历史多树现状」。

[断言漂移|S3] mynet.md 仍称 gnb 私钥「128 hex = 64 字节，含 seed + 公钥」 — 文档 `mynet_ctl/docs/knowledge-base/mynet.md:107`（及 :30「128 字节 hex」措辞）+ 事实 `AGENTS.md:47`（私钥 = `clamp(SHA512(seed))`，128 **hex 字符**，非 `seed‖pub`） — 拿 `seed‖pub` 去算会签错（AGENTS 坑表第 2 条），mynet-overview.md:741 早已把此行列为漂移但 mynet.md 未修、README §1.1 仍标「现行」 — 建议改 mynet.md 并在 README 状态列注明待修。

[断言漂移|S3] 「完整 13 条见 mynet-overview.md 第九节」指向落空 — 文档 `AGENTS.md:274`、`dev-plan.md:731` + 事实：`mynet-overview.md` 的「## 9」是「⑦ proxy」（:455），全文 grep「坑/13 条/踩」0 命中；§14「发现的问题与风险」实为 4+10+3 条 — 坑总表索引失真，「开发前必读 13 条」无处可查 — 建议改指 §14 并核对条数，或把 13 条坑补回 overview。

[断言漂移|S3] GUI_REPO 记录漂移 + 违反「不带 -V2」裁定 — 文档 `project-memory.md:99`（「GUI_REPO 默认写 mynet-club/mynet_gui-V2…需用户确认」）、`spec/node-upgrade-runbook-180-182.md:15`（「默认 mynet_gui-V2 未确认」）+ 事实 `mynet_desktop/.github/workflows/release.yml:48`（`GUI_REPO: mynet-club/mynet_gui  # 用户 2026-10-10 确认（不带 -V2）`）、`repo-migration.md:98`（明令不再引入 `-V2` 后缀） — 两份文档仍记录错误仓名且自称「未确认」，与代码及用户裁定冲突 — 建议两处改记 `mynet-club/mynet_gui`（已确认）。

[断言漂移|S4] AGENTS.md「8 个 repo」与全文档系 9 仓冲突 — 文档 `AGENTS.md:3`（「workspace 及其下 8 个 repo」）+ 事实：`AGENTS.md` §5 项目地图 9 行、`repo-migration.md:280`（「workspace 9 个仓全部在 org 下」）、`workspace-review-20261010.md:3`（「范围：9 个仓」）、实测 9 个 `.git`（agent/api/ctl/desktop/gnb/gui/openwrt/utc/web） — 小口径矛盾 — 建议改 9。

[断言漂移|S4] 「`%!d(string=...)` 下现有 5 个」措辞与 workspace 现状不符 — 文档 `AGENTS.md:268`（「下**现有** 5 个」）、`mynet-overview.md:757-762` + 事实：`find . -name "*string=*"` 与按 nodeId `4147069894021819` 检索 workspace 全部 0 命中（属旧树/生产节点物件） — 「现有」会让读者在本 workspace 白找 — 建议改「旧树/181 实测曾有 5 个（不搬）」。

### 互相矛盾

[互相矛盾|S3] local-api.md 头部与表格自相矛盾：relay 行「已改回 ✅」vs 表内仍「❌ 未实现」 — 文档 `spec/local-api.md:17`（「第二次复核又把 relay 那行改回 ✅」）vs `spec/local-api.md:37`（表内 relay 行仍标「❌ 未实现（2026-09-24 复核更正）…路由表没有 /api/v1/relay」）+ 事实 `mynet_agent/mynetd/localapi/server.go:85`（`s.mux.HandleFunc("/api/v1/relay", s.handleRelay)` + `relay.go` 整套） — 同一文档对同一端点给出两个相反结论，代码站在 ✅ 一侧 — 建议把表内 relay 行按 §3 事实改写（或明确该表只剩「意图登记」价值并在行首去状态符号）。

[互相矛盾|S3] v1 Playbook §3 仓库职责仍按作废 D-4 写，且与自己 §0.5 冲突 — 文档 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md:204`（「mynet_web 是『本机域 + 社区域』合并 WebUI 的在建骨架…计划吸收 mynet_ui 与 mynet_gui」）、`:194`（「mynet_gui：用户和**企业管理员**界面」）+ 事实 `AGENTS.md:347-350`（D-4 已作废：mynet_web 只是官网、mynet_gui 才是用户前端）与 `MYNET_V1_PLAYBOOK.md:97-101`（自己引的裁定「GUI = 普通用户前端，管理面走 console」） — v1 上位口径会把人带回收编 mynet_web/给 GUI 塞管理面 — 建议改 §3 两处为现行口径。

[互相矛盾|S3] README 将四份「已冻结旧线」spec 标为「现行」 — 文档 `README.md:72-75`（service/plugins/wizard/auth 行状态列「现行」）+ 事实 `spec/service.md:2-5`、`spec/plugins.md:2`、`spec/wizard.md:2`、`spec/auth.md:2`（均自declared「已冻结的旧线（mynet_lua / mynet_client），不是当前的实现依据」） — 导航表与文档自述相反，冻结线会被当现行实施依据 — 建议 README 状态列改「冻结（旧线对照）」。

[互相矛盾|S3] repo-migration.md 仍按 D-4 旧决策描述 mynet_web（多处未带作废标记） — 文档 `repo-migration.md:44`（「mynet_web/ … 一份 WebUI（本机域 + 社区域）」）、`:66`（「合并两仓」）、`:140`（「新建（合并 mynet_ui + mynet_gui）…已定合并」）、`:321`（「S8 mynet_web…一份 UI 两个功能域」）+ 事实 `AGENTS.md:347`（D-4 2026-09-24 已作废）、`repo-migration.md:275-277`（仅 §4.3 标了作废） — 除 §4.3 外四处仍以旧决策作指导性陈述 — 建议四行补「D-4 已作废」标记或改写为历史记录。

[互相矛盾|S4] back-baggage-cleanup.md 的 RBAC 拆除结论与 operator 回装裁定冲突未标注 — 文档 `spec/back-baggage-cleanup.md:43`（T50「整套 RBAC/权限子系统拆除…不需要 RBAC」）、`:57`（「operator_tenants 表本就不存在…排除该方向」）+ 事实 `AGENTS.md:100-105`（2026-10-01 裁定 operator 回装、roles/operator_tenants/RBAC 随之落地）、`mynet_api/deploy/schema/mynet_back-schema.sql:1082,1269`（`operator_tenants`/`roles` 建表） — 归档状态的旧结论未注「已被 10-01 裁定部分推翻」，照读会以为 RBAC 面不该存在 — 建议文首补一行指向 AGENTS §1.6 例外。

### 过期指针

[过期指针|S3] `mynet_ui` 仍被多份现行文档列为「有效/适用项目」 — 文档 `spec/README.md:5,20`、`spec/control-plane.md:4,437`、`spec/data-models.md:3`、`integration-plan-v2.md:17,50,74,78,97`、`mynet-overview.md:60,71` + 事实：workspace 无 `mynet_ui` 仓（仅 mynet_gui/mynet_web），`spec/local-api.md:9` 已声明「mynet_ui 不是本 workspace 的仓」 — 同一知识库内一处已更正、六份仍引用幽灵仓 — 建议批量把 mynet_ui 引用改注「历史仓，页面已作 mynet_web 素材」。

[过期指针|S3] node-keying.md「依据」指向不存在的旧仓路径 — 文档 `spec/node-keying.md:5`（`mynet_agent-integration/internal/utils/gnb_ed25519.go`、`mynet_agent-integration/internal/daemon/monitor/monitor.go`、`mynet_tui/internal/{utils,tui}/gnb_ed25519.go`）+ 事实：两仓名均不在 workspace，现实现为 `mynet_agent/mynet/keys/gnb_ed25519.go`（及 `gnb_ed25519_sign.go`） — 密钥口径的取证链指空 — 建议改指 `mynet_agent/mynet/keys/`。

[过期指针|S4] v1 Playbook 指导用 `make pkg-darwin` 出包，该 target 已移除 — 文档 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md:83`（「节点侧改动要 make dist + make pkg-darwin 重出包」）+ 事实：`mynet_agent/Makefile` 无 `pkg-darwin`（H2 退役 `packaging/darwin`，`project-memory.md:70`） — 照做直接 make 报错 — 建议改「make dist + mynet_desktop 安装器」。

[过期指针|S4] v1 Playbook 称「GUI 仓没有类型检查」 — 文档 `MYNET_V1_PLAYBOOK.md:102` + 事实 `mynet_gui/package.json:24-25`（`check:types`/`check:types:update`，vue-tsc + 棘轮）、`workspace-review-20261010.md:91`（194 错误/38 文件基线、棘轮通过） — 低估 GUI 防线、字段名风险判断失真 — 建议改写为「有类型棘轮但非零错误」。

[过期指针|S5] install-chain-status-20261002.md 是 10-02 过程记录滞留 docs/ 根且指向已退役路径 — 文档 `mynet_agent/docs/install-chain-status-20261002.md:27`（指向 `packaging/darwin/scripts/postinstall`）+ 事实：`mynet_agent/packaging/` 仍有 darwin 目录但 `postinstall` 委托链已迁 `mynet_desktop/macos/scripts/postinstall`（`project-memory.md:70`） — 带日期的过程对账与在世文档混放（正是「过程记录干扰」痛点） — 建议归档或文首加「历史快照」横幅。

### 废决策残留

[废决策残留|S3] v1 Playbook / repo-migration 仍按 D-4 旧决策指导（与上「互相矛盾」两条同源，此处记废决策面） — 文档 `MYNET_V1_PLAYBOOK.md:204`、`repo-migration.md:44,66,140,321`、`dev-plan.md:658`（#51 行「D-4 | UI 合并（Quasar 两域骨架已立；mynet_ui 页面批量迁移待续）」仍挂 ☑ 叙述）vs `dev-plan.md:211`（D-4 行已标 ~~作废~~）+ 事实 `AGENTS.md:347-350`（D-4 作废裁定） — 三份文档按已作废决策给指令/记状态 — 建议 #51 行正文改为「D-4 已作废，仅落 mynet_ui local/ 6 页」。

### 台账失联

[台账失联|S3] CPI-71~82 批次「未修复」大面积失真：R2–R5/R7–R12 对应项已修未移表 — 文档 `cross-project-issues.md:10-28`（CPI-72~CPI-82 全部标「未修复」）+ 事实 `spec/lifecycle-contract.md:228-242`（G-1/2/3/4/5/8/9/10/11/12/14/15 已关闭并注明对应 R2/R3/R4/R5/R7/R8/R9/R10/R11/R12）+ 代码抽证：`resolveInstallHome`（G-1/R12）、`cmd_install_openwrt.go` UCI 重写 + `cmd_install_openwrt_test.go`（R2）、nsis 无 `service.ps1`/postinstall 委托（R3/R4）、`mynet_desktop/nsis/vendor/wintun/wintun.dll` + `.guard-c4-allow`（R10 缓解）、`cmd_install_{systemd,launchd,win,unsupported}.go` build tag 互斥（R11） — 台账按「未修复」读会重做已完成工作 — 建议 CPI-72/73/74/75/77/78/79/81/82 移「已修复」，CPI-80 注「随包 wintun 缓解、src 未改」；真正仍开：CPI-71(R1)、CPI-76(R6)。

[台账失联|S3] workspace-review R1–R12「均未修复」与代码现状不符 — 文档 `workspace-review-20261010.md:23,106`（「优先问题（均未修复）」「审查发现的问题均保留『未修复』」）+ 事实：同上 G 表关闭记录 + `mynet_agent/mynet/cmd_install.go`（`--home` 解析前置，R12 修）等 — 报告自身是 10-10 快照但被 README §0 列为「开工必读」现行入口，无任何指向修复状态的注记（仅 R1/R6 仍准） — 建议文首加「R 项处置状态见 lifecycle-contract §5 G 表（R2–R5、R7–R12 已关闭）」。

[台账失联|S4] CPI「修好移入已修复」规则未执行：多条已修条目滞留「待修复」各段 — 文档 `cross-project-issues.md:67`（CPI-36 ✅ 已修复仍在 P1 段）、`:306,321`（CPI-30/30a）、`:273`（CPI-22「已修」）、`:906,927`（CPI-18~21/17「已修」）、`:151`（CPI-44 refresh-nodes 仍在待修复）+ 事实：CPI-36/30/30a 在「已修复」表另有重复行（:1054-1057）；CPI-44 已修 —— `mynet_agent/mynetd/commands/commands.go:93-95`（改 `refresh-config` 并注释）+ `backmap_test.go:223` 守护 — 与文件头「更新规则」（修好移到已修复）相悖，双重记账 — 建议按规则移表去重。

[台账失联|S4] CPI-47「17 张表只在 tests/Schema、迁移没有」疑已随 B-1 修复，条目未更新 — 文档 `cross-project-issues.md:172` + 事实 `mynet_api/database/migrations/2026_09_25_000002_create_monitor_tables.php`、`...000003_create_social_tables.php`、`...000004_create_vpn_extension_tables.php`、`2026_09_23_000001_create_vpn_business_tables.php`（dev-plan #31 B-1「create migration 全套」☑） — 欠债台账虚高 — 建议复核后移「已修复」（本条仅静态比对表名，建议逐表核对）。

### 过程记录残留

[过程记录残留|S4] 「更正/自纠/撤回旧说法」过程叙述混进最终文档（SSOT §2.3 禁止类） — 文档 `spec/local-api.md:3-5,9,15-18,56-…,61,195`（「本行原写…更正」「复核更正」多层嵌套）、`upstream-dsplat-issues.md:541`（「本批自纠一处旧口径」）、`:697`（「自纠一条旧口径（原 F-9 的另一半作废）」）、`dev-plan.md:482`（「① 撤回旧说法『无法配长度』」）、`MYNET_V1_PLAYBOOK.md:105-107`（「⚠️ 上面这段里两处已过期」）+ 规则 `AGENTS.md:166-169`（§2.3） — 修订史叙事与结论混排，读者需自行裁决哪句是现状（local-api.md 由此产生 relay 行矛盾，见上） — 建议：结论改写成现状陈述，修订由 git/归档承担；确属「真正的坑」的（如指向空气的 SSOT 引用）单列坑表。

[过程记录残留|S4] project-memory 保留被取代状态的打补丁句式（「已被取代」「用户质疑后自纠」） — 文档 `project-memory.md:54`（「收严（用户质疑后自纠…）」）、`:121`（「（2026-10-10：该状态已被取代 ——…）」） + 规则 `AGENTS.md:166-169` — 记忆体是允许过程记录的载体，但已取代状态段（111-125）整段留白易误导 — 建议把已被取代段压缩为一行历史注。

## 已知账命中

1. **dev-plan §3 #30 C-6 / #16 F-4 状态列依据过期（任务给定先例）** — 属实，但已带注记：`dev-plan.md:622,636` 状态列挂「※ …已过期，见 §3 后『2026-10-10 对齐注』」，`:703-719` 注内给出实测更正（`cmd/mynet_gateway/main.go` 存在、`NewRedisPresence` 被 `:90` 接线、`gateway/cross_instance_test.go` 在；windows 平台文件齐全、`Makefile` DIST_MATRIX 含 windows）。抽证复核成立（`cmd/mynet_gateway/main.go:90`、`gateway/presence_redis.go:29`、`mynet_agent/Makefile:84-91` 含 `windows:windows:amd64:amd64:`）。残余问题：状态列本身仍是旧记号（☐ 撤勾 / ⚠️ 部分），且 `dev-plan.md:717` 的 `Makefile:76` 行号已漂到 `:84`。
2. **#75 ◐→☑ 已修** — `dev-plan.md:684` 状态列已是 ☑。
3. **route.conf 口径** — 抽查 `node-connectivity-checklist.md:92-101`、`troubleshoot-connectivity.md:190-192`、`spec/route-firewall-split.md:46`、`spec/lifecycle-contract.md:162`、`spec/proxy-conf-design.md`（标题「route.conf 的延续」）：均只废弃「route.conf 注入/灌公网段」，gnb `route.conf` 保持正式配置地位，**未发现一刀切口径错误**。
4. **`back` 命名口径** — 全库未发现把 `back` 当待清命名的表述；`mynet_back` 均为数据库/备份语境（`deployment-registry.md`、`dba-operations-ticket-2026-10.md`），合规。
5. **CPI-16（6 份 spec 适用头指向旧线）已在台账自注**（`cross-project-issues.md:940`）；本轮新发现的 `mynet_ui` 残留（见上）可视为该条未清干净的尾巴。

## 疑点待验证

- **测试计数未复跑**（api 471/4690/1skip、ctl 54/426/1skip、gui 41 tests/194 类型错、agent 19 包）：只读审计未执行套件（避免写 storage/cache）；与 workspace-review §4、project-memory 记载互洽但未独立证伪。guard 自测亦未执行，43 用例为静态计数（函数循环调用若存在会低估——未见）。
- **gnb 未推送 2 条**（`project-memory.md:26`）：裸仓 `git log --branches --not --remotes` 受限返回 0，未能证实也未能证伪。
- `AGENTS.md:268` `%!d` 5 个 / `mynet-overview.md:757` 「5 个节点各一份」：物件在旧树/生产节点，workspace 内 0 命中，无法核原数（措辞问题已单列）。
- `AGENTS.md:263` 「私钥三处出口」收口状态、`deployment-registry.md` §8 部署清单、`api-specification.md`（1913 行）端点逐条、`skill-kit.md`/`brand-guide.md`/`url-architecture.md`/`troubleshoot-connectivity.md`/`long-range-plan.md`/`long-range-plan-t.md`：本轮仅低密度抽样或未抽，未发现硬矛盾但不构成核验。
- `mynet_agent/packaging/darwin` 目录仍在（仅 `make pkg-darwin` 与 scripts 已退役）：project-memory 称「退役 packaging/darwin」，实际为部分退役，是否整体删除待裁定（`spec/lifecycle-contract.md:46` 仍写「待退役」，与 project-memory:70「已退役」措辞不一）。

## 覆盖说明（每份文档抽验的断言）

| 文档 | 抽验断言（条数） | 结果 |
|---|---|---|
| AGENTS.md | 9：relaypolicy deny-first（`relaypolicy.go:52-56` ✓）、8096/8710 端口（`mynet_gateway/main.go:32` ✓）、ConsoleAuthController:13-14/:50（vendor 文件逐行 ✓）、31→3~4 module（vendor 4 包 ✓）、crumb-page（webui index.html ✓）、guard 45 用例（✗ 43）、8 repo（✗ 9）、13 条坑/第九节（✗ 落空）、%!d 现有 5 个（✗ 措辞） | 5✓ 4✗ |
| project-memory.md | 8：HEAD/未推送表（✗ 滞后）、lifecycle_contract_test.go / platform_filetags_test.go / resolveInstallHome / PlatformDefaultHome（✓ 齐）、desktop 薄壳（service.ps1/systemd/launchd 均无 ✓、postinstall 委托 ✓）、.guard-c4-allow + wintun.dll + wintun-provenance.md（✓）、GUI_REPO -V2（✗ 已改不带 -V2）、pkg-darwin 退役（✓） | 6✓ 2✗ |
| dev-plan.md | 7：76 项顺序表（✓ 至 #76）、#30/#16/#75 状态注（✓ 已注，状态列仍旧记号）、Makefile:76 DIST_MATRIX（✗ 行号漂至 :84，内容 ✓）、F-4 console SPA / G-4 前半（抽 file:line 命中 ✓）、D-4 行 #51（✗ 旧叙述） | 5✓ 2✗ |
| MYNET_V1_PLAYBOOK.md | 9：BundleEnvelope 双侧（✓）、vpn_driver.go DriverType*（✓:7-8）、driver/{gnb,wireguard}+三测试文件（✓）、IPPool IPS_PER_GROUP=6/calculateIPRangeFromSequence/降级日志（✓ IPPool.php:42,118,85）、TERM_ZONE/terms/termZone（✓ terms.ts:14、cmd_node.go:24）、35 用例（✗）、pkg-darwin（✗）、GUI 无类型检查（✗）、mynet_web 合并骨架/GUI 管理员界面（✗ D-4） | 5✓ 4✗ |
| workspace-review-20261010.md | 5：R11 go list 全编入（与 H1 前状态一致，现已修）、R12 requireHome（现已修）、R2 UCI（现已修）、R8 parseFilename（现已修）、R6 update（**仍成立**：`upgrade/install.go:389` 按名找二进制、`cmd_update.go:87` 按 runtime.GOOS） | R6 仍准，其余 4 条状态过期 |
| cross-project-issues.md | 6：CPI P1-3 cors `['*']`（现 cors.php:6 改 env 白名单、条目半过期）、CPI-42 gateway 总闸默认 false（✓ 仍成立 config/gateway.php:20）、CPI-44 refresh-nodes（✗ 已修未移表）、CPI-47 17 表（✗ 疑已修）、CPI-71~82 批次（✗ 9/12 已修）、维护工作流「修好移表」（✗ 未执行） | 1✓ 5✗ |
| README.md | 7：§3.0 V1-8/V1-9（✗）、§1.2 service/plugins/wizard/auth 现行（✗ 冻结）、§1.4/§6 引用文件（✓ 全部存在）、spec 清单缺 v1-permission-matrix/v1-error-codes/gnb-compat-baseline 等（✗ 不全）、api-specification 1800+ 行（✓ 1913） | 3✓ 4✗ |
| spec/local-api.md | 5：relay 矛盾（✗）、internal/localapi 路径更正（✓ 处理方式带过程叙述）、relay.go HTTPS 反代（✓ :49 注释形状）、ubus 未实现（✓）、token 只种不验（定性，未单测） | 3✓ 1✗ |
| spec/paths.md | 5：paths.go 存在（✓）、mynet subject 命令（✓ commands.go:43,108）、§2 三面职责/mynetd.env 退役（与 ctl `f148b0c` 提交信息互洽 ✓）、平台默认 home（✗ 见清单）、var/subject.json（subject.go 缓存 ✓） | 4✓ 1✗ |
| spec/lifecycle-contract.md | 5：G-1/2/3/4/5/8/9/11/12/14/15 关闭件抽查（resolveInstallHome、cmd_install_* 互斥、nsis/desktop 现状、wintun ✓）、G-2 文件名 `cmd_install_openwrt_only.go` 与实际 `cmd_install_openwrt.go` 不一致（✗ 小漂移） | 4✓ 1✗ |
| spec/node-keying.md | 3：依据路径（✗ 2 条旧仓路径）、gnb 只读口径（✓） | 1✓ 2✗ |
| spec/control-plane.md / driver-abstraction.md / proxy-split.md / proxy-conf-design.md / route-firewall-split.md / route-apply-unification.md / data-models.md / spec/README.md | 各 2–3：mynet_ui 引用（✗ 见清单）、gateway `/v1/relay` 口径（✓ 与 gateway 实现互洽）、proxy-split 新旧线标记（✓）、route.conf 口径（✓）、driver-abstraction「设计稿」状态（实现已超前，未计✗） | 约 10✓ 5✗ |
| conventions.md | 3：mynet_agent_* 前缀（✓ :313）、route.conf 段落（✓ 双层口径）、JS 段 mynet_gui/mynet_web（✓ 现名） | 3✓ |
| mynet-overview.md | 5：D-4 作废条目（✓ :798）、14.2 漂移表 mynet.md 私钥行（✓ 问题登记成立但未修）、%！d 残留行（✗ 措辞）、mynet_ui 项目地图（✗）、13 条坑指针（✗ 同 AGENTS） | 2✓ 3✗ |
| upstream-dsplat-issues.md | 3：4 个框架包（✓ 与 vendor 一致）、archive 收编指针（✓ 文件在）、自纠叙述（入过程残留） | 2✓ |
| mynet.md | 2：私钥布局（✗）、目录形状（security/ed25519 ✓） | 1✓ 1✗ |
| agent 其余 docs（MYNET_NETWORK_MODEL / LOCAL_WEBUI_EXPERIENCE / webui-local-ux / webui-ia / install-chain-status） | 各 2：webui 目录指针（✓ `mynetd/localapi/webui/`）、/api/v1/config/connect 契约（指向 local-api ✓）、8096 端口（✓）、install-chain 的 packaging/darwin 指针（✗） | 约 7✓ 1✗ |
| templates/（Makefile + guard.sh/guard_test.sh） | 3：A1–C4 红线名目（✓ guard.sh 结构与 AGENTS §2.6 对应）、C4_ALLOW/.guard-c4-allow 机制（✓）、用例数（✗ 43≠45） | 2✓ 1✗ |

合计约 80 条断言抽验；S3 15 条 · S4 9 条 · S5 1 条（另 3 条「废决策残留」与「互相矛盾」部分同源，见条内互引）。未复跑任何测试套件、未改任何仓库文件。
