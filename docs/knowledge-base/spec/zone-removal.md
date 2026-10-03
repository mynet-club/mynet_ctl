# Zone 删除 — 决策包（**已裁决**）

> **2026-10-04 用户裁决：议题 1 = A（改名保留）；议题 2 = B（改指真实平台租户
> `9007199254740991`，用户注明「现在生产端在用」）。**
> 用户同时确认了本包的核心判断：「zone 就是 tenant，防火墙那个 zone 是另外一回事，
> 从这个上来看，确实不能粗暴说删 zone」。
> 下面保留裁决前的取证与候选方案原文；落地情况见「## 裁决与执行」。

用户裁定是「干净，可以删掉」。动手前做了一轮取证，结论是：**这个任务的性质和裁定时以为的不一样。**
它不是「删掉一个叫 Zone 的业务对象」，而是「把一层名字换掉 + 处理两件残留物」。
按原口径盲删会出两类事故，见下。

---

## 裁决与执行

| 议题 | 裁决 | 落地 |
|---|---|---|
| 1 `ZoneListPage`/`ZoneSharingPage`/`/zones`/`ZoneSelector` | **A 改名保留** | Zone* → Tenant*，路由 `/zones` → `/tenants`，功能不减。执行中（第 1、2 步）。 |
| 2 `DEFAULT_GLOBAL_ZONE`（`999999999`） | **B 改指真实平台租户** | 已执行：`mynet_gui` `68b273f`，提出 `PLATFORM_TENANT_ID = 9007199254740991` 常量替掉四处魔法数，与 `mynet_api config/id.php:51 platform_tenant_id` 同源。**兜底行为按 B 保留**，没有改成 A 的显式错误态。 |
| — 第 0 步零风险清理 | — | 已执行：API 侧 7 处「另一个 zone」注释加防误删标记、③④ 类文案清理（`mynet_api` `df6c4da`）；删 GUI 死副本 `zoneServiceApiWithCache.ts`（`mynet_gui` `44f389d`）。 |

### 议题 2 落地后**新出现**的一个未决项（没有自行处置）

`DEFAULT_GLOBAL_ZONE.pivot.is_admin: 1` 是当年伪造 `999999999` 时一起编的。改指真实
平台租户之后，它等于在客户端断言「当前用户是平台租户管理员」。

- 读取点：`useZones.ts:39`（`isZoneAdmin`）、`:234`（`canManageZone`）、
  `ZoneListPage.vue:101`（`v-if="zone.pivot.is_admin === 1"`）—— 决定是否渲染管理入口。
- **不是提权**：真鉴权在服务端，客户端这个标志只能让按钮显示出来，点了照样 403。
- 但危害比原先大：原先指向一个不存在的租户，整个对象都是废的；现在指向真实租户，
  一个不属于平台租户的用户会看到「我是这里的管理员」。
- 候选：① `is_admin: 0`（保守，兜底租户只读）② 保持 1 ③ 删掉整个兜底（= 原议题 2 的 A）。
  属权限语义，**等裁决**，本轮按原样保留。

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
  （2^53-1，即 JS 安全整数上限），生产代码在 `SocialDomainService.php:1024` 与 `:1175`
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
3. **第 2 步（契约翻转，一次提交内完成两仓）**：API 的 `zone_id`/`total_zones`/`active_zones`
   → `tenant_id`/`total_tenants`/`active_tenants`，同时 GUI 改读新名。
   必须先写一条**真请求**用例钉住 `X-Tenant-ID` 的来源字段，否则 WS 断裂无人知晓。
   - **本步范围只有 3 处 API 写字段**（`TenantDomainService.php:157`、`UserService.php:104`、
     `TenantDomainService.php:177/186/187` 的统计键）+ 5 处配套注释。**请求参数不在范围内**——
     取证见下条「IP 池的 `zone_id` 查询参数是双端死代码」，别顺手给它改名，那是给不存在的
     功能改接口。
4. **第 3 步**：处理议题 1、议题 2 的裁决结果。

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
  开头「## 裁决与执行」表里（第 0 步与议题 2 已提交，第 1、2 步待做）。
- 没有对生产 API 或真节点发请求；`X-Tenant-ID: 9007199254740991` 的实际服务端行为**未实测**
  （缺外部条件 1 条：需要能连生产或起本地全栈）。裁决 B 的依据是用户口述「现在生产端在用」，
  加上 `config/id.php:51` 的默认值与之相同，**不是**实测。
- `mynet_web`（在建骨架）里另有 1 个文件 3 行 zone 引用：
  `src/pages/local/Dashboard.vue:56`（i18n 标签 `t('zone')`）、`:57`
  （`auth.currentZone?.zone_name`）、`:165`（`if (auth.currentZone)`）。
  将来两仓合并时会带上，本包未处理。
- **议题 2 落地后新冒出的 `pivot.is_admin: 1` 权限语义未裁决**（见上文专节），本轮按原样保留。
- CPI-69 明确**压后**，等第 2 步落地再改，避免和改名批次撞车。
