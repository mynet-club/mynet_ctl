# multi-tenant-saas 框架缺陷报告（下游使用方提交）

> 提交方：mynet.club 项目（`mynet-club/mynet_api`），框架的下游使用方。
> 提交日期：2026-09-25
> 诉求：以下 5 条都在**只安装 core + auth + infrastructure 三个包**时必现。
> 我方已在本地做了临时兜底（每条都注明了），但兜底不是修法 —— 希望上游评审后从根上解决。

---

## 0. 环境（全部实测，非推断）

| 项 | 值 |
|---|---|
| `dsplat/multi-tenant-saas` | `dev-main` @ `74d7dc0fc4` |
| `dsplat/multi-tenant-saas-module-auth` | `dev-main` @ `7a71a3ab8c` |
| `dsplat/multi-tenant-saas-module-infrastructure` | `dev-main` @ `290c39b6d1` |
| 未安装的模块 | ai / billing / notification / commerce / order / ibot / wechat* / course / pay / product / ticket / activity_plan / exam / logistics / operator / service-desk / knowledge / live 等（按框架的按需裁剪设计裁掉） |
| Laravel | `v13.33.0` |
| Sanctum | `v4.3.3` |
| PHP | 8.4.23（生产/staging）· 8.5.3（开发机） |
| 数据库 | MariaDB 10.11.18（生产/staging，迁移可跑通）· sqlite（开发，迁移跑不通，见缺陷 4） |

**贯穿性根因**：5 条里有 4 条（缺陷 1/2/3/5）是同一个问题的不同表现 ——
**core / auth / infrastructure 这三个「必装包」硬依赖了「可选模块」**。
框架鼓励按需裁剪模块，但裁剪后必装包就无法正常工作，这两件事不能同时成立。
如果只能改一处，建议优先解决这个依赖方向问题。

---

## 缺陷 1：`module-auth` 的 `User` 模型硬引用未声明的 billing / notification 模块

**位置**

- `multi-tenant-saas-module-auth/Models/User.php:18`
  `use MultiTenantSaas\Modules\Billing\Models\CreditAccount;`
- `multi-tenant-saas-module-auth/Models/User.php:20`
  `use MultiTenantSaas\Modules\Notification\Models\DatabaseNotification as CustomDatabaseNotification;`
- `multi-tenant-saas-module-auth/Models/User.php:120-123`
  ```php
  public function notifications(): MorphMany
  {
      return $this->morphMany(CustomDatabaseNotification::class, 'notifiable')->latest();
  }
  ```
- `multi-tenant-saas-module-auth/composer.json` 的 `require` 只有：
  `{"php": "^8.3", "dsplat/multi-tenant-saas": "*"}` —— **没有声明 billing / notification**

**症状**：未安装 notification 模块时，任何调用 `$user->notifications()`（含 Laravel 的
`Notifiable` trait 内部路径、`unreadNotifications`、通知相关路由）直接抛 `\Error`
（class not found），而不是可捕获的 `\Exception` —— 所以业务侧的 `catch (\Exception)`
拦不住，表现为 500。整个通知面不可用。

**为什么是框架的问题**：`User` 在 **auth 模块**里，而 auth 是几乎所有使用方都会装的包。
它引用了另外两个**可选**模块的类，却没在 composer.json 里声明依赖 ——
composer 不会把它们装进来，也不会有任何安装期警告。使用方只有在运行到那一行时才发现。

**建议修法**（任选）

1. 把 `notifications()` 的覆写做成**条件性**的：`class_exists(CustomDatabaseNotification::class)`
   时才覆写，否则回落到框架默认；`CreditAccount` 相关的关联同理。
2. 或者在 `module-auth/composer.json` 里用 `suggests` 明确声明，并在
   ServiceProvider 的 `boot()` 里对缺失依赖给出**一次性明确报错**（而不是等到调用点抛 `\Error`）。
3. 或者把 `notifications()` 的覆写下沉到 notification 模块自己（由它去 extend/装饰 User），
   让 auth 模块不反向依赖它。

**我方兜底**：GUI 侧对通知面做了优雅降级（不渲染通知入口），不在 vendor 打补丁。

---

## 缺陷 2：`AiStreaming` 的控制器注入零绑定的契约与不存在的类 → `route:list` 直接崩

**位置**（`AgentServiceContract` 在整个框架里只有 5 处命中：1 个接口定义 + 下面 4 个控制器；
`bind` / `singleton` / `instance` **零命中**，即从未被绑定过）

- `multi-tenant-saas/src/Modules/AiStreaming/Http/Controllers/AiStreamingController.php:8,21`
- `multi-tenant-saas/src/Modules/AiStreaming/Http/Controllers/ResolveController.php:9,31`
- `multi-tenant-saas/src/Modules/AiStreaming/Http/Controllers/ToolExecuteController.php:9,20`
- `multi-tenant-saas/src/Modules/AiStreaming/Http/Controllers/UsageReportController.php:9,17`
- 注入的契约：`MultiTenantSaas\Contracts\AgentServiceContract`（定义在 `src/Contracts/`）
  —— core 的任何 ServiceProvider 里**都没有绑定它**
- 另一处注入的是**具体类**：`ResolveController.php:18,33`
  `use MultiTenantSaas\Modules\Ai\Services\Agent\ToolRegistry;` + `private ToolRegistry $toolRegistry`
  —— 该类在 **Ai 模块**里（`find vendor/dsplat -path '*Ai/Services/Agent*'` 为空，确认未安装）；
  core 只有 `src/Contracts/ToolRegistryContract.php`（接口），没有实现

**症状**：`php artisan route:list` / `route:cache` 直接崩：

```
Target [MultiTenantSaas\Contracts\AgentServiceContract] is not instantiable
while building [Illuminate\Foundation\Console\RouteListCommand,
MultiTenantSaas\Modules\AiStreaming\Http\Controllers\ResolveController]
```

**为什么严重**：`route:list` 是最常用的排障命令之一。它崩了之后，使用方**无法列出自己的路由**，
只能退化成 grep 各模块的 `Routes/api.php`。而 `route:cache` 崩了意味着生产部署少一道优化，
且部署脚本里若有这一步会直接失败。

**为什么是框架的问题**：`AiStreaming` 在 **core 包**里（不是可选模块），
但它依赖 Ai 模块的实现类与一个没有绑定的契约。装了 core 就必然带上它。

**建议修法**

1. 把 `AiStreaming` 移出 core，放进 Ai 模块（它本来就只服务于 AI 能力）。
2. 或者给 `AgentServiceContract` / `ToolRegistryContract` 提供 **null-object 默认绑定**
   （在 core 的 ServiceProvider 里 `singleton(..., fn() => new NullAgentService)`），
   未装 Ai 模块时注入空实现，调用时返回明确的「AI 能力未启用」而不是容器解析失败。
3. 或者把这些控制器的路由注册做成条件性的（Ai 模块存在时才注册），
   这样 `route:list` 根本不会去解析它们。

**我方兜底**：改用 grep 各模块 `Routes/api.php` 来查路由，不跑 `route:list`。

---

## 缺陷 3：核心包的种子数据写入**可选模块**的表，且外键在 `FOREIGN_KEY_CHECKS=0` 下静默悬空

**位置**

- `multi-tenant-saas-module-infrastructure/Database/migrations/2025_01_01_000008_infrastructure_module.php:235`
  ```sql
  CONSTRAINT `tenants_subscription_plan_id_foreign`
    FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`subscription_plan_id`)
    ON DELETE SET NULL
  ```
- 同文件 `:11` `DB::statement('SET FOREIGN_KEY_CHECKS=0')`、`:239` 置回 `=1`
- `multi-tenant-saas/database/migrations/2025_01_01_000018_framework_init_data.php`
  —— 往 `subscription_plans` 插 **4 条**种子（basic / … / enterprise）

**问题**：`subscription_plans` 的建表语句在 **core + auth + infrastructure 三个包里一个都没有**
（全 vendor grep `CREATE TABLE.*subscription_plans` 与 `Schema::create('subscription_plans'` 均 0 命中）——
它属于 billing/subscription 这类**可选模块**。

于是：

1. `000008` 在 `FOREIGN_KEY_CHECKS=0` 下把外键建成**指向一张不存在的表**，不报错、不警告；
2. 报错被推迟到 `000018` 的种子插入：
   ```
   SQLSTATE[42S02]: Base table or view not found: 1146
   Table '…subscription_plans' doesn't exist
   (SQL: INSERT INTO `subscription_plans` (…) VALUES (4566353822138644,'enterprise',…))
   ```
3. **全新库上 `php artisan migrate` 到此为止**，后面的迁移全部不执行。

**为什么严重**：这是「装完框架跑一次 migrate」就会撞上的问题，属于开箱即坏。
而且第 1 步的悬空外键是**静默**的 —— 关掉外键检查本身是合理做法（解决迁移顺序依赖），
但代价是「外键指向不存在的表」这种严重不一致被完全隐藏，直到很久以后才在别处爆出来。

**建议修法**（任选，1 最小）

1. `framework_init_data` 对每张表先 `Schema::hasTable()` 再插（与其它种子的幂等风格一致）。
2. 把 `subscription_plans` 的建表移进 core 或 infrastructure ——
   既然 `tenants` 的外键指向它，它就是核心的依赖，不该放在可选模块里。
3. 去掉 `tenants.subscription_plan_id` 的外键约束，改为应用层校验。

**我方兜底**：新增一个本地迁移 `2025_01_01_000010_create_subscription_plans_table.php`，
带 `Schema::hasTable` 守卫（上游哪天自己建了就自动让位），列型从种子 INSERT 反推。
文件头写明「上游给出真定义后以它为准并删除本迁移」。

---

## 缺陷 4：迁移是 **MySQL 专用**的，换驱动即失败（无任何提示）

**位置**

- `multi-tenant-saas-module-auth/Database/migrations/2025_01_01_000003_auth_module.php`
  —— `DB::statement('SET FOREIGN_KEY_CHECKS=0')`
- `multi-tenant-saas-module-infrastructure/Database/migrations/2025_01_01_000008_infrastructure_module.php`
  —— 裸 `CREATE TABLE … ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`，
  同样带 `SET FOREIGN_KEY_CHECKS`

**症状**（sqlite 实测）：

```
$ touch /tmp/x.sqlite
$ # .env: DB_CONNECTION=sqlite, DB_DATABASE=/tmp/x.sqlite
$ php artisan migrate --force
   2025_01_01_000000_framework_core ....... DONE
   2025_01_01_000003_auth_module .......... FAIL
   SQLSTATE[HY000]: General error: 1 near "SET": syntax error
   (SQL: SET FOREIGN_KEY_CHECKS=0)
```

**MariaDB 10.11.18 实测可跑通**（59 条迁移全 DONE、120 张表），所以问题限于非 MySQL 系驱动。

**为什么是框架的问题**：Laravel 生态里 sqlite 是**测试与本地开发的默认选择**
（`phpunit.xml` 里 `DB_CONNECTION=sqlite` 是极常见的配置）。框架的迁移只能在 MySQL 上跑，
意味着使用方**无法用 migrate 建出开发/测试库**，只能自己另写一套 schema 定义 ——
于是「测试用的 schema」和「生产用的 schema」变成两份，长期必然漂移。
我方就因此踩到：测试套件 154 个用例全绿，而生产库缺 17 张表，相关端点上线即 500。

**建议修法**

1. 把 `SET FOREIGN_KEY_CHECKS` 包在驱动判断里：
   `if ($connection->getDriverName() === 'mysql') { … }`
   （sqlite 有等价的 `PRAGMA foreign_keys`，pgsql 不需要）
2. 裸 DDL 改用 Schema Builder 表达；确实需要 MySQL 专有特性时按驱动分支。
3. 退一步：至少在 README / composer.json 里**明确声明「迁移只支持 MySQL/MariaDB」**，
   并在非 MySQL 驱动下让迁移**主动报一条清晰错误**（"本框架迁移不支持 sqlite"），
   而不是抛一个 `near "SET": syntax error` 让人以为是自己的 SQL 写错了。

---

## 缺陷 5：`ModuleRegistry` 的依赖检查按**完整模块表**做，裁剪后每请求刷大量 WARNING

**位置**：`multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:317`

**症状**：按需裁剪模块后（我方从 31 个只留 3~4 个），**每个请求**都记 8 条形如

```
[ModuleManager] 模块 [activity_plan] 依赖 [ai], 但该模块未安装
[ModuleManager] 模块 [order] 依赖 [billing], 但该模块未安装
[ModuleManager] 模块 [pay] 依赖 [billing], 但该模块未安装
[ModuleManager] 模块 [service-desk] 依赖 [ai], 但该模块未安装
[ModuleManager] 模块 [user-ai] 依赖 [ai], 但该模块未安装
…
```

实测一天 **2224 条**（8 个模块 × 278 次请求）。

**为什么严重**

1. **真错误被淹没** —— 排障时要在两千多条噪音里找那一条真的 ERROR。
2. **WARNING 级别失去信号价值** —— 一天两千多条 WARNING 等于没有 WARNING，
   基于日志级别的告警全部失效。
3. **日志体积失控** —— 我方生产 `storage/logs/laravel.log` 涨到 **14 GB**，
   把 40 GB 磁盘吃到 91%（剩 3.5 GB），这类噪音是主要贡献者之一。

**为什么是框架的问题**：「模块 [X] 依赖 [Y] 但 Y 未安装」这条判断，
在 **X 本身也没被启用**时是没有意义的 —— 一个没启用的模块缺依赖不是异常状态，
恰恰是按需裁剪的**预期结果**。框架鼓励裁剪（否则不会把模块拆成可选包），
却又对裁剪后的状态持续告警。

**建议修法**（任选，1 最小）

1. 只对**已启用**的模块做依赖检查；未启用的模块缺依赖直接跳过。
2. 把「模块本身未启用」这种情况降到 `debug` 级别。
3. 同一条消息在单次进程生命周期内**去重**（现在每个请求都重记一遍）。

**我方兜底**：清理了日志（14 GB → 0，保留尾部 5 万行），计划加日志轮转与级别过滤。
不在 vendor 打补丁。

---

## 优先级建议

| 缺陷 | 建议优先级 | 理由 |
|---|---|---|
| **3** | 🔴 最高 | 全新库 `migrate` 开箱即坏，任何新使用方第一次装就会撞上 |
| **4** | 🔴 高 | 直接导致「测试 schema 与生产 schema 分叉」这类极难发现的长期问题 |
| **2** | 🟠 中高 | `route:list` / `route:cache` 是日常排障与部署链路的一部分 |
| **5** | 🟠 中 | 不影响功能，但持续污染日志、削弱告警信号、吃满磁盘 |
| **1** | 🟡 中 | 影响面限于通知功能，但抛的是 `\Error`（业务侧 `catch (\Exception)` 拦不住） |

## 复现环境（最小化）

```bash
composer require dsplat/multi-tenant-saas dsplat/multi-tenant-saas-module-auth \
    dsplat/multi-tenant-saas-module-infrastructure
# 不装任何可选模块（ai / billing / notification / …）

php artisan migrate --force     # → 缺陷 3（MySQL）或缺陷 4（sqlite）
php artisan route:list          # → 缺陷 2
# 起一个请求，看日志     # → 缺陷 5（每请求 8 条 WARNING）
# 调 $user->notifications() # → 缺陷 1（\Error: class not found）
```

## 我方立场

- **不在 vendor 里打补丁**：所有兜底都在我方代码/迁移里，且带守卫，上游修好后会自动让位或可直接删除。
- 每条兜底都在代码注释里写明了「这是上游缺陷的临时兜底 + 上游修好后怎么撤」，避免变成永久的隐性分叉。
- 如果上游需要我方提供更详细的复现（完整 stack trace、composer.lock、最小复现仓库），可以直接联系。
