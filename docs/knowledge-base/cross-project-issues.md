# 跨项目一致性问题清单（Cross-Project Issues）

> **维护说明**：本文件汇总 mynet 各子项目之间的契约偏差、规范不一致、待修复项。  
> **更新规则**：每次审计 / 重大重构后更新；问题修复后从「待修复」移到「已修复」并标注日期。

---

## 待修复（按优先级排序）

### 2026-10-10 跨平台 review 新登记（R2–R5/R7–R12 对应项已修并移入「已修复」表，2026-10-13 归位；仍开：CPI-71/R1、CPI-80/R10 部分（CPI-76/R6 已修 2026-10-13））

逐项源码证据、静态/复现边界及本轮测试见 [workspace 审查报告](workspace-review-20261010.md)；执行边界见 [跨平台规划](spec/platform-lifecycle-build-playbook.md)。本批不据已有测试绿勾选平台完成。

| ID | 优先级 | 问题与报告项 | 归属 |
|---|---|---|---|
| CPI-71 | P1 | **部分已修 2026-10-13**：C-2 签名薄桥、guest 路由唯一写面、桥 ACL 三方 19/19 一致；config/connect/apply 映射在 `mynet.uc`。**仍开**：真机合同字段/失败语义全量 | agent/OpenWrt |
| ~~CPI-76~~ | ~~P1~~ | ✅ **已修 2026-10-13**：自更新只取 archive（zip/tgz），拒绝 installer；HostPlatform 区分 openwrt≠linux；仅 ipk 时宁缺勿装错（agent `285029b`） | 已修复 |
| CPI-80 | P1 | GNB Wintun初始化失败分支调用未初始化函数指针（R10，静态确认、真Windows未验）。**随包缓解**：安装器带 wintun.dll（G-10/R10，`mynet_desktop/nsis/vendor/wintun/`）；gnb `src/` 未改，根治待上游/补丁包裁定 | GNB补丁包/上游，H3 |


### 🟠 P1 — 影响安全性

#### 3. mynet_api CORS 允许所有来源
- **位置**：[mynet_api/config/cors.php](../../../mynet_api/config/cors.php)
- **现状**：已改 `CORS_ALLOWED_ORIGINS` 环境变量白名单（`config/cors.php:6`，缺省 `localhost,127.0.0.1`；2026-10-13 实测）；`['*']` 只在 env 显式给空串时出现
- **风险**：生产 `.env` 白名单**仍待配置**（与 CPI-27 同源）
- **修复建议**：改为白名单（见 [.credentials/_templates/domains.md](../../.credentials/_templates/domains.md#cors-配置mynet_back)）
- **优先级**：上线生产前必修

#### 4. Token 刷新机制依赖 Sanctum 设计假设
- **背景**：mynet_api 实现的 `/api/v2/auth/refresh-token` 通过验证当前 Token 后签发新 Token
- **潜在风险**：若 Token 已过期则无法刷新（用户必须重新登录）
- **位置**：[mynet_api/routes/api.php](../../../mynet_api/routes/api.php) + auth controller
- **影响**：长期不活跃的客户端会被强制登出
- **修复建议**：方案 A — 延长 Sanctum 过期时间（已在 .env.example 设为 7 天）；方案 B — 引入 refresh token（需架构变更）

#### 5. mynet_api 生产 APP_KEY 与开发相同
- **现状**：`mynet_api/.env.production` 中的 `APP_KEY` 与开发 `.env` 完全一致（`base64:cbZh...`）
- **风险**：开发环境密钥泄露即可解密生产 session / 加密字段
- **修复建议**：在生产服务器执行 `php artisan key:generate --force`；同时 `.env.production` 中 APP_KEY 改为占位符（如 `BASE64:GENERATE_ON_DEPLOY`），由部署脚本生成
- **位置**：`mynet_api/.env.production`（生产机密，**不入仓**；本地如有则在该路径）

#### 6. mynet_api 生产仍残留 dsplat.com 引用（部署脚本 / 文档）
- **范围**：`.env.production` 的 SESSION_DOMAIN / SANCTUM / VITE_REVERB_HOST 已修复（2026-05）
- **剩余位置**（不在本轮修复范围）：
  - `mynet_api/scripts/shell/{mynet,mynet_linux,mynet_xiaomi,gnb_setup,mynet_bash}.sh`：硬编码 `https://x.dsplat.com/gnb_conf/${NodeID}.tgz`
  - `mynet_api/scripts/deployment/{deploy-mod-local.sh,nginx-config.conf}`：nginx 路径 / 日志名
  - `mynet_api/docs/deployment/PRODUCTION_DEPLOYMENT_CHECKLIST.md`：5× `api.mynet.dsplat.com`
- **保留不动**：`MAIL_FROM_ADDRESS=arthur@dsplat.com`(用户真实邮箱),`docs/deployment/mail-configuration-guide.md` 中同左
- **修复建议**：单独 PR 处理 shell 脚本与部署文档,与 .env 修复解耦

#### 7. Reverb 开发/生产共用同一 App Secret
- **现状**：`oyzmmhlqzmar2uvanq2k` / `yabimhxofy2jud7f3lo5` 在 mynet_api 与 mynet_gui 的 `.env` 与 `.env.production` 中完全相同（且已提交到 git 历史）
- **风险**：开发凭证泄露 = 生产 WebSocket 被冒充
- **修复建议**：生产轮换 Reverb 三件套（app_id / key / secret）；并将 `.env` 移到 `.env.local` 不入仓
- **位置**：[mynet_api/.env](../../../mynet_api/.env)、[mynet_gui/.env](../../../mynet_gui/.env)

#### CPI-37. `node_keys.algorithm` 与密钥实际类型不符 → 节点面**全 401**，且症状不指向真因
- **位置**：`mynet_api/app/Modules/VPN/Domain/Services/NodeFaceAuthService.php:40-57`（`sharedSecret`）+ `node_keys.algorithm` 列
- **现状**：`sharedSecret()` 按 `algorithm` 字段选分支 —— `ed25519` 会先做 `edwardsToMontgomeryBin($peer)`，`x25519` 直接用。但**没有任何机制保证这个字段与密钥的实际类型一致**。本地库实测：5 行标着 `algorithm='ed25519'` 的密钥，其 `public_key` 实际是 X25519（montgomery）形式 —— 用 `sodium_crypto_scalarmult_base(priv) == pub` 验证成立，而 ed25519 公钥不会满足这个等式。
- **后果**：后端对一个**已经是 montgomery** 的点又做一次 edwards→montgomery 转换，得到错误的点 → `shared` 与节点侧算出的不一致 → HMAC 不匹配 → **每个节点面请求都 401**。
- **为什么难查**：症状是「鉴权失败」，日志里没有「algorithm 标错了」这一说。V4-2 全链路验证时第一个撞上的就是它（401），排到密钥配对、排到签名体、最后才定位到这个字段。
- **修复建议**：① 写入 `node_keys` 时**校验** algorithm 与实际密钥一致（公钥可以反推：满足 `scalarmult_base(priv)==pub` 的是 X25519）；② DH 失败时把 `algorithm` 与公钥形态（长度/编码）一起记进日志，让症状指向真因；③ 补文档说明「节点面密钥与驱动密钥」的真实关系 —— §1.4 写「复用驱动密钥（同一套）」，但 gnb 的 128-hex `clamp(SHA512(seed))` 布局**不能**直接用于 X25519 DH，wg 的 32B 可以。这条差异目前没有任何文档写清。
- **优先级**：🟠 P1（会让节点面整体不可用，且极难定位）

#### CPI-38. 心跳里**缺** `vpn_interfaces` 段被判成「节点离线」，而该字段本是可选的
- **位置**：`mynet_api/app/Modules/VPN/Controllers/Api/V1/NodeHeartbeatController.php:421-432`（`isVpnInterfaceDown`）+ `:88-108` 的调用处
- **现状**：`isVpnInterfaceDown()` 的第一条是 `if (empty($vpnInterface)) return true;`（注释写「如果没有 VPN 接口信息，视为网络不可用」）。调用处一旦为真就 `markNodeOffline()` 并**直接 return**，连 `node_monitor` 都不处理。
- **矛盾点**：`vpn_interfaces` 在 `HeartbeatRequest::rules()` 里是 **`nullable|array`（可选）**。也就是说「客户端没报这个可选字段」被当成了「节点故障」—— 这两件事不是一回事。
- **实际触发路径**（V4-2 验证时撞到）：agent 侧非 root 时 `wg show <iface> dump` 会 Permission denied，拿不到 peer；而 `ifname` 是 `required_with`，空值会 422，所以整个 `vpn_interfaces` 段不发 → 后端判离线 → **指标数据全丢**。表现为「节点明明在发心跳、后端也返 200，但 Redis 里没有它」。
- **已做的规避**：agent 侧改为「读不到 peer 也照报接口存在」（`mynet 141b9ed`）—— 少报一个数字只是数据不全，误报离线是**状态错误**，后者严重得多。但后端侧的语义仍在，任何不发这个段的客户端都会中招。
- **修复建议**：区分「没报接口信息」与「报了且 `status=down`」。前者应只记 warning 并**照常处理 node_monitor**；后者才判离线。
- **优先级**：🟠 P1（静默丢数据 + 误报离线，且 HTTP 层返回 200 看不出异常）

#### CPI-39. 框架的模块依赖检查**每次请求刷 8 条 WARNING**，一天 2224 条
- **位置**：`vendor/dsplat/multi-tenant-saas-module-infrastructure/Services/ModuleRegistry.php:317`（框架侧，按 §1.8 **只读**）
- **现状**：按需 module（§1.6：31 个只留 3~4 个）砍掉 27 个模块后，框架仍按**完整模块表**做依赖检查，于是每次请求都记 8 条形如
  `[ModuleManager] 模块 [activity_plan] 依赖 [ai], 但该模块未安装` 的 WARNING。
  实测 2026-09-24 一天的日志里这类行 **2224 条**（8 个模块 × 278 次请求）。
- **影响**：① 真错误被淹 —— V4-4 做结构化日志的意义之一是能过滤，但噪音本身仍占空间与 IO；② **WARNING 级别失去信号价值**（一天两千多条 WARNING 等于没有 WARNING）；③ 日志文件膨胀
- **修复建议**：① 首选**回上游**（§1.8）：`ModuleRegistry` 应只对**已启用**的模块做依赖检查，或把「模块本身未启用」这种情况降到 debug；② 本地临时降噪：把该 channel 的 level 提到 error，或在 `config/logging.php` 里加过滤
- **已记入** `upstream-dsplat-issues.md`（框架缺陷走上游）
- **优先级**：🟡 P2（不影响功能，但持续污染日志、削弱告警信号）

#### CPI-40. 我方迁移与框架模块迁移**建同一张表** → 全新库 `migrate` 必失败（H5 的硬阻塞）
- **位置**：`mynet_api/database/migrations/`（我方 25 张建表）↔ `vendor/dsplat/*/database/migrations/`（框架 23 张，用**裸 `CREATE TABLE`** 写，所以 grep `Schema::create` 找不到）
- **撞车的 5 张**：`tenants` · `tenant_users` · `users` · `system_settings` · `tenant_settings`
- **实测**：在隔离的临时 MySQL 8.4.8 上跑 `php artisan migrate --force`，第 6 个迁移即
  `SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'tenant_settings' already exists`
  （我方 `2025_01_01_000003_create_tenant_settings_table` 先跑，框架 `2025_01_01_000008_infrastructure_module` 后撞）。
  复现工具：`mynet_api/deploy/gen-schema.sh`（失败时会点名撞车表并给出两侧建表名的取法）。
- **为什么一直没暴露**：本地开发用 sqlite 且库是既有的；`phpunit` 走测试库；生产**禁止 migration**（`deployment-registry.md:63`）。只有「从零建一个 MySQL 库」这条路会撞上 —— 而 H5 恰恰就是它。所以 **B-1 的验收判据「新库可从零建」实测不成立**。
- **修复方向（属归属决策，不在本轮替人定）**：vendor 只读（§1.8），所以只能动我方那 5 个迁移。**删之前必须逐列比对**我方定义与框架定义的差异 —— 我方可能加了业务列，直接删会静默丢字段。
- **✅ 已修**（`mynet_api 3466a76`）：删掉 11 个重复 create 迁移 + 1 个无守卫的 ALTER
  （`add_auth_security_fields_to_users` 加的三列框架 users 全有）；新增
  `2026_09_25_000001_add_mynet_columns_to_framework_tables` 用 `hasColumn` 守卫把
  3 个我方独有列补回（`tenants.custom_domain`、`tenant_users.role`、`users.role`）。
  实测：`deploy/gen-schema.sh` 在隔离临时 MySQL 8.4.8 上 migration 从「第 6 个即失败」
  变成 **56 条全 DONE**，导出 DDL 135352 字节 / 105 张表，18 张期望表与
  `node_keys` 的三个生命周期列全在；`make check` 仍 154 tests / 2503 assertions 全绿。
- **优先级**：🟠 P1 → ✅ 已修（H5 的建库阻塞解除，DDL 已产出 `deploy/schema/mynet_api-schema.sql`）

#### CPI-41. 生产 `api.mynet.club` 上跑的后端 **没有业务面**，V2 从未上线
- **实测（2026-09-25 外网探测）**：`/api/v1/nodes`、`/api/v1/mynet/health`、`/api/v1/social/friends`、`/api/v1/monitor/node/info`、`/api/v1/services`、`/api/v1/users/onboarding` **全 404**（新旧两种路径形状都试过）；只有框架的 `/api/v1/auth/me` 返 401、`/api/v1/auth/login` 返 405（说明路由存在）。`/up` 返回标题为 `MyNetBackNew` 的页面。
- **`gw` / `console` / `admin` 三个子域返回同一个 `{"name":"MyNetBackNew","version":"1.0.0","status":"ok"}`** —— `*.mynet.club` 是泛解析，全部落到同一个 server block，**gateway 并未部署**（`/healthz` 404）。
- **影响**：`long-range-plan-deep.md:61` 写「后端尚未部署过」，实况更具体：**部署过，但是更早的、只含框架 auth 面的实例**。G4 的三条判据（外网可注册/可互通/可升级）里，「可互通」在生产不可能成立。
- **顺带核正两条文档事实**：① H2（DNS）与 H3（证书 SAN）**实际已满足** —— 泛解析生效、线上证书 SAN = `*.mynet.club, mynet.club`、notAfter 2026-12-11；② 公网 IP 不是文档写的 `52.77.179.176`，实测解析到 `52.221.22.241`（`deployment-registry.md:23` 等待同步）。
- **优先级**：🟠 P1（G4 的直接阻塞）

#### CPI-42. `MYNET_GATEWAY_ENABLED` 是个**无文档的总闸**，默认 false → 后端一个推送都发不出
- **位置**：`mynet_api/config/gateway.php:17`（默认 false）→ `app/Services/Gateway/GatewayNotifier.php:83`（`if (! config('gateway.enabled')) return;`）
- **后果**：`command_queued` / `bundle_changed` / `route_changed` 三类事件全不发。而命令入队仍返 200，**从 API 完全看不出异常** —— 节点只能等下次心跳轮询。V1-9 勾「命令链已通」时未记这个前提。
- **已做**：`.env.example` 补全 5 个键并写清后果（`mynet_api c28b482`）；`manage-prod.sh` 新增远端守卫 4，部署时检查是否显式设置并打印后果。
- **仍未做**：生产 `.env` 未设（要等 gateway 真部署，见 CPI-41）。
- **优先级**：🟠 P1

#### CPI-43. `db-setup.sh` 硬编码本地库口令，且口令上命令行；guard A1 **漏报**裸 `PASS=`
- **位置**：`mynet_ctl/scripts/deploy/db-setup.sh:30`（明文口令）+ `:135`（`mysqldump -p"$LOCAL_DB_PASS"` → 进 `ps`）
- **guard 为什么没拦住**：A1 的关键字表是 `password|passwd|pwd`，`LOCAL_DB_PASS` 一个都匹配不到。
- **✅ 已修**（`mynet_ctl 84c7cba`）：口令改为 `LOCAL_DB_PASSWORD_FILE` 优先 / `LOCAL_DB_PASSWORD` 次之，缺则退出码 1 并提示走 `crt use`；mysqldump 改 `--defaults-extra-file`（0600 临时文件 + RETURN trap 清理 + option-file 转义），与 `backup.sh` 的 V3-5 做法一致；去掉 `2>/dev/null` 并校验 `Dump completed` 结束标记。A1 关键字表补 `pass`（实测零误报），诊断改用与判定同一套正则（此前打印的是 guard.sh 自己的定义行，**指错方向**）。
- **🔴 遗留人工动作**：那个口令**已进 git 历史，需要轮换** —— 从文件里删掉不等于失效。
- **优先级**：🟠 P1 → ✅ 代码已修，待轮换

#### CPI-45. mynet_web 没有真构建管线，而 embed 进 mynetd 的 WebUI 与 `src/` 无关
- **实测**：`package.json` 的 `build` 是 `mkdir -p dist && cp dist/index.html dist/index.html 2>/dev/null; echo "static dist/ 已可被 mynetd embed"` —— **cp 自己到自己**，源不存在时被 `2>/dev/null` 吞掉，什么都不产出却 exit 0 并报「已可被 embed」（§7.1 D1 的桩）。`lint`/`test:unit` 同为 echo 占位。
- **后果**：`mynet_agent/mynetd/localapi/webui/index.html`（5401 字节）是**手写静态页**，grep 无 vue/quasar 痕迹；而 `mynet_web/src/` 有 10 个 Vue 页面 + router + api 层，**从未被构建进去**。两边字节一致只是因为 `sync-embed` 手工跑过一次。D-3「WebUI embed」的机制是真的，但 embed 的不是那份 Vue 应用。
- **已做**：`scripts/check.sh` 把这条以「已知缺陷」每次打印（`mynet_web 498019b`）；`src/api/local.test.ts` 从 3 行空壳写成 6 个真用例。
- **优先级**：🟡 P2（不阻塞 G4，但 D-3/D-4 的完成度要按此重述）

#### CPI-46. mynet_utc 的构建 Makefile 曾被门禁模板**整个覆盖**；build.sh 仍断链
- **实测**：提交 `4300a92`「安装 .githooks 守护 + Makefile.guard」把根 `Makefile` 从 **490 行**（多平台构建、消费 config.mk、plugins/libs/server/client/install/uninstall 全套）覆盖成 **25 行**门禁模板；`configure` 生成的 `config.mk` 从此无人消费；`build.sh` 是指向 `scripts/dev/build.sh` 的**断链**（该目录不存在）。
- **连带**：`tests/README.md` 从 v1.3.1 起写着「cd tests && make」，而 `tests/Makefile` **从来不存在** —— 4 个测试程序一次都没被编译过。
- **✅ 大部分已修**（`mynet_utc b9c8678`）：从 `4300a92~1` 恢复 Makefile 并末尾 `include Makefile.guard`（target 零冲突，默认目标仍是 `all`）；补 `tests/Makefile`，4 个测试实测编译+链接+运行通过（**1054 条断言，0 失败**）；顺带修两个缺头缺陷（`mynet_handshake.h` 用 `htons()` 却没 include `<arpa/inet.h>`、`test_event_loop.c` 用 `struct timespec` 却没 include `<time.h>`）。
- **仍未修**：`build.sh` 断链 —— 是删是补需要裁定（删跟踪文件不替人决定）。门禁以「已知缺陷」每次打印。
- **教训**：**装门禁不要覆盖构建 Makefile，要 include**。
- **优先级**：🟡 P2 → ⚠️ 部分已修

#### CPI-48. 框架迁移是 **MySQL 专用**的，本机 sqlite 开发库无法由 `migrate` 建出来
- **位置**：`vendor/dsplat/multi-tenant-saas-module-auth/Database/migrations/2025_01_01_000003_auth_module.php`（`SET FOREIGN_KEY_CHECKS=0`）· `…-module-infrastructure/…000008_infrastructure_module.php`（裸 `CREATE TABLE … ENGINE=InnoDB`）
- **实测**：全新 sqlite 上 `php artisan migrate --force` 停在第 2 个迁移 ——
  `SQLSTATE[HY000]: General error: 1 near "SET": syntax error (SQL: SET FOREIGN_KEY_CHECKS=0)`。
  sqlite 不支持该语句，也不认 `ENGINE=InnoDB` / `unsignedBigInteger` 之外的 MySQL 方言。
- **后果**：`database/database.sqlite`（39 张表）**不是 migrate 建的**，是手工/测试机制建出来的局部 schema ——
  实测它缺 `email_verification_tokens` 与 `password_reset_tokens`，于是本机 `php artisan serve` 后
  **注册接口直接 500**（`no such table: email_verification_tokens`）。
  而 `make check` 全绿（154/2503），因为测试走 `tests/Schema` 自建表、不碰这个文件。
- **性质**：与 CPI-47 同族（测试态 / 本机开发态 / 生产态三套 schema 各自漂移），
  但根因不同：这条是**框架的迁移不可移植**，我方无法在 vendor 侧修（§1.8）。
- **对 0→1 的影响（重要）**：**本机 sqlite 不是验证 0→1 的可行路径** ——
  它连注册都跑不通，且无法用 migrate 重建。0→1 必须在 **staging(MySQL 8)** 上验，
  这也印证了「独立 staging」这个裁定是对的。
- **修复建议**：① 上游把 `SET FOREIGN_KEY_CHECKS` 与裸 DDL 包在
  `if ($connection->getDriverName() === 'mysql')` 里（或改用 Schema Builder）；
  ② 我方短期：给本机开发库也走 MySQL（docker 或 brew services），
  别再用 sqlite —— 否则「本机能跑」永远不代表生产能跑；
  ③ 或提供 `scripts/setup-local-db.sh`，从 `deploy/schema/mynet_api-schema.sql`
  建一个本机 MySQL 库，与生产同一份 DDL。
- **优先级**：🟠 P1（它让「本机验证」这个最便宜的反馈回路整体失效）

#### CPI-49. `deploy/nginx.conf` 缺 `listen [::]:443` → IPv6 请求被别的站点兜走
- **位置**：`mynet_api/deploy/nginx.conf`（api 段与 gw 段）· 派生物 `deploy/nginx-staging.conf`
- **实测**：把标记文件放进 `stage-api/public/`，`curl -4` 取回标记本身（HTTP 200），
  `curl -6` 取回的却是**另一个站的 HTML 404 页**。线上生产的 `api.mynet.club.conf`
  有 `listen [::]:443 ssl http2;`，仓里这份没有 —— 于是 IPv6 上没有匹配的 server block，
  落到了默认站点。
- **危害**：这个假象足以让人误判成「部署失败 / 路由没注册」。本轮就误判过一次：
  实际路由一直是好的（CLI 下 500 条路由、含 44 条 nodes/monitor），
  而 HTTP 探测全在打另一个应用。**排障时先用 `-4`/`-6` 分别验一遍**，
  或用「放一个唯一标记文件再取」的办法确认请求真的落在目标 root 上。
- **✅ 已修**（`mynet_api 8c3927f`）：补 3 处 `listen [::]:443 ssl http2;` 并重新派生 staging 配置。
  ⚠️ `zero_to_one.sh` 里的 `curl -4` 是**绕过不是修复**，nginx 侧修好后应去掉再验一遍。
- **优先级**：🟠 P1 → ✅ 已修（待去掉 -4 复验）

#### CPI-50. 生产环境把**任何**异常压成 500，「未登录」也返 500 且不留日志
- **位置**：`mynet_api/bootstrap/app.php` 的 `withExceptions` → `renderable(function (Throwable $e) …)`
- **原文**：`if (app()->environment('production') && (request()->is('api/*') || request()->expectsJson())) return json('服务器内部错误', 500);`
  —— **无条件**兜住任何 Throwable，包括 `AuthenticationException`。
- **后果**：① 未登录访问返 500 而不是 401，监控全是假告警（与 CPI-32 同类，
  且会把 CPI-32 修好的 409/422 再压平回 500）；② `AuthenticationException` 在框架的
  `internalDontReport` 里、默认不记日志，于是呈现出「**全站 API 恒 500 且日志零行**」
  这种最难查的状态。
- **为什么长期没被发现**：本机 `APP_ENV=local` 时条件不成立、走正常渲染返 401 ——
  **只在生产/staging 现形**，本地开发、154 个测试、pint 全绿都发现不了。
- **✅ 已修**（`mynet_api 8c3927f`）：只对没有明确 HTTP 语义的异常兜底，
  `HttpExceptionInterface` / `AuthenticationException` / `AuthorizationException` /
  `ValidationException` 一律交回框架渲染。实测 staging：nodes / auth/me /
  social/friends 从 500 → **401**，真不存在的路由仍 **404**，`/up` 仍 200。
- **连带发现**：`app/Exceptions/Handler.php` 里那个空的 `reportable(function (Throwable $e) {})`
  回调（注释写着「可以在这里添加日志记录逻辑」）—— 空回调会**截断默认报告链**。
  但实测该 Handler 在 Laravel 11 下**并未被绑定**（改它不影响行为），
  真正生效的是 `bootstrap/app.php` 的 `withExceptions`。已补实现并在注释里写明，
  免得下一个人以为日志开关在这个文件里。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-51. `deploy/.rsyncignore` 用了 gitignore 的 `!` 取反语法，rsync 不认
- **位置**：`mynet_api/deploy/.rsyncignore`（原第 15-17 行：`.env` / `.env.*` / `!.env.example`）
- **后果**：rsync 的排除文件里 `!` **不是取反**（会被当成字面量的一部分），
  所以 `.env.example` 一直被 `.env.*` 排掉，那条取反从未生效 ——
  staging 部署时 `cp .env.example .env` 直接失败。
- **✅ 已修**（`mynet_api 8c3927f`）：改用 rsync 自己的 `+ 包含 / - 排除` 语法
  （**第一条匹配的规则生效**，所以包含必须写在排除前面）。
- **教训**：`.gitignore` / `.rsyncignore` / `.dockerignore` 三者语法**互不通用**，
  抄写法之前先确认是哪个工具的。
- **优先级**：🟡 P2 → ✅ 已修

---

### 📝 控制面落地发现的缺陷（2026-09-22 记录，发现即记）

> 来源：控制面任务链第 4/5 单元落地过程中撞出来的。**记录在前、处置在后**，
> 避免"当场只修了主问题、周边缺陷随上下文一起丢掉"。
>
> 本批 CPI-1 ~ CPI-8 全部已处置（见「已修复」表）；仅 CPI-7 保留为低优先级记录
> （当前全局 ID 上限恰好等于 float64 精确整数上限，暂无风险）。

### 📝 T2-6 联调发现的缺陷（2026-09-24 记录，发现即记）

> 来源：T2-6「mynet_gui 四页真打通新后端」过程中撞出来的。
> 触发点是一个被误记为「TenantServiceController HTTP 500」的旧账——实测证明
> **控制器本身 12 个 action 全通**，500 来自两个全站性问题；顺着真浏览器联调
> 又挖出一串后端缺陷。全部按「记录在前、处置在后」入册。
>
> 本批 CPI-23 ~ CPI-29 已处置（见「已修复」表）；CPI-30 与 CPI-30a 已于 2026-09-24 修复（见下方 ✅ 标记与「已修复」表）；CPI-31 ~ CPI-35 待处置。

#### 已处置（本批）

| ID | 位置 | 现象 → 处置 |
|---|---|---|
| **CPI-23** | `mynet_api/.env`（缺失） | 仓库无 `.env` → `APP_KEY` 空 → **全站每个请求 500**（`MissingAppKeyException`，`EncryptionServiceProvider.php:83`），连 `/` 都不通。`phpunit` 走 `phpunit.xml` 的 env，**73/73 全绿也发现不了**。→ 建本地 dev `.env`（sqlite + file/sync）+ `key:generate`；`.env` 已被 `.gitignore:16` 挡住 |
| **CPI-24** | `mynet_api/bootstrap/app.php` | 未覆盖框架默认 `redirectGuestsTo(fn () => route('login'))`（`ApplicationBuilder.php:291`）。后端是纯 API 后端、无 `login` 命名路由，而 `Authenticate.php:104` 仅在请求**未**声明 `expectsJson` 时才调 `redirectTo` → **不带 `Accept: application/json` 的未鉴权请求抛 `RouteNotFoundException` → 500 而非 401**。→ 加 `$middleware->redirectGuestsTo(null)` |
| **CPI-25** | `mynet_api/app/Modules/VPN/Models/Node.php` | 只有 `scopeOnline()`/`scopeEnabled()` 两个**查询作用域**，无同名实例方法；`SocialDomainService` 三处（`:547`/`:700`/`:840`）按实例方法调用 → `BadMethodCallException` → `/social/friends/{id}/nodes` 与 `/social/friends/all/nodes` **双双 500**。GUI 捕获后回退空数组，表现为**连接向导「可用设备」恒 0、下一步永远禁用**——故障在后端、症状在前端。→ 补 `isOnline()`/`isEnabled()`，语义与 scope 对齐 |
| **CPI-26** | 原 `SocialDomainService.php:397`（`b79440a` 后该处已是批量计数 `:384-387` + 取用 `:405`；`getAllFriendsNodes()` 移到 `:748`，口径行 `:785`） | `'nodes_count' => 0, // TODO: 计算好友的节点数量` —— 硬编码 0，与真算法口径不一致，好友列表永远谎报 0 节点。→ 改为批量查询，只计 ENABLED/ONLINE。⚠️ 行号为重测结果；该 TODO 在当前文件里已不存在，本条**疑似已被实现消化，未复核、未关单** |
| **CPI-27** | `mynet_api/config/cors.php` | 默认值 `'localhost,127.0.0.1'` **缺 scheme+port**，而浏览器 `Origin` 头永远是完整的 `http://localhost:5173`，精确匹配永不相等 → 预检返 204 但**无 `Access-Control-Allow-Origin`**，浏览器全线拦。→ 本地 `.env` 配 `CORS_ALLOWED_ORIGINS`；**生产白名单仍待办**（与本文件 P1 第 3 条同源，那条记录的 `['*']` 已过时） |
| **CPI-28** | `mynet_api/phpunit.xml` | `Default` suite（= `make test` = DoD 门禁 = pre-push）**不含** `app/Modules/VPN/tests/Feature`，43 个 HTTP 层测试长期游离在门禁外。「TenantServiceController 500」被误记成控制器缺陷正是因此无人把守。→ Feature 并入 `Default`，`make check` 从 73/2253 变为 **126/2398** |
| **CPI-29** | `mynet_api/tests/TestCase.php` | Orchestra Testbench **不加载 `bootstrap/app.php`** → 测试态与生产态静默分叉（`TestCase.php:65` 原有注释已承认，`long-range-plan.md:129` 也写过「e2e 用真实 bootstrap，不走 Testbench」）。→ 在 TestCase 里镜像 `redirectGuestsTo(null)`；**必须走框架的 `(new Middleware)->redirectGuestsTo(null)`**，因为它一次设三个静态回调（`Middleware.php:571-575`），异常渲染路径读的是 `AuthenticationException` 那个 |

#### 待处置（本批）

#### CPI-31. `/api/v1/users/onboarding` 500 —— 我方未完成实现（**不是**框架缺陷）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_api/app/Modules/VPN/Controllers/Api/V1/UserController.php:411`（及 `:441` 的 save） |
| **现象** | 调 `App\Services\UserService::getOnboardingState()` / `saveOnboardingState()`，而该 service 只有 `listUsers`/`getUser`/`updateUser` 三个方法；且 `user_onboarding`/`onboarding`/`user_settings` 三张表都不存在 |
| **易误判点** | 框架确有 onboarding，但那是 `TenantOnboardingService`（infrastructure 模块）——**租户开通引导**，Operator 驱动，步骤为 基础信息→域名→套餐→支付→创建 Tenant。与此处的**用户新手引导**（`completed`/`currentStage`/`selectedPath`/`stages`/`preferences`）是两个概念，**不可混用、不应上报框架** |
| **影响** | 每页控制台一条 500。GUI 已优雅降级（`stores/onboarding.ts:301` 捕获后回落 localStorage，实测 `State loaded: {source: localStorage, progress: 0}`），不挡功能 |
| **修复建议** | 待用户裁定：① 补齐（建表 + 两个 service 方法 + 测试）；② 删掉死 API 面（两条路由 + 两个控制器方法），GUI 改为不再调用 |
| **优先级** | 🟡 P2 |

#### CPI-32. 业务规则拒绝返回 HTTP 500（应为 409/422）

| 项 | 内容 |
|---|---|
| **位置** | `mynet_api/app/Modules/VPN/Domain/Services/RouteRequestDomainService.php:103` 经 `BaseApiController::serverErrorResponse` |
| **证据** | 实测重复提交连接申请：`POST /api/v1/route/send-request` → **HTTP 500**，body `errors.exception = "该连接已经建立，无需重复申请"` |
| **影响** | 业务语义的正常拒绝被计入 5xx。上线后**监控与告警全是假阳性**，真实故障会被淹没；客户端也无法据状态码区分「重试无用」与「服务端坏了」 |
| **修复建议** | 给 `DomainException` 分类（冲突/校验/不存在/无权），在 `mapDomainExceptionToHttpResponse` 里映射到 409/422/404/403；`serverErrorResponse` 只留给真正未预期的异常。需配套 Feature 测试锁状态码 |
| **优先级** | 🟠 P1 —— 上线前必修（可观测性前提） |

#### CPI-33. `mynet_api/storage/` 子目录既未跟踪也未忽略 → 新克隆即坏

| 项 | 内容 |
|---|---|
| **位置** | `mynet_api/.gitignore:28,31` 只挡了 `storage/logs/` 与 `storage/framework/views/*.php` |
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

### 📝 Phase 6（两节点真互通）发现的缺陷（2026-09-25 记录，发现即记）

> **共同点，也是这一批最该记住的一条**：下面 12 个缺陷，本地 189 个单元测试
> **一个都没报**。因为它们全部停在「配置写出来了 / 接口返回 200 了」，
> 没有一个真去起隧道、真去 ping、真去查库。
> 0→1 的判据是「两节点真互通 + 心跳有数据」，只有跑到那一步才会现形。
> 这正是 AGENTS.md §7.1 D2/D4 说的：测试要打真的，勾选要附实测输出。

#### CPI-52. IP 池分配子系统整体未迁移（V2 迁移遗漏）
- **位置**：`mynet_api/app/Modules/VPN/Models/IPPool.php`（迁移后是 29 行裸 Eloquent 类，**零方法**）
- **现象**：`POST /api/v1/ippools` → 500 `Call to undefined method IPPool::getIPAddressGroup()`
  （`IpPoolDomainService.php:354` ← `createIPGroup()` ← `:231 allocateToUser()`）
- **根因**：调用方搬过来了、**被调方没搬**。旧仓 `mynet_main/app/Models/IPPool.php` 有 13 个方法约 500 行。
- **阻断链**（每一环都实测过）：`ip_pools` 0 行 → `allocateNodeIpFromPool()` **静默 return**
  （`NodeDomainService:1113-1131`，且只在 `app.debug` 时记日志，staging 是 false → 一声不响）
  → `node_ips` 0 行 → 19 个节点全部 `node_ip=null` → `NetworkSemanticsProjector:59-72`
  只从 `node_ips` 取 `IP_TYPE_VPN` → `interfaceAddresses` 空 → bundle 的 `network.addresses=[]`
  → 两节点不可能互通。
- **三处不能照搬**（照搬即复现 bug）：
  1. **status 语义相反**：旧仓 `STATUS_ALLOCATED=0` / `STATUS_AVAILABLE=1` 且建池写 0，
     而新表 `status` 默认 1、`allocateNodeIpFromPool` 查 `where('status',1)`
     → 池建得出来却永远查不到，**症状与没修一模一样**。
  2. **序号唯一性范围变了**：旧仓「Zone 内唯一，不同 Zone 可重复」，
     新表 `ip_group_sequence_number` 是**全局 UNIQUE** → 按租户扫会每次撞唯一键。
  3. 旧仓把最多 50 次重试圈在**嵌套事务**里，只会长时持锁；UNIQUE 约束本身就给了原子认领保证。
- **✅ 已修**（`mynet_api aef8834`）：迁移 `getIPAddressGroup` + `tryCreateIPGroup` +
  `findFirstAvailableSequence` + `calculateIPRangeFromSequence`。6 测试 / 23 断言，4 个变异全验证会红。
- **未迁移**：`prefer_continuous`（旧仓 249-540 共 292 行连续空隙启发式）。
  **不留「调不存在的方法」那种定时炸弹**：显式返回空 ips + 记 warning，
  让 `allocateToUser` 走它本来就有的回退分支，响应里 `allocation_mode` 如实是 `'random'`。
- **教训**：PHP 是动态派发，「调用方搬了、被调方没搬」**不会**在编译期或静态检查里暴露，
  只会在真跑那条路时 500。迁移时要**按调用图逐个核实被调方存在**，别只看调用方。
- **优先级**：🔴 P0（0→1 硬阻断）→ ✅ 已修

#### CPI-53. gnb 最后一公里根本没实现（渲染格式 4 处错 + 生命周期 3 处错 + Start 零调用者）
- **位置**：`mynet_agent/mynet/driver/gnb.go`
- **现象**：`refresh-config` 退出码 0、文件也写了，但**隧道起不来**。写出的配置 gnb 解析后几乎是空的。
- **渲染格式（4 处全错，每处都对着 C 源码核实）**：
  | 我们写的 | gnb 要的 | 后果 | 证据 |
  |---|---|---|---|
  | `node <id>` | `nodeid <id>` | `strncmp("node 1","nodeid",6)` 不匹配 → **静默丢弃**，本机 uuid 没设上 | `gnb.go:303` vs `gnb_conf_file.c:293` |
  | `<nodeID> <prefix>`（空格+CIDR） | `<uuid>\|<ip>\|<点分掩码>` | 分隔符不认 → `num!=3` → `continue`，**整份 route.conf 全被跳过** | `gnb.go:131` vs `gnb_conf_file.c:919-928` |
  | 不写 address.conf | `<attrib>\|<uuid>\|<host>\|<port>` | 缺文件时 gnb 直接 return **不报错**，但节点没有任何对端传输地址 | `gnb_conf_file.c:50-63` |
  | 不写 security/ 与 ed25519/ | 128/64 hex **无换行** | 读不到私钥就 `exit(0)`；裸 `read()` 要求读满，多一个换行都不行 | `gnb_keys.c:60-61,68-73,84-89,103,108-112` |
- **route.conf 必须含本机自己那一行**：gnb 是靠 route.conf 里 `uuid==本机` 的那条
  来设自己的 tun 地址的（`gnb_conf_file.c:938-946`）。bundle 的 `gnb.routes` 只含对端，
  本机地址在 `gnb.nodes` 里 —— 所以渲染要以 `nodes` 为主源。
- **生命周期（3 处全错）**：
  1. `Start()` = `gnb_ctl -b <conf>/gnb.map`。`-b` 是 `--ctl-block`（指定读哪块共享内存），
     **不是启动命令**；且 gnb_ctl 没有 `-s`/`-a` 时只打开映射就 `return 0`，
     文件不存在也只打印 error 后 `exit(0)` → **Start() 会假装成功**。
     真正的守护进程是 `gnb -c <conf_dir>`（`gnb_argv.c:899`）。
  2. `Stop()` = `gnb_ctl -k`，而 gnb_ctl 的 getopt 串是 `"b:n:csa:o46h"`（`gnb_ctl.c:102`），
     long_options 里也没有 k —— **这个 flag 根本不存在**。
  3. `IsProcessAlive()` = `pgrep -f gnb_es`，而 `gnb_es` 是上游的 endpoint scanner，
     **不是**主守护进程。两种错：本机跑着 gnb 却报 not alive；别处恰好有 gnb_es 时误报 alive。
- **`Start()`/`Stop()` 全仓零调用者**（只有 `Reload()` 调 `Start`，而 `Reload()` 也零调用者），
  `mynet service` 是返回 1 的桩 → 配置写得再对也没人起进程。
- **对照 wireguard**：`wg-quick up <iface>` 语义是**对的**，但同样没人调用，
  且写 `var/wg.conf` 而 wg-quick 读 `/etc/wireguard/<iface>.conf` —— 路径断点同构。
  差异只在：wg 的 Start 语义对，gnb 的 Start 语义错。
- **✅ 已修**（`mynet_agent a1c1514`）：渲染器全部重写、`Start()` 改 `gnb -c`、
  `Stop()` 改读 pid 文件发 SIGTERM、`IsProcessAlive()` 改按自己的 pid + `kill -0`、
  实现 `cmdService` 给 `Start()` 找到调用者。14 个新测试，7 个变异全验证会红。
  `daemon on` 是两种模式都对的选择：自己起时 gnb 双 fork 父进程立即 `exit(0)`
  （`gnb.c:397-399`），被 systemd 以 `--systemd` 拉起时该键被忽略、保持前台
  （`gnb_argv.c:439-440` + `gnb_conf_file.c:458`），正是 E-2 那个模型。
- **wg 那条路径断点仍未修**（要等 install 阶段落地），已写进 `cmdService` 注释，不假装能用。
- **教训**：「退出码 0 + 文件写出来了」不等于「配置是对的」。
  渲染器必须有**对着对端解析器源码**的契约测试，且夹具要用真数据形状。
- **优先级**：🔴 P0（0→1 硬阻断）→ ✅ 已修（wg 侧 🟡 待 install 阶段）

#### CPI-54. 授权绕过：软删除的行照样进 bundle（安全）
- **位置**：`NetworkSemanticsProjector.php:176,185,190` + `BundleEnvelopePublisher.php:197,262`（共 6 处）
- **根因**：这 6 处用了**无参** `withoutGlobalScopes()`。它的原意（见 `:171` 注释）是绕开
  TenantScope —— 节点通道走 HMAC、没有「当前租户」上下文，`BelongsToTenant` 会
  fail-closed 成 `WHERE 1=0` 让投影恒空。但无参形式会把**所有**全局作用域一起摘掉，
  包括 `SoftDeletingScope`。而 Node / NodeIP / NodeKey / Route 四个模型**全都** use SoftDeletes。
- **后果最严重的是 Route**：`RouteDomainService::deleteRoute()` 走 `$route->delete()`，
  软删只置 `deleted_at`、**status 仍是 STATUS_ENABLED** → 撤销授权后该行照样命中
  projector 的 `where('status', ENABLED)` → 对端的公钥、路由、endpoint 继续下发，
  **隧道照样能建**。这是授权绕过，不是显示问题。
- **不一致本身就是线索**：projector 取 NodeIP 那处手工补了 `->whereNull('deleted_at')`，
  但 publisher 取**对端** NodeIP 那处没补，Route 与 Node 两处也都没补 —— 逐个手工补、补漏了。
- **✅ 已修**（`mynet_api cdf9130`）：6 处统一改成 `withoutGlobalScopes([TenantScope::class])`，
  只摘租户作用域，让 SoftDeletingScope 恢复生效。
- **教训**：`withoutGlobalScopes()` **永远应该带参数**。无参形式是「我不知道有哪些作用域，
  全都关掉」—— 而 SoftDeletingScope 正是最不该被顺手关掉的那个。
- **优先级**：🔴 P0（安全）→ ✅ 已修

#### CPI-55. ifname 超长，会被 gnb 静默截断
- **位置**：`BundleEnvelopePublisher.php:131,287`（原为 `'gnb_tun_'.$nodeId`）
- **根因**：node_id 是 16 位，拼出来 **24 字符**。而 gnb 的 ifname 缓冲区是 16 字节且
  硬截断到 15 字符（`gnb_conf_file.c:275-277`：`memset(…,0,16)` + `strncpy(…,16)` +
  `ifname[15]='\0'`），Linux 的 `IFNAMSIZ` 同样是 16。
- **后果**：接口被**静默**建成 `gnb_tun_2132596` 这种谁也没预期的名字，
  之后所有按接口名做的操作（加地址、加路由、查状态）全对不上，**且不报错**。
- **✅ 已修**（`mynet_api cdf9130`）：改为常量 `GNB_IFNAME = 'gnb_tun'`
  （正是 gnb 自己在 Linux 上的默认名，`gnb_argv.c:224`）。agent 侧另加一道
  `checkGnbIfname()` 显式拒绝 >15 字符，不让它静默截断。
- **教训**：跨语言/跨进程的定长缓冲区，**生产端必须知道消费端的上限**。
  一台主机跑多个 gnb 实例需要另一套短后缀方案，属未实现，不假装支持。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-56. 空 `peer_public_keys` 编码成 `[]`（同一规则两处实现，只修了一处）
- **位置**：`BundleEnvelopePublisher.php:283`（直接拼 PHP 数组，**不经过** `GnbIdentity` 值对象）
- **根因**：这个坑此前修过（PHP 空关联数组 `json_encode` 成 JSON 数组 `[]`，
  Go 的 `map[string]string` 解码直接失败，症状是「越新的节点越拿不到配置」），
  但**只修在 `GnbIdentity::toArray()` 里**。publisher 是另一条生产路径，一直没修。
- **为什么没被发现**：agent 侧加了解码兜底（`bundle_envelope.go` 的
  `GnbIdentity.UnmarshalJSON` 容忍 `[]`）—— **正好把后端侧的缺陷掩盖了**。
- **✅ 已修**（`mynet_api cdf9130`）：抽成 `GnbIdentity::peerMapForJson()` 静态方法，
  两条生产路径共用，消除「同一条规则两处实现」这个它能活下来的根因。
- **教训**：一端加了容错，另一端就会失去暴露缺陷的机会。
  容错要加，但**生产端不该靠对端兜** —— 契约测试必须钉生产端的输出形状。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-57. gnb 侧心跳不发 `vpn_interfaces`，被后端判成离线（含一句没核实的错误假设）
- **位置**：`mynet_agent/mynet/metrics_vpn.go:60-70`（`probeGNB`）+ `cmd/mynetd/heartbeat.go`
- **现象**（Phase 6 实测，非常矛盾）：隧道 `ipv4 Direct Point to Point`、双向 ping 0% 丢包、
  gnb 计数器 0→840 字节，而后端侧 Redis `status=offline`、
  `node_monitor_snapshots` **0 行**、`nodes.last_seen` NULL。
- **根因链**：`probeGNB` 里 `projectPeers("gnb", "", peers)` 把接口名**硬编码成空串**
  → `heartbeat.go` 因 ifname 是后端的 `required_with:vpn_interfaces`（空值会 422）
  而整段跳过不发 → 后端的 `isVpnInterfaceDown(null)` 第一行就是
  `if (empty($vpnInterface)) return true;`（注释：「如果没有 VPN 接口信息，视为网络不可用」）
  → `markNodeOffline()` 并**提前 return，连 node_monitor 都不处理**
  （`NodeHeartbeatController.php:88-108`）。**一个原因同时造成全部四个症状。**
- **最该记住的一点**：`heartbeat.go` 里原本写着
  「后端的 `isVpnInterfaceDown(null)` 不会因此判节点下线」——
  **那是没核实的错误假设，实测正好相反**。典型 D5。
- **更讽刺的是**：`probeWireGuard` 早就用一大段注释写下了这个坑并正确处理了
  （`metrics_vpn.go:29-39`：「读不到 peer 也要报接口存在，少报数字只是数据不全，
  误报离线是**状态错误**」）—— 同一条红线，wg 侧学到了、gnb 侧没跟上。
- **✅ 已修**（`mynet_agent b08e65c`）：`probeGNB` 对齐 `probeWireGuard` 的形态。
  接口名从 bundle 的 `gnb.options.ifname` 取（那正是我们渲染进 node.conf 的值，
  权威且读取不需特权），存在性与 UP 用 `net.InterfaceByName` 判
  （标准库、跨平台、不 exec、不要 root）。读不到 peer 时照样报接口在。
  「gnb 侧拿不到接口名」这个前提也不成立了 —— 不是从 gnb_ctl 拿，是从 bundle 拿。
- **教训**：写「对端不会因此怎样」这类断言时，**必须去读对端源码**。
  同一个坑在同一个文件里被 wg 侧记录过，gnb 侧却重踩 —— 说明教训没有横向传播。
- **优先级**：🔴 P0（0→1 硬阻断）→ ✅ 已修

#### CPI-58. `gnb_ctl` 两处「exit 0 但根本没干活」
- **位置**：`mynet_agent/mynet/driver/gnb.go` 的 `IsAvailable()` / `GetAllPeers()` / `Refresh()`
- **三条都是退出码 0 而没干活**（全部实测）：
  1. 缺 `-b`：gnb_ctl 只打印 **usage** 就 `exit 0` → `IsAvailable()` 恒为真，
     `GetAllPeers()` 把那段 usage 当状态解析 → **0 个 peer**
  2. 缺 `-c`：只输出头部（`conf_dir`/`node_num`/`wan*_port`），**没有任何 node 段**
  3. `gnb.map` 是 `0600 root:root`（gnb 以 root 跑），mynetd 非 root 读到
     `open ctl block error`，而 gnb_ctl **仍然 exit 0**
- **后果**：`mynet metrics` 一直报 `vpn=[{"driver":"gnb","up":true}]` 却没有 peers ——
  那个 `up:true` 来自**一段帮助文本**。
- **✅ 已修**（`mynet_agent b08e65c`）：改为 `gnb_ctl -b <ConfDir>/gnb.map -c -s`，
  并用「输出里必须有 `node_num[`」作为真读到状态的判据 ——
  它是 `gnb_ctl_dump_status` 的头部字段，usage 与错误信息里都没有。
- **教训**：外部命令的**退出码不是契约的一部分**，除非你验过它在失败路径上也非零。
  很多 CLI 在「参数不对」时打 usage 然后 `exit 0`。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-59. `mynetd -once` 的竞态：可能在启动拉取完成前就退出
- **位置**：`mynet_agent/cmd/mynetd/main.go`（启动拉取在 goroutine 里，`-once` 分支无同步）
- **现象**（Phase 6 真机实测）：同一条命令在两台机器上，节点 B 落盘了
  `var/bundle.json`（1453 字节），节点 A 的 `var/` **全空** ——
  两边退出码都是 0，A 的日志停在 `transport: connected` 就没了，**没有任何错误行**。
- **为什么严重**：`-once` 是**验收脚本用来当一次性探针**的模式（flag 说明写的就是
  「只探测一次心跳就退出（调试）」）。一个不确定的探针比没有探针更糟 ——
  它会随机给出「通过」和「什么都没发生」两种结果，而两者退出码相同。
- **且它已经在糊别的测试**：`scripts/e2e/bundle_pull.sh` 此前在 `-once` 后 `sleep 1`，
  然后 `elif before == after → ok「文件未变」` —— 而 `before == after` 在
  「拉取根本没发生」时同样成立 → 判过，**等于没测**。
- **✅ 已修**（`mynet_agent 1e9ca89`）：加 `bundlePullDone` channel，goroutine 退出时 close，
  `-once` 分支 select 等它（上限 `bundleSyncTimeout`=15s，超时明确 Warn）。
  修复后在原先失败的节点 A 上连跑 3 次，3/3 落盘。`bundle_pull.sh` 的 sleep 去掉、
  弱断言改成「日志里必须出现『启动拉取 bundle』，否则判竞态复发」。
- **教训**：本文件此前已踩过一次同形状的坑（`:78-80` 注释：心跳 reporter 还没绑上就跑完，
  表现为「采了但没上报」）—— **同一个形状的静默失败，第二次**。
  「一次性探针」模式必须等所有异步启动动作收敛后才返回。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-60. `RouteController::getRequests()` 是 TODO 桩，导致验收脚本假绿
- **位置**：`mynet_api/app/Modules/VPN/Controllers/Api/V1/RouteController.php`
- **现状（当时）**：方法体是 `// TODO: 实现从数据库获取申请数据` + 硬编码返回
  `['pending' => [], 'sent' => []]`，**从不查库**，HTTP 200。
- **后果 1（产品）—— ⚠️ 此处修正我先前的高估**：我最初写「GUI 里永远看不到待批准的
  连接申请」，**这是错的**。查下来 GUI 走的是 `/route/data` 的 `requests` 子对象
  （`mynet_gui/src/composables/useRoutes.ts:70/73` → `PendingConnectionsPage.vue`），
  而 `/route/data` 一直在正常调 `getUserRouteRequests()`。本端点
  `GET /route/requests` 在**全仓（gui/web/desktop/openwrt/utc）没有任何调用方**。
  所以产品面影响接近于零。
- **后果 2（验收，这才是真问题）**：`scripts/e2e/zero_to_one.sh` Phase 4 那条
  「✓ B 能列到连接申请（HTTP 200）」是**假的绿** —— 端点返 200 + 空数组，
  断言只看状态码就过了。**我自己写的验收脚本被一个桩骗了。**
- **旁证**：库里两条 `node_route_requests` 都还是 `pending`（一条是 9-24 23:51 的旧跑），
  说明 `accept-request` 这步**从来没被任何脚本调用过**。而 Phase 4 的名字写着
  「连接授权 → bundle 下发」，它其实只走到了「申请已发出」。
- **✅ 已修**：
  - 后端侧 `884cf9c`：委托给既有的 `RouteRequestDomainService::getUserRouteRequests()`
    （:434-464，早已被 `/route/data` 消费），所以本端点与 `/route/data` 的
    `data.requests` **逐字节同形**，零前端改动。补 `catch (DomainException)`
    与兄弟方法一致（CPI-32）。新增 `RouteRequestsContractTest`（5 个 / 35 断言），
    **断言内容而非状态码**；变异验证：退回桩 → 3 条红。
  - agent 侧 `e5f3657`：Phase 4 改为断言 pending 里确有这条、id 一致、
    且能直接喂给 `accept-request`；**补上 accept-request**；Phase 3 的可重入性补齐
    （此前只把「发申请」做成 409→继续，后半段仍硬要求有待接受项，
    于是已是好友时重跑必停在「取不到 request_id」）。
    实测真 staging **21 通过 / 0 失败**。
- **教训（两条，都比这个 bug 本身值钱）**：
  1. 断言「能列到」就必须断言**列表内容**。一个返回 200 + 空集合的桩，
     能骗过所有只看状态码的验收。
  2. **报告缺陷影响时不要凭端点名字推断谁在用它。** 我看到「GUI 要列申请」
     就断定 GUI 坏了，实际 GUI 走的是另一个端点。查调用方是三分钟的事，
     省下来的是别人按错误结论去排优先级。
- **优先级**：🟡 P2（产品面影响小；验收面已修）→ ✅ 已修

#### CPI-61. `mynet setup` 对 gnb 节点生成的是 x25519 密钥（类型错）
- **位置**：`mynet_agent/mynet/cmd_setup.go:42-54`
- **现状（当时）**：`ecdh.X25519().GenerateKey()`，输出 `algorithm: x25519`、私钥 64 hex。
  而 **GNB 需要 Ed25519 的 128-hex 私钥**（`clamp(SHA512(seed))` 布局），
  且 gnb 读 `security/<uuid>.private` 时要求**恰好 128 字节**、读不满就 `exit(0)`
  （`gnb_keys.c:68-73`）。Phase 6 只能绕开 setup、在节点机上用
  `gnb_crypto -c -p … -k …` 生成后手工写进 `conf/mynet.conf`。
- **顺着这条线查出一个更隐蔽的错**：`GNBKeyPair.ToHex()` 的注释写着
  「as GNB expects」，实际编码的是 `kp.PrivateKey` —— 那是**未 clamp** 的原始
  `SHA512(seed)`（clamp 只落在副本 `ProcessedPrivateKey` 上）。而 GNB 期望
  `clamp(SHA512(seed)[0:32]) || SHA512(seed)[32:64]`（权威依据
  `mynet_gnb/src/libs/ed25519/keypair.c:8-12`，clamp 是**就地**作用在写盘缓冲区上；
  `gnb_keys.c:74` 读盘后**不再 clamp**）。
  **为什么能一直藏着**：`ToHex` 在生产代码里零调用者（只有测试用），
  而 Go 侧签名走 `scalarFromGNBPrivate()` 会显式再 clamp 一次（幂等），
  对两种输入都自洽 —— 所以 `TestSignGNB_MatchesStdlib` 一直是绿的。
- **✅ 已修**（`mynet_agent 38de1b9`）：`ToHex()` 改为输出 GNB 落盘格式；
  `cmdSetup` 加 `--vpn-type gnb|wireguard`（**默认 gnb**，与后端的
  `$isGnb = primaryVpnType() !== WIREGUARD` 判定一致）；`--print-keys` 明确给出
  「把 public_hex 用用户 token PUT 到 /nodes/{id}/keys，key_type 填 **gnbkey**
  而不是 ed25519」的下一步（填错会落到 CPI-37 那条「algorithm 标错 → 节点面全 401」）。
  顺带删掉零调用者且逻辑也错的 `ToGNBFormat()`（32 字节分支产出
  `scalar || SHA512(scalar)[32:]`，而正确的 nonce 半是 `SHA512(seed)[32:]`）。
- **验证**：8 个新测试；真机上把 setup 产物与 `gnb_crypto` 产物并排比对，
  128/128、64/64、clamp 位都为真 → **结构同构**。3 个变异全验证会红
  （ToHex 退回未 clamp → 「byte[0]&7=7」；默认改 wireguard；gnb 分支改走 wg）。
- **教训**：一个函数注释里写着「as X expects」而**生产零调用者**，
  它的正确性就从未被任何东西检验过。死代码不是无害的 ——
  它是「看起来能用其实是错的」API，等着下一个人接上去。
- **优先级**：🟡 P2 → ✅ 已修

#### CPI-62. bundle 的 `network.routes` 硬编码为空 → 隧道通了但流量不进隧道
- **位置**：`BundleEnvelopePublisher.php`（`compose()` 里 `'routes' => []`）
- **现象**（Phase 6 实测，最迷惑的一环）：`gnb_ctl -s` 里对端
  `ipv4 Direct Point to Point`、`addr4_ping_latency_usec 832`、`crypto_key` 非零、
  `static_address_list num[1]` —— **隧道已建好、PONG 正常、会话密钥已协商**，
  但 ping 对端 overlay IP **100% 丢包**，gnb 的 in/out 计数恒 0。
- **根因**：`ip route get 10.0.0.16` 命中的是**默认路由**（`via 192.168.0.1 dev ens18`），
  包从物理口出去被 LAN 网关丢掉，根本没进 `gnb_tun`。而 `ip route show dev gnb_tun` 是空的
  —— tun 地址是 /32，Linux 只建 local 路由、不建 link 路由；gnb 自己也**不写内核路由表**。
- **两份 routes 是两回事**：`gnb.routes` 填了（喂 gnb 的 route.conf，管 overlay 内部转发），
  `network.routes` 是空的（喂 agent 的 `refresh-routes` → `apply`，管**内核路由表**）。
- **✅ 已修**（`mynet_api fa1139a`）：gnb 分支按 `$peerTun` 生成 /32 主机路由
  （`dev=gnb_tun`，不带 `via` —— tun 是点对点接口，带网关 `ip route` 会拒）。
  **只加对端的 /32，绝不动对端整个 /24** —— 这是
  `node-connectivity-checklist.md` 里「路由劫持」那条教训。
  wireguard 分支不加：wg-quick 会按 peer 的 AllowedIPs 自动装路由。
- **教训**：「隧道建立」与「流量进隧道」是两件事，中间隔着一张内核路由表。
  只验前者会得到「一切正常但完全不通」的现场。
- **优先级**：🔴 P0（0→1 硬阻断）→ ✅ 已修

#### CPI-63. mynetd 非 root 读不到 `gnb.map` → 心跳里 peers/rx/tx 恒 0
- **位置**：gnb 以 root 跑并把 `gnb.map` 建成 `0600 root:root`；mynetd 原是非 root
  （它启动时自己就打印「mynetd 无需 root」）
- **现状（当时）**：CPI-57 修好后接口状态正常上报了，但 `GetAllPeers()` 读不到 ctl block
  （`open ctl block error`），于是走降级分支：报接口在、**不报 peer 数与流量**。
  实测 `node_monitor_snapshots` 里 `connected_peers=0`、`rx_bytes=0`、`tx_bytes=0`。
- **根因澄清**：那条「mynetd 无需 root」的提示**是模式盲的**。
  `dev-plan.md` 的 D-8 原文是「去掉强制 root（**console 模式**）」，D-12 定义 console 模式为
  「无 WS、无节点身份、无 presence、无 commands[]…非 root」。而我们跑的是**节点模式**
  （配了 `MYNET_GATEWAYS`），要 ① 起 gnb（建 tun 需 CAP_NET_ADMIN）② apply 写内核路由
  ③ 读 gnb.map —— 三件都要特权，且**失败时都不报错**，只静默降级。
  所以这不是「要不要提权」的取舍，是提示写错了适用范围。
- **✅ 已修**（`mynet_agent 10f7e22`，**用户 2026-09-25 裁定**）：
  1. root 提示改为分模式：console 保持 D-8；节点模式高声 Warn 并列出三条后果；
     未配 gateways 的纯本机模式不误用隧道话术。**不做 fail-fast** ——
     mynetd 的核心职责（WS + 拉 bundle + 心跳）确实不需要 root，整体拒启会打断
     合法的半特权部署与守护 V1-10 的 `bundle_pull.sh`。
  2. 生产部署走 `deploy/systemd/mynetd.service`（root）。
     ⚠️ 该单元**刻意不加** `ProtectSystem=strict` / `ProtectHome=true` / `PrivateTmp`：
     它们分别会让 mynetd 写不了 `$MYNET_HOME/var`、读不到含节点私钥的 `mynet.conf`、
     以及让 `/tmp` 与手工调试时看到的不是同一个。**不要照抄 gateway 单元那套硬化**。
- **实测结果**（两台真节点机，mynetd 以 root 跑）：
  - 节点侧 `mynet metrics --json`：`peers=1 peers_up=1 addrs=["10.0.0.16","64:ff9b::a00:10"] rx_bytes=4116 tx_bytes=4116`
  - 后端侧 `node_monitor_snapshots`：`connected_peers=1 total_peers=1 rx=4116 tx=4116`，各 109 行
  - 完整监控链首次打通：gnb ctl block → `mynet metrics` → 心跳 → MariaDB
- **顺带解开的**：`bundle_changed` 此前只跑 `refresh-config`（只写配置文件），
  不重启驱动（gnb 的 `HotReload` 是 false，配置改了必须重启才生效）、也不装路由
  （= CPI-62 那个形态）。现已接成 `refresh-config → service start → apply --auto`，
  实测从后端触发 `bundle/publish` 后节点**零人工干预**跑完三步，隧道未断、
  gnb 进程数仍为 1（`Start()` 的存活短路生效，没起重复实例）。
- **教训**：外部命令的退出码不是契约（见 CPI-58）；而「非 root 也能跑」这类说法
  必须写清**在哪个模式下成立** —— 模式盲的能力声明会把一个部署错误
  伪装成「设计如此」。
- **优先级**：🟡 P2 → ✅ 已修

#### CPI-68. 登录响应里有**三个装饰品**：refresh_token 与两个 expires_in 都是假的
- **位置**：`mynet_api` 的框架 auth 模块
  （`vendor/dsplat/.../Concerns/BuildsUserTokenResponse.php:44`、
  `vendor/dsplat/.../Http/Controllers/UserAuthController.php:139`）
- **现状（当时）**：
  | 字段 | 声称 | 实际 |
  |---|---|---|
  | `refresh_token` | 7 天会话凭证 | `Str::random(60)` **当场编**，不赋值、不入库、不校验、无端点 |
  | `auth_token_expires_in: 1800` | 30 分钟过期 | `createToken('auth_token')` **不传 expiresAt** → `expires_at` 恒 NULL，**永不过期** |
  | `refresh_token_expires_in: 604800` | 7 天 | 硬编码元数据，无人 enforce |
  也就是说**「会话」这个概念从来没建过，而登录响应长得像建过**。
  全仓唯一的 `refresh_token` 数据库列在 `OauthAccount`（存的是 **OAuth 供应商**给的
  refresh token，与自建登录无关）。
- **后果**：集成方照着响应做就会走到死路 —— LuCI 就在调不存在的 `/auth/refresh-token`。
  且因为三个值都不经服务器校验，**只看响应形状的话改前改后一模一样**，
  这正是它能骗这么久的原因。
- **✅ 已修**（`mynet_api f60cf1c` + `mynet_agent fa7497a/b40d472`）：
  1. **建在已有的 `user_sessions` 上**，不另起表 —— 框架登录时就写它
     （`SessionService::recordSession`：token_id/session_id/device_fingerprint/
     is_anomalous），另起表会造出两个「会话」，`/mfa/sessions` 就对不上。
     加 5 列：`refresh_token_hash`（**sha256，只存哈希**）、`refresh_token_prev_hash`、
     `refresh_expires_at`、`refresh_rotated_at`、`revoked_at`（写不物理删，留痕）。
  2. **轮转 + 重用检测**：每次刷新换新 refresh token，旧值挪进 prev_hash；
     拿「已轮转过」的旧 token 再来刷新 = 泄漏或状态错乱信号 →
     **当场吊销整个会话并清哈希**。不轮转的话 7 天固定，泄漏窗口太长。
  3. **不改 vendor、不动路由顺序**：用**容器绑定**把
     `UserAuthController` 指到子类 —— `Route::getController()` 是
     `$this->container->make($class)`（`Illuminate\Routing\Route::getController`）。
     不能靠改路由：`RouteCollection` 同 URI **先注册者胜**，而 auth 路由由框架
     ServiceProvider 加载，顺序不受我们控制。效果是**登录响应形状不变、值变真**，
     GUI/LuCI 无需跟着改解析。
  4. 客户端（`mynet_agent`）：`credential.json` 成为**会话存储**（0600，
     与 LuCI 侧 `/etc/mynet/conf/credential.json` 同路径同字段），
     `backClient` 在 401 时自动续期并**立刻落盘轮转后的新值**（只更新内存的话
     下次续期会撞重用检测、把整个会话吊销 —— 这就是「要轮转就必须有持久化」的因果）。
  5. `logout` 先 POST `/api/v1/auth/revoke-session` 再删本地文件：
     只删本地文件不叫登出，只是忘了。
- **实测（真 staging）**：登录 → credential 里 `refresh_token` 60 字符、
  两个 `expires_at` 都落盘 → 续期成功且两对 token 都换过 →
  拿轮转前的旧值再续 → `401 REFRESH_TOKEN_REUSED` 且会话被吊销、哈希被清 →
  同会话的新 token 也随之失效。
- **教训（两层）**：
  1. **响应里的字段名不等于契约。** 三个字段都出现在响应里、都被前端解析，
     而没有一个被服务器校验。核实契约要去读**实现**（有没有入库、有没有 enforce），
     不能读响应示例。
  2. **短测暴露不了这类 bug。** agent 侧第一版漏存 `refresh_token`，
     而「一条命令几分钟跑完」时 30 分钟 access token 够用 —— 一切正常。
     只有把**产物读回来核对**才发现 `refresh_token = <0 字符>`。
     验收要看落盘结果，不能只看命令退出码。
- **优先级**：🔴 P0（会话/认证的地基）→ ✅ 已修

#### CPI-64. `setsid nohup` 起的守护进程活不过 SSH 会话 —— Phase 6 的绿是**会话依赖**的
- **现象**：装服务前复查，两台上 gnb 都不见了、`gnb_tun` 消失。journal：
  ```
  13:35:09 systemd-networkd: gnb_tun: Link DOWN
  13:35:09 systemd[1]: session-1605.scope: Deactivated successfully.
  13:36:15 gnb_tun: Link UP
  13:37:18 gnb_tun: Link DOWN
  ```
  DOWN 与 session scope 失效是**同一秒**；UP/DOWN 的间隔正好是一次次 ssh 连上又断开。
- **根因**：`setsid nohup` 只改了 **ppid**，而 **systemd 按 cgroup 而非 ppid 追踪进程**。
  gnb 即便双 fork 成了 ppid=1 的守护进程，仍留在 `session-N.scope` 的 cgroup 里，
  会话结束时被整组回收。node.conf 里的 `daemon on` **不足以**让它脱离会话。
- **⚠️ 对验收结论的影响（最该记住的一条）**：Phase 6 那次「38 通过 / 0 失败」
  **只在有人连着 SSH 时成立**。人一断开隧道就没了，而所有断言当时都是绿的。
  这类问题只有把验收状态**放一段时间再回来看**才会暴露 ——
  一次性跑完就宣布达成，正好是它最容易骗过人的时机。
- **✅ 已修**（`mynet_agent 87e874c`）：新增 `deploy/systemd/mynet_gnb.service`
  （`gnb --systemd -c ${MYNET_HOME}/var/gnb`，Type=simple + Restart=always）。
  用 `--systemd` 而非自我 daemon 化：带该 flag 时 gnb 忽略 `daemon on` 保持前台
  （`gnb_argv.c:439-440` → `gnb_conf_file.c:458`、`gnb_argv.c:741-742`），
  前台正是 `Type=simple` 要的（进程死了 systemd 立刻知道、能 Restart）。
  同一份 node.conf 两种模式都能用 —— 这正是当初渲染器写 `daemon on` 而不是
  `daemon off` 的原因。
  实测两台均在 `/system.slice/`（不再是 `session-N.scope`）、active + enabled。
- **教训**：验证一个常驻服务时，**必须在断开控制通道之后再回来看一次**。
  「我连着的时候它是好的」不构成任何结论。
- **优先级**：🔴 P0（验收结论有效性）→ ✅ 已修

#### CPI-65. tun 重建后 /32 主机路由丢失，mynetd 启动时不走生命周期
- **现象**：装成服务后重启，gnb 由它自己的单元拉起、`gnb_tun` 也在，
  但 **B 侧 `ip route show dev gnb_tun` 是空的** → A→B ping 100% 丢包，
  而 A 侧路由还在（它是上一轮手工 apply 留下的）。症状与 CPI-62 同形。
- **根因**：内核不会在 tun 重建时自动恢复此前 `ip route replace` 加进去的路由；
  而 mynetd **只在 `bundle_changed` 时**走生命周期，启动路径只做「拉 bundle」，
  于是重启后没人重装路由。
- **✅ 已修**（`mynet_agent 87e874c`）：mynetd 启动拉取成功后也跑一遍
  `runBundleLifecycle()`（refresh-config → service start → apply --auto）。
  `-once` 时跳过（那是一次性验收探针，不该顺手改被测机器的内核路由）。
  实测 journal 四步齐全，B 侧路由自动恢复成 `10.0.0.10 scope link`。
- **教训**：「配置变更时做 X」和「启动时做 X」是两条路径，只实现前者的话，
  重启就是一个从未被测过的状态。凡是幂等的收敛动作，启动时都该跑一遍。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-66. `gnb --systemd` 忽略 `pid-file` → `Start()` 会 spawn 出第二个 gnb
- **根因**：`gnb_conf_file.c:349` 的 `pid-file` 解析条件带 `&& 0 == conf->systemd_daemon`
  （`ctl-block` 同理，:336）。所以 systemd 模式下**根本没有 pid 文件** →
  `readPidFile()` 失败 → `IsProcessAlive()` 恒 false → `Start()` 认为没在跑，
  于是 spawn **第二个 gnb**，两个进程抢同一个 tun 与 UDP 端口。
- **症状**：隧道时通时断，而两边日志都"正常"（各自都觉得自己起成功了）。
- **✅ 已修**（`mynet_agent 87e874c`）：`systemdManages()` 用
  `systemctl show -p LoadState --value <unit>` == `loaded` 判断，命中就把
  Start/Stop/IsProcessAlive 全部委托 systemctl。
  ⚠️ 判据不能用 `systemctl is-active` —— 它区分不了「单元不存在」与
  「单元存在但没跑」（两者都输出 inactive/unknown + 非零退出码）。
  Stop 也必须委托，否则与单元的 `Restart=always` 打架（kill 掉立刻又被拉起，
  看起来像"停不掉"）。
- **副作用（正向）**：这让 `mynet service` 终于符合 D-7 对它的原始定义
  「进程生命周期（委托 procd/systemd/launchd）」；直接 spawn 退化为
  没有服务管理器时的退路（容器内 / macOS / 手工调试），两条路都有测试。
- **验收**：phase6_tunnel.sh 新增断言「gnb 恰好 1 个实例」（每节点 1 条，共 2 条），
  专门钉这个 double-spawn 风险。
- **教训**：一个 flag 会**关闭**另一些配置键的解析（`--systemd` 关掉 daemon /
  pid-file / ctl-block），这种耦合不写在文档里就一定会踩。改启动方式前
  先把它对配置解析的影响读一遍。
- **优先级**：🟠 P1 → ✅ 已修

#### CPI-67. 用非引号 heredoc 生成远端脚本 → 注释里的反引号在**生成期**被执行
- **位置**：`mynet_agent/scripts/e2e/phase6_tunnel.sh` 的 `node_script()`
- **根因**：原来用非引号 `<<NODE`，于是脚本正文里所有 `$`、`$( )`、
  以及**注释里的 markdown 反引号**都会在「生成这段脚本」的时候就被工作站 shell
  展开/执行。实测：注释里写了一句「退回手工 `mynetd -once`」，
  生成时它真的在工作站上跑了 mynetd，报
  `command substitution: syntax error near unexpected token '<'`；
  而生成出来的脚本因为反引号被吃掉变成**未闭合**，整文件 EOF 语法错。
- **为什么之前没炸**：更早几版被误执行的内容恰好无害（比如 `$(wc -c < …)`
  在生成机上只是找不到文件）。**能跑通纯属运气**，不是设计正确。
- **✅ 已修**（`mynet_agent 87e874c`）：改成引号 heredoc `<<'NODE'`（零展开），
  动态值（MYNET_HOME / gateway / 对端 IP）全部由 `on()` 用**环境变量**送入。
  现在生成期输出为空 = 没有任何东西被误执行，且注释里的反引号原样保留。
- **顺带两处取数坑**（同一类：假设 shell 行为跨实现一致）：
  - `sudo sh -c "wc -c < $DIR/*.private"` 取不到值 —— sudo 起的 `/bin/sh`
    在 Debian 上是 **dash，而 dash 不在重定向里展开通配符**（bash 会），
    于是它把字面量 `security/*.private` 当文件名。改用 `sudo find` 拿名字 +
    `sudo wc -c FILE`（用参数而非重定向：重定向由外层 shell 以普通用户身份做会 EACCES）。
  - 远端 bash 的 `cannot change locale (en_US.UTF-8)` 警告走 stderr，
    被 `2>&1` 收进来后会污染「取最后一行」和「只留数字」两类解析
    （实测把计数器值整个替换成了那条警告）。统一在 `on()` 里滤掉。
- **教训**：**往远端送脚本时，「生成」与「执行」必须分离。**
  生成期不做任何展开、参数走环境变量。否则注释都能变成被执行的代码 ——
  这与本项目反复踩的「`$VAR` 后紧跟全角标点被并入变量名」是同一类错误：
  假设引号/分词/展开在另一个上下文里行为一样。
- **优先级**：🟠 P1（验收工具自身的可信度）→ ✅ 已修

---

### 📝 2026-10-04 v1 批次 0/1 发现的缺陷（发现即记）

#### CPI-69. `mynet_ctl` 安装向导的后端客户端打的是 `/api/v2/*`，而 mynet_api **只有 v1**
- **位置**：`mynet_ctl/app/Services/Setup/MynetBackClient.php:46`（`GET /api/v2/user/zones`）、
  `:51`（`POST /api/v2/user/switch-zone`，body `{zone_id}`）、`:60`（`GET /api/v2/nodes`）；
  对外由 `mynet_ctl/app/Http/Controllers/Api/V1/SetupController.php:62`
  （`POST /api/v2/setup/switch-zone`）与 `resources/views/pages/setup.blade.php:282` 使用。
- **事实核对**：`mynet_api` 全仓**没有任何 v2 路由**，模块 `Routes/api.php` 由
  `ModuleServiceProvider` 一律挂在 `/api/v1` 下；租户端点实际是
  `GET /api/v1/user/tenants`、`POST /api/v1/user/tenants/switch`（body `{tenant_id}`）、
  `GET /api/v1/user/tenants/current`（`Routes/api.php:268-271`）。
  与 CPI-41「V2 从未上线」是同一件事的下游后果。
- **影响**：安装向导（`spec/wizard.md`）的「选 Zone/租户 → 列节点」两步打不通。
  因为 CTL 是**出包发行面**，这条断链会直接体现在用户拿到的安装器上。
- **修复建议**：`MynetBackClient` 三个方法改指 v1 的 tenants 端点，参数 `zone_id` → `tenant_id`。
  ⚠️ **不要现在就改**：Zone→Tenant 改名正在按 `spec/zone-removal.md` 分步推进，
  改早了会与该批次的契约翻转撞车。等第 2 步（契约翻转）落地后一并处理。
- **优先级**：🟠 P1（发行面的安装向导不可用）

#### CPI-70. `mynet_gui` 对每个「未配置」节点打一次 `GET /nodes/{id}/status`，**响应从未被读取**
- **位置**：调用点 `mynet_gui/src/services/node/nodeService.ts:287 getNodeStatusInfo()`；
  唯一调用者 `mynet_gui/src/pages/node/NodeListPage.vue:185`，把结果存进
  `nodeStatusInfo`（`:186`）后，**全文件再没有读过这个变量**
  （只有 `:170` 声明、`:179` 去重守卫、`:186` 写入三处）。
  触发面是 `checkAndLoadUnconfiguredNodes()`（`:198-207`），对所有
  `isUnconfiguredNode` 的节点**并发**各打一次。
- **影响**：纯浪费，而且**两个方向都白跑**。触发条件已核实：
  `isUnconfiguredNode()`（`:284-287`）走 `NodeStatusManager.needsConfiguration()`
  （`src/utils/nodeStatus.ts:198-206`），数字状态时**只对 `NODE_STATUS.UNCONFIGURED`（-1）
  返回 true**。所以这次 GET 只打在**已经是 -1** 的节点上：
  ① 它不是把节点打成 -1 的原因（那些节点早已是 -1）；
  ② 它也**救不回来**——旧的写副作用走 `updateStatusBasedOnConfig(null)`，
  该分支只能降级不能升级，对 -1 节点是空转。
  即：N 个未配置节点 = N 次请求，既不改状态也不用结果。
- **修复建议**：二选一 —— 要么真的把 `configuration_status` / `next_steps` 渲染到
  节点卡片上（那才是这次请求的本意，`next_steps` 本来就是给「下一步该做什么」用的），
  要么删掉这次请求。属 GUI 侧产品决定，未自行处置。
- **优先级**：🟡 P2（无正确性危害，是无效请求 + 一个没接上的引导能力）

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

#### 5. mynet_api 文档分散
- **现状**：mynet_api 顶层散落临时 .md（如 `BACKEND_FIX_NEEDED.md`、多个 deploy shell 脚本注释长篇）
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
- **影响**：OpenWrt 节点不主动上报存活；后端仅通过 GNB 隧道流量间接判断
- **重新启用条件**：用户后续主动提出 / 出现因缺心跳导致的业务问题
- **关联 spec**：[spec/plugins.md](spec/plugins.md)

### mynet_lua 远程命令处理（route.refresh / client.update / client.report）
- **决策日期**：2026-05（与心跳一并暂缓）
- **背景**：mynet_client 通过 `cmdLoop` 拉取 `/api/v2/nodes/{id}/commands` 处理；lua 侧未实现
- **影响**：OpenWrt 节点收不到后端下发的命令；需手动操作或重启服务
- **重新启用条件**：同上

---

## 已修复

| 修复日期 | 问题 | PR / Commit |
|---------|------|------------|
| 2026-05 | mynet_gui 根目录文档归档 | 本次审计 |
| 2026-05 | mynet_client openwrt 注释清理 | 本次审计 |
| 2026-05 | mynet_api 缺 .env.example | 本次审计（新增） |
| 2026-05 | ctl 凭证集中目录建立 | 本次审计（新增 `.credentials/`） |
| 2026-05 | mynet_lua `/api/v1` → `/api/v2`（config.json + deploy.md） | 本次治理 |
| 2026-05 | mynet_lua 下载 URL `download.mynet.club` → `ctl.mynet.club/d` | 本次治理 |
| 2026-05 | mynet_api `.env.production` SESSION_DOMAIN/SANCTUM 域名 dsplat → mynet.club | 本次治理 |
| 2026-05 | mynet_api `.env.production` VITE_REVERB_HOST 一致性修正 | 本次治理 |
| 2026-09-22 | 后端: monitor 路由被模块级 `auth:sanctum` 覆盖 → 节点无 token 全 401（含心跳） | `836adc52` |
| 2026-09-22 | 后端: 请求体 gzip 从未被解压 → 真实心跳（必然 >1KB）验证阶段 422 | `836adc52` |
| 2026-09-22 | 后端: `Services/NodeMonitorDomainService` 空壳遮蔽 `Domain/Services/` 真实现 → 带 node_monitor 的心跳必 500 | `836adc52` |
| 2026-09-22 | 后端: 节点通道无租户上下文，`TenantScope` fail-closed（`WHERE 1=0`）→ 节点状态永不落库 | `8c40ff72` |
| 2026-09-22 | 后端: `node_monitor_snapshots` 同秒唯一键 + `create()` → 第二次心跳回滚整事务 | `8c40ff72` |
| 2026-09-22 | 后端: `monitor_rt_cmds` PK 重命名残留（`where('id')` / `->id` / `'creator'` / `'id'=>uuid`）→ 心跳/回调/指令历史全 500、配置变更命令静默丢失 | `c82a30f7` |
| 2026-09-22 | 后端: `NodeConfigService` 的 `params` 双重编码 → 节点收到字符串而非对象 | `c82a30f7` |
| 2026-09-22 | agent: 网关中继可被节点用 `X-Node-Id` 头冒充别的节点 | `b7aceeb` |
| 2026-09-22 | agent: gatewayclient 事件合并竞态 → 并发时事件永久丢失 | `d495bd8` |
| 2026-09-22 | agent: gatewayclient 取消 ctx 不生效 → SIGTERM 后卡满 180s 读超时 | `d495bd8` |
| 2026-09-22 | 响应性联动断裂（事件系统删除后节点上线不再触发对端 route.refresh） | `8c40ff72` |
| 2026-09-22 | **CPI-1** 节点启停/重启后台功能整体不可用（`App\Models\Node` 不存在） | `c10aaa45` |
| 2026-09-22 | **CPI-3** `App\Services\NodeRebootService` 三处悬空引用 | `c10aaa45` |
| 2026-09-22 | **CPI-2** 后端下发 `mynet.reboot`、agent 只有 `mynet.restart` → 重启静默失效 | `24570da3` |
| 2026-09-22 | **CPI-4** 后端命令词汇整体漂移（mynet.boot/shutdown/regenConfig/reloadConfigs/plugin.proxy.reload/upload-log） | `24570da3` |
| 2026-09-22 | **CPI-5** 命令输出没回传后端（`Execute` 把 result 硬写成"命令执行成功"） | `b407e91` |
| 2026-09-22 | **CPI-6** 节点通道 limit 按 IP 60/分（中继后即 300 节点打满）→ 改按已验签节点 180/分 | `fb97a776` |
| 2026-09-22 | **CPI-8** 心跳未收敛到中继 → `relay_heartbeat`（默认开）让心跳也走网关 | `9a3d4e8` |
| 2026-09-22 | **CPI-12** relay 域缺 node→后端用户层鉴权链路 → 已定设计并实现（节点持用户 token，网关按路径透传 Authorization） | 见 relay 系列提交 |
| 2026-09-22 | 本机能力面 relay 域 + relaypolicy 单一策略来源（网关/本机两道闸） | 见 relay 系列提交 |
| 2026-09-22 | **CPI-9** proxy 域落地（标志归后端，node.conf 为投影；读→改→整份写回→同步） | `c335296` |
| 2026-09-22 | relay 补上漏掉的限流（固定窗口 20/秒，超限 429 `rate_limited`） | `c335296` |
| 2026-09-22 | **CPI-18~21** 配置链路四个缺陷 + 一处悬空命名空间 | `84dd7170` |
| 2026-09-22 | **CPI-10** nodes/zones 域落地（zone ≡ tenant；relay 之上的具名薄封装 + 本机态标记） | `4c3025f` |
| 2026-09-22 | **R1** GNB P1/P2/P3 与 WG 能力探测落地；`Capabilities()` 改为反映实测（`OpenGNBDriver` 改名、不伪造 gnb 产物） | agent `e85eb5a` |
| 2026-09-22 | **R1 出口判据达成**：对真实编出的 gnb 二进制实测 `hot_restart=true, route_proxy=true, log_hot_config=false` | agent `2840888` |
| 2026-09-22 | 本机能力面骨架落地（信封/token 鉴权/HTTP/传输无关 Service 层） | `ee5467e` |
| 2026-09-22 | 本机能力面 service/config 两域 | `28afbb5` |
| 2026-09-22 | 本机能力面 peers 域 | `790edbd` |
| 2026-09-24 | **CPI-23** 后端缺 `.env` → `APP_KEY` 空 → **全站每个请求 500**（测试全绿也发现不了） | 本地操作（`.env` 不入库） |
| 2026-09-24 | **CPI-24** 未覆盖框架默认 `redirectGuestsTo` → 不带 `Accept` 头的未鉴权请求 500 而非 401 | `c98a30b` |
| 2026-09-24 | **CPI-25** `Node` 只有 scope 无实例方法 → 两个好友节点端点 500 → **连接向导「可用设备」恒 0、下一步永远禁用** | `30e3f88` |
| 2026-09-24 | **CPI-26** `SocialDomainService:397` 的 `nodes_count` 硬编码 0（原 `// TODO`） | `30e3f88` |
| 2026-09-24 | **CPI-27** `config/cors.php` 默认值缺 scheme+port → 预检无 ACAO 头，浏览器全线拦（本地已配，生产白名单仍待办） | 本地 `.env`；生产待办（H2 之后） |
| 2026-09-24 | **CPI-28** Feature 套件（43 测试）长期游离在 `Default` 门禁外 → 已并入，`make check` 73/2253 → **126/2398** | `c98a30b` |
| 2026-09-24 | **CPI-29** Testbench 不加载 `bootstrap/app.php` → 测试态/生产态分叉；已在 TestCase 镜像（走框架 `Middleware` 入口以对齐三个静态回调） | `c98a30b` |
| 2026-09-24 | **CPI-30** `mynet_gui` 的 `.env.development`/`.env.production` 是死文件（Quasar 只认 `.env.dev`/`.env.prod`）→ `git mv` 改名（rename 56%/58%，历史保留）+ 同步 9 处引用点（`quasar.config.ts` 手工加载器、`deployment.sh`、`quick-deploy.sh`、`pre-deploy-check.sh`、`verify-debug-disabled.sh`、`.env.example`、`docs/UPGRADE-v2.md`、`docs/06-部署指南.md`、`.github/copilot-instructions-详细版.md`）。实测：dev 日志 `Using .env files: .env, .env.local, .env.dev`；build 日志 `… .env.prod`；`dist/spa` 里 `api.mynet.local` **0 次**、`https://my.mynet.club/api/v1` 已烘入 | `mynet_gui d0961bf` |
| 2026-09-24 | **CPI-30a** Quasar 的 env 优先级与 Vite **相反**：`.env.dev` 覆盖 `.env.local`（`env.js:101-116` 的 `Object.fromEntries(fileList.flatMap(…))`，fileList 里 `.env.local` 第 2 位、`.env.[buildType]` 第 3 位，后者胜出）。本地覆盖必须写 `.env.local.dev`，而它**不被 `.gitignore:13` 的 `.env.*.local` 匹配** → 有误提交风险 | `mynet_gui d0961bf` |
| 2026-09-24 | **T2-6 达成**：mynet_gui 四页真打通新后端，连接向导四步走通并落库（`req=4456240307586718 status=pending`） | 任务达成（载体 `mynet_gui`；产物散在 CPI-23~30 各行） |
| 2026-09-24 | **CPI-36** `guard.sh` 的 A1 把「路径」「变量引用」误判成硬编码凭据 → 收窄 A1D（双引号内以 `$` 开头的不算），A1S 故意保持严格；9 份 guard.sh 同步；13 例变异测试 | 后端 `52374e6`（9 份 guard.sh 随各仓同批提交） |
| 2026-09-22 | **CPI-22** `node_ips.ip_type` 三套分类互相矛盾、两处引用不存在常量 → 模型冻结 1=vpn/2=lan/3=public、`match` 改真实常量、新增 `NetworkSemanticsProjector`（6 例测试） | 详录随移表留 git 历史 |
| 2026-09-22 | **CPI-17** `NodeConfig::updateOrCreateConfig` 从未定义 → 保存路径走不通 → 实现为整体替换 `config`（调用方 `saveFullConfig` 传全量重建数据） | 详录随移表留 git 历史 |
| 2026-09-25 | **CPI-44** `refresh-nodes` 是执行器不存在的域 → `bundle_changed` 链恒退出码 2 → 改 `refresh-config` + 跨包守护 `TestPlanRefreshDomainsExistInExecutor` | `mynet_agent aa7090d` |
| 2026-10-13 | **CPI-47**「17 张表只在 tests/Schema」→ 逐表核过：15/17 已有迁移（`2026_09_23_000001` + `2026_09_25_000002/3/4`）；余 `binary_assets`/`customers` 全仓零引用测试残留，裁定**不迁**（`2026_09_25_000004:32` 注明） | `mynet_api`（B-1） |
| 2026-10-10 | **CPI-72（R2）** UCI 兜底首装 zone 失败、forwarding 重跑重复 → G-11：`mynet/cmd_install_openwrt.go` UCI 步骤重写（捕获 section 名、逐 section 读 `src`/`dest` 幂等）+ `cmd_install_openwrt_test.go` 六场景 | `mynet_agent`（H2/G-11） |
| 2026-10-10 | **CPI-73/74/75（R3/R4/R5）** desktop 双服务机制 / macOS label·prefix·home / Linux 单元与 agent 冲突 → H2 薄壳收敛：系统登记一律委托 `mynet install`（`mynet_desktop/nsis/mynet-agent.nsi:10-16,145-158`、`macos/scripts/postinstall`；`service.ps1`/`systemd/`/`launchd/` 退役） | `mynet_desktop`（H2/G-3/G-4/G-5） |
| 2026-10-10 | **CPI-77（R7）** desktop CI 缺 GUI embed 输入、ref 浮动 fallback → `release.yml:117-142`（mynet_gui checkout + `.env.embed` 构建 + 断言 `/gui/assets/`）、`:103-108` ref 取不到即失败 | `mynet_desktop`（H4/G-12） |
| 2026-10-10 | **CPI-78（R8）** parseFilename 拒 exe/dmg/pkg、variant/ext 混用 → `ReleaseDomainService.php:228` 白名单 + `ARTIFACT_KINDS` + 迁移 `2026_10_10_000001_add_kind` + `ReleaseService.php:32-41` | `mynet_ctl`（H4/G-8） |
| 2026-10-10 | **CPI-79（R9）** init 仅 PPID=1 也清 gnb_es → G-9：`openwrt_mynet.init` 可验证归属（gnb cmdline 含本实例 confdir；归属不可证不杀只记）+ `TestOpenWrtInitGnbEsCleanupUsesVerifiableOwnership` | `mynet_agent`（H3/G-9） |
| 2026-10-10 | **CPI-81（R11）** installer 平台实现全编入、`_win` 不构成约束 → build tag 互斥（`cmd_install_{systemd,launchd,win,unsupported}.go`）+ `platform_filetags_test.go`（`go list` 断言每平台恰一入口） | `mynet_agent`（H1/G-2） |
| 2026-10-10 | **CPI-82（R12）** install 在解析 `--home` 前 requireHome → `resolveInstallHome`（`--home` > `MYNET_HOME`；冲突/都缺拒绝、不回退）+ `cmd_install_test.go` 四例 | `mynet_agent`（H2/G-1） |

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
   archive/2026-10/mynet-back-namespace-drift.md），不要重复记账
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

新守护做三件事：① 后端会下发的 13 条命令逐条断言能映射到 agent 认识的 Op 且能被
`Plan()` 展开成 argv；② 用 `go/ast` 解析 `commands.go`，强制 `knownOps` 列表与
`Plan()` 的 switch **双向同步**（改一边不改另一边即失败）；③ 断言不认识的命令
（含历史上真实漂过的 `mynet.reboot`）**必须显式失败**而非静默忽略。
已做变异测试验证守护真能拦住两个方向的漂移。

> 仍是 CPI-2 / CPI-4 的防复发手段：命令名写错的后果是「节点侧静默失效、后端侧看不出来」，
> 只靠人眼迟早再漂一次。区别是现在它**每次 `make check` 都会跑**，不再依赖有人记得手动执行。

## 2026-10-08 出口授权闭环取证（L1/L2）

- LOOP-1：`ProxyAuthDomainService::grant` 先 bulk revoke 再逐行 save，无事务；中途异常会丢旧授权。grant/revoke 未接现有 BundleDirtySet，pull staleness 也未读授权表，旧快照可能持续有效。已补失败注入回归；grant/revoke 包事务并锁稳定节点行，提交后标 dirty；publisher 链路测试通过。并发双连接争用仍待实测。
- LOOP-2：`StampCloudIntent(nil, revision)` 直接返回，DELETE 后 bundle 缺 proxy 段不会撤销旧 Cloud 许可，旧出口继续生效。已修：省略许可记为 Cloud 停用记录，Local 保留；先红后绿回归与真实 publisher fixture 通过。
- LOOP-3：`proxyCleanupCmds` 只撤 DNS/策略路由，不撤 server NAT/ACL；`executeApply` 明确不回滚 Proxy。旧 nft/iptables 规则复用公共链，ACL 追加且 optional，替换/幂等/fail-closed 无完整证据。待专项修复；不得据已有 Proxy 测试绿勾 L2/L3。

- LOOP-4（已修）：pull 仅用秒级 mtime 的 `>` 比较，会漏同秒 bulk 撤销。真实 API/publisher 测试先红后绿，新增许可内容比较兜底。
- LOOP-3 验收门：`mynet_agent/scripts/check-proxy-permission-gate.sh`；两条计划生成测试真实失败（不是实机防火墙测试）：停用无 nft NAT/ACL 清理，兜底 DROP 标 optional。诊断源码单独保存于 testdata，未加入基线 make check；独立 gate 的失败仍阻止 L2/L3/M4 达成。

- LOOP-3 出口规则专项已修（2026-10-08，用户授权“优化他”）：专表/专链与失败报错取代共享链追加；7 个行为测试及隔离 Linux nft/iptables 内核测试通过。旧失败诊断已换成正式回归，详见 `spec/proxy-permission-loop-playbook.md` §9。Proxy client 全局失败回滚、现存 conntrack 终止与实机流量矩阵不在本项达成范围。

LOOP-3 专项提交证据：agent `3e3d464`；默认 make check 与隔离内核规则验收通过。
