# P2 — Backend Routes (mynet_back)

> 提取自 `php artisan route:list --json`，共 **301** 个 API 路由（v1: 153, v2: 148）。

> v1/v2 同源 closure（`routes/api_routes.php` 在 `api.php` 中被注册到两个 prefix），下表合并展示。仅当 v2 与 v1 不同时单独标注。

## v2 路由全表

| METHOD | PATH | Action | Middleware |
|---|---|---|---|
| GET | `/assets` | `Api\V1\BinaryAssetController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/assets/avatar/upload` | `Api\V1\BinaryAssetController@uploadUserAvatar` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/assets/avatar/{userId?}` | `Api\V1\BinaryAssetController@getUserAvatar` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/assets/node-icon/upload` | `Api\V1\BinaryAssetController@uploadNodeIcon` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/assets/node-icon/{nodeId}` | `Api\V1\BinaryAssetController@getNodeIcon` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/assets/upload` | `Api\V1\BinaryAssetController@upload` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/assets/{assetId}` | `Api\V1\BinaryAssetController@delete` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/assets/{assetId}` | `Api\V1\BinaryAssetController@getAsset` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/auth/activate-account` | `Api\V1\AuthController@activateAccount` |  |
| DELETE | `/auth/cancel-password-reset` | `Api\V1\AuthController@cancelPasswordReset` |  |
| POST | `/auth/change-password` | `Api\V1\AuthController@changePassword` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/auth/check-activation-status` | `Api\V1\AuthController@checkActivationStatus` |  |
| POST | `/auth/forgot-password` | `Api\V1\AuthController@forgotPassword` |  |
| POST | `/auth/login` | `Api\V1\AuthController@login` |  |
| POST | `/auth/logout` | `Api\V1\AuthController@logout` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| POST | `/auth/refresh-token` | `Api\V1\AuthController@refreshToken` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| POST | `/auth/register` | `Api\V1\AuthController@register` |  |
| POST | `/auth/resend-activation-email` | `Api\V1\AuthController@resendActivationEmail` |  |
| POST | `/auth/reset-password` | `Api\V1\AuthController@resetPassword` |  |
| GET | `/auth/status` | `Closure` |  |
| POST | `/auth/token/generate` | `Api\V1\AuthController@generateToken` |  |
| POST | `/auth/validate-reset-token` | `Api\V1\AuthController@validateResetToken` |  |
| POST | `/auth/validate-session` | `Api\V1\AuthController@validateSession` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/auth/verify-token` | `Api\V1\AuthController@verifyToken` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/ids/formats` | `Api\V1\IDController@formats` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ids/generate` | `Api\V1\IDController@generate` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ids/generate/batch` | `Api\V1\IDController@generateBatch` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ids/parse` | `Api\V1\IDController@parse` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ids/statistics` | `Api\V1\IDController@statistics` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ids/validate` | `Api\V1\IDController@validateId` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ippools` | `Api\V1\IPPoolController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ippools` | `Api\V1\IPPoolController@store` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ippools/auto-allocate` | `Api\V1\IPPoolController@autoAllocate` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ippools/available-ips` | `Api\V1\IPPoolController@availableIPs` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/ippools/check-availability` | `Api\V1\IPPoolController@checkAvailability` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ippools/statistics` | `Api\V1\IPPoolController@statistics` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ippools/user-resources` | `Api\V1\IPPoolController@userResources` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/ippools/{id}` | `Api\V1\IPPoolController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/ippools/{sequence}` | `Api\V1\IPPoolController@release` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PATCH | `/monitor/commands/{id}/result` | `Api\V1\NodeHeartbeatController@reportResult` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| POST | `/monitor/heartbeat` | `Api\V1\NodeHeartbeatController@store` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/index-services` | `Api\V1\NodeMonitorController@getIndexServices` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/node/config` | `Api\V1\NodeMonitorController@getNodeConfig` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/node/full-config` | `Api\V1\NodeMonitorController@getNodeFullConfig` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/node/info` | `Api\V1\NodeMonitorController@getNodeInfo` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/node/route-config` | `Api\V1\NodeMonitorController@getRouteConfig` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/monitor/node/routes` | `Api\V1\NodeMonitorController@getNodeRoutes` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| POST | `/monitor/router-keys` | `Api\V1\NodeMonitorController@getRouterKeys` | App\Http\Middleware\NodeHeartbeatAuthMiddleware |
| GET | `/mynet/health` | `Api\V1\MyNetController@getHealthStatus` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/mynet/stats` | `Api\V1\MyNetController@getInitialData` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes` | `Api\V1\NodeController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/nodes/commands/{id}` | `Api\V1\NodeHeartbeatController@cancelCommand` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/create` | `Api\V1\NodeController@store` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/deviceinfo` | `Api\V1\NodeController@getBatchDeviceInfo` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/quick-create` | `Api\V1\NodeController@quickCreate` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/nodes/{id}` | `Api\V1\NodeController@destroy` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}` | `Api\V1\NodeController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT,PATCH | `/nodes/{id}` | `Api\V1\NodeController@update` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/config` | `Api\V1\NodeConfigController@getConfiguration` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT,POST | `/nodes/{id}/config` | `Api\V1\NodeConfigController@saveConfig` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/config-bundle` | `Api\V1\NodeConfigController@configBundle` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/configuration` | `Api\V1\NodeConfigController@getConfiguration` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/connection-stats` | `Api\V1\NodeMonitorHistoryController@getConnectionStats` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/deviceinfo` | `Api\V1\NodeController@getDeviceInfo` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PATCH | `/nodes/{id}/deviceinfo` | `Api\V1\NodeController@updateDeviceInfo` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/index-services` | `Api\V1\NodeController@getNodeIndexServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/nodes/{id}/ips` | `Api\V1\NodeController@deallocateIP` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/nodes/{id}/keys` | `Api\V1\NodeController@revokeKeys` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/keys` | `Api\V1\NodeController@getKeys` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/{id}/keys` | `Api\V1\NodeController@generateKeys` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT | `/nodes/{id}/network-config` | `Api\V1\NodeController@setNetworkConfig` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/{id}/operator` | `Api\V1\NodeActionController@execute` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/peer-connections` | `Api\V1\NodeMonitorHistoryController@getPeerConnectionHistory` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/nodes/{id}/router-keys` | `Api\V1\NodeController@getRouterKeys` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{id}/status` | `Api\V1\NodeController@getStatus` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PATCH | `/nodes/{id}/status` | `Api\V1\NodeController@updateStatus` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/nodes/{nodeId}/commands` | `Api\V1\NodeHeartbeatController@getCommands` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/notifications` | `Api\V1\NotificationController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/notifications/batch-delete` | `Api\V1\NotificationController@batchDelete` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/notifications/clear-read` | `Api\V1\NotificationController@clearRead` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/notifications/read-all` | `Api\V1\NotificationController@markAllAsRead` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/notifications/stats` | `Api\V1\NotificationController@stats` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/notifications/unread-count` | `Api\V1\NotificationController@unreadCount` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/notifications/{id}` | `Api\V1\NotificationController@destroy` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/notifications/{id}/read` | `Api\V1\NotificationController@markAsRead` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/route` | `Api\V1\RouteController@store` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/route/accept-request/{id}` | `Api\V1\RouteController@acceptRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/route/bulk` | `Api\V1\RouteController@bulkDelete` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/route/bulk/status` | `Api\V1\RouteController@bulkUpdateStatus` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/route/data` | `Api\V1\RouteController@getData` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/route/node/{nodeId}` | `Api\V1\RouteController@getNodeRoutes` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/route/reject-request/{id}` | `Api\V1\RouteController@rejectRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/route/requests` | `Api\V1\RouteController@getRequests` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/route/send-request` | `Api\V1\RouteController@sendRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/route/statistics` | `Api\V1\RouteController@statistics` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/route/{id}` | `Api\V1\RouteController@destroy` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/route/{id}` | `Api\V1\RouteController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT,PATCH | `/route/{id}` | `Api\V1\RouteController@update` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/social/data` | `Api\V1\SocialController@getData` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/friend-request` | `Api\V1\SocialController@sendFriendRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/friend-request/accept` | `Api\V1\SocialController@acceptFriendRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/friend-request/cancel` | `Api\V1\SocialController@cancelFriendRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/friend-request/reject` | `Api\V1\SocialController@rejectFriendRequest` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/friend/remove` | `Api\V1\SocialController@removeFriend` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/social/friends` | `Api\V1\SocialController@getFriends` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/social/friends/all/nodes` | `Api\V1\SocialController@getAllFriendsNodes` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/social/friends/{friendId}/nodes` | `Api\V1\SocialController@getFriendNodes` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/invitation` | `Api\V1\SocialController@generateInvitation` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/social/invitation/{id}` | `Api\V1\SocialController@deleteInvitation` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/social/invitations` | `Api\V1\SocialController@invitations` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/social/search-users` | `Api\V1\SocialController@searchUsers` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/user` | `Api\V1\UserController@profile` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/user/current-zone` | `Api\V1\UserZoneController@getCurrentZone` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| POST | `/user/switch-zone` | `Api\V1\UserZoneController@switchZone` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/user/zones` | `Api\V1\UserZoneController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/users` | `Api\V1\UserController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/my/statistics` | `Api\V1\UserController@myStatistics` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/onboarding` | `Api\V1\UserController@getOnboardingState` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT | `/users/onboarding` | `Api\V1\UserController@saveOnboardingState` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/profile` | `Api\V1\UserController@profile` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT | `/users/profile` | `Api\V1\UserController@updateProfile` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/search` | `Api\V1\UserController@search` | Illuminate\Auth\Middleware\Authenticate:sanctum |
| GET | `/users/tips/suggestions` | `Api\V1\UserController@tipsSuggestions` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/users/{id}` | `Api\V1\UserController@destroy` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}` | `Api\V1\UserController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT | `/users/{id}` | `Api\V1\UserController@update` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}/activity` | `Api\V1\UserController@activity` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}/nodes` | `Api\V1\UserController@getUserNodes` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}/permissions` | `Api\V1\UserController@permissions` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}/statistics` | `Api\V1\UserController@statistics` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/users/{id}/zones` | `Api\V1\UserController@getUserZones` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones` | `Api\V1\ZoneController@index` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/zones` | `Api\V1\ZoneController@store` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/anonymous` | `Api\V1\ZoneServiceController@getAnonymousServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/zones/services/create` | `Api\V1\ZoneServiceController@create` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/zones/services/delete/{id}` | `Api\V1\ZoneServiceController@delete` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/friends` | `Api\V1\ZoneServiceController@getFriendsServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/indexes` | `Api\V1\ZoneServiceController@getIndexServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/my` | `Api\V1\ZoneServiceController@getMyServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/public` | `Api\V1\ZoneServiceController@getPublicServices` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/zones/services/request-approval/{id}` | `Api\V1\ZoneServiceController@requestPublicApproval` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/zones/services/share/{id}` | `Api\V1\ZoneServiceController@removeShare` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| POST | `/zones/services/share/{id}` | `Api\V1\ZoneServiceController@shareWithFriends` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/services/show/{id}` | `Api\V1\ZoneServiceController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT | `/zones/services/update/{id}` | `Api\V1\ZoneServiceController@update` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| DELETE | `/zones/{id}` | `Api\V1\ZoneController@destroy` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| GET | `/zones/{id}` | `Api\V1\ZoneController@show` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |
| PUT,PATCH | `/zones/{id}` | `Api\V1\ZoneController@update` | Illuminate\Auth\Middleware\Authenticate:sanctum,App\Http\Mid |

## v1-only 路由（v2 已删除/合并），共 10 条

| METHOD | PATH | Action |
|---|---|---|
| GET | `/monitor/history/connections` | `Api\V1\Monitor\MonitorHistoryController@getNodeConnectionHistory` |
| PUT | `/nodes/deviceinfo` | `Api\V1\NodeController@getBatchDeviceInfo` |
| PUT | `/nodes/{id}/deviceinfo` | `Api\V1\NodeController@updateDeviceInfo` |
| POST | `/nodes/{id}/keys/generate` | `Api\V1\NodeController@generateKeys` |
| POST | `/nodes/{id}/keys/upload` | `Api\V1\NodeController@generateKeys` |
| GET | `/nodes/{id}/network-config` | `Api\V1\NodeController@getNetworkConfig` |
| GET | `/nodes/{id}/status-check` | `Api\V1\NodeController@checkConfigurationStatus` |
| GET | `/nodes/{id}/status-info` | `Api\V1\NodeController@getStatusInfo` |
| GET | `/route/connections` | `Api\V1\RouteController@getConnections` |
| GET | `/route/stats` | `Api\V1\RouteController@getStats` |

## v2-only 路由（v1 没有），共 5 条

| METHOD | PATH | Action |
|---|---|---|
| POST | `/nodes/deviceinfo` | `Api\V1\NodeController@getBatchDeviceInfo` |
| GET | `/nodes/{id}/config-bundle` | `Api\V1\NodeConfigController@configBundle` |
| GET | `/nodes/{id}/configuration` | `Api\V1\NodeConfigController@getConfiguration` |
| POST | `/nodes/{id}/keys` | `Api\V1\NodeController@generateKeys` |
| GET | `/nodes/{id}/status` | `Api\V1\NodeController@getStatus` |
