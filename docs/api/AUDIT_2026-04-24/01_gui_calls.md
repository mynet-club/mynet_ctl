# P1 — Frontend API Calls (mynet_gui)

> 提取自 `mynet_gui/src/`，共 **128** 个唯一 (METHOD, PATH) 组合。
> Base URL: 所有 `api`/`apiService` 实例的 baseURL = `{VITE_API_BASE_URL}/api/v2`，下表 PATH 为相对路径。

| METHOD | PATH | Sources |
|---|---|---|
| POST | `/assets/avatar/upload` | services/api.ts:536 |
| POST | `/auth/activate-account?lang={id}` | services/api.ts:303 |
| DELETE | `/auth/cancel-password-reset` | services/api.ts:283 |
| POST | `/auth/change-password` | services/api.ts:292 |
| GET | `/auth/check-activation-status` | services/api.ts:316 |
| POST | `/auth/forgot-password?lang={id}` | services/api.ts:265 |
| POST | `/auth/login` | services/api.ts:194 |
| POST | `/auth/logout` | services/api.ts:234 |
| POST | `/auth/refresh-token` | utils/tokenManager.ts:128, services/api.ts:350 |
| POST | `/auth/register?lang={id}` | services/api.ts:255 |
| POST | `/auth/resend-activation-email?lang={id}` | services/api.ts:312 |
| POST | `/auth/reset-password` | services/api.ts:275 |
| GET | `/auth/status` | services/api.ts:335 |
| POST | `/auth/token/generate` | services/api.ts:331 |
| POST | `/auth/validate-reset-token` | services/api.ts:279 |
| POST | `/auth/validate-session` | services/api.ts:327 |
| GET | `/auth/verify-token` | services/api.ts:238 |
| POST | `/config/validate` | services/node/nodeConfigService.ts:187 |
| GET | `/config/vpn-templates` | services/node/nodeConfigService.ts:213 |
| GET | `/config/vpn-templates/{id}` | services/node/nodeConfigService.ts:224 |
| GET | `/i18n/{id}` | services/api.ts:528 |
| GET | `/ippools` | composables/useIPPools.ts:84, services/ippool/ipPoolService.ts:7 |
| POST | `/ippools` | composables/useIPPools.ts:158, composables/useIPPools.ts:479, services/ippool/ipPoolService.ts:19 |
| POST | `/ippools/auto-allocate` | services/ippool/ipPoolService.ts:71 |
| GET | `/ippools/available-ips` | services/node/nodeService.ts:234 |
| POST | `/ippools/batch` | composables/useIPPools.ts:376, services/ippool/ipPoolService.ts:77 |
| GET | `/ippools/export` | composables/useIPPools.ts:397, services/ippool/ipPoolService.ts:86 |
| GET | `/ippools/statistics` | composables/useIPPools.ts:288, services/ippool/ipPoolService.ts:37 |
| DELETE | `/ippools/{id}` | composables/useIPPools.ts:196, services/ippool/ipPoolService.ts:31 |
| GET | `/ippools/{id}` | composables/useIPPools.ts:142, services/ippool/ipPoolService.ts:13 |
| PUT | `/ippools/{id}` | composables/useIPPools.ts:176, services/ippool/ipPoolService.ts:25 |
| POST | `/ippools/{id}/allocate` | composables/useIPPools.ts:215, services/ippool/ipPoolService.ts:44 |
| GET | `/ippools/{id}/allocations` | composables/useIPPools.ts:250, services/ippool/ipPoolService.ts:56 |
| GET | `/ippools/{id}/check-ip` | composables/useIPPools.ts:268, services/ippool/ipPoolService.ts:62 |
| POST | `/ippools/{id}/extend` | composables/useIPPools.ts:623 |
| POST | `/ippools/{id}/release` | composables/useIPPools.ts:233, services/ippool/ipPoolService.ts:50 |
| POST | `/monitor/heartbeat` | services/node/configBundleService.ts:109 |
| GET | `/monitor/history/connections` | pages/route/ActiveRoutesPage.vue:314, pages/route/ActiveRoutesPage.vue:403, pages/route/ConnectionDetailsPage.vue:170 (+1 more) |
| GET | `/nodes` | components/ippool/IPPoolDetailsDialog.vue:247, services/api.ts:488, services/node/nodeService.ts:42 |
| POST | `/nodes` | services/api.ts:492 |
| POST | `/nodes/create` | services/node/nodeService.ts:62 |
| POST | `/nodes/deviceinfo` | services/node/nodeService.ts:185 |
| POST | `/nodes/quick-create` | services/node/nodeService.ts:72 |
| DELETE | `/nodes/{id}` | services/api.ts:500, services/node/nodeService.ts:93 |
| GET | `/nodes/{id}` | services/node/nodeService.ts:52 |
| PUT | `/nodes/{id}` | services/api.ts:496, services/node/nodeService.ts:83 |
| GET | `/nodes/{id}/config` | composables/useVpnConfig.ts:21, services/node/nodeConfigService.ts:46, services/node/nodeConfigService.ts:97 (+1 more) |
| PUT | `/nodes/{id}/config` | composables/useVpnConfig.ts:65, services/node/nodeConfigService.ts:152 |
| GET | `/nodes/{id}/config-bundle` | services/node/configBundleService.ts:30 |
| POST | `/nodes/{id}/config/apply-template` | services/node/nodeConfigService.ts:237 |
| GET | `/nodes/{id}/config/compare/{id}` | services/node/nodeConfigService.ts:274 |
| GET | `/nodes/{id}/config/download` | services/node/nodeConfigService.ts:174 |
| GET | `/nodes/{id}/config/export` | services/node/nodeConfigService.ts:287 |
| GET | `/nodes/{id}/config/history` | services/node/nodeConfigService.ts:250 |
| POST | `/nodes/{id}/config/import` | services/node/nodeConfigService.ts:300 |
| POST | `/nodes/{id}/config/preview` | services/node/nodeConfigService.ts:163 |
| POST | `/nodes/{id}/config/reset` | services/node/nodeConfigService.ts:199 |
| POST | `/nodes/{id}/config/restore/{id}` | services/node/nodeConfigService.ts:263 |
| GET | `/nodes/{id}/configuration` | services/node/nodeService.ts:270 |
| GET | `/nodes/{id}/deviceinfo` | services/node/nodeService.ts:173 |
| PATCH | `/nodes/{id}/deviceinfo` | services/node/nodeService.ts:200, services/node/nodeService.ts:211 |
| GET | `/nodes/{id}/index-services` | services/node/nodeService.ts:244 |
| DELETE | `/nodes/{id}/keys` | services/node/nodeKeysService.ts:81 |
| GET | `/nodes/{id}/keys` | services/node/nodeKeysService.ts:39 |
| POST | `/nodes/{id}/keys` | services/node/nodeKeysService.ts:52, services/node/nodeKeysService.ts:71 |
| DELETE | `/nodes/{id}/keys/{id}` | services/node/nodeKeysService.ts:92 |
| PATCH | `/nodes/{id}/keys/{id}/status` | services/node/nodeKeysService.ts:104 |
| PUT | `/nodes/{id}/network-config` | services/node/nodeService.ts:255 |
| POST | `/nodes/{id}/operator` | services/node/nodeService.ts:126, services/node/nodeService.ts:142, services/node/nodeService.ts:158 |
| GET | `/nodes/{id}/status` | services/node/nodeService.ts:287 |
| PATCH | `/nodes/{id}/status` | services/node/nodeService.ts:104, services/node/nodeService.ts:115 |
| POST | `/route/accept-request/{id}` | services/route/routesService.ts:38 |
| GET | `/route/active` | services/route/routesService.ts:14 |
| POST | `/route/connect/{id}` | services/route/routesService.ts:32 |
| GET | `/route/connections/{id}` | services/route/routesService.ts:20 |
| GET | `/route/data` | services/route/routesService.ts:8 |
| GET | `/route/friend-nodes` | services/route/routesService.ts:26 |
| POST | `/route/reject-request/{id}` | services/route/routesService.ts:44 |
| POST | `/route/send-request` | services/route/routesService.ts:56 |
| DELETE | `/route/{id}` | services/route/routesService.ts:50 |
| GET | `/route/{id}` | services/route/routesService.ts:64 |
| GET | `/route/{id}/stats` | services/route/routesService.ts:70 |
| GET | `/routes` | services/api.ts:505 |
| POST | `/routes` | services/api.ts:509 |
| DELETE | `/routes/{id}` | services/api.ts:517 |
| PUT | `/routes/{id}` | services/api.ts:513 |
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
| GET | `/user` | services/api.ts:370 |
| POST | `/user/switch-zone` | services/zone/zoneService.ts:64, services/api.ts:462 |
| GET | `/user/zones` | services/zone/zoneService.ts:58, services/api.ts:436 |
| GET | `/users/onboarding` | stores/onboarding.ts:453 |
| PUT | `/users/onboarding` | stores/onboarding.ts:432 |
| PUT | `/users/profile` | services/api.ts:418 |
| GET | `/users/{id}/ip-resources` | composables/useIPPools.ts:359, services/ippool/ipPoolService.ts:95 |
| GET | `/zones` | components/ippool/IPPoolDialog.vue:139, services/zone/zoneService.ts:75 |
| POST | `/zones` | services/zone/zoneService.ts:84 |
| GET | `/zones/overview` | services/zone/zoneService.ts:90 |
| GET | `/zones/services` | services/zone/zoneServiceApiWithCache.ts:23, services/zone/zoneServiceApi.ts:23 |
| POST | `/zones/services` | services/zone/zoneServiceApiWithCache.ts:106 |
| GET | `/zones/services/anonymous` | services/zone/zoneServiceApiWithCache.ts:65, services/zone/zoneServiceApi.ts:65 |
| POST | `/zones/services/create` | services/zone/zoneServiceApi.ts:106 |
| DELETE | `/zones/services/delete/{id}` | services/zone/zoneServiceApiWithCache.ts:129, services/zone/zoneServiceApi.ts:129 |
| GET | `/zones/services/friends` | services/zone/zoneServiceApiWithCache.ts:51, services/zone/zoneServiceApi.ts:51 |
| GET | `/zones/services/indexeservices` | services/zone/zoneService.ts:123 |
| GET | `/zones/services/my` | services/zone/zoneServiceApiWithCache.ts:79, services/zone/zoneServiceApi.ts:79 |
| GET | `/zones/services/public` | services/zone/zoneServiceApiWithCache.ts:37, services/zone/zoneServiceApi.ts:37 |
| PUT | `/zones/services/update/{id}` | services/zone/zoneServiceApiWithCache.ts:117, services/zone/zoneServiceApi.ts:117 |
| GET | `/zones/services/{id}` | services/zone/zoneServiceApiWithCache.ts:93, services/zone/zoneServiceApi.ts:93 |
| POST | `/zones/services/{id}/approve` | services/zone/zoneServiceApiWithCache.ts:162, services/zone/zoneServiceApi.ts:162 |
| POST | `/zones/services/{id}/request-approval` | services/zone/zoneServiceApiWithCache.ts:152, services/zone/zoneServiceApi.ts:152 |
| POST | `/zones/services/{id}/share` | services/zone/zoneServiceApiWithCache.ts:141, services/zone/zoneServiceApi.ts:141 |
| POST | `/zones/services/{id}/unshare` | services/zone/zoneServiceApiWithCache.ts:176, services/zone/zoneServiceApi.ts:176 |
| DELETE | `/zones/{id}` | services/zone/zoneService.ts:112 |
| GET | `/zones/{id}` | services/zone/zoneService.ts:96 |
| PUT | `/zones/{id}` | services/zone/zoneService.ts:106 |
| GET | `/zones/{id}/statistics` | services/zone/zoneService.ts:117 |
