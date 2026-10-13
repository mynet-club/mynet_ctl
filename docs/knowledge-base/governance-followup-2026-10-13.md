# 对账修复接续证据（2026-10-13）

> 本文件仅保存完成批的详细证据。全部发现与剩余项看 [合并台账](audit-consolidated-2026-10-13.md)，完整执行顺序看 [治理计划](governance-plan-2026-10-13.md)。

依据：`audit-2026-10-13.md` 与 `governance-plan-2026-10-13.md`。原审计冻结；本文件记录接续批的实际交付，不将全部治理计划标为完成。

## OpenWrt 旧路由注入入口退役

提交：mynet_openwrt `b45a2e7`（本地，未 push）。对应总报告 §4 S2-16 的旧入口残留。

- `luasrc/model/mynet/node.lua:193-209`：保存 route 配置仍生成系统路由配置，但不再加载 Proxy 配置或调用旧 `route_inject()` 写入钩子。
- `luasrc/model/mynet/proxy.lua`：删除 `route_inject()`、`route_restore()`；删除前全仓复核，前者仅由上述钩子调用，后者无代码消费者。历史 CHANGELOG/测试报告引文保留。
- `luasrc/model/mynet/proxy.lua:334`：保留 `strip_route_injections()`，用于生成系统路由前过滤历史 marker 段；保留 UI 的历史注入状态查询。
- `tests/local_config_test.lua`：运行实际 Lua 模块，验证 route 保存不加载旧 Proxy 模块、原始配置内容与换行、node/address 保存、生成失败与未知类型错误传播、历史 marker 过滤及正常 GNB 路由保留。文件系统与配置依赖用内存替身；非设备验收。
- `Makefile.guard`：行为测试接入 `test`，可通过 `LUA` 指定目标运行时；缺 Lua 时失败，不静默跳过。

实测：`make -f Makefile.guard check` 通过（guard、Lua 语法、打包不变量、桥/ACL/LuCI 19 方法一致、上述行为测试）；`git diff --check` 通过。环境 Lua 5.4.8，**目标 Lua 5.1 与设备实测未做**。

审计口径核正：修复前 `route_inject()` 实际已经只清理历史 marker 段，不再添加 /8、/16 路由。因此不能继续把该函数描述为会回灌大段路由。本批解决旧生产调用与死入口；**不代表 O1「桥→Go apply --auto 唯一路径」整体完成**，Lua 自行配置/路由生成与完整装配迁移仍需后续治理。gnb 仓正式 route.conf 未动；未部署或重启设备。

## 避免重复工作的取证

接续时已存在的本地提交（只核代码/提交记录，本批不重测其全部出口）：

- agent `9525391`：私钥改 stdin 与 argv 遮蔽；`9a578a6`：OpenWrt embed/产物清理；`ee83dd9`：security_audit 路径修正。
- 后端 `c1e8fff`：心跳接口判全；`8c1fa4b`：keys 响应收窄；`51498f6`：DomainException 修正；`110f1d8`：500 断言修正。
- GUI `6678e11`：登出、状态请求、refresh、IP 池删除；`e08ad98`：残余死码清理。
- ctl `50f398b`：manifest 扩展名、filename 取件、版本比较、测试 kind。
- OpenWrt `bdb2939`：节点面 API 路径映射。

agent 托盘/服务名/home 与后端 `tests/TestCase.php` 在接续时有他人未提交改动，本批未碰。治理计划中的「执行中/未开始」不是上述提交是否存在的可靠依据；继续开工须核 HEAD 与工作树。

## 后端 GNB CLI 假绿与私钥日志收口

提交：mynet_api `873ebc9`（本地，未 push）。对应 A3 的 GnbKeyCliFallbackTest 假绿项，并新增核实私钥日志出口。

- `tests/Unit/Domain/GnbKeyCliFallbackTest.php`：删除旧 workspace 绝对路径与依赖缺失 skip，直接加载生产 `config/mynet.php`（配置变量 `MYNET_GNB_CRYPTO_BIN`，默认 PATH 上 `gnb_crypto`）。原生扩展存在时直接调用 CLI 分支，仍验证回退。默认套件需要真 CLI，缺少即失败。
- `app/Modules/VPN/Domain/Services/NodeKeyDomainService.php:523`：CLI 可执行路径作为单参数引用，支持空格路径；缺配置明确拒绝；输出必须合法 64/128 hex；成功、失败、格式异常都在 finally 清理临时私钥/公钥/目录。CLI 原始输出不进入异常与日志，仅保留退出码。
- 同服务的 Ed25519/GNB/WireGuard 三处成功日志删除私钥预览；`NodeKeyResponseDTO::getSummary()` 删除私钥预览。新增 `NodeKeyLogSafetyTest` 用实际生成与日志事件验证 Ed25519/WireGuard 日志及 DTO 输出无私钥片段；本机无 GNB 原生扩展，该分支日志删除为代码验证，CLI 分支已真跑。
- `TenantServiceApiTest` 的无 Accept 头请求按生产 `shouldRenderJsonWhen(api/*)` 断言 401 JSON，替换旧空正文断言。

实测：真 CLI 与失败路径 **3 tests / 10 assertions**，日志事件 **1 test / 6 assertions**；全量 `make test` **497 tests / 4790 assertions / 1 skip**（既有 SQLite 并发）；guard、改动文件 Pint 与 diff 检查通过。

全量测试依据为**当前工作树**，包含其他修复批尚未提交的 `tests/TestCase.php` 镜像；该文件未被本批改动或提交。401 JSON 断言依赖这份生产镜像，整批发布需一起集成，不能将此记录当作仅 `873ebc9` 干净 checkout 的全量验收。未测试或部署生产。

## GUI 三层路由守卫收敛

提交：mynet_gui `a874bc3`（本地，未 push）。对应 S2-15。

- `src/boot/auth.ts`：恢复认证后注册唯一的 `setupRouteGuards`，不再自建 beforeEach；认证初始化失败抛出，阻止无守卫启动。
- `src/router/middleware.ts`：读认证 store 的实时 `isAuthenticated/token`，包含过期时间与用户状态，不再以本地 token 存在作为登录依据。统一登录重定向与完整 redirect 参数、node ID 校验、页面标题；删除恒 true 的编辑权限函数，授权归后端。
- `src/router/index.ts`/`nodeRoutes.ts`：删除重复节点守卫注册与实现，其余导航工具保留。
- 既有 callback 白名单保留；用 URL.searchParams 设置 token，避免 fragment 吞参或旧 token 重复。外域拒绝与会话过期均由真实 memory router 导航测试覆盖。

实测：新增 5 项导航测试；全量 vitest **58/58**；`make check` 通过（eslint 零错误，存量 warnings；i18n；类型棘轮 **184 未升**；guard；vitest）；SPA 正式构建通过。最后移除未用 boot 参数与 EOF 空行后 diff 检查与提交 guard 通过。构建产生的已跟踪 `.quasar/prod-spa/client-entry.js` 在提交前恢复，本批不提交生成物。**未浏览器实测、未发布**。

## G6.a N-01 guard C4 词法修复 + G1 第一批余留删除（2026-10-13，未提交）

对应治理计划 G6.a / G1。**未 commit、未 push**；不触碰他人未提交件
（api `tests/TestCase.php`、agent 托盘/服务名/home/handlers/wizard、web `.githooks/guard.sh`）。

### G6.a N-01（guard C4 诊断乱码 → 自测 8 红）

- 根因：`bad "C4 编译产物不得入库（$c4_hit）: ..."` 里 `$c4_hit` 紧贴中文全角 `）`，
  shell 按字节解析变量名，诊断变成 `不得入库（��: app.exe`，原因丢失；
  退出码仍正确，故不能称 C4 全失效。
- 修法：显式变量边界 `${c4_hit}` / `${f}`（模板与各仓同一处）。
- 同步：模板 SSOT + ctl/api/gui/agent/desktop 六份；并把模板已有的
  `.guard-c4-allow` 持久白名单块补进上述五仓（desktop 原已一致）。
- **保留 agent B2 有意分叉**（`mynet/(detector|tools|apply|metrics|service|keys)|shared/config|gopsutil`）。
- web/utc/openwrt/gnb 仓副本尚无 C4 段，且 web guard 为他人未提交 —— 本批不动。
- 实测：`sh templates/.githooks/guard_test.sh [各仓 guard.sh]`，
  模板/ctl/api/gui/agent/desktop **均 45 通过 / 0 失败**（原样双断言：退出码 + 原因）；
  不是把断言改窄换绿。修复前模板与 ctl 为 37/8。

### G1 API（A2-05/08/12/13/15/17）

| 来源 | 动作 | 证据 |
|---|---|---|
| A2-08 | 删 `UserService::listUsers()` | 全仓仅声明行；同类 `getUserList` 等仍有调用 |
| A2-15 | `ApiExceptionHandler` 只留 `handleNodeException`，删 5 个零调用静态方法 | `handleDomainException/businessError/resourceNotFound/accessDenied/validationFailed` 全仓 0 调用（`NodeException::accessDenied` 是另一类） |
| A2-12 | 删 `config/logging.php` 的 `admin` 通道 | 仅定义，无 `Log::channel('admin')` |
| A2-13 | 删 `config/mynet.php` 的 `tenant_isolation_enabled` | 仅定义，零读取 |
| A2-17 | 删 `app/helpers.php` 的 `current_tenant_id()`/`with_tenant_id()` | 零调用；且 `env('DEFAULT_TENANT_ID',1)` 会静默落租户 1。`term_zone()` 保留（有调用） |
| A2-05 | 删空目录 `database/factories`、`lang`；composer 去掉 `Database\Factories\` autoload | `find -type d -empty` 仅此二处；app/database 等历史空目录已不在 |

实测：`php vendor/bin/phpunit --testsuite Default` → **497 tests / 4790 assertions / 1 skip**（原样绿）。

### G1 GUI（C2-08 + `.quasar` + public 测试 HTML）

| 项 | 动作 | 证据 |
|---|---|---|
| C2-08 | 删 `getDashboardData` + `mockApiResponse` + `mockData.ts` 整文件 | mock 栈仅 api.ts 自用；`useDashboard`/`DashboardPage` 走真数据，不删 |
| 生成物 | `git rm -r --cached .quasar front/.quasar`，`.gitignore` 加两行 | 24 个生成文件出索引；`npm ci→quasar prepare` 可重建 |
| 测试 HTML | `public/{network-config-test,test-boot,time-test}.html` → `dev-tests/html/` | 生产 `public/` 只剩 docs/favicon/icons |

实测：`npm run test:unit` **58/58**；`check:types` 棘轮 **184 未升**；`check:i18n` 通过。

### G1 ctl（C3-06/07）

- 删 `scripts/verify-endpoints.php`（全仓零消费者，校验的是已下线 URL）。
- 删 `ReleaseDomainService::buildDistUrl`（零调用；`ScriptService::buildDistUrl` 是同名异物，保留）。
- **不删** ReleaseService 上传/ext 与坏 URL fallback（活功能，归 G4）。
- 实测：`php vendor/bin/phpunit` → **73 tests / 513 assertions / 1 skip**。

### G1 OpenWrt（C3-18）

- 删 `scripts/shell/heartbeat.sh` 与 Makefile 安装行（无人调度；postinst/prerm 从不写 cron）。
- **保留** postinst/prerm 的 `sed -i '/mynet-heartbeat/d'` 旧 cron 清理迁移。
- 实测：`sh .githooks/guard.sh` 通过。

### G1 agent（C1-14 收口）

- 删 `cmd/keyprove`（头注释自述「实验」，Makefile/测试/脚本零引用；历史仅 archive 报告提及）。
- 保留 `mynet/keys/genvec` 并注明为 **NodeFace 联测向量生成器**（产出 `testdata/node_face_vectors.json`，供 `node_face_vector_test.go`）；删空壳 `rfc_check.go`（`//go:build ignore` 空文件）。
- 实测：`go test ./mynet/keys/ ./mynet/ ./shared/utils/` 全绿。

### 本批未做 / 阻断

- 未提交未推送（等确认后按仓小提交）。
- 未碰 G0 的 69 组「已提交待复验」逐项销账（体量大，另开批）。
- G2/G4 需用户裁决；web guard、api TestCase、agent 生命周期批仍为他人未提交。
- openwrt `make guard` 需 SDK `package.mk`，本机只跑了 `.githooks/guard.sh`。

## G0 已提交待复验 —— 文件/符号/行为抽查销账（2026-10-13，未提交）

对 §5 G0 的 69 组「已提交待复验」按**提交簇**做覆盖核对（不只看 commit 标题），
并对治理计划 G0「必须验证」行为面实跑。**一组多对象逐一对上**的以符号/文件清单为准。

### 提交簇覆盖核对（样本穷尽关键对象）

| 提交簇 | 组 | 核对结论（file:line / 实测） |
|---|---|---|
| api d73962f | A1-01, A2-07 | `GET /users`/`users/search` 已下架（`VPN/Routes/api.php:230` 注释+route:list 无 URI）；onboarding 仍在 → 按计划留 G2 |
| api 51498f6 | A1-08 | 域异常统一为 `App\Modules\VPN\Domain\Exceptions\DomainException`（`DomainException.php:22`）；SPL 混用消失 |
| api 8c1fa4b | A1-09 | `NodeController::keysResponsePayload()`（:966-976）白名单仅 node_id/public_key/key_type/algorithm/generated_at；mail_data/User 不进响应 |
| api 1564646 | A1-10/11/13 | 原 26 死类清单抽 16 个全部 gone；原 30 死方法（NodeMonitor/NodeConfig/NodeController×8/Route×4）全部 gone；**保留** `getRouterKeys`（live，G0 明示）与 `TenantServiceController::getIndexServices`（`Routes/api.php:192`） |
| api 74e534f | A2-01 | `app/Services/stubs/` 仅剩 `ToolDtoPlaceholder.php`；MailTemplateService/AuditService 垫片与 composer files 声明已删 |
| api ae39804/873ebc9 | A2-02 | `config/mynet.php:55` `gnb_crypto_bin` + `NodeKeyDomainService.php:525-527` 只认配置路径 |
| api c159c2c | A2-03 | `config/cors.php:16-17` 显式空赋值拒绝，不再 `? explode : ['*']` |
| api 4881f91 | A2-04/06/09/10/11/14 | stubs/cache 产物/dup 配置清理后与上述一致；composer 仅留 ToolDtoPlaceholder |
| api 110f1d8 | A3-03 | （测试面）create/update 成功断言不再接受 500 —— Default 套件 497 绿 |
| api 993b475 | A3-04 | MariaDB 方言说明保留；**未**据此宣称 MariaDB 行为已验 |
| openwrt bdb2939 | B1-03 | Lua 仍有 v1 path（`api.lua:153/188/209`）与 HMAC（`system.lua:698-703`）；HMAC/回执**实链仍归 G5** |
| gui 6678e11 | B1-07/08, C2-05/09 | middleware 导航测试 5/5；登出链路仍在（真能力）；旧 `monitor/heartbeat` 用户面调用 0 |
| api c1e8fff | B1-09 | `HeartbeatRequest.php:70-90` 复数 `vpn_interfaces` 标准注释；`HeartbeatVpnInterfacesTest` 覆盖「非仅 [0]」 |
| ctl 621a8a9 + agent 370b26d | B2-03…26, C1-18, B2-21…26 | 文档/台账归位提交（ctl 20+ kb 文件 + agent playbook）；B2 逐条正文核对仍属 G7 收口，本批只核提交文件覆盖 |
| agent 9525391 | C1-01 | `cmd_config_face_keys_test.go`：stdin 读入 / argv 保留 / 缺 private 报错 / 空 stdin 报错 **四出口全 PASS**；`cmd_config_face.go:431-433` 红线注释在 |
| agent 9a578a6 | C1-05/07 | OpenWrt 去 embed、webui.html 出产物（desktop 4ef90c3 口径一致） |
| agent ee83dd9 | C1-09 | 后端路径修正；**缺仓失败语义**仍未行为验收（不把 grep 当完成） |
| agent 44339e1 | C1-12 | shared/errors 删除（与本批 G1 删 keyprove 同类） |
| gui 0235888 | C2-02/07/11 | `src/pages` 无 debug 页；A1-07 旧心跳封装 0 |
| gui 8e623fe | C2-03 | deploy-target 单源；**未**做生产回滚实测 |
| gui 371edbd | C2-06/10 | 孤儿/推荐残骸删除；类型棘轮 184 未升 |
| gui e08ad98 | C2-12/13/14/18 | useCache/空文件/testData/middleware 死后半/barrel 清理（全量 vitest 58/58） |
| ctl 50f398b | C3-01/02/04/10 | `ReleaseFilenameTest`+`ReleaseServiceTest`+`ManifestServiceTest`+`ReleaseDownloadRouteTest` 等 **65 tests / 489 assertions 全绿** |
| ctl aa7a54a | C3-05/09/12 | 旧脚本删、公开产品面派生、远端口令 defaults-file（ctl 73 绿） |
| ctl b8fc68a | C3-19 | guest.htm.bak 删除 |

### G0「必须验证」行为面实跑

| 面 | 结果 |
|---|---|
| 后端全局中间件/异常渲染/租户 | `bootstrap/app.php:40-108` prepend/append/redirectGuestsTo(null)/shouldRenderJsonWhen + renderable；`tests/TestCase.php` 仍镜像（他人未提交件，只读核对） |
| GUI 守卫 | `src/router/__tests__/middleware.test.ts` **5/5**；全量 vitest **58/58** |
| agent 私钥 stdin/argv 失败出口 | `TestParseKeysSetArgsStdin/DirectFlag/MissingPrivate/EmptyStdin` + `TestKeysSet*` **全 PASS** |
| ctl filename/kind/版本 roundtrip | filter Release/Filename/Kind/Manifest **65/65** |
| OpenWrt v1 路径与剩余 Lua | 14 个 Lua 在；v1 URL/HMAC 注释与调用点在。**Lua5.1 设备验收 / HMAC 实链未做**（归 G5） |

### 保留理由（G0 明示，不删）

- `POST /nodes/{id}/router-keys`（`NodeController::getRouterKeys:816`）— live 消费者。
- GUI avatar/me/url 与 config-bundle 后端 v2 — 旧 bundle 待消费者全迁后删（G2）。
- onboarding PUT 假持久化 — 归 G2 裁决，不在 G0/G1 删。

### G0 仍未销账 / 下批

- B2 26 组文档**逐条**正文核对（本批只核提交文件覆盖）→ G7。
- A3-01/02、C1-06/11「进行中未提交」（TestCase、托盘/SCM/label/home）— 等其批集成后复验。
- A2-22 待核实、B2-02 待修复、C3-16 部分修复 — 不在已提交待复验集。
- C1-09 缺仓失败语义、C2-03 生产回滚、B1-03 HMAC 实链、OpenWrt Lua5.1 — 明确未验，不冒充绿。
- project-memory HEAD 表需在下一批收口时与当前 worktree 对齐刷新。

## G2/G4 用户裁决（2026-10-13，交互式拍板）

| 裁决项 | 用户选择 | 实施口径 |
|---|---|---|
| G2 四个鉴权 URL | **删壳，但迁等价项** | validate-reset-token/change-password/activate-account/resend-activation-email：无完全等价则删；verify-email/resend-verification/PUT auth/password 仅在载荷·token 类型·登录前后身份全等价时迁 |
| G2 per-key 撤销 | **删 per-key 按钮/调用** | 不映射成整节点撤销；负测试锁「整节点密钥未被 per-key 误撤」 |
| G2 onboarding | **两端删假持久化** | 删 PUT 假 handler 与 GUI 云保存调用；本地引导如仍要则明示本地状态 |
| G2 route stats/history | **stats 删壳；history 迁 peer-connections** | history 必须核 nodeId/routeId、分页/时间/字段对齐后迁，不只换 URL |
| G2 兼容面 | **connect 删，bundle 立即删，v2 保留** | 用户明确：legacy config-bundle **立即删**（严于计划的「待全迁」）；v2 Rewrite 保留至 ctl 迁完并给退出条件 |
| G4-A wire 词表 | **①保留驱动字段 + 抽翻译层** | BundleEnvelope 的 Gnb*/Wg* 不改名；业务命令走翻译层；双侧 fixtures/schema/兼容窗口同步 |
| G4-B 发布矩阵 | **双件：installer+archive** | Windows 更新 zip、darwin 更新 tgz、OpenWrt 更新 opkg |
| G4-B 身份 | **filename 精确唯一** | 沿用 filename=下载精确身份；重复 filename 幂等/拒绝 |
| G4-C/D 旧产品面 | **整面退役 tui+client+@mynet 插件** | 清单只留现役产品；successor 不得指向冻结的 client |
| G4-D 活发行错误 | **本批修** | 拒未知 ext/空 kind；无效 filename 不得成功登记；假 `/d/` 链消除；不重做已有 filename 精确下载修复 |

裁决来源：`question` 交互；计划 §7「G2 有消费者功能处置 / G4 wire·双 kind」两项已解除待裁决。

## G2 实施 + G4 部分实施（2026-10-13，按用户裁决，已提交未 push）

### G2（已完成）

| 裁决 | 落地 | 证据 |
|---|---|---|
| 鉴权：删壳迁等价 | changePassword→PUT auth/password；activate/resend→verify-email/resend-verification；validateResetToken 删预校验 | gui `4a3b146` |
| per-key 删除 | 删 GUI 按钮/对话框/service；整节点 revoke 保留 | gui `91ee3b7` |
| onboarding 两端删 | API GET/PUT 下架；GUI 本就 localStorage | api `1d25562` |
| stats 删 / history 迁 | ConnectionDetailsPage→/nodes/{id}/peer-connections；请求键对齐 | gui `4fd64b9`；api `6d3218b` |
| connect 删 / bundle 立即删 | routesService.connectToNode 删；legacy config-bundle 路由/handler 删；ctl ConfigPack 固定分散请求 | api `6d3218b`；ctl `f9f4877` |
| v2 Rewrite 保留 | 未动（退出条件：ctl 迁完 v1） | — |

测试：api **497/1skip**；gui **58/58**（类型棘轮 183≤184）；ctl **73→78/1skip**。

### G4（部分完成）

| 裁决 | 状态 | 证据 |
|---|---|---|
| D4-D 上传活错误 | **已修**：拒未知扩展/空 kind/重复 filename；假 /d/ 链消除 | ctl `01a3d7a`，ReleaseServiceTest 10 例 |
| filename 精确唯一 | **已修**：登记前查重 + 可反解析硬门槛 | 同上 |
| C3-08 产品面退役 | **已修**：PRODUCTS 只留现役；tui/client 进 RETIRED；successor=mynet_agent；@mynet plugins 不再广告 | ctl `833b4fe`，78 tests |
| D4-A 翻译层 | **未做**：BundleEnvelope Gnb*/Wg* 保持 wire 不变；业务命令翻译层待下批 | — |
| D4-B 双件发布 | **未做**：installer+archive 同 tuple 双 kind 上传/矩阵/迁移 DDL 待下批 | — |

### 下批入口

1. D4-B：同 tuple 两 kind 上传→manifest→精确下载→checksum；ctl 唯一键/查询支持双件；重复件预检 DDL（不实施生产）。
2. D4-A：业务命令词表→驱动字段翻译层（双侧 fixtures/schema/兼容窗口）。
3. G3 mynetd 边界、G6.b/c/d、G7 收口。
