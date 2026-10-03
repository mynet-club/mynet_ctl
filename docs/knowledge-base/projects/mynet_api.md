# mynet_api — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Laravel 12 / PHP 8.2 / MySQL 8.0 |
| **域名** | api.mynet.club (API) / console.mynet.club (租户管理，H4 裁定) |
| **本地** | api.mynet.local |
| **角色** | MyNet 组网服务核心后端 |

## 核心职责
- 用户注册/登录（Sanctum Token）
- Zone 多租户管理（创建、邀请、切换）
- 节点管理（CRUD、配置生成、密钥管理）
- 路由管理（node-to-node 路由规则）
- IP Pool 管理
- 节点心跳监控（HMAC-SHA256 签名认证）
- 实时推送（Laravel Reverb WebSocket）

## 架构
- DDD: `Domain/` → `Services/` → `Controllers/`（薄 Controller）
- 33 个 Eloquent Model
- 20+ REST API Controller
- 节点心跳独立中间件 `NodeHeartbeatAuthMiddleware`
- Zone 隔离中间件 `ApiZoneMiddleware`

## 关键 API (v2)
```
POST /api/v2/auth/login             → 登录
GET  /api/v2/zones                  → Zone 列表
GET  /api/v2/nodes                  → 节点列表
GET  /api/v2/nodes/{id}/config-bundle → 配置包（node.conf + keys）
POST /api/v2/monitor/heartbeat      → 节点心跳
GET  /api/v2/routes                 → 路由列表
```

## 数据流出
- → mynet_gui (REST API + WebSocket)
- → mynet_client (REST API: 认证 + 配置)
- → mynet_tui (REST API)
- → mynet_lua (REST API via curl)
- → mynet_javis (复用账号体系)
