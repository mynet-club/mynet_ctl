# 仓库结构与迁移计划

> 前提：**全 vibe coding**（不排人力，按阶段顺序推进）·
> 方法：**整理式重建**——新 repo + 新结构，从旧代码**搬运正确部分**，明确不搬死代码与 bug 产物。
> 配套文档：[`dev-plan.md`](dev-plan.md)（建完仓库后做什么）· [`mynet-overview.md`](mynet-overview.md)（设计与决策）

---

## 0. 为什么是「整理式重建」而不是从零重写

旧代码确实乱，但里面有**踩坑得来的隐性知识**，从零重写会丢。所以方法是
「**新骨架 + 搬运实现好的部分 + 逐条核对隐性知识**」，而不是照着旧代码重敲。

| 隐性知识 | 旧位置 | 丢了会怎样 |
|---|---|---|
| `_gnb_peer_heal` 的 15 分钟冷却 + 启动 90s 宽限 | `mynet_openwrt/scripts/shell/heartbeat.sh:61-128` | 持续不通时循环抖动 |
| `gnb_es` 子进程的 PPID 匹配清理 | `luasrc/model/mynet/node.lua:1335` | 孤儿进程累积 |
| `IsAllowedWith` 含 `%` 一律拒（防 `%2f` 绕过） | `mynet_agent/internal/relaypolicy/relaypolicy.go` | 路径穿越 |
| `IsAllowedWith` **先查 deny 再查 allow** | 同上 | 改白名单时漏删 fragment 导致失效 |
| `coordinator` 的 snapshot/restore/journal/`apply.lock` | `mynet_agent/internal/coordinator/applier.go` | 失败回滚不完整、并发写坏 |
| 秒精度唯一键的 upsert 规避 | `mynet_api/.../NodeMonitorService.php:135` | 同秒心跳撞 `uk_node_snapshot` |
| GNB 私钥是 `clamp(SHA512(seed))` **非** `seed‖pub` | `mynet_agent/internal/utils/gnb_ed25519.go` 注释 | 签名错（**R 对 S 错**，极隐蔽） |
| `keypair.c` 是**就地 clamp 后落盘** | 同上 | 拿未 clamp 的 `PrivateKey` 当标量会算错签名 |
| GNB 掩码只认 /32 /24 /16 /8 /0，其余**静默忽略** | `mynet_gnb/src/src/gnb_conf_file.c` | 配置错了不报错 |
| `node.conf` 的 `daemon on` 要改 `--systemd`/`daemon off` | — | procd 拿不到前台进程 |

---

## 1. 目录结构（workspace + 各项目独立 repo）

**不用 monorepo 的硬理由**：`mynet_gnb` 的补丁机制依赖独立 git 历史来 rebase 上游
（`git format-patch` / `git am -3`），并进 monorepo 会破坏它。

```
mynet-club/                          workspace（本仓，只放文档与索引）
├── docs/
│   ├── mynet-overview.md            设计与决策（已成文）
│   ├── dev-plan.md                  开发计划（已成文）
│   ├── repo-migration.md            本文
│   └── refs/                        opengnb / wireguard 协议资料（已成文）
├── index.html                       产品形态（已成文）
│
├── mynet_agent/        [新建 repo]  Go — mynetd（纯通信）+ mynet（执行器）+ mynet_gateway + mynet-updater
├── mynet_web/          [新建 repo]  Vue3+Quasar — 一份 WebUI（本机域 + 社区域）
├── mynet_desktop/      [新建 repo]  非 OpenWrt 平台适配（安装/服务/打包）
├── mynet_api/         [新建 repo]  Laravel — 业务权威（按需 module）
├── mynet_openwrt/      [改造 repo]  LuCI 壳 + ubus 薄桥 + ipk
├── mynet_ctl/          [挂载 repo]  分发中心 + 知识库中枢（docs/knowledge-base/）
├── mynet_gnb/          [挂载 repo]  opengnb fork + 补丁包（git 历史必须保留）
└── mynet_utc/          [挂载 repo]  UDP→TCP 隧道（独立可选组件）
```

**命名**：产物前缀统一 `mynet_agent_*`（`conventions.md` 现为 `agent_*`，需同步改）。

---

## 1.1 GitHub 仓库与旧代码路径

**org**：`https://github.com/mynet-club` · **命名**：**当前服务用裸项目名，不加后缀**
（2026-10-01 已去掉迁移期的 `-V2`；业务权威仓由 `mynet_back` 更名为 **`mynet_api`**，
对齐 `api.mynet.club`，并与同为 Laravel 的 `mynet_ctl`（分发/知识库）区分）。

| 本地目录 | GitHub repo | 旧代码路径 | 性质 |
|---|---|---|---|
| `mynet_agent/` | [`mynet_agent`](https://github.com/mynet-club/mynet_agent) | `lyt.com/mynet_agent`（自 `mynet_tui` 抽出，`detector` 亦源自 tui） | **新建** + 整理式搬运 |
| `mynet_web/` | [`mynet_web`](https://github.com/mynet-club/mynet_web) | `lyt.com/mynet_gui` + `lyt.com/mynet_ui` | **新建**（合并两仓） |
| `mynet_desktop/` | [`mynet_desktop`](https://github.com/mynet-club/mynet_desktop) | `lyt.com/mynet_desktop`（7 文件占位） | **新建** |
| `mynet_api/` | [`mynet_api`](https://github.com/mynet-club/mynet_api) | `lyt.com/mynet_back` | **新建** + 整理式搬运（按需 module） |
| `mynet_openwrt/` | [`mynet_openwrt`](https://github.com/mynet-club/mynet_openwrt) | `lyt.com/mynet_openwrt` | **改造**（历史保留） |
| `mynet_ctl/` | [`mynet_ctl`](https://github.com/mynet-club/mynet_ctl) | `lyt.com/mynet_ctl` | **挂载**（历史保留；**知识库中枢在此**） |
| `mynet_gnb/` | [`mynet_gnb`](https://github.com/mynet-club/mynet_gnb) | `lyt.com/mynet_gnb` | **挂载**（历史保留，rebase 上游） |
| `mynet_utc/` | [`mynet_utc`](https://github.com/mynet-club/mynet_utc) | `WorkSpaceCPlus/mynet_utc` | **挂载**（历史保留） |

**历史仓（GitHub 已 Archive，git 历史原样保留，不删）**：

| GitHub repo | 原名 | 说明 |
|---|---|---|
| `mynet_agent-legacy` · `mynet_ctl-legacy` · `mynet_web-legacy` · `mynet_utc-legacy` | 同名无后缀 | 旧线；为腾出当前服务名而改 `*-legacy` |
| `mynet_back-legacy` | `mynet_back` | 旧业务后端；当前业务权威已改名 `mynet_api` |
| `mynet_tui` · `mynet_lua` · `mynet_client` · `mynet_javis` · `mynet` | 不变 | 冻结归档，无撞名，仅 Archive |

**旧代码根目录**（搬运清单 §4 的相对路径以它们为基准）：
- `/Users/arthur/Devel/WorkSpacePHP/lyt.com/` —— 21 个 `mynet_*` 目录
- `/Users/arthur/Devel/WorkSpaceCPlus/mynet_utc` —— UTC 单独在 C 工作区

**冻结归档（不迁移，只作参考）**：

| 旧路径 | 说明 |
|---|---|
| `lyt.com/mynet_tui` | ⚠️ **`mynet_agent` 的来源**；`internal/detector` 也源自这里 |
| `lyt.com/mynet_client` · `mynet_client-hotproxy` | Node.js 客户端，已被 agent 线取代 |
| `lyt.com/mynet_lua` | OpenWrt LuCI 旧线（已被 `mynet_openwrt` 取代） |
| `lyt.com/mynet_web` · `mynet_front` | 官网 / 旧前端 |
| `lyt.com/mynet_main` · `mynet_saas` | 旧 Laravel / 框架相关 |
| `lyt.com/mynet_back_old` · `mynet_back_20250904` · `mynet_backup_20250610_155701` | back 的历史备份 |

> **命名约定（此后遵守）**：当前服务 = 裸名；历史仓 = `*-legacy` + Archive。
> 不要再引入 `-V2` 之类代际后缀；需要冻结时用 `-legacy` + Archive，不删仓。

---

## 1.2 守护机制（make 入口 + git hook 强制闸）

**决定：双层守护。** `make` 是显式入口（随时可跑），git hook 是强制闸（防漏）。

| 层 | 机制 | 内容 | 时机 |
|---|---|---|---|
| 入口 | `make check` | guard + lint + test | 随时 |
| 闸 1 | `.githooks/pre-commit` → `make guard` | **轻量**：安全红线 + 文件名（秒级） | 每次提交 |
| 闸 2 | `.githooks/pre-push` → `make check` | **重量**：lint + 全量测试 | 每次推送 |

**为什么这样分**：pre-commit 跑全量测试会拖垮提交体验；
pre-push 才是「不推坏东西」的关口（DoD 要求「测试通过」，这里强制执行）。

**模板**在 [`templates/`](templates/)，各 repo 复制一份（`Makefile` + `.githooks/`），
`make install-hooks` 装 hook（`git config core.hooksPath .githooks`）。
**自包含不跨仓依赖**——各 repo 可独立 clone / build，hook 不能断。

`make guard` 的**三类红线**（见 `templates/.githooks/guard.sh`，已功能实测 7 用例）：

| 类 | 红线 |
|---|---|
| **A 安全** | A1 硬编码凭据 · A2 私钥泄露 · A3 敏感文件名 |
| **B 结构** | B1 `mynet_gnb/src/` 只读 · B2 mynetd 不得依赖系统能力 · B3 relaypolicy 不得含鉴权 · B4 业务对象不得含驱动术语 |
| **C 产物** | C1 格式化 bug 产物文件名 · C2 端口漂移（≠8096）· C3 命名漂移（≠`mynet_agent_*`） |

**显式豁免**：违规那一行写 `GUARD-OK <原因>`；每次豁免记入 `.git/guard-exempt.log`。
**精确不激进**：误报过多会被 `--no-verify` 绕过，反而失效。

> 语言适配：`Makefile` 里 lint/test 的命令按 repo 类型放开对应块
> （Go: `gofmt`+`go vet`+`go test` · PHP: `pint`+`phpunit` · Vue: `eslint` · Lua: `luac -p`）。

---

## 2. 各项目决策

| 项目 | 决策 | 理由 |
|---|---|---|
| **mynet_agent** | **新建 + 整理式搬运** | 最乱（死代码 3970 行、重复实现 5300 行）；执行器架构要求模块边界大改，改造 ≈ 重建 |
| **mynet_web** | **新建**（合并 `mynet_ui` + `mynet_gui`） | 已定合并；以 Quasar 为基线 |
| **mynet_desktop** | **新建** | 现为 7 文件占位、mynet_client 血统，等于没有 |
| **mynet_api** | **新建 + 整理式搬运** ⚠️ **按需 module** | 31 个框架包里业务只用 3 个（见 §3）；裁掉 27 个后重建成本可控，且能一次性补 create migration |
| **mynet_openwrt** | **改造** | 12 个 model 全实装、83 路由、ipk 链路通；只需改 procd + Lua 分界 |
| **mynet_ctl** | **挂载，不动** | 知识库（`docs/knowledge-base/`，51 文件）在这，是文档中枢 |
| **mynet_gnb** | **挂载，不动** ⚠️ | fork 的 git 历史**必须保留**——补丁包机制（`format-patch` + `rebase.sh`）依赖它 |
| **mynet_utc** | **挂载，不动** | 独立可选组件 |

> **mynet_api 从「改造」改为「新建」**的依据见 §3——按需 module 后框架负担小到可以重建，
> 而重建能一次性解决「无 create migration」「L1/L2/L3 契约缺」两个大债。

---

## 3. mynet_api 按需 module（31 → 3~4）

实测统计（`grep` 覆盖 `app/` `bootstrap/` `routes/`）：

| 模块 | 引用 | 处置 | 说明 |
|---|---|---|---|
| `multi-tenant-saas`（主框架） | — | **必需** | `HasGlobalId`(24) / `BelongsToTenant`(9) / `TenantContext`(4) / `TenantScope`(1) |
| `infrastructure` | 7 | **必需** | `Tenant`/`TenantUser` 基类、`SystemSetting`、`IdentifyTenant`、`ModuleRegistry` |
| `auth` | 2 | **必需** | `User` 基类、`auth:sanctum`、`CheckPermission`/`CheckRbacPermission` 中间件别名 |
| `logging` | 1 | **待定** | `AuditLog` 基类——**不用审计就砍** |
| `ai` | 1 | **可砍** | 仅 `app/Services/ToolRegistry.php` 引 `Modules\Ai\...\Dto\Tool` |
| `conversation` | 1 | **可砍** | 仅 `ConversationController.php` |
| 其余 25 个 | **0** | **全砍** | ai-streaming / api-token / billing / campaign / coupon / developer-portal / domain / event / form / ibot / knowledge / lottery / monitoring / notification / operator / payment / platform / plugin / sms / ssl / storage / ticket / user / voting / workflow |

### 3.1 连带要删的业务残留（与被砍模块同源）

| 文件 | 来源 | 处置 |
|---|---|---|
| `app/Services/ToolRegistry.php` | 框架 AI/Workflow 的 ToolRegistry | **删** |
| `tests/ActionNodeTest.php` + `tests/Stubs/FakeToolRegistry.php` | 测试桩，对端是上面那个 | **删** ⚠️ 这直接解掉 **P0-1**——不必修 `FakeToolRegistry` 签名，删掉更干净 |
| `app/Http/Controllers/ConversationController.php` | 框架 Conversation | **删** |
| `tests/Conversation/`（9 个测试） | 同上 | **删** |

### 3.2 预期收益

| 项 | 现状 | 预期 |
|---|---|---|
| dsplat 依赖 | **31** 个包 | **3–4** 个 |
| 迁移文件 | **135** 个（约 107 张框架表） | **约 20** 张表（tenancy 3 + auth 5 + infrastructure 2 + cache/sessions/rate_limit 3 + VPN/Monitor/Social 业务表） |
| 测试噪音 | 177 个测试里 160+ 是框架 SaaS | 只剩业务测试 |
| 攻击面 | 含 AI/billing/workflow/lottery 等路由 | 只剩业务路由 |

### 3.3 框架中间件别名

`bootstrap/app.php:53` 现注册 `tenant.permission` / `rbac.permission` / `mcp.auth` 三个别名，
**它们是为框架 vendor 模块路由服务的**（back 自有路由挂载数为 0）。
裁掉 `operator`/`platform`/`ai` 等模块后**这三个别名可一并删除**，只留 `auth:sanctum` / `throttle:api` / `tenant.ensure`。

---

## 4. 搬运清单

### 4.1 `mynet_agent`（新建 + 整理式搬运）

**搬**（实现正确、含隐性知识）：

| 旧位置 | 新位置 | 备注 |
|---|---|---|
| `internal/coordinator/{applier,config_writer,gnb_adapter}.go` + tests | `mynet/apply/` | ⚠️ 隐性知识：snapshot/restore/journal/`apply.lock` |
| `internal/domain/{bundle_envelope,vpn_bundle,vpn_peer,entities}.go` + fixtures | `shared/domain/` | BundleEnvelope schema v2，双端对照 |
| `internal/utils/gnb_ed25519*.go` | `mynet/keys/` | ⚠️ 隐性知识：clamp 就地落盘、`SignGNB` 显式 clamp |
| `internal/gateway/*.go` + `cmd/mynet_gateway` | `gateway/` | ⚠️ **要拆掉 `auth.go`**（已定 gateway 不鉴权） |
| `internal/gatewayclient/*.go` | `mynetd/transport/` | |
| `internal/relaypolicy/*.go` | `gateway/relaypolicy/` | ⚠️ 隐性知识：先 deny 后 allow、含 `%` 一律拒 |
| `internal/localapi/*.go` | `mynetd/localapi/` | 改端口 8720 → **8096** |
| `internal/event/*.go` | `mynetd/event/` | |
| `internal/config/` `internal/common/` `internal/logger/` `internal/errors/` | `shared/` | |
| `conf/command.linux.json` 等 | `mynetd/commands/` | 内化 5 条 `bin/mynet` 调用 |
| `internal/detector/*` + `internal/tools/{firewall,routing}/*` | **`mynet/detector/`** | ⚠️ 两套重复，**收敛成一套**（`parseIptablesOutput` 两边同名） |

**不搬**：

| 不搬 | 理由 |
|---|---|
| `internal/{checker,legacy,interfaces}` | 真死代码（0 引用，~647 行） |
| `internal/detector/example.go`（357 行） | 演示代码 |
| `internal/tui/` `internal/composition/` | TUI 已谢幕；UI 归 `mynet_web` |
| `internal/proxy/` + `cmd/mynet_proxy` | 已定下架 |
| `internal/infrastructure/capability/` 的探测逻辑 | 归 `mynet detector` |
| `driver/gnb/conf/*/ed25519/%!d(string=...).pub` | Go 格式化 bug 产物 |
| `tests/api_route_test.go` | e2e 打真实 API + **硬编码生产密码** ⚠️ |
| `internal/legacy` 等死代码的注释 | — |

**新建**（不存在）：

| 新建 | 用途 |
|---|---|
| `mynet/detector/metrics.go` | `mynet metrics --json`（心跳数据源：系统指标 + VPN/peer 状态） |
| `mynet/apply/network.go` | L6 写入（OS 路由/防火墙），从 shell 脚本/Lua 迁 |
| `mynet/service/` | 进程生命周期（委托 procd/systemd/launchd） |
| `mynet/driver/` | 驱动管理（列/装/升），调 `upgrade_service` |
| `mynet/commands.go` | 子命令注册（setup/install/uninstall/update/config/detector/metrics/apply/service/driver） |
| `shared/system_snapshot.go` | `system.json` 的读写与指纹 |

### 4.2 `mynet_api`（新建 + 整理式搬运，按需 module）

**搬**：

| 旧位置 | 新位置 | 备注 |
|---|---|---|
| `app/Modules/VPN/Models/*`（20 个模型） | `app/Modules/VPN/Models/` | ⚠️ 补 `ConnectionGrant`/`KeyExchange` |
| `app/Modules/VPN/Domain/Bundle/*`（17 文件） | 同 | BundleEnvelope schema v2 |
| `app/Modules/VPN/Domain/Services/BundleEnvelopePublisher.php` | 同 | |
| `app/Modules/Monitor/*`（Models/Services/Domain） | 同 | ⚠️ `NodeMonitorDomainService` 已非空壳（361 行） |
| `app/Modules/Social/*` | 同 | |
| `app/Modules/VPN/Middleware/*` | 同 | ⚠️ 隐性知识：`InflateNodeRequestBody` gzip 解压 |
| `app/Modules/VPN/Routes/api.php` | 同 | |
| `tests/BundleEnvelopeV2Test.php` 等 | 同 | **唯一跑得通的测试**（13 tests, 135 assertions） |
| `tests/Schema/{Vpn,Monitor,Social}Module.php` | 同 | 测试库 schema |

**不搬**：

| 不搬 | 理由 |
|---|---|
| 27 个框架模块及其迁移 | 按 §3 裁掉 |
| `app/Services/ToolRegistry.php` + `tests/Stubs/FakeToolRegistry.php` + `tests/ActionNodeTest.php` | 框架 AI/Workflow 残留；**顺带解掉 P0-1** |
| `app/Http/Controllers/ConversationController.php` + `tests/Conversation/` | 框架 Conversation 残留 |
| `routes/admin.php` + `AdminMiddleware` 等自建 admin | 已定：admin 属平台层（框架 platform 模块），不属 back |
| `IdPool` 的自增逻辑 | 补齐 HasGlobalId（P0-6） |

**新建**（不存在，对应 `dev-plan.md` 轨道 B）：

| 新建 | 用途 |
|---|---|
| **create migration 全套** | VPN/Monitor/Social 全部表，可从零建库（⚠️ 需 baseline 策略） |
| `ConnectionGrant` 表 + 模型 | spec §1.1 |
| `KeyExchange` 表 + 模型（`algorithm`/`key_version`/`status`） | spec §1.1 |
| `ConnectionDomainService` | request/approve/revoke/updateGrant/revokeGrant |
| `KeyExchangeDomainService` | registerPublicKey/rotateKey/revokeKey/getPeerKeyPair |
| `TransportPlannerDomainService` | plan/planAll |
| X25519 DH + HMAC 的节点面鉴权中间件 | 替代现 HMAC（公钥当密钥） |

### 4.3 `mynet_web`（新建合并）—— **本节已作废（2026-09-24）**

> ⚠️ D-4 作废：`mynet_web` 只是**官网/项目主页**，`mynet_gui` 才是用户交互前端，**两仓不合并**。
> `mynet_gui` 已按「挂载，不动结构」纳入 workspace。origin 原指 `luoyueliang/mynet_gui`，
> **2026-09-24 该仓已 transfer 到 `mynet-club/mynet_gui`**，本地 remote 已同步
> （`git remote set-url`）。至此 workspace 9 个仓全部在 org 下。
> ⚠️ 但 `mynet_gnb` 的上游 fork `luoyueliang/opengnb` **没有一起迁** —— 实测
> org 下 not found、旧地址仍有 `main` / `dev-2026-01`，所以
> `mynet_ctl/scripts/import_gnb_*.sh` 的 `GH_BASE` 仍指个人仓，别顺手改。
> 下表仅保留作历史记录——实际只执行了 `mynet_ui/src/views/*` → `mynet_web/src/pages/local/` 这一行，
> `mynet_gui` 的 102 个页面一页未搬，也**不需要**搬。T2-6 一律在 `mynet_gui` 上做。

**搬**（历史计划，勿再执行）：

| 旧位置 | 新位置 |
|---|---|
| `mynet_gui/` 的 Quasar 骨架（`quasar.config.ts`、路由、Pinia） | 基线 |
| `mynet_gui/src/pages/{nodes,routes,services,social,zone,ippool,auth}` | `src/pages/social/`（社区域） |
| `mynet_ui/src/views/{Service,Plugins,Logs,Network,Settings,Dashboard}` | `src/pages/local/`（本机域） |

**不搬**：`mynet_ui` 的 Vite+Tailwind 骨架（只取页面）、`mynet_gui/dist/spa`（构建产物）。
**新建**：`echarts` 按需引入配置（现在 `ConnectionDetailsPage` 单 chunk 1MB）。

### 4.4 其他

- **`mynet_openwrt`（改造）**：`rc.mynet` 从 `rc.common` 改 procd；`node.lua` 的 `start_gnb`/`stop_gnb` 改委托 procd；`system.lua` 的 `_handle_heartbeat_commands`/`_report_command_result` 收窄为纯执行器；`_gnb_peer_heal` 判据改造（原依赖已下架的 `mynet_proxy`）后搬进 mynetd。
- **`mynet_desktop`（新建）**：从零写，参考 `mynet_openwrt` 的职责边界。
- **`mynet_ctl` / `mynet_gnb` / `mynet_utc`**：只挂载，不改。`mynet_ctl` 的 `docs/knowledge-base/` 是文档中枢，`conventions.md` 的产物前缀要改成 `mynet_agent_*`。

---

## 5. 迁移顺序（引用 `dev-plan.md` 的计划顺序表）

**跟踪入口是 [`dev-plan.md` §3 计划顺序表](dev-plan.md)**（64 项可勾选）——
本节只标阶段与仓库落点，不重复编号。

| 阶段 | 仓库落点 | 对应任务 ID（`dev-plan.md` §3） | 出口 |
|---|---|---|---|
| **S0 立骨架** | 本仓 + 8 个 repo | S0-1 · S0-2 · S0-3 | 目录骨架齐 |
| **S1 地基** | `mynet_agent/shared/`、`mynet_api` | P0-1 … P0-6 · S1-1 · S1-2 | **M0**：两套测试全绿、无明文密码、无私钥出口 |
| **S2 执行器 mynet** | `mynet_agent/mynet/` | F-1 · F-6 · S2-1 · F-2 · F-4 · F-5 · D-7 | `mynet detector` / `metrics` / `apply` 可用 |
| **S3 纯通信 mynetd** | `mynet_agent/mynetd/` | D-11 · D-6 · D-1 · D-2 · D-8 · S3-1 | mynetd = 纯通信层 |
| **S4 gateway** | `mynet_agent/gateway/` | C-3 … C-8 · C-7 · C-6 | gateway 零密码学 + 用户面入口 |
| **S5 back 业务层** | `mynet_api/app/Modules/` | B-1 … B-9 | create migration + 三个 DomainService |
| **S6 驱动** | `mynet_agent/mynet/driver/` | A-1 … A-5 · A-7 · C-1 · C-2 | 双驱动可注册、DH 可用 |
| **S7 验证** | 跨仓 | F-3 · A-8 | **M1**：三场景全绿 |
| **S8 mynet_web** | `mynet_web/` | D-3 … D-10 | 一份 UI 两个功能域 |
| **S9 平台** | `mynet_openwrt/` `mynet_desktop/` | E-1 … E-10 | **M2–M4** |

**依赖要点**（详见顺序表的「前置」列）：
- `S1 → S2 → S3` 顺序；`S4` 可与 S2/S3 并行；`S5` 依赖 S1；`S6` 依赖 S2；`S7` 汇合 A+B+C+F；`S8` 依赖 S3；`S9` 依赖 S3。
- **硬门槛**：S3 完成后 mynetd 就是「纯通信层」——执行器架构的验证点。

## 6. 与 `dev-plan.md` 的关系

| 文档 | 回答的问题 |
|---|---|
| **本文件**（`repo-migration.md`） | **仓库怎么建、代码怎么搬**（阶段 0–8） |
| `dev-plan.md` | **建完之后做什么**（P0 + 轨道 A–F，58 任务、验收标准） |
| `mynet-overview.md` | **为什么这么设计**（九条主线、L1–L7、决策记录） |

两者的任务映射：
- 本文件阶段 1 ≈ `dev-plan` 的 P0（测试与安全地基）
- 本文件阶段 2 ≈ 轨道 F（系统能力）
- 本文件阶段 3 ≈ 轨道 D 的一部分
- 本文件阶段 4 ≈ 轨道 C
- 本文件阶段 5 ≈ 轨道 B
- 本文件阶段 6 ≈ 轨道 A
- 本文件阶段 7 ≈ 轨道 D
- 本文件阶段 8 ≈ 轨道 E

> **重建改变了 `dev-plan` 的若干任务落点**（例如 P0-1 从「修签名」改为「删残留」、
> A-3 的实现归 `mynet`、F-1/F-2 归 `mynet detector`/`mynet apply`）——
> 这些已在 `dev-plan.md` 中同步。
