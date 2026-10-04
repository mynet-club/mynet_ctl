# dsplat/multi-tenant-saas 上游缺陷反馈

> 用途：交给框架维护者（`github.com/dsplat/*`）的缺陷报告 + 可直接粘贴的提示词。
> 依据 `AGENTS.md` §1.8——`vendor/dsplat/**` 只读，缺陷一律回上游修，这边 `composer update` 拉回来。
>
> 环境：`mynet_api` · PHP 8.5.3 · Laravel ^13.0
> 装了的框架包（2026-10-05 按 `ls vendor/dsplat` + `composer.json:31-34` 核对，共 4 个）：
> `multi-tenant-saas`（`dev-main as 2.7.0`）· `multi-tenant-saas-module-auth`（`dev-main`）·
> `multi-tenant-saas-module-infrastructure`（`dev-main`）· `multi-tenant-saas-module-operator`
> （`dev-main as 1.99.0`，2026-10-01 为 console 加装，见 AGENTS.md §1.6 裁定例外）
> 证据均为 `file:line`，可逐条复核。
>
> **本文件在 2026-10-05 补入 `dev-plan.md` 表 F-7…F-21 这批的复核结果**，编号接续既有的
> 「缺陷 6」，从 **缺陷 7** 起到 **缺陷 18**（共 12 条框架侧缺陷）；`dev-plan` G1 清单的**第 13 条**
> 不按新缺陷提，写成文末的「对齐请求 1 —— 属主判定（Owner Guard）方法签名」——框架侧已在实现
> 该判据，这条只剩**签名对齐 + back 侧接线计划**。「本批 12 条 + 1 条对齐请求 = 13 条」，
> 与 `dev-plan.md:528`（G1 行）的口径一一对上。
> 同一批里既有各条（缺陷 1/2/3/5/6）的**状态行**是本批复核的产物：判据已不成立的写了原因，
> 正文一字未删。

---

## 缺陷 1 —— `auth` 模块硬引用未声明的 `notification` / `billing` 模块

> **状态（2026-10-05）**：⚠️ 本条判据**已不成立** —— 上游按下面「建议修法 2」的方向修了，且用的是
> `OptionalModule` 守卫（`vendor/dsplat/multi-tenant-saas/src/Support/OptionalModule.php:10-40` 的
> 类注释把「必装包引用可选模块」这件事分级讲清楚了）。当前 `module-auth/Models/User.php:137-143` 的
> `notifications()` 是 `OptionalModule::has(CustomDatabaseNotification::class) ? Custom : DatabaseNotification`，
> 缺 notification 模块时**回落到 Laravel 原生类**；`:96-101` 的 `creditAccounts()` 走
> `OptionalModule::ensure()`，给的是可捕获的 `ModuleNotInstalledException`（501）而不是 `\Error`。
> **下面正文里的行号（18/20/120-123）已整体漂到 19/21/137-143，「一调即致命」这句不再成立**，
> 正文按要求一字未删，只作历史记录。
> 附带取证：框架把这条抽象成了架构红线 —— `src/Support/NullAuditLogger.php:13` 明文写
> 「这正是『必装包硬依赖可选模块』的典型形态（下游 mynet.club 缺陷报告 #1）」。

### 现象

任何调用 `User::notifications()` 关系的路径直接致命错误（不是异常，是 `Error`）：

```
GET /api/v1/notifications/unread-count  →  HTTP 500
Class "MultiTenantSaas\Modules\Notification\Models\DatabaseNotification" not found
```

### 根因

`multi-tenant-saas-module-auth/Models/User.php`：

| 行 | 内容 | 问题 |
|---|---|---|
| 18 | `use MultiTenantSaas\Modules\Billing\Models\CreditAccount;` | 引用 **billing** 模块 |
| 20 | `use MultiTenantSaas\Modules\Notification\Models\DatabaseNotification as CustomDatabaseNotification;` | 引用 **notification** 模块 |
| 120–123 | `public function notifications(): MorphMany { return $this->morphMany(CustomDatabaseNotification::class, 'notifiable')->latest(); }` | **真的去解析**该类，一调即致命 |

而 `multi-tenant-saas-module-auth/composer.json` 的 `require` 只有：

```json
{ "php": "^8.3", "dsplat/multi-tenant-saas": "*" }
```

**既没声明 `notification`，也没声明 `billing`。** 按 §1.6「31 个框架包只留 3~4 个」裁到
`multi-tenant-saas` + `infrastructure` + `auth` 之后，这两个类必然不存在。

PHP 的 `use` 是惰性的，所以第 18 行的 `CreditAccount` 只要不被真正引用就不炸；
但第 122 行是**主动解析**，只要有人调 `notifications()` / `unreadNotifications()` 就必炸。

### 影响面

`Notifiable` trait 的整个数据库通知面都不可用：`notifications()`、`unreadNotifications()`、
`readNotifications()`、`notify()` 走 database channel 时全部致命。
下游任何「未读数」「通知中心」功能都无法实现。

### 复现

```bash
# 装 multi-tenant-saas + module-infrastructure + module-auth（不装 notification/billing）
php artisan tinker --execute='
  $u = MultiTenantSaas\Modules\Auth\Models\User::first();
  echo $u->unreadNotifications()->count();
'
# → Class "MultiTenantSaas\Modules\Notification\Models\DatabaseNotification" not found
```

### 建议修法（任选，按侵入性从小到大）

1. **声明依赖**：`module-auth/composer.json` 的 `require` 加上
   `dsplat/multi-tenant-saas-module-notification`（若 billing 也真被用到，一并加）。
   —— 最小改动，但会把 notification 变成 auth 的强制依赖，裁剪自由度下降。
2. **可配置的通知模型**（推荐）：把第 122 行的硬编码类名换成配置项，例如
   ```php
   return $this->morphMany(
       config('multi-tenant-saas.notifications.model', \Illuminate\Notifications\DatabaseNotification::class),
       'notifiable'
   )->latest();
   ```
   默认回落到 Laravel 原生 `DatabaseNotification`，装了 notification 模块再覆盖成自定义类。
   这样 auth 模块**单独可用**，符合「模块可裁剪」的设计意图。
3. **拆关系**：把 `notifications()` 从 auth 的 `User` 移到 notification 模块自己的 trait 里，
   由装了该模块的应用 `use` 进来。

### 附带的下游坑（不是框架 bug，但值得写进文档）

`Class not found` 抛的是 `\Error`，**不被 `catch (\Exception)` 捕获**。
下游若按惯例写 `try { ... } catch (\Exception $e) { return 0; }` 做降级，
降级根本不会触发，而是直接 500。建议框架文档明确提示这一点。

---

## 缺陷 2 —— 主包 `AiStreaming` 模块的契约无人绑定，`route:list` 直接崩

> **状态（2026-10-05）**：⚠️ 本条判据**已不成立** —— `php artisan route:list` 实测 **rc=0、299 条路由**。
> 上游的修法不是「绑一个 Null 实现」，而是**把模块源码从 core 包里拆干净**：
> `vendor/dsplat/multi-tenant-saas/src/Modules/` 现在**只有 `Contracts` 一个目录**
> （`ls` 实测），`AiStreaming` 的四个控制器随之消失；全 vendor grep `AgentServiceContract` 只剩
> 两处 —— 契约定义 `src/Contracts/AgentServiceContract.php:14` 与
> 守卫脚本 `scripts/architecture_guard.py:1051` 的**注释**（后者正是本条的复盘：
> 「下游 mynet.club 因此在 core+auth+infra 的最小安装上撞上 route:list 崩溃……」）。
> 根因比原报告更深一层：core 包的 `.gitattributes` 与 `composer.json` 的 `archive.exclude` 长期不一致，
> GitHub zip 里躺着 20 个模块的源码 ⇒ 上游加了**检查 17「core 包不得夹带模块源码」**
> （`architecture_guard.py:1042-1060`）来防回归。
> 下面正文的行号（21/31/20/17/33）指向的文件已不存在，正文按要求保留为历史。

### 现象

```bash
$ php artisan route:list
BindingResolutionException
  Target [MultiTenantSaas\Contracts\AgentServiceContract] is not instantiable while building
  [Illuminate\Foundation\Console\RouteListCommand,
   MultiTenantSaas\Modules\AiStreaming\Http\Controllers\ResolveController].
```

`route:list` 会解析每个路由的控制器以取中间件，于是**任何**用了本框架的项目都跑不了
`route:list`（连带 `route:cache`、部分 IDE 插件、CI 里的路由审计）。

### 根因

`AgentServiceContract` 定义在 `multi-tenant-saas/src/Contracts/AgentServiceContract.php:14`，
被主包 `AiStreaming` 模块的 **4 个控制器**构造注入：

| 文件 | 行 |
|---|---|
| `src/Modules/AiStreaming/Http/Controllers/AiStreamingController.php` | 21 |
| `src/Modules/AiStreaming/Http/Controllers/ResolveController.php` | 31 |
| `src/Modules/AiStreaming/Http/Controllers/ToolExecuteController.php` | 20 |
| `src/Modules/AiStreaming/Http/Controllers/UsageReportController.php` | 17 |

而整个 `vendor/dsplat/` 里**没有任何一处 `bind` / `singleton` / `scoped` 绑定它**
（`AgentServiceContract` 全仓 9 处引用：1 处定义 + 8 处 use/类型标注，0 处绑定）。

`ResolveController.php:33` 还注入了 `ToolRegistry`，该类在 `vendor/dsplat/` 里**完全不存在**
——期望由应用侧提供。

即：主包（不可裁，人人都装）里塞了一个**必须依赖被裁模块 + 应用侧自备类**才能解析的模块。

### 建议修法

1. **模块自绑定 + 空实现兜底**：`AiStreaming` 的 ServiceProvider 里
   ```php
   $this->app->scoped(AgentServiceContract::class, fn () => new NullAgentService);
   ```
   装了 ai 模块再由它覆盖成真实实现。这样契约永远可解析，`route:list` 不再崩。
2. **把 AiStreaming 移出主包**，作为独立的 `multi-tenant-saas-module-ai-streaming`，
   并在其 `composer.json` 里声明对 ai 模块的依赖。
3. **惰性解析**：控制器改为构造注入 `Application`/工厂，用到时才 `app(AgentServiceContract::class)`。
   —— 能让 `route:list` 不崩，但把失败推迟到运行时，不如前两种。

`ToolRegistry` 同理：要么框架提供接口 + 空实现，要么在文档里明确它是应用侧必须自备的契约。

---

## 可直接粘贴的提示词

```
在 dsplat/multi-tenant-saas 框架里修两个「未声明的跨模块硬依赖」缺陷。

背景：下游项目按「31 个框架包只留 3~4 个」裁剪，只装
multi-tenant-saas + module-infrastructure + module-auth。

缺陷 1：module-auth/Models/User.php 第 18 行 use Billing\Models\CreditAccount、
第 20 行 use Notification\Models\DatabaseNotification，但 module-auth/composer.json
的 require 只有 php 和 multi-tenant-saas，两个模块都没声明。
第 120-123 行的 notifications() 关系主动解析 CustomDatabaseNotification::class，
一调即抛 Class not found（是 \Error，不被 catch (\Exception) 捕获）。
请改成可配置：morphMany(config('multi-tenant-saas.notifications.model',
\Illuminate\Notifications\DatabaseNotification::class), 'notifiable')，
默认回落 Laravel 原生类，让 auth 模块单独可用。

缺陷 2：主包 src/Modules/AiStreaming 的 4 个控制器
（AiStreamingController:21 / ResolveController:31 / ToolExecuteController:20 /
UsageReportController:17）构造注入 Contracts\AgentServiceContract，
但整个框架没有任何一处绑定它；ResolveController:33 还注入了框架里根本不存在的
ToolRegistry。后果是 php artisan route:list 直接抛 BindingResolutionException，
route:cache 与 CI 路由审计同样不可用。
请在 AiStreaming 的 ServiceProvider 里 scoped 绑定一个 NullAgentService 兜底，
或把 AiStreaming 拆成独立包并声明依赖。

约束：不要破坏已装模块的现有行为；两个修法都要能在「只装主包 + infrastructure + auth」
的最小组合下通过 php artisan route:list 与 User::unreadNotifications()->count()。
```

---

## 我方（mynet_api）对应的待办

上游修好之前，这两条在我方侧的表现与处置：

| 上游缺陷 | 我方表现 | 我方处置 |
|---|---|---|
| 1 notification | `/api/v1/notifications/unread-count` 500 | GUI 已优雅降级（控制台报错但页面正常）。**不在 vendor 打补丁** |
| 2 AiStreaming | `php artisan route:list` / `route:cache` 不可用 | 改用 `grep` 模块 `Routes/api.php` 查路由；**不在 vendor 打补丁** |

---

## 缺陷 3 —— `ModuleRegistry` 对**未启用**的模块也做依赖检查，每次请求刷一批 WARNING

> **状态（2026-10-05）**：两条都要记。
> ① **与下面「缺陷 5」是同一件事写了两遍**（同一条判据、同一个 CPI-39）⇒ 以 **缺陷 5** 为准，
>    本条保留取证，不再单独向上游提一次。
> ② ⚠️ 本条 2026-10-05 复核：**判据已不成立**，原因是上游已按下面「建议修法 1 + 3」同时落地 ——
>    `ModuleRegistry::validateDependencies()` 现在只遍历**已启用**清单
>    （`vendor/dsplat/multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:374`，
>    循环体 `:379` 的入参是 `$enabledNames`），报错文案从 `:317` 漂到 `:386`（「未安装」），
>    并新增 `:388` 的「未启用」分支；日志面 `Services/ModuleManager.php:200-211` 改成
>    **一切正常时一条 INFO（`:201`）／异常时一条汇总 WARNING（`:207-211`）**，
>    逐模块明细要显式开 `TENANCY_LOG_MODULE_DEPENDENCY_GATE=true` 才出。
>    本地日志计数佐证（`grep -c "但该模块未安装"`）：`09-23` 600 条、`09-24` 2448 条，
>    `09-25` 及以后（`09-28`/`10-01`/`10-04`/`laravel.log`）全部 **0 条**。
>    下面正文里的 `:317` 与「8 条/请求、一天 2224 条」已失效，按要求不删、只标注。

### 现象

按需启用模块（本项目 31 个只留 3~4 个，见 `AGENTS.md` §1.6）之后，**每次请求**都会记
一批形如下面的 WARNING：

```
[ModuleManager] 模块 [activity_plan] 依赖 [ai], 但该模块未安装
[ModuleManager] 模块 [ai-streaming] 依赖 [ai], 但该模块未安装
[ModuleManager] 模块 [commerce] 依赖 [billing], 但该模块未安装
…（共 8 条）
```

实测一天 **2224 条**（8 个模块 × 278 次请求）。

### 根因

`multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:317`：

```php
$errors[] = "模块 [{$name}] 依赖 [{$dep}], 但该模块未安装";
```

依赖检查遍历的是**完整模块表**，而不是「已启用的模块」。于是被裁掉的模块
（`activity_plan` / `ai-streaming` / `commerce` / `ibot` / `order` / `pay` /
`service-desk` / `user-ai`）**自身的**依赖缺失也被当成错误报出来 ——
而这些模块本来就没打算启用，它们依赖谁与运行时无关。

### 影响面

- 真错误被噪音淹掉
- **WARNING 级别失去信号价值**：一天两千多条 WARNING，等于没有 WARNING
- 日志膨胀：本项目一天的日志里绝大部分是这 8 条的重复

### 建议修法（按侵入性从小到大）

1. 依赖检查只对**已启用**的模块做 —— 未启用的模块不加载，依赖缺失就不影响运行
2. 或把「模块自身未启用」这种情况降到 `debug` 级
3. 或提供开关（如 `modules.skip_dependency_check_for_disabled: true`）

### 复现

```bash
# 只装 multi-tenant-saas + module-infrastructure + module-auth（不装 ai / billing）
LOG_CHANNEL=json php artisan tinker --execute="Log::info('probe');"
# → stderr 里先出现 8 条 ModuleManager WARNING，然后才是 probe 那条
```

### 下游的临时处置

不改 vendor（§1.8）。本项目侧记为 `cross-project-issues.md` **CPI-39**；
临时降噪手段是把日志 channel 的 level 提到 error，或加过滤。

---

### 非上游、纯我方的已知缺口（2026-09-24 实测记录，用户裁定「只记录不改」）

| 端点 | 表现 | 根因 | 处置 |
|---|---|---|---|
| `/api/v1/files/entity/avatar/{id}/url` | 404 | `app/Modules/VPN/Routes/api.php:213` 注释称该能力「现由框架 Storage 模块承接」，但 Storage 属 §1.6 被砍模块，路由从未存在 | **只记录不改**。GUI 已降级到内置 SVG 头像（实测控制台 `Avatar loaded successfully: data:image/svg+xml;base64,...`），不挡功能 |
| `/api/v1/users/onboarding` | 500 | 见下——**我方未完成实现**，非框架缺陷 | 待用户裁定：补齐（建表 + 两个 service 方法）或删掉死 API 面 |

⚠️ 注意区分：`/api/v1/users/onboarding` 的 500 **不是**框架缺陷——
那是我方 `UserController.php:411` 调 `App\Services\UserService::getOnboardingState()`，
而该方法在我方 `UserService` 里不存在（只有 `listUsers`/`getUser`/`updateUser` 三个），
且 `user_onboarding`/`onboarding`/`user_settings` 三张表都没有。
框架的 `TenantOnboardingService` 是**租户开通引导**（Operator 驱动，
基础信息→域名→套餐→支付→创建 Tenant），与这里的**用户新手引导**
（`completed`/`currentStage`/`selectedPath`/`stages`）是两个概念，不能混用。

---

## 缺陷 3：核心包的 init_data 给**可选模块**的表插种子，且外键在 `FOREIGN_KEY_CHECKS=0` 下静默悬空

> **状态（2026-10-05）**：⚠️ 本条判据**已不成立**，且上游的注释直接点名了本条 ——
> `framework_core_data.php:76` 写「（下游 2026-09-25 缺陷报告 #3）」、`:1213` 写
> 「是下游 2026-09-25 缺陷报告 #3『core 种子写可选模块表』的**直接来源**」。
> 修法与本条「建议上游怎么修」的第 1 条一致（逐表 `hasTable` 守卫）：
> `multi-tenant-saas/database/migrations/2025_01_02_000000_framework_core_data.php:1215-1219`
> 的 `seedSubscriptionPlans()` 首行 `if (! Schema::hasTable('subscription_plans')) { return; }`，
> `:69-80` 的类注释解释了「守卫写成缺表则**跳过**而非报错」的理由，`:1331-1345` 的 `down()` 同样逐表守卫。
> 外键那一半也修了：`module-infrastructure/Database/migrations/2025_01_01_000014_infrastructure_module.php:236-252`
> 把 `tenants.subscription_plan_id` 的外键改成 `Schema::hasTable()` 条件添加（注释 `:239` 同样点名缺陷报告 #3），
> 且 `:242-244` 说明了为什么**故意保留**那条同名索引不删（怕 MySQL 自建出 `…_index` 与生产分叉）。
> 上游还把它固化成**检查 18「必装包的迁移不得对可选模块的表建无条件外键」**
> （`scripts/architecture_guard.py:1158-1169`）。
> **行号漂移两处**：原引用的
> `2025_01_01_000008_infrastructure_module.php` 现为 `2025_01_01_000014_infrastructure_module.php`；
> `2025_01_01_000018_framework_init_data.php` 已不存在，种子并进了上面的
> `2025_01_02_000000_framework_core_data.php`。
> back 侧的本地兜底迁移 `database/migrations/2025_01_01_000010_create_subscription_plans_table.php`
> （`:9` 自陈「已记入本文件的缺陷 3」、`:37` 约定「上游给出**真定义**后以它为准并删除本迁移」）**不能按这条
> 一并退场** —— 上游给的是**守卫**不是真定义，而兜底今天已不是惰性件：它排在 `000014` 之前，
> 所以框架那条**条件外键**因此真的建上了，种子也因此真的灌了 4 行
> （只读实测：`information_schema` 里 `tenants_subscription_plan_id_foreign → subscription_plans` 存在、
> `subscription_plans` 4 行）。删掉兜底 = 外键不建 + 种子不灌，**是行为变更**，
> 属 back 侧单独的裁定与验证，本条不在此范围内下结论。

- **位置**：
  - `vendor/dsplat/multi-tenant-saas-module-infrastructure/Database/migrations/2025_01_01_000008_infrastructure_module.php:235`
    —— `CONSTRAINT tenants_subscription_plan_id_foreign FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans (subscription_plan_id)`
  - 同文件 `:11` `SET FOREIGN_KEY_CHECKS=0` / `:239` 置回 1
  - `vendor/dsplat/multi-tenant-saas/database/migrations/2025_01_01_000018_framework_init_data.php`
    —— 往 `subscription_plans` 插 **4 条**种子（basic / … / enterprise）
- **问题**：`subscription_plans` 的建表语句在**已安装的三个包里一个都没有**
  （全 vendor grep `CREATE TABLE.*subscription_plans` 与 `Schema::create('subscription_plans'` 均 0 命中）——
  它属于按需裁剪（§1.6：31 个模块只留 3~4 个）时被移除的 billing/subscription 模块。
  于是：① 000008 在 `FOREIGN_KEY_CHECKS=0` 下把外键建成**指向不存在的表**，不报错；
  ② 报错被推迟到 000018 的种子插入 —— `SQLSTATE[42S02] 1146 Table '…subscription_plans' doesn't exist`，
  **全新库上 `migrate` 到此为止**。
- **为什么是上游的锅**：核心包（必装）无条件依赖一个可选模块的表。裁剪模块是框架**鼓励**的用法
  （§1.6），而裁剪后核心迁移就跑不完 —— 这两件事不能同时成立。
- **建议上游怎么修**（任选其一）：
  1. `framework_init_data` 对每张表先 `Schema::hasTable()` 再插（最小改动，且与其它种子的幂等风格一致）；
  2. 把 `subscription_plans` 的建表移进核心/infrastructure（既然 `tenants` 的外键指向它，它就是核心的依赖）；
  3. 去掉 `tenants.subscription_plan_id` 的外键约束，改为应用层校验。
- **我方处置**（§1.8：不在 vendor 打补丁）：新增
  `database/migrations/2025_01_01_000010_create_subscription_plans_table.php` 本地兜底 ——
  带 `hasTable` 守卫（上游哪天自己建了就自动让位）、列型从种子 INSERT 反推（无权威定义可抄，
  故约束从宽）、文件头写明「上游给出真定义后以它为准并删除本迁移」。
- **可粘贴给上游的复现**：
  ```
  composer require dsplat/multi-tenant-saas dsplat/multi-tenant-saas-module-auth \
      dsplat/multi-tenant-saas-module-infrastructure   # 只装这三个，不装 billing/subscription
  php artisan migrate --force
  # 期望：迁移跑完
  # 实际：2025_01_01_000018_framework_init_data 失败
  #       SQLSTATE[42S02]: 1146 Table '…subscription_plans' doesn't exist
  ```

---

## 缺陷 4：迁移是 **MySQL 专用**的，换任何别的驱动都跑不起来

> **状态（2026-10-05）**：⚠️ 本条**原判据已不成立，残余部分降级为「已知限制且已文档化」** ——
> 上游的注释同样点名了本条（`module-auth/Database/migrations/2025_01_01_000004_auth_module.php:15`、
> `module-infrastructure/.../2025_01_01_000014_infrastructure_module.php:15`、
> `multi-tenant-saas/src/Support/MySqlOnlyMigration.php:23` 三处都写
> 「（下游 mynet.club 2026-09-25 缺陷报告 #4）」）。落法是本条建议的两级：
> ① **必装三包已改成 Schema Builder**（`000004`/`000014` 通体 `Schema::create(...)`，无裸 DDL、无
>    `SET FOREIGN_KEY_CHECKS`），② 其余 19 个模块迁移包进驱动守卫
>    `MultiTenantSaas\Support\MySqlOnlyMigration::skip()`，非 MySQL 驱动下给一句人话
>    （抛 `DomainException`，抛出点 `src/Support/MySqlOnlyMigration.php:73`，消息里带支持矩阵文档路径
>    与开关 `TENANCY_SKIP_MYSQL_ONLY_MIGRATIONS=true`）。
> **本批实测**（临时空 sqlite 文件，非生产库，跑完即删）：
> `DB_CONNECTION=sqlite DB_DATABASE=/tmp/x.sqlite php artisan migrate --force`
> ⇒ `000004_auth_module` DONE、`000014_infrastructure_module` DONE、
> `000022_operator_module` FAIL 但报的是那句可读的 DomainException —— 原症状
> `near "SET": syntax error` 已消失。
> **残余一条不属于框架**：开上 `TENANCY_SKIP_MYSQL_ONLY_MIGRATIONS=true` 后，框架迁移能过，
> 挂在 **back 自己的** `database/migrations/2026_09_22_000006_add_tenant_id_to_users.php:32`
> （裸 `UPDATE users u JOIN …` 是 MySQL 方言）⇒ 这是 back 侧欠账，记一句免得下次算到框架头上。
> 下面正文里的两个文件名已漂（`000003_auth_module` → `000004`；`000008_infrastructure_module` → `000014`）。

- **位置**：
  - `vendor/dsplat/multi-tenant-saas-module-auth/Database/migrations/2025_01_01_000003_auth_module.php`
    —— `DB::statement('SET FOREIGN_KEY_CHECKS=0')`
  - `vendor/dsplat/multi-tenant-saas-module-infrastructure/Database/migrations/2025_01_01_000008_infrastructure_module.php`
    —— 裸 `CREATE TABLE … ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`，同样带 `SET FOREIGN_KEY_CHECKS`
- **问题**：这两处都是 MySQL 方言，**没有按驱动分支**。实测在 sqlite 上
  `php artisan migrate --force` 停在第 2 个迁移：
  `SQLSTATE[HY000]: General error: 1 near "SET": syntax error (SQL: SET FOREIGN_KEY_CHECKS=0)`。
- **后果（对我方很实际）**：本机开发库无法用 `migrate` 建出来，只能靠
  `tests/Schema/*.php` 自建一套 —— 于是**测试态 / 本机开发态 / 生产态三套 schema 各自漂移**
  （CPI-47 / CPI-48 就是这么来的）。staging 上实测 MariaDB 10.11 可以跑通
  （59 条迁移全 DONE、120 张表），所以问题限于非 MySQL 系驱动。
- **建议上游怎么修**：把 `SET FOREIGN_KEY_CHECKS` 与裸 DDL 包在驱动判断里
  （`if ($connection->getDriverName() === 'mysql')`），或改用 Schema Builder 表达同样的结构。
  退一步，至少在文档里写明「本框架的迁移只支持 MySQL/MariaDB」。
- **可粘贴给上游的复现**：
  ```
  # .env: DB_CONNECTION=sqlite, DB_DATABASE=/tmp/x.sqlite（空文件即可）
  touch /tmp/x.sqlite && php artisan migrate --force
  # 期望：迁移跑完（或在 sqlite 上明确报「不支持」）
  # 实际：2025_01_01_000003_auth_module FAIL
  #       SQLSTATE[HY000]: General error: 1 near "SET": syntax error
  ```

---

## 缺陷 5：`ModuleRegistry` 的依赖检查按**完整模块表**做，裁剪后每请求刷 8 条 WARNING

> **状态（2026-10-05）**：本条与上面的「缺陷 3」（ModuleRegistry 那一版）是同一件事，以本条为准；
> ⚠️ 且**判据已不成立** —— 上游已改成只遍历已启用模块，并把逐条 WARNING 收成一条汇总。
> 现行源码：`multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:374`
> （`validateDependencies(array $enabledNames)`）、循环体 `:379`、
> 文案「未安装」已从本条引用的 `:317` **漂到 `:386`**，另有新分支 `:388`「未启用」；
> 日志面 `Services/ModuleManager.php:200-211` —— 正常时一条 `Log::info`（`:201`），
> 异常时一条带条数的 `Log::warning`（`:207-211`），明细需显式开 `TENANCY_LOG_MODULE_DEPENDENCY_GATE=true`。
> 取证：本地日志 `grep -c "但该模块未安装"` = 09-23 共 600 条 / 09-24 共 2448 条 / **09-25 起 0 条**。
> 本条正文里的 `:317`、「每请求 8 条」「一天 2224 条」按行号规则属**已失效**，保留仅作历史。

- **位置**：`vendor/dsplat/multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:317`
- **问题**：按需裁剪（我方按 AGENTS.md §1.6 从 31 个模块只留 3~4 个）之后，框架仍按完整模块表
  检查依赖，于是**每个请求**都记 8 条形如
  `[ModuleManager] 模块 [activity_plan] 依赖 [ai], 但该模块未安装` 的 WARNING。
  实测一天 2224 条（8 × 278 次请求）。
- **后果**：① 真错误被淹；② **WARNING 级别失去信号价值**（一天两千多条等于没有 WARNING）；
  ③ 日志膨胀 —— 我方生产上 `storage/logs/laravel.log` 涨到 **14 GB**、把 40G 磁盘吃到 91%，
  这类噪音是主要贡献者之一（2026-09-25 已清理，磁盘回到 25%）。
- **建议上游怎么修**：只对**已启用**的模块做依赖检查；或把「模块本身未启用」这种情况降到 debug。
- **我方处置**：不在 vendor 打补丁（§1.8）；已清理日志并计划给日志加轮转与级别过滤。
- **对应台账**：CPI-39


---

## 缺陷 6：`platform:init` 的租户 seeder 裸 `new SlugService`，未装 `domain` 包时直接致命

> **状态（2026-10-05）**：本条对应 `dev-plan.md` 的 **F-6**，**back 侧复核已关闭，正文取证保留**
> （关闭理由记的是「`platform:init` 路径上的 `new SlugService` 不是绕过闸门的特例，框架自己在
> `TenantService.php` 里也同样直接 `new`，属既有约定」）。
> ⚠️ 但**本批复核要把一条事实钉回去：那个关闭理由引述的现状在今天的 vendor 里是反的** ——
> `module-infrastructure/Services/TenantService.php:31-36` 现在是
> `OptionalModule::ensure(SlugService::class, …)` **之后**才 `new SlugService`（有守卫），
> 而 `module-operator/Database/Seeders/PlatformTenantSeeder.php:6,55` 仍是裸 import + 裸 `new`（无守卫）。
> ⇒ 本条正文「同一个类、两套纪律，seeder 这条是漏改的那条」这句**仍然成立且行号未漂**
> （`PlatformTenantSeeder.php:55` 本日复核仍在那一行）。
> 关闭与否属主线的裁定，本批不翻案；这里只保证**不把「框架两处都裸 new」这个说法当事实传下去**。
> 另：`module-operator/composer.json` 的 `require` 到本批仍只有 `php` + `dsplat/multi-tenant-saas`，
> `domain` 依然未声明。

- **位置**：`vendor/dsplat/multi-tenant-saas-module-operator/Database/Seeders/PlatformTenantSeeder.php:6,55`
- **现象**：装了 `operator`、没装 `domain` 的宿主跑 `php artisan platform:init`：
  ```
  Class "MultiTenantSaas\Modules\Domain\Services\SlugService" not found
  ```
  不是异常，是 `\Error`（`catch (\Exception)` 拦不住），命令整条挂在建「平台超级租户」这一步。
- **根因**：`module-operator/composer.json` 的 `require` 只有 `php` + `dsplat/multi-tenant-saas`，
  **没声明 `multi-tenant-saas-module-domain`**，但 seeder 第 55 行直接实例化它的 `SlugService`。
  与本文 **缺陷 1** 同一类问题（硬引用未声明的可选模块）。
- **对照：框架自己在别处是对的**——`module-infrastructure/Services/TenantService.php:31-35` 走
  `OptionalModule::ensure(SlugService::class, ...)` 再 `new`，缺包时给的是可读的 501/提示而非致命。
  **同一个类、两套纪律**，seeder 这条是漏改的那条。
- **建议上游怎么修**：`PlatformTenantSeeder.php:55` 改成复用 `TenantService::slugService()`（或同样
  `OptionalModule::ensure` 包一层）；要么就在 `module-operator/composer.json` 里把 `domain` 声明成依赖。
- **我方处置**：不在 vendor 打补丁（AGENTS.md §1.8）。本地演示租户改由宿主侧
  `mynet_api/database/seeders/ConsoleDevSeeder.php` 种（显式非保留 slug，不碰 `platform:init`）。
  代价：`platform:init` 附带的 `RoleSeeder`/`PermissionSeeder`/`SuperAdminSeeder` 这条正规装配路径
  我方暂时用不上，RBAC 字典得自己种 —— **上游修好后应回退到 `platform:init`**。
- **对应计划**：`dev-plan.md` 轨道 G（#65–70）本地跑通补记。

---
---

# 第二批（2026-10-05）：`dev-plan` F-7…F-21 的复核结果

## 这一批怎么读

- **来源**：`dev-plan.md:471-491` 那张「框架侧问题与待裁定」表里的 **F-7、F-8、F-9、F-10、F-14、
  F-15、F-16、F-17、F-18、F-19、F-20、F-21** 共 12 条 —— 它们才是框架侧的；同表其余条目的处置见文末
  「不列入本清单的条目」。
- **编号**：接续本文既有的「缺陷 6」，从 **缺陷 7** 到 **缺陷 18**；`dev-plan` G1 清单的第 13 条
  **不按缺陷提**，写成文末的「对齐请求 1」。
- **每条固定六节**：现象 → 根因与取证 → 复现方式 → back 现状的兜底 → 要框架定的一件事 → 复核行。
- **路径口径**：取证路径一律相对 `mynet_api/`（`vendor/dsplat/...` 是框架，`app/...`、`src/...`、
  `resources/...`、`bootstrap/...`、`database/...` 是 back）。
- **行号**：本批 12 条的每一个 `file:line` 都在 2026-10-05 对当前 vendor / back 源码重跑过一遍；
  与 `dev-plan` 原记录不一致的，**以本文为准并在正文标出漂移**。
- **「全绿而无后果」这一类单独标出来**：**缺陷 8、10、12、13、14、15、16、18** 共八条都不返回错误码 ——
  HTTP / build 全绿而行为没发生（该显示的行不在响应里、该拒的操作放过了、该生效的判定跳过了），
  这是最难发现的一种，本批把它们排在各自条目的第一句。同族只有两种例外：
  **缺陷 11** 在构建期就红（掀桌反而是好消息），**缺陷 17** 有输出但退出码归不了因。

## 对照表（`dev-plan` ↔ 本文）

| 本文 | dev-plan | 一句话 |
|---|---|---|
| 缺陷 7 | F-7 | 邀请码唯一键是 `(tenant_id, code)` ⇒ 跨区重码可达；字母表 `self::` 绑死不可注入 |
| 缺陷 8 | F-8 | 框架不给 guest 认证路由写租户上下文 ⇒ 填码注册**静默空转**（200，什么都没发生） |
| 缺陷 9 | F-9 | 注册路径不写 `users.tenant_id`；框架侧没有「当前工作租户」这个概念 |
| 缺陷 10 | F-10 | 上下文为空时 `(int)` 强转成 0，在**租户 0** 上懒建一张真码 |
| 缺陷 11 | F-14 | element-plus 布局对 `AiAssistant` 是硬 import ⇒ 不装 `module-ai` 就 **build 不起来**；附框架自带内容里的单一客户（scrm）痕迹 |
| 缺陷 12 | F-15 | module-loader 的 glob 对 `resources` 段大小写敏感，失败方式是**静默不产出** |
| 缺陷 13 | F-16 | Operator 列表把「账户停用」「本区关联失效」「待接受」三层都当过滤 ⇒ 三类人一起消失、操作成了单向门 |
| 缺陷 14 | F-17 | 列表不带 pivot（角色不在响应里），可分配角色枚举又没有读取端点 |
| 缺陷 15 | F-18 | Operator 写面没有自我隔离，也没有「本区最后一个 `tenant_admin`」保护；顺带四条端点不按租户收口 |
| 缺陷 16 | F-19 | 框架模型日期序列化成无偏移的 `Y-m-d H:i:s` ⇒ 与 back 自有端点同页混用差 8 小时 |
| 缺陷 17 | F-20 | 框架自带前端源在下游 tsconfig 下带 16 条类型错 ⇒ 退出码设不了闸门 |
| 缺陷 18 | F-21 | 「待接受邀请」多租户语义不一致：接受一次激活全部待接受关联，重发只认账户 |
| 对齐请求 1 | A1 后半（`dev-plan.md:510`、G1 行 `:528`） | 属主判定（Owner Guard）**方法签名对齐** + back 侧接线计划 |

---

## 缺陷 7 —— 邀请码的唯一键是**区内**唯一，而 console 可以指定字面码：跨区重码可达

### 现象

同一个码串（例如运营手工建的 `VIP2026`）可以**在 A 区和 B 区各存在一条**。用户拿它入区时
「这条码属于哪个区」没有唯一答案。框架面**不报任何错**（建码 201、列表 200），冲突推迟到
下游消费时才暴露 —— back 侧的暴露形式是 HTTP 422「邀请码不唯一，请联系管理员」，把框架的数据模型
缺陷转嫁成了运维工单。

### 根因与取证

- **唯一键的构成**：`vendor/dsplat/multi-tenant-saas-module-auth/Database/migrations/2026_10_01_000001_add_invite_code_tables.php:42`
  `$table->unique(['tenant_id', 'code'], 'invite_codes_tenant_id_code_unique')` —— 复合键，
  `code` 单列**没有**唯一约束（同文件的其余索引 `:43-45` 都是 `tenant_id` 打头的普通 index）。
- **字面码入口是现役的**：console 建码端点把 `code` 原样收进校验
  （`.../Http/Controllers/InviteCodeController.php:48` `'code' => 'nullable|string|max:32'`），
  透传到服务层的 `$options['code']`（`.../Services/InviteCodeService.php:138`），
  `generate()` 只在 `$code === null` 时才随机（`:139`）⇒ **两条区可以填同一个字符串**。
- **字母表不可注入，原因比「私有」更硬**：`CODE_ALPHABET` 是 `protected const`（`:37`），
  读取用的是 `self::CODE_ALPHABET`（`:224`，`generateCode()` 内）。PHP 的 `self::` 在编译期绑到**定义类**，
  子类重写常量对父类这段代码无效 ⇒ 「back 继承 `InviteCodeService` 覆盖字母表」这条绕行也不通，
  除非框架把它改成 `static::`。
- **本批自纠一处旧口径（长度是可配的）**：`generate()` 读 `$options['length']`，缺省再读**租户级设置**
  `registration.invite_code_length`（`:140-141`），边界常量 `DEFAULT_CODE_LENGTH=8` / `MIN=6` / `MAX=16`
  （`:40`/`:43`/`:46`，均 public），`:142` 用 `max/min` 夹住；另有 `registration.invite_code_prefix`（`:143-144`）。
  ⇒ 旧记录里「无法配长度」不成立。**行号漂移**：`dev-plan.md:477` 写的是 `:138-142`，
  实际长度那半在 **`:140-142`**（`:138` 是 `$options['code']` 那一行）。
- **与本题无关但已复核仍在**（记录以免重复排查）：`consume()`（`:295-348`）走事务 +
  `lockForUpdate`（`:301`）+ `(码, 人)` 幂等（`:309-315`）+ 自邀请拦截（`:328-330`）。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，**只读**（不写任何行）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && php artisan tinker --execute='
$fk = DB::select("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\"invite_codes\" AND REFERENCED_TABLE_NAME IS NOT NULL");
echo json_encode(["fk"=>$fk, "indexes"=>array_map(fn($i)=>$i->Key_name."(".$i->Seq_in_index.")=".$i->Column_name, DB::select("SHOW INDEX FROM invite_codes"))], JSON_UNESCAPED_SLASHES);'
```

2026-10-05 本机实测输出：`fk` 为 **`[]`**，索引里有
`invite_codes_tenant_id_code_unique(1)=tenant_id` 与 `(2)=code` ⇒ 唯一性确实只到「区内」。

### back 现状的兜底

`app/Modules/VPN/Domain/Services/ZoneInviteDomainService.php:79-98`（私有 `resolveTenantId()`）：
按码反查 `tenant_id` 去重（`:81-85`），**空集**判「码不存在」（`:87-89`），
**多值**判歧义并拒（`:91-95` `'邀请码不唯一，请联系管理员'`，`:92-93` 的注释点名的正是框架这条
`(tenant_id, code)` 唯一键 + console 可指定字面码）。
⇒ **框架改成全局唯一（或给出 `globally_unique` 开关）后，`resolveTenantId()` 的 `:91-95` 这段歧义拒绝应删**
—— 全局唯一态下它永不可能命中，留着就是把一个已经消失的失败模式常驻在代码里。
判据已在源码里逐行核过；「实际造出两条跨区重码并触发 422」**未做数据实测**（要写库）。
回归锁：`php vendor/bin/phpunit --filter ZoneInviteJoinTest` → `OK (13 tests, 45 assertions)`。

### 要框架定的一件事

收敛成一条：**把 `code` 的唯一性提升为全局**（或加 `globally_unique` 开关）。
⚠️ 这是**破坏性 DDL**（改唯一键要迁移既有 `invite_codes`），back 不擅自动框架表，
也**不自研第二张码表**。字母表注入是次要诉求（`self::` → `static::` 一行），可与主诉求分开裁。
back 现在的兜底是「歧义即拒 + 提示联系管理员」，能用，但把冲突推给了运维。
复核：2026-10-05，行号已对源码验证

---

## 缺陷 8 —— 框架不给 **guest 认证路由**写租户上下文：填码注册静默空转（最难发现的一种）

### 现象

**静默不生效**，不是 500 也不是 422：`POST /api/v1/auth/register` 带邀请码 ⇒ **HTTP 200 + 建号成功**，
但三件事一件都没发生 —— 注册门禁没判（`registration.mode=invite_only` 形同 `open`）、
`tenant_users` 成员行没建、邀请码一次都没核销。用户「填了码、注册成功、却没进任何网络区」。
`POST /api/v1/auth/invite-code/verify` 同形（校验也拿不到区，只能一律判无效或一律放过）。
症状比 500 难查得多：客户端与服务端都没有任何错误面。

### 根因与取证

- **框架自己装配的公开路由里没有租户识别器**：
  `vendor/dsplat/multi-tenant-saas-module-auth/Routes/public.php:16-17`（`auth/register`，只挂 `throttle:10,1`）、
  `:34-35`（`auth/invite-code/verify`，只挂 `throttle:20,1`）；
  `:12` 的注释是「公开路由（无需认证）」，**整份文件没有任何 `tenant.identify`**（本批全文 grep 命中 0）。
  ⚠️ **路径漂移**：`dev-plan.md:478` 里写的旧位置 `routes/api/public.php` 现已是
  `Routes/public.php`（大写 `R`、不在 `routes/api/` 下）。
- **注册链路三步全读同一个判据**：
  `.../Http/Controllers/UserAuthController.php:121` `$tenantId = $request->attributes->get('tenant_id')`，
  三处消费点依次是门禁 `:127`（`assertRegistrationAllowed`）、成员行 `:139-145`（`if ($tenantId)`）、
  核销 `:148-151`（`if ($tenantId && $inviteCode !== null)`）⇒ `$tenantId` 为 null 时三步**各自跳过**，
  而且跳过得完全合规，没有 else 分支报错。短信登录建号路径同形（`:291` 门禁、`:307-318` 成员行、
  `:322-323` 核销）。
- **那个属性唯一的写入口是框架的 `TenantContext`**：
  `vendor/dsplat/multi-tenant-saas/src/Context/TenantContext.php:65-71`（`setTenantId()` 就是
  `$request->attributes->set('tenant_id', …)`，`:69`），读侧 `:58`。
- **并且解析失败时框架会主动清空上下文**：
  `vendor/dsplat/multi-tenant-saas-module-infrastructure/Http/Middleware/IdentifyTenant.php:64-68`
  —— else 分支同时 `setTenant(null)` 与 `setTenantId(null)`（`:66-67`，注释 `:65` 明写「清除可能由
  前一次中间件设置的上下文」）⇒ 即使以后有别的解析器先跑过，这里也不会残留，
  下游补丁只能排在它**之后**。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读一条：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && php artisan tinker --execute='
foreach (["api/v1/auth/register","api/v1/auth/invite-code/verify","api/v1/app/invite-code","api/v1/tenant/invite-codes"] as $uri) {
  $r = collect(Route::getRoutes())->first(fn ($x) => $x->uri() === $uri);
  echo $uri, "  =>  ", $r ? implode(" | ", $r->gatherMiddleware()) : "NOT FOUND", PHP_EOL;
}'
```

2026-10-05 实测（前两条是 guest 路由，后两条是登录态对照）：

```
api/v1/auth/register          =>  api | throttle:10,1
api/v1/auth/invite-code/verify=>  api | throttle:20,1
api/v1/app/invite-code        =>  api | auth:sanctum | throttle:api | tenant.identify | …VerifyOperatorTenant | module.enabled:auth
api/v1/tenant/invite-codes    =>  api | auth:sanctum | throttle:api | tenant.identify | module.enabled:auth | rbac.permission:invite_code.manage
```

⚠️ 口径说明：`gatherMiddleware()` 返回**路由自身声明的中间件**，组名 `api` 不展开
（back 追加的两条在组里，用 `app('router')->getMiddlewareGroups()['api']` 可看到
`EnsureFrontendRequestsAreStateful, SubstituteBindings, ResolveTenantFromUser, ResolveTenantFromInviteCode`）
⇒ 上面这列证明的是「框架路由声明里**没有** `tenant.identify`」，登录态两条有它、guest 两条没有。

### back 现状的兜底

- `bootstrap/app.php:50-53` 往 `api` 组**末尾**追加两条：
  `ResolveTenantFromUser`、`ResolveTenantFromInviteCode`（`:45-49` 的注释写明「后者必须排在最后」
  以及为什么：注册三步全读 `request` 属性 `tenant_id`）。
- `app/Http/Middleware/ResolveTenantFromInviteCode.php` 用**码上的租户**补上下文，
  类注释 `:13-31` 整段就是本条的 back 侧复述（`:19-21` 点名 `UserAuthController::register():121`、
  `InviteCodeController::verify():222`、`TenantContext.php:65-71`；`:27-31` 写「静默空转而不是报错」）。
- 登录态那半由 `app/Http/Middleware/ResolveTenantFromUser.php` 兜（见缺陷 9 的兜底段）。
⇒ **框架若选下面的 A（自己在 register/verify 里按码反查租户并置上下文），back 的
`ResolveTenantFromInviteCode` 整条应删**（`bootstrap/app.php:52` 那一行同时删）；
选 B 则这条长期持有，back 只需要框架把归属写成一句话。
判据逐行核过；触发方式（`mode=invite_only` 下真跑一次填码注册）**未做实测**，
需要改 `registration.mode` 设置 = 数据动作，back 不动库。

### 要框架定的一件事

两个方向请框架选：

- **(A)** 框架自己在 `register` / `verify` 里按 `invite_code` 反查租户并置上下文
  —— 语义正主，`mode=invite_only` 到这一步才算真生效。
- **(B)** 框架在决策文里**明文声明**「租户上下文由下游提供」，back 的
  `ResolveTenantFromInviteCode` 就是那个补丁并长期持有。

当前 back 走自补，能跑，但下次框架改动 register 的读法时 back 无从知晓
—— 要的只是把归属写死一句话。
复核：2026-10-05，行号已对源码验证

---

## 缺陷 9 —— 框架的注册路径**不写** `users.tenant_id`：框架侧没有「当前工作租户」这个概念

### 现象

下游把「这个人现在在哪个区」的判据放在 `users.tenant_id` 上时，**注册完成的那一刻该列是 NULL**
（框架三步只写成员行 `tenant_users`）。不报错、不返回 null 之外任何信号 —— 表现是
「加入成功了，但按 `users.tenant_id` 读归属的地方读到空」，随后下游的硬闸会 403。
本条与缺陷 8 是一对：8 说的是**上下文**从哪来，9 说的是**指针列**谁写。

### 根因与取证

- **邮箱注册**：`vendor/dsplat/multi-tenant-saas-module-auth/Http/Controllers/UserAuthController.php:132-136`
  `User::create([...])` 只给 `name` / `email` / `password` 三个键，**没有 `tenant_id`**；
  租户信息全部落在成员行 `:139-145`（`if ($tenantId)` 包住 `TenantUser::create`，`:140-144`）。
- **短信注册同形**：`:293-297` 建号（`name`/`phone`/`password`）、`:307-318` 确保成员行
  （`:312-316` 才是真正 insert 的那三条键）。
- **OAuth 注册同形**：`.../Services/Concerns/GatesOAuthRegistration.php:47-58`
  的 `finalizeOAuthRegistration()` 只做「核销 `:52-55` + 派发事件 `:57`」，同样不写 `users.tenant_id`；
  门禁断言在 `:36-39`。
  ⚠️ **路径漂移**：`dev-plan.md:473`（F-3 行）记的 `Http/Controllers/Concerns/…` 不存在，
  实际在 **`Services/Concerns/`**。
- **`TenantUser::$fillable` 不含 `role`**：`vendor/dsplat/multi-tenant-saas-module-infrastructure/Models/TenantUser.php:32-38`
  = `tenant_id, user_id, credits, is_active, joined_at` ⇒ 批量赋值传 `role` 会被**静默丢掉**。
  由于该列有库层默认值（见下），旧代码里那句 `'role' => 'end_user'` 丢掉后落库结果仍是对的，
  属「无害但误导」，**不是 bug**，本批不把它列进诉求。
- **自纠一条旧口径（原 F-9 的另一半作废）**：旧记录写「框架 `tenant_users` 没有 `role` 列」—— **错**。
  本地 `mynet_api_dev` 只读实测该表 9 列、含 `role varchar(50) NOT NULL DEFAULT 'end_user'`
  与索引 `tenant_users_role_index`；出处是 back 自己的迁移
  `database/migrations/2026_09_25_000001_add_mynet_columns_to_framework_tables.php:50-61`。
  真正的测试/生产漂移方向相反（`tests/Schema/CoreModule.php` 多建了一条 `tenant_users.role_id`，
  该幽灵列已删，`mynet_api@19a2807`）。⇒ 「role 谁补」这一问作废。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读一条（把注册路径的建号语句摊出来看键）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && sed -n '131,145p' vendor/dsplat/multi-tenant-saas-module-auth/Http/Controllers/UserAuthController.php
```

期望读到的事实：`User::create` 的数组里**没有** `tenant_id` 这个键，`tenant_id` 只出现在紧随其后的
`TenantUser::create` 里。列面复核：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && php artisan tinker --execute='echo json_encode(DB::select("SHOW COLUMNS FROM users LIKE \"tenant_id\""));'
```

### back 现状的兜底

三处，**框架若把指针写上（下面的 A 向），前两处应退成兼容层**：

1. `app/Modules/VPN/Listeners/BindUserToZoneOnRegistration.php:35-37`
   —— 挂框架的 `UserRegistered` 扩展点（类注释 `:18-19`），用
   `where('user_id')->whereNull('tenant_id')->update([...])` 写指针；
   `:32-34` 的注释写明了为什么判据必须留在 SQL 里而不是先读模型（访问器回退会掩盖真实列值，见第 3 点）。
2. `app/Modules/VPN/Domain/Services/ZoneInviteDomainService.php:60`
   —— 凭码入区同样用 `User::where(...)->update(['tenant_id' => $tenantId])` 强制写库。
3. `app/Models/User.php:30-44` 的 `getTenantIdAttribute()` 回退 —— 属性里没这个键时改查
   `tenant_users`（active + `orderBy('tenant_users.tenant_id')` 取首条，`:38-43`），
   判据与 `ResolveTenantFromUser` 对齐（注释 `:27-28`）。这条掩盖是**双刃**：
   它让「读指针」在指针为空时也能给出一个区，所以第 1、2 处都不能改成「先读模型再判」。
4. `app/Http/Middleware/ResolveTenantFromUser.php:72-95` —— 按裁定 A2 把服务端指针当唯一权威：
   `:72-78` 取 active 成员集合、`:82` 读 `users.tenant_id` **原始列**（注释 `:80-81` 点名访问器回退的坑）、
   `:91` 指针仍是活跃成员就用指针、否则回退最小 active 区、`:93-95` 写进 `TenantContext`。
   ⚠️ **行号漂移**：`dev-plan.md:480`（F-10 行）引用的 `:61-69` 已不是这段（A2 重写后落在 `:72-95`）。
   ⇒ 框架给出「当前工作租户」权威定义后，这条中间件的**回退分支**（`:91` 的 `?? $memberships[0]`）应删，
   只保留指针读取。

回归锁：`php vendor/bin/phpunit --filter TenantSourceAuthorityTest` → `OK (13 tests, 50 assertions)`；
`php vendor/bin/phpunit --filter ZoneInviteJoinTest` → `OK (13 tests, 45 assertions)`。

### 要框架定的一件事

收敛成一句（原第 ② 问「role 谁补」已作废，见取证最后一点）：
**`users.tenant_id`（或等价的「当前工作租户」指针）算不算框架支持的下游判据？**
- **A**：算 ⇒ 请框架保证注册路径把它写上（back 的监听器 `:35-37` 与 `:60` 就可以退成兼容层）。
- **B**：不算，要求下游一律走 `tenant_users` ⇒ back 的节点/社交硬闸要改成查成员行，
  牵动「一个终端配多个网络区」的取向（`dev-plan.md:481` F-11，已由裁定 D1「v1 不做多区自助」收口）。

**back 不自行加列、不擅自动框架表结构。**

复核：2026-10-05，行号已对源码验证

---

## 缺陷 10 —— 用户侧取码端点不判「租户必须存在」：上下文为空时会在**租户 0** 上懒建一张真码

### 现象

**静默不生效**（本批最难发现的一类同族）：`GET /api/v1/app/invite-code` 对「没有任何活跃成员行的用户」
返回 **HTTP 200 + 一张格式完全正确的邀请码**，界面照常显示「我的邀请码」，
而这张码挂在 `tenant_id = 0` 上 —— **不属于任何区、谁也核销不了**（核销要按租户查码行）。
没有任何错误面；脏数据只在库里静默积累。

### 根因与取证

- **`(int)` 强转把 null 变成 0**：`vendor/dsplat/multi-tenant-saas-module-auth/Http/Controllers/InviteCodeController.php:166`
  起 `myCode()`，`:173` `$tenantId = (int) $request->attributes->get('tenant_id')`，`:174` 直接喂给服务层。
  `myUsages()` 同形（`:190` 方法起、`:197` 取属性、`:199` 喂 `listUsagesByInviter`）。
  公开校验端点 `verify()` 也是同一形状（`:216` 方法起、`:222` `(int)` 强转）。
- **服务层一路无校验**：`.../Services/InviteCodeService.php:196-213` 的
  `getOrCreateUserCode(int $tenantId, int $userId)` —— 查不到就 `:209` 调 `generate()` **落库**，
  全程没有租户存在性判定、没有 `tenantId > 0` 判定。
  `listUsagesByInviter()` 在 `:444`（同样按传入 `$tenantId` 过滤，`:448` 起）。
- **库里挡不住它**：`invite_codes.tenant_id` 是 `unsignedBigInteger` 且**没有外键**
  （`.../Database/migrations/2026_10_01_000001_add_invite_code_tables.php:30`；
  整张表**唯一**一条 FK 是 `:63-64` 的 `invite_code_usages.invite_code_id → invite_codes`）
  ⇒ 插 0 号行静默成功。`usages` 侧同理（`:51` 的 `tenant_id` 也无 FK）。
- **中间件面救不了它**：`api/v1/app/invite-code` 挂的是框架自己的 `tenant.identify`
  （复现输出见缺陷 8 的同一条命令），**不是** back 的解析器 ⇒ 对「没有任何活跃成员行」的用户
  上下文照样是 null ⇒ `(int) null = 0` 这条路径真实可达。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读（看约束 + 看存量有没有 0 号脏行）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && php artisan tinker --execute='
echo json_encode(["fk"=>DB::select("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\"invite_codes\" AND REFERENCED_TABLE_NAME IS NOT NULL"),
 "tenant0_rows"=>DB::select("SELECT COUNT(*) c FROM invite_codes WHERE tenant_id = 0")], JSON_UNESCAPED_SLASHES);'
```

2026-10-05 本机实测：`{"fk":[],"tenant0_rows":[{"c":0}]}` —— **约束侧成立（无外键，0 号可插）**，
**存量侧当前为 0 行**（dev 库 `invite_codes` 表本身 0 行）。
⚠️ 口径：本批**没有做触发实测** —— 造一条 `tenant_id=0` 的探针行属**数据动作**，
未请示就不动；上面结论是代码级 + 约束级取证，不是「实测跑出来过」。
现网是否已有 0 号脏行本机够不到，属「**需 DBA 在现网执行的只读 SQL**」：
`SELECT COUNT(*) FROM invite_codes WHERE tenant_id = 0;`（清理动作另说，back 不动库）。

### back 现状的兜底

两道，**框架补上 `tenantId <= 0` 的正数校验后第 2 道可留、第 1 道的语义仍要保留**（它管的是归属不是校验）：

1. `app/Http/Middleware/ResolveTenantFromUser.php:72-95` —— 只有拿到**真实活跃区**才
   `TenantContext::setTenantId()`（`:93-95` 的 `if ($tenantId)`）；
   「只剩指针、没有任何 active 成员行」的账号**故意不设上下文**（注释 `:87-90`，
   出路写进裁定 A5 的 DBA 回填清单）。上下文为空 ⇒ 框架端点仍会走 0 号，
   所以这条兜底是「**缩小可达面**」而不是「挡住」。
2. GUI 侧 `useInviteCode.fetchMyCode()` 在当前租户为空时**根本不发请求**
   （`mynet_gui` 仓；本批只复核到 back 侧代码，GUI 那半**未在代码中逐行验证**，此处按既有记录转述）。

`put` 语义不冲突：console 面读的是 `TenantContext::getId()`，不是懒建那条。

### 要框架定的一件事

`getOrCreateUserCode()` / `listUsagesByInviter()`（以及公开校验端点 `verify()` 的同一判据）
对 `tenantId <= 0` 或解析不到租户时，应 **抛 `DomainException`（4xx）而不是当成租户 0 继续写表**。
是否顺带清一次已产生的 0 号脏行属**数据动作** —— back 不擅自动库，需 DBA 走 `database/sql/`。

复核：2026-10-05，行号已对源码验证

复核：2026-10-05，行号已对源码验证

---

## 缺陷 11 —— element-plus console 布局对 AI 模块是**硬 import**：不装 `module-ai` 就 build 不起来

### 现象

`npm run build` 直接失败（不是运行时 500，是**构建期**红）：

```
Could not load …/vendor/dsplat/multi-tenant-saas/src/Modules/Ai/resources/console/ai-assistant/AiAssistant.vue
```

后果是把「可选的 AI 能力」变成「装 console 就必须装 AI」——连带该包的表（生产禁 migration，要走
`database/sql/`）与路由。本条附带**同源的第二处**：框架自带内容里留着单一客户（`scrm`）的痕迹，
已被 back 的 console 自动发现并打进构建产物。

### 根因与取证

**① 硬 import 那一处（全框架只有一处）**

- `vendor/dsplat/multi-tenant-saas/resources/pages/console/ui/element-plus/layouts/ConsoleLayout.vue:96`
  `import AiAssistant from '@modules/Ai/resources/console/ai-assistant/AiAssistant.vue'`
  （模板使用点 `:81` `<AiAssistant />`，注释 `:80` 自称「旁挂增强，AI 关闭时零 DOM」）。
- `@modules` 的解析目标：框架自己的 `resources/js/console/vite.config.ts:24`
  `{ find: '@modules', replacement: resolve(FW_DIR, '../../../src/Modules') }`，
  而 `FW_DIR = __dirname`（`:11`，注释 `:9` 点名「this file lives at resources/js/console/vite.config.ts」）
  ⇒ 指向**框架根包的 `src/Modules`**。
- 实测那个目录**只有 `Contracts` 一个条目**（`ls vendor/dsplat/multi-tenant-saas/src/Modules`）
  —— AI 前端在拆包 `multi-tenant-saas-module-ai` 里，back 没装（`ls vendor/dsplat` 共 4 个包：
  根包 + `module-auth` + `module-infrastructure` + `module-operator`）。
- **对照取证（这条为什么算例外）**：module-loader 其余部分对缺包是**容错的**——
  走 `import.meta.glob` 发现、装了才挂路由（`resources/js/console/module-loader.ts:28-98` 共 12 条 glob，
  见缺陷 12）。只有布局里这一个静态 import 不是 glob，所以只有它会掀桌。

**② 框架自带内容里的单一客户痕迹（同源问题：可下发的框架内容写着某一个下游的名字）**

| 位置 | 行 | 内容 |
|---|---|---|
| `vendor/dsplat/multi-tenant-saas-module-auth/resources/console/ui/element-plus/views/OAuthSettings.vue` | 42 | `<el-input … placeholder="scrm_prod" />` |
| 同文件 bootstrap 版 `…/ui/bootstrap/views/OAuthSettings.vue` | 46 | `<input … placeholder="scrm_prod" />` |
| `vendor/dsplat/multi-tenant-saas/database/migrations/2025_01_02_000000_framework_core_data.php` | 1201 / 1204 / 1207 | 往 `system_settings` 插 `group='scrm'` 三条：`scrm_platform_tenant_id` / `scrm_initialized_at` / `scrm_version` |
| `vendor/dsplat/multi-tenant-saas-module-auth/Services/IdentityProviderOAuthService.php` | 26 | 类注释示例 `- idp_client_id: "scrm_prod"`（**本批新发现的一处**，dev-plan F-14 未记） |
| `vendor/dsplat/multi-tenant-saas-module-infrastructure/Http/Middleware/IdentifyTenant.php` | 258 | 注释举例用 `arthur.scrm.com` |

其中 `OAuthSettings.vue` **已被 back 的 console 自动发现并挂上 `/oauth`**，且能在 back 的构建产物里
grep 到那条串：`public/console/assets/OAuthSettings-B8rgX5Ei.js`、`OAuthSettings-DuO3UUC2.js`
（本批 `grep -rl "scrm_prod" public/console/assets/` 实测命中这两个文件）。

### 复现方式

仓库 `mynet_api`。① 的复现 = 「引用存在 + 引用目标不存在」这一对事实，一条只读命令：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && grep -n "AiAssistant" vendor/dsplat/multi-tenant-saas/resources/pages/console/ui/element-plus/layouts/ConsoleLayout.vue && ls vendor/dsplat/multi-tenant-saas/src/Modules
```

期望读到：`:96` 那条 import + `:81` 使用点，而 `ls` 只出 `Contracts`。
构建侧当前是**绿的**，因为 back 放了占位件（见下节）；真要复现失败，把
`resources/js/console/vite.config.ts:88-91` 那四行别名临时摘掉再跑
`cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api/resources/js/console && npm run build`
—— 本批**没有做这个删除动作**（它会改 back 的构建配置），只记录了不摘时的实测输出
`✓ built in 3.59s`。

② 的复现 = `grep -rn "scrm_prod" vendor/dsplat/`（表里三处）+
`grep -n "'scrm'" vendor/dsplat/multi-tenant-saas/database/migrations/2025_01_02_000000_framework_core_data.php`。

### back 现状的兜底

- `resources/js/console/stubs/AiAssistant.vue`：**零行为占位件**（模板只有 `<span v-if="false" />`，
  文件头 `:1-13` 的注释写清了来由与「裁定落地后这个文件就该删」）。
- `resources/js/console/vite.config.ts:88-91`：用**精确说明符**别名顶上
  （`find: '@modules/Ai/resources/console/ai-assistant/AiAssistant.vue'`），
  刻意排在通用 `@modules`（`:93`）之前，注释 `:86-87` 说明「不影响按目录生成的 `@modules/<Pascal>`」。
- scrm 那几处 **back 没有兜底**：视图是框架文件，改它要 fork（不划算，等框架去品牌）；
  `system_settings` 那 3 行是**数据动作**，back 不动库。
⇒ **框架把 AiAssistant 改成可选挂载后，`stubs/AiAssistant.vue` 与 `vite.config.ts:86-91` 这两处应一起删**；
裁定 G2（`dev-plan.md:529`）已定「不装 module-ai，stub 继续兑」，所以本条在 back 侧无待办。

### 要框架定的一件事

两问，back 都没自决：

- **① `AiAssistant` 的挂载方式**：**(A)** 改成可选挂载（glob 发现或有则挂、缺包不报错，
  与 module-loader 其余口径一致）；**(B)** 或在文档里明文规定「用 element-plus console 布局
  必须装 `module-ai`」—— 那 back 就得 composer 装包，连带它的表（生产禁 migration，要走
  `database/sql/`）与路由，back 不为一个悬浮按钮摊这些。
- **② 那些 `scrm` 痕迹要不要清**：`placeholder` 属框架视图文案，back 改要 fork 视图文件
  （不划算，等框架去品牌）；`system_settings` 那 3 行是**数据动作**，back 不动库，
  等 F-13 那份 DDL 的交付口径定了以后一起处理。
复核：2026-10-05，行号已对源码验证

---

## 缺陷 12 —— module-loader 的模块发现对 `resources` 这一段**大小写敏感**，失败方式是「静默不产出」

### 现象

**静默不产出**（本批最难发现的一种，与缺陷 8/10 同族但更坏）：`vite build` **全程成功、零告警**，
只是产物里没有那几页 —— 侧栏少几项、深链回落 `/dashboard`，看起来像「模块没装」而不是「路径拼法不对」。
对比同类问题另一处就有报错（视图相对深度写错时 `Could not resolve "../../api"` 直接 build 失败）：
静默是 **glob 语义**决定的，不是下游能控制的。

### 根因与取证

- **12 条 glob 里的 `resources` 全是字面量**：
  `vendor/dsplat/multi-tenant-saas/resources/js/console/module-loader.ts:28-98`，
  逐条起行 `:28, 34, 40, 46, 52, 58, 67, 73, 79, 83, 89, 95`；
  模式形如 `'/app/Modules/*/resources/console/ui/*/views/*.vue'`（`:74`）、
  `'/src/Modules/*/resources/console/routes.ts'`（`:68`）——
  **只有模块名那一段是 `*`，`resources` 是写死的小写字面量**。
  ⚠️ **数量漂移**：`dev-plan.md:485`（F-15 行）记「八条 glob」，当前是 **12 条**。
- **框架自己知道大小写这件事，但兜不住这一段**：
  `:101` 注释「Vite `import.meta.glob` keys preserve filesystem case (PascalCase on disk)」；
  `buildCaseInsensitiveMap()` 在 `:105-111`（把 glob 的 **key** 做成 lowercase→原 key 的映射），
  `resolveGlobLoader()` 在 `:117-127`（先精确命中、再按 `key.toLowerCase()` 兜）。
  ⇒ 这套 CI 机制作用在「已经进了 glob 的 key」上；目录名拼成大写 `Resources` 时 **glob 本身就不返回这个 key**，
  CI map 里自然也没有它。
  ⚠️ **行号漂移**：dev-plan 记的 `:105-127` 把 `resolveGlobLoader()` 一并算进了
  `buildCaseInsensitiveMap()`，实际后者只到 `:111`。
- **模块名派生同样按小写匹配**：`extractModuleName()` 在 `:139-148`，
  那四条正则里的 `resources` 段也是字面小写。
- **为什么这条对 Laravel 下游特别坏**：Laravel 的模块目录习惯写法是大写 `Resources`
  （放 `XxxResource.php`，命名空间 `App\Modules\X\Resources`，Linux 上 PSR-4 要求目录保持大写）；
  而 APFS/NTFS 大小写不敏感 ⇒ **一个目录只能存一种拼法**，这类下游没有两全解。
  参考项目 scrm 之所以能放 `app/Modules/*/resources/console/`，是它模块目录里没有大写 `Resources`。
- **分组标签那半（顺带复核，属缺陷 12 的同族但不是本条诉求）**：
  `MODULE_LABELS` 常量在 `:275`，本批 grep 确认表里**没有 `VPN` 键**；
  `:335` `if (!route.meta.module) route.meta.module = moduleName` 只在缺失时注入目录名；
  兜底在 `:532-533`（去连字符 + 首字母大写）⇒ back 那组的组头就是字面 `VPN`。
  这条是**裁定项（dev-plan F-2 / G-5）**，不是框架缺陷，故只在对照处记一句，不另立缺陷。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/resources/js/console`（fast-glob 是 vite 的传递依赖，这里装得到）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api/resources/js/console && node -e "
const fg=require('fast-glob');const cwd='../../..';
console.log('lowercase  app/Modules/*/resources/*.php  ->', fg.sync(['app/Modules/*/resources/*.php'],{cwd}).length);
console.log('uppercase  app/Modules/*/Resources/*.php  ->', fg.sync(['app/Modules/*/Resources/*.php'],{cwd}).length);
"
```

2026-10-05 实测：`lowercase -> 0` / `uppercase -> 8` —— 同一个目录，pattern 里那段大小写换一下
就从「一条不返回」变成「8 条命中」，**这就是 glob 段字面量的效果**（用 PHP 目录演示是因为
back 的 console 前端刻意没放在 `app/Modules` 下，见下节）。

### back 现状的兜底

**没有兜底件，只有落点约束**（这正是 dev-plan 里 back 倾向「① 文档写死」的原因）：

- back 的 console 前端全部落在**小写字面量命中**的位置
  `src/Modules/VPN/resources/console/`（`git ls-files 'src/Modules/*'` = 13 个跟踪文件，
  其中 `.vue` 8 个，本批用上面同一条 fast-glob 探针实测命中 8）。
- back 的大写 `app/Modules/VPN/Resources/` 里**只放 PHP**（API Resource），
  `git ls-files 'app/Modules/*'` 里 `/Resources/` 命中 8 条、`/resources/` 命中 0 条 ——
  两种拼法在同一个模块目录族里**并存但各管一端**，这是刻意选择，不是巧合。
⇒ 框架若选「② 代码补一层（`[Rr]esources` 变体）」，back 上面这条落点约束的注释可以放宽，
  但**没有需要删除的兜底件**（约束本身仍是好写法）。

### 要框架定的一件事

二选一的口径：

- **① 文档写死**：明确「console 前端只能放在**字面小写** `resources/console/` 下；模块旁边若已有
  大写 `Resources`（Laravel API Resource 目录），请把 console 前端放 `src/Modules/<Name>/resources/console/`」
  —— back 已按这条落（五页在 `src/Modules/`），改动为零。
- **② 代码补一层**：12 条 glob 各加一条 `Resources` 变体，并把 `extractModuleName()`（`:139-148`）
  那四条正则的 `resources` 段改成 `[Rr]esources` —— 这样 `app/Modules/<X>/Resources/console/`
  也能被发现；代价是 glob 数量翻倍、且 `MODULE_LABELS` 的键要同时容两种拼法。

back 倾向 ①（约束小、语义清楚），但**这条口径归框架定**：定了以后 back 照改注释与落点，
不自行改 vendor。
复核：2026-10-05，行号已对源码验证

---

## 缺陷 13 —— Operator 列表把**三层**状态都当过滤条件：停用、移出、待接受的人一起消失

### 现象

**全绿而无后果**：`GET /api/v1/operators` 返回 **200 且 `success: true`**，但被停用的成员、被移出本区的成员、
**以及刚被邀请还没接受的人**，三类都不在响应里。管理页因此没有恢复入口，也没有「他还在库里、只是本区断了」
这一眼判据；而「移出」（只断本区）与「停用账户」（影响他所有租户）两种语义在列表里长得一模一样 —— 更坏的是
管理员**邀请完一个人在列表里看不到他**，只能重复点邀请，第二次撞 `Operator already belongs to this tenant.`（422）。

### 根因与取证

- **两层过滤在同一句里**：`vendor/dsplat/multi-tenant-saas-module-operator/Services/OperatorService.php:237-244`
  的 `listByTenant()` —— `whereHas('tenants', …)` 内判 `operator_tenants.is_active = true`（`:241`），
  **外层又判 `operator.is_active = true`**（`:243`），中间没有 `withTrashed`/`include` 之类的口子。
- **两个写操作都只改状态、不删行** ⇒ 被过滤掉的那些行**仍在库里**：
  `removeFromTenant()`（`:182`，pivot 置 `is_active=false` 在 `:199`；`:178-180` 的注释明文
  「Operator 主体记录不被删除，仅断开与团队的关联」，顺带 `:194-197` 撤销其全部 token）、
  `toggleStatus()`（`:260-266`，翻的是**账户级**那列）。
- **第三层是本批新取证的一档（dev-plan F-16 只记了两层）**：`invite()` 新建的 pivot 就是
  `is_active=false`（`:79-85`，`:83` 那行；`:84` 写 `invited_at=now()`，`accepted_at` 不写）⇒
  **待接受**的人按定义就被 `:241` 挡掉；新建账户时 operator 本体的 `is_active` 也是 false（`:58`），
  双重不落列表。
- **反向同一形状还有一处**：`listTenants()`（`:303-306`）在 pivot 上判 `is_active = true`（`:306`）
  ⇒ 「这个 Operator 管着哪些区」同样只报活跃的，**两侧都看不见已断开的关联**。
- **`pending` 与 `disabled` 不可区分**：列表只剩一个布尔，而界面态需要三个来源
  （`operator.is_active` / `operator_tenants.is_active` / `operator_tenants.accepted_at`）才分得开 ——
  这正是 back 要自己摊的原因（见下节）。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读（一条命令看全三层）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && sed -n '237,244p;79,85p;306p' vendor/dsplat/multi-tenant-saas-module-operator/Services/OperatorService.php
```

期望读到的三对事实：`:241` 与 `:243` 各一条 `is_active` 过滤、`:83` 建 pivot 时就写 `is_active=false`、
`:306` 在 pivot 上再过滤一次。
⚠️ 口径：本批**没有做数据实测**（造一条停用行要写库）；上面是代码级取证。
现网/开发库存量可只读核对：
`SELECT COUNT(*) FROM operator_tenants WHERE tenant_id = <区> AND is_active = 0;` —— 大于 0 即列表正在吞行。

### back 现状的兜底

`app/Modules/VPN/Controllers/Console/V1/MemberController.php` —— **只补读模型，不复制第二份写面**
（类注释 `:20-36` 逐条点名本缺陷）：

- 以 **pivot 为骨架**全量摊（`:59-64`：`OperatorTenant::where('tenant_id', …)->with('operator')`），
  所以三类被框架吞掉的行都在。
- **两层状态分开回显**：`operator_is_active`（`:96`）与 `membership_is_active`（`:97`），
  再合成界面态 `pending | removed | disabled | active`（`stateOf()` `:111-122`）。
  判定顺序是刻意的：`accepted_at IS NULL` 必须**早于** `! pivot.is_active` 判（注释 `:106-110`）——
  否则没接受过邀请的人会被显示成「已停用」，管理员会去催一个从未进来过的人。
- 写操作仍一律打框架端点（`api/v1/operators/*`），back 不加恢复端点。

⇒ **框架改成「全量 + 状态位」后，`MemberController::stateOf()` 的三态合成仍要留**（界面态是 back 的语义），
但 `:59-64` 那条「绕开框架列表、自己查 pivot」的取证理由应改成「按框架状态位过滤」。
回归锁：`php vendor/bin/phpunit --filter ConsoleTenantMemberTest` → 8 例，第 1 条就锁「七行全摊」
（含框架列表会吞掉的 `disabled` / `removed` 两档）。

复核：2026-10-05，行号已对源码验证

### 要框架定的一件事

两问，back 都没自决：

- **① `GET /api/v1/operators` 是不是该回「全量 + 状态位」**（或加 `?state=` / `?include_inactive=1`），
  让下游能做出恢复入口 —— 现在这三个字面条件把三类人都吞了，下游唯一正确的做法就是**不走这个端点**
  （back 已经在走自己查 pivot 那条路，这对框架的读面是净流失）。
- **② 「关联失效」与「账户停用」这层语义差别要不要写进 `resources/kb/`** —— 目前只活在代码注释里
  （`OperatorService.php:178-180`），下游只能靠读 vendor 猜。

---

## 缺陷 14 —— 列表**不带 pivot**（角色不在响应里），可分配角色的枚举又**没有读取端点**

### 现象

**全绿而数据不在**：`GET /api/v1/operators` 的每个条目里**根本没有角色这一列** —— 而
`PUT /api/v1/operators/{id}/role` 写的恰恰就是那一列（pivot `operator_tenants.role`）；
读面与写面形状不一致。第二半更棘手：`role` 的**合法取值**只存在于一个 `private` 方法里，
全框架没有任何端点把它摊出来，于是下游的下拉只能自己查库 —— 页面与后端校验各说一套。

### 根因与取证

- **返回的是 `Operator` 模型集合**：`.../Services/OperatorService.php:238` `listByTenant(): Collection` →
  `:239` `Operator::whereHas('tenants', …)` → `:244` `->get()`；既没有 `with('tenants')` 也没有 `->withPivot()`，
  而 `role` 挂在 `operator_tenants` 上（框架迁移建表即如此，back 测试 schema 同形：
  `tests/Schema/OperatorModule.php:48-60`）。写侧则明确按 pivot 走：
  `updateRole()`（`:211-228`）先 `OperatorTenant::where('operator_id')->where('tenant_id')->firstOrFail()`
  （`:213-216`）再 `$ot->update(['role' => $role])`（`:217`）⇒ **读不到、但写得进**。
- **枚举被锁在 private**：`.../Http/Controllers/OperatorController.php:41-46` 的 `getValidRoles()` 是
  `private function`，取值 `DB::table('roles')->pluck('name')->toArray()`（`:44`），
  缓存键 `ROLES_CACHE_KEY`（`:16`）+ TTL 3600（`:18`）。消费点只有两处校验
  （`invite()` 的 `in:` 在 `:55`、`updateRole()` 的 `in:` 在 `:120`），**没有任何路由把它摊成读端点**。
- ⚠️ **行号漂移**：`dev-plan.md:487`（F-17 行）记的 `OperatorController.php:41-46` 经本批复核**正确**，
  但要补一句 ——`in:` 的两个消费点是 `:55` 与 `:120`（dev-plan 未记），这正是「同源」论证的落点。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读两条：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && grep -rn "getValidRoles" vendor/dsplat/ ; grep -n "OperatorController" vendor/dsplat/multi-tenant-saas-module-operator/Routes/*.php | grep -i role
```

期望读到：`getValidRoles` 全 vendor **3 条命中**（定义 `:41` + 校验 `:55`、`:120`）⇒ 没有第四个读取方；
路由侧只有 `PUT /{operatorId}/role`（写），**没有 `GET …/roles`**。

### back 现状的兜底

`app/Modules/VPN/Controllers/Console/V1/MemberController.php` 两处，都是「补读面、不动写面」：

1. **角色取自 pivot 并回显**：`:94` `'role' => $row->role`（骨架就是 `:59-64` 那条 pivot 查询）。
2. **可分配角色与框架校验同源**：`assignableRoles()`（`:127-131`）
   直接 `DB::table('roles')->distinct()->pluck('name')` —— **同一张表、同一列、back 侧不加白名单**，
   理由写在类注释 `:38-42`：下拉藏起一部分选项不会让另外那些变得不能用，只会让页面和后端校验各说一套。
   ⚠️ **一条 back 侧仍未消除的偏差，记在这里免得被当成已解决**：框架那条读带 1h 缓存（`OperatorController.php:16-18`），
   back 每请求直查 ⇒ **新建角色后的最长 1 小时内，back 的下拉已列出、框架校验仍拒**，
   而 back 没有任何失效入口（那是框架的缓存键）。

⇒ **框架补出 `GET …/roles` 后，`assignableRoles()`（`:127-131`）应改成打那个端点**（同源这件事就不再靠
「碰巧读同一张表」维持）；`role` 的回显在框架列表带 pivot 之前要留着。

复核：2026-10-05，行号已对源码验证

### 要框架定的一件事

两件都是**补读面**，不动写面语义：

- **① 列表带 pivot**（角色 + 关联状态），并分页（现在 `->get()` 无界）。
- **② 一条 `GET /api/v1/operators/roles`** —— 或直接返回 role 资源（含权限集），
  让下游的下拉与后端的 `in:` 校验取同一个源、共用同一份缓存失效。

---

## 缺陷 15 —— Operator 写面**没有自我隔离**，也没有「本区最后一个 `tenant_admin`」保护；顺带**跨租户也照写**

### 现象

**全绿而该拒的没拒**（本类里最危险的一种，因为它返回的是成功）：

- `PUT /api/v1/operators/{id}/role` 把本区**最后一个** `tenant_admin` 降成 `member` ⇒ **200 `role_updated`**，
  此后**整区再没有人能进 console**（back 的 console 面唯一门禁角色就是 `tenant_admin`），
  而恢复路径在框架侧也不可见 —— 他从列表里消失了（缺陷 13）。
- `POST /api/v1/operators/{id}/toggle-status` 可以让**登录者自己停用** ⇒ 200，下一次请求即 401。
- `DELETE /api/v1/operators/{id}` 可以**自己移除自己** ⇒ 200。
- **本批新取证的一层（dev-plan F-18 只记了自我隔离那半）**：这四个写/读面**都不按租户收口** ⇒
  A 区的 `tenant_admin` 可以改 / 停用 / 移出 / 读到 **B 区成员的记录**（`operator_id` 是全局 ID，
  从路径参数来）。同一条根因：**判据里没有出现「调用者」这个变量**。

### 根因与取证

- **三个写方法都没有目标判据**（`.../Services/OperatorService.php`）：
  `removeFromTenant()`（`:182-206`）只判关联存在 —— `if (! $ot) { return; }` 在 `:189-191`
  （⚠️ **行号漂移**：`dev-plan.md:488` 记的是 `:188-191`，实际 `if` 起在 `:189`）；
  `updateRole()`（`:211-228`）拿到 pivot 就 `update(['role' => $role])`（`:217`）；
  `toggleStatus()`（`:260-266`）一行 `update(['is_active' => ! $operator->is_active])`（`:263`）。
  **没有任何一处比较 `operator_id === 当前登录者`，也没有任何一处数「本区还有几个 tenant_admin」。**
- **跨租户那半的取证**：`show()`（`OperatorController.php:158-166`）→ `getOperator()`
  （`OperatorService.php:247-250`）= `findOrFail`，**无租户条件**；
  `update()`（`OperatorController.php:171-191`）→ `updateOperator()`（`OperatorService.php:252-257`）
  同样 `findOrFail` + `update($data)`，**无租户条件**；`toggleStatus` / `resendInvite` 同形。
  而 `remove`/`updateRole` 的 `$tenantId` 取自 `TenantContext::getId()`
  （`OperatorController.php:123`、`:142`）—— 有租户条件的这两条反而**更安全**，
  形状不统一本身就是证据。
- **路由形状按「账户」不按「关联」**：`.../Routes/tenant.php:15-25` 全部是 `/operators/{operatorId}…`，
  中间件只有 `rbac.permission:member.{view,create,update,delete}`（`:16-24`）——
  **那是「有没有这个权限位」，不是「这条记录是不是你的」**（权限 ≠ 属主，正是本项目 A1 裁定的那条区分）。
- **为什么在本项目特别疼**：back 的 console 唯一门禁角色是 `tenant_admin`
  （`CheckPermission::checkConsoleAccess` 要求 `role_id` 指向 `name='tenant_admin'` 的角色行），
  所以这不是「体验瑕疵」而是**一次误点即整区后台不可达**；而 `routes/api.php:36-42` 的 back 侧闸门
  只挡「谁能进 console」，进来之后的这五条框架写端点全在框架自己的闸下。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读（把「判据里没有调用者」这件事摊出来看）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && grep -n "auth()->user\|request->user\|\\$this->operatorId\|operator_id ===" vendor/dsplat/multi-tenant-saas-module-operator/Services/OperatorService.php
```

期望读到：`OperatorService.php` **零命中** —— 服务层从头到尾没有「当前登录 Operator」这个概念，
所以自我隔离与最后一个 admin 保护**在结构上无法存在**。
路由侧对照：`grep -n "toggle-status\|resend-invite\|/role" vendor/dsplat/multi-tenant-saas-module-operator/Routes/tenant.php`
⇒ `:20`、`:21`、`:22` 三条都只有 `{operatorId}`。
⚠️ 口径：真造「最后一个 admin 被降权后整区不可达」是**写数据 + 改角色**动作，本批**未做**；
上面是代码级 + 路由级取证。

### back 现状的兜底

**只有一道前端闸门，back 明说它不是服务端防线**（裁定 G3 已定「只上报，不代做」）：

- `Console/V1/MemberController.php:66` 取当前登录 Operator 的 `operator_id`、`:101` 逐行回显 `is_self`；
  `src/Modules/VPN/resources/console/views/TenantMembers.vue` 据此**禁用对自己的**角色下拉 + 停用 + 移出，
  表尾注释写明「框架服务端没有这道自我隔离」。类注释 `:44-47` 把这条的性质写死在 back 侧源码里。
- **跨租户那半 back 没有兜底**：这五条都是框架端点，back 不 fork vendor、也不在自己这层再包一份写面
  （包了就违反「同一判据两处各写一遍 = 缺陷」的矩阵 R6）。
- 进 console 的总闸（`routes/api.php:36-42`：`domain.console` + `tenant.permission:tenant_admin`）**管不到这一层** ——
  它判的是「能不能进后台」，不是「这条 operator 记录动不动得」。

⇒ **框架补出服务端判据后，back 的 `is_self` 回显与前端禁用仍保留**（防误点独立有价值），
但 `MemberController` 类注释 `:44-47` 那段「框架没有这道闸门」的登记应删。

复核：2026-10-05，行号已对源码验证

### 要框架定的一件事

请在**服务端**补判据（back 不代做，也不在 vendor 里打补丁）；取向可选，也可以都要：

- **① 自我隔离**：拒绝 `operator_id === 调用者` 的自我停用 / 自我降权 / 自我移除。
- **② 最后一个 admin 保护**：本区只剩一个 `tenant_admin` 时拒降权、拒移出。
- **③（本批追加的一问）跨租户写面收口**：`show` / `update` / `toggle-status` / `resend-invite` 四条
  要不要按 `operator_tenants` 加租户条件 —— 现在 `remove`/`updateRole` 有、另四条没有，
  **同一张表上两种归属口径**本身就是待修的形状。

---

## 缺陷 16 —— 框架模型日期序列化成**无偏移**的 `Y-m-d H:i:s`：与 back 自有端点同页混用差 8 小时

### 现象

**全绿而显示错**：同一组 console 页面里，back 自有端点的时间显示对、框架模型的时间**早 8 小时**，
而错的那批看起来完全合理（`2026-10-04 09:56:23` 这种串没有任何东西提示它不是本地墙钟）。
2026-10-04 #37 浏览器实测：「租户档案」创建时间显示 **17:20**（back，对），
「邀请码」失效时间显示 **09:56**（框架，真实本地时刻 **17:56**）。没有错误码、没有告警，
只有和同页另一批 ISO 值并排比才露馅 —— 这也是为什么它排在本批「全绿而无后果」那一类里。

### 根因与取证

- **序列化点**：`vendor/dsplat/multi-tenant-saas/src/Concerns/SerializesFriendlyDates.php:19-22` ——
  `serializeDate(DateTimeInterface $date): string`（`:19`）统一 `return $date->format('Y-m-d H:i:s')`（`:21`），
  **不带偏移、不带 `Z`**。它替代的是 Laravel 默认的 `2026-08-17T07:42:14.000000Z`（注释 `:10-11` 自陈动机是
  「保证全项目拿到人类可读格式」）。
- **框架自己知道这里有个坑，但把处置交给了下游**：注释 `:14-15` 明写
  「前端若需将该字符串转 Date 对象，Safari 不认空格分隔格式，应先 `replace(' ', 'T')` 再 `new Date()`」。
  ⇒ **而那一步做完，字符串就被按浏览器本地时区解释了** —— parse 问题解决得越干净，偏移归属错得越隐蔽。
- **使用面是全模型**：本批 `grep -rl "SerializesFriendlyDates" vendor/dsplat/` = **34 个文件**，
  含 `module-operator/Models/Operator.php:19`、`OperatorTenant.php:24`、
  `module-auth/Models/InviteCode.php:26`、`module-infrastructure/Models/Tenant.php:27` 等 ⇒
  不是个别视图的问题，是框架 JSON 面的统一形状。
- **应用时区实测为 UTC**：`config('app.timezone')` 运行时取值 **UTC**；本仓 `config/*.php` 里
  **grep `timezone` 命中 0**（back 无 `config/app.php` 覆盖，Laravel 11 slim skeleton）。
  于是同一瞬间两种显示：back `toIso8601String()` → `2026-10-04T10:09:59+00:00` →
  前端 `toLocaleString('zh-CN')` = **18:09**（对）；框架 `2026-10-04 10:09:59` → 前端 = **10:09**（早 8 小时）。
- **back 不共享这个形状**：本批复核 `grep -rln "SerializesFriendlyDates" app/` = **0 命中** ⇒
  back 自有读模型全部显式 `toIso8601String()`（例如 `Console/V1/MemberController.php:98-100`、`:76`）——
  也就是说**这个差是「框架面 vs back 面」的差，任何把两类端点放进同一页的下游都会撞**。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读（不写任何行、不改配置）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && php artisan tinker --execute='
echo json_encode(["tz"=>config("app.timezone"), "wall"=>date_default_timezone_get(), "iso"=>now()->toIso8601String(), "fw_style"=>now()->format("Y-m-d H:i:s"), "safari_note"=>"see SerializesFriendlyDates.php:14"]);'
```

期望读到：`tz` = `UTC`，`iso` 带 `+00:00` 而 `fw_style` **不带任何偏移标记**；
两者在浏览器里 `new Date()` 之后的本地显示相差访客所在时区的偏移。
构建面无需触碰；对照读源码：`sed -n '19,22p' vendor/dsplat/multi-tenant-saas/src/Concerns/SerializesFriendlyDates.php`。

### back 现状的兜底

三道，**没有一道能修「归属」，都只把症状压住**（这条的根因判据必须框架定，back 不自定）：

1. `mynet_gui/src/utils/format.ts` 的 `toDate()` 收口：含 `T` 直接 parse，否则先 `replace(' ', 'T')` ——
   注释里就写明它**只解决能不能 parse，不解决偏移归属**。
2. back 自有端点一律显式 `toIso8601String()`（`Console/V1/MemberController.php:98-100` 等），
   保证 back 面内部自洽；框架端点原样透出（back 不改 vendor 的序列化）。
3. **裁定 G4**（`dev-plan.md:531`）已定生产 `app.timezone` = **Asia/Shanghai**，随 G-6 部署落：
   两个来源都按东八区墙钟解释 ⇒ **本机访问时 8 小时差消失**。⚠️ 但这只是把两边拉回同一墙钟，
   **不改变「无偏移」这个形状** —— 跨时区访客仍会把框架那批串按自己本地时区解释而读错，
   且部署清单必须写死「框架端点的时间字符串按应用时区解释」，否则同页混用又会显出差。

⇒ 框架若选 ①（改回带偏移的 ISO8601），`format.ts` 的 `replace(' ','T')` 分支可以删（但留着无害），
   `toDate()` 的收口价值上升为唯一 parse 入口；G4 的部署口径注记同时作废。

复核：2026-10-05，行号已对源码验证（`grep -rl` 计数 34 / `grep -rln app/` 计数 0 为本批实测）

### 要框架定的一件事

请定一条口径（back 不自定，因为改的是**所有下游都在读**的那一列形状）：

- **① 推荐**：`serializeDate()` 改回**带偏移的 ISO8601** —— 前端一律能正确还原瞬间，
  `Y-m-d H:i:s` 的人类可读诉求可以在视图层解决。
- **②** 或在 `resources/kb/` 明文写死「**框架模型日期 = 应用时区墙钟、无偏移**」，
  并要求 `app.timezone` 与部署时区一致 —— 那下游就都得按「不做时区换算」处理。

**顺带一问（同一条的部署侧）**：`app.timezone` 保持 UTC 是有意取向还是没配过？
back 生产时区已按裁定 G4 定为 Asia/Shanghai，但这条最好由框架确认「默认 UTC + 无偏移序列化」不是配套设计。

---

## 缺陷 17 —— 框架自带的 console 前端源在下游 tsconfig 下带 16 条类型错 ⇒ **退出码设不了闸门**

### 现象

不是静默 —— `tsc` 确实报，问题是**报的东西没法用作闸门**：下游把 vendor 与自有源码一起 include 进
typecheck，输出里自有源码 **0 错**、框架源 **16 错**，退出码恒非零 ⇒ 「类型检查绿」这条红线永远做不到，
只能改成按路径筛输出（筛法本身又是脆弱的 grep）。

### 根因与取证

**① 缺 devDeps 类型（13 条，全是 TS2307 + 3 条 TS7006）—— 属「下游装的是子集」**

- `vendor/dsplat/multi-tenant-saas/resources/js/console/vite.config.ts:1,2,5,6,7` 取
  `vite` / `@vitejs/plugin-vue` / `unplugin-auto-import/vite` / `unplugin-vue-components/vite` /
  `unplugin-vue-components/resolvers`；`vitest.config.ts:1` 与 `tests/{guards,user-store}.test.ts:1` 取 `vitest`；
  `main.ts:7` 取 `../../pages/console/App.vue`、`:53` 取 `element-plus/es/locale/lang/zh-cn`、
  `:79` 取 `bootstrap/dist/js/bootstrap.bundle.min.js`（装了但那条是 `.js` 无类型）。
- 这些包在**框架自己的** `package.json` 里齐；下游 `mynet_api/resources/js/console/package.json` 装的是**子集**（无 vitest）。
- 另有 3 条 TS7006（隐式 any）在 `vite.config.ts:72-73`。

**② 两处真类型错（与依赖无关，是类型本身不成立）**

| 位置 | 实测行号 | 事实 |
|---|---|---|
| `vendor/dsplat/multi-tenant-saas/resources/js/ui-core/adapters/index.ts` | **`:46-48`**（方法体 `:46`，`dark` 分支 `:47`，空对象分支 `:48`） | `getThemeVariables(mode)` 的返回联合里那个 `{ '--bs-body-bg'?: undefined }` 分支不满足 `Record<string, string>` |
| `.../resources/js/ui-core/theme-manager.ts` | **`:222`** | `Object.entries(this.config.value.customVariables).forEach(([key, value]) => root.style.setProperty(key, value))` —— `value` 是 `unknown` 直接喂给 `string \| null` 形参 |

⚠️ **行号漂移**：`dev-plan.md:490`（F-20 行）记 `adapters/index.ts:46`，本批复核**那条不成立的分支在 `:47`**
（`:46` 是 `getThemeVariables(mode) {`）。`theme-manager.ts:222` 与 dev-plan 一致。
同类还有 `registry.ts:7` 取 `vue` 失败 —— 属 ① 类不是 ② 类，dev-plan 已如此归类。

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/resources/js/console`：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api/resources/js/console && npx tsc -p tsconfig.json --noEmit 2>&1 | grep -cE "error TS"
# 再看自有源码那部分是否为空
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api/resources/js/console && npx tsc -p tsconfig.json --noEmit 2>&1 | grep -E "src/Modules|app/Modules"
```

**计数 16 沿用 2026-10-04 的实测结果**（本批只复核了 ② 两处的 `file:line`，未重跑全量 tsc —— 那条要装依赖、耗时长）；
`include` 覆盖 vendor 与 `src/Modules/**` 这一形状未变。

### back 现状的兜底

**闸门按路径筛，退出码不作判据**（不改 vendor 一行、不 fork）：

- `make check` 里的类型检查用上面第二条命令的形状 —— `grep -E "src/Modules|app/Modules"` 为空即绿。
  这条筛法依赖 tsconfig 的 include 形状，**框架侧若调整目录结构，back 的筛法要跟着改**（已登记为脆弱点）。
- ⚠️ **一条仍然敞着的盲区，本批只登记不修**：`vue-tsc` 未装 ⇒ `.vue` 的 `<script>` 块**不在检查范围外**，
  那一段仍靠 `vite build` + 浏览器实测兜（本轮的页面级实测覆盖）。同族盲区在
  `mynet_gui` 的验证工具上另有五条登记（项目记忆），两处不是同一套工具。

⇒ 框架若保证「源可被下游 include 进 typecheck」，back 这条 grep 筛法可以换成退出码闸门，
  届时要删的是 back 自己的 `make check` 里那段 grep（不在本文档维护）。

复核：2026-10-05，② 两处行号已对源码验证；16 这条计数为 2026-10-04 实测沿用

### 要框架定的一件事

两问：

- **① 框架自带的前端源要不要保证「可被下游 include 进 typecheck」？** 要的话 ② 那两类得在框架仓修 ——
  都很小：联合里去掉 `undefined` 分支 / 把 `customVariables` 的 value 收口一次 `String()` 或加断言。
- **② 若 ① 长期成立**，请在文档里给出**下游的最小依赖清单**（含 `vitest` 是不是必须装、
  `.vue` 类型要不要 `vue-tsc`）—— 现在下游只能自己猜哪些 TS2307 是「我没装」而不是「框架写坏了」。

---

## 缺陷 18 —— 「待接受的邀请」在多租户态下语义不一致：接受一次**激活全部**关联，重发**只认账户**

### 现象

**全绿而无后果**，而且是两个方向都错：

- **过头**：一个 Operator 同时挂着 A、B 两区的待接受关联时，点**A 那封**邮件里的链接
  ⇒ 200 `account_activated`，但 **B 的关联也被一起激活**（`accepted_at` 写上、`is_active=true`）——
  B 从未被接受过，却已经是活跃成员并可登录进 B 区后台。
- **不够**：**已经激活**的账户被邀请进第二个区时，那条新的 pending 关联**无法重发邀请** ——
  管理员点「重发」必 422 `Operator is already active.`；而它也不属于任何可登录路径（接受只认 token，
  token 已经在上一次激活时被清空），于是**这条 pending 是死记录**：既激活不了，也删不掉（框架无删除端点）。
- **附带一层不确定性（本批新取证）**：重发邮件的收件目标取 `whereNull('accepted_at')->first()`，
  **没有租户条件、也没有排序** ⇒ 多条 pending 时**发哪一封由数据库返回顺序决定**。

### 根因与取证

- **pending 的构造**：`invite()`（`.../Services/OperatorService.php:46-100`）建的 pivot 是
  `is_active=false` + `invited_at=now()` + **`accepted_at` 不写**（`:79-85`，其中 `is_active` 在 `:83`）；
  同区重复邀请抛 `'Operator already belongs to this tenant.'`（`:75`）⇒ 「同一个人在两个区各一条 pending」
  是**框架自己允许并鼓励**的状态（第二个区是新关联，不撞这条）。
- **接受时不限租户**：`acceptInvite()`（`:106-140`）的判据只有 `token`
  （`:110` `Operator::where('invite_token', $token)->first()`）+ 过期判定（`:114`），
  而激活范围是（`:126-131`）
  `OperatorTenant::where('operator_id', $operator->operator_id)->whereNull('accepted_at')->update(['is_active' => true, 'accepted_at' => now()])`
  —— **按 `operator_id` 全量更新，没有任何 `tenant_id` 条件**。
  根因在数据形状：**token 存在 `operators` 行上**（`:59` 新建时、`:64` 刷新时），
  一个账户只有一个 token ⇒ 它**天然带不了「这封邀请属于哪个区」**。
- **重发只认账户**：`resendInvite()`（`:271-299`）第一道闸
  `if ($operator->is_active) throw new \DomainException('Operator is already active.')`（`:274-276`）；
  邮件目标 `OperatorTenant::where('operator_id', $operatorId)->whereNull('accepted_at')->first()`
  （`:286-288`，无租户条件、无排序），随后 `:290-292` 才按那条的 `tenant_id` 发信。
- **路由也没有租户参数**：`Routes/tenant.php:22` / `api.php:31` / `admin.php:22` 全是
  `POST /operators/{operatorId}/resend-invite` —— 与缺陷 15 同源：
  **框架 Operator 写面按「账户」建模，而 pending/active 的状态按「关联」存储。**

### 复现方式

仓库 `mynet_api`、目录 `mynet_api/`，只读（把三处判据一次摊出来）：

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && sed -n '110,114p;126,131p;274,276p;286,288p' vendor/dsplat/multi-tenant-saas-module-operator/Services/OperatorService.php
```

期望读到的四对事实：接受只按 `invite_token` 找人（`:110`）、激活更新里**没有** `where('tenant_id', …)`（`:126-131`）、
重发第一道闸判的是**账户级** `is_active`（`:274-276`）、邮件目标那条 `first()` 无租户无排序（`:286-288`）。
⚠️ 口径：本批**未做触发实测** —— 「造两区 pending 再点 A 的链接」要写 `operators` / `operator_tenants` 两张表，
属数据动作，未请示就不动；上面是代码级取证。

### back 现状的兜底

**没有兜底件，只有如实回显**（裁定 G3「只上报不代做」+ 不改 vendor、不加假成功）：

- `Console/V1/MemberController.php:113-115` 把 `accepted_at IS NULL` 判成 `pending`（而**不是**用
  `is_active=false` —— 类注释 `:32-36` 写明后者还包含停用/移出，两者混起来会把停用的成员显示成「等他点邮件」）。
- `TenantMembers.vue` 对 `pending` 条目给「重发邀请」按钮：对**全新账户**真实可用（这是主用途），
  对**已激活账户的第二条 pending** 注定失败 ⇒ 服务端 `DomainException` 原文回显成 422
  （`OperatorController.php:221-226`），**back 不自造绕行**（不预筛 `is_active` 把按钮藏掉，那等于替框架掩盖这条）。
- 多区归属这一取向本身已被裁定 **D1「v1 不做多区自助」**收口（`dev-plan.md:520`）⇒
  back 侧只按「一个账户在本区一条关联」运作，所以过头那半在 mynet 当前形态下**不可达**，
  而**不够**那半可达（同一个 Operator 被两个区邀请是 console 运营会做的事）。

⇒ 框架若把 `acceptInvite()` 收窄到本次 token 所属关联、`resendInvite()` 改成 `operator + tenant` 粒度，
  `MemberController` 的 `stateOf()`（`:111-122`）判定顺序注释要跟着改，代码形状不变。

复核：2026-10-05，行号已对源码验证

### 要框架定的一件事

三问，back 都不自决：

- **① `acceptInvite()` 是否收窄到本次 token 所属的那一条关联？** 要收窄就得先定
  **token 与租户的绑定放哪儿** —— 现在 token 存在 `operators` 行上（`:59`/`:64`），一账户一 token，
  形状上带不了租户（这是缺陷本体，不是实现疏忽）。
- **② `resendInvite()` 要不要改成 `operator + tenant` 粒度**（连带路由参数），
  并允许对已激活账户只重发**某一区**的邀请？顺带那条 `first()` 至少要加租户条件与排序。
- **③ 多区归属取向**：与 F-11 / 裁定 D1 是同一件事（「一个终端配多个网络区」）。
  mynet 已裁 v1 不做多区自助，但**框架侧的 Operator 关联本来就是多区的**，
  所以这两条形状问题不该由单个下游的取向决定。

**back 侧本轮不动框架、不加假成功**：按钮保留（对全新账户真实可用），失败原文回显。

---

## 对齐请求 1 —— 属主判定（Owner Guard）**方法签名对齐** + back 侧接线计划

### 这条为什么不在「缺陷」序列里

**这不是新诉求。** 裁定 A1（`dev-plan.md:510`）当时把「框架出属主/归属判定方法、下层调用、业务继承 + 扩容」
记成对框架的**能力诉求**；2026-10-04 用户告知**框架侧已经在实现**同名方法 ⇒
本条按裁定 G1 的更新口径降级成**签名对齐 + 接线计划**（`dev-plan.md:528`「⚠️ 2026-10-04 更新（第 13 条降级）」），
**不按「请求框架新增能力」提**。

### 现状核对（2026-10-05，只读）

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api && grep -rln "OwnerGuard\|ownsResource\|isOwnerOf" vendor/dsplat/
```

实测 **0 命中** ⇒ 方法**尚未下发到 vendor**（可能还在框架仓未发布，本机够不到）。
所以 back 维持现状：判据仍在自己的 `OwnerGuard`，**7 处零散比对一处未动**（任务 #54 的核对结论）。

### back 现在的签名（逐字，框架同名方法请按这个形状对齐）

```php
// app/Modules/VPN/Domain/Guards/OwnerGuard.php
public const OWNER_COLUMN = 'user_id';                                                            // :40
public static function owns(Model $resource, ?int $userId, string $ownerColumn = self::OWNER_COLUMN): bool;  // :47
public static function authorize(string $ability, Model $resource): void;                         // :69
```

三条设计约束请框架一并保住（**丢了任何一条 back 就得保留第二套判据 = 矩阵 R6**）：

1. **归属列可参数化**要保留：`node_route_requests` 有 `source_user_id` / `target_user_id` 两列，
   同一张表要按不同列判两次 —— 写死 `user_id` 就接不住。
2. **fail-closed 两条**要一致：无认证身份拒（`OwnerGuard.php:49-51`）、归属列为 NULL/0 拒（`:53-55`）。
   后者是裁定 A5「7 列一步收 NOT NULL + DBA 回填」的配套判据（`dev-plan.md:514`），
   **框架若把 NULL 当放行，回填口径就被打穿**。
3. **身份只从已认证主体取**，不接受「以参数定身份」（矩阵 R4）—— 这正是 back 那批
   `/users/{id}` 端点被下架（裁定 A4）的根因，`authorize()` 的实现里写死了这条（`:61-63` 的注释与 `:71-74`）。

另外两条口径：拒绝要翻成 **`DomainException::forbidden()`**（back 已登记错误码 `DOMAIN_FORBIDDEN`，
`spec/v1-error-codes.md` §2.1），而不是 Laravel 默认的 `AuthorizationException`（那会多出一套码）；
**绝不掺入 tenant 管理员语义** —— 用户面不表达 tenant 权限是 10-04 的既有裁定，
框架若把 `canAccessTenant()` 之类塞进同名方法，back **不会接**（那是另一种身份模型）。

### back 侧接线计划（框架方法到位后**一次接完**，改动面就在这几处）

| # | 停等的调用点（2026-10-05 逐个复核行号） | 所属方法 | 现状出口 |
|---|---|---|---|
| 1 | `app/Modules/VPN/Domain/Services/NodeDomainService.php:299` | `getNodeInfo()` `:291` | 已改 `DomainException`（`69e0c2d`），**判据仍是裸比** |
| 2 | `NodeDomainService.php:860` | `getNodeNetworkConfig()` `:851` | 返 `null`（不是 500 出口，一行未动） |
| 3 | `NodeDomainService.php:933` | `setNodeNetworkConfig()` `:924` | 已改 `DomainException` |
| 4 | `NodeDomainService.php:1276` | `getNodeIndexServices()` `:1266` | 已改 `DomainException` |
| 5 | `NodeKeyDomainService.php:1165` | `validateNodeOwnership()` `:1153`（**private**） | 已用 `NodeException::accessDenied` ⇒ 接线只改这一个私有方法 |
| 6 | `RouteRequestDomainService.php:70` | `sendRouteRequest()` `:49` | 已用 `DomainException::forbidden` |
| 7 | `app/Modules/Social/Domain/Services/SocialDomainService.php:538` | `removeSocialConnection()` `:531` | 已改 `DomainException` ⚠️ **行号漂移**：dev-plan A1 行记 `:509` / `:510`，D2 在其上方插入 `establishFriendship()`（`:227`）后落在 **`:538`** |

**接线动作**（一次做完，不分批）：这 7 处改为调用框架同名方法（或经 `OwnerGuard` 委托到它），
`OwnerGuard` 退成**适配层**；已接线的 4 处写面（`DELETE /nodes/{id}`、`POST /nodes/{id}/operator`、
`DELETE /route/{id}`、`POST /route/bulk/status`）与已收敛的 6 处
（`IpPoolDomainService:390,420`、`TenantScopedServiceService:263,343,379,440`）跟着换实现点，
**控制器与 22 个调用方一行不动**（这正是 A1 要求留适配层的原因）。
回归锁：`OwnerGuardSurfaceTest`（11 例 / 37 断言）+ `DomainExceptionHttpStatusTest`
—— 接线后必须**逐处变异实测**（把框架调用摘掉即转红），否则「接了但没生效」会像缺陷 8 那样静默。

**刻意不收的两处**（不是遗漏，别「顺手」一起改）：
`NodeDomainService.php:835` 比的是「这个 `User` 自己是不是这个人」（身份不是属主）、
`Console/V1/ServiceController.php:168` 的 `(int) $service->user_id === 0` 是**系统服务哨兵语义**（0 不是某个人的 id）。

**半成品别照做**：`/tmp/a1-owner-guard-convergence.patch`（209 行）是裁定 A1 那轮停在半路的批次 ——
`/tmp` 会被清，且框架版本大概率让那份改名直接作废 ⇒ **不要照着它继续做，按框架 API 重新接**。

复核：2026-10-05，7 处行号 + `OwnerGuard` 签名已对源码验证；vendor 侧「方法尚未下发」为本批实测 0 命中

---

## 不列入本清单的条目（免得框架看到两份真相）

| `dev-plan` 条目 | 为什么不在本清单 |
|---|---|
| **F-1** | 已落（`mynet_api@912ab7e` 补 `rbac.permission` 别名）—— 本来就只是 back 少注册一个别名，不是框架缺陷。 |
| **F-2 / F-3 / F-4 / F-5 / F-6** | 已闭环或已关闭：F-2 菜单两套口径的取向已按裁定 D3 落（组头 `VPN`，注入点诉求**已并入缺陷 12 的对照段**，不另立条）；F-3/F-5 按裁定执行完毕；F-4 控制器归属已闭环；F-6 复核后**撤销疑点**（即本文第一批的**缺陷 6**，已在那里记状态）。 |
| **F-11** | 不是框架缺陷，是**业务取向**，已由裁定 D1「v1 不做多区自助」收口（`dev-plan.md:520`）。它与本清单的关系只有一处：**缺陷 18 的第 ③ 问**明确写了「框架侧 Operator 关联本来就是多区的，不该由单个下游取向决定」。 |
| **F-12** | **已由 back 于 2026-10-05 在裁定 D2 自行实现**（`mynet_api@37bd540` + `mynet_gui@b80a577`），不需要框架动作。本清单只保留其中一条**框架侧事实**（写给框架确认语义，不作为诉求）：核销只发生在**建号路径**（email `UserAuthController.php:149,159`、sms `:323,326`、OAuth `GatesOAuthRegistration.php:53,57`），**已注册成员凭码入区（join）不产生 `UserRegistered`** ⇒ back 的「核销即成好友」只覆盖新人。 |
| **F-13** | 是 **back 的交付件**（给 DBA 的建库 DDL 与生成器对不上），取向已裁（裁定 E1「只取 back 启用的 74 张」）。它牵到框架的只有一句：那 47 张下游表**本来就不该由 mynet 建**，属交付口径不属框架缺陷。 |
| **F-22 … F-28** | 七条全是 **back 侧裁决项**（2026-10-04 P0 审计提出，全文在 [`spec/v1-permission-matrix.md`](spec/v1-permission-matrix.md) §4/§5 与 [`spec/v1-error-codes.md`](spec/v1-error-codes.md) §3/§5），且**已于同日逐项裁完**（`dev-plan.md:515-527`）⇒ 一条都不进这份对外清单。<br>⚠️ 唯一值得框架知道的一句：**F-28 的端别判定**（框架没给资源属主判定、`RbacService::check()` 对非 Operator 主体直接 `return false`、铁律 `identity-model.md` §2/§4 禁止 `User` 持有角色）是「对齐请求 1」成立的前提 —— 本清单**不把它当 bug 提**，因为那是框架有意的设计；back 只按上文请求签名对齐。 |
| **A1 的「框架出属主判定方法」那半** | 按上文**对齐请求 1** 提，**不按新增缺陷提**（框架已在实现，用户 2026-10-04 告知）。 |

---

## 这份清单不要读成的三件事

1. **不要读成「back 已经在按这些绕行做」**：每条的「back 现状的兜底」都写清了哪道是**代码级补丁**
   （缺陷 7 的歧义拒绝、缺陷 9 的监听器、缺陷 11 的 stub、缺陷 13/14 的读模型、缺陷 15 的前端闸门），
   哪道**根本没有兜底**（缺陷 12 只有落点约束、缺陷 18 只如实回显、缺陷 15 的跨租户那半没有兜底）。
2. **不要读成「back 已经实测触发过」**：本批**只读**取证 —— 凡属**写库 / 改构建配置 / 动生产**的动作一律没做，
   每条的「复现方式」里都用 ⚠️ 标出了「代码级取证」与「实测跑过」的分界（唯一实测跑出过后果的是
   缺陷 12 的 fast-glob 探针与缺陷 7、10 的 `information_schema` / `SHOW INDEX` 只读查询）。
3. **不要读成「back 等不及就自己改了」**：全清单**没有一处 vendor 改动、没有 fork**；
   候选方案一律是 `dev-plan` 原文的转述 + back 的倾向，**归属框架拍板**（裁定 G1 的口径）。
