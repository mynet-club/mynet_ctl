# P3 — OpenAPI Endpoints (mynet_ctl/docs/api/openapi/back.yaml)

> 提取自 `back.yaml`，共 **52** 个 (METHOD, PATH) 端点声明。

> Base URL（per `version.json`）：`https://api.mynet.club/api/v2`，下表 PATH 不含前缀。

| METHOD | PATH | operationId | Tag |
|---|---|---|---|
| POST | `/auth/activate-account` | `activateAccount` |  |
| POST | `/auth/forgot-password` | `forgotPassword` |  |
| POST | `/auth/login` | `login` |  |
| POST | `/auth/logout` | `logout` |  |
| POST | `/auth/refresh-token` | `refreshToken` |  |
| POST | `/auth/register` | `register` |  |
| POST | `/auth/reset-password` | `resetPassword` |  |
| POST | `/auth/validate-session` | `validateSession` |  |
| GET | `/auth/verify-token` | `verifyToken` |  |
| PATCH | `/monitor/commands/{id}/result` | `reportCommandResult` |  |
| POST | `/monitor/heartbeat` | `heartbeat` |  |
| GET | `/nodes` | `listNodes` |  |
| POST | `/nodes/create` | `createNode` |  |
| POST | `/nodes/deviceinfo` | `batchDeviceInfo` |  |
| POST | `/nodes/quick-create` | `quickCreateNode` |  |
| DELETE | `/nodes/{id}` | `deleteNode` |  |
| GET | `/nodes/{id}` | `getNode` |  |
| PATCH | `/nodes/{id}` | `updateNode` |  |
| PUT | `/nodes/{id}/config` | `saveConfig` |  |
| GET | `/nodes/{id}/config-bundle` | `getConfigBundle` |  |
| GET | `/nodes/{id}/configuration` | `getConfiguration` |  |
| GET | `/nodes/{id}/deviceinfo` | `getDeviceInfo` |  |
| PATCH | `/nodes/{id}/deviceinfo` | `updateDeviceInfo` |  |
| DELETE | `/nodes/{id}/keys` | `revokeNodeKeys` |  |
| GET | `/nodes/{id}/keys` | `getNodeKeys` |  |
| POST | `/nodes/{id}/keys` | `generateOrUploadKeys` |  |
| PUT | `/nodes/{id}/network-config` | `updateNetworkConfig` |  |
| POST | `/nodes/{id}/router-keys` | `getRouterKeys` |  |
| GET | `/nodes/{id}/status` | `getNodeStatus` |  |
| PATCH | `/nodes/{id}/status` | `updateNodeStatus` |  |
| POST | `/route` | `createRoute` |  |
| POST | `/route/accept-request/{id}` | `acceptRouteRequest` |  |
| DELETE | `/route/bulk` | `bulkDeleteRoutes` |  |
| POST | `/route/bulk/status` | `bulkRouteStatus` |  |
| GET | `/route/data` | `getRouteData` |  |
| GET | `/route/node/{id}` | `getNodeRoutes` |  |
| POST | `/route/reject-request/{id}` | `rejectRouteRequest` |  |
| POST | `/route/send-request` | `sendRouteRequest` |  |
| DELETE | `/route/{id}` | `deleteRoute` |  |
| GET | `/route/{id}` | `getRoute` |  |
| PUT | `/route/{id}` | `updateRoute` |  |
| GET | `/user` | `getProfile` |  |
| POST | `/user/switch-zone` | `switchZone` |  |
| GET | `/user/zones` | `listZones` |  |
| PUT | `/users/profile` | `updateProfile` |  |
| GET | `/users/search` | `searchUsers` |  |
| POST | `/zones/services/create` | `createService` |  |
| DELETE | `/zones/services/delete/{id}` | `deleteService` |  |
| GET | `/zones/services/indexes` | `getIndexServices` |  |
| DELETE | `/zones/services/share/{id}` | `unshareService` |  |
| POST | `/zones/services/share/{id}` | `shareService` |  |
| PUT | `/zones/services/update/{id}` | `updateService` |  |
