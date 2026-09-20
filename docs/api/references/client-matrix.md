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
| X-Tenant-ID 来源 | `localStorage:user_info.tenant_id` | `zone.tenant_id \|\| zone.id` | `credential.tenant_id` |
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
| `data.user.tenant_id` | ✅ int\|null | ✅ | ✅ | ✅ |
| `data.current_tenant` | ⚠️ 仅 verify-token | — | ✅ (自动恢复) | — |

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
| 请求体 | `{ "router_node_ids": [int] }`（required, 1..100） | ✅ | ⚠️ 发送空 `{}` |
| 响应路径 | `data.keys`（**对象映射**，非数组） | ✅ | ✅ (多路径 fallback) |
| key 结构 | `{ "<nodeId>": "<public_key_hex>" }` | ✅ | ✅ |
| 其他字段 | `data.request_node_id`、`data.failed[]={node_id,reason}`、`data.summary` | — | — |

> 更正（2026-09-21）：此前本表把 key 结构写成 `{router_node_id,key_type,public_key}` 的**数组**，
> 并断言 client 的 `{peerId:"hex"}` 期望是「⚠️ 不对」——**恰好相反**。
> back 实际返回映射 `{"<nodeId>":"<hex>"}`（`NodeKeyDomainService` 里 `$keys[(string)$id]=public_key`），
> 故 client 的映射期望才是对的。agent 侧已按此修正（mynet_agent 49fe022）。

---

## 3. mynet_back API 调用矩阵

> ⚠️ **本表部分行已过时**（最后核对：2026-09-21）。路径与方法的**权威来源是
> [`../openapi/back.yaml`](../openapi/back.yaml)**（123 paths / 147 ops，已与
> `app/Modules/VPN/routes/api.php` 逐条对账）。本表保留用于「哪个客户端调用哪个端点」的对照；
> **两者冲突时以 back.yaml 为准**。
>
> 已知仍待校对的区域（不在本轮修正范围内）：
> - `/route/*` 数行：`/route/active`、`/route/connections/{type}`、`/route/friend-nodes`、
>   `/route/connect/{nodeId}` 在 back 中并未注册（实际为 `/route/statistics`、`/route/requests`、
>   `GET /route/{id}`、`PUT|PATCH /route/{id}`）
> - keys 相关的 v1/v2 方法与 `/nodes/{id}/config` 的 `put|post` 组合
> - 第 66+ 行汇总未覆盖 `/ippools`、`/notifications`、`/ids`、`/mynet/*`、`/users/*`、`/tenants/*`

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
| 17 | GET | `/user/tenants` | sanctum | ✅ | ✅ | ✅ | ✅ |
| 18 | POST | `/user/tenants/switch` | sanctum | ✅ | ✅ | — | ✅ |
| 19 | GET | `/nodes` | sanctum+tenant | ✅ | ✅ | ✅ | ✅ |
| 20 | POST | `/nodes/create` | sanctum+tenant | ✅ | ✅ | — | — |
| 21 | POST | `/nodes/quick-create` | sanctum+tenant | ✅ | — | — | — |
| 22 | GET | `/nodes/{id}` | sanctum+tenant | ✅ | ✅ | ✅ | ✅ |
| 23 | PATCH | `/nodes/{id}` | sanctum+tenant | ✅ | — | — | — |
| 24 | DELETE | `/nodes/{id}` | sanctum+tenant | ✅ | — | — | — |
| 25 | PATCH | `/nodes/{id}/status` | sanctum+tenant | ✅ | ✅ | ✅ | — |
| 26 | GET | `/nodes/{id}/config` (v1) | sanctum+tenant | — | — | ✅ | — |
| 27 | GET | `/nodes/{id}/configuration` (v2) | sanctum+tenant | ✅ | ✅* | — | ✅ |
| 28 | PUT | `/nodes/{id}/config` | sanctum+tenant | ✅ | — | — | — |
| 29 | GET | `/nodes/{id}/config-bundle` (v2) | sanctum+tenant | — | ✅ | ✅† | ✅ |
| 30 | GET | `/nodes/{id}/keys` | sanctum+tenant | ✅ | ✅ | ✅ | ✅ |
| 31 | POST | `/nodes/{id}/keys` (v2) | sanctum+tenant | — | — | ✅ | — |
| 32 | POST | `/nodes/{id}/keys/generate` (v1) | sanctum+tenant | ✅ | — | — | — |
| 33 | POST | `/nodes/{id}/keys/upload` (v1) | sanctum+tenant | ✅ | ✅ | — | — |
| 34 | DELETE | `/nodes/{id}/keys` | sanctum+tenant | ✅ | — | — | — |
| 35 | DELETE | `/nodes/{id}/keys/{keyId}` | sanctum+tenant | ✅ | — | — | — |
| 36 | PATCH | `/nodes/{id}/keys/{keyId}/status` | sanctum+tenant | ✅ | — | — | — |
| 37 | POST | `/nodes/{id}/router-keys` | sanctum+tenant | ✅ | ✅ | ✅ | ✅ |
| 38 | GET | `/nodes/{id}/deviceinfo` | sanctum+tenant | ✅ | — | — | — |
| 39 | POST | `/nodes/deviceinfo` (batch) | sanctum+tenant | ✅ | — | — | — |
| 40 | PATCH | `/nodes/{id}/deviceinfo` | sanctum+tenant | ✅ | ✅ | — | — |
| 41 | POST | `/nodes/{id}/operator` | sanctum+tenant | ✅ | — | — | — |
| 42 | GET | `/nodes/{id}/status` (v2) | sanctum+tenant | — | — | — | — |
| 43 | GET | `/services/indexes` | sanctum+tenant | ✅ | ✅* | ✅ | ✅ |
| 44 | — | （原 `GET /zones/services` 裸列表）**back 未注册裸 `/services`**，无对应端点 | — | — | — | — | — |
| 45 | GET | `/services/my` | sanctum+tenant | ✅ | — | — | — |
| 46 | GET | `/services/public` | sanctum+tenant | ✅ | — | — | — |
| 47 | GET | `/services/friends` | sanctum+tenant | ✅ | — | — | — |
| 48 | GET | `/services/anonymous` | sanctum+tenant | ✅ | — | — | — |
| 49 | GET | `/services/show/{id}` | sanctum+tenant | ✅ | — | — | — |
| 50 | POST | `/services/create` | sanctum+tenant | ✅ | — | — | — |
| 51 | PUT | `/services/update/{id}` | sanctum+tenant | ✅ | — | — | — |
| 52 | DELETE | `/services/delete/{id}` | sanctum+tenant | ✅ | — | — | — |
| 53 | POST | `/services/share/{id}` | sanctum+tenant | ✅ | — | — | — |
| 54 | GET | `/route/data` | sanctum+tenant | ✅ | — | — | — |
| 55 | GET | `/route/active` | sanctum+tenant | ✅ | — | — | — |
| 56 | GET | `/route/connections/{type}` | sanctum+tenant | ✅ | — | — | — |
| 57 | GET | `/route/friend-nodes` | sanctum+tenant | ✅ | — | — | — |
| 58 | POST | `/route/connect/{nodeId}` | sanctum+tenant | ✅ | — | — | — |
| 59 | POST | `/route/accept-request/{id}` | sanctum+tenant | ✅ | — | — | — |
| 60 | POST | `/route/reject-request/{id}` | sanctum+tenant | ✅ | — | — | — |
| 61 | DELETE | `/route/{id}` | sanctum+tenant | ✅ | — | — | — |
| 62 | POST | `/route/send-request` | sanctum+tenant | ✅ | — | — | — |
| 63 | GET | `/route/node/{id}` | sanctum+tenant | — | ✅* | ✅ | ✅ |
| 64 | POST | `/monitor/heartbeat` | hmac | — | ✅ | ✅ | — |
| 65 | PATCH | `/monitor/commands/{id}/result` | hmac | — | ✅ | — | — |
| 66+ | | 社交/通知/IP池等 | sanctum+tenant | ✅ | — | — | — |

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
