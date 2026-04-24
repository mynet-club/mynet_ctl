# P4 — 三方对账矩阵 (GUI ↔ BACK ↔ CTL OpenAPI)

> 数据源：
> - GUI：`01_gui_calls.md` (128 端点)
> - BACK：`02_back_routes.md` (152 个 v2 路由方法组合)
> - CTL：`03_ctl_endpoints.md` (52 个 OpenAPI 声明)

---

## ❌ 1. BLOCKER — GUI 调用但 Back 路由不匹配

**共 45 个**。这是当前页面 404/405 的根因。

| GUI METHOD | GUI PATH | Sources | Back 实际支持的 method |
|---|---|---|---|
| POST | `/config/validate` | services/node/nodeConfigService.ts:187 | **路径不存在** |
| GET | `/config/vpn-templates` | services/node/nodeConfigService.ts:213 | **路径不存在** |
| GET | `/config/vpn-templates/{id}` | services/node/nodeConfigService.ts:224 | **路径不存在** |
| GET | `/i18n/{id}` | services/api.ts:528 | **路径不存在** |
| POST | `/ippools/batch` | composables/useIPPools.ts:376, services/ippool/ipPoolService.ts:77 | **路径不存在** |
| GET | `/ippools/export` | composables/useIPPools.ts:397, services/ippool/ipPoolService.ts:86 | **路径不存在** |
| PUT | `/ippools/{id}` | composables/useIPPools.ts:176, services/ippool/ipPoolService.ts:25 | DELETE, GET |
| POST | `/ippools/{id}/allocate` | composables/useIPPools.ts:215, services/ippool/ipPoolService.ts:44 | **路径不存在** |
| GET | `/ippools/{id}/allocations` | composables/useIPPools.ts:250, services/ippool/ipPoolService.ts:56 | **路径不存在** |
| GET | `/ippools/{id}/check-ip` | composables/useIPPools.ts:268, services/ippool/ipPoolService.ts:62 | **路径不存在** |
| POST | `/ippools/{id}/extend` | composables/useIPPools.ts:623 | **路径不存在** |
| POST | `/ippools/{id}/release` | composables/useIPPools.ts:233, services/ippool/ipPoolService.ts:50 | **路径不存在** |
| GET | `/monitor/history/connections` | pages/route/ActiveRoutesPage.vue:314, pages/route/ActiveRoutesPage.vue:403 (+2) | **路径不存在** |
| POST | `/nodes` | services/api.ts:492 | GET |
| POST | `/nodes/{id}/config/apply-template` | services/node/nodeConfigService.ts:237 | **路径不存在** |
| GET | `/nodes/{id}/config/compare/{id}` | services/node/nodeConfigService.ts:274 | **路径不存在** |
| GET | `/nodes/{id}/config/download` | services/node/nodeConfigService.ts:174 | **路径不存在** |
| GET | `/nodes/{id}/config/export` | services/node/nodeConfigService.ts:287 | **路径不存在** |
| GET | `/nodes/{id}/config/history` | services/node/nodeConfigService.ts:250 | **路径不存在** |
| POST | `/nodes/{id}/config/import` | services/node/nodeConfigService.ts:300 | **路径不存在** |
| POST | `/nodes/{id}/config/preview` | services/node/nodeConfigService.ts:163 | **路径不存在** |
| POST | `/nodes/{id}/config/reset` | services/node/nodeConfigService.ts:199 | **路径不存在** |
| POST | `/nodes/{id}/config/restore/{id}` | services/node/nodeConfigService.ts:263 | **路径不存在** |
| DELETE | `/nodes/{id}/keys/{id}` | services/node/nodeKeysService.ts:92 | **路径不存在** |
| PATCH | `/nodes/{id}/keys/{id}/status` | services/node/nodeKeysService.ts:104 | **路径不存在** |
| GET | `/route/active` | services/route/routesService.ts:14 | **路径不存在** |
| POST | `/route/connect/{id}` | services/route/routesService.ts:32 | **路径不存在** |
| GET | `/route/connections/{id}` | services/route/routesService.ts:20 | **路径不存在** |
| GET | `/route/friend-nodes` | services/route/routesService.ts:26 | **路径不存在** |
| GET | `/route/{id}/stats` | services/route/routesService.ts:70 | **路径不存在** |
| GET | `/routes` | services/api.ts:505 | **路径不存在** |
| POST | `/routes` | services/api.ts:509 | **路径不存在** |
| DELETE | `/routes/{id}` | services/api.ts:517 | **路径不存在** |
| PUT | `/routes/{id}` | services/api.ts:513 | **路径不存在** |
| GET | `/users/{id}/ip-resources` | composables/useIPPools.ts:359, services/ippool/ipPoolService.ts:95 | **路径不存在** |
| GET | `/zones/overview` | services/zone/zoneService.ts:90 | **路径不存在** |
| GET | `/zones/services` | services/zone/zoneServiceApiWithCache.ts:23, services/zone/zoneServiceApi.ts:23 | **路径不存在** |
| POST | `/zones/services` | services/zone/zoneServiceApiWithCache.ts:106 | **路径不存在** |
| GET | `/zones/services/indexeservices` | services/zone/zoneService.ts:123 | **路径不存在** |
| GET | `/zones/services/{id}` | services/zone/zoneServiceApiWithCache.ts:93, services/zone/zoneServiceApi.ts:93 | **路径不存在** |
| POST | `/zones/services/{id}/approve` | services/zone/zoneServiceApiWithCache.ts:162, services/zone/zoneServiceApi.ts:162 | **路径不存在** |
| POST | `/zones/services/{id}/request-approval` | services/zone/zoneServiceApiWithCache.ts:152, services/zone/zoneServiceApi.ts:152 | **路径不存在** |
| POST | `/zones/services/{id}/share` | services/zone/zoneServiceApiWithCache.ts:141, services/zone/zoneServiceApi.ts:141 | **路径不存在** |
| POST | `/zones/services/{id}/unshare` | services/zone/zoneServiceApiWithCache.ts:176, services/zone/zoneServiceApi.ts:176 | **路径不存在** |
| GET | `/zones/{id}/statistics` | services/zone/zoneService.ts:117 | **路径不存在** |

---

## ⚠️ 2. DOC GAP — GUI ↔ Back 一致，但 OpenAPI 未声明

**共 43 个**。功能正常，但 SSOT 文档缺失。

| METHOD | PATH | GUI Sources |
|---|---|---|
| POST | `/assets/avatar/upload` | services/api.ts:536 |
| DELETE | `/auth/cancel-password-reset` | services/api.ts:283 |
| POST | `/auth/change-password` | services/api.ts:292 |
| GET | `/auth/check-activation-status` | services/api.ts:316 |
| POST | `/auth/resend-activation-email` | services/api.ts:312 |
| GET | `/auth/status` | services/api.ts:335 |
| POST | `/auth/token/generate` | services/api.ts:331 |
| POST | `/auth/validate-reset-token` | services/api.ts:279 |
| GET | `/ippools` | composables/useIPPools.ts:84, services/ippool/ipPoolService.ts:7 |
| POST | `/ippools` | composables/useIPPools.ts:158, composables/useIPPools.ts:479 (+1) |
| POST | `/ippools/auto-allocate` | services/ippool/ipPoolService.ts:71 |
| GET | `/ippools/available-ips` | services/node/nodeService.ts:234 |
| GET | `/ippools/statistics` | composables/useIPPools.ts:288, services/ippool/ipPoolService.ts:37 |
| DELETE | `/ippools/{id}` | composables/useIPPools.ts:196, services/ippool/ipPoolService.ts:31 |
| GET | `/ippools/{id}` | composables/useIPPools.ts:142, services/ippool/ipPoolService.ts:13 |
| PUT | `/nodes/{id}` | services/api.ts:496, services/node/nodeService.ts:83 |
| GET | `/nodes/{id}/config` | composables/useVpnConfig.ts:21, services/node/nodeConfigService.ts:46 (+2) |
| GET | `/nodes/{id}/index-services` | services/node/nodeService.ts:244 |
| POST | `/nodes/{id}/operator` | services/node/nodeService.ts:126, services/node/nodeService.ts:142 (+1) |
| GET | `/social/data` | services/social/socialService.ts:15 |
| POST | `/social/friend-request` | services/social/socialService.ts:40 |
| POST | `/social/friend-request/accept` | services/social/socialService.ts:46 |
| POST | `/social/friend-request/cancel` | services/social/socialService.ts:58 |
| POST | `/social/friend-request/reject` | services/social/socialService.ts:52 |
| POST | `/social/friend/remove` | services/social/socialService.ts:63 |
| GET | `/social/friends` | services/social/socialService.ts:21 |
| GET | `/social/friends/all/nodes` | services/social/socialService.ts:93 |
| GET | `/social/friends/{id}/nodes` | services/social/socialService.ts:87 |
| POST | `/social/invitation` | services/social/socialService.ts:75 |
| DELETE | `/social/invitation/{id}` | services/social/socialService.ts:81 |
| GET | `/social/invitations` | services/social/socialService.ts:69 |
| POST | `/social/search-users` | services/social/socialService.ts:27 |
| GET | `/users/onboarding` | stores/onboarding.ts:453 |
| PUT | `/users/onboarding` | stores/onboarding.ts:432 |
| GET | `/zones` | components/ippool/IPPoolDialog.vue:139, services/zone/zoneService.ts:75 |
| POST | `/zones` | services/zone/zoneService.ts:84 |
| GET | `/zones/services/anonymous` | services/zone/zoneServiceApiWithCache.ts:65, services/zone/zoneServiceApi.ts:65 |
| GET | `/zones/services/friends` | services/zone/zoneServiceApiWithCache.ts:51, services/zone/zoneServiceApi.ts:51 |
| GET | `/zones/services/my` | services/zone/zoneServiceApiWithCache.ts:79, services/zone/zoneServiceApi.ts:79 |
| GET | `/zones/services/public` | services/zone/zoneServiceApiWithCache.ts:37, services/zone/zoneServiceApi.ts:37 |
| DELETE | `/zones/{id}` | services/zone/zoneService.ts:112 |
| GET | `/zones/{id}` | services/zone/zoneService.ts:96 |
| PUT | `/zones/{id}` | services/zone/zoneService.ts:106 |

---

## ✅ 3. FULL MATCH — 三方一致

**共 40 个**。无需处理。

<details><summary>展开列表</summary>

| METHOD | PATH |
|---|---|
| POST | `/auth/activate-account` |
| POST | `/auth/forgot-password` |
| POST | `/auth/login` |
| POST | `/auth/logout` |
| POST | `/auth/refresh-token` |
| POST | `/auth/register` |
| POST | `/auth/reset-password` |
| POST | `/auth/validate-session` |
| GET | `/auth/verify-token` |
| POST | `/monitor/heartbeat` |
| GET | `/nodes` |
| POST | `/nodes/create` |
| POST | `/nodes/deviceinfo` |
| POST | `/nodes/quick-create` |
| DELETE | `/nodes/{id}` |
| GET | `/nodes/{id}` |
| PUT | `/nodes/{id}/config` |
| GET | `/nodes/{id}/config-bundle` |
| GET | `/nodes/{id}/configuration` |
| GET | `/nodes/{id}/deviceinfo` |
| PATCH | `/nodes/{id}/deviceinfo` |
| DELETE | `/nodes/{id}/keys` |
| GET | `/nodes/{id}/keys` |
| POST | `/nodes/{id}/keys` |
| PUT | `/nodes/{id}/network-config` |
| GET | `/nodes/{id}/status` |
| PATCH | `/nodes/{id}/status` |
| POST | `/route/accept-request/{id}` |
| GET | `/route/data` |
| POST | `/route/reject-request/{id}` |
| POST | `/route/send-request` |
| DELETE | `/route/{id}` |
| GET | `/route/{id}` |
| GET | `/user` |
| POST | `/user/switch-zone` |
| GET | `/user/zones` |
| PUT | `/users/profile` |
| POST | `/zones/services/create` |
| DELETE | `/zones/services/delete/{id}` |
| PUT | `/zones/services/update/{id}` |

</details>


---

## 🟡 4. 后端孤儿 — Back 实现但 GUI 未调用

**共 69 个**。可能是其他客户端使用、CTL 服务端调用，或死代码。

<details><summary>展开列表</summary>

| METHOD | PATH | Action |
|---|---|---|
| DELETE | `/assets/{assetId}` | `Api\V1\BinaryAssetController@delete` |
| DELETE | `/nodes/commands/{id}` | `Api\V1\NodeHeartbeatController@cancelCommand` |
| DELETE | `/nodes/{id}/ips` | `Api\V1\NodeController@deallocateIP` |
| DELETE | `/notifications/{id}` | `Api\V1\NotificationController@destroy` |
| DELETE | `/route/bulk` | `Api\V1\RouteController@bulkDelete` |
| DELETE | `/users/{id}` | `Api\V1\UserController@destroy` |
| DELETE | `/zones/services/share/{id}` | `Api\V1\ZoneServiceController@removeShare` |
| GET | `/assets` | `Api\V1\BinaryAssetController@index` |
| GET | `/assets/avatar/{userId?}` | `Api\V1\BinaryAssetController@getUserAvatar` |
| GET | `/assets/node-icon/{nodeId}` | `Api\V1\BinaryAssetController@getNodeIcon` |
| GET | `/assets/{assetId}` | `Api\V1\BinaryAssetController@getAsset` |
| GET | `/ids/formats` | `Api\V1\IDController@formats` |
| GET | `/ids/statistics` | `Api\V1\IDController@statistics` |
| GET | `/ippools/user-resources` | `Api\V1\IPPoolController@userResources` |
| GET | `/monitor/index-services` | `Api\V1\NodeMonitorController@getIndexServices` |
| GET | `/monitor/node/config` | `Api\V1\NodeMonitorController@getNodeConfig` |
| GET | `/monitor/node/full-config` | `Api\V1\NodeMonitorController@getNodeFullConfig` |
| GET | `/monitor/node/info` | `Api\V1\NodeMonitorController@getNodeInfo` |
| GET | `/monitor/node/route-config` | `Api\V1\NodeMonitorController@getRouteConfig` |
| GET | `/monitor/node/routes` | `Api\V1\NodeMonitorController@getNodeRoutes` |
| GET | `/mynet/health` | `Api\V1\MyNetController@getHealthStatus` |
| GET | `/mynet/stats` | `Api\V1\MyNetController@getInitialData` |
| GET | `/nodes/{id}/connection-stats` | `Api\V1\NodeMonitorHistoryController@getConnectionStats` |
| GET | `/nodes/{id}/peer-connections` | `Api\V1\NodeMonitorHistoryController@getPeerConnectionHistory` |
| GET | `/nodes/{nodeId}/commands` | `Api\V1\NodeHeartbeatController@getCommands` |
| GET | `/notifications` | `Api\V1\NotificationController@index` |
| GET | `/notifications/stats` | `Api\V1\NotificationController@stats` |
| GET | `/notifications/unread-count` | `Api\V1\NotificationController@unreadCount` |
| GET | `/route/node/{nodeId}` | `Api\V1\RouteController@getNodeRoutes` |
| GET | `/route/requests` | `Api\V1\RouteController@getRequests` |
| GET | `/route/statistics` | `Api\V1\RouteController@statistics` |
| GET | `/user/current-zone` | `Api\V1\UserZoneController@getCurrentZone` |
| GET | `/users` | `Api\V1\UserController@index` |
| GET | `/users/my/statistics` | `Api\V1\UserController@myStatistics` |
| GET | `/users/profile` | `Api\V1\UserController@profile` |
| GET | `/users/search` | `Api\V1\UserController@search` |
| GET | `/users/tips/suggestions` | `Api\V1\UserController@tipsSuggestions` |
| GET | `/users/{id}` | `Api\V1\UserController@show` |
| GET | `/users/{id}/activity` | `Api\V1\UserController@activity` |
| GET | `/users/{id}/nodes` | `Api\V1\UserController@getUserNodes` |
| GET | `/users/{id}/permissions` | `Api\V1\UserController@permissions` |
| GET | `/users/{id}/statistics` | `Api\V1\UserController@statistics` |
| GET | `/users/{id}/zones` | `Api\V1\UserController@getUserZones` |
| GET | `/zones/services/indexes` | `Api\V1\ZoneServiceController@getIndexServices` |
| GET | `/zones/services/show/{id}` | `Api\V1\ZoneServiceController@show` |
| PATCH | `/monitor/commands/{id}/result` | `Api\V1\NodeHeartbeatController@reportResult` |
| PATCH | `/nodes/{id}` | `Api\V1\NodeController@update` |
| PATCH | `/route/{id}` | `Api\V1\RouteController@update` |
| PATCH | `/zones/{id}` | `Api\V1\ZoneController@update` |
| POST | `/assets/node-icon/upload` | `Api\V1\BinaryAssetController@uploadNodeIcon` |
| POST | `/assets/upload` | `Api\V1\BinaryAssetController@upload` |
| POST | `/ids/generate` | `Api\V1\IDController@generate` |
| POST | `/ids/generate/batch` | `Api\V1\IDController@generateBatch` |
| POST | `/ids/parse` | `Api\V1\IDController@parse` |
| POST | `/ids/validate` | `Api\V1\IDController@validateId` |
| POST | `/ippools/check-availability` | `Api\V1\IPPoolController@checkAvailability` |
| POST | `/monitor/router-keys` | `Api\V1\NodeMonitorController@getRouterKeys` |
| POST | `/nodes/{id}/config` | `Api\V1\NodeConfigController@saveConfig` |
| POST | `/nodes/{id}/router-keys` | `Api\V1\NodeController@getRouterKeys` |
| POST | `/notifications/batch-delete` | `Api\V1\NotificationController@batchDelete` |
| POST | `/notifications/clear-read` | `Api\V1\NotificationController@clearRead` |
| POST | `/notifications/read-all` | `Api\V1\NotificationController@markAllAsRead` |
| POST | `/notifications/{id}/read` | `Api\V1\NotificationController@markAsRead` |
| POST | `/route` | `Api\V1\RouteController@store` |
| POST | `/route/bulk/status` | `Api\V1\RouteController@bulkUpdateStatus` |
| POST | `/zones/services/request-approval/{id}` | `Api\V1\ZoneServiceController@requestPublicApproval` |
| POST | `/zones/services/share/{id}` | `Api\V1\ZoneServiceController@shareWithFriends` |
| PUT | `/route/{id}` | `Api\V1\RouteController@update` |
| PUT | `/users/{id}` | `Api\V1\UserController@update` |

</details>


---

## 🔵 5. OpenAPI 超前 — 声明但 Back 未实现

**共 0 个**。文档与实现脱节。

（无）

---

## 📊 汇总统计

- GUI 调用端点：**128**
  - ✅ 三方一致：40
  - ⚠️ 缺 OpenAPI 文档：43
  - ❌ 与 Back 不匹配（BLOCKER）：45
- Back v2 路由总数：**152**
  - 🟡 GUI 未使用：69
- OpenAPI 声明总数：**52**
  - 🔵 Back 未实现：0
  - GUI 未使用：12
