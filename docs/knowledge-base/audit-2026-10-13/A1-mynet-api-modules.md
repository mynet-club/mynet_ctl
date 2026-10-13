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
