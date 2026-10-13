# A2 轨审计报告 — mynet_api 服务层与杂项（2026-10-13，只读审计）

> 范围：app/Services、app/domain、app/database、app/Models、app/Providers、app/Exceptions、app/Jobs、app/View、app/helpers.php、src/**、config/**、database/**、bootstrap/**、resources/**、public/**、lang/**、storage（仅入库垃圾）。
> routes/、app/Http/、app/Modules/ 仅作引用（A1 轨）；tests/、*.md 不审（A3 轨）。
> 判零调用前均过全仓（含 tests、Blade、ts/vue、composer files autoload、字符串调用）静态 grep；ripgrep 为主、shell grep 复核（BSD grep 曾出现一次 alternation 不确定输出，已用 ripgrep 重验结论）。

## 发现清单

### S2 — 违反设计

1. **[不必要 fallback|S2] `MailTemplateService` 垫片推翻框架的 fail-loud 门控 → 模板邮件静默发空正文** — 证据：`app/Services/stubs/MailTemplateService.php:11-14`（`render()` 恒返回 `''`，经 `composer.json:57` files autoload 常驻加载）；框架判定 `OptionalModule::has()` 就是 `class_exists()`（`vendor/dsplat/multi-tenant-saas/src/Support/OptionalModule.php:105-110`），于是本垫片让 `has(MailTemplateService::class)` 恒真、`ensure()` 不再抛；而框架设计注释明说「没有模板就发不出任何正文……静默降级等于静默发空邮件，比报错更坏。故模板发信路径抛 ModuleNotInstalledException」（`vendor/dsplat/multi-tenant-saas/src/Mail/TenantMail.php:100-115`，`TenantInvitationMail.php:33-34` 同族）→ 实际效果恰是设计要防的「静默空邮件」。垫片自述理由「infrastructure MailerService 仍 type-hint 本类」已过期：`MailerService.php:33` 注释为「**曾经**注入的 MailTemplateService」，现无 type-hint（全 vendor grep 仅注释/改名表命中）。— 影响：邮件模板路径 501 明错被换成空正文静默降级。— 建议：删除该垫片与 `composer.json:57` 声明，恢复框架 `ensure()` 语义（发信路径会以 501 + 明确消息报缺模块）。

### S3 — 错漏

2. **[错漏|S3] `config/mynet.php` 缺 `gnb_crypto_bin` 键，调用点用硬编码兜底掩盖** — 证据：读取点 `app/Modules/VPN/Domain/Services/NodeKeyDomainService.php:526` `config('mynet.gnb_crypto_bin', 'gnb_crypto')`（引用 A1 文件），而 `config/mynet.php` 全文 52 行无此键 → 兜底字面量恒生效，env/配置文件均无法覆盖该二进制路径；`tests/Unit/Domain/GnbKeyCliFallbackTest.php:22` 还在 `config(['mynet.gnb_crypto_bin' => …])` 注入，只在测试内存仓库成立，给「可配置」错觉。— 影响：运维/部署无法换密钥 CLI 路径，配置缺失被兜底吞掉（项目裁定：兜底只当存量兼容层，新代码应根治）。— 建议：在 config/mynet.php 补 `'gnb_crypto_bin' => env('MYNET_GNB_CRYPTO_BIN', 'gnb_crypto')` 并进 .env.example；或删除可配置假象。

3. **[不必要 fallback|S3] `config/cors.php` 空值 env → 全开 `['*']`** — 证据：`config/cors.php:6` `env('CORS_ALLOWED_ORIGINS', 'localhost,127.0.0.1') ? explode(...) : ['*']`：显式设 `CORS_ALLOWED_ORIGINS=""` 时条件为假 → `['*']` 通配，静默放开所有来源。与已知账 P1-3 / CPI-27 同源（那两条已记默认值缺 scheme 的问题），此「空串兜底成通配」角度为**新账**。— 影响：一个空赋值把 CORS 从（坏的）白名单变成全开，且无任何日志。— 建议：空值回退默认白名单或直接 fail，不落 `['*']`。

4. **[垃圾+误导|S3] `app/Exceptions/Handler.php` 整类零绑定死代码，与 `bootstrap/app.php` 双轨且语义不一致** — 证据：Laravel 11 入口 `withExceptions()` 把 `ExceptionHandler::class` 绑到框架实现（`bootstrap/app.php:97-137`；绑定本身见 `vendor/laravel/framework/.../ApplicationBuilder.php:397-398`），全仓（含 tests）无任何 `App\Exceptions\Handler` 的绑定/实例化/引用（ripgrep 复核）。其 :37-54 大段注释描述的「reportable 空回调截断日志链已修复」实际**不在生效路径上**；渲染语义还与 bootstrap 分叉（Handler `renderRuntimeException` 返 422，`:238-248` vs bootstrap 对非 HTTP 异常生产返 500，`bootstrap/app.php:125-136`）。— 影响：维护者误以为自定义 Handler 生效（含其日志修复），实际一切走 bootstrap；约 320 行死代码 + 双份异常渲染逻辑。— 建议：删除该类，或显式绑定并收敛到单一渲染口径（二选一，删前确认无人依赖其 422 语义）。

### S4 — 冗余 / 垃圾 / 无人调用

5. **[垃圾|S4] 空目录 4 处** — 证据：`find … -type d -empty` 实测输出 `app/database`、`app/domain/placeholder`、`database/factories`、`lang`（另 A1 轨的 `app/Http/Resources`、`app/Modules/Monitor/Domain/{Exceptions,ValueObjects}` 不在本轨）。`app/database`、`app/domain/placeholder`、`lang` 均无任何文件/引用（lang 目录 git 也不跟踪，框架语言包走 vendor 自带路径 `TenancyServiceProvider::boot` addPath）。— 建议：删 `app/database`、`app/domain/placeholder`；`database/factories`、`lang` 留作 Laravel 骨架可接受，但可随大清理一并去掉（composer autoload 仍声明 `Database\Factories\`）。

6. **[垃圾+错漏|S4] `app/domain/` 整层是死占位，且命名空间与目录大小写不匹配（Linux 上 PSR-4 无法解析）** — 证据：`app/domain/Services/UserDomainService.php:5-11` 自述「占位：身份生命周期归 auth 模块」，`updateUser()` 原样回传 `$data`（假实现）；全仓零调用（ripgrep `UserDomainService` 仅命中自身）。composer psr-4 `"App\\": "app/"`（composer.json autoload）⇒ 类 `App\Domain\Services\…` 应落 `app/Domain/…`，磁盘却是小写 `app/domain` —— macOS（APFS 不区分大小写）能载、Linux 生产 PSR-4 查找不到（类一旦被调用即 Error）。— 影响：死代码 + 潜在跨平台炸点；`App\Domain` 与模块内 `App\Modules\*\Domain` 两套「domain」并存易混淆。— 建议：整目录删除（零调用）。

7. **[垃圾|S4] `app/Services/User/UserService.php` 整类零调用，`allocateId` 是伪 ID 假实现且带死变量** — 证据：`allocateId()`（`:14-29`）new 的匿名 Model（`:16-25`）从未使用；`:28` 返回「时间片伪 ID」注释自认「简化：真实场景走 IdGeneratorContract」，与类头「P0-6：统一 HasGlobalId，无自增」相悖；`findById()`（`:31-34`）零调用。全仓 grep `allocateId|findById(` 仅命中定义。注入点 `app/Modules/VPN/Controllers/Api/V1/IDController.php:5,16-21`（A1 引用）注入了该服务但**从未调用其任何方法**（控制器自造 ID 用私有 `generateIdByType`，`:99-118`）。— 影响：ID 生成存在两套口径（伪 ID vs HasGlobalId 16 位 vs IDController 时间戳+随机串）的错觉面；死代码。— 建议：删除本类，并请 A1 轨去掉 IDController 的无用注入（另 IDController 的 TODO 假实现属 A1 轨）。

8. **[垃圾|S4] `app/Services/UserService.php::listUsers()` 零调用** — 证据：`app/Services/UserService.php:17-25`；全仓 grep `listUsers` 在该文件外零命中（同类其余方法 getUserList/getUserStatistics/getUserDetail/getOnboardingState/saveOnboardingState/updateUser 均有调用点，已逐个复核）。— 建议：删方法。

9. **[垃圾|S4] `app/Services/stubs/AuditService.php` 是多余垫片（其自述理由不成立）** — 证据：类注释称「infrastructure 的部分控制器仍 type-hint 本类」，但全 vendor（含 dsplat 四包）grep `Logging\Services\AuditService` 仅命中：改名脚本表 `update_namespaces.php:111`、两处**注释**（`TenancyServiceProvider.php:139`、`IpWhitelistService.php:267`），无任何 type-hint / `app()` 解析；框架审计写入绑的是自带 `NullAuditLogger`（`vendor/dsplat/multi-tenant-saas/src/TenancyServiceProvider.php:138-141`）。本仓也零引用。且该文件**无 class_exists 守卫**（对比 `ToolDtoPlaceholder.php:14` 有），将来真装 module-logging 会「cannot redeclare class」。经 `composer.json:56` files 常驻加载。— 建议：删除该垫片与 `composer.json:56` 声明。

10. **[冗余|S4] `config/id.php` 与框架副本逐字节相同（纯冗余覆盖），且 10 个键里 9 个全仓零读取（AI SaaS 历史包袱）** — 证据：`diff vendor/dsplat/multi-tenant-saas/config/id.php config/id.php` 输出 IDENTICAL；框架 `TenancyServiceProvider.php:93` 本就 `mergeConfigFrom` 自己的 id.php。全仓（app+vendor+tests）读取仅 `id.platform_tenant_id`（`SuperAdminSeeder.php:26`、`PlatformInitCommand.php:35`、`AuthorizesTenantAccess.php:51`、`database/seeders/ConsoleDevSeeder.php:31`）；零读取键：`id_length`、`js_max_safe_integer`、`min_value`、`max_value`、`platform_welcome_credits`、`guest_user_id`、`guest_invoke_max_requests`、`guest_conversation_max_requests`、`guest_rate_limit_per_ip`、`guest_max_sessions_per_ip`（guest 会话/对话限流、欢迎积分——旧 AI 产品语义，P0-1 裁定不搬的那类业务）。— 建议：删除 app 侧 config/id.php（框架副本仍在，`platform_tenant_id` 语义不变）；若担心将来覆盖，至少删 9 个死键。

11. **[冗余|S4] `config/channel.php` 与框架副本逐字节相同，且 `config('channel.*')` 全仓零读取** — 证据：diff IDENTICAL；框架 `TenancyServiceProvider.php:90` 自行 mergeConfigFrom channel.php。读取面复核：全 vendor + app grep `config(['\"]channel\.` 零命中（含 Laravel core——core 不读该键）。内容是企业微信/微信公众号/小程序/Slack 渠道凭据位（`config/channel.php:5-33`，含 `SLACK_BOT_TOKEN`、`WECHAT_*_SECRET` 等 env 面）——被裁 channel 模块遗物。— 建议：删除 app 侧 config/channel.php（连带 .env.example 的渠道 env 注释亦可收，见第 17 条）。

12. **[垃圾|S4] `config/logging.php` 的 `admin` 日志通道零使用（连带 ADMIN_LOG_* 两个 env 键）** — 证据：通道定义 `config/logging.php:158-164`（`ADMIN_LOG_LEVEL`/`ADMIN_LOG_DAYS`）；全仓（app+vendor）grep `channel('admin')` 零命中，`admin.log` 无消费者。— 建议：删通道与两 env 键，或给 console 运营面真接上。

13. **[垃圾|S4] `config/mynet.php` 的 `tenant_isolation_enabled`（`MYNET_TENANT_ISOLATION`）零读取** — 证据：定义 `config/mynet.php:17`；全仓 grep `tenant_isolation` 仅此一处。— 影响：文档化「租户隔离开关」实际不存在——隔离由框架 TenantScope 承担，这个开关给人可关隔离的错觉。— 建议：删除。

14. **[垃圾|S4] 编译产物入库：`bootstrap/cache/packages.php`、`services.php` 被 git 跟踪，`bootstrap/cache/.gitignore` 0 字节** — 证据：`git ls-files bootstrap/` 含两文件；`wc -c bootstrap/cache/.gitignore` = 0（标准 Laravel 应为 `*` + `!.gitignore`）。两文件是 `package:discover` 的机器本地产物（services.php 329 行、packages.php 98 行），入库会随本机 composer 状态漂移。— 建议：.gitignore 恢复标准内容并 `git rm --cached` 两文件（属 git 写操作，本轮未动）。

15. **[垃圾|S4] `app/Exceptions/Api/ApiExceptionHandler` 6 个静态方法里 5 个零调用** — 证据：仅 `handleNodeException` 被用（`app/Modules/VPN/Controllers/Api/V1/NodeController.php:851,895,937,961,1039`）；零调用：`handleDomainException`（`:47`）、`businessError`（`:75`）、`resourceNotFound`（`:94`）、`accessDenied`（`:108`）、`validationFailed`（`:120`）。另注（移交 A1）：异常处理器 `extends BaseApiController`（`:21`）属怪异分层。— 建议：删 5 个死方法。

16. **[冗余|S4] `GatewayNotifier::routeChanged()` / `broadcast()` 生产零调用（仅测试覆盖）** — 证据：`app/Services/Gateway/GatewayNotifier.php:73-84`；全仓调用点仅 `tests/Unit/Domain/GatewayNotifyDeliveryTest.php:76,102`。`commandQueued`（`MonitorRtCmdObserver.php:29`）与 `bundleChanged`（`app/Jobs/NotifyGatewayBundleChanged.php:35`）有真实调用。事件模型三件套 `route_changed` 目前无人发。— 建议：若路由拓扑变更链尚未接线，先留但补 TODO 出处；否则删（连同 `EVENT_ROUTE_CHANGED` 的对外承诺收窄）。

17. **[垃圾|S4] `app/helpers.php` 的 `current_tenant_id()` / `with_tenant_id()` 全仓零调用，且带危险默认** — 证据：`app/helpers.php:3-19`；全仓 grep `current_tenant_id(\|with_tenant_id(` 除 helpers.php 自身外零命中（`ResolveTenantFromUser.php:19-21` 的命中是 User 属性名注释，非本函数）。`current_tenant_id()` 兜底链 `app('current.tenant_id') ?? env('DEFAULT_TENANT_ID', 1)` 双重问题：上下文缺失时**静默落租户 1**（跨租户错写隐患，一旦被新代码调用）+ 直接 `env()` 在 config:cache 下恒 null。— 建议：删两个死 helper（`term_zone()` 在用，保留）。

### S5 — 包袱 / 文档 / 漂移

18. **[包袱|S5] `config/tenancy.php` 发布副本已过期漂移（diff 109 行），且硬覆盖 `default_tenant_id => null` 使 `TENANCY_DEFAULT_TENANT_ID` env 失效** — 证据：`diff vendor/dsplat/multi-tenant-saas/config/tenancy.php config/tenancy.php` 109 行（vendor 侧新增 `log_module_bootstrap`、`log_module_dependency_gate`、MySQL 专有迁移跳过开关等，app 副本没有；`config/tenancy.php:4` 把 `env('TENANCY_DEFAULT_TENANT_ID')` 改写为 `null`）。该键是框架的「识别不出租户时的默认租户」回退（`IdentifyTenant.php:148`、`TenantContext.php:59`、`HealthCheckService.php:241`、`ChannelWebhookController.php:160`）。fail-closed 方向大概率是有意的，但**无注释**，且整个副本随 vendor 版本漂。— 建议：把有意覆盖（default_tenant_id）留注释说明，其余段落随框架版本重新发布或整文件删掉只留覆盖项（mergeConfigFrom 会补默认）。

19. **[漂移|S5] `.env.example` 与 config/ 的 env 面不同步** — 证据：config/ 读取而 .env.example 完全没有的 MyNet 自有键：`MYNET_USER_IP_POOL_QUOTA`（config/mynet.php:11）、`MYNET_IPS_PER_POOL`（:14）、`MYNET_TENANT_ISOLATION`（:17，建议随第 13 条删）、`MYNET_TERM_ZONE`（:28）、`MYNET_GNB_INDEX_SERVERS`（config/vpn.php:6）、`MYNET_GATEWAY_NOTIFY_ATTEMPTS`（config/gateway.php:33，同文件其余 gateway 键都有示例行）、`MYNET_BACK_FACE_*` 5 键（config/mynet.php:44-48，H7 裁定文件化、不进 env 是有意的，但 .env.example 无一行指引注释指向 0600 文件方案）。— 建议：.env.example 补示例行/注释（back_face 用注释指路即可）。

20. **[包袱|S5] `ConsoleDevSeeder` 直接 `env()` 取值（config:cache 下静默跳过建号）** — 证据：`database/seeders/ConsoleDevSeeder.php:53-54` `env('CONSOLE_DEV_OPERATOR_EMAIL'/_PASSWORD')`，Laravel 规范是经 config 转 env；config 缓存后 env() 恒 null → `:56-58` warn 后 return，开发者只看到「跳过」。— 建议：挪进 config（如 config/mynet.php dev 段）或注释声明「本 seeder 禁与 config:cache 同用」。

21. **[包袱|S5] 两个迁移共用时间戳前缀 `2026_09_25_000001`** — 证据：`database/migrations/2026_09_25_000001_add_mynet_columns_to_framework_tables.php` 与 `…_000001_add_refresh_token_to_user_sessions.php`。排序靠字母序兜住（`add_m` < `add_r`），无实际乱序风险，但回滚/对照 DBA SQL 时易错位。— 建议：以后新迁移改唯一前缀；存量不动。

22. **[垃圾|S5] `public/console/assets` 残留双份 hash 的过期构建 chunk（本地、未入库）** — 证据：同名双 chunk 例 `ApplyTeam-CAdB7uWW.js`/`ApplyTeam-DInwYbY.js`、`Webhooks-*` 4 份、`Login-*` 4 份等（ls 实测约 50 文件，其中约半是旧 hash 孤儿）；`public/console/` 已被 `.gitignore:37` 忽略，`git ls-files public/` 仅 `index.php`，**不构成入库垃圾**，但本地开发机目录会误导排查（旧 chunk 可能仍被旧 index.html 引用）。— 建议：本地 `rm -rf public/console && npm run build` 一次即可；无需改仓。

### 无发现 / 核过合规（重要否定结论）

- **安全红线（Models 层私钥暴露面）**：`NodeKey` 已合规——`private_key` 不进 `$fillable`、`$hidden = ['private_key']`（`app/Modules/VPN/Models/NodeKey.php:33-36`，类注释记录 P0-4 收口裁定）；其余敏感邻近模型 `KeyExchange`、`ProxyExitGrant` 的 `$fillable` 仅公钥/授权字段（`KeyExchange.php:31-33`、`ProxyExitGrant.php:30-33`）。`app/Models/{User,Tenant,TenantUser}` 是框架模型薄别名（无新增可批量赋值字段）；`User.php:30-44` 的 tenant_id 回退 accessor 注释与判据完整（显式属性优先、回退与 ResolveTenantFromUser 同判据）。**红线其余两个出口（API 响应序列化、日志）在 app/Modules（A1 轨）**，本轨只核了 Models 层。
- **`src/` 反常之疑（src/Modules 与 app/Modules 是否双份）**：不是垃圾、无双份——`src/Modules/VPN/resources/console/` 是 console SPA 的**模块前端**，由框架 module-loader 的 glob 第一来源 `/src/Modules/*/resources/console/…` 自动发现（`vendor/dsplat/multi-tenant-saas/resources/js/console/module-loader.ts:28-88`）；放 src/ 而非 app/Modules 是因 app/Modules/VPN 已有大写 `Resources/`（Laravel API Resources）而 glob 字面量是小写 `resources`，APFS 不容两拼法（`resources/js/console/vite.config.ts:4-8`、`src/Modules/VPN/resources/console/routes.ts:10-20` 注释记载）。8 个 .vue 视图 ↔ routes.ts 8 条路由一一对应，无死视图；5 个 ts 帮助文件均进构建（public chunk 名可证 api/format/nodeStatus/routes/tenantApi）。
- **`resources/js` 三个薄转发**（module-loader.ts / stores/user.ts / console/main.ts）与 `resources/js/console/stubs/AiAssistant.vue`：均是有据的框架占位/转发（各自文件头注释），非重复维护，保留。
- **`app/View/Components/Admin/DataTable` + `resources/views/components/admin/data-table.blade.php`**：在用——框架 vendor 视图引用 `<x-admin.data-table>`（`vendor/dsplat/multi-tenant-saas-module-{infrastructure,auth}/resources/views/admin/index.blade.php:19`），本组件补其缺失实现（组件注释所载），保留。
- **database/sql/ 与 migrations 双轨**：是约定而非重复——「生产禁 migration、DBA 走 database/sql/」（`database/sql/2026_10_05_sync_invite_code_infrastructure.sql` 头注、deployment-registry §5）；`device_fingerprint` 列由框架迁移 `2025_01_01_000004_auth_module.php` 建（trusted_devices），SQL 文件是生产侧等价 DDL，不算漂移。
- **`2025_01_01_000010_create_subscription_plans_table`**：不是包袱——是框架悬空外键/种子的本地兜底，注释完整、已记 `upstream-dsplat-issues.md` 缺陷 3（已知账）。
- **NullToolRegistry 一族完整清单与引用面**（SSOT §1.6 裁剪连带名单）：`app/Services/ToolRegistry.php`、`tests/Stubs/FakeToolRegistry.php`、`tests/ActionNodeTest.php`、`tests/Conversation/` **均已删除**（ls 实测不存在）。现存同族：
  - `app/Services/NullToolRegistry.php` — **保留**：框架 boot 解析 `ToolRegistryContract` 的空实现，唯一绑定点 `app/Providers/AppServiceProvider.php:24`；
  - `app/Services/stubs/ToolDtoPlaceholder.php` — **保留**：契约返回类型指向被裁 Ai 模块的 `Tool` DTO，有 class_exists 守卫（`:14`）；
  - `app/Services/stubs/AuditService.php` — **应删**（发现 9）；
  - `app/Services/stubs/MailTemplateService.php` — **应删**（发现 1，有害）。
- **口径修正核查**：mynet_api 无 gnb route.conf 相关物（gnb 目录在 agent 侧）；config/ 内 grep `mynet_back` 零命中（`.env.example` 的 `DB_DATABASE=mynet_back` 为合法库名，不动）。
- **B4（驱动术语渗业务对象）**：本轨范围内（Models/Services/Jobs/Providers/View/helpers）grep `gnb|wireguard|wg_` 零命中（gateway 为组件名除外）；config/vpn.php 是驱动参数声明处（设计如此，不算业务对象）。
- **storage 入库垃圾**：无——`git ls-files storage/` 仅各级 `.gitignore`（CPI-33 修复后形态），本地 cache/data 运行时文件未入库。

## 已知账命中

| 已知账 | 本轮核对 |
|---|---|
| P1-3 / CPI-27（CORS 来源） | 复核属实并**补充新角度**：空串 env → `['*']` 通配（发现 3）；默认值缺 scheme 问题依旧（`config/cors.php:6`） |
| CPI-42（gateway.enabled 无文档总闸默认 false） | 已修文档：`.env.example:12-20` 现有大段警示注释；代码行为依旧（`config/gateway.php:20`、`GatewayNotifier.php:93`），网关部署后需显式开 |
| CPI-40（迁移撞表） | 修复在位：`2026_09_25_000001_add_mynet_columns_to_framework_tables` 为 hasColumn 守卫补丁（已知 ✅）；未见新增撞车迁移 |
| CPI-33（storage 未跟踪即坏） | 修复在位（storage 各级 .gitignore 已入库） |
| upstream 缺陷 3（subscription_plans 悬空外键） | 对应迁移注释与 upstream 台账一致，属已登记兜底 |
| SSOT §1.6 裁剪连带删除名单 | 主名单已删；**新增残留账**：stubs/AuditService（死）、stubs/MailTemplateService（有害）、及 NullToolRegistry/ToolDtoPlaceholder 两个应留垫片（发现 1、9 与否定结论节） |
| workspace-review R1–R12 | 均属 agent/OpenWrt/desktop/ctl/GNB/CI 面，本轨无重叠项 |

## 疑点待验证

1. **`config/database.php` 的自定义 `pool.*` 键疑似无效**（`:63-68` `pool.enabled/max/idle/timeout` ← `DB_POOL_*`）——Laravel 的 MySQL 连接器不读 `pool` 段（该形状是部分扩展/框架私有约定），本轮未逐扩展验证是否有人消费；`DB_TIMEOUT`/`DB_PERSISTENT` 挂在 `options`（PDO_ATTR）倒是有效。请 DBA/部署面确认 `DB_POOL_MAX` 是否实际起作用。
2. **`config/services.php` 的 slack.notifications 段与 `config/channel.php` 的 slack 段重叠**（两组不同 env：`SLACK_BOT_USER_OAUTH_TOKEN` vs `SLACK_BOT_TOKEN`）——channel 段已判死；services.slack 是否被通知链读取未穷尽验证（框架 SocialiteService 只按 `services.{$provider}` 取 socialite provider）。
3. **vendor/dsplat 是否被本地改动（与 composer.lock 声明不符）未做逐字节校验**——本轮只做静态阅读；dsplat 四包为源码安装，无 dist hash 可比对。若需 S2 结论，请跑 `composer install` 干净校验或与上游 tarball 对比。
4. **tests/Schema 与生产 SQL 的 `device_fingerprint` 长度不一致**：`tests/Schema/CoreModule.php:118`（64）与 `:177`（128）两处不同，`database/sql/2026_09_27_*.sql` 为 VARCHAR(64)——测试 schema 自造不归本轨（A3），但长度口径值得顺手核对。
5. **ID 生成三套口径**（`App\Services\User\UserService::allocateId` 伪 ID、`IDController::generateIdByType` 时间戳+随机、`HasGlobalId` 16 位全局 ID）——前两者本轮判死/移交 A1，若 A1 保留 IDController，需裁定对外 ID 格式承诺。
6. **`config/mynet.php:47-48` 的 `private_key_hex`/`public_key_hex` env 兼容回退**（H7 裁定「迁移窗口过后应删除」）——回退何时关账无日期记录，建议在 project-memory 定删除时点。

## 覆盖说明

- **已审**：app/Services/**（5 文件+stubs 3）、app/domain/**（1 文件+placeholder 空目录）、app/database/（空）、app/Models/**（3）、app/Providers/**（3）、app/Exceptions/**（2）、app/Jobs/**（1）、app/View/**（1）+ resources/views/components/admin/data-table.blade.php、app/helpers.php、src/**（13 前端文件）、config/**（17 文件，逐文件查了 config('…') 读者面，含 vendor/dsplat 与 laravel core 交叉）、database/**（17 迁移+1 seeder+8 SQL，重点核重复/无用/漂移）、bootstrap/**（app.php + cache 入库物）、resources/**（console 构建配置与转发件）、public/**（git 跟踪面 + console 构建残留）、lang/（空）、storage（仅 git 跟踪面）。
- **越界只读引用**：app/Modules/VPN（Models 私钥红线、NodeKeyDomainService:526、NodeController、IDController）、app/Http/Middleware（ResolveTenantFromUser）、routes/ 未审内容、vendor/dsplat（契约与门控语义取证）、tests/（仅作零调用反证与红线复核，不审质量——A3）。
- **不审**：routes/、app/Http/、app/Modules/（A1）；tests/ 与 *.md（A3）；deploy/、manage-prod.sh、Makefile、composer.json/lock 本体（A3 任务 #9 已领）；vendor 修改性校验（见疑点 3）。
- **方法边界**：全部为静态检查 + 只读命令（find/diff/git ls-files/wc/grep）；零调用结论基于静态 grep（含字符串调用、Blade 组件名、config 键、composer files 列表、前端 import/glob），动态字符串调用（变量类名/方法名拼接）无法完全排除，各条已尽量列明复核过的引用面。未运行测试套件、未连数据库、未改任何仓库文件。
