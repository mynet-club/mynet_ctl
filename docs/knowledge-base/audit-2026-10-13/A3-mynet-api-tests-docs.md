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
