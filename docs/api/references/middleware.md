# 中间件矩阵

> Source of Truth: mynet_ctl/docs/api/references/middleware.md

---

## mynet_back 中间件

| 中间件 | 标识 | 作用 | 适用端点 |
|--------|------|------|----------|
| **Sanctum Auth** | `auth:sanctum` | Bearer Token 验证 | 除 /auth/login,register 外所有 |
| **Zone Context** | `api.zone` | X-Zone-ID 头验证，确保用户属于该 Zone | 节点/路由/服务等需 Zone 上下文的端点 |
| **Heartbeat Auth** | `node.heartbeat.auth` | HMAC-SHA256 签名验证 | `/monitor/*` |
| **Throttle** | `throttle:api` | 请求频率限制 | 大部分认证端点 |
| **Admin Access** | `admin.access` | 管理员权限验证 | 管理后台 |
| **Admin Security** | `admin.security` | 管理安全策略 | 管理后台 |
| **WebSocket Auth** | `websocket.auth` | WebSocket 认证 | WS 端点 |
| **API Logger** | `ApiRequestLogger` | 请求日志记录 | 全局 |
| **Frontend Zone** | `FrontendZoneMiddleware` | 前端 Zone 上下文 | Web 端 |

### 中间件组合模式

| 端点类型 | 中间件组合 | 说明 |
|----------|-----------|------|
| 公开端点 | 无 | `/auth/login`, `/auth/register` |
| 用户端点 (无 Zone) | `auth:sanctum` | `/user`, `/user/zones`, `/user/switch-zone` |
| 用户端点 (需 Zone) | `auth:sanctum, api.zone, throttle:api` | `/nodes`, `/route`, `/zones/services` |
| 心跳端点 | `node.heartbeat.auth` | `/monitor/heartbeat`, `/monitor/commands/{id}/result` |

### Zone Context 中间件详解

`api.zone` 中间件从 `X-Zone-ID` 请求头中读取 Zone ID，验证：
1. 头存在且为有效整数
2. 当前用户属于该 Zone（通过 zone_members 表）
3. Zone 处于活跃状态

失败时返回 `403 ZONE_ACCESS_DENIED`。

### HMAC-SHA256 心跳认证详解

心跳端点使用独立的签名认证（非 Sanctum），需要三个请求头：

| 头 | 说明 |
|----|------|
| `X-Node-Id` | 节点 ID |
| `X-Timestamp` | Unix 时间戳（±300s 有效） |
| `X-Node-Signature` | HMAC-SHA256 签名 |

签名计算：
```
message = "{METHOD}|{path}|{timestamp}|{body_json}"
key     = hex_decode(node_public_key)
```

**注意**：签名前从 body 中**剥离** `node_monitor` 字段，因为该字段体积大且非关键。

---

## mynet_ctl 中间件

| 中间件 | 标识 | 作用 | 适用端点 |
|--------|------|------|----------|
| **API Token** | `auth.token:{ability}` | Bearer Token + abilities 验证 | 写操作 (upload/manage/publish) |
| **Admin Auth** | `auth.admin` | 管理员登录验证 | `/admin/*` |
| **无** | — | 公开访问 | `/d/*`, 读取 API, Setup 代理 |

### CTL Token 权限 (abilities)

| ability | 说明 |
|---------|------|
| `release:upload` | 上传版本 |
| `release:manage` | 删除版本 |
| `plugin:upload` | 上传插件 |
| `plugin:manage` | 管理插件 |
| `script:publish` | 发布脚本 |
| `script:manage` | 管理脚本 |
| `*` | 全部权限 |

Token 存储在 `api_tokens` 表，使用 SHA256 哈希。每次写请求：
1. 从 `Authorization: Bearer {token}` 提取 plaintext token
2. SHA256(token) 查表
3. 检查 `abilities` JSON 数组是否包含所需权限
4. 检查 `expires_at` 是否过期

### Setup 代理认证

Setup 端点（`/api/v2/setup/*`）直接透传客户端的 Sanctum Token 给 mynet_back，CTL 不存储也不验证该 Token。认证成功与否完全取决于 back 的响应。
