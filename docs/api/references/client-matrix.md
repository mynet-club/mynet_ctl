# 客户端匹配分析 & API 调用矩阵

> Source of Truth: mynet_ctl/docs/api/references/client-matrix.md

---

## 0. 目标线客户端（2026-09 起）

> 背景：旧线的 `mynet_lua` / `mynet_client`（Node）已冻结，产品本体改为 **mynet_agent** + **mynet_ui**。
> 见 [`../../knowledge-base/integration-plan-v2.md`](../../knowledge-base/integration-plan-v2.md) §1。

| 项 | mynet_agent | mynet_ui |
|----|-------------|----------|
| 形态 | Go：`mynetd`（守护）+ `mynet`（CLI） | 静态 WebUI（`dist/`） |
| 对 back 的调用 | 用户面 `/api/v2/*`（Bearer）；心跳 `/monitor/*`（HMAC） | **无**（不直连 back） |
| 对 ctl 的调用 | `/d/apps.json`、`/d/{product}/manifest.json`、`/d/{product}/{file}` | **无** |
| 对**本机**的调用 | 提供 HTTP `/api/v1` + ubus `mynet.*` | 调 agent 本机 API（HTTP/ubus） |
| Token 存储 | `$MYNET_HOME/conf/credential.json`（0600） | 不存 token（经 agent 代理） |
| User-Agent | `mynet-agent/{ver}` | — |
| 平台差异 | 桌面/Linux/Windows 走 HTTP；OpenWrt 走 ubus | 页面由 agent 提供或 LuCI 壳承载 |

> 关键：**ui 的能力全部来自 agent 本机 API**。ui 不持有 token，也不直接访问 back/ctl。
>
> 下方 §1-§3 的 `lua` / `client` 列对应**已冻结**的旧线，保留作迁移参考；继续维护时以 agent/ui 为准（含 `mynet_desktop` / `mynet_openwrt` 两个壳，它们不新增 back 调用）。

---

## 1. 各客户端认证配置

| 项 | GUI (Quasar/Vue) | client (Node.js) | lua (LuCI) |
|----|-------------------|-------------------|-------------|
| HTTP 库 | axios | axios + 原生 http | curl CLI |
| User-Agent | 默认 | `mynet-client/{ver}` | `mynet-luci/2.1.0` |
| Base URL | `VITE_API_BASE_URL/api/v2` | `config.json:api_base_url` | `config.lua:api_base_url` |
| Token 存储 | localStorage | `credential.json` | `/etc/mynet/conf/credential.json` |
| X-Zone-ID 来源 | `localStorage:user_info.zone_id` | `zone.zone_id \|\| zone.id` | `credential.zone_id` |
| Token 刷新 | ✅ 自动 (401 拦截) | ✅ 自动 (401 拦截) | ✅ 手动 |
| X-Client-Request-ID | ❌ | ✅ `c-{ts}-{seq}` | ❌ |

---

## 2. 关键字段匹配表

### 2.1 登录字段

| 字段 | back 返回 | GUI | client | lua |
|------|-----------|-----|--------|-----|
| `data.auth_token` | ✅ string | ✅ | ✅ | ✅ |
| `data.refresh_token` | ✅ string | ✅ | ✅ | ✅ |
| `data.auth_token_expires_in` | ✅ int | ✅ | ✅ | ✅ |
| `data.refresh_token_expires_in` | ✅ int | ✅ | ✅ | — |
| `data.user.user_id` | ✅ int | ✅ (映射 id→user_id) | ✅ (fallback user.id) | ✅ (fallback user.id) |
| `data.user.name` | ✅ string | ✅ | ✅ → `user_name` | — |
| `data.user.email` | ✅ string | ✅ | ✅ | — |
| `data.user.zone_id` | ✅ int\|null | ✅ | ✅ | ✅ |
| `data.current_zone` | ⚠️ 仅 verify-token | — | ✅ (自动恢复) | — |
| `data.default_zone` | ⚠️ 仅 verify-token | — | ✅ (备选恢复) | — |

### 2.2 节点列表字段

| 字段路径 | back 返回 | GUI | client | lua |
|----------|-----------|-----|--------|-----|
| `data.nodes` | ✅ array | ✅ | ✅ (多路径 fallback) | ✅ |
| `data.meta.total` | ✅ int | ✅ | — | — |
| `nodes[].node_id` | ✅ int | ✅ | ✅ (fallback .id) | ✅ |
| `nodes[].node_name` | ✅ string | ✅ | ✅ (fallback .name) | ✅ |
| `nodes[].vpn_type` | ✅ string | ✅ | ✅ | ✅ |
| `nodes[].node_status` | ✅ int | ✅ | ✅ (fallback .status) | ✅ |
| `nodes[].node_ip` | ✅ string\|null | ✅ | ✅ (fallback .virtual_ip) | ✅ |

### 2.3 config-bundle 字段

| 字段 | back 返回 | client | lua | CTL ConfigPackService |
|------|-----------|--------|-----|----------------------|
| `data.files["node.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.files["address.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.files["route.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.keys.ed25519.{nodeId}` | ✅ hex | ✅ | ✅ | ✅ |
| `data.keys.security.private` | ✅ string\|null | ✅ | ✅ | ✅ |
| `data.keys.security.public` | ✅ string | ✅ | ✅ | ✅ |
| `data.warnings` | ✅ array | — | ✅ (显示) | — |
| `data.node_id` | ✅ int | ✅ | — | — |
| `data.zone_id` | ✅ int | ✅ | — | — |
| `data.version` | ✅ string | ✅ | — | — |

### 2.4 心跳字段

| 字段 | back 期望 | client 发送 | lua 发送 |
|------|-----------|-------------|----------|
| `vpn_interface` (单数) | ✅ 接受 | ✅ 使用单数 | — |
| `vpn_interfaces` (复数) | ✅ 接受 | — | ✅ 使用复数 |
| `node_monitor` | ✅ 可选 | ✅ 发送 | — |
| `status` | — | — | ✅ "online"\|"offline" |
| `uptime` | — | — | ✅ int |

> back 同时接受 `vpn_interface`(单数) 和 `vpn_interfaces`(复数)，两端均匹配。

### 2.5 router-keys 字段

| 特性 | back 期望/返回 | client | lua |
|------|---------------|--------|-----|
| 请求体 | `{ "router_node_ids": [int] }` | ✅ | ⚠️ 发送空 `{}` |
| 响应路径 | `data.keys[]` | ✅ (多路径 fallback) | ✅ (多路径 fallback) |
| key 结构 | `{ router_node_id, key_type, public_key }` | ⚠️ 期望 `{peerId: "hex"}` | ⚠️ 期望 `{peerId: "hex"}` |

---

## 3. mynet_back API 调用矩阵

| # | Method | Path | 中间件 | GUI | client | lua | CTL代理 |
|---|--------|------|--------|-----|--------|-----|---------|
| 1 | POST | `/auth/login` | 无 | ✅ | ✅ | ✅ | — |
| 2 | POST | `/auth/register` | 无 | ✅ | — | — | — |
| 3 | POST | `/auth/refresh-token` | sanctum | ✅ | ✅ | ✅ | — |
| 4 | POST | `/auth/logout` | sanctum | ✅ | ✅ | ✅ | — |
| 5 | GET | `/auth/verify-token` | sanctum | ✅ | ✅ | ✅ | — |
| 6 | POST | `/auth/forgot-password` | 无 | ✅ | — | — | — |
| 7 | POST | `/auth/reset-password` | 无 | ✅ | — | — | — |
| 8 | POST | `/auth/activate-account` | 无 | ✅ | — | — | — |
| 9 | POST | `/auth/resend-activation-email` | 无 | ✅ | — | — | — |
| 10 | GET | `/auth/check-activation-status` | 无 | ✅ | — | — | — |
| 11 | POST | `/auth/validate-session` | sanctum | ✅ | — | — | — |
| 12 | POST | `/auth/token/generate` | sanctum | ✅ | — | — | — |
| 13 | GET | `/auth/status` | 无 | ✅ | — | — | — |
| 14 | GET | `/user` | sanctum | ✅ | — | — | — |
| 15 | PUT | `/users/profile` | sanctum | ✅ | — | — | — |
| 16 | GET | `/users/search` | sanctum | ✅ | — | — | — |
| 17 | GET | `/user/zones` | sanctum | ✅ | ✅ | ✅ | ✅ |
| 18 | POST | `/user/switch-zone` | sanctum | ✅ | ✅ | — | ✅ |
| 19 | GET | `/nodes` | sanctum+zone | ✅ | ✅ | ✅ | ✅ |
| 20 | POST | `/nodes/create` | sanctum+zone | ✅ | ✅ | — | — |
| 21 | POST | `/nodes/quick-create` | sanctum+zone | ✅ | — | — | — |
| 22 | GET | `/nodes/{id}` | sanctum+zone | ✅ | ✅ | ✅ | ✅ |
| 23 | PATCH | `/nodes/{id}` | sanctum+zone | ✅ | — | — | — |
| 24 | DELETE | `/nodes/{id}` | sanctum+zone | ✅ | — | — | — |
| 25 | PATCH | `/nodes/{id}/status` | sanctum+zone | ✅ | ✅ | ✅ | — |
| 26 | GET | `/nodes/{id}/config` (v1) | sanctum+zone | — | — | ✅ | — |
| 27 | GET | `/nodes/{id}/configuration` (v2) | sanctum+zone | ✅ | ✅* | — | ✅ |
| 28 | PUT | `/nodes/{id}/config` | sanctum+zone | ✅ | — | — | — |
| 29 | GET | `/nodes/{id}/config-bundle` (v2) | sanctum+zone | — | ✅ | ✅† | ✅ |
| 30 | GET | `/nodes/{id}/keys` | sanctum+zone | ✅ | ✅ | ✅ | ✅ |
| 31 | POST | `/nodes/{id}/keys` (v2) | sanctum+zone | — | — | ✅ | — |
| 32 | POST | `/nodes/{id}/keys/generate` (v1) | sanctum+zone | ✅ | — | — | — |
| 33 | POST | `/nodes/{id}/keys/upload` (v1) | sanctum+zone | ✅ | ✅ | — | — |
| 34 | DELETE | `/nodes/{id}/keys` | sanctum+zone | ✅ | — | — | — |
| 35 | DELETE | `/nodes/{id}/keys/{keyId}` | sanctum+zone | ✅ | — | — | — |
| 36 | PATCH | `/nodes/{id}/keys/{keyId}/status` | sanctum+zone | ✅ | — | — | — |
| 37 | POST | `/nodes/{id}/router-keys` | sanctum+zone | ✅ | ✅ | ✅ | ✅ |
| 38 | GET | `/nodes/{id}/deviceinfo` | sanctum+zone | ✅ | — | — | — |
| 39 | PUT/POST | `/nodes/deviceinfo` (batch) | sanctum+zone | ✅ | — | — | — |
| 40 | PUT | `/nodes/{id}/deviceinfo` | sanctum+zone | ✅ | ✅ | — | — |
| 41 | POST | `/nodes/{id}/operator` | sanctum+zone | ✅ | — | — | — |
| 42 | GET | `/nodes/{id}/status` (v2) | sanctum+zone | — | — | — | — |
| 43 | GET | `/zones/services/indexes` | sanctum+zone | ✅ | ✅* | ✅ | ✅ |
| 44 | GET | `/zones/services` | sanctum+zone | ✅ | — | — | — |
| 45 | GET | `/zones/services/public` | sanctum+zone | ✅ | — | — | — |
| 46 | GET | `/zones/services/friends` | sanctum+zone | ✅ | — | — | — |
| 47 | GET | `/zones/services/anonymous` | sanctum+zone | ✅ | — | — | — |
| 48 | GET | `/zones/services/my` | sanctum+zone | ✅ | — | — | — |
| 49 | GET | `/zones/services/{id}` | sanctum+zone | ✅ | — | — | — |
| 50 | POST | `/zones/services/create` | sanctum+zone | ✅ | — | — | — |
| 51 | PUT | `/zones/services/update/{id}` | sanctum+zone | ✅ | — | — | — |
| 52 | DELETE | `/zones/services/delete/{id}` | sanctum+zone | ✅ | — | — | — |
| 53 | POST | `/zones/services/{id}/share` | sanctum+zone | ✅ | — | — | — |
| 54 | GET | `/route/data` | sanctum+zone | ✅ | — | — | — |
| 55 | GET | `/route/active` | sanctum+zone | ✅ | — | — | — |
| 56 | GET | `/route/connections/{type}` | sanctum+zone | ✅ | — | — | — |
| 57 | GET | `/route/friend-nodes` | sanctum+zone | ✅ | — | — | — |
| 58 | POST | `/route/connect/{nodeId}` | sanctum+zone | ✅ | — | — | — |
| 59 | POST | `/route/accept-request/{id}` | sanctum+zone | ✅ | — | — | — |
| 60 | POST | `/route/reject-request/{id}` | sanctum+zone | ✅ | — | — | — |
| 61 | DELETE | `/route/{id}` | sanctum+zone | ✅ | — | — | — |
| 62 | POST | `/route/send-request` | sanctum+zone | ✅ | — | — | — |
| 63 | GET | `/route/node/{id}` | sanctum+zone | — | ✅* | ✅ | ✅ |
| 64 | POST | `/monitor/heartbeat` | hmac | — | ✅ | ✅ | — |
| 65 | PATCH | `/monitor/commands/{id}/result` | hmac | — | ✅ | — | — |
| 66+ | | 社交/通知/IP池等 | sanctum+zone | ✅ | — | — | — |

> `✅*` = 仅作为 config-bundle 失败的降级路径
> `✅†` = lua 已实现 config-bundle 调用（强制 v2 路径），含 404 时 fallback 到分散请求

---

## 4. mynet_ctl API 调用矩阵

| # | Method | Path | 认证 | client | lua |
|---|--------|------|------|--------|-----|
| 1 | GET | `/d/apps.json` | 无 | ✅ | ✅ |
| 2 | GET | `/d/{product}/manifest.json` | 无 | ✅ | ✅ |
| 3 | GET | `/d/{product}/plugins.json` | 无 | ✅ | — |
| 4 | GET | `/d/plugins/{filename}` | 无 | ✅ | — |
| 5 | GET | `/d/{product}/{filename}` | 无 | ✅ | ✅ |
| 6 | GET | `/d/plugins/mp/{name}.txt` | 无 | ✅ | ✅ |
| 7 | GET | `/api/v2/proxy/latest` | 无 | ✅ | — |
| 8 | GET | `/api/v2/proxy/iplists/meta` | 无 | ✅ | — |

---

## 5. CTL 端点匹配

| CTL 端点 | client | lua | 已实现? |
|----------|--------|-----|---------|
| `GET /d/apps.json` | ✅ | ✅ | ✅ |
| `GET /d/{product}/manifest.json` | ✅ | ✅ | ✅ |
| `GET /d/{product}/plugins.json` | ✅ | — | ✅ |
| `GET /d/plugins/{filename}` | ✅ | — | ✅ |
| `GET /d/{product}/{filename}` (binary) | ✅ | ✅ | ✅ |
| `GET /d/plugins/mp/{name}.txt` | ✅ | ✅ | ✅ |
| `GET /api/v2/proxy/latest` | ✅ | — | ✅ |
| `GET /api/v2/proxy/iplists/meta` | ✅ | — | ✅ |
