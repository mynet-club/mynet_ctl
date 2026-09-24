# dsplat/multi-tenant-saas 上游缺陷反馈

> 用途：交给框架维护者（`github.com/dsplat/*`）的缺陷报告 + 可直接粘贴的提示词。
> 依据 `AGENTS.md` §1.8——`vendor/dsplat/**` 只读，缺陷一律回上游修，这边 `composer update` 拉回来。
>
> 环境：`mynet_back` · PHP 8.5.3 · Laravel ^13.0
> 装了的框架包：`multi-tenant-saas`（`dev-main as 2.7.0`）· `-module-infrastructure`（`dev-main`）· `-module-auth`（`dev-main`）
> 证据均为 `file:line`，可逐条复核。

---

## 缺陷 1 —— `auth` 模块硬引用未声明的 `notification` / `billing` 模块

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

## 我方（mynet_back）对应的待办

上游修好之前，这两条在我方侧的表现与处置：

| 上游缺陷 | 我方表现 | 我方处置 |
|---|---|---|
| 1 notification | `/api/v1/notifications/unread-count` 500 | GUI 已优雅降级（控制台报错但页面正常）。**不在 vendor 打补丁** |
| 2 AiStreaming | `php artisan route:list` / `route:cache` 不可用 | 改用 `grep` 模块 `Routes/api.php` 查路由；**不在 vendor 打补丁** |

---

## 缺陷 3 —— `ModuleRegistry` 对**未启用**的模块也做依赖检查，每次请求刷一批 WARNING

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

