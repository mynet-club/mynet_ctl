# SPEC: v1 权限矩阵（P0 冻结件）

> **状态**：2026-10-04 首次成文。取证方法 = `php artisan route:list`（308 条路由）+ 代码 grep + 中间件实读，
> 每条判据都带 `file:line`。**现状列**记录今天真正生效的防线，**冻结列**记录 v1 应有的判据。
> 两列不一致的地方就是洞，逐条见 §4；需要裁定的见 §5（已进 `../dev-plan.md` 决策包）。
>
> 关联：`MYNET_V1_PLAYBOOK.md` §7 P0（「真实缺口是**权限矩阵**与稳定错误码清单」）、
> [error-codes](v1-error-codes.md)（同批 P0 件）、[zone-removal.md](zone-removal.md)（「用户面不表达 tenant 权限」裁定）

---

## 1. 冻结的边界规则（先给结论）

| # | 规则 | 为什么这么定 |
|---|---|---|
| R1 | **每个业务写操作只有一个权威判据点，在服务端。** 前端隐藏按钮**不算**防线，只能算防误点 | 「前端有门禁、服务端没有」在本项目已有三处实测（§4-3/§4-5/§4-8），表现都是「界面拦住了，URL 直达拦不住」 |
| R2 | **用户面（GUI）只有「自己 + 本租户作用域」这一种权限语义。** 不出现角色下拉、不出现 tenant 管理边界、不出现 `is_admin` 之类字段 | 用户裁定原话：「user 是 user，operator 是 operator……用户无感 tenant 的存在」 |
| R3 | **console 面（租户后台）= Operator 主体 + `tenant_admin` 角色闸门**；普通 `User` 一律拒。v1 不在 console 内做细粒度分权（要做得先补权限项注册，见 §5-F-24） | 框架那套 `rbac.permission` 在 back 不可调用（dev-plan F-1），拿它当 v1 前提会阻塞 |
| R4 | **节点面（设备通道）判据 = 验签 + 节点自身租户上下文**，绝不接受请求体里传来的 `tenant_id` 作为归属依据 | 该面没有登录用户，任何「以参数定租户」的写法都是越权入口 |
| R5 | **本机面（mynetd 8096）判据 = 回环 Host + 同源 Origin**，它的安全性来自「只监听 127.0.0.1」这个物理事实，不来自 token | 见 [local-api.md](local-api.md)；token 只用于种 cookie |
| R6 | **同一操作的判据在两个地方各写一遍 = 缺陷**，必须收敛到单点（服务层或中间件），并让另一处只读上下文 | 实测：`NodeMonitorHistoryController` 让 `input('tenant_id')` 压过已校验的 TenantContext（§4-4）⇒ **2026-10-04 随 A2 收敛到单点**，同批还删了第 5 个实现 `NodeDomainService::getCurrentTenantId()`（未传参时自取 `Auth::user()->tenant_id`）与 `NodeController` 的哨兵 `1` 覆写 |

## 2. 表面清单（现状，带证据）

| 表面 | 认证 | 授权判据（今天真正生效的） | 数据来源 | 证据 |
|---|---|---|---|---|
| **公开** | 无 | 无：`GET /`、`/up`、`/console/*` SPA 壳；`GET /api/v1/system/back-face-key`（只发公钥）；`POST /api/v1/auth/{refresh-token,revoke-session}`（刷新链路刻意摘掉 auth 防死锁） | config | `mynet_api/routes/web.php:7,23-26`、`app/Modules/VPN/Routes/api.php:38-43,52-58`、`bootstrap/app.php:34` |
| **登录面**（框架） | email+密码 + 限流 | 无角色判据；`console/auth/login` 只查 `operator_tenants` 关联是否存在 | `operators` / `operator_tenants` | `vendor/dsplat/multi-tenant-saas-module-auth/Routes/public.php:12-59`、`.../ConsoleAuthController.php:45-98` |
| **节点面** `/api/v1/node/*` | X25519 DH + HMAC（`node.heartbeat.auth`），无 Sanctum | **验签即通过整组路由**；租户上下文取自节点自身 | `node_keys.public_key/algorithm`、`nodes.tenant_id` | `VPN/Routes/api.php:79-90`、`VPN/Middleware/NodeHeartbeatAuthMiddleware.php:49-118,133-147` |
| **用户面** `/api/v1/{nodes,route,ippools,services,social,notifications,users,ids,files,user/tenants}` | `auth:sanctum` + `token.binding`（设备指纹；**空指纹放行**）+ throttle | **没有任何角色/权限判据**。隔离只有两条：`TenantScope` fail-closed + 控制器内各自实现的 user_id/租户作用域。**且本组不挂 `tenant.identify`** ⇒ 框架那套「不可信来源（`?tenant_id=` / `X-Tenant-ID` / Cookie）必须先验归属」的闸门在本面**从不执行**（详见 §4-4） | `tenant_users`、`personal_access_tokens.device_fingerprint` | `VPN/Routes/api.php:97,286`、`app/Modules/ModuleServiceProvider.php:58`（`$moduleMiddleware` 无 `tenant.identify`）、`vendor/.../src/Scopes/TenantScope.php:96`、`VPN/Middleware/DeviceFingerprintMiddleware.php:39-55`、归属校验的实现处 `vendor/.../Http/Middleware/IdentifyTenant.php:161-192`（**本面不调用**） |
| **console 面** `/api/v1/console/*` + `/api/v1/tenants` | `auth:sanctum` + `domain.console` + `tenant.identify` + `tenant.permission:tenant_admin` | Operator 须 ∈ `operator_tenants`（`is_active`）且 `role_id` 指向 `name='tenant_admin'` 的角色行（全局行与租户专属行都认）；`User` 主体一律 403 | `operator_tenants.role_id`、`roles` | `mynet_api/routes/api.php:36-66`、`VPN/Routes/api.php:264-280`、`vendor/dsplat/multi-tenant-saas-module-auth/Http/Middleware/CheckPermission.php:56-97` |
| **平台 admin 面**（框架 vendor 路由，back 未装载使用） | 框架基类统一栈：`api.php`=sanctum+tenant.identify+VerifyOperatorTenant+module.enabled；`tenant.php`=sanctum+tenant.identify+module.enabled；`admin.php`=`tenant.permission` + 逐条 `rbac.permission:*` | `RbacService::check`：`User` 恒 false；`scope=platform` 的 Operator 恒 true；租户域查 `role_permissions⋈permissions`（缓存 1h，无租户上下文 fail-closed） | `roles`/`permissions`/`role_permissions`/`operator_tenants` | `vendor/.../src/Modules/Contracts/ModuleServiceProvider.php:144-187`、`.../CheckRbacPermission.php:17-31`、`.../RbacService.php:33-118`、`.../VerifyOperatorTenant.php:30-64` |
| **mynet_gui**（用户前端） | localStorage token → `Authorization: Bearer` + `X-Tenant-ID` | 路由守卫只看 `meta.requiresAuth` + 有 token；`hasNodeEditPermission` 是**恒 true 的 TODO 桩** | localStorage | `mynet_gui/src/services/api.ts:52,61`、`src/router/middleware.ts:17-19,40-46,105-108` |
| **console SPA**（租户后台前端） | Cookie 会话；守卫探测 `/auth/user` | `src/Modules/VPN/resources/console/routes.ts:25-76` **只有 `meta.title`/`requiresAuth`，零 `meta.permission`**；store 的 `hasPermission` 无调用方；`ConsoleMenuController` 的 permission 字段全 null | — | 同左 + `vendor/.../Http/Controllers/ConsoleMenuController.php:14-68` |
| **mynetd localapi**（8096） | 回环 Host 白名单 + Origin 同源 | Origin/Host 过 = 全权限。token 只在种 cookie 时比对，**请求侧从不校验** | 进程内存 `s.tok` | `mynet_agent/mynetd/localapi/server.go:214-267,269-288` |
| **OpenWrt LuCI** | LuCI 会话登录（页面挂 `/admin/services/mynet`），再叠 `require_auth` / `require_api_auth`（mynet API 凭证存 uci，失效自动重登） | **单一登录态，无分级**；rpcd ACL 只授 uci 读 `luci,network,system` / 写 `luci` | uci credential | `mynet_openwrt/luasrc/controller/mynet.lua:35,274-373,515-575`、`root/usr/share/rpcd/acl.d/luci-app-mynet.json` |

## 3. 权限矩阵（操作域 × 表面）

记号：`✅` = 已有服务端判据；`🟡` = 只有租户作用域隔离（同租户内不细分）；`🖥` = 只在前端拦（服务端缺）；`❌` = 无判据；`⏸` = 待裁定。

| 操作域 | 用户面 GUI | console 后台 | 节点面 | LuCI | mynetd 本机 |
|---|---|---|---|---|---|
| 读自己的节点/连接/路由 | 🟡（TenantScope + 控制器） | ✅ `tenant_admin` | ✅ 验签后限本节点 | ✅ 单登录态 | ✅ 回环+Origin |
| **改他人用户档案** | **❌**（§4-2） | ⏸ 属平台面，v1 不给 console | — | — | — |
| **列全站用户 / 按 id 读任意用户** | **❌**（§4-1/§4-2） | ⏸ 同上 | — | — | — |
| 成员与角色（`tenant_users`） | 🖥（R2：用户面本不该出现，现状有残留，§4-8） | ✅ `tenant_admin`；**但 Operator 写面缺自我隔离 = 框架侧 F-18** | — | — | — |
| 邀请码 / 入区 | 🟡 框架 `invite_codes`，链路已按框架实现（G-7/G-7b） | ✅ `invite_code.manage`（框架权限名有种子） | — | — | — |
| 节点池配额（IP 池） | 🟡 | 🟡 v1 不做分权（R3） | — | — | — |
| 路由审批（`node_route_requests`） | 🖥 只读视图 + 前端按钮 | ✅ 只读审批台（②）；**Operator 代批/应答/撤销 v1 一律不给**（dev-plan G-3 行） | — | — | — |
| 服务策略 / Proxy 开关 | 🟡 | ✅ 只读 | — | 🟡 执行归 uci/Lua | 🟡 本机全权限 |
| bundle 下发与私钥 | 🟡（私钥出口已收口，dev-plan P0-4） | — | ✅ 只发该节点应得的 bundle | — | 🟡 |
| 节点在线状态写 | 🟡 | 🟡 | ✅ 心跳（无条件写 `STATUS_ENABLED` 已裁定保持） | — | 🟡 |
| console 内细粒度分权（比 `tenant_admin` 更细） | 不适用 | **⏸ 需先注册权限项**（G-5 半边 + §5-F-24） | — | — | — |

## 4. 现状与冻结的差（洞，按可修性分级）

> 「立刻可修」= 不需要任何新裁定，改法唯一；「需裁定」= 有两条以上合法路径，已进 §5。

**逐条分级（本轮复测后的结论，别按标题里的 ❌/🟡 读分级）**：
#1 / #2 / #3 → **F-22**（同一组用户面 `/{id}` 端点，一并裁）；#4 → **F-27**（两条合法路径，本轮新增）；
#5 / #7 → **F-24**；#6 → **F-23**；**#8 = 立刻可修**（裁定已经有了 —— 「用户面不表达 tenant 权限」是
2026-10-04 的既有裁定，这里只是执行：删 GUI 的 `admin_name` 展示 + 响应不再带 `admin_id/admin_name`）；
#9 = **非洞**，记为已知设计，但必须在 [local-api.md](local-api.md) 写明。
**#10 → F-28**（本轮新增；它给 #1/#2 补的是「端别判定」—— 属主层缺在 **back**，框架只是**没提供**而非**提供坏了**）。

> **2026-10-04：上面所有「需裁定」的项已全部裁完**（#1/#2/#3 = A4、#4 = A2+A3、#5/#7 = B3、#6 = B4、
> #10 = A1+C1，另有 #8 属执行项随 C1 收）。裁定原文见 [dev-plan.md](../dev-plan.md)「2026-10-04 逐项裁定」表。
> 本节下面的分级继续保留 —— 它同时是**取证位置索引**，落码时按它找行号。

1. **`❌` 用户面整组无授权判据**：`VPN/Routes/api.php:97-286` 这 **116 条路由声明**上只挂了 `auth:sanctum` + `token.binding` + throttle，没有任何 permission/role 判据。8 个 FormRequest **一个授权判据都没有** —— 6 个 `authorize()` 直接 `return true`（例：`app/Http/Requests/GetRouterKeysRequest.php:14-17`），另外 2 个只判「登录了没」（`app/Modules/VPN/Requests/Node/CreateNodeRequest.php:21-24`、`Requests/Route/CreateRouteRequest.php:19-22` 都是 `Auth::check()`）。这不是「缺 RBAC」，是**缺 R2 的最低那层：能不能碰这个资源**。
   > **✅ 部分已落（2026-10-04，裁定 A1，`mynet_api@b3493a8`）**：属主判据层建起来了，覆盖 **5 个裸写面**
   > （`DELETE /nodes/{id}`、`POST /nodes/{id}/operator`、`PUT|DELETE /route/{id}`、`POST /route/bulk/status`），
   > 判据单点在 `Domain/Guards/OwnerGuard::owns()`。**这 116 条路由声明本身仍然只挂 `auth:sanctum` + `token.binding` + throttle** ——
   > 本轮的口径是「谁的资源」在策略/服务层答，不在路由上挂 permission。
   > ⚠️ **8 个 FormRequest 的 `authorize()` 一字未改**（6 个 `return true`、2 个 `Auth::check()`）：这是刻意的 ——
   > 单资源归属判定已经在策略里问了一次，再在请求对象里问第二遍就是矩阵 R6 禁的「同一判据两处各写一遍」。
   > 未被本轮覆盖的写面（`NodeConfigController` 一族、`connections`、`service_ports` 等）仍按旧状态读，
   > 扩面时只加 `OwnerGuard::authorize()` 调用点，不要复制判据。
2. **`❌` 跨用户写与读**：`PUT /api/v1/users/{id}` 任意登录用户可改他人 `name/email/phone` —— `UserController::update()`（`app/Modules/VPN/Controllers/Api/V1/UserController.php:210-232`）把 `$id` 直接喂给 `UserService::updateUser()`，而后者（`app/Services/UserService.php:196-213`）**完全忽略 `$updaterId`**。同组还有 `GET /users`（全站列表，`:33-52`）、`GET /users/{id}`（`:145-162`）、`/{id}/statistics|activity|nodes|tenants`。
   **消费者取证**：`mynet_gui` 只用 `/users/profile`（`src/services/api.ts:430`）与 `/users/onboarding`（`stores/onboarding.ts:432,453`）；console SPA 与 Lua 均不调这些 `/{id}` 端点 ⇒ **零消费者的管理语义端点**，与 #36 删掉的 Social 死副本控制器同类。
   > **✅ 已落（2026-10-04，裁定 A4）**：上面点名的按 id 端点**整组从用户面删净**（路由 + handler + `UserService` 三个孤儿方法 + `UserDetailResource`），OpenAPI `back.yaml` 同批改（含从未注册的 `/users/{id}/permissions` 幻影）。锁住它的是新用例 `VPN/tests/Feature/Api/UserSelfSurfaceTest.php`：路由表 users 段逐条不存在、按 id 请求回 404 而非 500、`PUT /users/profile` 只作用当前登录用户的白名单列。
   > **本条未收口的部分（归 A1 的 Gate 层）**：`GET /users` 与 `GET /users/search` 仍在，且都是只受 `auth:sanctum` 的**跨租户全站查询**；`index` 那个 `tenant_id` 参数**根本没生效**（`UserService::getUserList()` 不读它）——「参数在、判据不在」比没参数更容易骗人。已在 `UserController` 类 docblock 显式登记。
3. **`❌` 悬空路由**：`DELETE /api/v1/users/{id}` 指向不存在的 `UserController::destroy` ⇒ 命中即 500（`VPN/Routes/api.php:226`）。
   > **✅ 已随 A4 消失**（路由整组下架，命中变 404；`UserSelfSurfaceTest` 里那条 404 断言就是为了防止它以「路由在、handler 不存在」的形状长回来）。
4. **`❌` 租户归属有两个来源，而其中一个是客户端可写的、且无归属校验**（本条 2026-10-04 复测后重写，原记载「改法唯一：只认 TenantContext」**不成立**）：
   - 参数优先：`NodeMonitorHistoryController.php:110,176` 写 `$request->input('tenant_id') ?? TenantContext::getId()` ——
     注释自陈「请求参数优先」，违 R4/R6。
   - 头优先：`BaseApiController.php:302-316`（`getCurrentTenantId()`，**20+ 个调用点**：`RouteController` 7、`NodeController` 3、`IPPoolController` 4 等）
     在 TenantContext 为空时回退 `header('X-Tenant-ID', input('tenant_id'))`。GUI 每次请求都发这个头
     （`mynet_gui/src/services/api.ts:61,115,140`、`websocket.ts:108`），Lua 也靠它传区
     （`mynet_openwrt/luasrc/model/mynet/node.lua:114`）⇒ **它是活路径，不是遗留回退**。
   - **为什么这条比原先记的更严重**：框架对不可信来源的归属校验在
     `IdentifyTenant::resolveWithOwnershipCheck()`（`vendor/.../Http/Middleware/IdentifyTenant.php:161-192`），
     而**用户面整组不挂 `tenant.identify`** —— 实测 `php artisan tinker --execute` 打印中间件组：
     `api` 组 = `EnsureFrontendRequestsAreStateful / SubstituteBindings / ResolveTenantFromUser / ResolveTenantFromInviteCode`，
     **没有 `IdentifyTenant`**（`bootstrap/app.php:35-58` 没注入、框架 `TenancyServiceProvider` 只注册 `module.enabled` 别名）。
     ⇒ 同一个头在 console 面（`routes/api.php:40` 挂了 `tenant.identify`）是**校验过的**，在用户面是**不校验直采的**。
     现在没炸，只因为下游查询多数走带 `TenantScope` 的模型（与上下文求交后落空）；
     **一旦某个服务用裸 `DB::table()` 或把该值写进归属字段，就是静默跨租户**。
   - **为什么「只认 TenantContext」不是唯一改法**：用户面唯一的上下文来源 `ResolveTenantFromUser`
     取的是 `tenant_users` 里**最小的 `tenant_id`**（`:61-65`），**不读 `users.tenant_id`** ——
     而后者才是 `POST /user/tenants/switch` 持久化的「当前选中区」指针（`UserTenantController.php:78-80`）。
     直接把 `getCurrentTenantId()` 改成只认 TenantContext，会**打断多租户用户的切区**（列表静默变另一个区）。
     ⇒ 两条合法路径都要动，已进 §5-F-27 裁决。
   - **复测后修正：「当前区」不止两条来源，实际是三条并存**（2026-10-04，这条比原记载更严重）：
     ① **服务端指针** `users.tenant_id` —— `POST /user/tenants/switch` 写它（`UserTenantController.php:77-78`），
     `getCurrentTenant():100` 也读它（空则 404「未设置当前网络区」）；
     ② **`TenantContext`** ← `ResolveTenantFromUser` 取 `tenant_users` 最小的 active `tenant_id`，**明文不读 ①**；
     ③ **客户端来源** `X-Tenant-ID` / `?tenant_id=` ← `BaseApiController::getCurrentTenantId()` 直采。
     ⇒ 症状不是抽象的「客户端可指定租户」，而是**切区之后同一个账号在不同端点各按不同区回数据**：
     走 ① 的端点显示新区、走 ② 的显示最小区、走 ③ 的跟随请求头。三者互不知情。
     另有一层**掩盖机制**：`User::getTenantIdAttribute()`（`app/Models/User.php:27-38`）在属性缺键时回退查
     `tenants()` 关联的首个 active 行 —— 于是 `NodeConfigController.php:937,984,1026` 那三处
     `(int) $user->tenant_id`（注释还写着「✅ 从认证上下文获取，不信任客户端输入」）**读的是 ① 的伪装版**：
     看着像服务端权威，实际是「有指针用指针、没指针偷偷查关联」，永远不会暴露归属为空。
   - **裁定（2026-10-04，A2）：路径 A —— 服务端指针唯一权威**。落法见 dev-plan 逐项裁定表 A2/A3 行。
   - **✅ 已落（2026-10-04，`TenantSourceAuthorityTest` 13 条 / 全套 382 passed）**，落码时**来源比上面记的多**：
     除 ①②③ 外还有 **④ `NodeController::getCurrentTenantId()` 覆写**（上下文为空时**返回哨兵 `1`**
     —— 等于把无区用户当成 1 号区）和 **⑤ `NodeDomainService::getCurrentTenantId():123-149`**
     （控制器没传 tenant_id 时自己回退 `Auth::user()->tenant_id`）。⑤ 就是 §4-R6 的现行实例：
     同一个判据在服务层再写一遍，于是出现过「控制器按 A 区查、响应里 `tenant_id` 写 B 区」。
     改动清单（全部只读上下文，客户端来源与再造来源删净）：
     `ResolveTenantFromUser`（①优先 + active 成员校验，否则回退最小 active 成员区）、
     `BaseApiController::getCurrentTenantId()`（= `TenantContext::getId()`，**指针回退也删**，理由见方法注释：
     `BelongsToTenant` 在无上下文时本来就 `WHERE 1=0`，那条回退救不了查询，只会造成错位）、
     `NodeController`（删 ④ 覆写 + 3 处）、`NodeDomainService`（⑤ 只取入参）、
     `NodeConfigController`×3、`NodeMonitorHistoryController`×2、`RouteController`、`SocialController`、
     `MyNetController`×6、`UserTenantController::getCurrentTenant()`、`User::getTenantIdAttribute()`（回退补 `orderBy`，与中间件同判据）；
     GUI 侧 `current_network` 明确降为显示缓存（`mynet_gui/src/config/app.ts` 注释）。
   - **落码得到的新事实（进 A5 的 DBA 回填清单条件）**：
     「有 `users.tenant_id` 指针、但 `tenant_users` 一行 active 成员都没有」的账号，
     在 ②③④⑤ 全删之后**处处为空**（列表空、`/user/tenants/current` 404）—— 而这在删之前也是空的
     （`BelongsToTenant` 早就 fail-closed），所以这不是新回归，是**存量脏数据的既有故障**，
     只有补成员行能修。`TenantSourceAuthorityTest::test_pointer_without_any_membership_row_is_empty_everywhere`
     钉的是「两边一起空」的一致性，不许再造一个来源来掩盖它。
   - **为什么全仓测试抓不到它**（同 dev-plan **B-6**）：`tests/TestCase.php:73` 用
     `pushMiddlewareToGroup('api', IdentifyTenant::class)` 把这道闸**只在测试态**装进 api 组。
     于是套件里凡是「给 `tenant_users` 补一条 active 记录才敢发 `X-Tenant-ID`」的用例
     （`NodeApiTest.php:43`、`ApiInputOutputTest.php:43`、`RouteRequestsContractTest.php:79` 等一大批）
     测的是**生产不存在的护栏**——它们全绿，而真实用户面根本不跑这段校验。
     **这是「测试说通过 ≠ 没洞」的本项目最典型实例，F-27 落地时必须连 B-6 一起收口**
     （否则修完仍然测不到）。
   - **✅ B-6 已随 A2 收口（2026-10-04）**：`tests/TestCase.php` 补了 `pushMiddlewareToGroup('api', ResolveTenantFromUser::class)`
     （按生产顺序，在 `IdentifyTenant` 之后、邀请码之前）。⚠️ 但**测试态的 `IdentifyTenant` 保持没删**（裁定 A3 选「重建用例」
     而非「改装配」，19 个文件的头逐个对齐成本大于收益），于是它仍比生产多一道闸：要断言「当前区 = 服务端指针」的用例
     **必须自己 `withoutMiddleware(IdentifyTenant::class)`**，新落地的 `TenantSourceAuthorityTest` 就是这么写的。
     下次再加用户面租户相关用例时，忘了这一句就等于又测了一遍生产不存在的护栏。
   - 记一条边界，免得下次误判成洞：`TenantContext::getId()` 在无属性时回退
     `config('tenancy.default_tenant_id')`（`vendor/.../src/Context/TenantContext.php:51-60`）；
     back 实测该配置为 **null**（`config/tenancy.php:4`）⇒ 这条路**不会**兜到平台默认租户，未识别即空值 + `TenantScope` fail-closed。
   - **顺带清掉两处假的「防线记载」**：① `ModuleServiceProvider.php:45` 注释称「`api` 组已在 `bootstrap/app.php`
     prepend 了框架真实的 `IdentifyTenant` 与 `SetLocale`」—— 两头实测都没有，属于
     「注释宣称有闸门、实际没有」，比没注释更容易让后来人停止怀疑；② `ApiTenantMiddleware` 与
     `FrontendTenantMiddleware` **全仓零注册**（死码），而后者实现的优先级是「URL 参数 > 表单 > 会话 > 用户默认」
     （`FrontendTenantMiddleware.php:154-173`）—— 与 R4/R6 **正好相反**，还带 `$request->merge(['tenant_id'=>…])`
     改写请求体的能力。⇒ 谁「发现它没挂」而顺手挂上，等于给全站装上「参数定租户」
     （②两处**已于 2026-10-04 删除**，删后 `make check` 全绿；取证与教训留在
     [v1-error-codes.md](v1-error-codes.md) §3-8/§3-9。①那条注释**已按实测改写**（`ModuleServiceProvider.php:43-54`
     现在写明「本面没有 `IdentifyTenant`，且测试态才有」）；`tests/TestCase.php:73` 那半边留着没动，
     它属测试地基决定（B-6 自标待裁定），要连 F-27 的选型一起收口 —— **只改注释不改测试装配，下一批用例还会继续绿在生产不存在的护栏上**。）

5. **🖥 console 无细粒度且前端有门禁假象**：`tenant_admin` 即 8 页全租户数据；`hasPermission`、菜单 `permission` 字段都是死代码（§2）。
6. **`❌` 平台 admin 面在 back 是 fail-open + 误拒叠加**：back 不挂 `IdentifyDomain` ⇒ `TenantContext::getDomainType()` 恒 null，于是 `CheckPermission.php:33-38` 的 `match` 落到 **`default => $next($request)`** —— 任何**没有被 `domain.console`/`domain.admin` 置位**的路由，即使挂了 `tenant.permission`，也**等价于没挂**（console 组之所以有效，是因为 `routes/api.php:36-66` 给它挂了 `domain.console`）。反过来，实拦点 `RbacService.php:56-64` 在 `domainType!=='admin'` 时拒一切 `platform.*` ⇒ 平台面不可达，且这是副作用而非裁定（`bootstrap/app.php:84-86` 自认「方向是更严」）。
7. **`❌` 权限名漂移**：种子里大量已砍模块的权限名（workflow/ai/service_desk…）入库但无路由；`tenant.view`/`tenant.update` 只被**未加载**的 core `routes/api.php` 引用。
8. **`🖥` 用户面仍在表达租户管理边界（违 R2）**：`mynet_gui/src/pages/…/TenantListPage.vue:36,95` 显示 `admin_name`；数据源 `/user/tenants` 返回整个 Tenant 模型（含 `admin_id/admin_name`，`UserTenantController.php:36-44` + infra 迁移 `:200`）；`types/index.ts:16 permissions?`、`stores/auth.ts:381-387` 残留未删（零调用）。
9. **`🟡` mynetd localapi 的 token 不在请求侧校验**（`server.go` 只在 `ensureToken` 里比对以防重种）。防线是 Host/Origin，与 R5 一致 ⇒ **记为已知设计**，不是洞；但 [local-api.md](local-api.md) 必须写明，否则后来人会以为有 token 闸门。

10. **`🔴` 「属主鉴定」这一层缺在 back，不在框架**（2026-10-04 针对「用户面可操作没鉴权」的端别判定）：
   先把两种「鉴权」分开 —— **租户归属**（我是不是这个区的成员）和**资源属主**（这条路由/节点/池是不是我的）。
   - **框架只给前者，且刻意不给后者**：`IdentifyTenant::resolveWithOwnershipCheck()`
     （`vendor/.../Infrastructure/Http/Middleware/IdentifyTenant.php:161-192`）名字里的 ownership 查的是
     `tenant_users` / `operator_tenants` 的 active 成员关系，**输入里没有任何资源**；
     `RbacService::check()`（`vendor/.../Auth/Services/RbacService.php:40-43`）对非 Operator 主体
     **直接 `return false`**；框架铁律 `vendor/dsplat/multi-tenant-saas/.qoder/rules/identity-model.md` §2/§4
     明写「User 不拥有角色…禁止以任何方式让 User 持有角色。需要区分 User 侧能力时走套餐/功能开关」。
     ⇒ **「这是框架层的问题」只对一半**：框架不是漏了属主判定，是把它划给自己下游（back）。
   - **back 是真缺陷端**：全仓 **0** 处 `Gate::` / `Policies` / `->authorize(`（`grep -rn "Gate::\|Policies\|->authorize(" app/` = 空）
     —— 属主判定层从来没建。落点证据：`UserService::updateUser():196-213` 收下 `$updaterOrData` 却只用它分辨参数形态，
     正文 `User::find($userId)->fill(...)->save()` 无任何归属比较 ⇒ 任意登录用户可改他人 `name/phone`。
   - **数据层同样没钉死「谁的东西」**：`nodes.user_id` / `connections.user_id` / `routes.user_id` / `ip_pools.user_id`
     **全是 nullable**（`database/migrations/2026_09_23_000001_create_vpn_business_tables.php:30,106,124,161`），
     只有 `services.user_id` NOT NULL（`:176`）。所以 15 处零散 `where('user_id')` 之外，归属为空的行对任何用户都「可见」。
   - **GUI 端不是缺陷端**：`router/middleware.ts:105-108` 的 `hasNodeEditPermission()` 是恒 `true` 的 TODO 桩 ——
     按 R1 前端隐藏从来不算防线，但它现在**也没有可依据的数据源**（见 F-28）。
   > **✅ 已落（2026-10-04，裁定 A1，`mynet_api@b3493a8`）**：判据单点在
   > `app/Modules/VPN/Domain/Guards/OwnerGuard.php` 的 `owns()`（规则只出现一次，两条 fail-closed：无认证身份拒、
   > 归属列 NULL/0 拒），`NodePolicy`/`RoutePolicy` 只是它的 Gate 形状，由 `app/Providers/AuthServiceProvider.php`
   > **显式** `Gate::policy()` 注册 —— 不走命名空间约定推导：本仓模型在 `App\Modules\VPN\Models\*`，而 Gate 推导失配的
   > 默认结果是**一律拒绝**，表现为「功能莫名 403」且不可 grep。上面那句「全仓 **0** 处」的现值为
   > **5 处 `OwnerGuard::authorize()` 写面 + 6 处收敛到 `owns()` 的零散判据**；落码清单、接线时打出的两条真 bug
   > （`deleteNode` 旧签名不收 `$userId`、`createRoute` 只写不存在的 `created_by` 列）、以及**仍未收敛**的读面查询级
   > `where('user_id')` 过滤，全部见 [dev-plan.md](../dev-plan.md)「2026-10-04 逐项裁定」A1 行。
   > 两条边界照旧守：本层**只答属主**，绝不掺入 tenant 管理员语义（用户面不表达 tenant 权限是既有裁定）；
   > 读面仍按 `BelongsToTenant` 的租户作用域，策略里刻意不定义 `view` —— 否则会有人以为读也按属主过滤。
   > 框架侧「出方法、下层调用、业务继承 + 扩容」的方法诉求进 G1 那份清单第 13 条，back 不改 vendor、不 fork。
   > ⏸ **扩面已停（2026-10-04，用户指示）**：框架侧**正在实现**同名的属主判定方法 ⇒ back 不再自建第二套。
   > 剩下的 7 处零散比对（`NodeDomainService:299,860,933,1276`、`NodeKeyDomainService:1165`、
   > `RouteRequestDomainService:70`、`SocialDomainService:509`，其中 4 处抛裸 `\Exception` ⇒ 越权表现为 500）
   > **不要现在顺手收敛** —— 等框架 API 落地一次接完，替换点仍是 `OwnerGuard` 一处。清单与理由见
   > [dev-plan.md](../dev-plan.md) A1 行。

## 5. 待裁定（已进 dev-plan 决策包）

> **2026-10-04 全部已裁**，裁定原文与落地动作在
> [dev-plan.md](../dev-plan.md) 的「2026-10-04 逐项裁定」表（菜单编号 A1–A5 / B1–B4 / C1 / D1–D3 / E1–E4 / F1 / G1–G4，
> 共 22 项；A2 重问一次、A5 的回填规则另问一次）。本节保留的是**裁定时的取证与合法路径全集**，
> 下面每条末尾标注对应裁定编号与结论。

- **F-22**（→ **已裁 A4**：整组从用户面下架，同批改 OpenAPI）：§4-1/§4-2/§4-3 的用户面 `/{id}` 管理端点怎么收 —— 路径 A：整组从用户面下架（零消费者，与 #20 `/tenants` 收敛同类处置）；路径 B：保留但补 self-only + 租户内判据。**下架会改变 OpenAPI 面**（`docs/api/openapi/back.yaml:3596,3638-3753` 声明着它们），back 不擅自选。
- **F-23**（→ **已裁 B4**：v1 平台面不进 back，`default => $next` 保持不动）：§4-6 平台 admin 面 —— 是要在 back 真正装载 `IdentifyDomain` 打开平台面，还是明确「v1 平台面不进 back」并把 `default => $next` 改成 fail-closed。**后者会同时影响 console 面**（`domain.console` 缺失时的行为）。
- **F-24**（→ **已裁 B3**：v1 不做细粒度，`vpn.*` 不入库，前端假门禁死码清掉）：G-5 的「权限项入库」半边 —— v1 要不要在 `permissions`/`role_permissions` 注册 mynet 自有权限名（`vpn.*`）并把 console 门禁从「角色 = tenant_admin」升级为「权限项」。见 dev-plan G-5 行与 F-2（菜单组头裁定同批）。
- **F-27**（§4-4，本轮新增，**这条是六条里唯一带真实跨租户读风险的**）⇒ **已裁 A2：路径 A，服务端指针唯一权威**（详见 §4-4 末尾「裁定」段与 dev-plan 逐项裁定表 A2/A3 行）：用户面的「当前区」权威来源放哪 ——
  - **路径 A：服务端为唯一权威。** `ResolveTenantFromUser` 改为优先读 `users.tenant_id`（切换指针）并校验它仍是 active 成员，
    `BaseApiController::getCurrentTenantId()` 删掉 `X-Tenant-ID` / `tenant_id` 参数回退。
    切区从此只有 `POST /user/tenants/switch` 一条路；GUI 的三个注入点降级为装饰（可随后删）。
    代价：**改的是全用户面的租户语义**，`mynet_gui` + `mynet_openwrt` 两条 wire 的行为要同批核对。
  - **路径 B：保留 wire，补上校验。** 用户面挂一个 back 自己的归属校验中间件（等价框架 `resolveWithOwnershipCheck`：
    查 `tenant_users` 的 active 成员关系，不通过则忽略该来源并回退），不改 vendor。
    代价：每请求多一次 `tenant_users` 查询（框架本来也这么做）；且**参数优先那半（§4-4 第一条）仍要单独修**。
  - 两条路径都不改变 console 面（那里 `tenant.identify` 已在校验）。**back 不擅自选**：A 动 wire 语义、B 动每请求成本。
    ⇒ **裁定 A**，且 A3 另裁「测试代码不逐条对齐，按新判据重建那批用例」。
- **F-28**（§4-10，本轮新增 —— 用户点名的「userid 有 owner/level/score/credits/right，前端判断要立住」的正面包）：
  **属主与权益的权威载体定在哪一层**。先交付逐字段取证（2026-10-04 实跑）：

  | 字段 | 库里是否存在 | 权威位置 | 现状 |
  |---|---|---|---|
  | `owner` | 半有 | 各资源表的 `user_id`（**除 `services` 外全 nullable**） | 无统一 owner 抽象；框架不提供 |
  | `credits` | 有 | `tenant_users.credits`（pivot，infra 迁移 `:172`）+ `tenants.total_credits/used_credits` | **不在 `users` 上**；`TenantController.php:40` 自陈「框架 SaaS 计费用的，back 没有在用」 |
  | `level` | **无** | — | `users` / `tenant_users` 均无此列，全仓 0 命中 |
  | `score` | **无** | — | GUI 唯一命中是 `sceneParser.ts:248` 的场景解析打分，与身份无关 |
  | `right`(权益) | **无此列名** | 框架给的对应机制 = `tenants.subscription_plan` + `feature_flags` | **这条链在 back 是断的**（见下） |

  - 权益链的断点：`feature_flags` 表与 `CheckFeatureFlag` 中间件都由**必装**的 Infrastructure 提供
    （infra 迁移 `:65-80`、`Http/Middleware/CheckFeatureFlag.php:38-58`），但它依赖的 `FeatureFlagService`
    属**可选的 module-platform** —— back 的 `composer.json:31-34` 只装了 core/auth/infrastructure/operator，
    于是该中间件对每条路由都 **fail-closed 返 404**；且 `bootstrap/app.php:87-92` 的别名表**根本没注册 `feature.flag`**
    （`grep -rn "feature.flag\|CheckFeatureFlag" bootstrap/ app/` = 空）。⇒ 「走功能开关」目前是**能力宣称，不是可用机制**。
  - 反向的一处应删未删：`users.role`（我方迁移 `2026_09_25_000001:58-63` 加的，默认 `platform_user`）**全仓无人读**，
    却经 `UserService::getUserDetail():108-109` 以 `user_role`/`role` 发到前端，GUI `types/index.ts:8` 接着 ——
    与「user 设计不设 role」和框架铁律直接冲突（同 #8 一族）。
  - **待裁的两问**（⇒ **已裁：① = A1，② = C1**）：① 属主判据落在哪层 —— **裁定：Laravel `Gate`/Policy**，且写成
    **可被框架同名方法替换的接线点**（判据集中在一个适配层；框架补上属主/归属判定方法后只改适配层，不动控制器），
    同时把「框架出方法、下层调用、业务继承 + 扩容」列为对框架的第 13 条诉求（G1 那份清单）；
    ② 权益载体 —— **裁定：v1 只做租户级 `subscription_plan`，用户级 `level`/`score`/`right` 明确不进 v1 合同**；
    `credits` 作为 `tenant_users` 既有列只读摊平，不引入计费写路径、**不装 module-platform**，
    `feature_flags` 链保持未接线。
    （取证原文保留如下，供下次核对 —— 原两问：Laravel `Gate`/Policy（框架零使用，但它是标准位、可被 `->authorize()` 复用）
    vs back 服务层单点判据（与 #20 `/tenants` 收敛、F-22 路径 B 同口径）；把 `credits`/套餐真正接进
    用户面读模型（需要先决定 module-platform 装不装，属依赖变更）vs 只做租户级。**back 不擅自选**：①决定后续所有写路径的改法，②决定要不要引新依赖。）
  - 顺带一条由 ② 派生的执行项（**已裁 C1，无争议**）：上表「应删未删」的 `user_role`/`role` 外发
    （`UserService::getUserDetail():108-109` + GUI `types/index.ts:8`）随 C1 一起收 —— 用户面不再表达角色。

## 6. 复现口径（下次核对就跑这几条，2026-10-04 实测值写在行尾）

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_api

php artisan route:list | grep -cE '^ *(GET|POST|PUT|PATCH|DELETE)'
#   308        —— 本 Laravel 版 route:list 没有 --columns，别加

sed -n '97,286p' app/Modules/VPN/Routes/api.php | grep -cE 'Route::(get|post|put|patch|delete|match)'
#   116        —— 用户面整组：只挂 sanctum + token.binding + throttle

find app -path '*Requests*' -name '*.php' -not -path '*tests*' -exec grep -A3 'function authorize' {} +
#   8 个文件；6 个 return true，2 个 Auth::check()

grep -rn "input('tenant_id')" app/Modules/VPN/Controllers | wc -l
#   9          —— 违 R4/R6 的「读参数定租户」

php artisan route:list -v | grep -c NodeConfigController
#   6          —— NodeConfigController 有 9 个方法根本没被路由（见 v1-error-codes.md §3-3）

php artisan tinker --execute='$g = app("router")->getMiddlewareGroups(); print_r($g["api"]);'
#   4 项：EnsureFrontendRequestsAreStateful / SubstituteBindings / ResolveTenantFromUser / ResolveTenantFromInviteCode
#   —— **没有 IdentifyTenant**，这就是 §4-4「用户面的 X-Tenant-ID 不经归属校验」的直接证据

grep -rn "ApiTenantMiddleware\|FrontendTenantMiddleware" app routes bootstrap config | grep -v Middleware/ApiTenant | grep -v Middleware/Frontend
#   0          —— 两个租户中间件：删前取证为「零注册的死码」（§4-4 末 / 错误码清单 §3-8），2026-10-04 已删

grep -rn "Gate::\|Policies\|->authorize(\|canAny" app/ | grep -v vendor | wc -l
#   17（2026-10-04 A1 前是 **0**）—— 但这条数**不能当判据用**：它数到的是 import、
#   命名空间行和注释。要看接线面跑下面这条。

grep -rn "OwnerGuard::authorize(\|OwnerGuard::owns(" app/ | grep -v "Guards/OwnerGuard.php" | grep -v tests | grep -v ' \*' | wc -l
#   13 处 = 5 个写面 authorize（`NodeDomainService:525` 删节点 /
#        `NodeOperatorDomainService:76` 远程指令 / `RouteDomainService:117,173,526` 改·删·批量）
#        + 6 处旧零散判据收敛（`IpPoolDomainService:390,420` + `TenantScopedServiceService:263,343,379,440`）
#        + 2 处 Policy→owns（`NodePolicy:27` / `RoutePolicy:22`）
#   ⇒ 规则本体仍只在 `OwnerGuard::owns()` 一处（R6）；要扩面就加 authorize 调用点，别复制判据

grep -rn "where('user_id'" app/Modules/VPN/Controllers/Api/ | wc -l
#   15         —— 唯一的「归属」实现方式：控制器里零散 where，非集中判据

grep -n "user_id" database/migrations/2026_09_23_000001_create_vpn_business_tables.php | grep -c nullable
#   7          —— nodes(:30)/connection_grants(:77)/connections(:106)/routes(:124)
#                node_route_requests 的两端(:140,142)/ip_pools(:161) 的归属列全可空；
#                只有 services.user_id（:176）NOT NULL ⇒「谁的东西」在 schema 层就没钉死

grep -rn "feature.flag\|CheckFeatureFlag" bootstrap/ app/ | wc -l
#   0          —— §5-F-28：框架的功能开关中间件在 back 连别名都没注册

grep -n "dsplat/multi-tenant-saas" composer.json | grep -c platform
#   0          —— module-platform 未安装 ⇒ CheckFeatureFlag 即便挂上也是恒 404（fail-closed）
```

未取证（下次补）：`NodeController`/console 控制器内部 owner 校验的逐条一致性（已知的 15 处 `where('user_id')` 覆盖到哪条路由还没逐条对）；`config/auth.php` 双主体 guard 细节；`tenants.subscription_plan` 在生产库上的实际取值分布。
