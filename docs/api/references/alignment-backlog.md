# API 对齐待办清单（Alignment Backlog）

> 更新：2026-09-21 | 来源规划：`integration-plan-v2.md` §3 W3
> **基准（SSOT）**：`../openapi/back.yaml`、`../openapi/ctl.yaml`、`client-matrix.md`
> **偏差来源**：`issues.md`、`../AUDIT_2026-04-24/`
> **本文件是 W3 的唯一执行清单** —— 修复后回填状态，不在别处另开清单。

---

## 0. 复核结果（2026-09-21 对代码实测）

> ⚠️ `issues.md` 是 **2026-04-24** 的，代码已前进：`zone → tenant` 更名、GUI v1→v2 迁移、config-bundle 加固。
> 下表为**逐条对代码复核**的结论，**以本节为准**；§2 原始清单保留作来源追溯。

| ID | 声称问题 | 复核 | 证据 |
|----|----------|------|------|
| B1 | lua 默认配置写死 `/api/v1` | ✅ **已修** | `mynet_openwrt/luasrc`、`mynet_lua/luasrc` 中仅 `api.lua:147` 注释提及 v1；`validator.lua` 无硬编码 |
| B2 | config-bundle 缺 zone 鉴权 | ✅ **已修** | `configBundle()` 以 `$user->tenant_id` + `$user->user_id` 收口（`NodeConfigController.php:912-917`） |
| B3 | router-keys 静默失败 | ✅ **已修** | `collectKeys()` 逐个跟踪失败并产出 `warnings[missing_peer_key]`（`ConfigBundleService.php:146-186`） |
| M3 | 批量设备信息用 `PUT /nodes/deviceinfo` | ✅ **已修** | 路由已是 `POST /nodes/deviceinfo`（`app/Modules/VPN/routes/api.php:84`） |
| M4 | keys generate/upload 分开、走 v1 | 🟡 **部分** | 有 `POST/GET/DELETE /nodes/{id}/keys`（:87-89），**无 PUT 上传**语义 |
| N1 | `GET /zones/overview` 缺失 | 🔴 **仍缺** | 无 `/tenants/overview`（zone 已更名 tenant） |
| N2 | `GET /zones/{id}/statistics` 缺失 | 🔴 **仍缺** | 无 `/tenants/{id}/statistics`；相邻 `GET /users/{id}/statistics` 存在（:189） |
| N3 | `GET /users/{id}/ip-resources` 缺失 | ✅ **已修（异路径）** | 实现为 `GET /ippools/user-resources`（:125）→ **改 GUI 路径**，非 back 补端点 |
| F1-F10 | GUI v1→v2 命名 | ✅ 已修 | `/services/*` 已为扁平动作路径（:133-146） |
| DOC GAP 43 | OpenAPI 未声明 | ⏳ **待复核** | 需逐条比对 `openapi/back.yaml` |
| 孤儿 69 | 零调用端点 | ⏳ **保留** | 其中 `/monitor/node/*`、`/ippools/*` 实为 agent / lua 在用 |

**结论**：原定「P0 修 back 安全漏洞（B2/B3）」**无需动代码，已修**。真正待办收敛为：

1. **doc 侧**：A1-A5（agent/ui 进矩阵与版本表）+ DOC GAP 43 复核
2. **back 侧**：N1/N2（视 GUI 是否仍需要）、M4 的 keys 上传语义
3. **agent 侧**：M1/M5（客户端字段，属 `mynet_agent`）
4. **下一批实现**：W2 driver 接口壳（`mynet_agent`）

---

## 0.2 第二批复核（2026-09-21，并行侦察实测）

### 已实现（本轮，`mynet_back` 分支 `feat/integration-api-fixes`）

| ID | 动作 | commit |
|----|------|--------|
| M4 | 新增 **幂等** `PUT /nodes/{id}/keys`（替换语义，不因已存在而报错；未传 custom_* 且已有密钥时原样返回） | `f46a18b7` |
| N1 | 新增 `GET /tenants/overview` → `{total_zones, active_zones, total_nodes, total_routes}` | `34eab809` |
| N2 | 新增 `GET /tenants/{id}/statistics` → `{zone_id, node_count, active_node_count, route_count, ip_pool_count, user_count}` | `34eab809` |

> ⚠️ **前端仍需改路径**：`mynet_gui` 现在调 `/zones/overview`、`/zones/{id}/statistics`，而 back 只有 `/tenants/*`。见 §0.3 G1。

### 新发现的真实缺陷

| ID | 位置 | 问题 | 严重度 |
|----|------|------|--------|
| **X1** | `mynet_back` `app/Modules/VPN/routes/api.php:198-205` | 使用 `BinaryAssetController`，但该类**全库不存在且未 import** → 命中 `/assets/*` 即 500 | 🔴 高 |
| **X2** | `mynet_ctl` `openapi/ctl.yaml` | `/admin` 24 个 + 6 个公开页面 + 3 个 `/api/v2` raw 下载端点共 **33 条**未声明；`/d/` 完全同步 | 🟡 中 |
| **X3** | `mynet_back` `openapi/back.yaml` | 声明了 15 组 `/zones*` 端点，代码里**不存在**（已更名 `/tenants*`、`/services/*`）→ OpenAPI 有"僵尸"端点 | 🟡 中 |

### 关键机制发现（影响后续所有路径判断）

| 发现 | 说明 |
|------|------|
| **`/api/v2` 是重写别名** | VPN 模块原生前缀是 `/api/v1`（`VpnServiceProvider.php:47-49`）；`RewriteApiVersion` 中间件（`app/Http/Middleware/RewriteApiVersion.php:14-20`，`bootstrap/app.php:39` prepend）把 `/api/v2/*` 重写为 `/api/v1/*`。**注释里写的 "v1 only" 是真实的。** |
| **DOC GAP 实际为 60，不是 43** | 代码侧 147 个 (method,path)，OpenAPI 仅声明 95。缺口集中在 monitor/users/nodes/notifications/assets/ids/route/ippools/mynet/channels |
| **Zone → Tenant 更名是全局的** | 所有 `/zones*` 路径在代码中已不存在；OpenAPI 与旧 audit 仍按 zone 记名 |

### W2（driver 抽象）范围修正 ⚠️

对 `mynet_agent` 实测后，W2 比 `spec/driver-abstraction.md` 假设的**大得多**：

| 设计稿假设 | 代码实际 |
|------------|----------|
| 热重载存在（`gnb_ctl -w core.flag=2`） | **不存在**。全仓库无该调用；只有 `mynetd` 对自身 config 的 SIGHUP（`cmd/mynetd/main.go:152,174`） |
| 只有一个 driver 实现待扩展 | `GNBDriver` 仅只读（`internal/infrastructure/gnb_driver.go`）；工厂对 wireguard 直接返回 "TODO v0.10.0"；`driver/wireguard/` 空目录 |
| VPN 类型抽象单一 | **三套常量并存**：`domain.VPNDriverType`、`node.VPNTypeWireGuard`、`common.NodeTypeWireGuard` |
| conf 路径集中 | `driver/gnb/conf/{nodeID}` 硬编码散落 ~10 个文件 |
| gnb 生命周期单点 | **三条并存**：`GNBRunner`(PID 文件) / `Manager`(内存 PID) / 系统服务（systemd 等） |

**处理**：W2 拆为两步——先「立接口 + 划边界」（不动行为），再「收敛三条生命周期 + 两套常量」。详见 `spec/driver-abstraction.md` 后续修订。

---

## 0.3 下一批任务

| ID | 事项 | Owner |
|----|------|-------|
| G1 | GUI 把 `/zones/*` 调用改为 `/tenants/*`（overview、statistics、user/zones→user/tenants） | mynet_gui |
| X1 | 修复 `BinaryAssetController` 缺失：补类或移除 `/assets/*` 路由 | mynet_back |
| X3 | `back.yaml` 清理 15 组僵尸 `/zones*`，按 `/tenants*` 重写 | mynet_ctl docs |
| X2 | 补 `ctl.yaml` 的 33 条（或显式声明 `/admin`、页面不在 API 规范范围） | mynet_ctl docs |
| T3 | `back.yaml` 增量补 60 个端点（DOC GAP 修正后） | mynet_ctl docs |

---

## 0.4 GUI 调用面实测（2026-09-21）

`mynet_gui` 去重后实际发起 **91** 个 (method, path)，其中 **25 个后端不存在（真实阻塞）**。

阻塞高度集中，两类占 24/25：

| 类别 | 数量 | 处理 |
|------|------|------|
| **zone→tenant 未迁移** | 9 | GUI 改路径（见 G1）：`/zones`→`/tenants`、`/user/zones`→`/user/tenants/`、`/user/switch-zone`→`/user/tenants/switch`、`/zones/services/indexes`→`/services/indexes`、`/zones/{id}/statistics`→`/tenants/{id}/statistics` |
| **IPPool 契约漂移** | 7 | GUI 调 `PUT /ippools/{id}`、`/ippools/{id}/allocate|release|allocations|check-ip`、`/ippools/batch`、`/ippools/export` → back 只有 `auto-allocate`/`check-availability`/`DELETE /ippools/{sequence}`/`user-resources`。需逐条对齐（改 GUI 或补 back） |

其余零散：`GET /monitor/history/connections`、`POST /route/connect/{nodeId}`、`GET /route/{id}/stats`、`GET /social/friend-requests`、`DELETE|PATCH /nodes/{id}/keys/{keyId}`。

### 🔴 崩溃级缺陷（非路由缺失，是调用方式错）

| 位置 | 问题 |
|------|------|
| `mynet_gui` `src/services/node/configBundleService.ts:30,109` | 调用 `apiService.get(...)` / `apiService.post(...)`，但 `apiService`（`src/services/api.ts:174`）**没有 get/post 方法** → 运行时必然 `TypeError`。端点本身 back 都有 |

### dead code（有封装、无调用方）

`services/ippool/ipPoolService.ts`、`services/ippool/mockIPPoolService.ts`、`services/zone/zoneServiceApiWithCache.ts`、`services/node/configBundleService.ts`（经 `useConfigBundle` 转发但无消费者）、`composables/useVpnConfig.ts`、`composables/useSidebarStats.ts:3`

> 附带：`zoneService.getUserZones`(`:58`) 与 `api.ts` 的 `getZones`(`:444`→`/user/tenants/`) 是两条并行实现，前者为旧路径。

---

## 0.5 下一批任务修订

| ID | 事项 | Owner | 优先级 |
|----|------|-------|--------|
| **C1** | 修 `configBundleService.ts` 的 `apiService.get/post` 崩溃 | mynet_gui | 🔴 P0 |
| G1 | GUI zone→tenant 路径迁移（9 条） | mynet_gui | 🔴 P0 |
| X1 | 修 `BinaryAssetController` 缺失（`/assets/*` 500） | mynet_back | 🔴 P0 |
| P1 | IPPool 契约对齐（7 条） | mynet_gui / back | 🟠 P1 |
| X3 | `back.yaml` 清理僵尸 `/zones*` | mynet_ctl docs | 🟠 P1 |
| X2+T3 | 补 OpenAPI（back 60 + ctl 33） | mynet_ctl docs | 🟡 P2 |

---

## 0.6 第三批执行结果（2026-09-21）

| ID | 结果 | commit |
|----|------|--------|
| **C1** | ✅ 已修。且实测确认该路径**无调用方**（`useConfigBundle` 只在 `index.ts` 再导出）→ **潜在 bug，非现网故障** | `mynet_gui 54af29c` |
| **G1** | ✅ 10 处路径迁移 + **payload key 修正**：`/user/tenants/switch` 校验 `tenant_id`，GUI 原来发 `zone_id`——只改 URL 仍会 422 | `mynet_gui da1a070` |
| **X1** | ✅ 移除 `/assets/*`。类全库不存在且**无 `class_alias` 桥**（`App\Models\*` 指的是框架真实 `app/Models/*`，不是 Modules 别名），命中即 `BindingResolutionException` 500；前端已改用 `/files/entity/*` | `mynet_back 0a3ef65c` |
| **X3/T17** | ✅ 13 个僵尸路径改名：`/zones*`→`/tenants*`、`/zones/services/*`→`/services/*`（back 中服务是**独立 prefix 组**，不是 tenants 子路径）。pyyaml 校验通过，78 paths，`/zones*` 清零 | `mynet_ctl 7dac269` |
| W2 设计稿 | ✅ 按实测修订：**热重载代码根本不存在**（仅 daemon 自身 SIGHUP）、`GNBDriver` 仅只读、wireguard 空目录、三套 VPN 常量、conf 路径散落 ~10 文件、gnb 三条生命周期 → 补 S1-S7 分步 | `mynet_ctl 9f801a9` |

### ⚠️ T21（IPPool）结论被推翻：不是契约漂移，是**死代码**

原判「GUI 有 7 条 IPPool 路径与 back 不匹配」不成立。实测：

| 发现 | 细节 |
|------|------|
| 服务层整片死掉 | `services/ippool/ipPoolService.ts`、`mockIPPoolService.ts`、`index.ts` **零引用**（barrel 无人 import） |
| 组件也是死的 | `IPPoolDialog.vue`、`IPPoolDetailsDialog.vue`、`IPPoolCard.vue` **零引用** |
| 唯一活链 | `IpPoolListPage.vue` → `IPPoolManager.vue` → `useIPPools`，其 4 个调用点（`GET /ippools`、`GET /ippools/statistics`、`POST /ippools`、`DELETE /ippools/{sequence}`）与 back **已一一对应** |
| 语义陷阱 | `release`（单 IP）**≠** `DELETE /ippools/{sequence}`（整池）；`check-ip`(GET) **≠** `check-availability`(POST，且是恒返回 true 的 TODO 桩) |

**正确动作 = 删死代码**，不是补 back 端点。另建议把 `autoAllocatePool: createPoolAdvanced` 这个别名改名为 `createPools`（现名易误读为 `/ippools/auto-allocate`）。

### 本批遗留

| ID | 事项 |
|----|------|
| ZoneId 参数清理 | `$ref: "#/components/parameters/ZoneId"`（X-Zone-ID 头）在 back.yaml 被引用 **82 处**，但 back 路由已**无 `api.zone` 中间件**。属于全文件级修正，勿只改局部 |
| `/user/tenants/current` | back 有 `GET /user/tenants/current`，OpenAPI 未声明 |
| operationId 重复 | `searchUsers` 在 back.yaml 出现两次（**既有问题**，非本批引入） |

---

## 0.7 T18 定稿对账（2026-09-21）

| 项 | 数 |
|----|----|
| 代码侧 (method,path)：VPN 142 + channels 2 + 框架 Auth 5 | **147** |
| OpenAPI 声明（T17 改名后） | **95** |
| A · 代码有 / OA 无 | **55** |
| 其中属「重命名/别名类」（非真缺口） | 6 |
| **真缺口** | **49** |
| B · OA 有 / 代码无 | 6（真僵尸仅 `/assets/avatar/upload`；其余 5 条 `/auth/*` 由框架 MultiTenantSaas 提供，属**误报**，应在 spec 标注 framework-owned） |

**6 条重命名/别名类**：`GET /users/profile`(≈`GET /user`)、`GET /user/tenants/current`、`POST /nodes/{id}/config`(≈PUT)、`PUT /nodes/{id}/keys`(OA 有路径缺 PUT verb)、`PATCH /route/{id}`(≈PUT)、`PATCH /tenants/{id}`(≈PUT)

**本批已补（commit `8a96d08`）**：`/user/tenants/current`、`/tenants/overview`、`/tenants/{id}/statistics`；并移除僵尸 `/assets/avatar/upload`。

**余量（55 − 3 = 52 条）**：

| 组 | 条数 | 端点 |
|----|------|------|
| monitor | 7 | `GET /monitor/node/{info,config,routes,full-config,route-config}`、`GET /monitor/index-services`、`POST /monitor/router-keys` |
| users | 11 | `GET /users`、`/users/my/statistics`、`/users/tips/suggestions`、`GET\|PUT\|DELETE /users/{id}`、`GET /users/{id}/{statistics,activity,permissions,nodes,tenants}` |
| notifications | 8 | 通知中心全套：`GET /notifications[,-unread-count,-stats]`、`POST /{id}/read`、`/read-all`、`/batch-delete`、`/clear-read`、`DELETE /{id}` |
| nodes | 6 | `GET /nodes/{id}/commands`、`DELETE /nodes/commands/{id}`、`DELETE /nodes/{id}/ips`、`GET /nodes/{id}/{peer-connections,connection-stats}` + `POST /nodes/{id}/config` |
| ids | 6 | `/ids/{generate,generate/batch,validate,parse,formats,statistics}` |
| route | 2 | `GET /route/statistics`、`GET /route/requests` |
| ippools | 2 | `POST /ippools/check-availability`、`GET /ippools/user-resources` |
| services | 2 | `GET /services/show/{id}`、`POST /services/request-approval/{id}` |
| tenants | 2 | `PATCH /tenants/{id}` + 相关 |
| mynet | 2 | `GET /mynet/{stats,health}` |
| channels | 2 | `POST /channels/{enterprise-wechat,wechat-official}/webhook` |

**纳入策略建议**：
- `/monitor/*`：**纳入**并加 HMAC 安全方案（同属签名通道，OA 已声明其中 `heartbeat`、`commands/{id}/result` 两条）
- `/channels/*`：**纳入**但用独立 `webhooks` tag 标注 no-auth / internal，**不生成客户端 SDK**
- `/auth/*` 框架端点：标注 `framework-owned`，避免下次审计再次误报为僵尸

---

## 1. 归属重映射（旧线 → 新线）

v1 的 issue 清单按 `lua / client / GUI` 记名，需按目标线重新归属：

| 旧 owner | 新 owner | 说明 |
|----------|----------|------|
| `mynet_lua` 的**业务**问题 | **mynet_agent** | 业务迁入 mynetd；Lua 只留壳 |
| `mynet_lua` 的**壳/渲染**问题 | **mynet_openwrt** | LuCI 菜单/模板/ACL |
| `mynet_client` (Node) | **mynet_agent**（`mynet` CLI） | 产品线退役，CLI 能力在 agent |
| `GUI` | **mynet_gui** | 不变 |
| `back` / `ctl` | **mynet_back** / **mynet_ctl** | 不变 |

---

## 2. 待办清单

### 2.1 BUG（必须修）

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| B2 | **mynet_back** | config-bundle 缺严格 `zone_id` 鉴权（可越 Zone） | 加 Zone 归属校验 | 🔴 P0 | open |
| B3 | **mynet_back** | router-keys 失败静默继续 → 缺 peer 公钥 | 失败上报 + 进 `warnings` | 🔴 P0 | open |
| B1 | **mynet_openwrt** | 默认配置写死 `/api/v1`，应 `/api/v2` | 改默认值（若由 mynetd 下发则归 **agent**） | 🟠 P1 | open |

### 2.2 字段/契约不匹配

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| M1 | **mynet_agent** | router-keys 发空 body `{}` | 按契约发 `{router_node_ids:[int]}` | 🟠 P1 | open |
| M5 | **mynet_agent** | router-keys 期望 `{peerId:"hex"}` 扁平格式 | 适配 back 的 `{keys:[{router_node_id,public_key}]}` | 🟠 P1 | open |
| M2 | **mynet_agent** | keys/upload 发 `custom_public_key` | 确认 v2 是否仍接受该字段名 | 🟡 P2 | open |
| M3 | **mynet_gui** | 批量设备信息用 `PUT /nodes/deviceinfo` | 改 `POST` | 🟠 P1 | open |
| M4 | **mynet_gui** | keys generate/upload 分开调 v1 路径 | 改用 v2 合并语义 | 🟡 P2 | open |

### 2.3 BLOCKER（GUI 在调、back 无路由）

| ID | Owner | 端点 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| N1 | **mynet_back** | `GET /zones/overview` | 实现或从 GUI 移除 | 🟠 P1 | open |
| N2 | **mynet_back** | `GET /zones/{id}/statistics` | 同上 | 🟠 P1 | open |
| N3 | **mynet_back** | `GET /users/{id}/ip-resources` | 同上 | 🟡 P2 | open |

### 2.4 新线新增（文档覆盖缺口）

这些是目标线引入后**矩阵/规范里还没有的**：

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| A1 | mynet_ctl/docs | `client-matrix.md` 无 **agent** 行 | 补 agent 的用户面调用（back `/api/v2/*`） | 🔴 P0 | open |
| A2 | mynet_ctl/docs | `client-matrix.md` 无 **ui** 行 | ui 只调**本机** agent API，不直连 back —— 需在矩阵中明确标注 | 🔴 P0 | open |
| A3 | mynet_ctl/docs | agent 本机 API（`/api/v1`）无规范文件 | 以 `mynet_agent/docs/API.md` 为源，落 `spec/local-api.md` | 🟠 P1 | open |
| A4 | mynet_ctl/docs | `version.json` 无 agent/ui/desktop/openwrt | 补四行（见 W1-2） | 🔴 P0 | open |
| A5 | mynet_ctl/docs | `deployment-registry.md` 无 desktop/openwrt 条目 | 补打包/分发条目 | 🟡 P2 | open |

### 2.5 文档缺口（DOC GAP，back 一致但 OpenAPI 未声明）

43 个端点（`/auth` 8、`/ippools` 7、`/nodes` 4、`/social` 13、`/users` 2、`/zones` 5、`/assets` 1）。
详见 `../AUDIT_2026-04-24/04_diff_matrix.md` §2。

**动作**：增量补 `openapi/back.yaml`，使 OpenAPI 覆盖 GUI 实际调用面。**Owner: mynet_ctl/docs**。

### 2.6 后端孤儿（back 有实现、无客户端调用）

69 个端点（`/notifications/*`、多数 `/users/*`、`/ids/*`、`/monitor/node/*`、`/assets/*`）。

**动作**：**保留**（lua/旧的 monitor 端点仍可能被 agent 使用）；确认废弃后再清理。**Owner: mynet_back**。

### 2.7 GUI dead code（前端有调用、功能未上线）

D1-D7（config 高级管理 9 个、ippool 高级 7 个、route 5 个、keys 管理、i18n 等）。

**动作**：标记 TODO 或清理。**Owner: mynet_gui**，P3 处理。

### 2.8 已修复（留档）

F1-F10：GUI v1→v2 命名迁移，已完成（2026-04-24）。不重复处理。

### 2.9 建议改进

| ID | Owner | 建议 | 优先级 |
|----|-------|------|--------|
| S1 | mynet_agent / openwrt | 弃 4 次独立 API，统一用 config-bundle | 🟡 P2 |
| S2 | mynet_agent | 实现心跳上报（HMAC 签名已有） | 🟠 P1 |
| S3 | mynet_agent | 确认是否需 switch-zone 调用 | 🟡 P2 |
| S4 | 全端 | 启动时按 `version.json` 做 API 版本协商 | 🟠 P1 |

---

## 3. 统计与执行顺序

| Owner | 待办数（不含已修） |
|-------|-------------------|
| mynet_back | B2,B3,N1,N2,N3 + DOC GAP 43 + 孤儿确认 69 |
| mynet_ctl/docs | A1,A2,A3,A4,A5 + DOC GAP 补文档 |
| mynet_agent | B1(可能),M1,M2,M5,S1,S2,S3 |
| mynet_gui | M3,M4,D1-D7 |
| mynet_openwrt | B1(壳侧),A5 |

**执行顺序（P1 起）**：

1. 🔴 **安全优先**：B2（越 Zone 漏洞）
2. 🔴 **本规划 P0**：A1-A4（矩阵/版本表补 agent/ui，属 W1 交付）
3. 🔴 **功能正确**：B3（peer key 缺失）
4. 🟠 **契约统一**：M1,M3,M5 + S4（版本协商）
5. 🟠 **端点补齐**：N1-N3
6. 🟡 **文档完整**：DOC GAP 43 补 OpenAPI、A3 本机 API 规范
7. 🟡 **清理**：D1-D7、孤儿确认
