# 八轨对账合并台账（2026-10-13）

这份台账逐项合并 8 份分册的发现，并记录本地代码快照下的处置状态。原 `audit-2026-10-13.md` 是重点总览，原分册是冻结取证；本文件是治理任务的完整来源索引。执行顺序与出口见 [治理计划](governance-plan-2026-10-13.md)，已运行测试的详细证据见 [接续记录](governance-followup-2026-10-13.md)。

## 1. 口径与计数

- 编号 A1/A2/A3/B1/B2/C1/C2/C3 是分册发现出现顺序；A3 对应原①～⑮。每行保留来源行号；组内多个对象仍按原分册合在一项，实施时按工作包子项验收。
- A1-Z01 是原「零消费者端点」整组，不能算 28 个确定无消费者端点：最新 d73962f 已证实 router-keys、avatar/me/url 有消费者，必须保留。
- 原总报告「168+28」是各分册约数，不是去重后的待办数。B2 文本实际有 26 个发现块（自报25），C1 包含 1 条核查通过；以下按原文块与去重映射计数，不强行配成旧总数。
- 「已提交待复验」只表明有对应本地提交及范围证据，不表示本轮重跑行为测试；「已验证本地」有接续记录中的实际测试，但仍不等于生产验收；「进行中未提交」不销账；「部分修复」须把未完成子项继续列入计划。
- 本轮工作是整理/规划与只读核验；未修改业务代码、未接触凭证、未部署、未 push。未对全部项目重新执行测试。guard 自测是在临时仓复跑的本轮行为证据。
- 命名采用 mynet_api/后端；现网库名 mynet_back、back_priv/back_pub、已发布 URL/字段保留。gnb route.conf 是正式驱动配置，禁止按垃圾删除；gnb/utc 不在本轮治理实现范围。

**完整发现块 170 个（含 A1 路由组与 1 个核查通过）；合并 9 个重复来源后有 161 个来源组，其中问题组 160 个、非问题 1 个。另列新增发现/已知账补充 4 项。**这些单位是发现组，不是端点/死类/测试用例的数量。

| 分册 | 发现块数（含组/通过） | SHA256（冻结源文件） |
|---|---:|---|
| [A1](audit-2026-10-13/A1-mynet-api-modules.md) | 18 | `dbf444c1a6367b83695da0d1bbded9c8e39479b49ebfe0df5c3863cf49c2e340` |
| [A2](audit-2026-10-13/A2-mynet-api-services.md) | 22 | `68999e87709b23a47b87bd8f6881ea4ba1c4944e9a5842e7d62601e8831a45b4` |
| [A3](audit-2026-10-13/A3-mynet-api-tests-docs.md) | 15 | `577c04314e04d39ee7d07e4f6d11b144e478768237603815794df49c7ed667e1` |
| [B1](audit-2026-10-13/B1-cross-repo-contracts.md) | 12 | `75d4b58052ae439d433d5a9ef578e7e22b554271dc751130d977e688abd1422f` |
| [B2](audit-2026-10-13/B2-docs-drift.md) | 26 | `4a77dadd99382d28bf6592586f6bcf5c56bae05c2fa1d45ec4b174389ea5125d` |
| [C1](audit-2026-10-13/C1-mynet-agent.md) | 23 | `9010674dbc408f51457155b21a16943a7f6f6431170e77462c7931618794eac9` |
| [C2](audit-2026-10-13/C2-mynet-gui.md) | 27 | `f06b3352f7f7ad4796b7037efba6bf668aa2ee9e5c109683cba97171988cd286` |
| [C3](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) | 27 | `c0fc337bf685b49790c085756aa8098e4c6a4261b168fe622c49af7891bd8c3a` |

## 2. 当前仓库快照

| 仓 | HEAD | 工作树与限制 |
|---|---|---|
| mynet_api | `873ebc9` | tests/TestCase.php 为他人未提交镜像修复，发布前需集成；497/4790/1skip 为含此修改的前轮工作树验证 |
| mynet_agent | `f2905ec` | 13 项未提交/未跟踪（含裸 mynet_tray 产物）；托盘/服务名/home、handlers/wizard 在改，勿覆盖 |
| mynet_gui | `a874bc3` | 干净；本文生成前快照 |
| mynet_ctl | `dd3a87c` | 存在变更，开工重跑 status |
| mynet_openwrt | `b45a2e7` | 干净；本文生成前快照 |
| mynet_desktop | `4ef90c3` | 干净；本文生成前快照 |
| mynet_web | `dc3ad08` | .githooks/guard.sh 他人未提交；G10 只规划，接手前确认文件归属 |

快照基准在本次文档提交之前；知识库 HEAD 会随本文件提交变化。未推送/线上状态不由本表推断。

## 3. 合并发现与工作包归属

每个原发现只在一个来源组内出现；重复轨道只增加来源，不重复排期。未修/部分/进行中/待裁决/待核实各行均是开放工作。已提交项仍保留 G0 集成复验责任。标题为原发现摘要，完整证据、建议、已知账与疑点在 §5 原文合订中，不因摘要而丢弃。

### A1

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| A1-01 / S1 | GET /users 与 GET /users/search 是只受 auth:sanctum 的跨租户全站用户查询，search 直出他人 email | [A1-01](audit-2026-10-13/A1-mynet-api-modules.md) L14 | 已提交待复验 | G0 | mynet_api d73962f；假端点族仅前 5 子项已删，onboarding 另留 G2 |
| A1-02 / S2 | B4 驱动术语渗入业务/领域对象（轻—中，含跨端契约） | [A1-02](audit-2026-10-13/A1-mynet-api-modules.md) L18 | 待裁决 | G4 | BundleEnvelope 与命令词表是 wire 契约，不能按 B4 字面单端改名 |
| A1-03 / S2 | 骗人端点族（200 + 假数据，违反「文档/响应不说谎」红线，A4 幻影端点同族） | [A1-03](audit-2026-10-13/A1-mynet-api-modules.md) L20 | 部分修复 | G2 | d73962f 删前 5 子项；PUT users/onboarding 仍只记日志，G2 必须双侧收口 |
| A1-04 / S3 | GUI「删除密钥」按钮打的 PATCH /nodes/{id}/keys/{keyId}/status 在 back 无路由（404） | [A1-04](audit-2026-10-13/A1-mynet-api-modules.md) L24<br>[B1-04](audit-2026-10-13/B1-cross-repo-contracts.md) L28 | 待修复 | G2 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-05 / S3 | GUI 连接详情的 GET /route/{routeId}/stats 在 back 无路由（404），且对应 handler 是 TODO 桩 | [A1-05](audit-2026-10-13/A1-mynet-api-modules.md) L26<br>[B1-05](audit-2026-10-13/B1-cross-repo-contracts.md) L34 | 待修复 | G2 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-06 / S3 | GUI 连接历史页打的 GET /monitor/history/connections 在 back 无路由（404），handler 是死链 | [A1-06](audit-2026-10-13/A1-mynet-api-modules.md) L28<br>[B1-06](audit-2026-10-13/B1-cross-repo-contracts.md) L40 | 待修复 | G2 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-07 / S3 | GUI 仍打已归档的 POST /monitor/heartbeat（404） | [A1-07](audit-2026-10-13/A1-mynet-api-modules.md) L30<br>[B1-11](audit-2026-10-13/B1-cross-repo-contracts.md) L70 | 已提交待复验 | G0 | GUI 0683adc 删除旧配置包整簇及用户面心跳封装 |
| A1-08 / S3 | `UserService::updateUser()` 抛 SPL `\DomainException`，控制器 catch 的是 `App\...\DomainException` → 业务拒绝变 500（CPI-32 的新变体：两个 DomainException） | [A1-08](audit-2026-10-13/A1-mynet-api-modules.md) L32 | 已提交待复验 | G0 | 51498f6：统一 App 域异常 |
| A1-09 / S3 | POST/PUT /nodes/{id}/keys 响应夹带内部编排字段（含整个 User 模型） | [A1-09](audit-2026-10-13/A1-mynet-api-modules.md) L34 | 已提交待复验 | G0 | 8c1fa4b：keys 响应白名单 |
| A1-10 / S4 | 零引用死类 26 个 ≈ 2941 行（全仓 php 除 vendor/storage 的 \bClassName\b 交叉核验为 0 引用） | [A1-10](audit-2026-10-13/A1-mynet-api-modules.md) L38 | 已提交待复验 | G0 | 1564646：死类/方法/别名删除；G0 核原清单逐项覆盖 |
| A1-11 / S4 | 未挂路由的控制器 public 方法 30 个 ≈ 1363 行（route 对账 + 内部调用反查均为 0） — 按控制器：`NodeMonitorController`(7：getNodeInfo/getNodeConfig/getNodeRoutes/getNodeFullConfig/getIndexServices/getRouteConfig/g…（完整分册见来源） | [A1-11](audit-2026-10-13/A1-mynet-api-modules.md) L52 | 已提交待复验 | G0 | 1564646：死类/方法/别名删除；G0 核原清单逐项覆盖 |
| A1-12 / S4 | 空目录 3 个 — `app/Modules/Monitor/Domain/Exceptions/`、`app/Modules/Monitor/Domain/ValueObjects/`、`app/Http/Resources/`（`find app/Modules app/Http routes -type d -empty` 实测）— 处置建议：删除或补…（完整分册见来源） | [A1-12](audit-2026-10-13/A1-mynet-api-modules.md) L54 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-13 / S4 | `NodeDeprecatedHeadersMiddleware` + `node.deprecated` 别名：注册了、零路由使用，且注释指向已归档的 /monitor 路径 | [A1-13](audit-2026-10-13/A1-mynet-api-modules.md) L56 | 已提交待复验 | G0 | 1564646：死类/方法/别名删除；G0 核原清单逐项覆盖 |
| A1-14 / S4 | 同能力多头：① `GET /user` 与 `GET /users/profile` 同 handler（route:list 实测）② `GET /nodes/{id}/config` 与 `/nodes/{id}/configuration` 同 handler（`VPN/Routes/api.php:131-132`）③ 两套 bundle 并存：`G…（完整分册见来源） | [A1-14](audit-2026-10-13/A1-mynet-api-modules.md) L58 | 部分修复 | G2 | 搜人及 GUI 旧 bundle 客户端已删；后端 legacy config-bundle/兼容别名仍需消费者复核 |
| A1-15 / S5 | v2 时代痕迹：全局 /api/v2/* → /api/v1/* 重写 + 控制器 docblock 仍写 /api/v2、/api/monitor 路径 | [A1-15](audit-2026-10-13/A1-mynet-api-modules.md) L62 | 待修复 | G2 | 控制器 docblock 与 v2 兼容层分开处置；现存 ctl v2 消费者迁完前不删 Rewrite |
| A1-16 / S5 | 契约文档 back.yaml 双向漂移 | [A1-16](audit-2026-10-13/A1-mynet-api-modules.md) L64 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-17 / S5 | 注释残留不存在的模块/路径 — `app/Providers/ModuleDiscoveryServiceProvider.php:26`（「back 自有模块（VPN/Monitor/Social/**WebSocket**）」——WebSocket 模块已不存在）；`NodeConfigController.php:983,985`（「节点通道走 /mo…（完整分册见来源） | [A1-17](audit-2026-10-13/A1-mynet-api-modules.md) L66 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A1-Z01 / S4 | 零消费者端点约 28 条组（包含已被复核推翻的消费者结论） | [A1-Z01](audit-2026-10-13/A1-mynet-api-modules.md) L68 | 部分修复 | G2 | d73962f 删已复核零消费者者；router-keys、avatar/me/url 有真调用，保留；onboarding 归 G2 |
### A2

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| A2-01 / S2 | `MailTemplateService` 垫片推翻框架的 fail-loud 门控 → 模板邮件静默发空正文 | [A2-01](audit-2026-10-13/A2-mynet-api-services.md) L11 | 已提交待复验 | G0 | 74e534f：MailTemplateService 垫片删除 |
| A2-02 / S3 | `config/mynet.php` 缺 `gnb_crypto_bin` 键，调用点用硬编码兜底掩盖 | [A2-02](audit-2026-10-13/A2-mynet-api-services.md) L15 | 已提交待复验 | G0 | ae39804：生产 gnb_crypto_bin 配置；873ebc9：路径/失败安全 |
| A2-03 / S3 | `config/cors.php` 空值 env → 全开 `['*']` | [A2-03](audit-2026-10-13/A2-mynet-api-services.md) L17 | 已提交待复验 | G0 | c159c2c：空 CORS 明确拒绝 |
| A2-04 / S3 | `app/Exceptions/Handler.php` 整类零绑定死代码，与 `bootstrap/app.php` 双轨且语义不一致 | [A2-04](audit-2026-10-13/A2-mynet-api-services.md) L19 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-05 / S4 | 空目录 4 处 | [A2-05](audit-2026-10-13/A2-mynet-api-services.md) L23 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-06 / S4 | `app/domain/` 整层是死占位，且命名空间与目录大小写不匹配（Linux 上 PSR-4 无法解析） | [A2-06](audit-2026-10-13/A2-mynet-api-services.md) L25 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-07 / S4 | `app/Services/User/UserService.php` 整类零调用，`allocateId` 是伪 ID 假实现且带死变量 | [A2-07](audit-2026-10-13/A2-mynet-api-services.md) L27 | 已提交待复验 | G0 | d73962f：伪 ID 类及控制器消费者删除 |
| A2-08 / S4 | `app/Services/UserService.php::listUsers()` 零调用 | [A2-08](audit-2026-10-13/A2-mynet-api-services.md) L29 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-09 / S4 | `app/Services/stubs/AuditService.php` 是多余垫片（其自述理由不成立） | [A2-09](audit-2026-10-13/A2-mynet-api-services.md) L31 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-10 / S4 | `config/id.php` 与框架副本逐字节相同（纯冗余覆盖），且 10 个键里 9 个全仓零读取（AI SaaS 历史包袱） | [A2-10](audit-2026-10-13/A2-mynet-api-services.md) L33 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-11 / S4 | `config/channel.php` 与框架副本逐字节相同，且 `config('channel.*')` 全仓零读取 | [A2-11](audit-2026-10-13/A2-mynet-api-services.md) L35 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-12 / S4 | `config/logging.php` 的 `admin` 日志通道零使用（连带 ADMIN_LOG_* 两个 env 键） | [A2-12](audit-2026-10-13/A2-mynet-api-services.md) L37 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-13 / S4 | `config/mynet.php` 的 `tenant_isolation_enabled`（`MYNET_TENANT_ISOLATION`）零读取 | [A2-13](audit-2026-10-13/A2-mynet-api-services.md) L39 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-14 / S4 | 编译产物入库：`bootstrap/cache/packages.php`、`services.php` 被 git 跟踪，`bootstrap/cache/.gitignore` 0 字节 | [A2-14](audit-2026-10-13/A2-mynet-api-services.md) L41 | 已提交待复验 | G0 | 4881f91：死层、垫片、重复配置、cache 产物清理 |
| A2-15 / S4 | `app/Exceptions/Api/ApiExceptionHandler` 6 个静态方法里 5 个零调用 | [A2-15](audit-2026-10-13/A2-mynet-api-services.md) L43 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-16 / S4 | `GatewayNotifier::routeChanged()` / `broadcast()` 生产零调用（仅测试覆盖） | [A2-16](audit-2026-10-13/A2-mynet-api-services.md) L45 | 待修复 | G3 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-17 / S4 | `app/helpers.php` 的 `current_tenant_id()` / `with_tenant_id()` 全仓零调用，且带危险默认 | [A2-17](audit-2026-10-13/A2-mynet-api-services.md) L47 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-18 / S5 | `config/tenancy.php` 发布副本已过期漂移（diff 109 行），且硬覆盖 `default_tenant_id => null` 使 `TENANCY_DEFAULT_TENANT_ID` env 失效 | [A2-18](audit-2026-10-13/A2-mynet-api-services.md) L51 | 待核实 | G9 | default_tenant_id=null 是 fail-closed 候选保留项，不直接恢复默认租户 |
| A2-19 / S5 | `.env.example` 与 config/ 的 env 面不同步 | [A2-19](audit-2026-10-13/A2-mynet-api-services.md) L53 | 待修复 | G9 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-20 / S5 | `ConsoleDevSeeder` 直接 `env()` 取值（config:cache 下静默跳过建号） | [A2-20](audit-2026-10-13/A2-mynet-api-services.md) L55 | 待修复 | G9 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A2-21 / S5 | 两个迁移共用时间戳前缀 `2026_09_25_000001` | [A2-21](audit-2026-10-13/A2-mynet-api-services.md) L57 | 保留备案 | G9 | 已发布迁移文件名不改；新迁移使用唯一时间戳 |
| A2-22 / S5 | `public/console/assets` 残留双份 hash 的过期构建 chunk（本地、未入库） | [A2-22](audit-2026-10-13/A2-mynet-api-services.md) L59 | 待核实 | G0 | 本地 gitignored console chunk；清理前确认构建来源/索引，不作入库删除 |
### A3

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| A3-01 / S2 | ① Testbench 镜像缺 bootstrap/app.php 三块配置——SSOT「逐条镜像」要求未满足 | [A3-01](audit-2026-10-13/A3-mynet-api-tests-docs.md) L13 | 进行中未提交 | G0 | tests/TestCase.php 有镜像修改；当前测试通过不等于提交闭环，归 G0 集成 |
| A3-02 / S2 | ② 测试态 api 组挂着生产不存在的 IdentifyTenant，缓解纪律只覆盖 5/34 | [A3-02](audit-2026-10-13/A3-mynet-api-tests-docs.md) L18 | 进行中未提交 | G0 | tests/TestCase.php 有镜像修改；当前测试通过不等于提交闭环，归 G0 集成 |
| A3-03 / S3 | ③ NodeApiTest 把 500 断成可接受期望（反纸面 D2） | [A3-03](audit-2026-10-13/A3-mynet-api-tests-docs.md) L25 | 已提交待复验 | G0 | 110f1d8：create/update 成功断言不再接受 500 |
| A3-04 / S3 | ④ deploy/test-mysql.sh 与 gen-schema.sh 的「生产是 MySQL 8」口径与实际生产引擎（MariaDB 10.11.18）不符 | [A3-04](audit-2026-10-13/A3-mynet-api-tests-docs.md) L30 | 已提交待复验 | G0 | 993b475：MariaDB 方言说明；未据此宣称 MariaDB 行为已验 |
| A3-05 / S3 | ⑤ GnbKeyCliFallbackTest 硬编码旧 workspace 绝对路径，缺失即静默 skip（假绿通道） | [A3-05](audit-2026-10-13/A3-mynet-api-tests-docs.md) L35 | 已验证本地 | G0 | 873ebc9；接续记录真 CLI 3 tests/10 assertions，缺依赖不 skip |
| A3-06 / S3 | ⑥ ProxyExitContractDumpTest 是 dump 工具混在 Default 门禁里：2 条断言 vs 7 个产物文件 + 有副作用写盘 | [A3-06](audit-2026-10-13/A3-mynet-api-tests-docs.md) L40 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-07 / S4 | ⑦ Business/Unit 两个 testsuite 无仓内消费者，条目与 Default 逐条重复 | [A3-07](audit-2026-10-13/A3-mynet-api-tests-docs.md) L47 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-08 / S4 | ⑧ composer.json require-dev 的 fakerphp/faker 零使用 | [A3-08](audit-2026-10-13/A3-mynet-api-tests-docs.md) L52 | 待修复 | G9 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-09 / S4 | ⑨ DomainServicesTest 死断言 | [A3-09](audit-2026-10-13/A3-mynet-api-tests-docs.md) L56 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-10 / S5 | ⑩ README 把 operator 列入「全砍」清单，与 composer/代码现实矛盾 | [A3-10](audit-2026-10-13/A3-mynet-api-tests-docs.md) L61 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-11 / S5 | ⑪ deploy/README 未收录 4 个同目录工具；`cd mynet_back` 目录名与实际不符 | [A3-11](audit-2026-10-13/A3-mynet-api-tests-docs.md) L65 | 部分修复 | G7 | 8e86b88 已改目录称谓；工具索引覆盖仍需补核 |
| A3-12 / S5 | ⑫ tests/Schema/MonitorModule 注释引用不存在的文件 | [A3-12](audit-2026-10-13/A3-mynet-api-tests-docs.md) L69 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-13 / S5 | ⑬ redirectGuestsTo 的静态守卫是纯字符串 grep，可被注释行绕过 | [A3-13](audit-2026-10-13/A3-mynet-api-tests-docs.md) L73 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| A3-14 / S5 | ⑭ 跨仓 fixture 缺失时静默 skip | [A3-14](audit-2026-10-13/A3-mynet-api-tests-docs.md) L77 | 部分修复 | G6 | GNB CLI skip 已删；NodeFace 跨仓 fixture/向量 skip 仍开放 |
| A3-15 / S5 | ⑮ phpunit.xml 遗留 CACHE_DRIVER 旧键 | [A3-15](audit-2026-10-13/A3-mynet-api-tests-docs.md) L81 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
### B1

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| B1-01 / S2 | GUI 四个「有调用方」的鉴权端点在后端（框架 auth + back）根本不存在 | [B1-01](audit-2026-10-13/B1-cross-repo-contracts.md) L10 | 待修复 | G2 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| B1-02 / S2 | 发行面「每 platform+arch 一件」把 windows zip / darwin tgz（自更新归档）挤出发布集，`mynet update` 无归档可取 | [B1-02](audit-2026-10-13/B1-cross-repo-contracts.md) L16 | 待裁决 | G4 | installer/archive 双件发布与 ctl 唯一键冲突；不得只改 updater |
| B1-03 / S2 | mynet_openwrt 的 Lua 节点面整体指向 `/api/v2/monitor/*` 与 `…/api/v2`，而 mynet_api 只有 `/api/v1/node/*`（monitor 已归档、v2 从未存在） | [B1-03](audit-2026-10-13/B1-cross-repo-contracts.md) L22<br>[C3-17](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L45 | 已提交待复验 | G0 | openwrt bdb2939：逐调用映射 v1；HMAC/回执实链仍需 G5 验 |
| B1-07 / S3 | refresh 响应解包两套口径都不对齐信封，其中一套会把 token 刷成 undefined | [B1-07](audit-2026-10-13/B1-cross-repo-contracts.md) L46 | 已提交待复验 | G0 | GUI 6678e11：refresh 信封与 IP sequence；现有单测分别核对 |
| B1-08 / S3 | IP 池删除回退把 poolId 当 sequence 传，与 `DELETE /ippools/{sequence}` 语义不符 | [B1-08](audit-2026-10-13/B1-cross-repo-contracts.md) L52 | 已提交待复验 | G0 | GUI 6678e11：refresh 信封与 IP sequence；现有单测分别核对 |
| B1-09 / S3 | 心跳 `vpn_interfaces` 只判第一个元素；且 back 请求注释里的新/旧格式标注与所有人用法相反 | [B1-09](audit-2026-10-13/B1-cross-repo-contracts.md) L58 | 已提交待复验 | G0 | API c1e8fff：全部 vpn_interfaces 判定及复数标准注释 |
| B1-10 / S4 | 幽灵端点 `/route/connect/{nodeId}` —— 服务层在、后端无、页面零调用 | [B1-10](audit-2026-10-13/B1-cross-repo-contracts.md) L64 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| B1-12 / S4 | `mynet_agent/Makefile:93` 打包器注释写「darwin/windows 用 zip，其余 tgz」，实际 darwin 是 tgz | [B1-12](audit-2026-10-13/B1-cross-repo-contracts.md) L76 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
### B2

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| B2-01 / S3 | guard 自测用例数三处口径不一（45 / 35 / 实际 43） | [B2-01](audit-2026-10-13/B2-docs-drift.md) L10 | 部分修复 | G6 | 计数口径已改 45；本次实测 template/ctl 均 37 通过/8 失败，原因见 N-01 |
| B2-02 / S3 | project-memory「当前基线」表 HEAD/未推送已滞后三天，与「唯一权威」身份不符 | [B2-02](audit-2026-10-13/B2-docs-drift.md) L12 | 待修复 | G0 | project-memory 表刷新后再次滞后；本次快照见合并台账，需 G0 回写入口 |
| B2-03 / S3 | README 称「V1-8/V1-9 仍 ☐」，V1-9 早已 ✅ | [B2-03](audit-2026-10-13/B2-docs-drift.md) L14 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-04 / S3 | spec/paths.md 平台默认 home 与代码/合同冲突（Windows `C:\mynet`、macOS `/usr/local/opt/mynet`） | [B2-04](audit-2026-10-13/B2-docs-drift.md) L16 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-05 / S3 | mynet.md 仍称 gnb 私钥「128 hex = 64 字节，含 seed + 公钥」 | [B2-05](audit-2026-10-13/B2-docs-drift.md) L18 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-06 / S3 | 「完整 13 条见 mynet-overview.md 第九节」指向落空 | [B2-06](audit-2026-10-13/B2-docs-drift.md) L20 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-07 / S3 | GUI_REPO 记录漂移 + 违反「不带 -V2」裁定 | [B2-07](audit-2026-10-13/B2-docs-drift.md) L22 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-08 / S4 | AGENTS.md「8 个 repo」与全文档系 9 仓冲突 | [B2-08](audit-2026-10-13/B2-docs-drift.md) L24 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-09 / S4 | 「`%!d(string=...)` 下现有 5 个」措辞与 workspace 现状不符 | [B2-09](audit-2026-10-13/B2-docs-drift.md) L26 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-10 / S3 | local-api.md 头部与表格自相矛盾：relay 行「已改回 ✅」vs 表内仍「❌ 未实现」 | [B2-10](audit-2026-10-13/B2-docs-drift.md) L30 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-11 / S3 | v1 Playbook §3 仓库职责仍按作废 D-4 写，且与自己 §0.5 冲突 | [B2-11](audit-2026-10-13/B2-docs-drift.md) L32<br>[B2-13](audit-2026-10-13/B2-docs-drift.md) L36<br>[B2-20](audit-2026-10-13/B2-docs-drift.md) L54 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-12 / S3 | README 将四份「已冻结旧线」spec 标为「现行」 | [B2-12](audit-2026-10-13/B2-docs-drift.md) L34 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-14 / S4 | back-baggage-cleanup.md 的 RBAC 拆除结论与 operator 回装裁定冲突未标注 | [B2-14](audit-2026-10-13/B2-docs-drift.md) L38 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-15 / S3 | `mynet_ui` 仍被多份现行文档列为「有效/适用项目」 | [B2-15](audit-2026-10-13/B2-docs-drift.md) L42 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-16 / S3 | node-keying.md「依据」指向不存在的旧仓路径 | [B2-16](audit-2026-10-13/B2-docs-drift.md) L44 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-17 / S4 | v1 Playbook 指导用 `make pkg-darwin` 出包，该 target 已移除 | [B2-17](audit-2026-10-13/B2-docs-drift.md) L46 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-18 / S4 | v1 Playbook 称「GUI 仓没有类型检查」 | [B2-18](audit-2026-10-13/B2-docs-drift.md) L48 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| C1-18 / S5 | install-chain-status-20261002.md 是 10-02 过程记录滞留 docs/ 根且指向已退役路径 | [B2-19](audit-2026-10-13/B2-docs-drift.md) L50<br>[C1-18](audit-2026-10-13/C1-mynet-agent.md) L43 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-21 / S3 | CPI-71~82 批次「未修复」大面积失真：R2–R5/R7–R12 对应项已修未移表 | [B2-21](audit-2026-10-13/B2-docs-drift.md) L58 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-22 / S3 | workspace-review R1–R12「均未修复」与代码现状不符 | [B2-22](audit-2026-10-13/B2-docs-drift.md) L60 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-23 / S4 | CPI「修好移入已修复」规则未执行：多条已修条目滞留「待修复」各段 | [B2-23](audit-2026-10-13/B2-docs-drift.md) L62 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-24 / S4 | CPI-47「17 张表只在 tests/Schema、迁移没有」疑已随 B-1 修复，条目未更新 | [B2-24](audit-2026-10-13/B2-docs-drift.md) L64 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
| B2-25 / S4 | 「更正/自纠/撤回旧说法」过程叙述混进最终文档（SSOT §2.3 禁止类） | [B2-25](audit-2026-10-13/B2-docs-drift.md) L68 | 部分修复 | G7 | 621a8a9 已压缩部分修订叙事；playbook/upstream 文档按全条目核尾巴 |
| B2-26 / S4 | project-memory 保留被取代状态的打补丁句式（「已被取代」「用户质疑后自纠」） | [B2-26](audit-2026-10-13/B2-docs-drift.md) L70 | 已提交待复验 | G0 | ctl 621a8a9 + agent 370b26d：文档/台账归位；本次按原条目复核 |
### C1

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| C1-01 / S1 | 私钥经 argv 进执行器并回显到本机 API 响应 | [C1-01](audit-2026-10-13/C1-mynet-agent.md) L9 | 已提交待复验 | G0 | 9525391：私钥 stdin/argv 遮蔽；handlers/wizard 又有未提交修改，集成后复验 |
| C1-02 / S2 | mynetd 二进制链入整个执行器包 | [C1-02](audit-2026-10-13/C1-mynet-agent.md) L11 | 已验证本地 | G3 | **2026-10-13 G3 完成**：localapi B2 CLEAN；cmd/mynetd 仅 keys/config（GUARD-OK 组合根） |
| C1-03 / S2 | mynetd/localapi 行为越界（gnb_ctl/OS route/主机指标） |  [C1-03](audit-2026-10-13/C1-mynet-agent.md) L13<br>[C1-13](audit-2026-10-13/C1-mynet-agent.md) L33 | 已验证本地 | G3 | **2026-10-13**：node_status/route 迁执行器 + Ops；localapi 不 import mynet |
| C1-04 / S2 | guard B2 守护面缺口 | [C1-04](audit-2026-10-13/C1-mynet-agent.md) L15 | 已验证本地 | G3 | **2026-10-13**：B2 扫 cmd/mynetd+mynetd，变异测试 10/0，多 GOOS 干净 |
| C1-05 / S2 | make dist 的 openwrt 腿给 mynetd 带 embed，与 SSOT/文档/CI 三方矛盾 | [C1-05](audit-2026-10-13/C1-mynet-agent.md) L17 | 已提交待复验 | G0 | 9a578a6：OpenWrt 去 embed、webui.html 出产物 |
| C1-06 / S3 | 托盘启停打在 install 从不注册的服务标识上（macOS + Windows） | [C1-06](audit-2026-10-13/C1-mynet-agent.md) L19 | 进行中未提交 | G0 | 托盘/服务常量/paths 工作树修改存在；本次不改他人文件 |
| C1-07 / S3 | 产物仍带 webui.html，安装文档称已无此物 | [C1-07](audit-2026-10-13/C1-mynet-agent.md) L21 | 已提交待复验 | G0 | 9a578a6：OpenWrt 去 embed、webui.html 出产物 |
| C1-08 / S3 | deploy/README.md 两处与实现漂移 | [C1-08](audit-2026-10-13/C1-mynet-agent.md) L23 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C1-09 / S3 | scripts/security_audit.sh 指向已改名的 mynet_back 仓 → 假绿与假红并存 | [C1-09](audit-2026-10-13/C1-mynet-agent.md) L25 | 已提交待复验 | G0 | ee83dd9：后端路径；缺仓失败语义须另核，不把 grep 当行为验收 |
| C1-10 / S3 | 自更新链两个平台洞（CPI-76 现状确认，未修） | [C1-10](audit-2026-10-13/C1-mynet-agent.md) L27 | 待修复 | G4 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C1-11 / S3 | shared/utils/paths.go HomeSource 判据退化 | [C1-11](audit-2026-10-13/C1-mynet-agent.md) L29 | 进行中未提交 | G0 | 托盘/服务常量/paths 工作树修改存在；本次不改他人文件 |
| C1-12 / S4 | shared/errors 整包零消费 | [C1-12](audit-2026-10-13/C1-mynet-agent.md) L31 | 已提交待复验 | G0 | 44339e1：shared/errors 删除 |
| C1-14 / S4 | 死代码 4 处 + 实验工具零引用 | [C1-14](audit-2026-10-13/C1-mynet-agent.md) L35 | 部分修复 | G1 | 44339e1 删 4 死函数；cmd/keyprove 与 keys/genvec 存废仍需复核 |
| C1-15 / S4 | 死分支与冻结测试 | [C1-15](audit-2026-10-13/C1-mynet-agent.md) L37 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C1-16 / S5 | 旧仓名/旧布局残留（注释与脚本） | [C1-16](audit-2026-10-13/C1-mynet-agent.md) L39 | 部分修复 | G7 | f2905ec 命名修正；旧 8097/布局描述仍需逐项查证 |
| C1-17 / S5 | README 漂移 3 处 | [C1-17](audit-2026-10-13/C1-mynet-agent.md) L41 | 部分修复 | G7 | 9a578a6 修构建口径；README 与 B2 规则表仍需 G7 同步 |
| C1-19 / S5 | mynet/driver/gnb.go:42 注释指向不存在的单元文件 | [C1-19](audit-2026-10-13/C1-mynet-agent.md) L45 | 待修复 | G7 | 本次读 mynet/driver/gnb.go:42 仍指 deploy/systemd/mynet_gnb.service，旧提交不能销账 |
| C1-20 / S5 | packaging/darwin 退役残留清点 | [C1-20](audit-2026-10-13/C1-mynet-agent.md) L47 | 非问题保留 | G0 | 原条目即核查通过；后续 9a578a6 已退役 darwin 文档残留并重定向引用 |
| C1-21 / S5 | usage 与实现不符的子命令 | [C1-21](audit-2026-10-13/C1-mynet-agent.md) L49 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C1-22 / S5 | fixture 双源 + 跨仓相对路径 → CI 静默跳过（D2 现状） | [C1-22](audit-2026-10-13/C1-mynet-agent.md) L51 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C1-23 / S5 | 环境性 t.Skip 共 18 处 | [C1-23](audit-2026-10-13/C1-mynet-agent.md) L53 | 待修复 | G6 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
### C2

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| C2-01 / S1 | middleware.ts 的 OAuth callback 开放重定向把 access token 拼进任意外域 URL | [C2-01](audit-2026-10-13/C2-mynet-gui.md) L12 | 已验证本地 | G0 | 90bccf2 白名单 + a874bc3 回调/守卫测试；未浏览器验收 |
| C2-02 / S2 | 调试/占位页挂在生产 UI：ApiTestPage 是 "Coming Soon" 空占位却进生产菜单 | [C2-02](audit-2026-10-13/C2-mynet-gui.md) L16 | 已提交待复验 | G0 | 0235888：生产调试页/菜单/文件剔除 |
| C2-03 / S2 | 部署脚本族两套目标并存，rollback/pre-deploy-check 仍指向旧 dsplat 部署 | [C2-03](audit-2026-10-13/C2-mynet-gui.md) L18 | 已提交待复验 | G0 | 8e623fe：deploy-target 单一来源；不作生产回滚实测 |
| C2-04 / S2 | 三层路由守卫并存互相打架 + 用户面假门椒残留 | [C2-04](audit-2026-10-13/C2-mynet-gui.md) L20 | 已验证本地 | G0 | a874bc3：唯一守卫，vitest 58/58、棘轮184、SPA 构建通过 |
| C2-05 / S3 | MainLayout 登出后跳转不存在的 `/login` 路由 | [C2-05](audit-2026-10-13/C2-mynet-gui.md) L24 | 已提交待复验 | G0 | 6678e11：登出路由/无用 status 请求删除 |
| C2-06 / S3 | 推荐功能删了壳没删，残留 3 条 TS2307 断链并被棘轮固化 | [C2-06](audit-2026-10-13/C2-mynet-gui.md) L26 | 已提交待复验 | G0 | 371edbd：孤儿/推荐残骸删除，0dfd6f2 类型棘轮下调 |
| C2-07 / S3 | 死模块内另有断链/类型漂移 3 处（均在 194 基线内） | [C2-07](audit-2026-10-13/C2-mynet-gui.md) L28 | 已提交待复验 | G0 | 0683adc + 0235888：旧配置包/调试死模块删除 |
| C2-08 / S3 | apiService.getDashboardData 永远返回 mock 假数据 | [C2-08](audit-2026-10-13/C2-mynet-gui.md) L30 | 待修复 | G1 | getDashboardData/mockDashboardData 仍在；先复核零消费者，只删死假方法不删真仪表盘 |
| C2-09 / S3 | CPI-70 现状核实**未修**：`GET /nodes/{id}/status` 每个未配置节点打一次，响应只写不读 | [C2-09](audit-2026-10-13/C2-mynet-gui.md) L32 | 已提交待复验 | G0 | 6678e11：登出路由/无用 status 请求删除 |
| C2-10 / S4 | 12 个零引用组件共 3361 行（流程简化 3a1b53d 后的孤儿） | [C2-10](audit-2026-10-13/C2-mynet-gui.md) L36 | 已提交待复验 | G0 | 371edbd：孤儿/推荐残骸删除，0dfd6f2 类型棘轮下调 |
| C2-11 / S4 | 「配置包 v2.2.0」旧实现整簇死亡（被 bundleEnvelopeService 取代） | [C2-11](audit-2026-10-13/C2-mynet-gui.md) L38 | 已提交待复验 | G0 | 0683adc + 0235888：旧配置包/调试死模块删除 |
| C2-12 / S4 | 缓存三件套死一半：useCache.ts(198 行) 三个导出零调用 | [C2-12](audit-2026-10-13/C2-mynet-gui.md) L40 | 已提交待复验 | G0 | e08ad98：useCache、空文件/testData、middleware 死后半、barrel |
| C2-13 / S4 | 空文件/调试数据残留 | [C2-13](audit-2026-10-13/C2-mynet-gui.md) L42 | 已提交待复验 | G0 | e08ad98：useCache、空文件/testData、middleware 死后半、barrel |
| C2-14 / S4 | router/middleware.ts 后半 ~230 行零调用 | [C2-14](audit-2026-10-13/C2-mynet-gui.md) L44 | 已提交待复验 | G0 | e08ad98：useCache、空文件/testData、middleware 死后半、barrel |
| C2-15 / S4 | Quasar 生成物入仓两份，且曾手改生成文件 | [C2-15](audit-2026-10-13/C2-mynet-gui.md) L46 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-16 / S4 | 三个测试 HTML 随构建发进生产 | [C2-16](audit-2026-10-13/C2-mynet-gui.md) L48 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-17 / S4 | 格式化函数多份重复实现 | [C2-17](audit-2026-10-13/C2-mynet-gui.md) L50 | 待修复 | G8 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-18 / S4 | 四个死 barrel：src/services/{node,route,social,zone}/index.ts 零导入（各文件 `export * from` 转出；notification/index.ts 有真实消费者不在此列）— 处置建议：删或统一走单入口。 | [C2-18](audit-2026-10-13/C2-mynet-gui.md) L52 | 已提交待复验 | G0 | e08ad98：useCache、空文件/testData、middleware 死后半、barrel |
| C2-19 / S4 | i18n 死键规模超一千 | [C2-19](audit-2026-10-13/C2-mynet-gui.md) L54 | 待修复 | G8 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-20 / S4 | console 直用 84 处/11 文件（生产靠 terser drop_console 兜底） | [C2-20](audit-2026-10-13/C2-mynet-gui.md) L56 | 待修复 | G8 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-21 / S4 | 未用 import 两处 | [C2-21](audit-2026-10-13/C2-mynet-gui.md) L58 | 待修复 | G1 | api.ts 仍 import mockDelay；与 mockData 死消费者同批复核 |
| C2-22 / S5 | 两份 openapi 指向虚构/错误域名且主次不明 | [C2-22](audit-2026-10-13/C2-mynet-gui.md) L62 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-23 / S5 | docs/01-06 主文档群停在 Vue CLI/localhost:8000 时代 | [C2-23](audit-2026-10-13/C2-mynet-gui.md) L64 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-24 / S5 | docs/quick-create-node-frontend.md 指向已零引用的 QuickCreateDialog.vue | [C2-24](audit-2026-10-13/C2-mynet-gui.md) L66 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-25 / S5 | 根 nginx.conf 整份是旧 dsplat 模板，与 deploy/production-nginx.conf 分叉 | [C2-25](audit-2026-10-13/C2-mynet-gui.md) L68 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-26 / S5 | dev-tools/check-types.js 注释与基线漂移 | [C2-26](audit-2026-10-13/C2-mynet-gui.md) L70 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C2-27 / S5 | README 口径与用户裁定有出入 | [C2-27](audit-2026-10-13/C2-mynet-gui.md) L72 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
### C3

| ID / 严重度 | 合并问题 | 来源 | 当前状态 | 工作包 | 证据或下一步 |
|---|---|---|---|---|---|
| C3-01 / S3 | manifest 下载 URL 丢失扩展名，非平台默认 ext 的产物 URL 恒 404 | [C3-01](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L11 | 已提交待复验 | G0 | 50f398b：真实 filename、语义版本、kind schema/往返测试 |
| C3-02 / S3 | /d/{product}/{filename} 下载按四元组 first() 匹配，忽略 filename/ext/variant | [C3-02](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L13 | 已提交待复验 | G0 | 50f398b：真实 filename、语义版本、kind schema/往返测试 |
| C3-03 / S4 | buildDownloadUrl 的无前缀 fallback 生成永不可达 URL，且上传不校验扩展名 | [C3-03](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L15 | 待修复 | G4 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-04 / S3 | apps.json 的 latest 用 SQL MAX(version) 字符串比较 | [C3-04](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L17 | 已提交待复验 | G0 | 50f398b：真实 filename、语义版本、kind schema/往返测试 |
| C3-05 / S3 | 随仓 install.sh / upgrade.sh 打的是 /api/v1/* 旧 URL 形态，与仓内路由全不对 | [C3-05](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L19 | 已提交待复验 | G0 | aa7a54a：旧脚本删、公开产品面派生、远端口令 defaults-file |
| C3-06 / S4 | verify-endpoints.php 校验的全是已下线 URL | [C3-06](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L21 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-07 / S4 | ReleaseDomainService::buildDistUrl 零调用且 $variant 参数未用 | [C3-07](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L23 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-08 / S4 | 旧线产品面仍在对外清单里：tui「旧版」+ client「计划中」+ @mynet/* 插件面，且 tui 的 successor 指向已冻结的 client | [C3-08](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L25 | 待修复 | G4 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-09 / S3 | 公开下载页硬编码 ['tui','gnb','utc']，mynet_agent/lua/proxy 产物不上下载面 | [C3-09](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L27 | 已提交待复验 | G0 | aa7a54a：旧脚本删、公开产品面派生、远端口令 defaults-file |
| C3-10 / S3 | 测试建表与迁移分叉：CreatesCtlTables 的 releases 无 kind 列，kind 落库路径零测试 | [C3-10](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L29 | 已提交待复验 | G0 | 50f398b：真实 filename、语义版本、kind schema/往返测试 |
| C3-11 / S3 | deploy.sh 是「rsync --delete 部署即覆盖」，与生产 7 个月漂移、非 git 的事实（reference-ctl-production-deploy）冲突，且无漂移检测 | [C3-11](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L31 | 部分修复 | G5 | aa7a54a 增覆盖警示；生产 diff/发布清单与漂移阻断未完成，G5 开放 |
| C3-12 / S3 | db-setup.sh 远程导入段口令上命令行（ps 可见） | [C3-12](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L33 | 已提交待复验 | G0 | aa7a54a：旧脚本删、公开产品面派生、远端口令 defaults-file |
| C3-13 / S4 | docs/ 三件错位/垃圾内容 | [C3-13](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L35 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-14 / S4 | 一次性导入脚本与已退役下载域默认值 | [C3-14](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L37 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-15 / S5 | README 是 Laravel 脚手架原文 | [C3-15](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L39 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-16 / S2 | 已废弃的「route.conf 路由注入」路线仍在生产链上（违反 O1-③） | [C3-16](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L43 | 部分修复 | G0 | b45a2e7 删旧调用/入口；历史段读取过滤保留，完整 O1/Go apply 迁移未关 |
| C3-18 / S4 | heartbeat.sh 装进包却无人调度，注释口径与 postinst 相反 | [C3-18](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L47 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-19 / S4 | 跟踪在库的备份文件 `luasrc/view/mynet/guest.htm.bak` | [C3-19](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L49 | 已提交待复验 | G0 | b8fc68a：guest.htm.bak 删除 |
| C3-20 / S4 | 13 个 LuCI api_* 端点零调用 | [C3-20](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L51 | 待修复 | G1 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-21 / S5 | `.github/copilot-instructions.md` 仍写 rc.mynet / route.mynet 为现行布局 | [C3-21](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L53 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-22 / S5 | docs/ 陈旧件一批 | [C3-22](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L55 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-23 / S5 | INSTALL.md/README 残留「Windows 计划任务 MyNetd」旧机制口径，与自身 SCM 裁定矛盾 | [C3-23](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L59 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-24 / S5 | README 标题仍是旧仓名 mynet_desktop-V2 | [C3-24](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L61 | 待修复 | G7 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-25 / S4 | 社区域 4 页近空壳占位却在路由里 | [C3-25](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L65 | 待修复 | G10 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-26 / S5 | README 旧仓名 + 旧定位描述；Settings 占位符仍写 /api/v2 | [C3-26](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L67 | 待修复 | G10 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |
| C3-27 / S5 | check.sh 的已知缺陷编号写错（CPI-40 ≠ 实际 CPI-45） | [C3-27](audit-2026-10-13/C3-ctl-openwrt-desktop-web.md) L69 | 待修复 | G10 | 原分册证据保留；本次未取得该项完整销账证据，实施前复核现状/消费者 |

### 新增发现与已知账补充

| ID | 严重度 | 问题 | 状态 | 工作包 | 证据 / 边界 |
|---|---|---|---|---|---|
| N-01 | S3 | guard C4 诊断文本损坏导致 8 项自测失败 | 待修复 | G6 | 模板与 ctl 原样运行均 37/8；$c4_hit 紧贴中文括号，原因乱码；拦截退出码正确，不能称 C4 全失效 |
| N-02 | S1 | 节点私钥预览日志/CLI 失败临时材料与输出出口 | 已验证本地 | G0 | 873ebc9 已收口；接续记录含真 CLI/日志事件测试，GNB 原生扩展日志分支仅静态核 |
| K-01 | S3 | C2 已知账：Reverb app key 源码 fallback 与环境重复 | 待核实 | G9 | websocket.ts:96 仍 fallback；客户端 app key 是公开标识，不能直接判作服务端 secret 泄露；核清密钥类型后删配置副本/分环境 |
| K-02 | S2 | CPI-71/R1：Lua Ops 覆盖合同与 OpenWrt 桥回退 | 待修复 | G5 | C1 已知账与 C3 O1 相邻链；保持命令语义，逐操作收口，不因 API 改路径宣称完成 |

## 4. 疑点与已知账的处置边界

原分册的「已知账命中」「疑点待验证」「覆盖说明」全部随 §5 合订保留。疑点不是已证实漏洞，也不能从合并时消失。G0 按每册疑点逐条登记核验结论，以下优先核验：

| 原始来源 | 必须查证的边界 | 归属 |
|---|---|---|
| A1 疑点1～6 | B4/wire 边界、尾斜杠、BundleEnvelope 私钥豁免、console 端点实际消费者、测试消费者定义、status 双源 | G4 / G2 / G3；未核不删 |
| A2 疑点 | Tool DTO 动态反射、services.slack 消费者、vendor 本地修改一致性、device_fingerprint 长度、ID口径、back_face env 回退退役时点；不得改 vendor | G9 / G0 |
| A3 疑点1～6 | 471/454 历史计数不复现；Handler已删；schema 文件名、Laravel版本、框架重依赖、dev-main别名 | G0 / G7 / G9；旧计数不继续报当前 |
| B1 疑点 | 同 tuple 的 installer/archive、variant/filename、HMAC路径、身份/单位/字段对齐；以双侧真实消费者验证 | G4 / G5 / G2 |
| B2 疑点 | 实际测试/guard计数、gnb未推送、旧树产物、低密度未审文档、darwin退役范围 | G0 / G7；gnb仅记录不实施 |
| C1 疑点1～7 | UCI幂等、install --home、私有工作树资产、本机会话残留、命令前缀分派、跨仓init、gateway行为 | G0 / G5 / G6；不读或删除用户私钥材料 |
| C2 疑点1～5 | 死模块构建原因、/gui/api/v1挂载、诊断页消费者、onboarding与localapi流程归属、中英键差 | G2 / G8 / G0 |
| C3 疑点与已知账 | guest/legacy端点间接调用、手工cron、旧导入源、web定位与构建、设备/SDK未验项 | G1 / G5 / G10 |

原分册 C1/C3 里把 back 简写视为合法的文字，按原总报告 §0.1 的更新裁定解释；只保留作历史引文，不据此恢复废弃称谓。C3「大段路由注入会复发」已被接续代码核正：被删前旧函数只清历史段；完整 O1 迁移仍是另一层开放项。

## 5. 原始分册全文合订

以下为 8 份原文逐字内容（仅追加隔离边界与文件名导航）。这里的「未修复」「只读」「合法 back」等是原审计时点的陈述，当前状态以 §3 和治理计划为准。各册的非问题、疑点、覆盖边界与全部证据均保留，不将它们等同于开放修复项。


---

<!-- SOURCE-BEGIN A1-mynet-api-modules.md -->

# A1 轨审计报告 — mynet_api 模块与接口面（2026-10-13，只读）

- 范围：`mynet_api/app/Modules/**`、`mynet_api/routes/**`、`mynet_api/app/Http/**`（其他目录只读引用）
- 方法：静态精读 + `php artisan route:list --json` 实测（302 条路由，back 自有 137 条）+ 自写 python 路由/控制器/消费者三方对账 + 全 workspace 消费者 grep
- 口径：`back` = mynet_back 合法简写（不列包袱）；gnb 的 route.conf 是正式配置（不列垃圾）；grep 结论只算静态检查
- 已知账对照：`mynet_ctl/docs/knowledge-base/cross-project-issues.md`、`workspace-review-20261010.md`(R1–R12)、`spec/v1-permission-matrix.md`

---

## 发现清单

### S1 安全红线

[错漏|S1] GET /users 与 GET /users/search 是只受 auth:sanctum 的跨租户全站用户查询，search 直出他人 email — 证据 `app/Modules/VPN/Controllers/Api/V1/UserController.php:22-27`（类 docblock 自认「仍未收口」）`/:91-98`（`User::select('user_id','name','email','tenant_id')` 全站 LIKE，无成员/租户判定）`/:46-53`（index 经 `UserService::getUserList()`，`tenant_id`/`status`/`sort_by` 参数收下但不生效）— 影响：任意登录用户可枚举全站用户并取 email，与 A4 删除 `users/{id}` 组的理由（无属主判定层）直接矛盾；且 `tenant_id` 参数在判据不在更易误导调用方 — 处置建议：与 A4 同口径整组下架或先补 Gate，`tenant_id` 参数在生效前应从校验里删除。**已知**（v1-permission-matrix.md §4 洞 2/3 + UserController docblock 显式登记，cross-project-issues 台账未登记）；零 GUI/agent/ctl 消费者（仅 UserSelfSurfaceTest 白名单引用）。

### S2 违反设计

[违反设计|S2] B4 驱动术语渗入业务/领域对象（轻—中，含跨端契约） — 证据 `app/Modules/Monitor/Models/MonitorRtCmd.php:121-124,144-147`（业务模型内的命令词表 `mynet.gnb.refresh.route`/`mynet.wg.refresh.route`）；`app/Modules/VPN/Domain/Bundle/` 全目录 10 个 `Gnb*`、3 个 `Wg*` 值对象（GnbIntent/GnbIdentity/WgPeer…）；`app/Modules/VPN/Models/NodeConfig.php:82-134`（业务模型直接读 `config('vpn.gnb…')`/`wireguard` 配置路径并含 ifname 授权逻辑）— 影响：驱动名渗进业务对象命名/词表，与 SSOT B4「业务对象不得含驱动术语」字面冲突 — 处置建议：列为裁定项而非直接改——命令词表与 BundleEnvelope 是与 agent 的跨端契约（CPI-44/56 一族），改动需两端同步；至少给 MonitorRtCmd 词表抽成翻译层（参照 `NodeKeyDomainService::algorithmForKeyType()` 的做法）。另注：`NodeCapability.php:12`/`RelayTopology.php:8` 已显式写明 B4 口径，说明该红线在部分 VO 上是自觉的，渗漏集中在上述三处。

[违反设计|S2] 骗人端点族（200 + 假数据，违反「文档/响应不说谎」红线，A4 幻影端点同族） — 证据 ① `POST /api/v1/ippools/check-availability` 恒 `is_available:true`（`IPPoolController.php:558-566` `// TODO: 实现IP可用性检查`）② `POST /api/v1/ids/validate` 恒 `$isValid = true`（`IDController.php:134-135`）③ `POST /api/v1/ids/parse` 恒 `detected_type:'uuid'`（`IDController.php:269-279`）④ `GET /api/v1/ids/statistics` 假统计（`IDController.php:232`）⑤ `GET /api/v1/ippools?is_allocated=false` 恒返回空集合却报 "Available IP pools retrieved successfully"（`IPPoolController.php:66-68`）⑥ `PUT /api/v1/users/onboarding` 只记日志、不落库、返 200（`app/Services/UserService.php:158-166`「现在记日志即可」← `UserController.php:253-264`）— 影响：调用方拿「可用」当真去分配 IP 即埋雷；与 CPI-60（TODO 桩骗过验收）同族，比 404 更坏 — 处置建议：删桩或在实现前返 501/空语义明确的响应；`check-availability` 优先（有真实判据需求）。

### S3 错漏（契约断裂 / 错误码错用）

[错漏|S3] GUI「删除密钥」按钮打的 PATCH /nodes/{id}/keys/{keyId}/status 在 back 无路由（404） — 证据 调用链 `mynet_gui/src/pages/node/edit/KeyManagementPage.vue:264,551` → `src/composables/useNodeKeys.ts:210` → `src/services/node/nodeKeysService.ts:104`；back 侧 keys 路由仅 4 条且无 `{keyId}` 子路径（`app/Modules/VPN/Routes/api.php:126-129`，route:list 实测确认）— 影响：密钥删除在 UI 上报「删除失败」（真实功能坏）；同文件 `DELETE /nodes/{id}/keys/{keyId}`（`nodeKeysService.ts:92`←`useNodeKeys.ts:181`）同样 404，仅 `revokeKey` 未接 UI 为潜伏 — 处置建议：back 补 per-key 撤销/状态端点或 GUI 收敛到现有 `DELETE /nodes/{id}/keys`（整组撤销）；属 api↔gui 契约裁定。

[错漏|S3] GUI 连接详情的 GET /route/{routeId}/stats 在 back 无路由（404），且对应 handler 是 TODO 桩 — 证据 `mynet_gui/src/components/route/RouteDetailsDialog.vue:252` → `src/services/route/routesService.ts:52`；back 只有 `GET /route/statistics` 与 `GET /nodes/{id}/connection-stats`（`VPN/Routes/api.php:158,151`）；`RouteController::getStats` 存在但未挂路由且实现为 `// TODO: 实现从数据库获取统计数据`（`RouteController.php:464-489`）— 影响：连接详情弹窗统计恒失败/空 — 处置建议：明确「按路由统计」到底归哪个端点，删掉三个兄弟桩（`findOptimalPath:209`/`analyzeConnectivity:238`/`getConnections:399`）。

[错漏|S3] GUI 连接历史页打的 GET /monitor/history/connections 在 back 无路由（404），handler 是死链 — 证据 `mynet_gui/src/pages/route/ConnectionDetailsPage.vue:170,293`；`app/Modules/Monitor/Controllers/Api/V1/MonitorHistoryController.php:22`（`getNodeConnectionHistory` 存在、全仓无路由引用）+ `Monitor/Services/MonitorHistoryService.php`（514 行仅被该死控制器引用）— 影响：连接历史/流量统计功能整块不可用；旧 /monitor/* 链 2026-09-28 归档（`VPN/Routes/api.php:93-95`）后 GUI 与 handler 两侧都没跟 — 处置建议：把 GUI 迁到 `/nodes/{id}/peer-connections`（NodeMonitorHistoryController，已活）或恢复一条别名路由；死链本身按 S4 清理。

[错漏|S3] GUI 仍打已归档的 POST /monitor/heartbeat（404） — 证据 `mynet_gui/src/services/node/configBundleService.ts:109`（带 X-Node-Id/X-Timestamp 头，注释还写「X-Node-Signature 需要在实际实现中添加」）— 影响：该路径本属节点面（现 `/api/v1/node/heartbeat`，HMAC），GUI 用户态调用本身就不该存在——现为死代码 + 404 — 处置建议：删除该 GUI 调试路径，或如需联调走节点面。

[错漏|S3] `UserService::updateUser()` 抛 SPL `\DomainException`，控制器 catch 的是 `App\...\DomainException` → 业务拒绝变 500（CPI-32 的新变体：两个 DomainException） — 证据 `app/Services/UserService.php:183`（`throw new \DomainException("user {$userId} not found")`）← `UserController::updateProfile`（`UserController.php` updateProfile 仅 catch `\Exception` → `serverErrorResponse`，后者只特判 App 域异常，`BaseApiController.php:197-201`）— 影响：`PUT /users/profile` 在用户行缺失时返 500 而非 404/409 — 处置建议：UserService 统一抛 App 域异常（`DomainException`/`NodeException`），或在 BaseApiController 同时识别 SPL 版。跨轨提示：改点在 app/Services（非本轨），错误码出口在本轨。

[错漏|S3] POST/PUT /nodes/{id}/keys 响应夹带内部编排字段（含整个 User 模型） — 证据 `app/Modules/VPN/Domain/Services/NodeKeyDomainService.php:95-107`（`$result['mail_data']` 含 `User` 模型、`should_send_email`）+ `app/Modules/VPN/Services/NodeKeyService.php:49`（`$result['status_check']`）→ `NodeController.php:845-847,1031-1033` `createdResponse($result, …)` 原样回显 — 影响：API 契约泄漏内部邮件/状态编排字段（`mail_data.user` 序列化出 email/phone 等自有字段）；不是私钥泄漏，但破坏响应形状稳定性 — 处置建议：响应层只挑契约字段（node_id/public_key/key_type/algorithm/generated_at），mail_data 留在服务层传给队列。

### S4 冗余 / 垃圾

[冗余垃圾|S4] 零引用死类 26 个 ≈ 2941 行（全仓 php 除 vendor/storage 的 \bClassName\b 交叉核验为 0 引用） — 清单（file: 行数）：
- `app/Http/Controllers/Concerns/ApiResponse.php`(103，与 BaseApiController 响应助手重复的第二套 successResponse/errorResponse)
- `app/Modules/Monitor/Controllers/Api/V1/MonitorHistoryController.php`(43) + `Monitor/Services/MonitorHistoryService.php`(514)（死链整体）
- `app/Modules/Monitor/DTOs/CreateRtCmdDTO.php`(76)
- `app/Modules/VPN/DTOs/UpdateUserDTO.php`、`UserSearchDTO.php`
- `app/Modules/VPN/Domain/Exceptions/BinaryAssetException.php`；`Domain/Services/NodeCapabilityResolver.php`；`Domain/ValueObjects/{BinaryAssetId,BinaryAssetType,IPAddress,PaginationParams}.php`
- `app/Modules/VPN/Helpers/{IpAddressHelper,RouteStructureNormalizer}.php`
- `app/Modules/VPN/Middleware/{ApiRequestLogger,TenantContextMiddleware}.php`
- `app/Modules/VPN/Models/{IdPool,ServiceConnectionLog,SmsVerification}.php`（SmsVerification=短信验证遗物）
- `app/Modules/VPN/Requests/Node/CreateNodeRequest.php`、`Requests/Route/CreateRouteRequest.php`
- `app/Modules/VPN/Resources/{NodeDetailResource,RouteResource,ServiceResource,TenantDetailResource,TenantResource}.php`（其中 TenantDetailResource/NodeDetailResource 还带 `// TODO: 实现真实统计` 的假字段，:39,:50,:69）
- `app/Modules/VPN/Services/{Cache/TenantConfigCacheService,RouteRequestService,ServiceService,SystemSettingService}.php`
— 影响：约 4.4k 行（含下条死方法）「看起来可用其实是错的/没人用」的 API 面；BinaryAsset* 一族对应 Routes 注释里「BinaryAssetController 在模块迁移中丢失」（`VPN/Routes/api.php:256`）的搬运残骸 — 处置建议：分批删除（Resource/DTO/Helpers 可直接删；SmsVerification/ServiceConnectionLog/IdPool 删前对照迁移表名确认无表绑定）。

[冗余垃圾|S4] 未挂路由的控制器 public 方法 30 个 ≈ 1363 行（route 对账 + 内部调用反查均为 0） — 按控制器：`NodeMonitorController`(7：getNodeInfo/getNodeConfig/getNodeRoutes/getNodeFullConfig/getIndexServices/getRouteConfig/getRouterKeys，:123-680，docblock 仍写 `GET /api/monitor/…`)；`NodeConfigController`(10：getConfig/getConfigForm/getConfigPresets/getDefaultConfig/getFormFields/getSupportedVpnTypes/getVpnConfigTpl/validateConfig/generateConfigPreview/clearCache，:167-705，旧「分散配置端点」R9 遗物)；`NodeController`(8：getNodeTypes/getNodeTypesDef/checkNodeType/getStatusInfo/getAvailableIPPools/getNetworkConfig/getNodeIPs/checkConfigurationStatus，:648-1357)；`RouteController`(4：findOptimalPath/analyzeConnectivity/getConnections/getStats，全是 TODO 桩)；`MonitorHistoryController`(1) — 证据：`php artisan route:list --json` 无对应 URI；`grep -rn "\-><method>(" app tests` 命中的全是同名 service/模型方法（如 `Node::checkConfigurationStatus`、`NodeKeyDomainService::getRouterKeys`），控制器自身零调用 — 处置建议：随 S4 死类同批删；若要恢复能力先挂路由并补契约。

[冗余垃圾|S4] 空目录 3 个 — `app/Modules/Monitor/Domain/Exceptions/`、`app/Modules/Monitor/Domain/ValueObjects/`、`app/Http/Resources/`（`find app/Modules app/Http routes -type d -empty` 实测）— 处置建议：删除或补 .gitignore 存根（无消费者）。

[冗余垃圾|S4] `NodeDeprecatedHeadersMiddleware` + `node.deprecated` 别名：注册了、零路由使用，且注释指向已归档的 /monitor 路径 — 证据 `app/Modules/VPN/VpnServiceProvider.php:48`（aliasMiddleware）；route:list 全表无 `node.deprecated` 中间件；`Middleware/NodeDeprecatedHeadersMiddleware.php:8-13`（「指向统一的 /monitor/node/bundle…端点暂不删除」——被标记的那批端点已于 2026-09-28 归档）— 处置建议：连别名一起删；R9 的「逐步关闭」已被整组归档替代。

[冗余|S4] 同能力多头：① `GET /user` 与 `GET /users/profile` 同 handler（route:list 实测）② `GET /nodes/{id}/config` 与 `/nodes/{id}/configuration` 同 handler（`VPN/Routes/api.php:131-132`）③ 两套 bundle 并存：`GET /nodes/{id}/config-bundle`（legacy「一次性配置包」，`NodeConfigController.php:931-979`）与 `GET /nodes/{id}/bundle`（BundleEnvelope schema v2，:981-1020），GUI 两套 service 各调一套（`configBundleService.ts` vs `bundleEnvelopeService.ts`）④ 两套搜人：`UserController::search` 与 `SocialController::searchUsers`（后者带成员判定，Routes 注释已认定为正统）— 影响：契约面双份、后续改动易只改一处（CPI-56「同一规则两处实现」同族风险）— 处置建议：③ 最值得收敛（legacy config-bundle 下线要看 GUI 迁移）；①② 属兼容别名可留但应注释标明主次。

### S5 历史包袱 / 过期文档

[包袱|S5] v2 时代痕迹：全局 /api/v2/* → /api/v1/* 重写 + 控制器 docblock 仍写 /api/v2、/api/monitor 路径 — 证据 `app/Http/Middleware/RewriteApiVersion.php:16-28`（反射清 Symfony 路径缓存的重写）；docblock：`NodeConfigController.php:59-61,932-934`、`NodeController.php:968-970`、`TenantController.php:129,150`、`NodeHeartbeatController.php:34,270`、`NodeMonitorController.php:54,219` 等 — 影响：① 新人按 docblock 找 `/api/v2/...` 会以为有 v2 面；② 重写兜底让旧客户端「看起来没坏」——实测 `mynet_ctl` 的 `/api/v2/user/zones` 经重写落到 `/api/v1/user/zones` 仍 404（CPI-69 已知，重写只救了 `/api/v2/nodes` 那条），兜底掩盖了一半断链 — 处置建议：docblock 统一改回真实 URI；重写层去留随 CPI-69 的 Zone→Tenant 契约翻转一并裁定。

[过期文档|S5] 契约文档 back.yaml 双向漂移 — 证据 `mynet_ctl/docs/api/openapi/back.yaml` 仍含 8 条已归档路径（`/monitor/heartbeat`、`/monitor/node/{info,config,full-config,routes,route-config}`、`/monitor/router-keys`、`/monitor/index-services`、`/monitor/commands/{id}/result`），同时缺 23 条现行路由（`/api/v1/node/*` 节点面 6 条、`/system/back-face-key`、`/nodes/{id}/bundle` 与 `/bundle/publish`、`/auth/revoke-session`、`/console/*` 10 条、`/files/entity/avatar/*` 3 条、`/user/tenants/join`）— 影响：按文档对接必踩空（GUI 就是照旧 monitor 面在调）— 处置建议：随 monitor 归档批同步删旧条目、补节点面与 console 面；文档在 mynet_ctl（越界，只记不动）。

[包袱|S5] 注释残留不存在的模块/路径 — `app/Providers/ModuleDiscoveryServiceProvider.php:26`（「back 自有模块（VPN/Monitor/Social/**WebSocket**）」——WebSocket 模块已不存在）；`NodeConfigController.php:983,985`（「节点通道走 /monitor/node/bundle（HMAC）」——实际为 `/api/v1/node/bundle`）— 处置建议：文字更正（不影响行为）。

### 零消费者端点（暴露但无用；判定证据见「覆盖说明」的引用面清单）

以下 back 自有路由在 mynet_gui/src、mynet_agent、mynet_ctl(app+resources+scripts)、mynet_web、mynet_desktop、mynet_openwrt、mynet_utc 及 public/console 构建产物中均无调用（部分仅被 mynet_api 自身测试引用）：
- `/api/v1/ids/*` 6 条（generate/generate/batch/validate/parse/formats/statistics）——且 4 条是 S2 桩
- `/api/v1/ippools/{auto-allocate,check-availability,user-resources}`
- `/api/v1/node/dns`（DoW，注释称转发上游智能 DNS，agent backclient 无此调用）
- `/api/v1/nodes/{id}/connection-stats`（仅 TenantSourceAuthorityTest）、`/nodes/{id}/ips`、`/nodes/{id}/router-keys`（注释自述已被 config-bundle 收敛，`NodeConfigController.php:936`）、`/nodes/{nodeId}/commands`（用户面；agent 走节点面 `/node/commands`）
- `/api/v1/route/{bulk,bulk/status,node/{nodeId},statistics}`
- `/api/v1/users/{my/statistics,tips/suggestions,search}`（仅测试白名单/无 UI）
- `/api/v1/mynet/health`（仅 OpenAPI/文档引用）
- `/api/v1/files/entity/avatar/me/url`
- `/api/v1/tenants*` console 组 6 条（`GET/POST /tenants`、`/tenants/overview`、`/tenants/{id}` GET/PUT/DELETE、`/tenants/{id}/statistics`）——console SPA 实际消费的是 `/api/v1/console/tenant` 与框架 `/api/v1/tenant/*`（public/console/assets 构建产物实测）
— 影响：约 28 条路由暴露面无产品消费者（其中 tenants 组带 tenant_admin 门禁、风险低；ids/ippools 桩组带骗人响应）— 处置建议：按「删桩→删端点」两步走；tenants 组属 console 预留能力，删留需产品裁定（见疑点）。

---

## 已知账命中

| 已知 | 本轮核对结论 |
|---|---|
| CPI-32（业务拒绝返 500） | **已缓解**：`BaseApiController.php:156-201` 现有 `domainErrorResponse` 且 `serverErrorResponse` 特判 DomainException，`DomainExceptionHttpStatusTest` 在册；**但新变体仍在**（UserService 抛 SPL \DomainException，见 S3-5） |
| CPI-31（/users/onboarding 500） | **疑似已修但台账未回填**：`UserService.php:112,158` 两个方法已存在，不再 500；**但 save 路径不落库只记日志**（S2-⑥），GUI 仍靠 localStorage 兜底 |
| CPI-69（ctl 打 /api/v2 而 back 只有 v1） | **部分被 RewriteApiVersion 救活**（`/api/v2/nodes` 可达），`/api/v2/user/zones`→`/api/v1/user/zones` 仍 404，Zone→Tenant 翻转未完，维持已知 |
| A4 幻影端点族（users/{id} 整组） | 已删净并由 UserSelfSurfaceTest 锁住；本轮发现**同族新成员**：S2 骗人端点 6 处 |
| v1-permission-matrix §4 洞 2/3（/users、/users/search 未收口，index tenant_id 不生效） | 命中 S1-1（代码 docblock 已自认，台账仅在 permission-matrix） |
| CPI-38（心跳缺 vpn_interfaces 判离线） | 行为面归心跳链/其他轨；本轨确认 `HeartbeatRequest` 仍 `nullable`（`VPN/Requests/HeartbeatRequest.php`），语义未变，维持已知 |
| 私钥三出口（$fillable / 生成接口回显 / config bundle） | **本轮逐项核为已收口**：`Models/NodeKey.php:33-36`（fillable 无 private_key + `$hidden`）；`NodeKeyDomainService.php:1067-1073`（saveNodeKeys 返回不含 private_key，DTO `toArray()` 只回 `has_custom_*`，`CreateNodeKeyDTO.php:41-48`）；`ConfigBundleService.php:136-144`（`keys.security.private` 恒空串）。BundleEnvelope 下发私钥属设计，未列入 |

---

## 疑点待验证

1. **B4 边界**：`Domain/Bundle/Gnb*|Wg*` 与 MonitorRtCmd 命令词表是否算 B4 违例——它们同时是与 agent 的 wire 契约（BundleEnvelope schema、命令词汇守护在 agent `backmap_test.go`），删除/改名需两端同步，需 SSOT 裁定而非审计独断。
2. **尾斜杠**：GUI/agent 调 `GET /api/v1/user/tenants/`（带尾斜杠，`mynet_gui/src/services/tenant/tenantService.ts`、`mynet_agent/cmd/mynetd` 相关 client），back 路由无尾斜杠；T2-6 曾真浏览器跑绿、倾向等价，但未在本轮实测 Laravel 13 匹配行为。
3. **`GET /nodes/{id}/bundle`（用户面 getBundle）逐字段未核**：走 `BundleEnvelopePublisher`，其下发私钥按 brief 判定属设计豁免；本轮只核了 legacy config-bundle 的 `keys.security.private` 恒空。若要闭环「用户面任何响应不含私钥」，需对 envelope 逐字段核一次（归私钥专项）。
4. **tenants console 组 6 条**：是「预留 console 能力还是死端点」需产品裁定（routes/api.php:279-302 注释说明了收敛动机，但 console SPA 未接这组）。
5. **零调用判据**：把 mynet_api 自身测试当「非产品消费者」；若裁定测试即消费者，则 S4 死方法/零消费者清单中 connection-stats、users/my/statistics、users/tips/suggestions、users/search 四项降级为「仅测试覆盖」。
6. **`GET /nodes/{id}/status` 响应形状**（GUI 存了不用那条，CPI-70 已知在 GUI 侧）：back 侧 `getStatus` 与 `NodeController::getStatusInfo`（死）语义是否重复未逐行比对。

---

## 覆盖说明

**扫了（全量）**
- `routes/api.php`(316 行)、`routes/web.php`、`routes/console.php` 逐行；`app/Modules/VPN/Routes/api.php` 逐行；路由↔控制器↔方法三方对账用自写脚本 + `php artisan route:list --json`（302 条路由 = back 自有 137 + 框架/vendor 165；vendor 不在本轨）
- `app/Modules/**` 249 文件：全部类的全仓零引用扫描（除 vendor/storage 的 php 全文 \bClassName\b 交叉）、控制器 30 个 public 方法与路由映射、`app/Http/**` 11 文件全读、ServiceProvider/Middleware/Requests/Resources/Models/DTOs/Helpers 逐个过引用
- VPN 模块内部：Controllers 全读重点方法（keys/bundle/users/ippools/ids）、Domain/Services 关键类（NodeKeyDomainService、BaseApiController、BundleEnvelope* 抽查）、Config/vpn.php+gnb.php 存在性
- 消费者引用面（判零调用前复核）：mynet_gui/src 全部 .ts/.vue 的 `api.<verb>(path)` 方法+路径抽取（89 条唯一路径）；mynet_agent 的 backclient/cmd_auth/config_cloud/config_keys_sync/cmd_node/port_owner/nodeface 的 /api/v1 字符串；mynet_ctl（app+resources+scripts）、mynet_web、mynet_desktop、mynet_openwrt、mynet_utc 的 /api/v1 字符串；`mynet_api/public/console/assets/*.js` 构建产物（console SPA 源码不在 workspace）；OpenAPI `back.yaml`/`mynet_gui/docs/openapi*.yaml` 文档面；`tests/` 仅作「仅测试引用」标注
- 敏感面：node_keys 回显链（NodeController keys 四动作 → NodeKeyService → NodeKeyDomainService → NodeKey 模型）、back-face-key、config-bundle、Resources/Controllers 的 private_key/password/secret grep
- B4：Models/ValueObjects/Resources/routes 的 gnb|wireguard|wg 标识符 grep

**没覆盖 / 边界**
- 字段级 api↔gui 契约（枚举、字段名、类型逐一对账）归契约轨任务 #1，本轨只做到「路由/方法/消费者」面
- `app/Services/**`、`database/migrations/**`、`tests/` 质量、`config/**` 归其他轨；引用到的地方只读取证（UserService、ConfigBundleService、composer.json）
- 控制器内部业务逻辑未逐行审；未跑测试、未发真实 HTTP（route:list 为唯一实测）；vendor 框架路由面（165 条，含 storage.local.upload 签名上传）仅点验性质，不在报告结论内
- 字符串 grep 结论均为静态检查；「零引用」已过动态入口（路由表、容器字符串、composer 映射、前端 fetch、Blade）复核，但 PHP 动态派发理论上仍可绕过静态可见性（Notification 替身即为一例，已按「有间接引用」保留）

<!-- SOURCE-END A1-mynet-api-modules.md -->

---

<!-- SOURCE-BEGIN A2-mynet-api-services.md -->

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

<!-- SOURCE-END A2-mynet-api-services.md -->

---

<!-- SOURCE-BEGIN A3-mynet-api-tests-docs.md -->

# A3 轨审计报告 — mynet_api 测试/文档/构建部署面

日期：2026-10-13 ｜ 性质：**只读审计**，未修复、未改任何仓库文件
范围：mynet_api/tests/**、mynet_api 所有 *.md、deploy/**、composer.json/lock、phpunit.xml、Makefile、manage-prod.sh、.gitignore（本仓无 .github）
越界仅作引用：app/、routes/（A1/A2 轨）、mynet_ctl 知识库、deployment-registry.md

---

## 发现清单

### S2（违反设计）

**[违反设计|S2] ① Testbench 镜像缺 bootstrap/app.php 三块配置——SSOT「逐条镜像」要求未满足**
- 证据：`bootstrap/app.php:39-43`（全局 prepend `RewriteApiVersion`+`AddSecurityHeaders`）、`:60-62`（api prepend `EnsureFrontendRequestsAreStateful`）、`:97-137`（`withExceptions` 整块：`shouldRenderJsonWhen`、`ValidationException` 422 render、production-only 500 renderable）↔ `tests/TestCase.php`（grep `RewriteApiVersion|AddSecurityHeaders|EnsureFrontendRequestsAreStateful|shouldRenderJsonWhen` = **0 命中**）。已镜像的只有：`redirectGuestsTo(null)`（TestCase:105）、api 组两条 Resolve 兜底（:82,87）、四条 alias（:70-95）。
- 影响：测试态与生产态的**异常渲染和全局中间件静默分叉**——删掉/改坏 bootstrap 的 422 render、500 兜底、v2→v1 改写（`RewriteApiVersion` 是 ctl 客户端打 /api/v2/* 的唯一承接，CPI-69）、console SPA 的 cookie-session 开关，`make test` 全绿无感。TenantServiceApiTest:155-166 只给 `redirectGuestsTo(null)` 补了静态守卫，其余三块零守卫。
- 处置建议（只建议）：按 SSOT 口径逐条补镜像进 TestCase；或把三块配置收敛成可被测试与 bootstrap 共同引用的单一来源，并给每块补一条「生产文件仍在」的静态守卫（现有 guard 模式照抄）。

**[违反设计|S2] ② 测试态 api 组挂着生产不存在的 IdentifyTenant，缓解纪律只覆盖 5/34**
- 证据：`tests/TestCase.php:73-74` 把 `IdentifyTenant` push 进整个 api 组，其注释 :77-80 自己承认这是「**生产不存在的** IdentifyTenant（读 X-Tenant-ID）」；生产侧 IdentifyTenant 只作为**路由级** middleware 挂在 console/tenants 组（`routes/api.php:40`、`app/Modules/VPN/Routes/api.php:290`）。文件内定下的规矩是用例须 `withoutMiddleware(IdentifyTenant::class)` 才代表生产用户面（引 F-27/A2、dev-plan B-6/A3）——实测只有 5 个测试文件照做（grep 计数），而 24 个文件靠 `X-Tenant-ID` 头走这条生产不会跑的中间件。
- 影响：大多数用例验证的租户上下文链路与生产不一致；用户面路由若在生产漏挂 `tenant.identify`，测试仍绿（X-Tenant-ID 在测试里替它把上下文填了）。SSOT 明确要求镜像 IdentifyTenant 行为，现实是镜像了一个超集。
- 处置建议：要么把 IdentifyTenant 从 api 组撤下、改为按路由组挂（真正镜像），要么把 `withoutMiddleware(IdentifyTenant)` 写进 Tests\TestCase 的默认请求构造里由个别用例显式关闭。

### S3（错漏）

**[错漏|S3] ③ NodeApiTest 把 500 断成可接受期望（反纸面 D2）**
- 证据：`app/Modules/VPN/tests/Feature/Api/NodeApiTest.php:105-106` 与 `:126-127`：`$this->assertContains($response->status(), [200, 201, 500]);`，注释「May return 200 or 500 depending on DomainService implementation」。用例名却是 `test_create_node_returns_success` / `test_update_node_returns_success`。
- 影响：create/update 真的500 时测试照样绿——错误状态被断言成期望值，正是反纸面五条要抓的形态。
- 处置建议：成功路径钉死 201/200；500 场景另立负向用例并断言错误码。

**[错漏|S3] ④ deploy/test-mysql.sh 与 gen-schema.sh 的「生产是 MySQL 8」口径与实际生产引擎（MariaDB 10.11.18）不符**
- 证据：`deploy/test-mysql.sh:75,78`、`deploy/gen-schema.sh:66-69,202` 均写「生产是 MySQL 8」；`mynet_ctl/docs/knowledge-base/deployment-registry.md:328` 明确生产引擎是 **MariaDB 10.11.18**（VER 15.1），:272 亦记载 MySQL 8.4.8 演练与生产「不同方言」。
- 影响：告警文案把方言风险指错了方向——使用者会以为「临时 MySQL 8 全绿 ≈ 生产可上」，而实际连演练引擎都与生产不同族。
- 处置建议：两处文案改为「生产是 MariaDB 10.11，本机 X 与之不同方言」，并把 gen-schema.sh 的交付提示同步改口。

**[错漏|S3] ⑤ GnbKeyCliFallbackTest 硬编码旧 workspace 绝对路径，缺失即静默 skip（假绿通道）**
- 证据：`tests/Unit/Domain/GnbKeyCliFallbackTest.php:18` 默认 `GNB_CRYPTO_BIN=/Users/arthur/Devel/WorkSpacePHP/lyt.com/mynet_gnb/src/bin/gnb_crypto`（AGENTS.md 明示 WorkSpacePHP 路径仅作历史参考）；:19-21 找不到二进制直接 `markTestSkipped`。当前 workspace 的 mynet_gnb 在 `/Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_gnb`。
- 影响：换机器/CI 上该用例静默跳过（skip≠通过），U1「扩展缺失走 CLI 回退」这条链路可能长期无人真正跑过。
- 处置建议：默认值改新 workspace 路径或只认 env；缺二进制时 fail 而非 skip（或至少打醒目警告）。

**[错漏|S3] ⑥ ProxyExitContractDumpTest 是 dump 工具混在 Default 门禁里：2 条断言 vs 7 个产物文件 + 有副作用写盘**
- 证据：`tests/Unit/Domain/ProxyExitContractDumpTest.php:165-175` `write()` 每次跑测向 `$outDir`（默认 `sys_get_temp_dir()/mynet-proxy-contract`，:55-58）落 7 个 JSON；全文件断言仅 `:260-261` 两条（permission 矩阵两点）。头注释自述「L0 合同取证……fixture 落到 ctl 知识库供跨语言对照」，但 `MYNET_PROXY_CONTRACT_OUT` 全仓无消费者（grep 0）。
- 影响：它名义是测试、实际是取证工具——dump 内容错了测试照样绿（五态响应的正确性没有任何 golden 对比）；且门禁跑测带写盘副作用。
- 处置建议：移出 Default（改放独立 tool/入口），或补「五态响应 vs 期望」的 golden 断言后留任。

### S4（冗余/垃圾）

**[冗余|S4] ⑦ Business/Unit 两个 testsuite 无仓内消费者，条目与 Default 逐条重复**
- 证据：`phpunit.xml:23-33`（Business/Unit）条目是 `:10-22`（Default）的逐字子集；grep Makefile/manage-prod.sh/deploy/*.sh/.githooks/* 仅 `Makefile:13`（Default）与 `deploy/test-mysql.sh:93`（Feature）被消费。实测 `phpunit --testsuite Default,Business` 出 2 条 PHPUnit warning「Cannot add file … already added to test suite "Business"」。
- 影响：纯维护冗余＋多套件同选时的 runner 噪音；新增文件要记得同步两处。
- 处置建议：删 Business/Unit，或在注释里标明「仅供人工分组，不进任何门禁」。

**[冗余|S4] ⑧ composer.json require-dev 的 fakerphp/faker 零使用**
- 证据：`composer.json:39`；grep `Faker|faker` 在 app/tests/database 0 命中；`database/factories/` 为空目录。
- 处置建议：删依赖（或删掉空 factories 目录 + autoload 映射二选一）。

**[冗余|S4] ⑨ DomainServicesTest 死断言**
- 证据：`tests/Unit/Domain/DomainServicesTest.php:38` `assertTrue(true)`（其上循环 :29-36 已有真断言）。处置：删。

### S5（历史包袱/文档）

**[过期文档|S5] ⑩ README 把 operator 列入「全砍」清单，与 composer/代码现实矛盾**
- 证据：`README.md:7-9` 称只留「主框架 + infrastructure + auth（+ 待定 logging）」、operator 在「27 个零引用模块全砍」名单里；但 `composer.json:34` require `dsplat/multi-tenant-saas-module-operator`，`app/Modules/VPN/Controllers/Console/V1/MemberController.php:10-11`、`TenantController.php:15` 正在用 Operator/OperatorTenant（console 面活着）。
- 影响：新人按 README 会以为 operator 可删。处置：改 README 模块清单。

**[过期文档|S5] ⑪ deploy/README 未收录 4 个同目录工具；`cd mynet_back` 目录名与实际不符**
- 证据：`deploy/README.md` grep `diag-bundle|stage-bind-tenant|gen-back-face-keys|gen-staging-conf|test-mysql|gen-schema` = **0 命中**（这些文件在 deploy/ 实存，且被知识库/Makefile 引用）；`deploy/README.md:19` 写 `cd mynet_back`，实际目录是 `mynet_api`（deployment-registry.md:19 也是 `cd mynet_api`）。注：按用户口径，mynet_back 作为服务/库名是合法简写——此处只指**目录名**不符。
- 处置建议：README 补工具索引表；目录指令改 `mynet_api`。

**[历史包袱|S5] ⑫ tests/Schema/MonitorModule 注释引用不存在的文件**
- 证据：`tests/Schema/MonitorModule.php:44` 注释「与 database/sql/node_monitor_tables.sql:32 一致」——mynet_api/database/sql/ 只有 8 个 dated SQL，全 workspace find 无 `node_monitor_tables.sql`。实际对齐对象应是 `deploy/schema/mynet_back-schema.sql:928`（uk_node_snapshot 存在）。
- 处置：注释改指真实文件。

**[弱守卫|S5] ⑬ redirectGuestsTo 的静态守卫是纯字符串 grep，可被注释行绕过**
- 证据：`app/Modules/VPN/tests/Feature/Api/TenantServiceApiTest.php:155-166` `assertStringContainsString('redirectGuestsTo(null)', $source)`——把该行**注释掉**后字符串仍在源文件里，守卫照样绿。属反纸面 D5「只 grep 源码」型，但其存在理由（Testbench 不加载 bootstrap）在注释里写得很清楚，是有意的补偿守卫，故降级记账。
- 处置：改为正则剥注释后匹配，或加行为探针。

**[假绿通道|S5] ⑭ 跨仓 fixture 缺失时静默 skip**
- 证据：`tests/Unit/Interop/NodeFaceInteropTest.php:19-21` 向量文件在 `mynet_agent/mynet/keys/testdata/`（跨仓），缺失即 skip；`tests/Unit/Domain/GnbKeyCliFallbackTest`、`app/Modules/VPN/tests/Unit/NodeFaceEdwardsConvertTest:54` 同类（后者注释已写「跳过不等于通过」）。对照 `tests/BundleEnvelopeV2Test.php:29` 缺 fixture 是 `self::fail`（硬失败，正确姿势）。
- 处置：统一按 BundleEnvelopeV2Test 的 fail 姿势。

**[琐碎|S5] ⑮ phpunit.xml 遗留 CACHE_DRIVER 旧键**
- 证据：`phpunit.xml:53` `CACHE_DRIVER`（Laravel 10+ 用 CACHE_STORE，:54 已设）。无害冗余，顺手删。

---

## 已知账命中

| 已知账 | 本轮核对 |
|---|---|
| **CPI-29**（Testbench 不加载 bootstrap/app.php，须镜像） | 部分已修（redirectGuestsTo/alias/Resolve 兜底已镜像），**又见新漂移**——见发现①② |
| **CPI-47**（17 张表只在 tests/Schema、迁移没有） | 现状未变，tests/Schema 5 个模块仍在；另发现 MonitorModule 注释死引用（发现⑫） |
| **CPI-69**（ctl 打 /api/v2、back 只有 v1） | 兜底 = `RewriteApiVersion` 中间件；但该中间件零测试覆盖且未镜像（并入发现①） |
| workspace-review-20261010「make test 471 tests」 | **已过期**：今日实测 479 tests / 4711 assertions / 1 skip（10-10 后 4 个测试提交） |
| 「1 skip = sqlite 并发需 MySQL 双连接」 | 命中且**判定正当**：`ProxyExitApiTest.php:401-404` skip 理由是行锁语义，且该类挂 `#[Group('mysql')]`（:35），`make test-mysql`（test-mysql.sh:93）在真 MySQL 上覆盖 → skip 不是假绿 |
| deploy.py --dry-run 坑 | 本仓**无 deploy.py**，不适用 |
| route.conf / `back` 命名口径 | 本轨文件未见违反；mynet_back 作为库名/服务名出现处（README、gen-schema 默认输出名）均按口径放行 |
| §1.6 点名残留（tests/Stubs/FakeToolRegistry.php、tests/ActionNodeTest.php、tests/Conversation/） | **三者均已不存在**（ls 报 No such file），且全仓 grep 零引用（php/xml/json/md）——已清干净，无待办 |

---

## 重点核查题：phpunit 套件 471 vs 454 的定性（今日实测）

- 实测命令与结果（2026-10-13，本机 sqlite :memory:）：
  - `make test`（= `php vendor/bin/phpunit --testsuite Default`）→ **Tests: 479, Assertions: 4711, Skipped: 1**
  - 裸跑 `vendor/bin/phpunit` → **同样 479 / 4711 / 1**（`phpunit.xml:6` 有 `defaultTestSuite="Default"`，裸跑即 Default）
  - `--list-tests` 各套件：Default 479、Unit 161、Business 10、Feature 308；`--list-test-files` Default = **63 文件 = 全仓 63 个 *Test.php 的全集**（diff 逐行相等）。
- **重叠在哪**：套件**定义层**——`phpunit.xml:11-21`（Default）把 Business 的 2 个 file、Unit 的 5 条目、Feature 的 1 目录**逐条复写**了一遍（Default ⊇ 其余三套件）。
- **谁被重复计数**：**没有**。PHPUnit 11 按文件去重：实测 `--testsuite Default,Business` 仍 479，附 2 条 warning「Cannot add file … already added to suite "Business"」；`--testsuite Business,Unit,Feature` 也是 479。
- **有没有漏跑**：**没有**。Default 收齐全部 63 个测试文件；tests/Schema、tests/Concerns、tests/TestCase 是支撑类，非测试文件。
- **471 vs 454**：**今日两口径均 479，该差值不可复现**。471 是 2026-10-10 的旧计数（workspace-review:92，此后 4 个提交）；454 的来源无从复现（见疑点）。套件重叠不产生重复计数、也不漏跑，可排除其为差值根因。

---

## 疑点待验证

1. **454 的来源**：今日 make test 与裸跑均为 479（含 1 skip）。若委托方的 471/454 是近期实测，差 17 个用例需要提供当时的完整命令输出/提交号才能追（怀疑与测量时点的树状态或 fixtures 在位与否有关）。
2. **app/Exceptions/Handler.php 疑似死代码**（归 A1/A2 轨）：实测容器解析 `ExceptionHandler::class` = `Illuminate\Foundation\Exceptions\Handler`（bootstrap 的 withExceptions 绑定），Testbench 侧绑 `Orchestra\Testbench\Exceptions\Handler`——`App\Exceptions\Handler` 的 render*（renderValidationException :127 等）两界都不走，疑似无人调用。
3. **cross-project-issues.md:128,213 引用 `deploy/schema/mynet_api-schema.sql`**，实际文件名是 `mynet_back-schema.sql`（gen-schema.sh:24 默认输出）——已知账文档自身的文件名漂移，归文档轨。
4. **deployment-registry.md:19 写「Laravel 12」**，composer.json 要求 `^13.0`、README 写 Laravel 13——registry 侧过期，归文档轨。
5. **composer.lock 传递依赖体量**：prod 99 包里含 barryvdh/laravel-dompdf、phpoffice/phpspreadsheet、smalot/pdfparser、laravel/socialite、spatie/laravel-health、firebase/php-jwt 等重量级包（`composer why` 证实均由 `dsplat/multi-tenant-saas` 引入）——本仓无法删，若属上游过度依赖可向框架包提。
6. composer.json `dev-main as 2.7.0 / as 1.99.0` 别名 + `minimum-stability: dev`：可工作但弱化了版本约束语义，是否维持现约定待用户裁定。

---

## 覆盖说明

- **方法**：只读。跑了 `phpunit`（sqlite :memory:，全绿 479/1skip）、`--list-tests/--list-test-files`（只列不执行）、`composer validate`、`composer why`、grep/find/git log 等；未改任何仓库文件、未连生产。注：`make test` 本身会让 ProxyExitContractDumpTest 往 /tmp 写 fixture（测试自带副作用，非审计写仓库，见发现⑥）。
- **范围达成**：tests/** 28 文件全读或抽查；README.md、deploy/README.md 全文；deploy/ 17 文件（脚本类看头注释+关键段，nginx/.rsyncignore/DDL 抽查）；composer.json 全文 + lock 声明层；phpunit.xml、Makefile、manage-prod.sh 全文；.gitignore 全文；本仓无 .github。
- **未覆盖**：app/、routes/ 内部逻辑（A1/A2）；mynet_ctl/agent/gnb 各仓只作交叉引用；数据库实连（生产/远端一律未碰）；deploy/backup-drill、test-mysql 未实跑（起临时 mysqld 属写操作，按红线不跑）。
- **判零引用的方法**：目录存在性 + 全仓 grep（php/xml/json/md/sh/Makefile）双验；§1.6 三项残留即此法判定为「已清干净」。

<!-- SOURCE-END A3-mynet-api-tests-docs.md -->

---

<!-- SOURCE-BEGIN B1-cross-repo-contracts.md -->

# B1 轨审计（横向契约）— 2026-10-13

> 只读审计，未改任何仓库文件。范围：仓与仓之间的接口契约（路径/字段/枚举/单位/命名/端口/产物名/白名单）。
> 基准：`mynet_ctl/docs/knowledge-base/spec/lifecycle-contract.md`、`spec/proxy-permission-loop-playbook.md`、
> `cross-project-issues.md`（CPI 台账）、`workspace-review-20261010.md`。
> 证据口径：每条结论给双侧 file:line（producer + consumer）；静态检查为主，未实机。

## 发现清单

[api↔gui|契约对|S2] GUI 四个「有调用方」的鉴权端点在后端（框架 auth + back）根本不存在 —
甲方 `mynet_gui/src/services/api.ts:292`（POST `/auth/validate-reset-token`）、`:305`（POST `/auth/change-password`）、`:316`（POST `/auth/activate-account`）、`:325`（POST `/auth/resend-activation-email`）；
乙方 `mynet_api/vendor/dsplat/multi-tenant-saas-module-auth/Routes/public.php:22,24`（实际是 `/auth/verify-email`、`/auth/resend-verification`）+ `Routes/api.php:27`（改密实为 `PUT /auth/password`）+ `mynet_api/routes/api.php` 与模块 `Routes/api.php` 全表无 `validate-reset-token`（全仓 grep `activate-account|validate-reset-token|change-password|resend-activation` 在 app/routes/vendor 零命中）—
调用方均在活页面：`mynet_gui/src/composables/usePasswordManagement.ts:77`、`composables/useAuth.ts:310`、`components/auth/AccountActivationDialog.vue:163`、`pages/auth/AccountActivationPage.vue:252`、`components/auth/PasswordResetDialog.vue:316` ⇒ 激活/重发激活/重置码校验/改密四个流程打不存在的端点（404）—
处置建议：GUI 改指框架真实端点（`/auth/verify-email`、`/auth/resend-verification`、`PUT /auth/password`），`validate-reset-token` 需求先定契约再补端点或删除。

[agent↔ctl/desktop↔ctl|契约对|S2] 发行面「每 platform+arch 一件」把 windows zip / darwin tgz（自更新归档）挤出发布集，`mynet update` 无归档可取 —
甲方 `mynet_desktop/.github/workflows/release.yml:246-249`（windows 只上传 `mynet_agent_windows_*.exe`）、`:327-331`（macos 只上传 `*.pkg`+`*.dmg`，`make dist` 产出的 darwin tgz / windows zip 不进 assets）、`:416-421`（发布去重：「每个 platform+arch 一件…darwin→.dmg 内含 .pkg；windows→.exe，其余显式跳过」）；
乙方 `mynet_agent/mynet/upgrade/install.go:265-270`（update 只认 `.tgz/.tar.gz/.zip`，exe/dmg/pkg 直接拒）+ `mynet_ctl/docs/knowledge-base/spec/lifecycle-contract.md:209-213`（§4 矩阵：Windows update archive=zip、macOS=tgz/zip）—
影响：即使 CPI-76（update 不识别 exe）修好，/d/ 与 manifest 里也没有 windows zip / darwin tgz 可下；mac/win 自更新链两头都断（发布面无归档 + 消费面不认安装器）。根因是 ctl `releases` 唯一键不含 ext（见已知账）逼出的「一件」口径与 §4 的 installer/archive 双 kind 相撞 —
处置建议：desktop 发布把归档（tgz/zip）与安装器（exe/dmg/pkg）都发（需 ctl 唯一键纳入 kind/ext），或明确宣布 mac/win 只走安装器升级、把 §4 矩阵改掉；二选一，不要两头都写。

[api↔openwrt|契约对|S2] mynet_openwrt 的 Lua 节点面整体指向 `/api/v2/monitor/*` 与 `…/api/v2`，而 mynet_api 只有 `/api/v1/node/*`（monitor 已归档、v2 从未存在） —
甲方 `mynet_openwrt/luasrc/model/mynet/system.lua:700,805,818`（`sign_path = "/api/v2/monitor/heartbeat"`、`full_url = api_base .. "/api/v2/monitor/heartbeat"`）、`:462`（`PATCH /api/v2/monitor/commands/{id}/result`）、`luasrc/model/mynet/config.lua:8`（`DEFAULT_API_URL = "https://api.mynet.club/api/v2"`）、`validator.lua:163,172`（写入 `api_base_url=…/api/v2`）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:80-95`（节点面现为 `/api/v1/node/heartbeat|commands|commands/{id}/result|bundle`，`:93-95` 注明 `/api/v1/monitor/*` 已于 2026-09-28 归档）+ `mynet_api/routes/api.php` 全文无 v2 前缀（CPI-69 同源）—
附注：SSOT `spec/node-keying.md:196` 的签名示例也仍写 `PATCH /api/v2/monitor/commands/{id}/result` —— spec 与两侧实现一起漂。功能面（lua 心跳/命令）虽在「暂缓」清单，但 `DEFAULT_API_URL` 还喂给 validator/config 的节点配置写入，不是纯死码 —
处置建议：暂缓解除前先把 `config.lua` 的 base 与 `system.lua` 的路径迁到 `/api/v1/node/*`，node-keying.md 示例同步；或在暂缓条目里显式记「恢复时必须先改路径」。

[api↔gui|契约对|S3] GUI 密钥管理页按 keyId 撤销/停用密钥，后端只有「整节点撤销」端点 —
甲方 `mynet_gui/src/services/node/nodeKeysService.ts:91-92`（`DELETE /nodes/{id}/keys/{keyId}`）、`:103-104`（`PATCH /nodes/{id}/keys/{keyId}/status`），调用链 `composables/useNodeKeys.ts:181,210` ← `pages/node/edit/KeyManagementPage.vue:338-345,544`（路由挂载 `src/router/routes.ts:141`）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:126-129`（只有 `POST/GET/DELETE/PUT /nodes/{id}/keys`，无 `/{keyId}` 子资源）—
影响：UI 的「删除密钥」（实为置非活跃）与「撤销特定密钥」恒 404，密钥管理只有一半能用 —
处置建议：back 补 `DELETE|PATCH /nodes/{id}/keys/{keyId}`，或 GUI 收敛到 `DELETE /nodes/{id}/keys`（整节点撤销）并改交互文案。

[api↔gui|契约对|S3] `GET /route/{routeId}/stats` 后端不存在，路由详情弹窗活调用 —
甲方 `mynet_gui/src/services/route/routesService.ts:51-53`，调用方 `components/route/RouteDetailsDialog.vue:252`（挂 `pages/route/RouteListPage.vue:111`）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:155-175`（route 组只有 `/data`、`/statistics`、`/{id}`，无 `/{id}/stats`；`/{id}` 限 `[0-9]+` 单段，`/route/5/stats` 不匹配任何路由）—
影响：连接统计在详情弹窗取数失败（`nodes/{id}/connection-stats` 是后端真实存在的对应面，GUI 没用它）—
处置建议：GUI 改打 `/nodes/{nodeId}/connection-stats`（或 back 补别名），别让弹窗吃 404。

[api↔gui|契约对|S3] `/monitor/history/connections` 已随 monitor 面归档，ConnectionDetailsPage 仍在调 —
甲方 `mynet_gui/src/pages/route/ConnectionDetailsPage.vue:170,293`（`api.get('/monitor/history/connections', {params})`，路由挂载 `src/router/routes.ts:182`）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:93-95`（「旧 /api/v1/monitor/* 已归档下线（2026-09-28）」）+ 同文件 `:150-151`（替代面 `GET /nodes/{id}/peer-connections`、`/{id}/connection-stats`）—
旁证：`src/pages/route/ActiveRoutesPage.vue:304` 注释明写「v2: 使用 RESTful 端点 /nodes/{id}/peer-connections 代替已移除的 /monitor/history/connections」——同仓一半迁了一半没迁 —
处置建议：ConnectionDetailsPage 改打 `/nodes/{id}/peer-connections`。

[api↔gui|契约对|S3] refresh 响应解包两套口径都不对齐信封，其中一套会把 token 刷成 undefined —
甲方 `mynet_gui/src/services/api.ts:363-364`（`const {auth_token,…} = response.data` —— 直接解信封层，实际数据在 `response.data.data`）+ `src/utils/tokenManager.ts:132-143`（另一实现解包正确 `data.success ? data.data : data`，但找的过期字段是 `expires_at`/`expires_in`）；
乙方 `mynet_api/app/Modules/VPN/Controllers/Api/V1/RefreshTokenController.php:96-105`（响应 `{success,code,message,data:{auth_token,refresh_token,auth_token_expires_in,refresh_token_expires_in}}`，字段名是 `*_expires_in` 秒数，无 `expires_in`/`expires_at`）—
影响：`apiService.refreshToken` 存下四个 undefined（现无调用方，属埋雷）；tokenManager 走到 `expires_in` 分支永不命中 → 落到 `tokenManager.ts:134`「默认1小时」，与 back 的 ACCESS_TTL/REFRESH_TTL 语义漂移，且 `:152` 注释自认「假设刷新令牌过期时间与访问令牌相同」（back 刷新窗是 7 天）—
处置建议：两处统一读 `data.data` 与 `auth_token_expires_in`/`refresh_token_expires_in`；删掉死的 apiService.refreshToken 或改成委托 tokenManager。

[api↔gui|契约对|S3] IP 池删除回退把 poolId 当 sequence 传，与 `DELETE /ippools/{sequence}` 语义不符 —
甲方 `mynet_gui/src/composables/useIPPools.ts:150-158`（`const identifier = sequenceNumber || poolId`，无序号时把 16 位全局 ID 发进 `{sequence}` 槽）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:187`（`DELETE /ippools/{sequence}` where `[0-9]+` → `IPPoolController::release`）—
影响：poolId 是纯数字能过路由正则，按 sequence 查不到行 → 404/误删同序号池，静默错行的风险高于干净报错 —
处置建议：GUI 去掉 poolId 回退（缺序号就报错），或 back 显式区分 `DELETE /ippools/by-id/{id}`。

[api↔agent|契约对|S3] 心跳 `vpn_interfaces` 只判第一个元素；且 back 请求注释里的新/旧格式标注与所有人用法相反 —
甲方 `mynet_api/app/Modules/VPN/Controllers/Api/V1/NodeHeartbeatController.php:83-89`（只取 `$validated['vpn_interfaces'][0]` 判 down）+ `Requests/HeartbeatRequest.php:66-71`（注释把单数 `vpn_interface` 标「新格式」、`:78-79` 把复数标「旧格式，兼容性支持」）；
乙方 `mynet_agent/cmd/mynetd/heartbeat.go:70-77`（`heartbeatPayload` 发复数 `vpn_interfaces`）+ `mynet_gui/src/services/node/configBundleService.ts:102-109`（把单数转复数，注释称单数是「旧的」）+ 控制器自身 `:75-78`「统一为复数形式：vpn_interfaces (plural) 是标准格式」—
影响：多 VPN 接口节点（gnb+wg 并存）只有 [0] 参与在线判定，其余被忽略；三处注释口径互斥会误导下一次改动 —
处置建议：控制器遍历全部接口（任一 up 即网络可用，或按接口语义定）；HeartbeatRequest 注释改为与控制器/GUI/agent 一致的「复数=标准」。

[api↔gui|契约对|S4] 幽灵端点 `/route/connect/{nodeId}` —— 服务层在、后端无、页面零调用 —
甲方 `mynet_gui/src/services/route/routesService.ts:12-15`（`POST /route/connect/${nodeId}`），导出至 `composables/useRoutes.ts:162-165`；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:155-175`（route 组无 `connect`；建联语义在 `POST /route/send-request`:162）—
影响：当前全 src 无页面调用（`grep connectToNode(` 只有 composable 与 service），属「看起来能用其实是错的」API（CPI-61 教训同款）—
处置建议：删掉或改指 `/route/send-request` 并在注释里写清映射。

[api↔gui|契约对|S4] 幽灵端点 `/monitor/heartbeat`（GUI 心跳封装指着已归档路径，且签名没实现） —
甲方 `mynet_gui/src/services/node/configBundleService.ts:109-117`（`POST /monitor/heartbeat`，注释自认「X-Node-Signature 需要在实际实现中添加HMAC签名」）；
乙方 `mynet_api/app/Modules/VPN/Routes/api.php:84`（现为 `POST /node/heartbeat`，且带 `node.inflate.body + node.heartbeat.auth` HMAC 门禁）—
影响：现无页面调用（`useConfigBundle.ts:134-138` 导出后零消费），一旦被接上就是 404 或 401；GUI 不具备 HMAC 能力，本就不该直接发心跳 —
处置建议：删除该封装（心跳归 mynetd），或迁 `/api/v1/node/heartbeat` 并补 HMAC（不建议）。

[agent↔ctl|契约对|S4] `mynet_agent/Makefile:93` 打包器注释写「darwin/windows 用 zip，其余 tgz」，实际 darwin 是 tgz —
甲方 `mynet_agent/Makefile:93`（注释）；
乙方 `mynet_agent/scripts/pack.sh:8-10,27-30`（`*_windows_*) ext=zip ;; *) ext=tgz`）+ `mynet_ctl/app/Domain/Release/Services/ReleaseDomainService.php:32-40`（PLATFORMS：windows ext=zip、darwin ext=tgz）—
影响：行为三方一致，仅注释误导；按注释改 pack.sh 会立刻造成 ctl 默认扩展名/URL 反解析对不上 —
处置建议：注释改成「windows→zip，其余→tgz」。

## 已知账命中

- **已知(CPI-69)**：`mynet_ctl` MynetBackClient 打 mynet_api `/api/v2/*`（含 `zone_id` 参数旧口径）。本轮另见上条 S2 —— mynet_openwrt Lua 是同一个 v2/monitor 漂移的**第二个消费方**，台账未记。
- **已知(CPI-70)**：GUI 对未配置节点打 `GET /nodes/{id}/status` 且结果不消费（`nodeService.ts:287` ← `NodeListPage.vue:185`）。
- **已知(CPI-76 + lifecycle G-8 残余)**：update 不识别 exe；`/d/` 按 (product,platform,arch,version) 取件不带 kind，同 tuple installer+archive 会取错 —— 与本轮 S2「发行面根本没有 archive」是同一断链的两半。
- **已知(playbook:131)**：ctl `releases` 唯一键不含扩展名 ⇒ darwin dmg/pkg 撞键、desktop 只发一件 —— 本轮 S2 的直接成因，已在 `release.yml:416-418` 注释里自我记录。
- **已知(CPI-37/38/CPI-57)**：node_keys.algorithm 与密钥实际类型、心跳缺 `vpn_interfaces` 判离线 —— 本轮未重复展开；HeartbeatRequest 与 agent 字段名逐项核对**一致**（node_id/timestamp/cpu_usage/memory_usage/disk_usage/connection_count/vpn_interfaces[].{type,ifname,status,ip,peers_count}/node_monitor.node_self 七字段）。
- **已知(CPI-68)**：登录/续期响应字段名保持 vendor 形状（`SessionAwareUserAuthController.php:97-108` 与 GUI `api.ts:199-221` 对齐）——本轮核对形状一致，仅过期字段消费有漂（见 S3）。
- **已知(CPI-31)**：GUI 活调用 `GET/PUT /users/onboarding`（`stores/onboarding.ts:444,465`），back 实现未完成（已降级 localStorage）。
- **已知(CPI-60)**：`/route/data` 的 `requests` 形状双侧对齐（GUI `routesService.ts:8-9` 取 `response.data.data`）。
- **已知(CPI-71/R1)**：OpenWrt Lua Ops 不满足 connect/config/apply 合同 —— 路径层本轮核对**一致**（LuCI 桥 `mynet.uc:30-45` 的 17 条 `/api/v1/*` 全部存在于 `mynetd/localapi`），断层在语义不在路径。
- **已知(CPI-73/74/75)**：台账仍标「未修复」，但 H2（2026-10-10）已关闭对应 G-3/G-4/G-5：nsis 不再 schtasks、改委托 `mynet install`（`mynet_desktop/nsis/mynet-agent.nsi:10-16,145`）；macOS postinstall 委托 + 清 legacy `club.mynet.mynetd`（`macos/scripts/postinstall:2-15`）；desktop Linux 单元已删、install-linux.sh 委托 —— **台账未跟上修复状态**，建议按维护工作流补记。

## 疑点待验证

1. **`node_keys.private_key` 编码不对称**：`BundleEnvelopePublisher.php:202-205` 只把公钥经 `pubHex()`（base64→hex）转换，`:206,358` 私钥**原样透传**进 `gnb.identity.private_key`，而 `mynet_agent/mynet/driver/gnb.go:293-295` 原样写 `security/<uuid>.private`、gnb 读盘要求 128-hex clamp 布局（CPI-61）。若 PUT keys 时存的不是 GNB 落盘格式，gnb 会 exit(0)。node-keying.md 只写了公钥 base64 与 .private 文件 128-hex，未写 `node_keys.private_key` 的**存储**编码契约 —— 需在 SSOT 补一句或在 publisher 收口。
2. **`GET /ippools/{id}`（show）的 id 语义**：路由限数字，但与 `DELETE /ippools/{sequence}` 的 sequence 口径是否同一维度未核实（controller 未读）。
3. **desktop `pub()` 幂等探针**（`release.yml:396-398`）以 `/d/<product>/<file>` 返回 200 判「已发」；若下载面改走 302/CDN 会重推触发 ctl 唯一键 500 —— 现行 200（playbook:131 实测）但没钉死。
4. GUI `types/index.ts:153` 称 `GET /api/v1/app/invite-code` 只回五个键（框架 `Routes/api.php:33-35` 确实存在该面）—— 键名逐项未与 `InviteCodeController::myCode` 输出比对。

## 覆盖说明

| 契约对 | 做了什么 | 未做 |
|---|---|---|
| api↔gui | 84 个 GUI 调用路径 × 120 条 back/框架路由全量对表；suspect 逐个追调用方判活/死；登录/续期/refresh 响应形状与字段名核对；node status 枚举（-1/0/1/2/99）双侧一致 | 各端点响应体逐字段深比（只核了 auth/route/nodes keys/ippools/status）；WebSocket/Reverb 面未审 |
| api↔agent | 节点面 6 端点路径双侧一致；HeartbeatRequest 字段名/单位/required_with 逐项与 `heartbeat.go` 结构体核对一致；BundleEnvelope 顶层+gnb/wg/proxy/connections 嵌套字段名逐项一致（ProxyPermission/ProxyACL/ConnectionPlan/GnbOptions 完全对齐）；zip/crypto 键双侧均不下发（约定共同遵守 ✓） | generation/checksum 计算细节、HMAC 签名体逐字节（依赖既有契约测试） |
| api↔ctl / agent↔ctl | PRODUCTS/PLATFORMS/ARCHITECTURES/ARTIFACT_KINDS 与 agent `DIST_MATRIX`/pack.sh/产物名对表（mynet_agent_* 前缀、mipsle→mipsel、windows=zip、openwrt=tgz/ipk）；`/d/apps.json`、`/d/{product}/manifest.json` 路径与 agent index.go 形状注释一致；parseFilename↔generateFilename 正则互逆抽验（含 `v0.0.0-<sha>.<owsha>` 双段版本）；kind 枚举 installer/archive 双侧一致 | 真上传→manifest→下载全链（静态）；同 tuple 取件属已知 G-8 残余 |
| agent↔openwrt/desktop | 服务名矩阵核对（systemd `mynetd`+`mynet_gnb`、launchd `com.mynet.mynetd`、SCM `mynetd`、OpenWrt `/etc/init.d/{mynet,mynetd}`）与 lifecycle-contract §3 一致；ipk 不再带 init、装配委托 `mynet install`（O1 已收敛，`mynet_openwrt/Makefile:64,145,208`）；LuCI 桥 17 条 localapi 路径全存在 | OpenWrt Lua Ops 语义（已知 CPI-71）；mac/win 真机登记 |
| 全局 | 端口 SSOT 一致：8096（localapi/WebUI）+ 8710（gateway），`mynet_api/config/gateway.php:23` 用 8710，各仓 guard.sh C2 锁 8096（8711 历史漂移已修，只剩注释）；`back`/mynet_back 命名口径按用户裁定不列问题 | — |

严重度口径：S2=违反设计合同，S3=错漏（功能断/字段漂），S4=冗余垃圾/注释漂移。

<!-- SOURCE-END B1-cross-repo-contracts.md -->

---

<!-- SOURCE-BEGIN B2-docs-drift.md -->

# B2 轨审计 — 文档漂移对账（2026-10-13，只读审计，未改任何仓库文件）

范围：`mynet_ctl/docs/knowledge-base/**`（含 spec/、templates/）与 `mynet_agent/docs/**`；代码仅作验证引用。
口径基线（用户裁定）：gnb 的 `route.conf` 是正式配置不可动（废弃的只是安装器「route.conf 路由注入」）；`back` 是 `mynet_back` 合法简写、库名不动。

## 发现清单

### 断言漂移

[断言漂移|S3] guard 自测用例数三处口径不一（45 / 35 / 实际 43） — 文档 `mynet_ctl/docs/knowledge-base/AGENTS.md:191,244`（「45 用例」）、`mynet_agent/docs/MYNET_V1_PLAYBOOK.md:231`（「35 用例」）+ 事实 `mynet_ctl/docs/knowledge-base/templates/.githooks/guard_test.sh:72-…`（`expect "` 用例调用恰 43 条；grep "expect " 出 45 是含 :42 注释与 :49 函数定义） — 守护自测的数字口径不可信，三个版本互相矛盾 — 处置建议：以脚本输出的 `guard 自测：N 通过` 为准统一两处引用（或让脚本回显用例总数后引用）。

[断言漂移|S3] project-memory「当前基线」表 HEAD/未推送已滞后三天，与「唯一权威」身份不符 — 文档 `mynet_ctl/docs/knowledge-base/project-memory.md:20-29`（agent `db398d2`、ctl `8fc5e6e`、openwrt `f7c956e` 未推送 10、gnb 未推送 2）+ `AGENTS.md:17`（称其为「HEAD / 未推送 / 测试计数的唯一权威」）+ 事实：`git rev-parse HEAD` 各仓为 agent `ceb1a5f`(10-12)、api `5a1f4b5`(10-13)、ctl `f148b0c`(10-12)、gui `0da3881`、desktop `786d9ca`、openwrt `c495ff6`(10-11)，`git log --not --remotes` 显示 openwrt 已 0 未推送、反而是 mynet_web 有 1 条未推送 — 10-11~10-13 又有约 15+ 提交，权威表失真，按表推状态会错判 — 建议刷新表或降级为「2026-10-10 快照」并加「已过期」横幅。

[断言漂移|S3] README 称「V1-8/V1-9 仍 ☐」，V1-9 早已 ✅ — 文档 `mynet_ctl/docs/knowledge-base/README.md:127` + 事实 `long-range-plan-deep.md:309`（V1-9 ✅ 2026-09-24）、`:310`（V1-10 亦 ✅），仅 V1-8（:308）仍 ☐ — 导航层对底座线进度判断错误 — 建议改「V1-8 仍 ☐」。

[断言漂移|S3] spec/paths.md 平台默认 home 与代码/合同冲突（Windows `C:\mynet`、macOS `/usr/local/opt/mynet`） — 文档 `mynet_ctl/docs/knowledge-base/spec/paths.md:137-141`（§3 表）+ 事实 `mynet_agent/shared/utils/paths.go:406-414`（`PlatformDefaultHome()` = darwin/linux `/var/lib/mynet`、windows `C:\ProgramData\MyNet`）与 `spec/lifecycle-contract.md:241`（G-14 关闭记录同一口径） — 路径 SSOT 与生命周期合同给出两套默认树，安装/排障会指错 — 建议 paths.md §3 补一行「默认 home 以 `PlatformDefaultHome()`/lifecycle-contract §3 为准，本表是历史多树现状」。

[断言漂移|S3] mynet.md 仍称 gnb 私钥「128 hex = 64 字节，含 seed + 公钥」 — 文档 `mynet_ctl/docs/knowledge-base/mynet.md:107`（及 :30「128 字节 hex」措辞）+ 事实 `AGENTS.md:47`（私钥 = `clamp(SHA512(seed))`，128 **hex 字符**，非 `seed‖pub`） — 拿 `seed‖pub` 去算会签错（AGENTS 坑表第 2 条），mynet-overview.md:741 早已把此行列为漂移但 mynet.md 未修、README §1.1 仍标「现行」 — 建议改 mynet.md 并在 README 状态列注明待修。

[断言漂移|S3] 「完整 13 条见 mynet-overview.md 第九节」指向落空 — 文档 `AGENTS.md:274`、`dev-plan.md:731` + 事实：`mynet-overview.md` 的「## 9」是「⑦ proxy」（:455），全文 grep「坑/13 条/踩」0 命中；§14「发现的问题与风险」实为 4+10+3 条 — 坑总表索引失真，「开发前必读 13 条」无处可查 — 建议改指 §14 并核对条数，或把 13 条坑补回 overview。

[断言漂移|S3] GUI_REPO 记录漂移 + 违反「不带 -V2」裁定 — 文档 `project-memory.md:99`（「GUI_REPO 默认写 mynet-club/mynet_gui-V2…需用户确认」）、`spec/node-upgrade-runbook-180-182.md:15`（「默认 mynet_gui-V2 未确认」）+ 事实 `mynet_desktop/.github/workflows/release.yml:48`（`GUI_REPO: mynet-club/mynet_gui  # 用户 2026-10-10 确认（不带 -V2）`）、`repo-migration.md:98`（明令不再引入 `-V2` 后缀） — 两份文档仍记录错误仓名且自称「未确认」，与代码及用户裁定冲突 — 建议两处改记 `mynet-club/mynet_gui`（已确认）。

[断言漂移|S4] AGENTS.md「8 个 repo」与全文档系 9 仓冲突 — 文档 `AGENTS.md:3`（「workspace 及其下 8 个 repo」）+ 事实：`AGENTS.md` §5 项目地图 9 行、`repo-migration.md:280`（「workspace 9 个仓全部在 org 下」）、`workspace-review-20261010.md:3`（「范围：9 个仓」）、实测 9 个 `.git`（agent/api/ctl/desktop/gnb/gui/openwrt/utc/web） — 小口径矛盾 — 建议改 9。

[断言漂移|S4] 「`%!d(string=...)` 下现有 5 个」措辞与 workspace 现状不符 — 文档 `AGENTS.md:268`（「下**现有** 5 个」）、`mynet-overview.md:757-762` + 事实：`find . -name "*string=*"` 与按 nodeId `4147069894021819` 检索 workspace 全部 0 命中（属旧树/生产节点物件） — 「现有」会让读者在本 workspace 白找 — 建议改「旧树/181 实测曾有 5 个（不搬）」。

### 互相矛盾

[互相矛盾|S3] local-api.md 头部与表格自相矛盾：relay 行「已改回 ✅」vs 表内仍「❌ 未实现」 — 文档 `spec/local-api.md:17`（「第二次复核又把 relay 那行改回 ✅」）vs `spec/local-api.md:37`（表内 relay 行仍标「❌ 未实现（2026-09-24 复核更正）…路由表没有 /api/v1/relay」）+ 事实 `mynet_agent/mynetd/localapi/server.go:85`（`s.mux.HandleFunc("/api/v1/relay", s.handleRelay)` + `relay.go` 整套） — 同一文档对同一端点给出两个相反结论，代码站在 ✅ 一侧 — 建议把表内 relay 行按 §3 事实改写（或明确该表只剩「意图登记」价值并在行首去状态符号）。

[互相矛盾|S3] v1 Playbook §3 仓库职责仍按作废 D-4 写，且与自己 §0.5 冲突 — 文档 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md:204`（「mynet_web 是『本机域 + 社区域』合并 WebUI 的在建骨架…计划吸收 mynet_ui 与 mynet_gui」）、`:194`（「mynet_gui：用户和**企业管理员**界面」）+ 事实 `AGENTS.md:347-350`（D-4 已作废：mynet_web 只是官网、mynet_gui 才是用户前端）与 `MYNET_V1_PLAYBOOK.md:97-101`（自己引的裁定「GUI = 普通用户前端，管理面走 console」） — v1 上位口径会把人带回收编 mynet_web/给 GUI 塞管理面 — 建议改 §3 两处为现行口径。

[互相矛盾|S3] README 将四份「已冻结旧线」spec 标为「现行」 — 文档 `README.md:72-75`（service/plugins/wizard/auth 行状态列「现行」）+ 事实 `spec/service.md:2-5`、`spec/plugins.md:2`、`spec/wizard.md:2`、`spec/auth.md:2`（均自declared「已冻结的旧线（mynet_lua / mynet_client），不是当前的实现依据」） — 导航表与文档自述相反，冻结线会被当现行实施依据 — 建议 README 状态列改「冻结（旧线对照）」。

[互相矛盾|S3] repo-migration.md 仍按 D-4 旧决策描述 mynet_web（多处未带作废标记） — 文档 `repo-migration.md:44`（「mynet_web/ … 一份 WebUI（本机域 + 社区域）」）、`:66`（「合并两仓」）、`:140`（「新建（合并 mynet_ui + mynet_gui）…已定合并」）、`:321`（「S8 mynet_web…一份 UI 两个功能域」）+ 事实 `AGENTS.md:347`（D-4 2026-09-24 已作废）、`repo-migration.md:275-277`（仅 §4.3 标了作废） — 除 §4.3 外四处仍以旧决策作指导性陈述 — 建议四行补「D-4 已作废」标记或改写为历史记录。

[互相矛盾|S4] back-baggage-cleanup.md 的 RBAC 拆除结论与 operator 回装裁定冲突未标注 — 文档 `spec/back-baggage-cleanup.md:43`（T50「整套 RBAC/权限子系统拆除…不需要 RBAC」）、`:57`（「operator_tenants 表本就不存在…排除该方向」）+ 事实 `AGENTS.md:100-105`（2026-10-01 裁定 operator 回装、roles/operator_tenants/RBAC 随之落地）、`mynet_api/deploy/schema/mynet_back-schema.sql:1082,1269`（`operator_tenants`/`roles` 建表） — 归档状态的旧结论未注「已被 10-01 裁定部分推翻」，照读会以为 RBAC 面不该存在 — 建议文首补一行指向 AGENTS §1.6 例外。

### 过期指针

[过期指针|S3] `mynet_ui` 仍被多份现行文档列为「有效/适用项目」 — 文档 `spec/README.md:5,20`、`spec/control-plane.md:4,437`、`spec/data-models.md:3`、`integration-plan-v2.md:17,50,74,78,97`、`mynet-overview.md:60,71` + 事实：workspace 无 `mynet_ui` 仓（仅 mynet_gui/mynet_web），`spec/local-api.md:9` 已声明「mynet_ui 不是本 workspace 的仓」 — 同一知识库内一处已更正、六份仍引用幽灵仓 — 建议批量把 mynet_ui 引用改注「历史仓，页面已作 mynet_web 素材」。

[过期指针|S3] node-keying.md「依据」指向不存在的旧仓路径 — 文档 `spec/node-keying.md:5`（`mynet_agent-integration/internal/utils/gnb_ed25519.go`、`mynet_agent-integration/internal/daemon/monitor/monitor.go`、`mynet_tui/internal/{utils,tui}/gnb_ed25519.go`）+ 事实：两仓名均不在 workspace，现实现为 `mynet_agent/mynet/keys/gnb_ed25519.go`（及 `gnb_ed25519_sign.go`） — 密钥口径的取证链指空 — 建议改指 `mynet_agent/mynet/keys/`。

[过期指针|S4] v1 Playbook 指导用 `make pkg-darwin` 出包，该 target 已移除 — 文档 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md:83`（「节点侧改动要 make dist + make pkg-darwin 重出包」）+ 事实：`mynet_agent/Makefile` 无 `pkg-darwin`（H2 退役 `packaging/darwin`，`project-memory.md:70`） — 照做直接 make 报错 — 建议改「make dist + mynet_desktop 安装器」。

[过期指针|S4] v1 Playbook 称「GUI 仓没有类型检查」 — 文档 `MYNET_V1_PLAYBOOK.md:102` + 事实 `mynet_gui/package.json:24-25`（`check:types`/`check:types:update`，vue-tsc + 棘轮）、`workspace-review-20261010.md:91`（194 错误/38 文件基线、棘轮通过） — 低估 GUI 防线、字段名风险判断失真 — 建议改写为「有类型棘轮但非零错误」。

[过期指针|S5] install-chain-status-20261002.md 是 10-02 过程记录滞留 docs/ 根且指向已退役路径 — 文档 `mynet_agent/docs/install-chain-status-20261002.md:27`（指向 `packaging/darwin/scripts/postinstall`）+ 事实：`mynet_agent/packaging/` 仍有 darwin 目录但 `postinstall` 委托链已迁 `mynet_desktop/macos/scripts/postinstall`（`project-memory.md:70`） — 带日期的过程对账与在世文档混放（正是「过程记录干扰」痛点） — 建议归档或文首加「历史快照」横幅。

### 废决策残留

[废决策残留|S3] v1 Playbook / repo-migration 仍按 D-4 旧决策指导（与上「互相矛盾」两条同源，此处记废决策面） — 文档 `MYNET_V1_PLAYBOOK.md:204`、`repo-migration.md:44,66,140,321`、`dev-plan.md:658`（#51 行「D-4 | UI 合并（Quasar 两域骨架已立；mynet_ui 页面批量迁移待续）」仍挂 ☑ 叙述）vs `dev-plan.md:211`（D-4 行已标 ~~作废~~）+ 事实 `AGENTS.md:347-350`（D-4 作废裁定） — 三份文档按已作废决策给指令/记状态 — 建议 #51 行正文改为「D-4 已作废，仅落 mynet_ui local/ 6 页」。

### 台账失联

[台账失联|S3] CPI-71~82 批次「未修复」大面积失真：R2–R5/R7–R12 对应项已修未移表 — 文档 `cross-project-issues.md:10-28`（CPI-72~CPI-82 全部标「未修复」）+ 事实 `spec/lifecycle-contract.md:228-242`（G-1/2/3/4/5/8/9/10/11/12/14/15 已关闭并注明对应 R2/R3/R4/R5/R7/R8/R9/R10/R11/R12）+ 代码抽证：`resolveInstallHome`（G-1/R12）、`cmd_install_openwrt.go` UCI 重写 + `cmd_install_openwrt_test.go`（R2）、nsis 无 `service.ps1`/postinstall 委托（R3/R4）、`mynet_desktop/nsis/vendor/wintun/wintun.dll` + `.guard-c4-allow`（R10 缓解）、`cmd_install_{systemd,launchd,win,unsupported}.go` build tag 互斥（R11） — 台账按「未修复」读会重做已完成工作 — 建议 CPI-72/73/74/75/77/78/79/81/82 移「已修复」，CPI-80 注「随包 wintun 缓解、src 未改」；真正仍开：CPI-71(R1)、CPI-76(R6)。

[台账失联|S3] workspace-review R1–R12「均未修复」与代码现状不符 — 文档 `workspace-review-20261010.md:23,106`（「优先问题（均未修复）」「审查发现的问题均保留『未修复』」）+ 事实：同上 G 表关闭记录 + `mynet_agent/mynet/cmd_install.go`（`--home` 解析前置，R12 修）等 — 报告自身是 10-10 快照但被 README §0 列为「开工必读」现行入口，无任何指向修复状态的注记（仅 R1/R6 仍准） — 建议文首加「R 项处置状态见 lifecycle-contract §5 G 表（R2–R5、R7–R12 已关闭）」。

[台账失联|S4] CPI「修好移入已修复」规则未执行：多条已修条目滞留「待修复」各段 — 文档 `cross-project-issues.md:67`（CPI-36 ✅ 已修复仍在 P1 段）、`:306,321`（CPI-30/30a）、`:273`（CPI-22「已修」）、`:906,927`（CPI-18~21/17「已修」）、`:151`（CPI-44 refresh-nodes 仍在待修复）+ 事实：CPI-36/30/30a 在「已修复」表另有重复行（:1054-1057）；CPI-44 已修 —— `mynet_agent/mynetd/commands/commands.go:93-95`（改 `refresh-config` 并注释）+ `backmap_test.go:223` 守护 — 与文件头「更新规则」（修好移到已修复）相悖，双重记账 — 建议按规则移表去重。

[台账失联|S4] CPI-47「17 张表只在 tests/Schema、迁移没有」疑已随 B-1 修复，条目未更新 — 文档 `cross-project-issues.md:172` + 事实 `mynet_api/database/migrations/2026_09_25_000002_create_monitor_tables.php`、`...000003_create_social_tables.php`、`...000004_create_vpn_extension_tables.php`、`2026_09_23_000001_create_vpn_business_tables.php`（dev-plan #31 B-1「create migration 全套」☑） — 欠债台账虚高 — 建议复核后移「已修复」（本条仅静态比对表名，建议逐表核对）。

### 过程记录残留

[过程记录残留|S4] 「更正/自纠/撤回旧说法」过程叙述混进最终文档（SSOT §2.3 禁止类） — 文档 `spec/local-api.md:3-5,9,15-18,56-…,61,195`（「本行原写…更正」「复核更正」多层嵌套）、`upstream-dsplat-issues.md:541`（「本批自纠一处旧口径」）、`:697`（「自纠一条旧口径（原 F-9 的另一半作废）」）、`dev-plan.md:482`（「① 撤回旧说法『无法配长度』」）、`MYNET_V1_PLAYBOOK.md:105-107`（「⚠️ 上面这段里两处已过期」）+ 规则 `AGENTS.md:166-169`（§2.3） — 修订史叙事与结论混排，读者需自行裁决哪句是现状（local-api.md 由此产生 relay 行矛盾，见上） — 建议：结论改写成现状陈述，修订由 git/归档承担；确属「真正的坑」的（如指向空气的 SSOT 引用）单列坑表。

[过程记录残留|S4] project-memory 保留被取代状态的打补丁句式（「已被取代」「用户质疑后自纠」） — 文档 `project-memory.md:54`（「收严（用户质疑后自纠…）」）、`:121`（「（2026-10-10：该状态已被取代 ——…）」） + 规则 `AGENTS.md:166-169` — 记忆体是允许过程记录的载体，但已取代状态段（111-125）整段留白易误导 — 建议把已被取代段压缩为一行历史注。

## 已知账命中

1. **dev-plan §3 #30 C-6 / #16 F-4 状态列依据过期（任务给定先例）** — 属实，但已带注记：`dev-plan.md:622,636` 状态列挂「※ …已过期，见 §3 后『2026-10-10 对齐注』」，`:703-719` 注内给出实测更正（`cmd/mynet_gateway/main.go` 存在、`NewRedisPresence` 被 `:90` 接线、`gateway/cross_instance_test.go` 在；windows 平台文件齐全、`Makefile` DIST_MATRIX 含 windows）。抽证复核成立（`cmd/mynet_gateway/main.go:90`、`gateway/presence_redis.go:29`、`mynet_agent/Makefile:84-91` 含 `windows:windows:amd64:amd64:`）。残余问题：状态列本身仍是旧记号（☐ 撤勾 / ⚠️ 部分），且 `dev-plan.md:717` 的 `Makefile:76` 行号已漂到 `:84`。
2. **#75 ◐→☑ 已修** — `dev-plan.md:684` 状态列已是 ☑。
3. **route.conf 口径** — 抽查 `node-connectivity-checklist.md:92-101`、`troubleshoot-connectivity.md:190-192`、`spec/route-firewall-split.md:46`、`spec/lifecycle-contract.md:162`、`spec/proxy-conf-design.md`（标题「route.conf 的延续」）：均只废弃「route.conf 注入/灌公网段」，gnb `route.conf` 保持正式配置地位，**未发现一刀切口径错误**。
4. **`back` 命名口径** — 全库未发现把 `back` 当待清命名的表述；`mynet_back` 均为数据库/备份语境（`deployment-registry.md`、`dba-operations-ticket-2026-10.md`），合规。
5. **CPI-16（6 份 spec 适用头指向旧线）已在台账自注**（`cross-project-issues.md:940`）；本轮新发现的 `mynet_ui` 残留（见上）可视为该条未清干净的尾巴。

## 疑点待验证

- **测试计数未复跑**（api 471/4690/1skip、ctl 54/426/1skip、gui 41 tests/194 类型错、agent 19 包）：只读审计未执行套件（避免写 storage/cache）；与 workspace-review §4、project-memory 记载互洽但未独立证伪。guard 自测亦未执行，43 用例为静态计数（函数循环调用若存在会低估——未见）。
- **gnb 未推送 2 条**（`project-memory.md:26`）：裸仓 `git log --branches --not --remotes` 受限返回 0，未能证实也未能证伪。
- `AGENTS.md:268` `%!d` 5 个 / `mynet-overview.md:757` 「5 个节点各一份」：物件在旧树/生产节点，workspace 内 0 命中，无法核原数（措辞问题已单列）。
- `AGENTS.md:263` 「私钥三处出口」收口状态、`deployment-registry.md` §8 部署清单、`api-specification.md`（1913 行）端点逐条、`skill-kit.md`/`brand-guide.md`/`url-architecture.md`/`troubleshoot-connectivity.md`/`long-range-plan.md`/`long-range-plan-t.md`：本轮仅低密度抽样或未抽，未发现硬矛盾但不构成核验。
- `mynet_agent/packaging/darwin` 目录仍在（仅 `make pkg-darwin` 与 scripts 已退役）：project-memory 称「退役 packaging/darwin」，实际为部分退役，是否整体删除待裁定（`spec/lifecycle-contract.md:46` 仍写「待退役」，与 project-memory:70「已退役」措辞不一）。

## 覆盖说明（每份文档抽验的断言）

| 文档 | 抽验断言（条数） | 结果 |
|---|---|---|
| AGENTS.md | 9：relaypolicy deny-first（`relaypolicy.go:52-56` ✓）、8096/8710 端口（`mynet_gateway/main.go:32` ✓）、ConsoleAuthController:13-14/:50（vendor 文件逐行 ✓）、31→3~4 module（vendor 4 包 ✓）、crumb-page（webui index.html ✓）、guard 45 用例（✗ 43）、8 repo（✗ 9）、13 条坑/第九节（✗ 落空）、%!d 现有 5 个（✗ 措辞） | 5✓ 4✗ |
| project-memory.md | 8：HEAD/未推送表（✗ 滞后）、lifecycle_contract_test.go / platform_filetags_test.go / resolveInstallHome / PlatformDefaultHome（✓ 齐）、desktop 薄壳（service.ps1/systemd/launchd 均无 ✓、postinstall 委托 ✓）、.guard-c4-allow + wintun.dll + wintun-provenance.md（✓）、GUI_REPO -V2（✗ 已改不带 -V2）、pkg-darwin 退役（✓） | 6✓ 2✗ |
| dev-plan.md | 7：76 项顺序表（✓ 至 #76）、#30/#16/#75 状态注（✓ 已注，状态列仍旧记号）、Makefile:76 DIST_MATRIX（✗ 行号漂至 :84，内容 ✓）、F-4 console SPA / G-4 前半（抽 file:line 命中 ✓）、D-4 行 #51（✗ 旧叙述） | 5✓ 2✗ |
| MYNET_V1_PLAYBOOK.md | 9：BundleEnvelope 双侧（✓）、vpn_driver.go DriverType*（✓:7-8）、driver/{gnb,wireguard}+三测试文件（✓）、IPPool IPS_PER_GROUP=6/calculateIPRangeFromSequence/降级日志（✓ IPPool.php:42,118,85）、TERM_ZONE/terms/termZone（✓ terms.ts:14、cmd_node.go:24）、35 用例（✗）、pkg-darwin（✗）、GUI 无类型检查（✗）、mynet_web 合并骨架/GUI 管理员界面（✗ D-4） | 5✓ 4✗ |
| workspace-review-20261010.md | 5：R11 go list 全编入（与 H1 前状态一致，现已修）、R12 requireHome（现已修）、R2 UCI（现已修）、R8 parseFilename（现已修）、R6 update（**仍成立**：`upgrade/install.go:389` 按名找二进制、`cmd_update.go:87` 按 runtime.GOOS） | R6 仍准，其余 4 条状态过期 |
| cross-project-issues.md | 6：CPI P1-3 cors `['*']`（现 cors.php:6 改 env 白名单、条目半过期）、CPI-42 gateway 总闸默认 false（✓ 仍成立 config/gateway.php:20）、CPI-44 refresh-nodes（✗ 已修未移表）、CPI-47 17 表（✗ 疑已修）、CPI-71~82 批次（✗ 9/12 已修）、维护工作流「修好移表」（✗ 未执行） | 1✓ 5✗ |
| README.md | 7：§3.0 V1-8/V1-9（✗）、§1.2 service/plugins/wizard/auth 现行（✗ 冻结）、§1.4/§6 引用文件（✓ 全部存在）、spec 清单缺 v1-permission-matrix/v1-error-codes/gnb-compat-baseline 等（✗ 不全）、api-specification 1800+ 行（✓ 1913） | 3✓ 4✗ |
| spec/local-api.md | 5：relay 矛盾（✗）、internal/localapi 路径更正（✓ 处理方式带过程叙述）、relay.go HTTPS 反代（✓ :49 注释形状）、ubus 未实现（✓）、token 只种不验（定性，未单测） | 3✓ 1✗ |
| spec/paths.md | 5：paths.go 存在（✓）、mynet subject 命令（✓ commands.go:43,108）、§2 三面职责/mynetd.env 退役（与 ctl `f148b0c` 提交信息互洽 ✓）、平台默认 home（✗ 见清单）、var/subject.json（subject.go 缓存 ✓） | 4✓ 1✗ |
| spec/lifecycle-contract.md | 5：G-1/2/3/4/5/8/9/11/12/14/15 关闭件抽查（resolveInstallHome、cmd_install_* 互斥、nsis/desktop 现状、wintun ✓）、G-2 文件名 `cmd_install_openwrt_only.go` 与实际 `cmd_install_openwrt.go` 不一致（✗ 小漂移） | 4✓ 1✗ |
| spec/node-keying.md | 3：依据路径（✗ 2 条旧仓路径）、gnb 只读口径（✓） | 1✓ 2✗ |
| spec/control-plane.md / driver-abstraction.md / proxy-split.md / proxy-conf-design.md / route-firewall-split.md / route-apply-unification.md / data-models.md / spec/README.md | 各 2–3：mynet_ui 引用（✗ 见清单）、gateway `/v1/relay` 口径（✓ 与 gateway 实现互洽）、proxy-split 新旧线标记（✓）、route.conf 口径（✓）、driver-abstraction「设计稿」状态（实现已超前，未计✗） | 约 10✓ 5✗ |
| conventions.md | 3：mynet_agent_* 前缀（✓ :313）、route.conf 段落（✓ 双层口径）、JS 段 mynet_gui/mynet_web（✓ 现名） | 3✓ |
| mynet-overview.md | 5：D-4 作废条目（✓ :798）、14.2 漂移表 mynet.md 私钥行（✓ 问题登记成立但未修）、%！d 残留行（✗ 措辞）、mynet_ui 项目地图（✗）、13 条坑指针（✗ 同 AGENTS） | 2✓ 3✗ |
| upstream-dsplat-issues.md | 3：4 个框架包（✓ 与 vendor 一致）、archive 收编指针（✓ 文件在）、自纠叙述（入过程残留） | 2✓ |
| mynet.md | 2：私钥布局（✗）、目录形状（security/ed25519 ✓） | 1✓ 1✗ |
| agent 其余 docs（MYNET_NETWORK_MODEL / LOCAL_WEBUI_EXPERIENCE / webui-local-ux / webui-ia / install-chain-status） | 各 2：webui 目录指针（✓ `mynetd/localapi/webui/`）、/api/v1/config/connect 契约（指向 local-api ✓）、8096 端口（✓）、install-chain 的 packaging/darwin 指针（✗） | 约 7✓ 1✗ |
| templates/（Makefile + guard.sh/guard_test.sh） | 3：A1–C4 红线名目（✓ guard.sh 结构与 AGENTS §2.6 对应）、C4_ALLOW/.guard-c4-allow 机制（✓）、用例数（✗ 43≠45） | 2✓ 1✗ |

合计约 80 条断言抽验；S3 15 条 · S4 9 条 · S5 1 条（另 3 条「废决策残留」与「互相矛盾」部分同源，见条内互引）。未复跑任何测试套件、未改任何仓库文件。

<!-- SOURCE-END B2-docs-drift.md -->

---

<!-- SOURCE-BEGIN C1-mynet-agent.md -->

# C1 轨审计报告 — mynet_agent 仓自身（2026-10-13，只读审计）

范围：/Users/arthur/Devel/WorkSpaceAI/mynet-club/mynet_agent 全仓（cmd/、mynet/、mynetd/、gateway/、shared/、packaging/、deploy/、scripts/、docs/、Makefile、.github/、.githooks/、testdata/、secure-backup/）。
判定基准：SSOT 硬约束（mynetd 纯通信层、产物前缀、8096/8710、WebUI 三态构建、安全红线、禁不必要 fallback）。
口径：gnb 的 route.conf 是正式配置不列包袱；back = mynet_back 合法简写。本轮不修改任何仓库文件。

## 发现清单

[错漏|S1] 私钥经 argv 进执行器并回显到本机 API 响应 — 证据：mynetd/localapi/config_api.go:185（`argv = {"config","keys","set","--json","--private", body.PrivateHex}`，私钥上命令行）；mynetd/localapi/wizard.go:331-333（sanitizeWizardOut 显式放行 argv：「argv 里不带口令（走 stdin）」——该前提被 config keys set 打破）；mynetd/localapi/handlers.go:143-151（失败路径 `payload["argv"] = v` **不经 sanitizeValue**）；mynetd/localapi/handlers.go:34-52 + :96-107（`POST /api/v1/command` 直通浏览器 argv，allowedSubcommand 白名单含 "config"，故上述回显可达）— 影响：节点私钥可出现在 HTTP 响应体与同机 `ps` 中，违反「私钥不进响应/日志」与本仓自述「口令不走 argv（会进 ps）」（handlers.go:90）两条红线 — 处置建议（不执行）：keys set 改 stdin/0600 临时文件传递（对齐 auth login --password-stdin）；argv 一并走 redactSecrets 且对 `--private`/secret 关键字行强制遮蔽；补一条「失败响应不含 argv 秘密」的回归测试。

[违反设计|S2] mynetd 二进制链入整个执行器包（纯通信层失守，链接层面）— 证据：`go list -deps ./cmd/mynetd` 实证依赖 mynet（119 个文件的执行器包）、mynet/detector、mynet/driver、mynet/keys、mynet/upgrade；cmd/mynetd/main.go:23 import mynet，:145 调 mynet.SyncPluginProxyFromPolicy；mynetd/localapi/wizard.go:142,184-196 用 mynet.DeviceFingerprint / mynet.Credential / mynet.SaveCredential — 影响：常驻进程携带 install/apply/防火墙/驱动/升级全部能力，违反 mynetd/doc.go:7-8「不做系统能力」；且 cmd/mynetd/main.go:143 的注释「B2：…localapi 不 import shared/config / mynet」与现状不符（wizard.go 仍 import mynet）— 处置建议：把 SyncPluginProxyFromPolicy、DeviceFingerprint、credential 存取下沉为注入接口或独立小包（对齐 ProxyLoad/ProxySave 的注入做法），cmd/mynetd 纳入 guard B2 扫描面。

[违反设计|S2] mynetd/localapi 行为越界：gnb_ctl 解析、OS 路由查询、主机指标/进程探测 — 证据：mynetd/localapi/node_status.go:121-135（gnbPeerLinksOf → driver.GnbPeerLinks，gnb_ctl 对端解析跑在 mynetd 进程内）、:155-200（ifaceStats 读 /sys/class/net 计数器 + 解析 ifconfig = 主机指标采集）、:264（procUptimeSec 用 `ps -o etime=`）、mynetd/localapi/route_query_unix.go:26-64（ip route show / route -n get）、route_query_windows.go:24-58（route print -4 解析）、cmd/mynetd/tunnel_restore.go:72-105（读 gnb.pid + `kill -0` 判进程、决定拉起隧道 = 进程生命周期判据）— 对照 mynetd/doc.go:7-8 明列禁止项 — 影响：状态机判据在通信层自算一套，与执行器 detector/metrics 双源漂移（维度 2）— 处置建议：状态判据统一走 Ops（如 `mynet status --json`）或把 node_status/route_query 移出 mynetd 归执行器。

[违反设计|S2] guard B2 守护面缺口，上述越界全部静默通过 — 证据：.githooks/guard.sh:286-300 —— 只扫 `mynetd/*`（不扫 cmd/mynetd/*），模式 `"[^"]*mynet/(detector|tools|apply|metrics|service|keys)` 不含 `mynet` 根包（wizard.go:13 的 `"…/mynet"`）、不含 `mynet/driver`（node_status.go:13）、不含 cmd/mynetd/nodeface.go:16 的 shared/config；且 `added_in_file` 只看新增行，存量违规永不复扫 — 影响：README.md:12-15 与 mynetd/doc.go 的架构红线实际无守护 — 处置建议：扫描面扩到 cmd/mynetd/**、模式补 `mynet/driver` 与根包 `mynet"`，并对存量做一次性全量复扫（改 9 份 guard 须同步，见 CPI-36 维护说明）。

[违反设计|S2] make dist 的 openwrt 腿给 mynetd 带 embed，与 SSOT/文档/CI 三方矛盾 — 证据：Makefile:131（tags 非空时 `-tags "$$tags embed"`，而 openwrt 腿 tags=openwrt，见 Makefile:90）；对照 mynetd/doc.go:13-15（OpenWrt 无 embed）、README.md:31（none=OpenWrt 瘦构建）、.github/workflows/build_openwrt.yml「Build Go Go binaries」段（`-tags openwrt`，注释「mynetd 瘦，无 embed」）— 影响：make dist 产出的 mynet_agent_openwrt_*.tgz 里的 mynetd 违反「OpenWrt 不带 embed」；且与 CI 的 ipk 形态不一致（两套 OpenWrt 发行路径各说各话）— 处置建议：openwrt 腿去掉 embed，或按 build.yml:8-9 的口径直接砍掉 openwrt tgz 腿（OpenWrt 只出 ipk）。

[错漏|S3] 托盘启停打在 install 从不注册的服务标识上（macOS + Windows）— 证据：cmd/mynet_tray/control.go:39（launchd label `club.mynet.mynetd`）vs mynet/cmd_install_launchd.go:40（LabelMynetd=`com.mynet.mynetd`，:68-72 用它 bootstrap）；cmd/mynet_tray/control.go:44,181-191（Windows 走 `schtasks /run MyNetd`）vs mynet/cmd_install_win.go:174-181（SCM 服务 `mynetd` 为唯一常驻，且卸载时把 schtasks mynetd/mynet-gnb 当旧物删除）— 影响：CLI 安装后托盘「启动/停止」按钮对 launchd 而言目标不存在（no-op/报未注册），对 Windows 而言打的是已被取代的计划任务机制 —— 托盘启停即摆设（cmd_install_win.go:152 自己都注释过「托盘的启停按钮就是摆设」的历史坑）— 处置建议：服务标识常量收敛到 shared（与 install 共用），Windows 托盘改 SCM；本条是 CPI-73/CPI-74 的 agent 侧内证，可并账跟踪。

[错漏|S3] 产物仍带 webui.html，安装文档称已无此物 — 证据：Makefile:148（非 openwrt 平台 `cp mynetd/localapi/webui/index.html $$out/webui.html`）vs packaging/darwin/INSTALL.md:39 与 packaging/linux/INSTALL.md:28（「WebUI 已 embed，无 webui.html」）；mynet/upgrade/install.go:34（webui.html 不参与替换，「由 embed 烘进去」）— 影响：包里多一份 133030 字节的重复静态页（与 embed 内容重复），文档与产物不一致误导排障 — 处置建议：删 Makefile:148（embed 后无消费者；scripts/dev-push-0.32.sh:93 还在拷它，同批清）。

[错漏|S3] deploy/README.md 两处与实现漂移 — 证据：①「mynet_gateway 就在常规产物包里（make dist …3 二进制之一）」vs Makefile dist 循环只编 ./cmd/mynet、./cmd/mynetd（+ darwin/windows 的 mynet_tray），scripts/pack.sh:53 注释明写「客户端包不含 mynet_gateway」——gateway 无任何产物构建路径，照文档装必然缺件；②「NewRedisPresence 目前只被测试用」vs cmd/mynet_gateway/main.go:90 已按 env 装配（降级内存）——文档把活代码记成死代码 — 影响：生产主机部署（H8）会卡在第一步；读者误删 presence_redis.go — 处置建议：文档改为「gateway 需单独 go build ./cmd/mynet_gateway」（或给 Makefile 加 dist-gateway），presence 段更新为已装配+内存降级语义。

[错漏|S3] scripts/security_audit.sh 指向已改名的 mynet_back 仓 → 假绿与假红并存 — 证据：scripts/security_audit.sh:6（`BACK="$CLUB/mynet_back"`，workspace 现名 mynet_api）；A 节 `grep … "$AGENT" "$BACK" … 2>/dev/null | grep -q .` 在目录不存在时被吞、照样 ok「无明文密码」（假绿）；B/D 节对缺失文件 `grep -q` 直接 bad()（恒红）；F 节还要在 $BACK 里跑 guard — 影响：安全复检能力失效，且它不在 make test（Makefile:24-28 只跑 check-route-writers/check-no-back-url），无人发现 — 处置建议：改指 mynet_api 或把跨仓安全复检移出 agent 仓（归统一入口），并让脚本在目标仓缺失时 fail 而不是 ok。

[错漏|S3] 自更新链两个平台洞（CPI-76 现状确认，未修）— 证据：mynet/upgrade/install.go:39（agentBinaryNames = mynet/mynetd/mynet_gateway 裸名）+ :389-397（locateBinaries 逐个 `base == want` 精确比对）→ Windows 包内全是 *.exe 会判「产物里没有 …」；mynet/upgrade/install.go:39 同时列 mynet_gateway，而产物包根本不含它（见上条）；mynet/cmd_update.go:87-96（pickLatestMynetForPlatform 用 runtime.GOOS，OpenWrt 上选 "linux/mipsel" 通用包，不认 openwrt 平台/ipk）— 影响：Windows 自更新不可用；OpenWrt 会被塞通用 Linux 二进制（不带 openwrt 装配后端）— 处置建议：按 GOOS 补 .exe 名单（并去掉包内没有的 mynet_gateway 或把它打进包）；OpenWrt 更新走 opkg 协调（H4）。

[错漏|S3] shared/utils/paths.go HomeSource 判据退化 — 证据：shared/utils/paths.go:63-73，`if filepath.Base(filepath.Dir(exePath)) == "bin" { return "exe" }; return "exe"` 两分支同返回值，注释承诺的 env|exe|cwd 三态里 exe 分支恒等 — 影响：诊断信息误导（小）— 处置建议：删冗余分支或补 cwd 判据。

[冗余|S4] shared/errors 整包零消费 — 证据：shared/errors/errors.go（AppError/New/Newf/Wrapf/WithComponent/IsAppError/ErrorCode + 5 个 Err* 哨兵，121 行）仅被自身 errors_test.go 引用；`grep -r mynet_agent/shared/errors` 全仓除包自身外 0 处（代码里清一色用标准库 errors.New/fmt.Errorf）— 影响：死抽象 + 与实际错误风格两套 — 处置建议：整包删除，或让真实调用点先迁移（后者成本高、收益低）。

[冗余|S4] OS 路由查询/解析双实现 — 证据：mynetd/localapi/route_query_unix.go:26-64（`ip route show` / `route -n get` 解析）与 route_query_windows.go:17-58（`route print -4` 扫列）↔ mynet/apply_osroute_unix.go:20-90（bsdRouteGet 等）、mynet/apply_osroute_windows.go:53-79（routesOnDevice/defaultGateway 同样解析 route print）— 影响：同一能力两处实现、口径漂移风险，且其中一份落在 mynetd（叠加 S2）— 处置建议：查询收敛到执行器/共享探测层，localapi 经 Ops 取。

[垃圾|S4] 死代码 4 处 + 实验工具零引用 — 证据：shared/utils/paths.go:362 dirExists、cmd/mynet_tray/control.go:122 shellJoin、mynetd/localapi/handlers.go:214 systemJSONPath、cmd/mynetd/main.go:401 waitSignal —— 全仓 grep 各自仅剩声明行 1 处（含测试在内 0 调用）；cmd/keyprove/main.go 头注释自述「实验」，全仓（脚本/文档/Makefile）零引用 — 处置建议：删 4 个死函数；keyprove 与 mynet/keys/genvec 判存废（genvec 若保留应在 docs 注明「向量生成器」）。

[垃圾|S4] 死分支与冻结测试 — 证据：mynet/commands.go:137 `case "refresh":` 不可达（:93 `strings.HasPrefix(cmd,"refresh")` 已先 return cmdRefresh）；testdata/proxy-permission-loop/kernel_test.go.txt（测试改名 .txt 冻结，LOOP-3 已知账）— 处置建议：删死分支；.txt 测试给结论或挂独立 gate（不进基线是已裁定，但应有归属说明）。

[包袱|S5] 旧仓名/旧布局残留（注释与脚本）— 证据：scripts/e2e/relay_smoke.sh:2、cmd/mynetd/backclient.go:197、shared/domain/vpn_bundle.go:10、mynet/cmd_auth.go:126,254 注释引用 mynet_back（现 mynet_api）；scripts/security_audit.sh:6 见 S3 条；cmd/mynetd/main.go:132 注释「本机 8096/8097」含旧第二端口说法 — 影响：检索与排障指错方向（`back` 简写合法，问题在仓名与端口叙述）— 处置建议：注释统一现仓名；8097 说法核实后删除。

[文档|S5] README 漂移 3 处 — 证据：README.md:12-15 硬约束清单（detector/tools//infrastructure/coordinator/node//service//gopsutil）与 guard B2 实际清单（detector|tools|apply|metrics|service|keys，guard.sh:290）互不一致；README.md:27 「Makefile `pack` 就是这个」——Makefile 无 pack target（实际 `make dist`，pack 是 scripts/pack.sh）；README.md:31 「none=OpenWrt 瘦构建」与 make dist openwrt 腿带 embed 矛盾（见 S2 条）— 处置建议：README 按 guard 与 Makefile 现状重写那三行。

[文档|S5] docs/install-chain-status-20261002.md 引用已迁移路径 — 证据：该文「packaging/darwin/scripts/postinstall」——packaging/darwin 现仅剩 INSTALL.md（postinstall 已随 H2 迁 mynet_desktop）；文中其余改动表仍写 packaging/darwin 四平台 INSTALL「已改写」属历史快照 — 影响：按文索引找不到文件 — 处置建议：文首标注「历史快照（2026-10-02）」并把待办段与现状对齐，或移 docs/releases/。（docs/MYNET_V1_PLAYBOOK.md 归 B2，本轮未审。）

[文档|S5] mynet/driver/gnb.go:42 注释指向不存在的单元文件 — 证据：「DefaultGNBServiceUnit deploy/systemd/mynet_gnb.service 装出来的单元名」，而 deploy/systemd/ 现只有 mynet_gateway.service；mynet_gnb.service 的唯一源是 mynet/units/mynet_gnb.service（units_systemd.go:13 embed）— 处置建议：改注释指向 mynet/units/。

[核查通过|S5] packaging/darwin 退役残留清点 — 现状：packaging/darwin/ 只剩 INSTALL.md，无打包脚本/postinstall/plist 残留；且它仍被 Makefile:145-150 作为 darwin 产物的 INSTALL.md 消费，**不能整目录删**；packaging/darwin/INSTALL.md:58,163 的 club.mynet.tray 与 mynet/cmd_install_launchd.go:43 LabelTray 一致，无漂移（托盘 label 本身合法，问题见 S3 托盘条）。产物前缀 mynet_agent_*、本机 8096、gateway 8710（cmd/mynet_gateway/main.go:31）均与 SSOT 一致；`.gitignore` 覆盖 secure-backup/、*.private、credential.json；未见硬编码凭据（关键词扫描全仓仅 shared/utils/constants.go:58 的目录名常量）。

[过期|S5] usage 与实现不符的子命令 — 证据：mynet/commands.go:35 宣称「monitor 持续监控输出」，:155 `case "monitor": return cmdStub`（:171-176 打印「尚未实现」）；mynet/dns_system_windows.go:21 `listNetworkServices Windows 未实现` → `mynet dns` 在 Windows 恒报错（M4「DNS 保险丝」平台缺口）— 处置建议：usage 标注平台/实现状态，或按 dev-plan 补实现。

[冗余|S5] fixture 双源 + 跨仓相对路径 → CI 静默跳过（D2 现状）— 证据：mynet/driver/intent_fixtures_test.go:15-24 与 a8_dual_verify_test.go:28-38 只找 `../../.. /mynet_ctl/docs/knowledge-base/spec/fixtures/bundle-envelope-v2`，找不到 t.Skip；shared/domain/bundle_envelope_test.go:13-29 则 testdata 优先 + 找不到 Fatal —— 仓内已有 testdata/bundle-envelope-v2（git 已跟踪）却被 driver 测试无视；.github/workflows/build.yml 的 `go test ./...` 单仓 checkout 时这些用例全 skip（CI 绿不含 A5/A8 fixture 断言）— 处置建议：driver 测试改用 bundle_envelope_test 同款解析顺序（testdata 优先、缺失 Fatal）。

[测试|S5] 环境性 t.Skip 共 18 处 — 证据：`grep -rn t.Skip --include='*_test.go'` 18 行/14 文件；其中 fixture/向量缺失静默型 3 处（上条 + mynet/keys/node_face_vector_test.go:16）、平台/工具缺失条件型 15 处（darwin-only、/sbin/route 缺失、读不到接口等，属可接受但让「全绿」含水）— 本轮 `go test ./...` 全绿（24 包 0 fail，2026-10-13 实测），未见断言错误状态的测试（D4 未命中）— 处置建议：CI 输出补 `-run` 全量 + skip 计数可见化。

## 已知账命中

| 已知账 | 本轮结论 | 证据 |
|---|---|---|
| CPI-71/R1（Lua Ops 不满足合同） | 仍在：MYNET_EXECUTOR=lua 仍整套替换 Ops，Lua 侧只实现部分域 | cmd/mynetd/main.go:135-139、cmd/mynetd/executor_lua.go:27-39,251-291 |
| CPI-72/R2（UCI 兜底不幂等） | 代码仍在，本轮未重跑 fixture 复现 | mynet/cmd_install_openwrt.go（见疑点） |
| CPI-73/R3、CPI-74/R4（安装器与 CLI 机制/label 分叉） | 仍在，且补 agent 侧内证：托盘 vs install 的 label/机制分叉 | 见 S3 托盘条 |
| CPI-75/R5 | desktop 侧，不重复审 | — |
| CPI-76/R6（update 不识别 exe / OpenWrt 选 Linux 路径） | 确认未修 | 见 S3 update 条 |
| CPI-77/R7 | desktop/CI 侧；agent 的 build.yml 触发面已收窄（只 tag+手动+PR），未见 ref 浮动 fallback | .github/workflows/build.yml:14-23 |
| CPI-79/R9（init 按 PPID=1 杀 gnb_es） | **已修**：归属判据改「exe=本部署 gnb_es 且父进程是本实例 gnb」，孤儿显式 skip | mynet/units/openwrt_mynet.init:216-243 |
| CPI-81/R11（installer 全编入三平台） | **已修**：go list 三平台+openwrt 实证按 tag 分层 | cmd_install_win.go(windows)/cmd_install_launchd.go(darwin)/cmd_install_systemd.go(linux&&!openwrt)/cmd_install_openwrt.go(linux&&openwrt)/cmd_install_unsupported.go；对照 workspace-review R11 原描述已不成立 |
| CPI-82/R12（install 先 requireHome） | 有修复迹象（注释与 resolveInstallHome），本轮未 dry-run 复核 | mynet/cmd_install.go:91,143（见疑点） |
| CPI-45（WebUI 非 Vue 构建） | 机制仍在，但证据需更新：webui/index.html 现为 133030 字节手写页（含 IA 壳层），非旧述 5401 字节；mynet_gui SPA 另经 /gui/ 嵌入 | mynetd/localapi/gui.go:1-22、webui/ 三文件 |
| LOOP-3（kernel_test.go.txt 未进基线） | 仍在 | testdata/proxy-permission-loop/kernel_test.go.txt |
| O1（init 单一源码方向） | agent 侧只有一套模板：mynet/units/{mynetd.service, mynet_gnb.service, openwrt_mynetd.init, openwrt_mynet.init}，units_systemd.go/units_openwrt.go 按 tag 各 embed；无 rc.mynet 残留、无 Lua 启停脚本残留在 agent 仓（Lua Ops 执行器属 CPI-71 另账） | mynet/units.go:1-9、units_systemd.go:10-13、units_openwrt.go:14-17 |
| packaging/darwin 已退役（H2） | 残留仅 INSTALL.md 一份且仍被产物消费 | 见「核查通过」条 |

## 疑点待验证

1. CPI-72（cmd_install_openwrt.go:243-274 UCI 事务/幂等）本轮只做静态确认，未重跑隔离 fixture 复现，状态以 R2 为准。
2. CPI-82（`mynet install --home` 解析次序）本轮未跑 dry-run 实证；resolveInstallHome 的存在与注释表明确已改，需一次 dry-run 验收后销账。
3. secure-backup/ 下 10 个密钥材料（含 *.private、back_face_priv）明文在仓**工作树**（git ls-files 确认未入库，.gitignore 已排除、guard A3 拦提交）；建议迁出仓树，但是否清理属用户资产，未动。
4. mynetd/localapi/conf/credential.json（0600、gitignored）为本机会话残留（token 为样值形状），未入库；注意勿误提交。
5. `strings.SplitN(cmd,"-",2)[0]` 前缀分派（mynet/commands.go:98）使 "monitor-x"/"check-x"/"config-foo" 会落进基础命令分支且丢失原词——未找到现存调用方受影响，低优先级。
6. mynet/units/openwrt_mynet.init 与 mynet_openwrt 仓包内 Lua/init 是否存在跨仓双份（O1 的另一半）——跨仓比对归 B1 轨。
7. gateway 的 X-Node-Id 冒充修复（b7aceeb）与 events 合并竞态修复本轮未复核行为，仅确认代码在位。

## 覆盖说明

- 文件面：mynet_agent 全仓 322 个 .go（含测试）/448 个文件全量检索；重点逐读 cmd/mynetd、mynetd/localapi、mynet/{commands,cmd_install*,cmd_update,units*,upgrade}、cmd/mynet_tray、shared/utils、Makefile、guard.sh、packaging/、deploy/、scripts/、docs/、.github/。
- 零调用判定复核面：`go list -deps ./cmd/mynetd`（依赖树）、三平台 + `-tags openwrt` 的 go list 文件清单（build tag 互引）、全仓符号 grep（含 _test）、命令注册面（mynet/commands.go switch、mynetd/commands/commands.go Op 映射表、localapi allowedSubcommand 白名单、HandleFunc 路由表）、Makefile/两个 workflow 构建目标、脚本引用。
- 只读实测：`go test ./...` 全绿（24 包，0 fail/skip 计数未展开，2026-10-13）；`go list` 交叉核对；字符串检索为静态检查已注明。
- 未做：实机安装/升级/隧道验收、Windows/真机 OpenWrt、生产 gateway 部署；仓间契约（B1）与知识库文档、V1_PLAYBOOK（B2）不重复审。
- 统计：S1×1、S2×4、S3×6、S4×4、S5×8（含 1 条核查通过），共 23 条；已知账命中 12 项（其中 CPI-79、CPI-81 两项本轮证实已修）。

<!-- SOURCE-END C1-mynet-agent.md -->

---

<!-- SOURCE-BEGIN C2-mynet-gui.md -->

# C2 轨审计报告 — mynet_gui 仓自身（2026-10-13，只读审计，未改任何仓库文件）

范围：mynet_gui/** 全仓（Vue3 + Quasar 用户前端）。api 字段/路径契约归 B1 复核，知识库文档归 B2。
实测取证：`vue-tsc --noEmit`（194 错）、`vitest run`（4 文件/41 测试通过）、`eslint`（0 报）、
`node dev-tools/check-i18n.js`（通过，zh 2194 / en 2029 / 用到 1006 键）、dist/spa 产物反查、
全 src 双通道引用扫描（文件名 + 导出名，过路由表/动态 import/菜单配置/字符串引用/tests/docs）。

## 发现清单

### S1 安全红线

[错漏|S1] middleware.ts 的 OAuth callback 开放重定向把 access token 拼进任意外域 URL — 证据 src/router/middleware.ts:22-27（`const callback = to.query.callback` → `window.location.href = callback + sep + 'token=' + encodeURIComponent(token)`，无任何域名校验）— 影响：攻击者构造 `/auth/login?callback=https://evil.example/` 即可把已登录用户的 Bearer token 送进任意站点（token 外泄 + 开放重定向）— 处置建议：callback 白名单校验（同源/注册回调域），或改为一次性 code 而非 token 走 URL。 GUARD-OK 误报：对账记录引用的攻击示例代码片段，非凭据

### S2 违反设计

[违反设计|S2] 调试/占位页挂在生产 UI：ApiTestPage 是 "Coming Soon" 空占位却进生产菜单 — 证据 src/layouts/MainLayout.vue:593-600（`navigation.apiTest` → `/app/api-test`）+ src/pages/system/ApiTestPage.vue:1-27（只有一张 comingSoon 卡）+ src/router/routes.ts:285；另 TokenDebugPage 生产路由仍注册（routes.ts:300-305，菜单项仅 dev 显示 MainLayout.vue:606-614）、FormStyleGuidePage 示例页可从生产路由访问（routes.ts:293-299）— 影响：与「配置流程硬台阶+折叠引导」用户裁定的界面收敛相悖，用户面暴露无功能死页与调试面 — 处置建议：生产路由/菜单剔除或加 dev 门；FormStyleGuide 移出生产路由表。

[历史包袱|S2] 部署脚本族两套目标并存，rollback/pre-deploy-check 仍指向旧 dsplat 部署 — 证据 rollback.sh:10-11（`REMOTE_HOST="112.124.22.220"`、`REMOTE_PATH="/alidata/www/dsplat.com/mynet.dsplat.com/front"`）、rollback.sh:106（回滚后提示 `https://mynet.dsplat.com`）、pre-deploy-check.sh:111-122（同旧 IP/旧路径）对照 deployment.sh:14-15 / quick-deploy.sh:10-11（`my.mynet.club:/data/app/mynet.club/my/public`）— 影响：真出事跑 rollback 会 `rm -rf` 并恢复到**旧域名旧目录**（rollback.sh:91-93），现行站点不受影响也不回滚；pre-deploy-check 校验的也不是将要部署的主机 — 处置建议：收敛为单一部署目标来源，rollback/check 脚本与 deployment.sh 同源。

[违反设计|S2] 三层路由守卫并存互相打架 + 用户面假门椒残留 — 证据 src/boot/auth.ts:131-170、src/router/middleware.ts:15-83、src/router/nodeRoutes.ts:165-195 三个独立 `router.beforeEach`（认证语义各写一份）；`document.title` 双写两种格式（nodeRoutes.ts:189-194 `- MyNet` vs middleware.ts:65-70 `| MyNet`，最终值取决于注册顺序）；`hasNodeEditPermission` 是 TODO 恒 true 的假门椒（middleware.ts:56,105-111）— 影响：与裁定 B3「用户面不长门椒（857dfa1 已删 hasPermission/hasRole）」相悖，本处漏删；守卫分叉是 `/login` 一类跳转事故的温床 — 处置建议：收敛为单一守卫（boot/auth 一处），删假门椒与重复 afterEach。

### S3 错漏

[错漏|S3] MainLayout 登出后跳转不存在的 `/login` 路由 — 证据 src/layouts/MainLayout.vue:716,719（`router.push('/login')`）；路由表无 `/login`（routes.ts:4-328 旧路径重定向列表也不含）— 影响：正常登出靠 boot/auth.ts:162-164 守卫兜回 `/auth/login`（碰巧正确）；**登出失败路径**（catch 分支，仍带登录态）直接落 404 页（catchAll）— 处置建议：改 `/auth/login`。

[错漏|S3] 推荐功能删了壳没删，残留 3 条 TS2307 断链并被棘轮固化 — 证据 src/composables/useRecommendations.ts:6-7 与 src/components/dashboard/RecommendationsCard.vue:95 引用 `../services/recommendations/{UserStateAnalyzer,RecommendationEngine}`，目录 `src/services/recommendations/` 不存在；vue-tsc 实测 3 条 `TS2307`（在 194 基线内躺平，见 /tmp/audit-2026-10-13/gui-tsc.log）— 影响：基线被「死代码的编译错」占额，掩盖真错误；唯一调用者 RecommendationsCard 本身零引用（见死组件清单）— 处置建议：连同 RecommendationsCard、types/recommendation-api.ts 一并删除，基线随 `check:types --update` 下调。

[错漏|S3] 死模块内另有断链/类型漂移 3 处（均在 194 基线内）— 证据 src/services/apiVersionService.ts:7 `from '../api'` 模块不存在（应为 `./api`，TS2307）；src/composables/useConfigBundle.ts:15 引入 types/nodeConfig 不存在的导出 `VersionCompatibilityResult`（TS2305）；src/pages/system/TokenDebugPage.vue:122 引入 services/websocket 不存在的导出 `TimeUpdateMessage`（TS2305）— 影响：死代码断链靠摇树免炸（见疑点 1），一旦被引用即构建失败/运行时错 — 处置建议：死码删除优先，非死码修引用。

[错漏|S3] apiService.getDashboardData 永远返回 mock 假数据 — 证据 src/services/api.ts:468-471（`async getDashboardData() { return mockApiResponse(mockDashboardData) }`，注释「Dashboard 暂时使用模拟数据」）+ src/services/mockData.ts:175（mockDashboardData）— 影响：实测零调用（useDashboard 不走它，DashboardPage 经 useDashboard/useRoutes 取真数据）＝死假数据栈；若后人接上即向用户展示假仪表盘 — 处置建议：删 getDashboardData + mockData.ts + api.ts:165 mockApiResponse + 未用 import mockDelay（api.ts:5）。

[冗余垃圾|S3] CPI-70 现状核实**未修**：`GET /nodes/{id}/status` 每个未配置节点打一次，响应只写不读 — 证据 src/pages/node/NodeListPage.vue:126-152（`nodeStatusInfo.value[nodeId] = response.data` 仅在 :135 当去重标记用，模板与他处零读取）+ src/services/node/nodeService.ts:287-289 — 影响：无谓请求 + 状态从未呈现，正是「默认值掩盖/请求白打」类 — 处置建议：要么读出来渲染，要么删请求（产品裁定）。

### S4 冗余/垃圾

[冗余垃圾|S4] 12 个零引用组件共 3361 行（流程简化 3a1b53d 后的孤儿） — 证据（全 src 含 tests/docs/动态 import 字符串 grep 零命中）：src/components/service/ServiceCardContent.vue(575)、route/NodeRoutesDisplay.vue(368)、node/QuickCreateDialog.vue(300)、auth/AccountActivationDialog.vue(296)、route/NodeSelector.vue(273)、common/CardLayoutExample.vue(233)、dashboard/RecommendationsCard.vue(435)、auth/PasswordResetDialog.vue(482)、dashboard/RealtimeMetrics.vue(96)、social/SocialCard.vue(129)、common/LanguageSwitcher.vue(85)、auth/ResetPasswordForm.vue(89) — 处置建议：整批删除（参照 7717e65/f2ee1bb 的「页面与死文案一起删」先例）。

[冗余垃圾|S4] 「配置包 v2.2.0」旧实现整簇死亡（被 bundleEnvelopeService 取代） — 证据 src/composables/useConfigBundle.ts(186 行，唯一引用是 composables/index.ts:15 barrel) → src/services/apiVersionService.ts（唯一消费者是 useConfigBundle）→ src/services/node/configBundleService.ts（消费者仅 useConfigBundle + 死 barrel）；对照现行 src/services/node/bundleEnvelopeService.ts（useNodeKeys/useNodeConfig/NodeConfigPage 在用）— 处置建议：三件套 + 相关 types 一并删。

[冗余垃圾|S4] 缓存三件套死一半：useCache.ts(198 行) 三个导出零调用 — 证据 src/composables/useCache.ts:8,76,150（useCacheStatus/useApiCache/useDataPrefetch），全仓仅 composables/index.ts:23 转出，无消费者；活链是 zoneServiceApi → utils/apiCache.ts → utils/cacheManager.ts（zoneServiceApi.ts:2 在用）— 处置建议：删 useCache.ts 及 barrel 转出行。

[垃圾|S4] 空文件/调试数据残留 — 证据 src/utils/tokenRefreshService.ts（0 字节空文件）；src/services/testData.ts（74 行「测试IP池数据」，零引用）；src/utils/tokenRefreshTest.ts（token 刷新自测脚手架，仅 TokenDebugPage 用，随路由进生产包）— 处置建议：删前两个；tokenRefreshTest 随 TokenDebugPage 去留裁定。

[垃圾|S4] router/middleware.ts 后半 ~230 行零调用 — 证据 src/router/middleware.ts:113-341（RouteWatcher/BreadcrumbGenerator/PageProgressBar/RouteStateCache/createRouteUtils），`routeUtils` 仅 router/index.ts:21 导出后无人 import（全仓 grep 零）— 处置建议：删。

[历史包袱|S4] Quasar 生成物入仓两份，且曾手改生成文件 — 证据 git ls-files 含 `.quasar/**`(13 文件) 与 `front/.quasar/**`(13 文件)，front/ 目录下**只有** .quasar（无源码，最后被无关提交 9a7a1b6 带入）；a3fb888「prod-spa/client-entry.js 的 publicPath 跟上 /gui/」是手改生成物，而现行 quasar.config.ts:140 已有 `publicPath: process.env.VITE_ROUTER_BASE || '/'`，补丁已过时；另 tsconfig.json:3 `extends ./.quasar/tsconfig.json` 依赖可被 `postinstall quasar prepare` 重写的生成物 — 处置建议：两份 .quasar 出库进 .gitignore，publicPath 走配置（已具备）。

[垃圾|S4] 三个测试 HTML 随构建发进生产 — 证据 public/test-boot.html、public/time-test.html、public/network-config-test.html；dist/spa 实测三者均在产物根（构建 2026-10-12 16:03）— 处置建议：移出 public/。

[冗余垃圾|S4] 格式化函数多份重复实现 — 证据 formatBytes ×6：src/utils/dateFormat.ts:141、src/utils/nodeSummaryDisplay.ts:187、src/composables/useNode.ts:38、src/components/route/RouteDetailsDialog.vue:235、src/components/route/TrafficStatsCard.vue:125、src/components/route/ConnectionHistoryCard.vue:455；另 formatDateTime/formatDate/formatTime/formatSpeed 在 dateFormat.ts 与多个组件内各写一份（NotificationDialog.vue:304、RecentActivities.vue:105、NodeRoutesDisplay.vue:329 等），且 package.json 有 date-fns 又有 quasar `date.formatDate`（MainLayout.vue:725）— 处置建议：收敛到 utils/dateFormat.ts 一份。

[冗余垃圾|S4] 四个死 barrel：src/services/{node,route,social,zone}/index.ts 零导入（各文件 `export * from` 转出；notification/index.ts 有真实消费者不在此列）— 处置建议：删或统一走单入口。

[冗余垃圾|S4] i18n 死键规模超一千 — 证据 check-i18n 实测：zh 2194 键 / en 2029 键 / 全仓仅用 1006 键；工具只查「用到的键缺不缺」，查不出死键；中英差 165 键亦无人拦 — 处置建议：死键清理脚本 + 中英对齐检查进 lint。

[垃圾|S4] console 直用 84 处/11 文件（生产靠 terser drop_console 兜底） — 证据 grep `console.` 84 hits：src/App.vue、stores/onboarding.ts、utils/{dateFormat,debug,tipsStorage}.ts、components/node/NodeCardContent.vue、service/ServiceCardContent.vue、pages/route/ConnectionDetailsPage.vue、pages/services/{Public,Friend,My}ServicesPage.vue 等；仓内已有 debug 封装规范（.github/copilot-instructions.md「Use debug, NOT console」）与 CONSOLE_LOG_REMOVAL_REPORT.md（docs/_archive/reports/）— 处置建议：换 debug 封装，历史报告别再写第二份。

[冗余垃圾|S4] 未用 import 两处 — 证据 src/services/api.ts:5 `mockDelay`（仅 import 未用）；src/composables/useDashboard.ts:5 `apiService`（import 后无调用）— 处置建议：随死码清理顺手删。

### S5 包袱/过期文档

[过期文档|S5] 两份 openapi 指向虚构/错误域名且主次不明 — 证据 docs/openapi.yaml:26-36 与 docs/openapi-v1-complete.yaml:26-36：`https://api.mynet.com/api/v1`、`staging.mynet.com`、`github.com/mynet-project`，与现行「my.mynet.club 同源 /api/v1」（.env.prod、api.ts:13-17）不符；6074 行 vs 1591 行两份并存无权威声明 — 影响：契约文档误导（内容归 B1 复核）— 处置建议：改 servers 为同源相对、标注权威版本，另一份归档。

[过期文档|S5] docs/01-06 主文档群停在 Vue CLI/localhost:8000 时代 — 证据 docs/03-开发指南.md:32-33（`VUE_APP_API_URL`，Vue CLI 前缀，现行是 VITE_*）、docs/05-API文档.md:10-11,792（`localhost:8000/api/v1`、`ws://localhost:8000/ws`，现行 WS 走 my.mynet.club `/wss`）、docs/UPGRADE-v2.md:90（`api.mynet.local/api/v2`——V2 未上线见 CPI-41，api.ts 用 /api/v1）、docs/REAL_BACKEND_VERIFICATION.md、docs/TOKEN_REFRESH.md:174 同类 — 处置建议：按现状重写或归档进 docs/_archive。

[过期文档|S5] docs/quick-create-node-frontend.md 指向已零引用的 QuickCreateDialog.vue — 证据文档全文以该组件为对象，组件现已零引用（见死组件清单，NodeCreatePage 3a1b53d 重写后取代）— 处置建议：随组件删除一并归档。

[历史包袱|S5] 根 nginx.conf 整份是旧 dsplat 模板，与 deploy/production-nginx.conf 分叉 — 证据 nginx.conf:13-48,129-130（server_name mynet.dsplat.com、证书 /etc/letsencrypt/live/dsplat.com、root /alidata/www/dsplat.com/...）+ :105-117 WS 只认 `/app/`、`/ws/` 旧路径（现行客户端默认 `/wss`，src/services/websocket.ts:82-89）+ :88-89 `proxy_pass https://127.0.0.1:443/` 丢 `/api/` 前缀（对照 deploy/production-nginx.conf:83 保前缀、:102 `/wss/`）— 处置建议：删根 nginx.conf 或明确标注废弃，只留 deploy/production-nginx.conf + nginx-config-example.conf 一份模板。

[过期文档|S5] dev-tools/check-types.js 注释与基线漂移 — 证据 dev-tools/check-types.js:9（「211 条存量，33 个文件」）vs dev-tools/typecheck-baseline.json `total: 194` / 38 文件（本轮 vue-tsc 实测 194 一致）— 处置建议：注释改为不复述数字（Makefile 自己也写了「别复述数字」）。

[违反设计|S5] README 口径与用户裁定有出入 — 证据 README.md:23（「多区域隔离: Zone-based数据隔离和权限管理」）：「区域/Zone」与「网络区/Network」口径不一，「权限管理」表述与裁定 B3（用户面不表达 tenant 权限）相悖 — 处置建议：按 terms.ts 口径改写。

## 已知账命中

- **CPI-7（Reverb 开发/生产同密钥入仓）**：现状依旧——.env:16-17、.env.dev:27-28、.env.prod:29-31 同值。**新增证据**：src/services/websocket.ts:96 把 `oyzmmhlqzmar2uvanq2k` 硬编码为 fallback（源码里再藏一份）。
- **CPI-30/30a（Quasar env 命名法）**：已修属实——仓内只有 .env/.env.dev/.env.prod/.env.embed/.env.example（无 .env.development/.env.production 死文件）；.env.local.dev 未跟踪且 .gitignore 已补 `.env.local.*` 覆盖。
- **CPI-34（onboarding 后端 500 → localStorage 兜底）**：现状依旧——src/stores/onboarding.ts:470-477 fetchFromBackend catch 吞错返回 null 回落本地。
- **CPI-70（/nodes/{id}/status 响应未读）**：现状依旧，见 S3 第 5 条（NodeListPage.vue:126-152）。
- **CPI-77（desktop CI 缺 GUI embed 输入）**：GUI 侧佐证——本仓 Makefile 只有 lint/test/guard，无 `dist` 目标，agent Makefile 要求的兄弟 `GUI dist/spa` 在本仓没有对应构建契约入口（构建入口是 `npm run build`）；发行链归属归 B1/发行链复核。
- **类型棘轮基线 194/38**：实测 vue-tsc 194 条、38 文件（含 quasar.config.ts 1 条），与 typecheck-baseline.json 持平；**vitest 4 文件/41 测试全过**，与已知基线一致。
- **CPI-25、/route/requests 无人调用（GUI 走 /route/data）** 等 api 侧条目：按分工归 B1，本轮未重复详述。

## 疑点待验证

1. **死代码断链为何没炸生产构建**：apiVersionService.ts:7 的 `'../api'` 断链 + useRecommendations 断链在依赖图里（composables barrel → useConfigBundle → apiVersionService），但 dist/spa（2026-10-12 构建）成功且死代码未烘入（assets 中 grep `mockDashboardData|testIPPoolsData|supportsConfigBundle` 零命中）——推断摇树先于模块解析剔除；受只读限制未跑完整 `quasar build` 复证。
2. **.env.embed 的 `VITE_API_BASE_URL=/gui`**（.env.embed:6）经 api.ts:13-17 拼成 `/gui/api/v1`，与 mynetd localapi 的实际挂载前缀是否一致需归 B1 对 agent 契约复核。
3. **ApiTestPage 进生产菜单是否有意保留的诊断入口**：未查到用户裁定记录；若有裁定则 S2 第 1 条降级为「占位页需补功能或补文案」。
4. **WebUI 配置流程闸门（登录→选节点→密钥→加载配置→启动）实体在 mynet_agent 的 localapi WebUI，不在本仓**；本仓对应物是 onboarding（stores/onboarding.ts + useOnboardingActions + TipsRegistry）。本轮查得的 12 个孤儿组件多为 3a1b53d「简化设备与连接流程」后遗留，但未逐一 git 二分考证各自死于哪次流程改动。
5. **i18n 165 个中英键差**（zh 2194 vs en 2029）未逐键定性是死键还是漏译（check-i18n 判「用到的键」均在两语齐全，故倾向全是死键，未逐键核）。

## 覆盖说明

- **引用面复核（判零引用的口径）**：路由表 src/router/{routes,nodeRoutes}.ts 全量、MainLayout 菜单配置（MainLayout.vue:482-615）、动态 `import()` 字符串、composables/services/types 三个 barrel、i18n 键、docs/*.md、vitest 套件、dev-tools 脚本，均以「文件名含扩展名（.vue）+ 导出名裸词（.ts）」双通道 grep 复核；空结果前先以正向用例验证过 grep 可用。
- **实测命令**：vue-tsc（194 错逐文件归因已存 /tmp/audit-2026-10-13/gui-tsc.log）、vitest run（41/41）、eslint（0 报）、check-i18n.js、dist/spa 产物反查、git ls-files/log、stat 构建时间。
- **未覆盖**：浏览器实机流程、`quasar build` 全量复跑（避免写仓内 dist）、openapi 与 api 路由逐条契约（归 B1）、知识库文档（归 B2）、`.github/copilot-instructions-详细版.md` 未逐段核。
- 本轮未修改任何仓库文件；所有结论均静态 + 只读实测，未做生产验收。

<!-- SOURCE-END C2-mynet-gui.md -->

---

<!-- SOURCE-BEGIN C3-ctl-openwrt-desktop-web.md -->

# C3 轨审计：mynet_ctl / mynet_openwrt / mynet_desktop / mynet_web（2026-10-13，只读）

> 范围：四仓的代码与脚本（ctl 的 docs/knowledge-base/** 归 B2，本报告只审 ctl 非知识库部分）。
> 判定口径：gnb 仓 route.conf 本体不可动（仅「安装器 route.conf 注入」路线可列包袱）；`back`/`mynet_back` 为合法命名。
> 七类：1 错漏 · 2 冗余 · 3 历史包袱 · 4 垃圾 · 5 不必要 fallback · 6 违反设计 · 7 过期/无人调用

## 发现清单

### mynet_ctl

[ctl|错漏|S3] manifest 下载 URL 丢失扩展名，非平台默认 ext 的产物 URL 恒 404 — 证据 `mynet_ctl/app/Services/Manifest/ManifestService.php:271`（`generateFilename($internalProduct, $release->platform, $release->arch, $release->version)` 不传 ext → 落平台默认 tgz/zip；对照 `app/Domain/Release/Services/ReleaseDomainService.php:197-203`）；同病 `app/Http/Controllers/Api/V1/ProxyController.php:45` — 影响：ipk/exe/dmg/pkg 登记成功后 manifest `url` 指向 `.tgz/.zip` 假文件名（`file` 字段是真名），updater/下载面取 url 即 404；desktop 首发已因路由层同类漏项实测 404（`routes/web.php:56-58` 注释自证）— 建议：url 直接用 `$release->filename` 拼，或给 generateFilename 补 ext；补 exe/ipk 用例到 ManifestServiceTest。

[ctl|错漏|S3] /d/{product}/{filename} 下载按四元组 first() 匹配，忽略 filename/ext/variant — 证据 `app/Services/Release/ReleaseService.php:128-141`（`downloadByFilename` 只 where product/platform/arch/version）+ `app/Http/Controllers/Api/V1/ManifestController.php:100-115`（`response()->download($absolutePath, $filename)`）— 影响：同版本多容器（如 windows 同时有 zip 归档与 exe 安装器、openwrt 有 ipk+tgz）时会把 A 文件套 B 文件名发给用户，下载计数/日志也记错行 — 建议：按 `filename` 精确匹配（parseFilename 已给出 ext/kind）。

[ctl|不必要 fallback|S4] buildDownloadUrl 的无前缀 fallback 生成永不可达 URL，且上传不校验扩展名 — 证据 `app/Services/Release/ReleaseService.php:214-215`（解析失败时返回 `/d/{filename}`，而路由是 `/d/{product}/{filename}` 两段，`routes/web.php:59-60`）；`validateUpload`（:153-170）不查 ext，`artifactKindForExt` 可返回 `''`（`ReleaseDomainService.php:147-158`）— 影响：任意扩展名可登记成功、下载 404，fallback 把「登记错」洗成「正常 URL」— 建议：上传时拒绝 `kind==''` 的扩展名；删该 fallback（解析失败应 500 而非发死链）。

[ctl|错漏|S3] apps.json 的 latest 用 SQL MAX(version) 字符串比较 — 证据 `app/Services/Manifest/ManifestService.php:76-80`（`selectRaw('product, MAX(version)')`）— 影响：v10 永远小于 v9、v1.6.0.a vs v1.6.0 序错，索引层 latest 可指错 — 建议：取回后用 `compareVersions`（`ReleaseDomainService.php:279`）在 PHP 排序。

[ctl|错漏|S3] 随仓 install.sh / upgrade.sh 打的是 /api/v1/* 旧 URL 形态，与仓内路由全不对 — 证据 `scripts/install.sh:22,102,117,137`、`scripts/upgrade.sh:18,54,94,109`（`/api/v1/{mynet/manifest,detect/openwrt,releases/latest}`、注释里 `.../api/v1/scripts/.../raw/install.sh`）vs 现行路由：API 全在 `routes/api.php:23`（prefix v2）、manifest 在 `routes/web.php:29`（/d/{product}/manifest.json）— 影响：仓库 HEAD 一旦部署，curl|sh 一键安装/升级链全 404；反之在 2026-03 生产代码上「能跑」，正是 7 个月漂移的双面性（reference-ctl-production-deploy）— 建议：脚本改打 /d/ + /api/v2，并与「整仓发布」口径同批处置。

[ctl|过期|S4] verify-endpoints.php 校验的全是已下线 URL — 证据 `scripts/verify-endpoints.php:8-25`（`http://127.0.0.1:8002/api/v1/apps`、`/api/v1/mynet/manifest`、`/api/v1/releases?product=tui`，端点早已迁 /d/ 与 /api/v2，`ManifestController.php:23` 注释自证）— 影响：验旧面工具，跑即全红或被弃用 — 建议：更新端点表或删除该一次性工具。

[ctl|过期/无人调用|S4] ReleaseDomainService::buildDistUrl 零调用且 $variant 参数未用 — 证据 `app/Domain/Release/Services/ReleaseDomainService.php:267-274`；全仓（含 tests）grep 仅 `app/Services/Script/ScriptService.php:173` 的同名异物（不同类）— 影响：死 API 留着会误导「URL 生成有第二个真源」— 建议：删或并入 buildDownloadUrl 并补调用。

[ctl|历史包袱|S4] 旧线产品面仍在对外清单里：tui「旧版」+ client「计划中」+ @mynet/* 插件面，且 tui 的 successor 指向已冻结的 client — 证据 `app/Domain/Release/Services/ReleaseDomainService.php:21,24`（desc 自注「旧版」「计划中」）、`app/Services/Manifest/ManifestService.php:34-60,106-116,148-215`（CLIENT_PLUGINS、`successor='client'`）— 影响：apps.json 对外持续宣传冻结线与其「后继」，误导集成方 — 建议：产品面收敛随 zone-removal/产品裁定执行，至少改 successor 指向真实后继。

[ctl|错漏|S3] 公开下载页硬编码 ['tui','gnb','utc']，mynet_agent/lua/proxy 产物不上下载面 — 证据 `app/Http/Controllers/PageController.php:23-24`（home）与 `:34-35`（downloads allowedProducts）vs admin 已改 SSOT 派生（`app/Http/Controllers/Admin/ReleaseAdminController.php:40-51`，`tests/Unit/ReleaseFilenameTest.php:193-204` 也只护住了发布面）— 影响：发行面「上传得了、页面看不到」，正是本仓既有测试判据点名过的旧硬编码残留类 — 建议：页面白名单同样取 `ReleaseDomainService::PRODUCTS`（或显式产品线矩阵）。

[ctl|错漏|S3] 测试建表与迁移分叉：CreatesCtlTables 的 releases 无 kind 列，kind 落库路径零测试 — 证据 `tests/CreatesCtlTables.php:15-35`（CREATE TABLE releases 无 kind）vs `database/migrations/2026_10_10_000001_add_kind_to_releases_table.php:17`；全 tests 无一处调 `ReleaseService::upload`（grep 'upload(' 零命中）— 影响：`ReleaseService.php:44,66` 的 kind 落库与 `ManifestService.php:280` 的读回从未在真 schema 上验过；同 CPI-47「测试态/生产态 schema 静默分叉」家族 — 建议：测试建表跟随迁移（或改用迁移建表），补 upload→manifest kind 往返用例。

[ctl|违反设计|S3] deploy.sh 是「rsync --delete 部署即覆盖」，与生产 7 个月漂移、非 git 的事实（reference-ctl-production-deploy）冲突，且无漂移检测 — 证据 `scripts/deploy/deploy.sh:162-167`（`rsync -avz --delete`）、备份仅留 5 份 tar（:127-141）、无任何「生产领先于仓」检查；对照口径「整仓部署需单独发布」— 影响：下一次全量部署会无提示覆盖生产侧 2026-03 以来的手改，并连带删掉生产独有的垃圾文件（那颗 shell 码名文件按 --delete 规则会被清，属副作用而非修复）— 建议：发布前置 diff/清单核对步骤 + 脚本头注明「整仓覆盖需单独发布审批」。

[ctl|错漏|S3] db-setup.sh 远程导入段口令上命令行（ps 可见）— 证据 `scripts/deploy/db-setup.sh:256-266`（`mysql -u "$DB_USER" -p"$DB_PASS"` ×2，口令取自远端 .env）— 影响：CPI-43 只修了本机侧（:37-46、:150-161 用 --defaults-extra-file），远端同病遗留，生产主机任意用户可读 — 建议：远端同样走 option-file。

[ctl|垃圾|S4] docs/ 三件错位/垃圾内容 — 证据 `docs/xuqiu.md:1`（「AI+IP 创作助手小程序」微信小程序需求，与 MyNet 无关）；`docs/mynet_api-hotfix-tasks.md:3-5`（2026-04 面向旧路径 `/Users/arthur/Devel/WorkSpacePHP/lyt.com/mynet_back/` 的 mynet_api 任务单，放错仓）；`docs/todo.md:1-15`（快配脑暴稿，QuickConfigService 已实现）— 影响：文档门面噪音、误导检索 — 建议：xuqiu 删、hotfix-tasks 移交 mynet_api 或归档、todo 归档。

[ctl|历史包袱|S4] 一次性导入脚本与已退役下载域默认值 — 证据 `scripts/import_gnb_v1.6.0.a.sh`（版本锁死；`docs/knowledge-base/deployment-registry.md:85` 只引 v1.6.4）；`app/Console/Commands/ImportGnbFromDownload.php:36,76`（默认 `https://download.mynet.club/gnb`，该域已收敛到 ctl.mynet.club/d）— 影响：旧脚本/旧默认值被顺手跑会打错源 — 建议：v1.6.0.a 归档前先问（repo-migration.md:283 有「别顺手改 GH_BASE」的叮嘱），默认 base-url 改现域。

[ctl|过期|S5] README 是 Laravel 脚手架原文 — 证据 `README.md:1-60`（About Laravel/Sponsors 全文，无一句 ctl 职责；正本在 `docs/knowledge-base/projects/mynet_ctl.md`）— 建议：换成分发中心一页纸 + 指针。

### mynet_openwrt

[openwrt|历史包袱|S2] 已废弃的「route.conf 路由注入」路线仍在生产链上（违反 O1-③）— 证据 `luasrc/model/mynet/node.lua:204-215`（写 route 后调 `proxy_m.route_inject()` 注入策略路由进 GNB route.conf）、`luasrc/model/mynet/proxy.lua:280-334,374-437`（INJECT_MARKER/route_inject/route_restore/route_inject_status/strip_route_injections）、`node.lua:1128-1132`（生成 OS 路由前反向剥离注入段）；对照 `Makefile:109`「路由唯一路径 = 桥 → Go apply --auto（O1-③）」与 `scripts/runtime/README.md:40-42` — 影响：Lua 与 Go 双写路由面（CHANGELOG 自述曾抢 0xc8/200 对象），marker 段把 /8 /16 大段塞进 route.conf 的历史事故（`docs/FLOWS.md:176`、CHANGELOG.md:56「714 条/peer」）随时可复发 — 建议：按 O1 裁定退役注入/剥离对与 proxy.htm 的 Route Inject 徽标（`luasrc/view/mynet/proxy.htm:215,483`）；不涉 gnb 仓 route.conf 本体。

[openwrt|错漏|S3] Lua 面 API 目标仍是 /api/v2（back 只有 v1）— 证据 `root/etc/mynet/conf/config.json:4`（`api_base_url: https://api.mynet.club/api/v2`）、`luasrc/model/mynet/api.lua:152-159,215-220`（versioned_base 升 v2、config-bundle/heartbeat 走 v2）、`luasrc/model/mynet/system.lua:700,805,818`（POST /api/v2/monitor/heartbeat）；mynet_api 全仓无 v2 路由（grep 实证）— 影响：在线拉配置/心跳/命令回执链路整体 404（命令回执另打 `http://127.0.0.1:8096/api/v1/command-result`，`system.lua:475`，两套口径并存）— 建议：随 CPI-69 契约翻转同批把 Lua 面改 v1/tenants（属 O1 迁移面）。

[openwrt|冗余|S4] heartbeat.sh 装进包却无人调度，注释口径与 postinst 相反 — 证据 `scripts/shell/heartbeat.sh:8-9`（自称「cron（postinst 自动写入）: */5…」）vs `Makefile:104-106`（装 /usr/bin/mynet-heartbeat）与 `Makefile:226-228、158-161`（postinst/prerm 只 `sed -i '/mynet-heartbeat/d'`，从不写入）— 影响：死件随包分发；真被手工加 cron 还会与 mynetd 双心跳并打到 /api/v2 死端点 — 建议：确认无人手工使用后连安装行一起退役，或改注释说明「仅手动排障用」。

[openwrt|垃圾|S4] 跟踪在库的备份文件 `luasrc/view/mynet/guest.htm.bak` — 证据 `git ls-files`（已跟踪；文件名扫描四仓唯一 .bak）；`Makefile:50` 的 `*.htm` glob 不会装它 — 影响：库垃圾、易被误改 — 建议：删除。

[openwrt|无人调用|S4] 13 个 LuCI api_* 端点零调用 — 证据 `luasrc/controller/mynet.lua` entry 表：`auto_repair、gnb_start、gnb_stop、gnb_restart、mynetd_start、mynetd_stop、mynetd_restart、svc_start、svc_stop、svc_restart、service_detail、validate_config、guest_nodes`；复核面：`luasrc/view/`、`htdocs/`、`scripts/`、`mynet_agent/`（全 grep 无引用，仅 `mynet.js:644` 的 `svc_state` 是数据键非端点）— 影响：旧启停/自愈面残留路由，与「桥 = 页面唯一写入口」（`luasrc/model/mynet/bridge.lua:3-5`）并存，绕过 ACL/桥审计面 — 建议：逐个裁定删除或接线（guest_nodes 与 guest 家族一并核；删前见疑点 2）。

[openwrt|过期|S5] `.github/copilot-instructions.md` 仍写 rc.mynet / route.mynet 为现行布局 — 证据 `.github/copilot-instructions.md:169,254-255,267`（4 处）vs `Makefile:64,208`（init 不随包、route.mynet 已退役）— 影响：按文档实现会重建已退役件 — 建议：改写或加横幅指向 O1 现状。

[openwrt|过期|S5] docs/ 陈旧件一批 — 证据 `docs/SESSION_NOTES_20260930.md`、`docs/UPGRADE_PLAN_192_168_0_2.md`、`docs/UPGRADE_PLAN_192_168_8_1.md`、`docs/TEST_REPORT_E2E_20260419.md`、`docs/TEST_REPORT_PROXY_20260503.md`、`docs/TODO_key_import_verification.md`、`docs/MACOS_GATEWAY_PLAN.md`/`MACOS_QEMU_SPLIT_ROUTING.md`（CHANGELOG.md:50 自己判定「与 OpenWrt 插件无关」）；`README.md:34` 自述「包名待改…注释仍可能写着 mynet_lua」而实际包名已是 luci-app-mynet（`Makefile:3`）— 建议：归档到 docs/archive 并给 README 一句话现状。

### mynet_desktop

[desktop|过期|S5] INSTALL.md/README 残留「Windows 计划任务 MyNetd」旧机制口径，与自身 SCM 裁定矛盾 — 证据 `docs/INSTALL.md:204`（「完成后计划任务 MyNetd 已注册并启动」）、`:339`（托盘表「走 launchd（mac）或计划任务 MyNetd（win）」）vs 同文 `:217-230`（「计划任务 ≠ SCM 服务」「service.ps1 已废弃」）；`README.md:9`（「Windows 计划任务」）；NSIS 实际正确（`nsis/mynet-agent.nsi:10-16,145`：sc create mynetd，旧 schtasks 由 `mynet install` 清）— 影响：按 :204/:339 排障会去找不存在的计划任务 — 建议：两处改「SCM 服务 mynetd」。

[desktop|过期|S5] README 标题仍是旧仓名 mynet_desktop-V2 — 证据 `README.md:1` vs workflow 已按 2026-10-10 裁定写「不带 -V2」（`.github/workflows/release.yml:48`）— 建议：标题同步。

### mynet_web

[web|垃圾|S4] 社区域 4 页近空壳占位却在路由里 — 证据 `src/pages/social/Routes.vue`（8 行）、`Services.vue`（8 行）、`Friends.vue`（10 行）、`Nodes.vue`（20 行）+ `src/router/index.ts:9-12` 全挂载；check.sh 只验文件存在不验内容（`scripts/check.sh` lint_router）— 影响：用户可导航到空页；与官网定位双不符（既非官网内容也非可用 WebUI）— 建议：随 web 定位裁定（B2/产品）删除或补齐。

[web|过期|S5] README 旧仓名 + 旧定位描述；Settings 占位符仍写 /api/v2 — 证据 `README.md:1`（「# mynet_web-V2」）、`:3-12`（按「本机域/社区域 WebUI」描述，与官网定位漂移，workspace-review-20261010 §3 已记）；`src/pages/local/Settings.vue:13`（placeholder `https://127.0.0.1:8096/api/v2`）— 建议：定位段重写、占位符改 v1。

[web|错漏|S5] check.sh 的已知缺陷编号写错（CPI-40 ≠ 实际 CPI-45）— 证据 `scripts/check.sh`（打印「已知缺陷（CPI-40）：build 脚本不调打包器…」）vs 台账 `cross-project-issues.md:158`（web 无真构建管线是 **CPI-45**；CPI-40 是 mynet_api 迁移撞表）— 影响：按编号查账会指错条目 — 建议：改编号为 CPI-45。

## 已知账命中

- **CPI-69**（ctl 向导打 /api/v2，back 只有 v1）：仍开放；实测范围比登记更广——`MynetBackClient.php:35-154` 除 `getBundleEnvelope`(:166，已用 v1) 外 10 个方法全 /api/v2（含 login、configuration、route/node、keys、router-keys、config-bundle），原登记仅 3 处。且其「等 zone→tenant 契约翻转」前置已满足（mynet_api `Routes/api.php:251,276,310` 只留 /user/tenants），可排期处置。openwrt 侧同族见新账 [openwrt|S3]。
- **CPI-78 / R8**（parseFilename 拒 exe/dmg/pkg、variant/ext 混用）：已修——`ReleaseDomainService.php:228`（白名单含 exe/dmg/pkg）、`:55-58`（ARTIFACT_KINDS）、`2026_10_10_000001_add_kind_to_releases_table.php`、`ReleaseService.php:32-41`（ext 以上传件为准）；路由层也补了白名单（`routes/web.php:59-60`）。残留旧硬编码=发现 9；衍生缺口=发现 1/2。
- **CPI-73/74/75**（桌面双服务机制/label 分叉）：已按 H2 薄壳收敛——`nsis/mynet-agent.nsi:10-16,145-158`（SCM 委托 mynet install）、`macos/scripts/postinstall`（全文：清 legacy `club.mynet.mynetd`、委托 `mynet install` 写 `com.mynet.mynetd`）；残留仅文档（发现 23）。
- **CPI-77**（desktop CI 缺 GUI embed 输入、ref 浮动 fallback）：已修——`.github/workflows/release.yml:117-142`（checkout mynet_gui + `.env.embed` 构建 + 校验 `/gui/assets/`）、`:103-108`（agent ref 取不到即失败）。
- **CPI-45**（web 无真构建管线）：仍开放，`scripts/check.sh` 每次打印为已知缺陷（编号错写 CPI-40，见发现）。
- **O1**（openwrt init 双份 / Proxy 旧 Lua 路径）：init 已收敛（`Makefile:64,208`「init 不再随包，由 mynet install 从内嵌模板写出」，postinst 委托 `mynet install`）；Proxy 的 route.conf 注入与 Lua 写面残留=发现 16（O0-OPERATION-MATRIX 标「迁移」中）。
- **reference-ctl-production-deploy**（ctl 生产 .180 非 git、停 2026-03、整仓部署需单独发布）：deploy.sh 口径冲突=发现 11；install.sh/upgrade.sh 双面漂移=发现 5。
- **垃圾文件（shell 码名，生产未删）**：仓库侧干净——四仓文件名全扫（`$ ; && 空格 .bak .orig .old ~`）仅 `guest.htm.bak`（发现 19），ctl 根无同类。
- **R1/CPI-71、O2 rpcd 三宿主差异**：openwrt 本轮只核到桥面静态一致（`docs/O2-BRIDGE-METHOD-MAP.md` 记有 check_bridge_acl.sh 三方对齐门禁），未复评合同断层，不重复立账。

## 疑点待验证

1. [ctl] `PageController.php:23` home 只推 tui/gnb/utc 是否产品有意为之（下载面只推三件）——若是，发现 9 降级为口径文档化；若否，按发现 9 处理。
2. [openwrt] 13 个零调用 api_* 端点是字符串静态结论；四仓内无调用方（已含 mynet_agent），但若有仓外脚本/旧页面直呼 HTTP（设备侧遗留）则非死码——删除前建议看真机 uhttpd access log 一轮。
3. [openwrt] heartbeat.sh 若有现场手工加 cron 在用（Makefile 从不登记，但注释误导），退役前先确认无人使用。
4. [ctl] `tests/CreatesCtlTables.php` 与迁移的差异本轮只逐列核了 `releases.kind`；plugins/scripts/api_tokens 等其余表未逐列比对，可能还有同族分叉。
5. [ctl] `scripts/import_gnb_v1.6.0.a.sh` 是否随 gnb 版本推进已可归档——`repo-migration.md:283` 有「import_gnb_*.sh 的 GH_BASE 仍指个人仓，别顺手改」的叮嘱，动前需裁定。
6. [desktop] `dist/` 本地产物（v0.0.0-dirty/localtest 的 dmg/pkg/exe）未跟踪、被 .gitignore 吸收，不算仓垃圾；建议本地清理（未列发现）。
7. [web] 社区域/官网定位重定（B2/产品面）超出代码审计裁定范围，发现 26/27 的处置随该裁定。

## 覆盖说明

- **mynet_ctl**：app/ 全部控制器/服务/Domain、routes/{api,web,console}、tests 全部、scripts/ 全部（含 deploy/ 与 proxy-lists 指针）、Makefile/README/phpunit.xml/.gitignore、docs 非知识库文件全部开卷或 grep；vendor/、resources/views 只做引用面 grep（views 链接面核过 /d/、/api/v2 形态）。实测 `make test`：**60 tests / 483 assertions / 1 skip**（skip=Feature/ExampleTest「前端未构建」）。nginx.conf 核过无 v1→v2 rewrite。
- **mynet_openwrt**：415 个跟踪文件名级清点；Makefile（包/文件清单比对，install 清单与仓库文件核对）、13 个 Lua 模型 + 2482 行 controller + mynet.uc 桥结构与引用面 grep、heartbeat/firewall 脚本、docs 粗扫。未跑 Makefile.guard、未上真机（ubus/rpcd 行为未实测）。
- **mynet_desktop**：25 个跟踪文件全清点；`sh scripts/check.sh all` 实测 **40 通过 / 0 失败**；wintun.dll 三处 SHA256 一致（`e5da8447…`：provenance.md、build-nsis.sh:48、实际文件 shasum）；退役件（systemd/、launchd/、packaging/、service.ps1）实测不存在且 check.sh 有负向守卫（:60-85）；.github/workflows 与 CPI-77 对照。
- **mynet_web**：14 个代码文件全读或引用面 grep；`sh scripts/check.sh all` 实测 **7 通过 / 0 失败 / 1 已知缺陷 / 2 跳过**（eslint/vitest 缺 node_modules 跳过，跳过不算通过）。
- **零引用判定的复核面**：openwrt 端点查了 luasrc/view、htdocs、scripts、mynet_agent 全部消费者；ctl buildDistUrl 查了全仓含 tests；无 @/ 别名体系（PHP 按命名空间 use 语句、Lua 按 require 路径核对）。字符串 grep 属静态检查，标注处已进疑点。
- **只读约束**：本轮未改任何仓库文件；仅跑 `make test`（ctl，内存 sqlite）、`scripts/check.sh`（desktop/web，只读校验）与 grep/find/git 取证；未触生产。

<!-- SOURCE-END C3-ctl-openwrt-desktop-web.md -->
