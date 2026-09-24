# 跨项目一致性问题清单（Cross-Project Issues）

> **维护说明**：本文件汇总 mynet 各子项目之间的契约偏差、规范不一致、待修复项。  
> **更新规则**：每次审计 / 重大重构后更新；问题修复后从「待修复」移到「已修复」并标注日期。

---

## 待修复（按优先级排序）


### 🟠 P1 — 影响安全性

#### 3. mynet_back CORS 允许所有来源
- **位置**：[mynet_back/config/cors.php](../../../mynet_back/config/cors.php)
- **现状**：`'allowed_origins' => ['*']`
- **风险**：生产环境跨站请求无限制
- **修复建议**：改为白名单（见 [.credentials/_templates/domains.md](../../.credentials/_templates/domains.md#cors-配置mynet_back)）
- **优先级**：上线生产前必修

#### 4. Token 刷新机制依赖 Sanctum 设计假设
- **背景**：mynet_back 实现的 `/api/v2/auth/refresh-token` 通过验证当前 Token 后签发新 Token
- **潜在风险**：若 Token 已过期则无法刷新（用户必须重新登录）
- **位置**：[mynet_back/routes/api.php](../../../mynet_back/routes/api.php) + auth controller
- **影响**：长期不活跃的客户端会被强制登出
- **修复建议**：方案 A — 延长 Sanctum 过期时间（已在 .env.example 设为 7 天）；方案 B — 引入 refresh token（需架构变更）

#### 5. mynet_back 生产 APP_KEY 与开发相同
- **现状**：`mynet_back/.env.production` 中的 `APP_KEY` 与开发 `.env` 完全一致（`base64:cbZh...`）
- **风险**：开发环境密钥泄露即可解密生产 session / 加密字段
- **修复建议**：在生产服务器执行 `php artisan key:generate --force`；同时 `.env.production` 中 APP_KEY 改为占位符（如 `BASE64:GENERATE_ON_DEPLOY`），由部署脚本生成
- **位置**：[mynet_back/.env.production](../../../mynet_back/.env.production)

#### 6. mynet_back 生产仍残留 dsplat.com 引用（部署脚本 / 文档）
- **范围**：`.env.production` 的 SESSION_DOMAIN / SANCTUM / VITE_REVERB_HOST 已修复（2026-05）
- **剩余位置**（不在本轮修复范围）：
  - `mynet_back/scripts/shell/{mynet,mynet_linux,mynet_xiaomi,gnb_setup,mynet_bash}.sh`：硬编码 `https://x.dsplat.com/gnb_conf/${NodeID}.tgz`
  - `mynet_back/scripts/deployment/{deploy-mod-local.sh,nginx-config.conf}`：nginx 路径 / 日志名
  - `mynet_back/docs/deployment/PRODUCTION_DEPLOYMENT_CHECKLIST.md`：5× `api.mynet.dsplat.com`
- **保留不动**：`MAIL_FROM_ADDRESS=arthur@dsplat.com`(用户真实邮箱),`docs/deployment/mail-configuration-guide.md` 中同左
- **修复建议**：单独 PR 处理 shell 脚本与部署文档,与 .env 修复解耦

#### 7. Reverb 开发/生产共用同一 App Secret
- **现状**：`oyzmmhlqzmar2uvanq2k` / `yabimhxofy2jud7f3lo5` 在 mynet_back 与 mynet_gui 的 `.env` 与 `.env.production` 中完全相同（且已提交到 git 历史）
- **风险**：开发凭证泄露 = 生产 WebSocket 被冒充
- **修复建议**：生产轮换 Reverb 三件套（app_id / key / secret）；并将 `.env` 移到 `.env.local` 不入仓
- **位置**：[mynet_back/.env](../../../mynet_back/.env)、[mynet_gui/.env](../../../mynet_gui/.env)

#### CPI-36. `guard.sh` 的 A1 规则把「路径」和「变量引用」误判成硬编码凭据 ✅ 已修复（2026-09-24）
- **位置**：[templates/.githooks/guard.sh](templates/.githooks/guard.sh)（即与 `mynet_agent`/`mynet_utc` 字节一致的那份）
- **现状**：A1 的判据是「口令类关键字 → 24 字符内出现 `=` → 引号包裹的内容 ≥8 字符」，只看**引号里有多长**，不看里面装的是什么。于是这两类**根本不含凭据**的写法会被拦下：
  - 指向口令文件的环境变量，值是**路径**（形如 `DB_PASSWORD_FILE=$ROOT/db.pass`）
  - 把口令转交给客户端的写法，引号内是**变量引用**而非字面量（形如 `MYSQL_PWD=$DB_PASS`）
- **实撞**：写 V3-5 备份链（`mynet_back/deploy/backup-drill.sh`）时连撞两次，最后逐个加 `GUARD-OK` 才提交（豁免记入 `.git/guard-exempt.log`）
- **连描述它的文档也会被拦**：本条目初稿把上面两个例子照原样（**带双引号**）写出来，`mynet_ctl` 的 pre-commit 立刻又被 A1 拦了一次 —— 也就是说这条规则**同时**没能区分「代码 vs 文档」，而 guard 自己的注释写着「只抓确凿模式，**避免撞注释/文档**」。为过闸，本节两个例子刻意省了引号（shell 里无空格时本来就可不加）
- **风险**：§2.6 明写「误报过多的守护会被 `--no-verify` 绕过，反而失效」—— A1 是最该常驻的一条，它的误报代价比其他规则都高
- **处置**：采纳建议 ①，收窄 A1D 为「**双引号内以 `$` 开头的一律不算**」（那是变量引用或命令替换，不是字面量）。建议 ②（排除 `_FILE`/`_PATH` 后缀）与 ③（跳过 `.md`）**未采纳** —— ① 已覆盖本轮全部四个误报；② 会漏掉「关键字带 `_file` 后缀、但等号右边确实是字面量口令」的写法；③ 会让**文档里的真凭据**漏网。
  **A1S（单引号版）故意不动**：单引号里 `$` 不展开，`'$DB_PASS'` 是字面字符串，可能真就是个叫这名字的密码，保守抓它才对。
- **实测验证**：13 例变异测试（用 guard 的真实输入形态，即带 `+` 前缀的行）——
  6 类真凭据**全部仍被抓**（双引号字面量、单引号字面量、`secret=` / `api_key=` / `token=` 三种关键字、缩进带空格版；值均为 ≥8 字符字面量，并含恰好 8 字符的边界例）；
  4 类误报**全部放过**（双引号变量、命令替换、指向口令文件的路径、`MYSQL_PWD` 转交）；短值仍放过。
  改完把 `backup-drill.sh` 里那 3 处 `GUARD-OK` 豁免**删掉**，A1 仍放行 —— 这本身就是对新规则的验证，也少了 3 条审计噪音。
- **不采纳 ③ 的代价，本轮亲身付了一次**：结案时我把变异测试用的假凭据（形如 `password=` 后接一个 13 字符字面量）**原样写进了本条目**，于是 pre-commit 又被 A1 拦下。guard 抓得对 —— 它无法区分「文档里举例」和「代码里真写了」，而这正是 ③ 想解决、我却否掉的那件事。结论不是改规则，而是**写文档时别贴完整的凭据赋值形式**（本条已改为描述式）。
- **⚠️ 一处需要更正的旧说法**：本条目初稿（以及更早的记录）写「**三份** `guard.sh` 字节一致（`templates/` · `mynet_agent` · `mynet_utc`）」—— 实际是 **9 份**：`templates/` + 8 个仓的 `.githooks/guard.sh`。其中 8 份 SHA256 完全相同（`86897f6379f5…`），`mynet_gnb` 那份因 B1 规则不同而 SHA 不同、但 **A1 段落一致**。所以改 A1 要同步 9 份，本次已用脚本同步并逐份核对。
- **优先级**：🟡 P2 → ✅ 已修（`mynet_back 52374e6`，9 份 guard.sh 随各仓同批提交）

---

### 📝 控制面落地发现的缺陷（2026-09-22 记录，发现即记）

> 来源：控制面任务链第 4/5 单元落地过程中撞出来的。**记录在前、处置在后**，
> 避免"当场只修了主问题、周边缺陷随上下文一起丢掉"。
>
> 本批 CPI-1 ~ CPI-8 全部已处置（见「已修复」表）；仅 CPI-7 保留为低优先级记录
> （当前全局 ID 上限恰好等于 float64 精确整数上限，暂无风险）。

#### CPI-22. `node_ips.ip_type` 三套分类互相矛盾，且两处引用了不存在的常量（R3 撞出）（**已修 2026-09-22**）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_back/app/Modules/VPN/Models/NodeIP.php`；`Domain/Services/RouteDomainService.php`；`app/Modules/Monitor/Services/MonitorHistoryService.php` |
| **现象** | 三套分类并存（模型 1/2/3、旧库注释 0/1、路由投影 node_ip/vpn_ip/lan_ip/wan_ip/endpoint_ip）；两处 `match` 引用不存在的 `IP_TYPE_WAN`/`IP_TYPE_ENDPOINT` → 遇 `ip_type=3` 即 `Error: Undefined constant` |
| **处置** | ① 模型冻结 1=vpn 2=lan 3=public，`IP_TYPE_WAN=IP_TYPE_PUBLIC`；endpoint 不是存储类型（learned 只进 Status）；② 两处 `match` 改用真实常量；③ 新增 `NetworkSemanticsProjector` + 六类语义 VO（interface / advertised / WAN / peer / relay / key algorithm），出口判据「不再从混合 route 列表猜语义」达成；④ 测试 `tests/NetworkSemanticsProjectorTest.php` 6 例 |

---

### 📝 T2-6 联调发现的缺陷（2026-09-24 记录，发现即记）

> 来源：T2-6「mynet_gui 四页真打通新 back」过程中撞出来的。
> 触发点是一个被误记为「TenantServiceController HTTP 500」的旧账——实测证明
> **控制器本身 12 个 action 全通**，500 来自两个全站性问题；顺着真浏览器联调
> 又挖出一串后端缺陷。全部按「记录在前、处置在后」入册。
>
> 本批 CPI-23 ~ CPI-29 已处置（见「已修复」表）；CPI-30 与 CPI-30a 已于 2026-09-24 修复（见下方 ✅ 标记与「已修复」表）；CPI-31 ~ CPI-35 待处置。

#### 已处置（本批）

| ID | 位置 | 现象 → 处置 |
|---|---|---|
| **CPI-23** | `mynet_back/.env`（缺失） | 仓库无 `.env` → `APP_KEY` 空 → **全站每个请求 500**（`MissingAppKeyException`，`EncryptionServiceProvider.php:83`），连 `/` 都不通。`phpunit` 走 `phpunit.xml` 的 env，**73/73 全绿也发现不了**。→ 建本地 dev `.env`（sqlite + file/sync）+ `key:generate`；`.env` 已被 `.gitignore:16` 挡住 |
| **CPI-24** | `mynet_back/bootstrap/app.php` | 未覆盖框架默认 `redirectGuestsTo(fn () => route('login'))`（`ApplicationBuilder.php:291`）。back 是纯 API 后端、无 `login` 命名路由，而 `Authenticate.php:104` 仅在请求**未**声明 `expectsJson` 时才调 `redirectTo` → **不带 `Accept: application/json` 的未鉴权请求抛 `RouteNotFoundException` → 500 而非 401**。→ 加 `$middleware->redirectGuestsTo(null)` |
| **CPI-25** | `mynet_back/app/Modules/VPN/Models/Node.php` | 只有 `scopeOnline()`/`scopeEnabled()` 两个**查询作用域**，无同名实例方法；`SocialDomainService` 三处（`:547`/`:700`/`:840`）按实例方法调用 → `BadMethodCallException` → `/social/friends/{id}/nodes` 与 `/social/friends/all/nodes` **双双 500**。GUI 捕获后回退空数组，表现为**连接向导「可用设备」恒 0、下一步永远禁用**——故障在后端、症状在前端。→ 补 `isOnline()`/`isEnabled()`，语义与 scope 对齐 |
| **CPI-26** | `SocialDomainService.php:397` | `'nodes_count' => 0, // TODO: 计算好友的节点数量` —— 硬编码 0，与 `getAllFriendsNodes():857` 的真算法口径不一致，好友列表永远谎报 0 节点。→ 改为批量查询，口径与 `:776-777` 一致（只计 ENABLED/ONLINE） |
| **CPI-27** | `mynet_back/config/cors.php` | 默认值 `'localhost,127.0.0.1'` **缺 scheme+port**，而浏览器 `Origin` 头永远是完整的 `http://localhost:5173`，精确匹配永不相等 → 预检返 204 但**无 `Access-Control-Allow-Origin`**，浏览器全线拦。→ 本地 `.env` 配 `CORS_ALLOWED_ORIGINS`；**生产白名单仍待办**（与本文件 P1 第 3 条同源，那条记录的 `['*']` 已过时） |
| **CPI-28** | `mynet_back/phpunit.xml` | `Default` suite（= `make test` = DoD 门禁 = pre-push）**不含** `app/Modules/VPN/tests/Feature`，43 个 HTTP 层测试长期游离在门禁外。「TenantServiceController 500」被误记成控制器缺陷正是因此无人把守。→ Feature 并入 `Default`，`make check` 从 73/2253 变为 **126/2398** |
| **CPI-29** | `mynet_back/tests/TestCase.php` | Orchestra Testbench **不加载 `bootstrap/app.php`** → 测试态与生产态静默分叉（`TestCase.php:65` 原有注释已承认，`long-range-plan.md:129` 也写过「e2e 用真实 bootstrap，不走 Testbench」）。→ 在 TestCase 里镜像 `redirectGuestsTo(null)`；**必须走框架的 `(new Middleware)->redirectGuestsTo(null)`**，因为它一次设三个静态回调（`Middleware.php:571-575`），异常渲染路径读的是 `AuthenticationException` 那个 |

#### 待处置（本批）

#### CPI-30. `mynet_gui` 的 `.env.development` / `.env.production` 是**永远不会被加载的死文件** ✅ 已修复（2026-09-24）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_gui/.env.development`、`mynet_gui/.env.production`（两者均被 git 跟踪） |
| **证据** | `@quasar/app-vite/lib/utils/env.js:31` → `buildType: ctx.dev ? 'dev' : 'prod'`；`:59-94` 的查找序为 `.env → .env.local → .env.[buildType] → .env.local.[buildType] → .env.[quasarMode] → …`。即 Quasar 认的是 **`.env.dev` / `.env.prod`**，不是 Vite/CRA 惯例的 `.env.development` / `.env.production`。实测 `quasar dev` 日志只报 `Using .env files: .env`，且注入值 `VITE_API_BASE_URL=http://api.mynet.local`（取自 `.env`）而非 `.env.development` 里的 `http://localhost:8000` |
| **处置** | 采纳建议 ①：`git mv .env.development .env.dev`、`git mv .env.production .env.prod`（rename 相似度 56%/58%，历史保留），并同步全部 9 处引用点。两个文件顶部各加注释说明 Quasar 的命名法，防止再按 Vite 惯例建死文件 |
| **实测验证** | ① dev 启动日志：`Using .env files: .env, .env.local, .env.dev`（改前为 `.env, .env.local`）② build 启动日志：`Using .env files: .env, .env.local, .env.prod`，`Build succeeded`，exit 0 ③ `grep -ro 'api\.mynet\.local' dist/spa/` → **0 次**；`dist/spa/assets/index-DQx2KfW3.js` 内含 `https://my.mynet.club/api/v1`，`websocket-DZ-9vTY9.js` 内含 `wss://my.mynet.club/wss` ④ dev 注入值 `curl http://localhost:5173/src/services/api.ts` → `VITE_API_BASE_URL: "http://localhost:8000"`、`VITE_ENABLE_HTTPS: "false"`、`VITE_CACHE_TIMEOUT: "30000"`、`VITE_LOG_LEVEL: "debug"`（全部是 `.env.dev` 的值，`.env` 里对应为 true/300000/error）→ `.env.dev` 确已生效 |
| **优先级** | 🟠 P1 → ✅ 已修 |

> ⚠️ **两处对原记录的实测更正**（都推翻了当初的推断，按 §2.1「下判断前先实测」补记）：
>
> 1. **生产侧原本并没有坏。** 原「影响」栏断言「`quasar build` 根本不读该文件 → 照现状部署前端会指向 `http://api.mynet.local`」——**不成立**。`quasar.config.ts:10-31` 的 `loadEnvProduction()`（2026-06-09 `dee1fb3` 引入）早就手工 `readFileSync('.env.production')` 填 `process.env`，而 `build.env` 在 `getBuildSystemDefine`（`env.js:163-166`）里**后写、覆盖 fileEnv**，所以产物一直是对的。实测对照：`git stash` 回到改前状态跑 `quasar build`，产物 hash 与改后**完全相同**（同为 `index-DQx2KfW3.js`，`api.mynet.local` 同为 0 次）。**真正的缺陷只在 dev 侧**（`.env.development` 死 → dev 注入 `.env` 的 `api.mynet.local`，这才是当初不得不建 `.env.local` 的原因），以及 prod 侧「正确但靠一个手写 dotenv 解析器兜着」的脆弱性。改名后 `.env.prod` 被 Quasar 原生加载，两条路径一致。
> 2. **`.env.local` 的优先级低于 `.env.dev`**（与 Vite 相反）。见下方「连带发现」。

#### CPI-30a. Quasar 的 env 优先级与 Vite **相反** —— `.env.local` 覆盖不了 `.env.dev` ✅ 已修复（2026-09-24）

| 项 | 内容 |
|---|---|
| **位置** | `@quasar/app-vite/lib/utils/env.js:59-94`（fileList 顺序）+ `:101-116`（`Object.fromEntries(fileList.flatMap(…))`，后出现的文件覆盖先出现的） |
| **证据** | fileList 顺序是 `.env`(1) → `.env.local`(2) → `.env.[buildType]`(3) → `.env.local.[buildType]`(4) → …。**第 3 位覆盖第 2 位**，即 `.env.dev` > `.env.local`。实测：`.env.local`（`VITE_API_BASE_URL=http://127.0.0.1:8080`）在场时 dev 注入的是 `http://localhost:8000`；把 `.env.local` 移开后注入值**一模一样** → `.env.local` 对同名 key 已是 no-op |
| **影响** | 本 workspace 原有的 `.env.local`（把 API base 指向本地 back 的 `127.0.0.1:8080`）**已失效**，dev 会去打 `localhost:8000`。任何按 Vite 惯例「用 `.env.local` 做个人覆盖」的做法在 Quasar 里都不成立 |
| **处置** | ① `mv .env.local .env.local.dev`（fileList 第 4 位，真的能覆盖 `.env.dev`）；② `.gitignore` 补 `.env.local.*`（原 `:13` 的 `.env.*.local` 要求以 `.local` 结尾，匹配不到 `.env.local.dev`，会有误提交风险）；③ 文件头注释改写，讲清「命名法」与「优先级相反」两层原因；④ `AGENTS.md` §4 的坑表同步更正（原写「本地覆盖用 `.env.local`」是错的） |
| **实测验证** | `git check-ignore -v .env.local.dev` → `.gitignore:44:.env.local.*`（确认仍不入库）；`.gitignore` env 段现为 `:12 .env.local` / `:13 .env.*.local` / `:44 .env.local.*` |
| **优先级** | 🟠 P1 → ✅ 已修 |

#### CPI-31. `/api/v1/users/onboarding` 500 —— 我方未完成实现（**不是**框架缺陷）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_back/app/Modules/VPN/Controllers/Api/V1/UserController.php:411`（及 `:441` 的 save） |
| **现象** | 调 `App\Services\UserService::getOnboardingState()` / `saveOnboardingState()`，而该 service 只有 `listUsers`/`getUser`/`updateUser` 三个方法；且 `user_onboarding`/`onboarding`/`user_settings` 三张表都不存在 |
| **易误判点** | 框架确有 onboarding，但那是 `TenantOnboardingService`（infrastructure 模块）——**租户开通引导**，Operator 驱动，步骤为 基础信息→域名→套餐→支付→创建 Tenant。与此处的**用户新手引导**（`completed`/`currentStage`/`selectedPath`/`stages`/`preferences`）是两个概念，**不可混用、不应上报框架** |
| **影响** | 每页控制台一条 500。GUI 已优雅降级（`stores/onboarding.ts:301` 捕获后回落 localStorage，实测 `State loaded: {source: localStorage, progress: 0}`），不挡功能 |
| **修复建议** | 待用户裁定：① 补齐（建表 + 两个 service 方法 + 测试）；② 删掉死 API 面（两条路由 + 两个控制器方法），GUI 改为不再调用 |
| **优先级** | 🟡 P2 |

#### CPI-32. 业务规则拒绝返回 HTTP 500（应为 409/422）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_back/app/Modules/VPN/Domain/Services/RouteRequestDomainService.php:103` 经 `BaseApiController::serverErrorResponse` |
| **证据** | 实测重复提交连接申请：`POST /api/v1/route/send-request` → **HTTP 500**，body `errors.exception = "该连接已经建立，无需重复申请"` |
| **影响** | 业务语义的正常拒绝被计入 5xx。上线后**监控与告警全是假阳性**，真实故障会被淹没；客户端也无法据状态码区分「重试无用」与「服务端坏了」 |
| **修复建议** | 给 `DomainException` 分类（冲突/校验/不存在/无权），在 `mapDomainExceptionToHttpResponse` 里映射到 409/422/404/403；`serverErrorResponse` 只留给真正未预期的异常。需配套 Feature 测试锁状态码 |
| **优先级** | 🟠 P1 —— 上线前必修（可观测性前提） |

#### CPI-33. `mynet_back/storage/` 子目录既未跟踪也未忽略 → 新克隆即坏

| 项 | 内容 |
|---|---|
| **位置** | `mynet_back/.gitignore:28,31` 只挡了 `storage/logs/` 与 `storage/framework/views/*.php` |
| **现象** | `git status` 显示 `?? storage/`；`storage/app`、`storage/framework/{cache,sessions,testing,views}` 均无 Laravel 骨架标配的 `*` + `!.gitignore` 存根 |
| **影响** | 新克隆缺 `storage/framework/sessions` 等目录。`SESSION_DRIVER=file` 时 `FileSessionHandler` **不会自动建目录** → 运行期报错。与 CPI-23 同属「新克隆即坏」一族 |
| **修复建议** | 按 Laravel 骨架补 5 个 `.gitignore` 存根（内容 `*` + `!.gitignore`），使目录结构入库而内容不入库 |
| **优先级** | 🟡 P2 |

#### CPI-34 / CPI-35. 框架侧两个「未声明的跨模块硬依赖」

已单独成文，详见 [`upstream-dsplat-issues.md`](upstream-dsplat-issues.md)（含 file:line 证据、复现命令、三种修法建议、可直接粘贴的提示词）。此处只登记对我方的影响：

| ID | 框架缺陷 | 对我方的影响 | 我方处置 |
|---|---|---|---|
| **CPI-34** | 主包 `AiStreaming` 的 4 个控制器注入 `AgentServiceContract`，全框架**零绑定**；`ResolveController:33` 还注入了框架里不存在的 `ToolRegistry` | `php artisan route:list` / `route:cache` **直接崩**，CI 路由审计与部分 IDE 插件不可用 | 改用 `grep` 模块 `Routes/api.php` 查路由。**不在 vendor 打补丁**（§1.8） |
| **CPI-35** | `module-auth/Models/User.php:20` 硬引用 `Notification\Models\DatabaseNotification`、`:18` 引用 `Billing\Models\CreditAccount`，但 `composer.json` 只声明依赖 `multi-tenant-saas` | `/api/v1/notifications/unread-count` 500；Laravel 数据库通知面整体不可用 | GUI 已降级。**不在 vendor 打补丁**，等上游修后 `composer update` |

> ⚠️ 两条都**不得**用「改 vendor」的方式绕过。`Class not found` 抛的是 `\Error`，
> 不被 `catch (\Exception)` 捕获——这也是为什么下游写了降级分支却仍然 500。

---

## 📐 架构设计决策（非 bug，易被误判）

#### mynet_gui 生产 VITE_API_BASE_URL=https://my.mynet.club（同源反代）
- **现状**：`mynet_gui/.env.prod` 中 `VITE_API_BASE_URL=https://my.mynet.club`（指向自身域名）
- **设计意图**：生产部署中 nginx 在 `my.mynet.club` 上对 `/api/*` 反向代理到 `api.mynet.club`，实现 same-origin 访问以规避浏览器 CORS preflight
- **决策日期**：2026-05（用户确认）
- ✅ **2026-09-24 复核：载体已修好（CPI-30）**。文件已从 `.env.production` 改名为 **`.env.prod`**
  （Quasar 的 `buildType` 只有 `dev|prod`，见 `@quasar/app-vite/lib/utils/env.js:31`），
  `quasar build` 现在**原生加载**它（启动日志 `Using .env files: .env, .env.local, .env.prod`），
  `quasar.config.ts` 的手工加载器与 4 个部署脚本的引用也已同步改名。
  实测产物：`dist/spa` 里 `api.mynet.local` **0 次**，`index-DQx2KfW3.js` 内含
  `https://my.mynet.club/api/v1`、`websocket-DZ-9vTY9.js` 内含 `wss://my.mynet.club/wss`。
- ⚠️ **一处需澄清的旧记录**：本条原先的复核备注断言「生产构建会退回 `http://api.mynet.local`」——
  实测**不成立**。`quasar.config.ts:10-31` 的 `loadEnvProduction()`（2026-06-09 引入）一直在手工读
  旧文件名填 `process.env`，而 `build.env` 在 `getBuildSystemDefine`（`env.js:163-166`）里覆盖 fileEnv，
  所以**生产产物改名前后字节相同**（同为 `index-DQx2KfW3.js`）。真正坏掉的是 **dev 侧**
  （`.env.development` 死文件 → dev 注入 `.env` 的 `api.mynet.local`）。决策本身（同源反代）始终成立。
- ⚠️ **仍未解决**：`.env.local` **覆盖不了** `.env.dev`（Quasar 优先级与 Vite 相反），
  本地个人覆盖须改用 `.env.local.dev` 并给 `.gitignore` 补 `.env.local.*`。详见 **CPI-30a**。
- **关联**：CORS 配置 issue #3 仍存在，但因 SPA 走同源路径，实际风险被反代屏蔽；严格白名单仍建议补上
- **TODO**：在 [architecture.md](architecture.md) 增加「反代部署模式」章节

### 🟡 P2 — 文档 / 规范

#### 5. mynet_back 文档分散
- **现状**：mynet_back 顶层散落临时 .md（如 `BACKEND_FIX_NEEDED.md`、多个 deploy shell 脚本注释长篇）
- **修复建议**：仿 mynet_gui 归档方式，集中到 `docs/_archive/`
- **优先级**：低（不影响功能）

#### 6. mynet_gui 根目录 70+ 临时报告
- **状态**：✅ 已修复（2026-05 审计中归档）
- **归档位置**：`mynet_gui/docs/_archive/{plans,reports,fixes,guides,misc}/`

#### 7. mynet_client 注释残留 openwrt 引用
- **状态**：✅ 已修复（2026-05 审计中清理）
- **位置**：`src/service/manager.js`、`src/service/platform/darwin.js`、`pack.sh`、`src/service/platform/index.js`

---

### 🟡 P2 — SSOT 自身一致性（2026-09-22 记录）

#### CPI-18 ~ CPI-21. 配置链路四个缺陷（proxy 域落地时全部撞出来）（均已修）
- **ID**：CPI-18 / 19 / 20 / 21
- **来源**：把「本机 API 的 proxy 域」真跑通，就必须走完配置的读→改→写整条链路，
  于是这四个各自独立、都会让配置"存了不生效"的缺陷全部暴露

| ID | 缺陷 | 症状 | 修法 |
|----|------|------|------|
| **CPI-18** | 仅凭 Bearer token **解析不出租户**：`IdentifyTenant` 第 6 步靠 `property_exists($user,'current_tenant_id')`，而框架与 back 的 User 都没该属性（Eloquent 字段在 `$attributes`，`property_exists` 恒 false） | 租户为空 → `TenantScope` fail-closed（`WHERE 1=0`）→ 用户层查询**静默返回空**、`Node::find` 得"节点不存在"，而 HTTP 是 200 | 新增 `App\Http\Middleware\ResolveTenantFromUser`：框架没能显式识别时用用户在 `tenant_users` 的活跃归属兜底；显式来源（域名 / `X-Tenant-ID`）仍优先 |
| **CPI-19** | `extractConfigByType` 对 `expert`/`sys` **恒返回空数组**（原注释："暂时返回空数组"） | 保存的 expert 字段（如 `proxy`）读取时取不到，只见默认值 → "存了 on 读回还是 off" | 按 `config/vpn.php` 定义归类；未定义字段仍归 base（宁可放错也不丢） |
| **CPI-20** | 节点通道渲染 node.conf 时**丢掉用户配置**：控制器 `array_merge($config['base'], $config['expert'])`，而 `getNodeFullConfig()` 返回的是 `node_info/network_config/additional_config/custom_config`，没有 base/expert 段 → 合并恒空 | **任何一次配置同步都会把节点 node.conf "还原"成默认**，用户改的配置被无声改回 | 改用 `custom_config`（normalize 后的扁平表，生成器正好吃扁平数组） |
| **CPI-21** | `NodeConfig::updateOrCreateRawConfig()` **被调用而从未定义**（与 CPI-17 同类的第二个） | `saveFullConfig` 这条保存路径同样抛 `BadMethodCallException`——两条路径都要有 | 补实现（含 forceSave 刷 `updated_at`） |

> 另修一处悬空命名空间：`VpnConfigGeneratorFactoryDomainService` 被写成
> `App\Domain\Services\VPN\...`（真位置 `App\Modules\VPN\Domain\Services\...`），
> 两处引用（`NodeMonitorController` 的路由渲染、`RouteController`）都会 fatal
> → **节点侧路由刷新此前是坏的**。

- **优先级**：已修（`84dd7170`）

---

#### CPI-17. 保存节点配置整条路径走不通（`updateOrCreateConfig` 从未定义）（已修）
- **ID**：CPI-17
- **位置**：[NodeConfigDomainService.php:123](../../../mynet_back-integration/app/Modules/VPN/Domain/Services/NodeConfigDomainService.php) 调用
  `NodeConfig::updateOrCreateConfig()`，而 [NodeConfig.php](../../../mynet_back-integration/app/Modules/VPN/Models/NodeConfig.php) 只有 `findByNodeId`
- **实测**：`BadMethodCallException: Call to undefined method App\Modules\VPN\Models\NodeConfig::updateOrCreateConfig()`
  → `POST/PUT /api/v1/nodes/{id}/config` 这条保存路径根本走不通
- **与 CPI-2/4/13 同一类**：声明了却做不到（这次是"调用了却没人实现"）
- **已修**：实现为**整体替换 `config`**（调用方是 `saveFullConfig`，传的是重建的全量数据；
  合并会让"清掉某项"永远做不到）。模型带 SoftDeletes 且 node_id 唯一，故从软删记录里找并恢复
- **优先级**：已修（`02319ece`）

---

#### CPI-16. 6 份 spec 的「适用」头仍指向已冻结的旧线（已标注）
- **ID**：CPI-16
- **位置**：`spec/{auth,wizard,service,proxy,plugins,debug-env}.md`
- **现状**：`spec/README.md` 声明唯一实现是 `mynet_agent`，但这 6 份文件的「适用」头写的是
  `mynet_lua` / `mynet_client` —— 已在 `integration-plan-v2.md` §2.2 冻结归档的旧线
- **影响**：读者（含 agent）按索引进到这些文件，会读到旧线做法并照着实现，
  做出与目标线架构相悖的形态。这不是"文档旧"，而是**索引与内容互相矛盾**
- **已做**：6 份文件顶部加横幅 + 逐份写明目标线对应契约；`spec/README.md` 索引增
  「适用线」列。**只标注、不改写内容**——"哪些旧步骤在新线下仍成立"属决策，
  而"这份文件描述的是谁"是事实
- **仍待**：是否把其中某些内容重新基于目标线重写（例如 service.md 的三层模型是否
  仍然成立、还是整体由驱动层承担）——需逐份判断
- **优先级**：中（会误导实现，但不影响运行）

---

### 🟡 P2 — 本机能力面落地时发现的前置（2026-09-22 记录）

> 本轮在把 `spec/local-api.md`（本机能力面）落地到 mynetd 时撞出来的。
> 原则同前：**先记录、再处理**；需要决策的不猜。

#### CPI-11. `service.*` 与「切换当前节点」依赖驱动生命周期接口 —— **按 spec 现在就不该实现**
- **ID**：CPI-11
- **现状**：本机 API 的 `service.start/stop/reload` 端点已按契约接线，但
  `domain.VPNLifecycleDriver`（Start/Stop/Reload/Capabilities/WriteConfig/Status）
  **尚无任何实现**——`GNBDriver` 有 Capabilities/WriteConfig/Status，缺 Start/Stop/Reload，
  实测不满足该接口。本机 API 的做法是类型断言取能力，取不到返回稳定码 `unsupported`
  并点明原因，**不代驱动展开 gnb 机制**（契约明确禁止）。
- **为什么现在不该做（查过 spec 后更正）**：`driver-abstraction.md §5` 的落地顺序是
  **R0–R9**，而生命周期权威收敛是 **R5**，排在 R2（`BundleEnvelope` + `NetworkIntent`
  schema v2）与 R4（原子 Apply：stage→validate→write→reload→health-check→commit/rollback）
  之后。该 spec 明确写着：「**在 R2–R6 完成前，不应继续实现 `WireGuardDriver`；
  否则会把错误抽象固化进代码**」——同理，现在补 `Start/Reload` 会绕过 R4 的原子 Apply
  与 R5 的写入权收敛，等于把两套生命周期权威固化下来。
- **因此**：这不是"缺一个补丁"，而是**被已排期的工作流挡住**。本机能力面除这两项外已齐。
- **顺带更正**：`internal/domain/vpn_lifecycle_driver.go` 的注释原引用 S1/S3/S4/S5
  并称"见 spec §5"，而该章节早已改写成 R0–R9——过期引用，已更正（`988a543`）。
- **优先级**：记在案（非本机 API 的债；等 R2–R5）

#### CPI-7. 命令 ID 经 float64 往返
- **ID**：CPI-7
- **现状**：agent 的 `monitor.Command.ID` 是 `interface{}`，JSON 数字解码成 float64；
  `Execute()` 再 `strconv.FormatInt(int64(v))` 转回字符串
- **当前安全性**：全局 ID 上限 `2^53-1 = 9007199254740991`，**恰好是 float64 精确整数上限**，
  所以当前不会丢精度（实测回调 ID 正确）
- **风险**：一旦 ID 生成范围越过 2^53 就会静默串号。建议 `Command.ID` 改 `json.Number` 或 string
- **优先级**：低（记在案，避免以后扩大 ID 范围时踩）

## ⏸️ 暂缓（用户决策不实现）

### mynet_lua 定时心跳上报
- **决策日期**：2026-05
- **决策来源**：用户明确表示「mynet_lua 心跳先不实现」
- **背景**：spec/plugins.md 定义 client 端定时心跳，mynet_client 已实现，lua 侧暂缺
- **影响**：OpenWrt 节点不主动上报存活；back 仅通过 GNB 隧道流量间接判断
- **重新启用条件**：用户后续主动提出 / 出现因缺心跳导致的业务问题
- **关联 spec**：[spec/plugins.md](spec/plugins.md)

### mynet_lua 远程命令处理（route.refresh / client.update / client.report）
- **决策日期**：2026-05（与心跳一并暂缓）
- **背景**：mynet_client 通过 `cmdLoop` 拉取 `/api/v2/nodes/{id}/commands` 处理；lua 侧未实现
- **影响**：OpenWrt 节点收不到 back 下发的命令；需手动操作或重启服务
- **重新启用条件**：同上

---

## 已修复

| 修复日期 | 问题 | PR / Commit |
|---------|------|------------|
| 2026-05 | mynet_gui 根目录文档归档 | 本次审计 |
| 2026-05 | mynet_client openwrt 注释清理 | 本次审计 |
| 2026-05 | mynet_back 缺 .env.example | 本次审计（新增） |
| 2026-05 | ctl 凭证集中目录建立 | 本次审计（新增 `.credentials/`） |
| 2026-05 | mynet_lua `/api/v1` → `/api/v2`（config.json + deploy.md） | 本次治理 |
| 2026-05 | mynet_lua 下载 URL `download.mynet.club` → `ctl.mynet.club/d` | 本次治理 |
| 2026-05 | mynet_back `.env.production` SESSION_DOMAIN/SANCTUM 域名 dsplat → mynet.club | 本次治理 |
| 2026-05 | mynet_back `.env.production` VITE_REVERB_HOST 一致性修正 | 本次治理 |
| 2026-09-22 | back: monitor 路由被模块级 `auth:sanctum` 覆盖 → 节点无 token 全 401（含心跳） | `836adc52` |
| 2026-09-22 | back: 请求体 gzip 从未被解压 → 真实心跳（必然 >1KB）验证阶段 422 | `836adc52` |
| 2026-09-22 | back: `Services/NodeMonitorDomainService` 空壳遮蔽 `Domain/Services/` 真实现 → 带 node_monitor 的心跳必 500 | `836adc52` |
| 2026-09-22 | back: 节点通道无租户上下文，`TenantScope` fail-closed（`WHERE 1=0`）→ 节点状态永不落库 | `8c40ff72` |
| 2026-09-22 | back: `node_monitor_snapshots` 同秒唯一键 + `create()` → 第二次心跳回滚整事务 | `8c40ff72` |
| 2026-09-22 | back: `monitor_rt_cmds` PK 重命名残留（`where('id')` / `->id` / `'creator'` / `'id'=>uuid`）→ 心跳/回调/指令历史全 500、配置变更命令静默丢失 | `c82a30f7` |
| 2026-09-22 | back: `NodeConfigService` 的 `params` 双重编码 → 节点收到字符串而非对象 | `c82a30f7` |
| 2026-09-22 | agent: 网关中继可被节点用 `X-Node-Id` 头冒充别的节点 | `b7aceeb` |
| 2026-09-22 | agent: gatewayclient 事件合并竞态 → 并发时事件永久丢失 | `d495bd8` |
| 2026-09-22 | agent: gatewayclient 取消 ctx 不生效 → SIGTERM 后卡满 180s 读超时 | `d495bd8` |
| 2026-09-22 | 响应性联动断裂（事件系统删除后节点上线不再触发对端 route.refresh） | `8c40ff72` |
| 2026-09-22 | **CPI-1** 节点启停/重启后台功能整体不可用（`App\Models\Node` 不存在） | `c10aaa45` |
| 2026-09-22 | **CPI-3** `App\Services\NodeRebootService` 三处悬空引用 | `c10aaa45` |
| 2026-09-22 | **CPI-2** back 下发 `mynet.reboot`、agent 只有 `mynet.restart` → 重启静默失效 | `24570da3` |
| 2026-09-22 | **CPI-4** back 命令词汇整体漂移（mynet.boot/shutdown/regenConfig/reloadConfigs/plugin.proxy.reload/upload-log） | `24570da3` |
| 2026-09-22 | **CPI-5** 命令输出没回传 back（`Execute` 把 result 硬写成"命令执行成功"） | `b407e91` |
| 2026-09-22 | **CPI-6** 节点通道 limit 按 IP 60/分（中继后即 300 节点打满）→ 改按已验签节点 180/分 | `fb97a776` |
| 2026-09-22 | **CPI-8** 心跳未收敛到中继 → `relay_heartbeat`（默认开）让心跳也走网关 | `9a3d4e8` |
| 2026-09-22 | **CPI-12** relay 域缺 node→back 用户层鉴权链路 → 已定设计并实现（节点持用户 token，网关按路径透传 Authorization） | 见 relay 系列提交 |
| 2026-09-22 | 本机能力面 relay 域 + relaypolicy 单一策略来源（网关/本机两道闸） | 见 relay 系列提交 |
| 2026-09-22 | **CPI-9** proxy 域落地（标志归 back，node.conf 为投影；读→改→整份写回→同步） | `c335296` |
| 2026-09-22 | relay 补上漏掉的限流（固定窗口 20/秒，超限 429 `rate_limited`） | `c335296` |
| 2026-09-22 | **CPI-18~21** 配置链路四个缺陷 + 一处悬空命名空间 | `84dd7170` |
| 2026-09-22 | **CPI-10** nodes/zones 域落地（zone ≡ tenant；relay 之上的具名薄封装 + 本机态标记） | `4c3025f` |
| 2026-09-22 | **R1** GNB P1/P2/P3 与 WG 能力探测落地；`Capabilities()` 改为反映实测（`OpenGNBDriver` 改名、不伪造 gnb 产物） | agent `e85eb5a` |
| 2026-09-22 | **R1 出口判据达成**：对真实编出的 gnb 二进制实测 `hot_restart=true, route_proxy=true, log_hot_config=false` | agent `2840888` |
| 2026-09-22 | 本机能力面骨架落地（信封/token 鉴权/HTTP/传输无关 Service 层） | `ee5467e` |
| 2026-09-22 | 本机能力面 service/config 两域 | `28afbb5` |
| 2026-09-22 | 本机能力面 peers 域 | `790edbd` |
| 2026-09-24 | **CPI-23** back 缺 `.env` → `APP_KEY` 空 → **全站每个请求 500**（测试全绿也发现不了） | 本地操作（`.env` 不入库） |
| 2026-09-24 | **CPI-24** 未覆盖框架默认 `redirectGuestsTo` → 不带 `Accept` 头的未鉴权请求 500 而非 401 | `c98a30b` |
| 2026-09-24 | **CPI-25** `Node` 只有 scope 无实例方法 → 两个好友节点端点 500 → **连接向导「可用设备」恒 0、下一步永远禁用** | `30e3f88` |
| 2026-09-24 | **CPI-26** `SocialDomainService:397` 的 `nodes_count` 硬编码 0（原 `// TODO`） | `30e3f88` |
| 2026-09-24 | **CPI-27** `config/cors.php` 默认值缺 scheme+port → 预检无 ACAO 头，浏览器全线拦（本地已配，生产白名单仍待办） | 本地 `.env`；生产待办（H2 之后） |
| 2026-09-24 | **CPI-28** Feature 套件（43 测试）长期游离在 `Default` 门禁外 → 已并入，`make check` 73/2253 → **126/2398** | `c98a30b` |
| 2026-09-24 | **CPI-29** Testbench 不加载 `bootstrap/app.php` → 测试态/生产态分叉；已在 TestCase 镜像（走框架 `Middleware` 入口以对齐三个静态回调） | `c98a30b` |
| 2026-09-24 | **CPI-30** `mynet_gui` 的 `.env.development`/`.env.production` 是死文件（Quasar 只认 `.env.dev`/`.env.prod`）→ `git mv` 改名（rename 56%/58%，历史保留）+ 同步 9 处引用点（`quasar.config.ts` 手工加载器、`deployment.sh`、`quick-deploy.sh`、`pre-deploy-check.sh`、`verify-debug-disabled.sh`、`.env.example`、`docs/UPGRADE-v2.md`、`docs/06-部署指南.md`、`.github/copilot-instructions-详细版.md`）。实测：dev 日志 `Using .env files: .env, .env.local, .env.dev`；build 日志 `… .env.prod`；`dist/spa` 里 `api.mynet.local` **0 次**、`https://my.mynet.club/api/v1` 已烘入 | `mynet_gui d0961bf` |
| 2026-09-24 | **CPI-30a** Quasar 的 env 优先级与 Vite **相反**：`.env.dev` 覆盖 `.env.local`（`env.js:101-116` 的 `Object.fromEntries(fileList.flatMap(…))`，fileList 里 `.env.local` 第 2 位、`.env.[buildType]` 第 3 位，后者胜出）。本地覆盖必须写 `.env.local.dev`，而它**不被 `.gitignore:13` 的 `.env.*.local` 匹配** → 有误提交风险 | `mynet_gui d0961bf` |
| 2026-09-24 | **T2-6 达成**：mynet_gui 四页真打通新 back，连接向导四步走通并落库（`req=4456240307586718 status=pending`） | 任务达成（载体 `mynet_gui`；产物散在 CPI-23~30 各行） |
| 2026-09-24 | **CPI-36** `guard.sh` 的 A1 把「路径」「变量引用」误判成硬编码凭据 → 收窄 A1D（双引号内以 `$` 开头的不算），A1S 故意保持严格；9 份 guard.sh 同步；13 例变异测试 | back `52374e6`（9 份 guard.sh 随各仓同批提交） |

---

## 编码硬规矩（用户明示，2026-09-22）

1. **有副作用的入口不要叫 `New<Type>`**。用户原话：「看见 NewGnbDriver 这种命名我害怕」。
   `New*` 必须是**无副作用的纯构造**；会去磁盘定位工具/文件、可能失败的入口，
   用 `Open*` / `Discover*`（对齐 Go 惯例 `os.Open` / `sql.Open`），把代价写进名字里。
   只要一个纯结构体就**直接 `&Type{...}`**，别绕那个发现式构造函数。
   （已落实：`NewGNBDriver` → `OpenGNBDriver`，见 agent `e85eb5a`）
2. **gnb 构建与回滚（2026-09-22 记录）**：我们用的是**自己补丁过的树**（P1 v2 热重启 + P2 proxy
   路由放行）。`mynet_gnb` 通常只读，本次是用户明示「用我们自己 patch 过的代码去编译」后才动的：
   - 目标目录是 `mynet_gnb/src`（= 上游树根，含 `Makefile.inc` 与 `src/`）
   - **p2 的 `apply.sh` 必须用 `bash` 跑**（用了 `mapfile < <(...)`，`sh` 下直接语法错）
   - `make -f Makefile.Darwin install` 只拷到 `src/bin/`，不碰系统路径 ✓
   - 回滚：各补丁目录下的 `revert.sh`（p1 v2 / p2 各一份）
   - ⚠️ gnb / gnb_ctl / gnb_es 的 ctl block 共享内存布局变了，**必须来自同一份构建**
   - 实测能力：P1=true、P2=true、P3=false（未打 P3，符合预期）
3. **测试不得伪造 gnb 产物**。用户原话：「gnb.map 是 gnb 产物，不能手动修改」。
   `gnb.map`、`gnb_ctl` 等是 gnb 自己产出/安装的东西，测试里不能手搓来"凑安装现场"。
   需要验证"探测/读取"逻辑时，只放**探测夹具**（例如含补丁特征串的假二进制），
   并写明它不是 gnb 产物、也不会被执行。

---

## 维护工作流

```
发现跨项目偏差 / 规范冲突
  ↓
0. ★ 先记录，再处理。发现当场就写进本文件「待修复」——不要先动手修、
   更不要把没修的留在脑子里或对话里（上下文一丢就没了）
1. 在「待修复」加条目：ID / 位置 / 现状 / 证据 / 影响 / 修复建议 / 优先级
2. 链接相关 spec / 代码位置；同类问题交叉引用已有文档（如
   mynet-back-namespace-drift.md），不要重复记账
3. 修复完成后移到「已修复」表，commit message 里带上条目 ID。
   ⚠️ **但移表时通常还没提交**，于是第三列只能先写「待提交」占位。
   这一步有个已真实发生的失效：**提交后没人回头把 sha 填上**。
   2026-09-24 清理时发现「已修复」表里 **10 行**的占位从写下那天起就没再动过
   （跨越 2 个仓、4 次提交），台账的「已修复」栏因此整体不可信。
   → 规矩：**写「待提交」的同时就把它当成待办**，commit 完当轮回填短 sha；
   或者干脆等 commit 完再移表（推荐，少一次往返）。
4. 必要时更新 spec/* 或 conventions.md
```

**ID 约定**：`CPI-<n>`（Cross-Project Issue 序号）。新建条目取当前最大值 +1，
只在"待修复 / 暂缓"里分配；修好后 ID 随条目一起进「已修复」表，便于 commit 引用。

**反例（应当避免）**：先在代码里把主问题修掉，顺手发现的周边缺陷只在对话里说一句 ——
下一轮换个上下文就找不回来了。本轮（2026-09-22）那些 CPI 条目就是在补这个课。

**自动校验（2026-09-24 换载体）**：

命令词汇的守护**已从 PHP 脚本改为 agent 仓内的 Go 测试**。

- ❌ 已退休并删除：`scripts/check-command-vocabulary.php`、`scripts/check-command-invocations.php`
- ✅ 现载体：`mynet_agent/mynetd/commands/backmap_test.go`（随 `make check` 每次跑）

**为什么换**（用户裁定，2026-09-24）：

1. **节点/客户端没有 PHP 环境**。命令名的唯一真值来源在 agent（`commands.Plan` 的 switch），
   守护必须长在真值旁边、能在 agent 仓里跑——PHP 脚本做不到。
2. **两个脚本此前已永久失效**：默认路径是旧布局的 `mynet_{back,agent}-integration`
   （新 workspace 里没有这个后缀），且它们依赖的 SSOT `conf/command.*.json`
   在 D-6「内化 bin/mynet 命令链」后**已不存在**。实测两者恒 `exit 2`。
3. **从未接进任何门禁**：全 workspace 只有本文档提到它们，无 CI / Makefile / hook 引用
   ——这正是「永久 exit 2 却无人发现」的原因。

新守护做三件事：① back 会下发的 13 条命令逐条断言能映射到 agent 认识的 Op 且能被
`Plan()` 展开成 argv；② 用 `go/ast` 解析 `commands.go`，强制 `knownOps` 列表与
`Plan()` 的 switch **双向同步**（改一边不改另一边即失败）；③ 断言不认识的命令
（含历史上真实漂过的 `mynet.reboot`）**必须显式失败**而非静默忽略。
已做变异测试验证守护真能拦住两个方向的漂移。

> 仍是 CPI-2 / CPI-4 的防复发手段：命令名写错的后果是「节点侧静默失效、back 侧看不出来」，
> 只靠人眼迟早再漂一次。区别是现在它**每次 `make check` 都会跑**，不再依赖有人记得手动执行。
