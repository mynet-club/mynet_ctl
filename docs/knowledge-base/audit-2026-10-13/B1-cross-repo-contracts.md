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
