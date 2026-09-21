# mynet_back 命名空间漂移映射清单（T31a）

> 生成：2026-09-21 · 状态：**决策材料，本批未动任何代码**
> 背景：T31 —— mynet_back 在当前提交下**应用跑不起来**，模块路由（含 VPN 全部 `/api/v1/*`）不注册
> 相关：`integration-plan-v2.md` §3 W4（back 业务上移 / 改产 VPNBundle）

---

## 0. 结论摘要

扫描 `mynet_back/app/` 内全部 `use App\...` 语句（**后经独立抽查修正，见 §8**）：

| 类别 | 数量 | 出现次数 | 问题本质 | 修复动作 |
|------|------|---------|---------|---------|
| ✅ PSR-4 解析成功 | 21 类 | — | — | 无需处理 |
| **A · 漂移** | **39 类** | **89** | 文件声明 `Modules\...\`，`use` 却写 `App\...` | **改 import 语句** |
| **B · PSR-4 错位** | **22 文件** | — | 文件声明 `App\...` 却住在 `app/Modules/...` 下 | **挪文件 或 改 namespace** |
| **C · 真缺失（use 层）** | **10 类** | **12** | 全 app/ 与 vendor 都找不到同类 | 见 §4 分三种 |
| **D · 内联 FQCN 缺失** | **约 19 处** | — | 代码里直接写 `App\...` 而非 `use`，扫描不到 | 见 §8 |
| **E · 级联 fatal** | **2 文件** | — | 裸 `extends DomainException` 解析到不存在的类 | 见 §8 |
| **F · 大小写不自洽** | **9+** | — | namespace 与文件名/目录名大小写不符，Linux 上加载失败 | 见 §8 |

> `use App\...` 语句共 **217 条 / 74 个不同类**。
>
> ⚠️ **方法局限（重要，影响上面所有数字）**：本清单最初只扫描 `use` 语句，因此：
> - 漏掉了**内联 FQCN**（代码里直接写 `\App\Models\Node::` 这种）
> - 漏掉了**无 `use` 语句的 PSR-4 错位类**（19/22 个）
> - 漏掉了**裸 `extends`** 解析失败
> - 漏掉了**大小写不自洽**（namespace 与文件名不一致）
>
> **独立抽查（explore-1）证实了漏算**，§8 是修正后的补充。完整的修复清单应以
> **A+B+C（use 层）+ D+E+F（非 use 层）** 合计为准，而非仅本表 A/B/C。

> **「跑不起来」的直接原因不止 import 错误**：B 类文件声明的 namespace 看似正确，
> 但 PSR-4 会按 `App\Models\NodeMetric` 去 `app/Models/NodeMetric.php` 找——而文件在
> `app/Modules/Monitor/Models/`。**自动加载器根本找不到它们**，即使 namespace 写对也没用。
> 另：`vendor/composer/autoload_classmap.php` 中 `App\`/`Modules\` 条目均为 **0**，
> **无 classmap 兜底**，错位类运行时必 `Class not found`。

---

## 1. 为什么「模块路由不注册」

`app/Providers/AppServiceProvider.php` 把框架的 `ModuleRegistry` 绑定到
`App\Services\AppModuleRegistry`，而后者 `extends MultiTenantSaas\Services\ModuleRegistry` ——
**这个 namespace 在整份 vendor 里不存在**（框架实际类是
`MultiTenantSaas\Modules\Infrastructure\Services\ModuleRegistry`），且**无 `class_alias` 兜底**。

于是在模块 bootstrapping 时 fatal → 模块发现中断 → VPN 模块的路由一条都不注册。
（已用受控实验验证：把这两处 import 改成正确 FQCN 后，模块加载**推进了**，但立刻卡在
下一个不存在的类 `App\Services\MonitorRtCmdService` —— 证明这是**一条链**，不是单点。）

---

## 2. 表 A · 漂移清单（use 层）—— 修复动作：改 import

> ⚠️ 本表为**初版枚举**（含 4 个后来被归为 PSR-4 错位的项）。
> **权威数量以 §8.6 为准：漂移 39 类 / 89 处**。逐项内容仍有参考价值，但不要按本表行数作结论。
>
> **验收方式**：改完 import 后，逐条用 `class_exists` 验证，而非只看有没有报错。

| # | app 内的 use（错误） | 类实际所在 namespace | 文件 | 处数 |
|---|---|---|---|---|
| 1 | `App\Models\SystemEvent` | `Modules\VPN\Models` | `app/Modules/VPN/Models/SystemEvent.php` | 14 |
| 2 | `App\Domain\Exceptions\DomainException` | `Modules\VPN\Domain\Exceptions` | `.../Domain/Exceptions/DomainException.php` | 11 |
| 3 | `App\Models\Route` | `Modules\VPN\Models` | `app/Modules/VPN/Models/Route.php` | 5 |
| 4 | `App\Models\Service` | `Modules\VPN\Models` | `app/Modules/VPN/Models/Service.php` | 5 |
| 5 | `App\Domain\Services\VPN\GnbConfigGeneratorDomainService` | `Modules\VPN\Domain\Services` | `.../Domain/Services/GnbConfigGeneratorDomainService.php` | 4 |
| 6 | `App\Models\NodeKey` | `Modules\VPN\Models` | `app/Modules/VPN/Models/NodeKey.php` | 3 |
| 7 | `App\Models\NodeMetric` | `App\Models` ⚠️ | `app/Modules/Monitor/Models/NodeMetric.php` | 3 |
| 8 | `App\Models\ServiceMetric` | `Modules\Monitor\Models` | `app/Modules/Monitor/Models/ServiceMetric.php` | 3 |
| 9 | `App\Events\Node\NodeStatusChangedEvent` | `Modules\VPN\Events\Node` | `.../Events/Node/NodeStatusChangedEvent.php` | 3 |
| 10 | `App\Models\NodeConfig` | `Modules\VPN\Models` | `app/Modules/VPN/Models/NodeConfig.php` | 2 |
| 11 | `App\Models\NodeService` | `Modules\VPN\Models` | `app/Modules/VPN/Models/NodeService.php` ⚠️另有同名候选 | 2 |
| 12 | `App\Domain\Exceptions\TenantAccessDeniedException` | `Modules\VPN\Domain\Exceptions` | `.../TenantAccessDeniedException.php` | 2 |
| 13 | `App\Services\EmailVerificationCodeService` | `Modules\VPN\Services` | `app/Modules/VPN/Services/EmailVerificationCodeService.php` | 2 |
| 14-24 | `App\DTOs\{CreateNode,CreateService,UpdateNode,UpdateService,UpdateUser,UserSearch}DTO` | `Modules\VPN\DTOs` | `app/Modules/VPN/DTOs/*.php` | 各 1 |
| 25 | `App\Domain\Exceptions\NodeException` | `Modules\VPN\Domain\Exceptions` | `.../NodeException.php` | 1 |
| 26 | `App\Domain\Services\IpPoolDomainService` | `Modules\VPN\Domain\Services` | `.../IpPoolDomainService.php` | 1 |
| 27 | `App\Domain\Services\RouteDomainService` | `Modules\VPN\Domain\Services` | `.../RouteDomainService.php` | 1 |
| 28 | `App\Domain\Services\RouteRequestDomainService` | `Modules\VPN\Domain\Services` | `.../RouteRequestDomainService.php` | 1 |
| 29 | `App\Domain\Services\Monitor\NodeHeartbeatDomainService` | `App\Domain\Services\Monitor` ⚠️ | `app/Modules/Monitor/Domain/Services/NodeHeartbeatDomainService.php` | 1 |
| 30 | `App\Domain\Services\VPN\VpnConfigGeneratorFactoryDomainService` | `App\Domain\Services\VPN` ⚠️ | `.../VpnConfigGeneratorFactoryDomainService.php` | 1 |
| 31 | `App\Events\BaseSystemEvent` | `Modules\VPN\Events` | `app/Modules/VPN/Events/BaseSystemEvent.php` | 1 |
| 32-33 | `App\Events\Mail\{AccountVerification,PasswordReset}MailEvent` | `Modules\VPN\Events\Mail` | `.../Events/Mail/*.php` | 各 1 |
| 34-37 | `App\Events\Node\{NodeCreated,NodeDeleted,NodeUpdated}Event` | `Modules\VPN\Events\Node` | `.../Events/Node/*.php` | 各 1 |
| 38 | `App\Http\Controllers\Api\BaseApiController` | `Modules\VPN\Controllers\Api\V1` | `.../Controllers/Api/V1/BaseApiController.php` | 1 |
| 39 | `App\Http\Controllers\Api\V1\BaseApiController` | `Modules\VPN\Controllers\Api\V1` | 同上 | 1 |
| 40-41 | `App\Http\Requests\Api\V1\Notification\{DeleteNotifications,NotificationList}Request` | `Modules\VPN\Requests\Notification` | `.../Requests/Notification/*.php` | 各 1 |
| 42 | `App\Models\IPPool` | `Modules\VPN\Models` | `app/Modules/VPN/Models/IPPool.php` | 1 |
| 43 | `App\Models\NodeIP` | `Modules\VPN\Models` | `app/Modules/VPN/Models/NodeIP.php` | 1 |
| 44 | `App\Models\RoutePermission` | `Modules\VPN\Models` | `app/Modules/VPN/Models/RoutePermission.php` | 1 |
| 45 | `App\Models\ServiceApproval` | `Modules\VPN\Models` | `app/Modules/VPN/Models/ServiceApproval.php` | 1 |
| 46 | `App\Models\ServiceShare` | `Modules\VPN\Models` | `app/Modules/VPN/Models/ServiceShare.php` | 1 |
| 47 | `App\Models\SocialConnection` | `Modules\Social\Models` | `app/Modules/Social/Models/SocialConnection.php` | 1 |
| 48 | `App\Services\MonitorRtCmdService` | `Modules\Monitor\Services` | `app/Modules/Monitor/Services/MonitorRtCmdService.php` | 1 |
| 49 | `App\Services\Monitor\NodeOperatorService` | `Modules\Monitor\Services` | `.../NodeOperatorService.php` | 1 |

> ⚠️ 标记 = 该类属**B 类（PSR-4 错位）**：文件声明的 namespace 是 `App\...`（所以 import「看起来对」），
> 但文件物理位置在 `app/Modules/...` 下 → **自动加载器仍然找不到**。这类不能只改 import。

---

## 3. 表 B · PSR-4 错位（22 个文件）—— 修复动作：挪文件 或 改 namespace

这些文件**声明 `App\...` namespace，却住在 `app/Modules/...` 下**，与 PSR-4 规则不符。

| namespace（文件声明） | 实际位置 | PSR-4 期望位置 |
|---|---|---|
| `App\Models` | `app/Modules/Monitor/Models/{NodeMetric,MonitorRtCmd,AlertRule,AlertHistory,NodePeerConnection,NodeMonitorSnapshot}.php` | `app/Models/` |
| `App\Models` | `app/Modules/VPN/Models/NodeKeyResponseDTO.php`（namespace 为 `App\DTOs`） | `app/DTOs/` |
| `App\DTOs\Monitor` | `app/Modules/Monitor/DTOs/CreateRtCmdDTO.php` | `app/DTOs/Monitor/` |
| `App\Domain\Services\Monitor` | `app/Modules/Monitor/Domain/Services/{NodeHeartbeatDomainService,NodeMonitorDomainService,NodeOperatorDomainService}.php` | `app/Domain/Services/Monitor/` |
| `App\Services\Monitor` | `app/Modules/Monitor/Services/{MetricsCollectorService,MonitorCommandService,NodeStatusService}.php` | `app/Services/Monitor/` |
| `App\Services\Alert` | `app/Modules/Monitor/Services/AlertRuleService.php` | `app/Services/Alert/` |
| `App\Http\Middleware` | `app/Modules/WebSocket/Middleware/WebSocketAuthMiddleware.php` | `app/Http/Middleware/` |
| `App\Services\VPN` | `app/Modules/VPN/Services/{GnbConfigService,VpnConfigService}.php` | `app/Services/VPN/` |
| `App\Services\Network` | `app/Modules/VPN/Services/IpPoolService.php` | `app/Services/Network/` |
| `App\Services\Route` | `app/Modules/VPN/Services/{RouteService,RouteRequestService}.php` | `app/Services/Route/` |
| `App\Domain\Services\VPN` | `app/Modules/VPN/Domain/Services/VpnConfigGeneratorFactoryDomainService.php` | `app/Domain/Services/VPN/` |

**影响范围**：整个 `Modules/Monitor` 与 `Modules/WebSocket` 两个包**整包处于错位状态**——
它们物理上在 `Modules/` 下，却声明 `App\` 命名空间。这不是个别文件，是**一次未完成的搬迁**。

**修复方向二选一**：
- **(a) 挪文件**：按 namespace 把文件移到 `app/Models/...` 等位置 → 与 `use App\...` 一致，import 不用改
- **(b) 改 namespace**：把文件 namespace 改成 `Modules\Monitor\...` → 与路径一致，但所有 import 都要改

⚠️ (a) 与 (b) **不能混用**——一旦混用，同一类会有两个可加载的定义，更难排查。

---

## 4. 表 C · 真缺失（10 类 / 12 处）—— 修复动作：三种不同

### C1 · 类在框架 vendor 里（3 个）→ 改 import 为框架真实 namespace

| 引用 | 框架内实际位置 |
|---|---|
| `App\Models\Permission` | `vendor/dsplat/multi-tenant-saas-module-auth/Models/Permission.php` |
| `App\Models\Role` | `vendor/dsplat/multi-tenant-saas-module-auth/Models/Role.php` |
| `App\Models\SystemSetting` | `vendor/dsplat/multi-tenant-saas-module-infrastructure/Models/SystemSetting.php` |

> 别名归属以框架 PSR-4 为准（`MultiTenantSaas\Modules\Auth\Models\Permission` 等），改 import 即可。

### C2 · 类被改名（2 个）→ 需人工确认语义等价

| 引用 | 引用处 | 候选实际类 | 说明 |
|---|---|---|---|
| `App\Domain\Services\GnbConfigDomainService` | `app/Modules/VPN/Services/GnbConfigService.php` | `App\Services\VPN\GnbConfigService`（同文件内） | 名称从 `DomainService` 变成 `Service`，且 namespace 变了。**需确认二者语义等价**，不能机械替换 |
| `App\Domain\Services\VpnConfigDomainService` | 同上 | `App\Services\VPN\VpnConfigService` | 同上 |

### C3 · 类确实不存在（5 个）→ **阻断级**，需新建或删除调用

| 引用 | 引用处 | 说明 |
|---|---|---|
| `App\Handlers\BaseHandler` | 3 处：`app/Modules/VPN/Handlers/Event/Notification/{NodeStatusNotificationHandler,RouteStatusNotificationHandler,SendNotificationHandler}.php` | 这 3 个 handler **extends 一个不存在的基类** → 一旦被自动加载即 fatal。它们**永远无法运行** |
| `App\Notifications\NodeStatusNotification` | `.../NodeStatusNotificationHandler.php` | handler 引用了不存在的通知类 |
| `App\Notifications\RouteStatusNotification` | `.../RouteStatusNotificationHandler.php` | 同上 |
| `App\Notifications\SystemNotification` | `.../SendNotificationHandler.php` | 同上 |
| `App\Models\RolePermission` | `app/Http/Controllers/Admin/PermissionController.php` 等 | app 与 vendor 均无此类 |

---

## 5. 修复路线建议（依赖顺序）

```
第 1 步  C3（5 个阻断项）
         └─ BaseHandler 与 3 个 Notifications 不存在 → 通知/事件处理链路**不可能工作**
            建议：先决策「这些功能是否还要」；若要，则新建基类与通知类；
            若不要，则连同 3 个 handler 一并删除（避免死代码继续误导）

第 2 步  B（22 个 PSR-4 错位）
         └─ 二选一，且**必须统一**：(a) 挪文件 或 (b) 改 namespace
            注意 Monitor/WebSocket 两个包整包错位 —— 建议按「包」决定，不要按文件决定

第 3 步  C1 + C2（框架类 + 改名类）
         └─ C1 改 import 为框架真实 namespace
         └─ C2 人工确认 GnbConfigService/VpnConfigService 与 DomainService 是否语义等价

第 4 步  A（43 类 / 89 处漂移）
         └─ 批量改 import（机械动作，可脚本化，但改完必须逐条验证类可加载）

第 5 步  验收
         └─ `php artisan route:list` 中应出现 VPN 模块路由（当前为 0 条）
         └─ 目标：/api/v1 下出现 nodes、tenants、monitor 等模块路由
```

---

## 6. 对 WG 推进的意义（为什么这是前置）

用户已决策：**S6 = back 改产抽象 VPNBundle**。但 back 现在**跑不起来**——
模块路由不注册意味着 `GET /nodes/{id}/config-bundle` **根本不可达**。

因此：
- **本清单是 S6 的前置**：back 起不来 → 无法实现、更无法验证 bundle 下发
- WG 真实依赖链：`T31 修复 → back 可跑 → S6 改产 VPNBundle → S3 剩余/S4 → S7 WireGuardDriver`
- **S6 的改造点**在 `app/Modules/VPN/Services/ConfigBundleService.php`（当前产出已渲染的 gnb 文本），
  改为产出 agent 侧 `VPNBundle` 结构（字段见 `spec/driver-abstraction.md` §3.1）

---

## 7. 本批未做的事（明确边界）

- **未改动任何代码**（用户决策：先出映射清单）
- **未验证生产是否受影响**：生产可能跑的是别的提交/vendor 状态；`cd7374bf 以 mynet_back_new 代码覆盖`
  看起来是 v3 迁移的中途态
- **未统计**：`use MultiTenantSaas\...` 等非 `App\` 前缀的引用是否也有问题
- 抽查验证：见 explore-1 报告（独立抽样 8 项核对 namespace 与路径）

**等待用户决策**：(a) 这次 v3 迁移是否在进行中 / (b) B 类错位按「包」选 (a) 还是 (b) / (c) C3 的 5 个不存在类：新建还是删除调用方。

---

## 8. 独立抽查修正（explore-1，2026-09-21）

独立抽查**未采信我的结论**，自扫 `use App\...` 217 处后发现多处漏算。以下为修正补充。

### 8.1 PSR-4 错位文件：完整 22 个（我原先只报了 3 个）

我最初的脚本只报告「出现在 `use` 语句里」的错位类；其余 19 个**没有任何 `use` 引用**，
但**一旦被加载同样 fatal**。完整清单：

| namespace（文件声明） | 实际位置 | PSR-4 应在 |
|---|---|---|
| `App\Models` | `app/Modules/Monitor/Models/{AlertHistory,AlertRule,MonitorRtCmd,NodeMetric,NodePeerConnection,NodeMonitorSnapshot}.php` | `app/Models/` |
| `App\DTOs\Monitor` | `app/Modules/Monitor/DTOs/CreateRtCmdDTO.php` | `app/DTOs/Monitor/` |
| `App\Domain\Services\Monitor` | `app/Modules/Monitor/Domain/Services/{NodeHeartbeatDomainService,NodeMonitorDomainService,NodeOperatorDomainService}.php` | `app/Domain/Services/Monitor/` |
| `App\Services\Monitor` | `app/Modules/Monitor/Services/{MetricsCollectorService,MonitorCommandService,NodeStatusService}.php` | `app/Services/Monitor/` |
| `App\Services\Alert` | `app/Modules/Monitor/Services/AlertRuleService.php` | `app/Services/Alert/` |
| `App\Http\Middleware` | `app/Modules/WebSocket/Middleware/WebSocketAuthMiddleware.php` | `app/Http/Middleware/` |
| `App\Services\VPN` | `app/Modules/VPN/Services/{GnbConfigService,VpnConfigService}.php` | `app/Services/VPN/` |
| `App\Services\Network` | `app/Modules/VPN/Services/IpPoolService.php` | `app/Services/Network/` |
| `App\Services\Route` | `app/Modules/VPN/Services/{RouteService,RouteRequestService}.php` | `app/Services/Route/` |
| `App\Domain\Services\VPN` | `app/Modules/VPN/Domain/Services/VpnConfigGeneratorFactoryDomainService.php` | `app/Domain/Services/VPN/` |
| `App\DTOs` | `app/Modules/VPN/DTOs/NodeKeyResponseDTO.php` | `app/DTOs/` |

**影响**：`Modules/Monitor` 与 `Modules/WebSocket` 两个包**整包错位**，不是个别文件。

### 8.2 内联 FQCN 缺失（约 19 处）—— 仅扫 `use` 看不到

代码里直接写 `\App\...`，未通过 `use` 导入。这些**同样会在运行时 fatal**：

| 内联引用 | 位置 | 实际类（若存在） |
|---|---|---|
| `App\Models\Node` | `app/Modules/Monitor/Domain/Services/NodeHeartbeatDomainService.php:455,467` | ✅ `Modules\VPN\Models\Node` |
| `App\Http\Resources\{Node,User,Friend,FriendRequest,Invitation,SocialOverview}Resource` | `app/Modules/VPN/Controllers/Api/V1/SocialController.php:31-261` | ❓ 待查 |
| `App\Core\Domain\Node\*`（3 处） | SocialController 内 | ❓ 待查 |
| `App\Events\User{Registered,Updated,Deleted}Event` | 待查 | ❓ 待查 |
| `App\Handlers\Event\*`（3 处） | 待查 | ❓ 待查 |
| `App\Notifications\NewUserRegistered` | 待查 | ❓ 待查 |
| `App\Services\NodeRebootService` | 待查 | ❓ 待查 |
| `App\Models\UserTenant` | 待查 | ✅ `app/Models/TenantUser.php`（注意命名相反！） |
| `App\Jobs\Event\ProcessEventJob` | `app/Modules/VPN/Jobs/Event/` 内 | ❓ 待查 |

### 8.3 级联 fatal（2 文件）

| 文件 | 问题 |
|---|---|
| `app/Domain/Exceptions/ServiceConfigurationException.php:17` | 裸 `extends DomainException` → 解析为不存在的 `App\Domain\Exceptions\DomainException` |
| `app/Domain/Exceptions/UserException.php:8` | 同上 |

> 这两个文件在 `app/Domain/Exceptions/` 下（路径正确），但**父类不存在** → 一旦自动加载即 fatal。

### 8.4 大小写不自洽（Linux 上必失败）

| namespace | 实际文件名 | 影响 |
|---|---|---|
| `Modules\VPN\Models\IDPool` | `app/Modules/VPN/Models/IdPool.php` | namespace 是 `IDPool`，文件名是 `IdPool` → macOS 不敏感、**Linux 上加载失败** |
| `Modules\VPN\Tests\*`（8 个） | 实际在小写 `tests/` 目录 | 同上 |

### 8.5 抽查确认无误的部分（8 项样本）

以下经独立核对**结论成立**：
- ✅ 漂移样本（SystemEvent / DomainException / CreateNodeDTO / BaseApiController / NodeService）——namespace 与 `use` 确实不一致
- ✅ 真缺失样本（`App\Handlers\BaseHandler` / 3 个 Notifications）——全 app+vendor 均无声明
- ✅ C1 框架类（`Permission` / `Role` / `SystemSetting`）——确实在 vendor 的 `MultiTenantSaas\Modules\...` 下

### 8.6 数量修正

| 项 | 本文档初版 | 独立核对后 |
|---|---|---|
| 漂移类（use 层） | 43 | **39**（我误把 4 个 PSR-4 错位项并入） |
| PSR-4 错位文件 | 3（仅报了有 `use` 的） | **22**（完整） |
| 真缺失（use 层） | 10 | **10** ✅ 一致 |
| 内联 FQCN 缺失 | 未报 | **约 19 处** |
| 级联 fatal | 未报 | **2 文件** |
| 大小写不自洽 | 未报 | **9+ 项** |

**修复路线（第 5 步验收）应相应扩展**：除 `php artisan route:list` 出现 VPN 模块路由外，
还需用 `composer dump-autoload` + 全量类加载探测（或 `class_exists` 扫描 A~F 全集）
来确认无遗漏 —— **仅靠 `use` 层扫描不足以下结论**。
