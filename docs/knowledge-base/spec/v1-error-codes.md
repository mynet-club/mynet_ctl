# SPEC: v1 稳定错误码清单（P0 冻结件）

> **状态**：2026-10-04 首次成文。这份文件**先登记现状，再给出冻结口径**。
> 现状部分每条带 `file:line` 且都已实读核对；冻结部分标 `⏸ 待裁定` 的没有生效，
> 已进 `../dev-plan.md` 决策包（F-25 / F-26）。
> **2026-10-04 更新：F-25 / F-26 已裁定**（裁 B2 / B1，见 dev-plan「2026-10-04 逐项裁定」表）——
> C1 生效（E1 为唯一 HTTP 信封，只对新代码硬约束、存量按 §2 登记不动），本机面按**改文档承认现状**处置。
> 下面 `⏸` 标记保留作取证上下文。
>
> 为什么 P0 必须先做这件事：错误码是**代理与壳的编程接口**。playbook §11 要求
> 「代理不得静默改变…错误码语义」—— 而现在全仓有 **7 套响应信封、3 种 `code` 语义**，
> 「不静默改变」这件事根本无从判定。
>
> 关联：[v1-permission-matrix.md](v1-permission-matrix.md)（同批 P0 件）、[local-api.md](local-api.md) §2（信封口径冲突方）、
> `mynet_ctl/docs/api/references/errors.md:50-60`（旧的文档版清单，与代码不符，见 §3-4）

---

## 1. 今天在用的响应信封（7 套）

| # | 面 | 实际形状 | 出处 | 谁消费 |
|---|---|---|---|---|
| E1 | back **用户面 + console 面** | `{success, code(HTTP), message, data, timestamp[, errors][, error_code]}` | `app/Modules/VPN/Controllers/Api/V1/BaseApiController.php:35-48,58-79` | **22 个控制器**继承它（`grep -c 'extends BaseApiController' app` = 22）；GUI `src/services/api.ts` |
| E2 | back **老 trait**（框架别名的那套） | `{success, message, data}` —— **没有 `code`，也没有 `error_code`** | `app/Http/Controllers/Concerns/ApiResponse.php:17-27,50-59`；vendor 同名 trait `vendor/dsplat/multi-tenant-saas/src/Http/Concerns/ApiResponse.php:19-29,52-62` | 未被 back 控制器引用（`grep -rl 'use App\\Http\\Controllers\\Concerns\\ApiResponse' app` = 0）；**vendor 反向 import back 的 trait**（`.../Http/Controllers/ModuleController.php:5` 等 9 处）⇒ 命名方向是反的 |
| E3 | back **节点面**（agent 消费的那组） | `{success:false, error:"英文或中文自由文本"}` —— **无 code** | `NodeMonitorController.php:66,76,96,131,156`、`NodeHeartbeatController.php:160,245,263,317`、`Middleware/NodeHeartbeatAuthMiddleware.php:206-208` | `mynet_agent/mynet/credential.go:266-289`（`backClient.envelope` 只读 `success/code/message`，**`error` 键根本不读**） |
| E4 | **异常兜底** | `{success:false, message, errors}` —— 无 `code`、无 `error_code` | `app/Exceptions/Handler.php:127-134`（422）、`:141-146`（404 ModelNotFound）、`:154-256` 其余分支 | GUI：落 `api.ts:216` 的 `default: data?.message` |
| E5 | **mynetd localapi / gateway / relay** | `{success, message, data[, stage][, need_login][, warning]}` | `mynet_agent/mynetd/localapi/{config_api.go:45,55,290,318,329,334, wizard.go:152,181, relay.go:35}`、`gateway/{ws.go:38-41, server.go:396}` | 节点自带 WebUI（`localapi/webui/index.html`）、`mynet_desktop`、`mynet_gui` 的本机探测 |
| E6 | **OpenWrt Lua** | `{success:false, message}` + HTTP 码（`json_err`，`luasrc/controller/mynet.lua:303-307`）／ `{success:false, message, detail, code}`，**`code` = shell 退出码**（`mynet.lua:1304,1318`；取不到时 `-1`，`luasrc/model/.../util.lua:213,218`） | 同左 | LuCI 页面 |
| E7 | **仅存在于文档的信封** | `{ok, data, error:{code, message, detail}}` | `spec/local-api.md:70-71,85-87`、`spec/control-plane.md:76` | **代码零实现** —— `grep -c '"ok"' mynet_agent/mynetd/localapi/*.go` 无命中 ⇒ 见 §3-5 |

> ⚠️ `code` 这一个键名在三套信封里是**三种不同的东西**：E1 = HTTP 状态整数、E6 = shell 退出码（可 `-1`）、
> E7/agent `ErrCode` = 0–6 的业务码。任何「按 `code` 分支」的客户端代码跨面即失效。

## 2. 现有错误码注册表（今天真的会被发出的码）

命名口径：`error_code`（字符串，大写下划线）只在 **E1 的第三参显式传入时**才出现在响应里。

### 2.1 back：领域异常类别（唯一成体系的一组）

| `error_code` | HTTP | 含义 | 出处 | 消费者 |
|---|---|---|---|---|
| `DOMAIN_CONFLICT` | 409 | 唯一性/状态冲突 | `app/Modules/VPN/Domain/Exceptions/DomainException.php:49-55` | 出口 `BaseApiController.php:179-184`；**仅测试断言**（`app/Modules/VPN/tests/Feature/Api/DomainExceptionHttpStatusTest.php:171-341`） |
| `DOMAIN_VALIDATION` | 422 / 400 | 参数或业务规则校验失败 | 同上（`HTTP_STATUS_CATEGORY` 把 400 也归 VALIDATION） | 同上 |
| `DOMAIN_NOT_FOUND` | 404 | 资源不存在 | 同上 | 同上 |
| `DOMAIN_FORBIDDEN` | 403 | 越权/租户拒绝 | 同上 | 同上 |
| `INTERNAL_ERROR` | 500 | 兜底（`CATEGORY_UNEXPECTED`） | 同上 | 同上 |

### 2.2 back：控制器层通用码（与 2.1 **同一情形两套码**）

| `error_code` | HTTP | 出处 |
|---|---|---|
| `VALIDATION_ERROR` / `RESOURCE_NOT_FOUND` / `UNAUTHORIZED` / `FORBIDDEN` / `INTERNAL_ERROR` | 400/404/401/403/500 | `BaseApiController.php:96,111,126,141,222`；测试 `app/Modules/VPN/tests/Unit/VPN/Controllers/BaseApiControllerTest.php` |

⇒ 越权既可能是 `DOMAIN_FORBIDDEN`（走领域异常）也可能是 `FORBIDDEN`（走 helper），**GUI 无法写一个稳定分支**。

### 2.3 back：租户识别中间件（**⚠️ 整组死码 —— 发码的中间件从未接线**）

`MISSING_TENANT_HEADER` / `INVALID_TENANT_ID` / `TENANT_NOT_FOUND` / `TENANT_ID_MISMATCH` / `TENANT_ACCESS_DENIED`
写在 `app/Modules/VPN/Middleware/ApiTenantMiddleware.php:54,69,82,100,117`
（**该文件与 `FrontendTenantMiddleware.php` 已于 2026-10-04 删除** —— §3-8；下面这些 `file:line` 是删除前的取证位置，
回读用 `git log -p --follow -- app/Modules/VPN/Middleware/ApiTenantMiddleware.php`，别按 HEAD 找）。
但 **`ApiTenantMiddleware` 全仓零注册**（`grep -rn ApiTenantMiddleware app routes bootstrap config` 只命中该文件自身；
`aliasMiddleware` 清单里没有它，任何路由组也不含它）⇒ 这 5 个码**一个都发不出来**，
`FrontendTenantMiddleware` 同罪（见 §3-8）。
同组唯一**活的**发点是 `RouteController.php:102` 直接内联的 `TENANT_ACCESS_DENIED`
⇒ 名字像「中间件产出的通用码」，实际只在一个控制器里手搓，**别的越权路径没有这个码**。
> 记这条不是为了清码，是因为它证明了一件事：**「有错误码注册表」≠「有防线」**。
> 权限矩阵 §4-4 实测用户面连 `tenant.identify` 都不挂，所以这组码即便接线也管不到用户面。

### 2.4 back：会话/刷新链路

| `error_code` | 文案 | 出处 |
|---|---|---|
| `REFRESH_TOKEN_INVALID` / `REFRESH_TOKEN_EXPIRED` / `REFRESH_TOKEN_REUSED` | 中文（「refresh token 无效 / 已过期，请重新登录」） | `Domain/Services/SessionRefreshService.php:171,176,184` → `RefreshTokenController.php:42,54` |
| `SESSION_USER_GONE` | — | `SessionRefreshService.php:67` |
| `BACK_FACE_KEY_UNAVAILABLE` | **中文长句焊死在控制器** | `MyNetController.php:177-181` |

### 2.5 框架侧（vendor，back 目前一条都没主动用）

`MODULE_DISABLED`（`.../Http/Middleware/EnsureModuleEnabled.php:65`）、`IP_NOT_ALLOWED`（`CheckIpWhitelist.php:50`）、
`FEATURE_FLAG_DISABLED`（`CheckFeatureFlag.php:92`）、`SMS_CHANNEL_UNAVAILABLE`（`module-auth/.../UserAuthController.php:211`）。
另有 **snake_case 枚举** `vendor/dsplat/multi-tenant-saas/src/Enums/ErrorCode.php:10-22`
（`insufficient_credits`/`guest_limit_exceeded`/`wechat_not_registered`/`wechat_auth_failed`/`conversation_busy`）
⇒ 与 back 的 `UPPER_SNAKE` 口径**大小写不一致**，且这些值属已砍模块，back 侧零消费。

## 3. 死码 / 悬空 / 分叉（必须清，逐条带证据）

1. **GUI 的 `4031` / `4032` 分支是死路**：`mynet_gui/src/composables/useAuth.ts:69-92,192,200` 硬比较这两个数字
   （含 `errorCode === '4031'` 的字符串兼容），但 `grep -rn "4031\|4032" mynet_api/app mynet_api/routes mynet_api/vendor/dsplat` **零命中** —— 全后端不发。
   它们只活在文档里（`docs/api/openapi/back.yaml:987`、`docs/api/references/errors.md:55-56`、`api-specification.md:161,1851-1852`）。
   净效果：**「邮箱未验证 / 验证链接过期」两个引导流程永远走不到**，用户只看到通用 403。
2. **agent 的 `shared/errors` 整包是死码**：`mynet_agent/shared/errors/errors.go:11-17` 定义 `ErrCodeSuccess..ErrCodeNodeError`（0–6）
   与 `AppError{code,message,details,stack}`，但 `grep -rn "shared/errors" mynet_agent --include=*.go` **零 import**。
   另有第二套未使用结构 `shared/utils/types.go:6-12` `BaseResponse{success,code,message,timestamp,request_id}`。
3. **`NodeConfigController` 里 9 个方法没被路由**（`php artisan route:list -v | grep -c NodeConfigController` = 6，
   即只有 `getConfiguration/saveConfig/configBundle/getBundle/publishBundle` 上线）。这 9 个方法内曾有 **11 处**把
   `['error'=>…]` 数组当成 `errorResponse()` 的**第二个位置参数**（形参是 `int $statusCode`）⇒ 一旦命中必 `TypeError`。
   **2026-10-04 已把 11 处补成显式状态码**（`errorResponse('不支持的VPN类型', 400, [...])` 等），防止将来接路由时静默 500；
   **死方法本身待删**（与 #36 删 Social 死副本控制器同类）。
4. **文档版清单与代码不符**：`docs/api/references/errors.md:50-60` 列的 `INVALID_CREDENTIALS`、`INVALID_REFRESH_TOKEN`（词序与代码的 `REFRESH_TOKEN_INVALID` 相反）、
   `ZONE_ACCESS_DENIED`（Zone 已改名）、`DOMAIN_EXCEPTION`（代码里是 5 个 `DOMAIN_*`）—— 四处对不上；`api-specification.md:1845-1875` 是同内容的第二份拷贝。**两处都要么改名要么删除**。
5. **`spec/local-api.md` 与代码分叉**（§1-E7）：文档冻结的 `{ok,…,error{code,message,detail}}` 从未实现，
   `:16` 还声称 service 端点返回稳定码 `unsupported`（`grep unsupported mynet_agent` 只命中注释 `mynet/plugin_proxy.go:235`），
   `:83` 指向的路径 `mynet_agent/internal/localapi` **不存在**（实为 `mynet_agent/mynetd/localapi`）；
   `spec/control-plane.md:76` 的 `unauthorized|stale_ts|bad_sig|replayed` 与 `challenge/session_id` 在 `mynet_agent/gateway/` 零命中。
   ⇒ 这是**契约文档在描述一个没建的面**，比没文档更危险。⏸ 待裁定，见 F-25 ⇒ **已裁（B2）：改文档承认现状**，
   不给本机面补 `{ok,error{code}}` 契约。落地 = 把 `spec/local-api.md` 的信封改成代码真实形状
   `{success,message,data[,stage][,need_login]}`，并写明「分类靠文本嗅探、防线是 Host/Origin 而非 token」。
6. **`TenantAccessDeniedException` 的第 3 个参数被静默丢弃**：调用点传自定义文案
   （`NodeDomainService.php:831,838` 传 `'用户身份验证失败'` / `'无权限访问该Tenant'`），
   而构造器只收 `(UserId, TenantId)` 两个参数并自拼消息（`Domain/Exceptions/TenantAccessDeniedException.php:17-27`）。
   不报错（PHP 允许多传），但**改文案的人会以为改成功了**。
7. **两处异常构造的实参槽错位**（已修）：`NodeOperatorService.php:68`、`NodeStatusService.php:57` 曾写
   `new DomainException($msg, 0, $e)` —— `0` 落在 `array $context` 形参上 ⇒ 命中即 `TypeError`。已改为 `($msg, [], 0, $e)`（同文件正确的写法见 `NodeConfigService.php:86`）。
8. **两个租户中间件是死码，且其中一个在文档里被当成防线**：`ApiTenantMiddleware`（§2.3 那 5 个码的发码方）与
   `FrontendTenantMiddleware` **全仓零注册**（`grep -rn "ApiTenantMiddleware\|FrontendTenantMiddleware" app routes bootstrap config`
   只命中各自文件内部）。后者更危险：它的 `getCurrentTenantId()`（`:154-173`）实现的是
   **「URL 参数 > 表单 > 会话 > 用户默认」—— 与权限矩阵 R4/R6 完全相反的优先级**（客户端参数最大），
   还带 `$request->merge(['tenant_id' => …])` 改写请求体的能力。
   ⇒ 一旦有人「发现它没注册」而顺手挂上去，等于给全站装上「参数定租户」。
   **处置：已删**（2026-10-04，`mynet_api` 本批；删前三道核验 = 零注册 / 零引用（全仓 grep 只命中两文件自身）/
   无测试依赖，删后 `make check` 全绿：guard PASS、pint 325 files、369 tests 3559 assertions）。
9. **`ModuleServiceProvider.php:45` 的注释是假的**：它声称「`api` 组已在 `bootstrap/app.php` 中 prepend 了框架真实的
   `IdentifyTenant` 与 `SetLocale`」。实测两头都没有（`bootstrap/app.php:35-58` 只 prepend 了
   `RewriteApiVersion`/`AddSecurityHeaders`/`EnsureFrontendRequestsAreStateful`，框架 `TenancyServiceProvider` 只注册
   `module.enabled` 别名），`php artisan route:list -v` 上任一用户面路由的中间件栈只有
   `api / Authenticate:sanctum / ThrottleRequests:api`（`DeviceFingerprint` 视组而异）。
   ⇒ 这是「**注释宣称有防线、实际没有**」，比没注释更容易让后来人停止怀疑。见权限矩阵 §4-4。
   ⚠️ **本条不是全新发现**：dev-plan **B-6** 记的是同一件事的另一半 —— `tests/TestCase.php:73` 把
   `IdentifyTenant` 只推进**测试态**的 api 组，于是套件里那些「先补 `tenant_users` active 记录才敢发
   `X-Tenant-ID`」的用例，测的是生产根本不跑的护栏。**注释已于 2026-10-04 按实测改写**
   （`ModuleServiceProvider.php:43-54`，把「有闸门」的记载换成「本面没有，且测试态有」的真实口径）；
   `tests/TestCase.php:73` 那半边**留着没动** —— 它是测试地基决定（B-6 自标「待裁定」），要连 F-27 的选型一起收口。
   **⇒ F-27 已裁（A2：服务端指针 `users.tenant_id` 为唯一权威），B-6 同时裁（A3：不逐条对齐装配，那批用例直接重建）。**

## 4. 消费侧为什么现在无法冻结（三条实测）

1. **GUI 只按 HTTP 状态分支**：`mynet_gui/src/services/api.ts:85,96`；全 GUI 只有 `useAuth.ts:191` **一处**读 `error_code`。
   ⇒ 后端任何新错误码在 GUI 侧都**静默降级**为 `api.ts:216` 的 `default: data?.message`，即「把中文文案当错误码显示」。
2. **GUI 靠文本嗅探判权限**：`useAuth.ts:199` 用 `data?.message?.includes('not activated')` 判「未激活」，
   `api.ts:88` 的 410 分支用硬编码中文 `friendlyMessage`。后端改文案 = 前端逻辑失效（而文案已经全中文化，见下条）。
3. **文案与逻辑焊死**：控制器层 `errorResponse('中文…')` 37 处、`trans()` **0 次**；异常消息也全中文
   （`NodeException.php:40,50`、`ApiExceptionHandler.php:67,99-112`、`MyNetController.php:181`）。
   ⇒ 想做 en-US 错误提示只能改代码；且 `DOMAIN_*` 的 message 直接进响应体，等于把中文当协议。

## 5. 冻结口径（推荐，`⏸` 项未生效）

| # | 规则 | 状态 |
|---|---|---|
| C1 | **一个响应信封**：E1 `{success, code(HTTP 整数), message, error_code?, data, timestamp}` 为唯一 HTTP 信封；E2 trait 删除或改名以免与 E1 混淆；E3 节点面必须补 `error_code`（见 C4） | **已裁（B1）**：认 C1，**只对新代码硬约束**，存量 7 套按 §2 登记不动；E2 trait 在落码时定删或改名 |
| C2 | **`code` 只放 HTTP 整数**；业务码一律 `error_code`，字符串、`UPPER_SNAKE`，**不含模块名、不复用语义、只增不改**（改名视为新增 + 旧值标 deprecated） | 建议即刻生效（新代码遵守；存量按 §2 登记） |
| C3 | **`message` 不得作为判据**：客户端只允许读 `error_code`；文案进 i18n（后端出 key 或前端按码取词），中文不再是协议 | 建议即刻生效 |
| C4 | **节点面（agent ↔ back）最小码集冻结**：验签失败 / 时间戳过期 / 重放 / 节点不存在 / bundle 版本不支持 / bundle 校验和不符 —— 六条必须是码而不是文本；当前实现全是自由文本（§1-E3） | ⏸ 需实现（P2/P3 前置） |
| C5 | **本机面（8096）码表冻结**：`need_login` / `not_running` / `unsupported` / `preflight_failed` / `port_conflict` —— 现在这些语义只存在于字符串里（`config_api.go:42` 用 `strings.Contains(err.Error(),"need_login")` 分类；Lua 用 `_svc_error_hint` 匹配 `private_key`/`node.conf`/`gnb_ctl`/`kmod-tun`，`mynet.lua:332-352`） | **已裁（B2）**：本机面走「改文档承认现状」，这张码表随 `spec/local-api.md` 重写时一并决定是否补进代码 |
| C6 | **死码清理**：GUI `4031/4032`、agent `shared/errors`、`utils.BaseResponse`、`errors.md`/`api-specification.md` 两份旧清单 —— 要么补上真实 emit，要么删干净。**back 侧两处已清**（2026-10-04：§3-8 那两个零注册的租户中间件连同其 5 个 `TENANT_*` 码一起删） | 可执行，无需裁定 |

## 6. 复现口径

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club

grep -rn "4031\|4032" mynet_api/app mynet_api/routes mynet_api/vendor/dsplat | wc -l   # 0 —— GUI 分支是死路
grep -rn "shared/errors" mynet_agent --include=*.go | wc -l                             # 0 —— 整包死码
cd mynet_api && php artisan route:list -v | grep -c NodeConfigController               # 6，而控制器里有 15 个方法
grep -rn "trans(" app/Modules/VPN/Controllers | wc -l                                   # 0
grep -rEn "errorResponse\('[^']*'\s*,\s*\[" app | wc -l                                 # 0（2026-10-04 已修 11 处）
```
