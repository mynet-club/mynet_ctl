# Zone 删除 — 决策包（**已裁决**）

> **2026-10-04 用户裁决：议题 1 = A（改名保留）；议题 2 = B（改指真实平台租户
> `9007199254740991`，用户注明「现在生产端在用」）。**
> 用户同时确认了本包的核心判断：「zone 就是 tenant，防火墙那个 zone 是另外一回事，
> 从这个上来看，确实不能粗暴说删 zone」。
> **第二轮追加三条裁定（同日）**：① 用户面**不表达** tenant 权限/边界（「user 是 user，
> operator 是 operator……用户无感 tenant 的存在」「GUI = 普通用户前端」「收敛该收敛的吧」）
> ② LocalStorage 键 `current_zone` 改名并做迁移 ③ en-US 用词 = **Network**。
> **状态：本包四步全部落地**（`mynet_api df6c4da/1e42a4d/5aa8f34/8a98e3d` +
> `mynet_gui 44f389d/68b273f/cf00de3/61d6b86/434701b/716f431` + `mynet_agent b80c029/9223eeb`）；
> 剩下的 7 条未决见「第 2 步留下的未处置项」。
> 下面保留裁决前的取证与候选方案原文；落地情况见「## 裁决与执行」。

用户裁定是「干净，可以删掉」。动手前做了一轮取证，结论是：**这个任务的性质和裁定时以为的不一样。**
它不是「删掉一个叫 Zone 的业务对象」，而是「把一层名字换掉 + 处理两件残留物」。
按原口径盲删会出两类事故，见下。

---

## 裁决与执行

| 议题 | 裁决 | 落地 |
|---|---|---|
| 1 `ZoneListPage`/`ZoneSharingPage`/`/zones`/`ZoneSelector` | **A 改名保留** | Zone* → Tenant*，路由 `/zones` → `/tenants`，功能不减。**第 1 步（GUI 内部改名）已落**：`mynet_gui` `cf00de3`（24 文件，5 个 `git mv`）；**第 2 步（契约翻转）已落**：`mynet_api 5aa8f34` + `mynet_gui 716f431`，见下「第 2 步落地」与「建议的切法」。 |
| 2 `DEFAULT_GLOBAL_ZONE`（`999999999`） | **B 改指真实平台租户** | 已执行：`mynet_gui` `68b273f`，提出 `PLATFORM_TENANT_ID = 9007199254740991` 常量替掉四处魔法数，与 `mynet_api config/id.php:51 platform_tenant_id` 同源。**兜底行为按 B 保留**，没有改成 A 的显式错误态。 |
| — 第 0 步零风险清理 | — | 已执行：API 侧 7 处「另一个 zone」注释加防误删标记、③④ 类文案清理（`mynet_api` `df6c4da`）；删 GUI 死副本 `zoneServiceApiWithCache.ts`（`mynet_gui` `44f389d`）。 |
| 3 GUI 改名后界面叫什么 | **网络区**（2026-10-04 追加裁决） | 已执行：`mynet_gui` `61d6b86` + 补漏 `434701b`；API 侧中文消息 `mynet_api` `8a98e3d`；节点侧 WebUI+CLI `mynet_agent` `b80c029` + 补漏 `9223eeb`（`mynet/commands.go:usage` 的向导帮助行漏了）。产物侧已随 `v0.0.7-pkg` 出包（`make dist` 五平台 + `make pkg-darwin`，见下「出包口径」）。用户原话「我也觉得『租户』太技术腔……先叫网络区，用个变量名替代吧，这样我们后续想到好词可以替换」，第二轮追加「都统一吧，能否也设置为变量」。 |
| 4 en-US 界面词（第 3 项留下的未决 ①） | **Network**（2026-10-04 第二轮裁定） | 已执行：`mynet_gui 716f431`，`i18n/en-US.ts` **只改值不改 key**（`zones.*`、`navigation.zones` 等 key 名保持，属下面冻结的 i18n key 层）。落词：`navigation.zones='Network Management'`、`zones.*` 整块、`routes.friendNodes.zone='Network'`(`:1199`)、`social.search.zone='Network'`(`:1697`)、`ippools.quotaExhausted`、`tips.zoneManagement.{title,content}`、`nodes.quickCreate.noZone`。cluster B 的 `zoneServices`/`zoneSharing`/`zoneFilter` **没动**，挂在未决 ③ 上。 |
| 5 LocalStorage 键 `current_zone`（未决 ②） | **改名 + 迁移，不许静默丢用户已选网络区** | 已执行：`mynet_gui 716f431`。新键 `current_network` **只存 tenant_id**；旧键 `current_zone` 双读迁移（兼容历史上「对象」与「JSON 串」两种形态），迁移成功即写新键删旧键，解析失败只 warn 不崩，logout 两键都清。键名常量在 `config/app.ts:27-28`。 |
| 6 用户面要不要表达 tenant 权限/边界（第 2 步执行中冒出的越权面） | **不表达 —— 用户无感 tenant 的存在**（2026-10-04 裁定，原话见下「user≠operator 裁定」） | 已执行：`mynet_api 5aa8f34`（`/tenants` CRUD 收敛到 console 面）+ `mynet_gui 716f431`（删管理面残留）。**没有**给 `User` 补 `isTenantAdmin`/`canAccessTenant` 一类判定。 |

### 界面用词的三层分工（第 3 项裁落后固化，别再混用）

| 层 | 叫什么 | 落点 |
|---|---|---|
| 中文界面文案 | **网络区** | 四个面各有一个取词单点，换词四处一起改：① `mynet_gui/src/config/terms.ts` 的 `TERM_ZONE`（`i18n/zh-CN.ts`、`router/routes.ts` 的 `meta.title`、`TipsRegistry` 的 fallback 都从它拼接）② `mynet_api/config/mynet.php` 的 `terms.zone`（`term_zone()`，覆盖直接渲染到前端的校验/错误消息）③ 节点 WebUI `mynet_agent/mynetd/localapi/webui/index.html` 内联的 `TERM` ④ 节点 CLI `mynet/cmd_node.go` 的 `termZone`（同包的 `commands.go:usage` 帮助文案也拼它）。 |
| 代码标识符 | **Tenant** | 类型/文件/方法/组件名（第 1 步 `cf00de3` 已搬）。 |
| 接口 wire 字段 | **`tenant_*`**（2026-10-04 第 2 步已翻） | `mynet_api 5aa8f34` + `mynet_gui 716f431` 同批原子翻完，`zone_id/zone_name/zone_status/zone_remarks/zone_domain/zone_admin_name/total_zones/active_zones` 在**租户域**已消失。两处例外没跟翻，都有理由：① WebSocket 频道名 `zone.{id}.nodes`（`mynet_gui/src/services/websocket.ts:153/169`）与 `NodeEventPayload.zone_id`（`:28`）——**服务端没有对应实现**，不自行发明契约；② 非租户域的 `zone_id`（Node/Route/Friend/IPPools 等，见下「第 2 步留下的未处置项」）。 |

为什么用 TS 常量而不是 vue-i18n 的 `@:term.zone` 链接消息（实测，非推断）：intlify 的
key 解析器会把链接标记后面的中文字符一起吃进 key —— `@:term.zone名称` 被解析成 key
`term.zone名称` 并报 `Not found ... in 'zh' locale messages`。中文文案里「网络区名称/
网络区描述/切换网络区后」这类粘连用法是多数，链接方案覆盖不到，所以走模板字符串拼接。
打包产物可验证：`dist/spa/assets/index-*.js` 里「网络区」只出现 1 次（`const ng="网络区"`），
其余全是 `` `切换${ng}` ``。

节点侧为什么是「内联常量 + 静态字面量」两套（`mynet_agent` `b80c029`）：

- WebUI 的 `TERM` 内联在 `index.html` 的 `<script>` 顶部，**没有**单开 `terms.js` ——
  `Makefile:119` 的 dist 只 `cp index.html $out/webui.html`，多一个兄弟文件会让那个参照
  产物 404、`TERM` 变 undefined，整页 JS 直接崩（实际服务走 `-tags embed` 的 `all:webui`，
  不受影响）。
- `index.html` 的静态标签（导航按钮、`<small>`、`<option>`、iframe `title`）保持原生 HTML
  字面量：`webui-context.js` 头注释定的约定是「布局与页面结构由 index.html 原生维护」，
  所以不做 DOM 改写去填占位符。换词检索口径 `grep -n 网络区 mynetd/localapi/webui/index.html`。
- 原先同一概念在节点 WebUI 里有三种叫法（页名「网络空间」、卡片标签「空间」、
  sub「租户」、DNS 来源「租户下发」），这轮一并收敛；`<small>空间</small>` 后面那个
  只起解释作用的 `<div class="sub">租户</div>` 随改名删除。
- CLI 也要换：linux/openwrt 形态没有 WebUI，`mynet` 的中文输出就是界面（安装形态矩阵）。

**出包口径（`v0.0.7-pkg`，2026-10-04）**：节点侧改动只有重跑 `make dist` +
`make pkg-darwin` 才现网可见，所以本轮随包交付。`make pkg-darwin` 原先在 bare 化的
`mynet_gnb` 上必然报「找不到 gnb darwin 构建」，且 `P2_SRC` 那条硬编码相对路径同样落空
（后果是 pkg 只带 gnb 四件套、不带 p2 内容，与 v0.0.6 出厂面不一致）。本轮的出包命令：

```
cd mynet_agent && make check
make dist VERSION=v0.0.7-pkg
GNB_DIR=<含 gnb,gnb_ctl,gnb_es,gnb_crypto,MANIFEST 且目录名含 c411f14ee647 的目录> \
P2_DIR=<p2-route-proxy 目录> \
make pkg-darwin VERSION=v0.0.7-pkg
```

`GNB_DIR` 本轮复用 v0.0.6 已回归通过的 darwin/arm64 四件套（`mynet_gnb` 出包面这轮零改动，
重编 gnb 会撞 `1.6.8-c411f14ee647` 的基线漂移坑，见「gnb 出包基线漂移」）；`P2_DIR` 指向
`.wt/gnb-bare-leftover-20261004/packages/1.6.8-c411f14ee647/p2-route-proxy`，与 v0.0.6 pkg
内的 p2 文件逐字节一致。验收：pkg payload 文件清单与 v0.0.6 完全一致、gnb 字节一致、
`mynet version` 三查（baseline/patches/builder）、二进制内 `网络区` 有 / `租户`·`网络空间` 无、
`shasum -c checksums.txt` 全 OK、dmg 内 pkg 与 `dist/pkg` 同字节。

未决：~~en-US 仍显示 "Zone"~~ —— 已于 2026-10-04 第二轮裁定为 **Network** 并落地
（`mynet_gui 716f431`），见上表第 4 项。

**旧名对照**（本包「裁决前取证」原文不改写，按旧名 grep 会落空，映射如下）：
`pages/zone/ZoneListPage.vue` → `pages/tenant/TenantListPage.vue`、
`pages/zone/ZoneSharingPage.vue` → `pages/tenant/TenantSharingPage.vue`、
`components/common/ZoneSelector.vue` → `components/common/TenantSelector.vue`、
`composables/useZones.ts` → `composables/useTenants.ts`、
`services/zone/zoneService.ts` → `services/tenant/tenantService.ts`。
`services/zone/`（`index.ts` + `zoneServiceApi.ts`）**没动**——那是 cluster B。

### 议题 2 落地后新出现的 `pivot.is_admin` 权限语义 —— **已按「user≠operator」裁定消解**

`DEFAULT_GLOBAL_ZONE.pivot.is_admin: 1` 是当年伪造 `999999999` 时一起编的。改指真实
平台租户之后，它等于在客户端断言「当前用户是平台租户管理员」。

- 当年的读取点（第 1 步改名后的名字）：`useTenants.ts:38`（`isCurrentTenantAdmin`）、
  `:233`（`hasTenantManagePermission`）、`TenantListPage.vue:101`
  （`v-if="tenant.pivot.is_admin === 1"`）—— 决定是否渲染管理入口。
- **不是提权**：真鉴权在服务端，客户端这个标志只能让按钮显示出来，点了照样 403。
- 但危害比原先大：原先指向一个不存在的租户，整个对象都是废的；现在指向真实租户，
  一个不属于平台租户的用户会看到「我是这里的管理员」。
- **处置（第 2 步，`mynet_gui 716f431`）**：候选 ①②③ 都没选，因为用户 2026-10-04 的
  裁定把问题本身取消了 ——
  > 「我们的设计里, user 是 user, operator 是 operator，所以不会有 `isTenantAdmin` 这种
  > 可能出现的逻辑判断……使用这套框架, 就不应该对前端用户产生类似的权限/边界判断,
  > 用户无感 tenant 的存在。」
  > 「GUI = 普通用户前端」「此前设计我想让用户一个终端配置多个网络区的节点，因此有可以
  > 选择网络区、设置网络区的能力，都在用户面，所以后来泛滥到租户面了。**收敛该收敛的吧**。」

  所以管理入口连同读取点一起删除：`useTenants` 不再导出 `isCurrentTenantAdmin` /
  `hasTenantManagePermission`，`TenantListPage` 不再渲染管理 chip，
  `DEFAULT_PLATFORM_TENANT` 不再编造 `pivot.is_admin`
  （`config/app.ts:42` 只剩 `{ user_id: 1, tenant_id: PLATFORM_TENANT_ID }`，
  因为框架 `Tenant` 模型 `pivot` 是关系附带列、不给会 TS 报缺字段）。
  服务端半边是 `mynet_api 5aa8f34`：`/tenants` CRUD 收敛到 console 面。
- 顺带取证到的事实，写死在这里免得下次又编：**`tenant_users` 表没有 `is_admin` 列**，
  所以任何 `pivot.is_admin` 都不可能有真值来源；成员关系表里也只有 `tenant_id`/`user_id`。

### 顺带记入 CPI 的两条（都不是本包范围）

- **CPI-69（P1）**：`mynet_ctl` 安装向导的 `MynetBackClient` 打的是
  `/api/v2/user/zones`、`/api/v2/user/switch-zone`、`/api/v2/nodes`，而 mynet_api
  **只有 v1**（租户端点是 `/api/v1/user/tenants*`）→ 选租户/列节点两步打不通，
  且 CTL 是出包发行面。建议等本包第 2 步落地后一并改，避免撞车。
- **CPI-70（P2）**：`mynet_gui` 对每个 `node_status === -1` 的节点并发打一次
  `GET /nodes/{id}/status`，响应存进 `nodeStatusInfo` 后**从不读取**；且该端点旧的
  写副作用只能降级不能升级，对 -1 节点是空转 —— 两个方向都白跑。
  （该端点已在 `mynet_api` `4df87e3` 改为只读，见 `spec/node-status-gate.md`。）

---

## 现状：Zone 在代码里已经不存在了，只剩名字

### API 侧：**没有 Zone 业务对象**

`mynet_api` 的 `app/ database/ routes/ config/` 下含 zone 的行共 **22 行 / 9 个文件**，逐条分类：

| 类别 | 处数 | 位置 | 该怎么处理 |
|---|---|---|---|
| **① 对外响应字段名**（真 Zone 残留） | 6 | `TenantDomainService.php:157`（`zone_id`）、`:177/:186/:187`（`total_zones`/`active_zones`）、`:181`（`$activeZones` 局部变量）、`app/Services/UserService.php:104`（`'zone_id' => (int) $tenantId`，注释就写着「gui 仍叫 zone_id」） | 改名，**必须与 GUI 同步** |
| **①b 契约注释**（解释为何沿用 `zone_*`） | 5 | `TenantDomainService.php:140/141`、`TenantController.php:131/152`（「对齐 mynet_gui `useZones.*` 契约」）、`UserService.php:88` | 跟着①一起改措辞；**改字段名的那一提交里必须同时改**，否则注释就成了假文档 |
| **② 另一个 zone**（gnb 实例 / 防火墙 zone） | 7 | `BundleEnvelopePublisher.php:532/533/539/540`、`NodeConfig.php:46`、`BundleEnvelopeGnbContractTest.php:159/240` | **绝对不能删** |
| **③ 旧仓对比说明** | 2 | `IPPool.php:207`、`IpPoolAllocationTest.php:30`（「旧仓是 Zone 内唯一」，解释为何现在改成全局 UNIQUE） | 改措辞，保留理由 |
| **④ seeder 文案** | 1 | `ConsoleDevSeeder.php:40` `'演示租户（Zone）'` / `t-demozone01` | 改文案 |
| **⑤ 误报（不是 Zone）** | 1 | `UserService.php:127` `'timezone' => 'Asia/Shanghai'`（`grep -i zone` 命中 `timezone`） | **不动** |

合计 6+5+7+2+1+1 = 22，与 `grep -rni zone mynet_api/{app,database,routes,config} --include='*.php' \| wc -l` 一致。
（本表第一版把①b 折进①、②数成 8、并漏了 `TenantDomainService.php:140/141` 与
`UserService.php:88/127`，四类相加只有 18；已按上述命令重数校正。）

⚠️ **②是本次最大的误删风险。** 那些注释里的 zone 指的是「一台主机上跑多个 gnb 实例、
每个实例有自己的 tun 接口和防火墙 zone」，是现网 182 的实测结论：`gnb_tun` 被另一个实例
占用，回退到默认接口名会**静默**把整台机器的转发口径改错（gnb 还会把接口名截断到 15 字符）。
这与产品 Zone 毫无关系。按「grep -i zone 全删」的口径做，会把这段用事故换来的知识抹掉。

### API 侧已经是纯 tenant 的

- `app/Modules/VPN/Routes/api.php:256-271`：`tenants` CRUD + `overview` + `{id}/statistics`，
  以及 `user/tenants`（`index` / `switch` / `current`）→ `UserTenantController::switchTenant`。
- 也就是说**服务端迁移早就做完了**，Zone 只是没从响应字段名里清掉。

### GUI 侧：Zone 是一层壳，底下调的全是 tenant 端点

`mynet_gui/src` 下 **46 个文件、1041 行**提到 zone（`grep -rni zone mynet_gui/src`）。
但服务层的 URL 已经说明了性质：

| GUI 函数 | 实际打的端点 |
|---|---|
| `apiService.getZones()`（`src/services/api.ts:441`，请求在 `:444`） | `GET /user/tenants/` |
| `apiService.switchZone(zoneId)`（`api.ts:474`，请求在 `:477`） | `POST /user/tenants/switch`，body `{ tenant_id: zoneId }` |
| `zoneService.getUserZones()`（`src/services/zone/zoneService.ts:58`） | `GET /user/tenants` |
| `zoneService.switchZone()`（`zoneService.ts:64`） | `POST /user/tenants/switch` |
| `zoneService` 的租户 CRUD（`zoneService.ts:75/84/90/96/106/112/117`） | `GET/POST /tenants`、`/tenants/overview`、`GET/PUT/DELETE /tenants/{id}`、`/tenants/{id}/statistics` |

⚠️ **别把 `zoneService.ts` 和 `zoneServiceApi.ts` 弄混**（同一目录下两个文件，
本包第一版就把上面的行号挂到了错的那个）。`zoneServiceApi.ts`（187 行）打的**全是
`/services/*`**（`:65 /services/anonymous`、`:79 /services/my`、`:93 /services/show/{id}`、
`:106 /services/create`、`:117 /services/update/{id}`），缓存 tag 叫 `zone_services`；
它是**服务共享**面，`ZoneSharingPage` 用的就是它，与租户 CRUD 无关。
所以那一层的 Zone 命名要单独判定：改 `zone_services` → `tenant_services` 是纯内部改名，
但如果将来「服务共享」的作用域并不是租户，这个前缀本身就是错的。

**所以 GUI 的「Zone」= 租户，只是名字没换。** 这意味着任务是**改名**（机械但面宽），
不是重新设计作用域模型。多租户也是真的：`/user/tenants/` 返回列表、`switch` 可切换。

---

## 关键耦合：改名不同步会**静默**断掉 WebSocket

`src/services/websocket.ts:108` 把 GUI 内部叫 `zoneId` 的值，作为
**`X-Tenant-ID`** 头发给 broadcasting auth：

```
auth: { headers: { Authorization: `Bearer ${token}`, 'X-Tenant-ID': String(zoneId) } }
```

而 `src/boot/websocket.ts:14-16`：

```
const zoneId = authStore.user?.zone_id
if (!authStore.isAuthenticated || !zoneId || !userId || isWebSocketInitialized) return
```

**`zone_id` 缺失就直接 return，WebSocket 根本不连**，且不报错、不提示。
所以：API 把 `zone_id` 改名成 `tenant_id`、GUI 没跟上 → 通知功能全站静默失效。
这类 fail-quiet 正是 Playbook §9 要求 fail-closed 的反面。

推论：**api 与 gui 必须同批改、同批发布**，中间不能存在只改一边的状态。

**落地后状态（2026-10-04，`mynet_gui 716f431`）**：这条耦合已经消掉，做法不是「两边都改成
`tenant_id`」而是**把来源收成一处** —— 三个 `X-Tenant-ID` 注入点（`services/api.ts:61/:115/:140`、
`boot/websocket.ts:16`、`pages/system/TokenDebugPage.vue:212`）现在**全部只读
`authStore.user?.tenant_id`**，而 `user.tenant_id` 的唯一写者就是 `authStore`
（`stores/auth.ts` 的 `login:68` / `switchTenant:216` / `restoreCurrentTenant:193`）。
`services/websocket.ts:108` 那行头发本身不用改 —— 它接收的是形参。
另外原来的静默 return 改成带字段的 `debug.warn`（`boot/websocket.ts:19-23`）：
缺 `tenant_id` 或 `user_id` 时至少留下「通知推送不可用」的痕迹，不再 fail-quiet。
`services/websocket.ts` 的 `connected` 事件载荷也从 `{zone_id}` 改成 `{tenant_id}`。

⚠️ 但**这条仍然是全站最脆的一条**：GUI 没有类型检查（见「验证」节），字段名写错不会编译失败，
只会让通知静默失效。改这三个注入点之一时，用这条 grep 自查：
`grep -rn "X-Tenant-ID" mynet_gui/src`（当前 3 个写入点在 `api.ts:61/115/140`，
另两处走 `connect(token, String(tenantId))` 的形参）。

---

## 第 2 步落地：契约翻转（2026-10-04，`mynet_api 5aa8f34` + `mynet_gui 716f431`）

### 谁先动、怎么保证不撞车

API 半边先提交（`5aa8f34`，`php artisan test` **333 通过**），因为它带了一条
**真请求**用例把 wire 钉死：`tests/Feature/Api/TenantWireContractTest.php` 断言
租户列表项的键**恰为** `['tenant_id','name','status','description','domain','admin_name']`、
`pivot.tenant_id` 存在、`can_access` / `pivot.is_admin` / **任何 `zone_*` 不存在**、
`status` 保持字符串 `'active'`（不是 0/1）、switch 响应键恰为 `['tenant_id','name']`、
overview 键为 `['total_tenants','active_tenants','total_nodes','total_routes']`。
GUI 半边照着这份断言清单改读法，不靠猜。

### GUI 侧三层收敛（不只是换字段名）

| 层 | 位置 | 职责 |
|---|---|---|
| wire 通道 | `services/tenant/tenantService.ts`（125 行适配层 → **52 行**纯通道） | 只有 `getUserTenants` / `switchTenant` / `getIndexServices` 三个方法 + `unwrap()`。**不做字段改名、不折算 status** —— 旧适配层把 `status` 折算成 `zone_status=0/1`，而另一条直连裸 wire 的路径读不到，`TenantListPage` 因此一直是空值渲染。 |
| 状态与持久化 | `stores/auth.ts`（唯一写入者） | `readSavedTenantId:21` / `persistTenantId:43` / `loadUserTenants:184` / `restoreCurrentTenant:193` / `switchTenant:216` / `login:68` / `logout:160`。 |
| UI 编排 | `composables/useTenants.ts`（292 行 → **80 行**） | 导出 `{loading, availableTenants, currentTenant, hasTenants, fetchAvailableTenants, switchTenant, init}`，**不持有自己的租户状态**。 |

删掉的重复实现：`boot/auth.ts` 与 `layouts/MainLayout.vue` 各写了 ~60/~70 行的「4 层兜底恢复」
（读 LocalStorage → `JSON.parse` → 找 availableTenants → 回落 `user.zone_id`），两边会悄悄分叉；
现在各自只剩一句 `authStore.restoreCurrentTenant()`（`boot/auth.ts:120`、`MainLayout.vue:747`）。
第三条写入口 `authStore.setCurrentTenant` 一并删除。

`switchTenant` 新增一条**服务端回执校验**（`stores/auth.ts:216-250`）：
`result.tenant_id !== 请求的 tenantId` 就抛错，不把「接口 200 但切到别处」当成功。

### 验证（本仓的证据只有这三条 + grep）

- `npx eslint`（15 个改动文件）：**0 error / 11 warning**。逐条与 HEAD 对照
  （`git show HEAD:<file> | npx eslint --stdin --stdin-filename <file>`）：HEAD 同文件集合
  **12 条**，本批 **-2**（删掉 `api.ts` 的死 `Tenant`、`boot/auth.ts` 的死 `APP_CONFIG` 导入）、
  **新增 0**。
- `node dev-tools/check-i18n.js`：只剩既有的 `profile.preferences` 双语缺失（第 1 步已确认与 zone 无关）。
- `npm run build`：exit 0。
- 字段回归靠 grep：`grep -rn "can_access\|is_active\|pivot" mynet_gui/src` ——
  租户域已无这三个名字的读取（残余 `is_active` 属 node-keys/services/mockData 的另一语义）。

⚠️ **这三条都不等于行为验证**。实测确认的 GUI 校验盲区（已单独立档，见记忆
「mynet_gui 验证工具的三个实测盲区」）：
① 仓库**没有类型检查**（`package.json` 无 `vue-tsc`，`quasar build` 走 vite/esbuild 只剥类型）；
② `vitest` 装了但**零个 spec 文件**、无 `vitest.config`、无 `jsdom`/`@vue/test-utils`；
③ 无 `.githooks`，guard 在本仓提交上不运行；
④ `check-i18n` 只报「缺失」不报「未使用」，且对带插值实参的 `t()` 调用点是瞎的。
所以界面未实测（没起 dev server 点过网络区切换/选择器），字段读对只由 grep + build 背书。

### 第 2 步留下的未处置项（均未自行动手，等裁决）

1. **非租户域的 `zone_*` 死读**（这些是 Node/Route/Friend/Social/IPPools 域自己的字段，
   不在本步「租户 wire」范围内）：`types/index.ts:77/96/191/192/228/273/319/320/361/376`
   （`Node.zone_id`、`Route.zone_id`、`SearchUser.zone_id/zone_name`、`RouteStats.zone_id`、
   `RouteDataResponse.zone_id`、`IndexServiceMeta.zone_id`）、
   `useIPPools.ts:34/47/48/79/102/176/185/253/301`、`useConnectionsData.ts:59`、
   `pages/social/InvitationsPage.vue:77`（`user.zone_name`，界面直接渲染，
   而 `SearchUser` 的 wire 若真是 `zone_name` 就有值 —— **未实测**）、
   `services/zone/zoneServiceApi.ts:17`。
   注意 `useIPPools` 那几处**本来就是死代码**（取证见上节「IP 池的 `zone_id` 查询参数是双端死代码」；
   第 1 步改名只把局部变量改成了 `tenantId`，**查询参数名仍是 `zone_id`**，`:79/:176`），
   换死名字不解决问题，别在第 3 步顺手「修好」它。
2. **类型层断链，因为没类型检查所以从没暴露**：`TokenDebugPage.vue:122` 从
   `services/websocket.ts` 导入 `TimeUpdateMessage`，而该文件**没有导出这个名字**。
   同文件里 `handleConnected(data)` 现在读 `data.tenant_id`（`:244`），而
   `services/websocket.ts` 的 `connected` 载荷是内联对象 —— 编译器不参与，靠人工核对。
3. **WebSocket 服务端契约缺失**：`NodeEventPayload.zone_id`（`services/websocket.ts:28`）与
   频道名 `zone.{id}.nodes`（`:153/169`，日志里还写成 `private-zone.{id}.nodes`，`:144` 注释）
   在 `mynet_api` **没有任何对应实现**（无 broadcasting channel 声明）。GUI 侧订阅调用是
   单侧发明，所以本步**有意不动**它 —— 改名等于给不存在的功能定契约。
4. **vendor 框架侧的租户级端点 —— 复核后判定「无越权洞」，但口径与本项目不同源**：
   `mynet_api/vendor/dsplat/multi-tenant-saas-module-infrastructure/Routes/api.php`
   的 `tenants/{tenantId}/{modules,webhooks,ip-whitelist,tenant-keys,branding,…}`
   挂的是 **`rbac.permission:setting.view|update`**（`:16/20/26/31/39/43/49/54/61/65/70/74`），
   不是 `domain.console` + `tenant.permission:tenant_admin`。
   本包第一版曾把它记成「只挂 `auth:sanctum` 的越权面」—— **那是错的，已纠**：
   `CheckRbacPermission` 走 `RbacService::check()`，而
   `multi-tenant-saas-module-auth/Services/RbacService.php:41-44` 明写
   **「角色仅属于 Operator；User 不拥有角色，无 RBAC 权限」并直接 `return false`**，
   所以 User 的 Bearer 打进去是 403；`checkOperatorPermission()`（`:79-91`）在
   无租户上下文且非 platform scope 时也 `return false`；控制器侧还有
   `TenantContext::getId()` + `ensureTenantAccess()` 二次校验
   （`WebhookController.php:22-30/32-36`）。
   剩下的不是洞而是**分歧**：本项目 console 面的门禁是
   `auth:sanctum + domain.console + tenant.identify + tenant.permission:tenant_admin`
   （G-3 命名裁定，见 `dev-plan.md` 轨道 G），框架这组端点走的是 `rbac.permission:*` 且
   不声明 `domain.console`。两条门禁口径并存，将来 console 面（任务 G-4 业务页）要不要
   复用这组端点、复用时以哪套为准 —— **待裁决**，本包没动。
   另：`TenantDomainService::getOverview(UserId)`（`app/Modules/VPN/Domain/Services/
   TenantDomainService.php:173`）只按单个 `user.tenant_id` 聚合，跨租户 operator 视角的
   总览口径未定。
5. **cluster B 命名**（原未决 ③）：`services/zone/`、`types/zoneService.ts`、
   `useZoneServices`、`components/zone/`、en-US 的 `zoneServices` / `zoneSharing` /
   `zoneFilter` 三条 —— 指的是**租户内共享的服务**，不是租户实体，正确目标名应是
   `Service*` 而不是 `Tenant*`。等命名裁决。
6. **已删功能留下的死文案 key**（`check-i18n` 不报未使用，所以留着没人发现）：
   `zones.actions.create`、`zones.dialogs.createZone`、`zones.create*` / `update*` /
   `delete*`、`zones.stats.*`、`fetchOverviewError` 等 —— 管理入口随裁定 6 删了，文案没跟着删。
7. **5 个无路由孤儿页面**（原未决 ④，含 `pages/tenant/TenantSharingPage.vue`，
   它内部还有 46 处 zone 引用）、`profile.preferences` 的双语缺失措辞、
   未跟踪文件 `mynet_api/database/sql/migrate-tenant-to-platform.sql`（124 行）处置。

---

## 议题 1：`ZoneListPage` / `ZoneSharingPage` / `/zones` 路由 —— 删掉还是改名保留？

### 现状

- `src/router/routes.ts:38` `/zones` → redirect `/app/zones`；`:227-229` `path: 'zones'` →
  `component: () => import('../pages/zone/ZoneListPage.vue')`（`meta.title` 是「区域管理」）。
- `src/pages/zone/ZoneListPage.vue` 327 行、`src/pages/zone/ZoneSharingPage.vue` 379 行、
  `src/components/common/ZoneSelector.vue` 132 行（挂在 `src/layouts/MainLayout.vue:19`
  `<ZoneSelector />`，是应用外壳的一部分）。
- `ZoneListPage` 走的是 `/tenants` CRUD —— 即**租户管理后台**，是 Playbook §7 P7 里
  「让管理员看到成员、配额、自动组网和诊断」的一部分。
- `ZoneSharingPage` 走服务/共享相关接口（`src/services/zone/` 共 502 行）。

### 影响

- 直接删 = 从 GUI 移除**租户管理界面**和**顶栏租户切换器**。多租户能力在产品面上消失，
  但服务端 `/user/tenants/switch` 还在 —— 变成「后端有能力、前端没入口」。
- 保留改名 = `/zones` → `/tenants`、`ZoneListPage` → `TenantListPage` 等，功能不变。

### 候选方案

| | 方案 | 说明 |
|---|---|---|
| A | **改名保留** | Zone* → Tenant*，路由 `/zones` → `/tenants`（旧路径留一条 redirect 或直接不留）。功能不减。 |
| B | 连页面一起删 | 彻底「干净」，但同时移除租户管理 UI 与顶栏切换器。 |
| C | 删页面、留切换器 | 折中。 |

**推荐 A。** 理由：用户裁定的对象是「把 Zone 作为业务对象」（Playbook §1 明确排除项），
而这些页面承载的是**租户**业务对象，只是名字错了。B 会连带删掉 P7 要求的管理员界面，
超出裁定范围。**不推荐 B**，除非用户明确说租户管理 UI 也不要了。

### 需要谁决定

用户。这是用户可见功能的去留，且与 P7 的交付口径冲突。

---

## 议题 2：`DEFAULT_GLOBAL_ZONE`（`zone_id: 999999999`）怎么办？

### 现状

- `src/config/app.ts:4-21` 硬编码一个假 Zone：`id`/`zone_id: 999999999`（`:5/:6`）、
  `zone_name: 'Global'`（`:7`）、`zone_admin_name: 'System'`（`:11`）、
  `pivot: { user_id: 1, zone_id: 999999999, is_admin: 1 }`（`:17`）。
- `src/layouts/MainLayout.vue:804-808`：当 `availableZones` 为空且已认证时，
  **兜底塞入这个假 Zone 并设为 currentZone**（`authStore.availableZones = [DEFAULT_GLOBAL_ZONE]`）。
- `999999999` 作为**独立字面量**在 `mynet_api` 的 `app/ database/ config/ routes/` 里
  出现 **0 次**（`grep -rnw 999999999` 结果为空）。
  ⚠️ 不加 `-w` 会命中 11 行，但逐行看过全是 16 位 ID 里的子串
  （如 `vpn.php:675 'max' => 9999999999999999`、测试里的 `9999999999999999`），
  没有一处是租户 id。**别用不带 `-w` 的 grep 得出「服务端认识这个值」的相反结论。**

### 影响

- 兜底触发时，GUI 会拿 `X-Tenant-ID: 999999999` 去连 WebSocket、并用 `zone_id: 999999999`
  调各业务接口 —— 指向一个**不存在的租户**。后果取决于服务端如何处理未知租户：
  最好情况是全部 403/404，最坏情况是命中某个默认作用域。
- 它把「租户列表加载失败」这个真故障，伪装成「有一个叫 Global 的租户」，
  用户看到的是空数据而不是错误。这正是 Playbook §9 反对的 fail-open。
- 注意：**服务端确实有一个平台兜底租户，但不是这个值。** SSOT 是
  `mynet_api/config/id.php:51` `'platform_tenant_id' => env('PLATFORM_TENANT_ID', 9007199254740991)`
  （2^53-1，即 JS 安全整数上限），生产代码原在 `SocialDomainService.php:1024` 与 `:1175`（旧 `invitations` 实现，已随 `mynet_api@b79440a` 删除）
  用它兜 `$user->tenant_id` / `$invitation->tenant_id` 为空的场合；
  `InvitationJoinTenantTest.php:30` 也把它钉成 `PLATFORM_TENANT` 常量。
  也就是说 GUI 的 `999999999` 与服务端的平台租户是**两个互不相干的魔法数**，
  前者在服务端没有任何对应物。

### 候选方案

| | 方案 | 说明 |
|---|---|---|
| A | **删掉兜底，让它 fail-closed** | `availableZones` 为空时显示明确错误态，不伪造租户、不连 WS。 |
| B | 改指向真实平台租户 | 用 `config('id.platform_tenant_id')` 的值（默认 `9007199254740991`），与 `SocialDomainService` 的兜底同源。 |
| C | 保持现状只改名 | `DEFAULT_GLOBAL_TENANT`，行为不变。 |

**推荐 A。** 理由：伪造一个服务端不存在的租户 id 去发请求，是把故障藏起来；
Playbook §9 对鉴权与租户隔离明确要求 fail-closed。
**不推荐 B**：把「加载失败」静默映射成「你在平台租户里」是权限语义变化，
比显示错误危险。**不推荐 C**：等于把缺陷原样保留、只换个名字。

### 需要谁决定

用户。A 会让原本「看起来能用」的空态变成显式错误提示，属可感知的行为变化。

---

## 建议的切法（裁决后执行）

必须**原子**推进，任何中间态都不允许只改一边：

1. **第 0 步（可先做，零风险）**：
   - 修 API 侧类别 ③④ 的措辞与 seeder 文案；
   - 给类别 ② 的注释加一句「此处 zone 指 gnb 实例/防火墙 zone，与产品 Zone 无关」，
     防止后来人按 grep 误删；
   - 删 `mynet_gui/src/services/zone/zoneServiceApiWithCache.ts`（187 行）。
     取证：`diff -q` 与 `zoneServiceApi.ts` **逐字节相同**，`services/zone/index.ts`
     只 `export * from './zoneService'` 和 `'./zoneServiceApi'`（没有 WithCache），
     全 `mynet_gui/src` 内 `grep -rn WithCache` **零命中**。是死副本，与两个议题无关。
   - **不改任何字段名。**
2. **第 1 步**：GUI 内部改名（类型 `Zone`→`Tenant`、`currentZone`→`currentTenant`、
   `availableZones`→`availableTenants`、`useZones`→`useTenants`、目录 `services/zone`→
   `services/tenant`、`pages/zone`→`pages/tenant`），但**仍然读 `zone_id`**。
   这一步纯 GUI 内部，不动契约，可独立验证。
3. **第 2 步（契约翻转，一次提交内完成两仓）—— ☑ 已落 2026-10-04**：
   `mynet_api 5aa8f34` + `mynet_gui 716f431`。API 的 `zone_id`/`total_zones`/`active_zones`
   → `tenant_id`/`total_tenants`/`active_tenants`，同时 GUI 改读新名。
   「必须先写真请求用例钉住 `X-Tenant-ID` 的来源字段」这条已照做 ——
   `TenantWireContractTest.php` 钉响应形状，来源字段收成单点（见上「第 2 步落地」）。
   - **本步范围只有 3 处 API 写字段**（`TenantDomainService.php:157`、`UserService.php:104`、
     `TenantDomainService.php:177/186/187` 的统计键）+ 5 处配套注释。**请求参数不在范围内**——
     取证见下条「IP 池的 `zone_id` 查询参数是双端死代码」，别顺手给它改名，那是给不存在的
     功能改接口。
4. **第 3 步**：处理议题 1、议题 2 的裁决结果。☑ 已落（议题 1 = `cf00de3`、议题 2 = `68b273f`）。
   **本包四步全部走完**；剩下的只是上面「第 2 步留下的未处置项」里的 7 条未决，
   那些已不属于「Zone→Tenant」这件事，需要单独裁决或单独建包。

### IP 池的 `zone_id` 查询参数是双端死代码（2026-10-04 补取证）

第 2 步动手前查了一遍「请求方向」有没有 `zone_id`，结论是**有写法、无通路**，两端都是死的：

- **服务端从不读它**：`grep -rn zone_id mynet_api/app/Modules/VPN` 全模块只命中
  `TenantDomainService.php:140`（注释）和 `:157`（响应字段）—— **Controllers 下零命中**。
  `IPPoolController::index`（`:38-39`）与 `::statistics`（`:440-441`）验证的是
  `'tenant_id' => 'sometimes|numeric'`，随后 `$tenantId = TenantId::fromInt(intval($validated['tenant_id'] ?? $currentTenantId))`
  （`:53`、`:447`）。Laravel 的 `validate()` 只校验列出的键，多出来的 `zone_id` 被静默丢弃，
  于是一律回落到 `X-Tenant-ID` 的当前租户。
- **前端从不传它**：`useIPPools.ts:79`、`:176` 的 `const params = zoneId ? { zone_id: zoneId } : {}`
  里 `zoneId` 恒为 `undefined`——全部 10 个调用点（`useIPPools.ts:162/378/379`、
  `useRecommendations.ts:79`、`IPPoolManager.vue:309/310/316/322`、
  `IPPoolCreateDialog.vue:417`、`IpPoolListPage.vue:57`）**都是无参调用**。
- **配套的客户端过滤同样是死的**：`filteredPools`（`:43-48`）按 `filters.zone_id` 过滤，
  而 `filters.zone_id` 全仓只有一处赋值——`:253` 的 `resetFilters()` 把它置 `''`。
  `filters` 虽然从 composable 导出（`:429`），但 4 个消费点
  （`useRecommendations.ts:15`、`IPPoolManager.vue:191`、`IPPoolCreateDialog.vue:179`、
  `IpPoolListPage.vue:51`）没有任何一个取它。
- **所以这不是需要开 CPI 的线上缺陷**（没有用户可见的错误行为，租户隔离由 `X-Tenant-ID`
  正常兜住），是一段从没接通的死代码。第 1 步改名时按原样带着走（`zone_id` → `tenant_id`
  只是换个死名字），**不要**在第 2 步里把它「修好」——那等于自己发明一个未经裁决的
  跨租户查询能力。

规模参考（均为 `grep -rni zone` 实测，2026-10-04）：
GUI `src/` 46 文件 / 1041 行；API `app/ database/ routes/ config/` 9 文件 / 22 行，
其中**真改名只有 6 处响应字段 + 5 处配套注释**，7 处必须原样保留，1 处是 `timezone` 误报。

---

## 本包没有做的事

- **裁决前**没有改任何代码、没有改名、没有删任何文件。裁决后的落地情况逐条记在
  开头「## 裁决与执行」表里（**四步全部已提交**：第 0 步、议题 2、第 1 步、第 2 步契约翻转、
  议题 3 用词 + 其下 4/5/6 三条追加裁定）。
- **界面全程未实测**：第 2 步的 GUI 半边只有 eslint / check-i18n / `npm run build` + grep 背书，
  本仓零测试用例（详见上「验证」节的四条盲区）。网络区切换、选择器、一键建节点三条路径
  **没有点过**。
- 没有对生产 API 或真节点发请求；`X-Tenant-ID: 9007199254740991` 的实际服务端行为**未实测**
  （缺外部条件 1 条：需要能连生产或起本地全栈）。裁决 B 的依据是用户口述「现在生产端在用」，
  加上 `config/id.php:51` 的默认值与之相同，**不是**实测。
- `mynet_web`（在建骨架）里另有 1 个文件 3 行 zone 引用：
  `src/pages/local/Dashboard.vue:56`（i18n 标签 `t('zone')`）、`:57`
  （`auth.currentZone?.zone_name`）、`:165`（`if (auth.currentZone)`）。
  将来两仓合并时会带上，本包未处理。
- **议题 2 落地后新冒出的 `pivot.is_admin: 1` 权限语义未裁决**（见上文专节），本轮按原样保留。
- CPI-69 明确**压后**，等第 2 步落地再改，避免和改名批次撞车。

### 第 3 项（网络区）落地后留下的待裁决清单（4 条 → 2 条已裁，2 条仍未决）

1. **en-US 用词**：~~英文界面仍显示 "Zone"~~ **已裁 = Network，已落**（`mynet_gui 716f431`，
   只改值不改 key）。原取证：英文界面曾显示 "Zone"（`en-US.ts` 的 `switchZone: 'Switch Zone'` 等），
   用户第一轮只裁了中文措辞。
2. **LocalStorage key `current_zone`**：~~保持死名字 / 双读迁移~~ **已裁 = 改名 + 迁移，已落**
   （`mynet_gui 716f431`）。新键 `current_network` 只存 tenant_id，旧键双读后删除；
   迁移路径见 `stores/auth.ts:21-45`。用户裁定原话：「不许静默丢用户已选网络区」。
3. **cluster B 的归属命名**：`services/zone/`、`types/zoneService.ts`、`useZoneServices`、
   `ZoneService` 指的是**租户内共享的服务**，不是租户实体，所以第 1 步没跟着改名，
   `services/zone/index.ts` 里留了边界说明。它们调的全是 `/services/*` 端点，该不该改叫
   `services/service/` 属另一轮命名裁决。**仍未决**（en-US 的 `zoneServices`/`zoneSharing`/
   `zoneFilter` 三条也挂在这上面）。
4. **无路由孤儿页面**（含 `pages/tenant/TenantSharingPage.vue`）共 5 个：没挂在任何路由上，
   但仍在 lint/build 覆盖范围内。改名时按 A「功能不减」一并搬到了 tenant 目录并保持了原状，
   去留**仍未决**。
