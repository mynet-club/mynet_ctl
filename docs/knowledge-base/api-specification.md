# MyNet 生态系统 API 完整规范

> **SSOT** — 由 mynet_ctl（配置中心）统一维护  
> 最后更新：2026-04-20  
> 本文档包含：完整端点规范、请求/响应 JSON 结构、数据类型、中间件、客户端匹配分析、调用矩阵

---

## 目录

1. [分工总览](#分工总览)
2. [mynet_back API 完整规范](#一mynet_back-api-完整规范)
   - [认证端点](#11-认证端点-authcontroller)
   - [用户端点](#12-用户端点-usercontroller)
   - [节点端点](#13-节点端点-nodecontroller)
   - [节点配置端点](#14-节点配置端点-nodeconfigcontroller)
   - [心跳监控端点](#15-心跳监控端点-nodeheartbeatcontroller)
   - [路由端点](#16-路由端点-routecontroller)
   - [Zone 服务端点](#17-zone-服务端点-zoneservicecontroller)
3. [mynet_ctl API 完整规范](#二mynet_ctl-api-完整规范)
4. [中间件矩阵](#三中间件矩阵)
5. [客户端匹配分析](#四客户端匹配分析)
6. [API 调用矩阵](#五api-调用矩阵)
7. [不匹配与 BUG 清单](#六不匹配与-bug-清单)
8. [错误格式规范](#七错误格式规范)

---

## 分工总览

```
┌─────────────────────────────────────────────────────────────────┐
│  应用层 — 前端/客户端（全部同级）                                   │
│                                                                 │
│  ┌──────────┐  ┌────────────────┐  ┌───────────┐                  │
│  │mynet_gui │  │ mynet_client   │  │mynet_lua  │                  │
│  │Web 管理   │  │ desktop 跨平台  │  │OpenWrt    │                  │
│  │社交/节点  │  │ Node.js CLI    │  │LuCI       │                  │
│  └────┬─────┘  └───────┬────────┘  └─────┬─────┘                  │
│       │                │               │                        │
│       └────────────────┼───────────────┘                        │
│                        ▼               ▼                        │
├─────────────────────────────────────────────────────────────────┤
│  平台层 — 后端服务                                                │
│  ┌──────────────┐  ┌──────────────┐                             │
│  │  mynet_back  │  │  mynet_ctl   │                             │
│  │api.mynet.club│  │ctl.mynet.club│                             │
│  │ API v2       │  │ 配置中心      │                             │
│  │              │  │              │                             │
│  │ · 用户认证    │  │ · 分发配置    │                             │
│  │ · Zone/节点   │  │ · binary下载  │                             │
│  │ · 路由/密钥   │  │ · 脚本/插件   │                             │
│  │ · 心跳监控    │  │ · 设备检测    │                             │
│  │ · 配置包生成  │  │ · API文档     │                             │
│  └──────────────┘  └──────────────┘                             │
└─────────────────────────────────────────────────────────────────┘
```

| 服务 | 域名 | API 前缀 | API 版本 | 职责 |
|------|------|----------|----------|------|
| **mynet_back** | api.mynet.club | `/api/v2/` | v2.x.y | 业务服务：认证、Zone、节点、路由、心跳 |
| **mynet_ctl** | ctl.mynet.club | `/api/v2/`* | v1.x.y | 配置中心：分发、下载、检测、Setup 代理 |

> *CTL 当前路由前缀为 `/api/v2/`（历史原因），API 概念版本为 v1。

### 客户端请求模式

```
客户端启动 →
  1. GET ctl.mynet.club/d/apps.json              ← CTL: 产品索引
  2. GET ctl.mynet.club/d/{product}/manifest.json ← CTL: 版本清单
  3. GET ctl.mynet.club/d/{product}/{file}.tgz    ← CTL: binary 下载
  4. POST api.mynet.club/api/v2/auth/login        ← back: 认证
  5. GET api.mynet.club/api/v2/user/zones         ← back: Zone 列表
  6. GET api.mynet.club/api/v2/nodes/{id}/config-bundle ← back: 配置包
  7. POST api.mynet.club/api/v2/monitor/heartbeat  ← back: 心跳上报
```

---

## 版本管理机制

路径前缀 `/api/v2/` 不变，通过 [docs/api-versions.json](../api-versions.json) 追踪精细版本：

```json
{
    "back_api": { "version": "2.0.0" },
    "ctl_api": { "version": "1.0.0" },
    "clients": {
        "mynet_gui": { "min_back_api": "2.0.0", "min_ctl_api": "1.0.0" },
        "mynet_lua": { "min_back_api": "2.0.0", "min_ctl_api": "1.0.0" },
        "mynet_client": { "min_back_api": "2.0.0", "min_ctl_api": "1.0.0" }
    }
}
```

---

## 一、mynet_back API 完整规范

### 认证方式

| 方式 | 适用范围 | 请求头 |
|------|----------|--------|
| Sanctum Token | 用户 API（`/auth/refresh-token` 及以下所有） | `Authorization: Bearer {token}` |
| Sanctum + Zone | 需要 Zone 上下文的 API | `Authorization: Bearer {token}` + `X-Zone-ID: {int}` |
| HMAC-SHA256 | 节点心跳（`/monitor/*`） | `X-Node-Id: {int}` + `X-Timestamp: {int}` + `X-Node-Signature: {base64}` |
| 无 | 公开端点（`/auth/login`, `/auth/register` 等） | — |

**Zone 头行为（`api.zone` 中间件）**：
- 必须发送 `X-Zone-ID`，值必须是当前用户所属的 Zone
- 验证失败返回 `403 ZONE_ACCESS_DENIED`
- 以下端点**不需要** Zone 头：`/auth/*`, `/user`, `/user/zones`, `/user/switch-zone`

---

### 1.1 认证端点 (AuthController)

#### POST /auth/login
```
Middleware: 无
Content-Type: application/json

Request:
{
  "email": string,                     // required, email格式
  "password": string,                  // required
  "device_name": string|null           // optional, max:255, default: "Unknown Device"
}

Response 200:
{
  "success": true,
  "message": "登录成功",
  "data": {
    "auth_token": string,              // Sanctum plaintext token
    "refresh_token": string,           // 60 chars random
    "auth_token_expires_in": int,      // 秒, default: 1800
    "refresh_token_expires_in": int,   // 秒, default: 604800
    "user": {
      "user_id": int,
      "name": string,
      "email": string,
      "zone_id": int|null,
      "user_role": string              // "user" | "admin" | "super_admin"
    }
  }
}

Response 401:
{
  "success": false,
  "message": "用户名或密码错误",
  "error_code": "INVALID_CREDENTIALS"
}

Response 403 (邮箱未验证):
{
  "success": false,
  "message": "请先验证您的邮箱...",
  "error_code": "4031",               // 或 "4032" (验证链接已过期)
  "data": {
    "resend_url": string,
    "action_required": "check_email" | "resend_activation",
    "registered_hours_ago": int,       // 4031 时
    "expires_in_hours": int,           // 4031 时
    "expired_hours": int               // 4032 时
  }
}
```

#### POST /auth/register
```
Middleware: 无

Request:
{
  "name": string,                      // required, max:255
  "email": string,                     // required, email, unique:users, max:255
  "password": string,                  // required, min:8
  "device_name": string|null,          // optional, max:255
  "invitation": string,               // required, max:255
  "activation_url": string            // required, url, max:2048
}

Response 201:
{
  "success": true,
  "message": "注册成功，请查收激活邮件完成账号激活",
  "data": {
    "user_id": int,
    "email": string,
    "message": "注册成功，请查收激活邮件"
  }
}
```

#### POST /auth/refresh-token
```
Middleware: auth:sanctum

Request:
{
  "refresh_token": string              // required
}

Response 200:
{
  "success": true,
  "message": "Token刷新成功",
  "data": {
    "auth_token": string,
    "refresh_token": string,
    "auth_token_expires_in": int,      // 秒
    "refresh_token_expires_in": int    // 秒
  }
}

Response 403:
{
  "success": false,
  "message": "Refresh Token无效或已过期",
  "error_code": "INVALID_REFRESH_TOKEN"
}
```

#### POST /auth/logout
```
Middleware: auth:sanctum

Request: 空

Response 200:
{
  "success": true,
  "message": "已退出登录",
  "data": null
}
```

#### GET /auth/verify-token
```
Middleware: auth:sanctum（无 Zone 头）

Response 200:
{
  "success": true,
  "data": {
    "valid": boolean,
    "current_zone": object|null,
    "default_zone": object|null,
    "user_id": int,
    "exp": int                         // token 过期时间戳
  }
}
```

#### POST /auth/forgot-password
```
Middleware: 无

Request:
{
  "email": string,                     // required, email
  "redirect_url": string              // required, url
}
```

#### POST /auth/reset-password
```
Middleware: 无

Request:
{
  "email": string,
  "token": string,
  "code": string,
  "password": string,
  "password_confirmation": string
}
```

#### POST /auth/activate-account
```
Middleware: 无

Request:
{
  "email": string,
  "user_id": int,
  "token": string,
  "code": string
}
```

#### POST /auth/validate-session
```
Middleware: auth:sanctum

Request:
{
  "user_id": int,
  "token_hash": string,
  "last_sync": int,
  "client_version": string,
  "timestamp": int
}
```

---

### 1.2 用户端点 (UserController)

#### GET /user
```
Middleware: auth:sanctum（无 Zone 头）

Response 200:
{
  "success": true,
  "message": "User profile retrieved successfully",
  "data": {
    "user_id": int,
    "name": string,
    "email": string,
    "phone": string|null,
    "signature": string|null,          // max:140
    "zone_id": int|null,
    "user_role": string,               // "user" | "admin" | "super_admin"
    "preferences": {
      "theme": "light" | "dark" | "auto",
      "language": "zh-CN" | "en-US",
      "timezone": string,
      "notifications_enabled": boolean
    },
    "created_at": string,              // ISO 8601
    "updated_at": string               // ISO 8601
  }
}

v1 差异: 返回简化版 (仅 id, name, email, zone_id)
```

#### GET /users/search
```
Middleware: auth:sanctum

Query: ?q={string}&limit={int}
  q: required, min:1, max:255
  limit: optional, min:1, max:50, default:20

Response 200:
{
  "success": true,
  "data": [
    {
      "id": int,
      "name": string,
      "email": string,
      "user_role": string,
      "zone_id": int|null,
      "display_name": string,          // "name (email) [管理员]"
      "badge": "admin" | "user"
    }
  ]
}
```

#### PUT /users/profile
```
Middleware: auth:sanctum

Request:
{
  "name": string|null,
  "phone": string|null,
  "signature": string|null,
  "preferences": {
    "theme": string|null,
    "language": string|null,
    "timezone": string|null
  }
}

Response 200:
{
  "success": true,
  "data": { ...更新后的用户数据... }
}
```

---

### 1.3 节点端点 (NodeController)

#### GET /nodes
```
Middleware: auth:sanctum, api.zone, throttle:api

Query:
  status: optional, in:active,inactive,suspended
  zone_id: optional (已通过中间件验证)
  search: optional, max:255
  page: optional, min:1
  per_page: optional, min:1, max:100

Response 200:
{
  "success": true,
  "message": "Nodes retrieved successfully",
  "data": {
    "nodes": [
      {
        "node_id": int,
        "node_name": string,
        "vpn_type": "gnb" | "wireguard" | "openvpn",
        "node_ip": string|null,        // 点分十进制 (long2ip)
        "node_type": int,
        "node_status": int,            // -1=未配置, 0=禁用, 1=就绪, 2=启用, 99=维护
        "created_at": string,          // ISO 8601
        "updated_at": string
      }
    ],
    "meta": {
      "total": int,
      "zone_id": int,
      "user_id": int,
      "filters_applied": object
    }
  }
}
```

#### POST /nodes/create
```
Middleware: auth:sanctum, api.zone

Request:
{
  "node_name": string,                 // required, min:3, max:255
  "vpn_type": string,                 // required, in:gnb,wireguard,openvpn
  "node_type": int|null,              // optional, min:1, max:255
  "description": string|null,         // max:1000
  "ip_pool_id": int|null
}

Response 201:
{
  "success": true,
  "message": "节点创建成功",
  "data": {
    "node_id": int,
    "node_name": string,
    "vpn_type": string,
    "node_type": int,
    "node_status": int,                // default: -1 (STATUS_UNCONFIGURED)
    "node_ip": string|null,
    "created_at": string
  }
}
```

#### POST /nodes/quick-create
```
Middleware: auth:sanctum, api.zone

Request:
{
  "node_name": string                  // required, min:3, max:255
}

Response 201:
{
  "success": true,
  "message": "节点一键创建成功",
  "data": {
    "node": {
      "node_id": int,
      "node_name": string,
      "vpn_type": "gnb",              // 默认 gnb
      "node_ip": string|null,
      "node_type": int,               // NODE_TYPE_EDGE
      "created_at": string
    },
    "config": object,                  // 默认配置
    "next_steps": [string]            // 后续步骤建议
  }
}
```

#### GET /nodes/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "message": "节点详情获取成功",
  "data": {
    "node_id": int,
    "node_name": string,
    "vpn_type": string,
    "node_ip": string|null,
    "node_type": int,
    "node_status": int,
    "node_app_version": string|null,
    "endpoint": string|null,
    "node_port": int|null,
    "node_remarks": string|null,
    "created_at": string,
    "updated_at": string
  }
}
```

#### PATCH /nodes/{id}
```
Middleware: auth:sanctum, api.zone

Request (所有字段可选):
{
  "name": string,                      // max:255 (别名: node_name)
  "vpn_type": string,                 // in:gnb,wireguard,openvpn
  "node_type": int,                   // min:1, max:255
  "node_app_version": string,         // max:50
  "server_host": string,              // max:255 (别名: endpoint)
  "server_port": int,                 // min:1024 (别名: node_port)
  "description": string,              // max:1000 (→ node_remarks)
  "status": string,                   // in:active,inactive,maintenance
  "is_active": boolean,
  "virtual_ip": string                // 有效 IPv4
}

Response 200:
{
  "success": true,
  "message": "节点更新成功",
  "data": { ...更新后的节点数据... }
}
```

#### DELETE /nodes/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "message": "节点删除成功", "data": null }
```

#### PATCH /nodes/{id}/status
```
Middleware: auth:sanctum, api.zone

Request:
{
  "status": int                        // required, in:-1,0,1,2,99
}

Response 200:
{
  "success": true,
  "message": "节点状态更新成功",
  "data": {
    "node_id": int,
    "node_status": int,
    "status_name": string
  }
}
```

#### GET /nodes/{id}/network-config (v1 独有)
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "data": {
    "ifname": string,
    "node_ip": string|null,
    "ip_group_sequence_number": int|null,
    "lan_ips": [string],
    "public_ips": [string],
    "index_servers": [object]
  }
}

v2: 合并到 GET /nodes/{id}/configuration?section=network
```

#### PUT /nodes/{id}/network-config
```
Middleware: auth:sanctum, api.zone

Request:
{
  "ifname": string|null,               // max:32
  "node_ip": string|null,             // IP 格式
  "ip_group_sequence_number": int|null, // min:1
  "lan_ips": [string]|null,
  "public_ips": [string]|null,
  "index_servers": [int|string|object] // service_id(int), ip:port(str), service_name(str)
}

Response 200:
{
  "success": true,
  "message": "网络配置更新成功",
  "data": {
    "...配置字段...",
    "index_servers": {
      "added": [object],
      "kept": [object],
      "removed": [object],
      "errors": [object],
      "total_processed": int
    }
  }
}
```

#### GET /nodes/{id}/deviceinfo
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "data": {
    "exists": boolean,
    "node_id": int,
    "device_info": {
      "cpu_model": string|null,
      "cpu_arch": string|null,
      "cpu_cores": int|null,
      "memory_total_mb": int|null,
      "os_name": string|null,
      "os_version": string|null,
      "kernel_version": string|null,
      "system_arch": string|null,
      "local_ips": string|null,        // JSON encoded array
      "public_ip": string|null,
      "nic_count": int|null
    } | null
  }
}
```

#### POST /nodes/deviceinfo (v2) | PUT /nodes/deviceinfo (v1)
```
Middleware: auth:sanctum, api.zone

Request:
{
  "node_ids": [int]                    // required, array, min:1, max:100
}

Response 200:
{
  "success": true,
  "data": {
    "total": int,
    "devices": [
      {
        "node_id": int,
        "exists": boolean,
        "device_info": object|null
      }
    ]
  }
}

v1: PUT /nodes/deviceinfo
v2: POST /nodes/deviceinfo
```

#### PATCH /nodes/{id}/deviceinfo
```
Middleware: auth:sanctum, api.zone

Request (所有字段可选):
{
  "cpu_model": string,                 // max:255
  "cpu_cores": int,                   // min:1
  "memory_total_mb": int,             // min:0
  "os_name": string,                  // max:128
  "os_version": string,               // max:128
  "kernel_version": string,           // max:128
  "local_ips": [string]|string,       // 数组或 JSON 字符串
  "public_ip": string,                // max:64
  "nic_count": int,                   // min:0
  "firewall_enabled": int,            // in:0,1
  "collected_at": string              // date 格式
}

Response 200:
{
  "success": true,
  "message": "设备信息已更新",
  "data": { "node_id": int, "device_info": {...} }
}
```

#### GET /nodes/{id}/keys
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "message": "节点密钥信息获取成功",
  "data": {
    "key_id": string,                  // UUID
    "node_id": int,
    "key_type": string,                // "gnbkey" | "wireguard" | "rsa"
    "public_key": string,              // 仅返回公钥
    "created_at": string,
    "expires_at": string|null,
    "status": "active" | "expired" | "revoked"
  }
}
```

#### POST /nodes/{id}/keys
```
v2 合并 v1 的 keys/generate + keys/upload

Middleware: auth:sanctum, api.zone

Request:
{
  "force_regenerate": boolean|null,
  "custom_private_key": string|null,   // max:4096
  "custom_public_key": string|null     // max:4096
}

Response 201:
{
  "success": true,
  "message": "节点密钥生成成功 (类型: gnbkey)",
  "data": {
    "key_id": string,                  // UUID
    "node_id": int,
    "key_type": string,
    "public_key": string,
    "private_key": string,             // 仅生成时返回一次！
    "created_at": string,
    "expires_at": string|null
  }
}

v1: POST /nodes/{id}/keys/generate 和 POST /nodes/{id}/keys/upload 分开
v2: 合并为 POST /nodes/{id}/keys
```

#### DELETE /nodes/{id}/keys
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "message": "节点密钥撤销成功", "data": { "revoked": boolean } }
```

#### POST /nodes/{id}/router-keys
```
Middleware: auth:sanctum, api.zone
Form Request: GetRouterKeysRequest

Request:
{
  "router_node_ids": [int]             // required, array, min:1, max:100
}

Response 200:
{
  "success": true,
  "message": "批量获取路由密钥成功",
  "data": {
    "keys": [
      {
        "router_node_id": int,
        "key_type": string,
        "public_key": string,
        "expires_at": string|null
      }
    ],
    "failed": [
      {
        "router_node_id": int,
        "error": string
      }
    ]
  }
}
```

#### GET /nodes/{id}/status (v2 only)
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "data": {
    "node_id": int,
    "current_status": {
      "code": int,
      "name": string,
      "description": string
    },
    "configuration_status": {
      "is_fully_configured": boolean,
      "missing_items": [string],       // "has_node_ip", "has_config", "has_keys"
      "completion_percentage": int      // 0-100
    },
    "can_enable": boolean,
    "status_updated": boolean,
    "status_changed": boolean,
    "available_transitions": { "int": "string" },
    "status_definitions": object,
    "next_steps": [string]
  }
}

v1: 拆分为 GET /nodes/{id}/status-check 和 GET /nodes/{id}/status-info
```

---

### 1.4 节点配置端点 (NodeConfigController)

#### GET /nodes/{id}/config (v1)
```
Middleware: auth:sanctum, api.zone

Query:
  render_conf: boolean|null            // true → 返回 text/plain
  version: string|null                 // VPN 版本, default: "default"
  include_sys: boolean|null            // 包含系统配置
  merge_config: boolean|null           // 合并所有配置层

Response 200 (render_conf=false):
{
  "success": true,
  "data": {
    "base": { ...基础配置... },
    "expert": { ...专家配置... },
    "sys": { ...系统配置... }          // include_sys=true 时
  }
}

Response 200 (render_conf=true, Content-Type: text/plain):
# GNB configuration file
nodeid 3722810996882027
ifname gnb_tun
listen 9001
mtu 1450
...
```

#### GET /nodes/{id}/configuration (v2, 合并 config + network-config)
```
Middleware: auth:sanctum, api.zone

Query:
  section: "vpn" | "network" | "all"   // default: "all"
  render_conf: boolean|null
  version: string|null
  include_sys: boolean|null

Response 200 (section=all):
{
  "success": true,
  "data": {
    "vpn": {
      "base": { ...基础配置... },
      "expert": { ...专家配置... },
      "sys": { ... }
    },
    "network": {
      "ifname": string,
      "node_ip": string|null,
      "lan_ips": [string],
      "public_ips": [string],
      "index_servers": [object]
    }
  }
}

Response 200 (render_conf=true, section=vpn, Content-Type: text/plain):
纯文本 node.conf 格式
```

#### PUT /nodes/{id}/config
```
Middleware: auth:sanctum, api.zone

Request (一维数据):
{
  "vpn_type": string|null,             // in:gnb,wireguard,openvpn
  "vpn_version": string|null,         // max:50
  "nodeid": int|null,
  "zone_id": int|null,
  "ifname": string|null,              // max:32
  "listen": int|null,                 // min:1, max:65535
  "mtu": int|null,                    // min:500, max:9000
  "multi_socket": "on"|"off"|null,
  "listen6": string|null,             // max:255
  "direct_forwarding": "on"|"off"|null,
  "unified_forwarding": "off"|"auto"|"force"|"super"|"hyper"|null,
  "keepalive": int|null,              // min:0, max:300
  "daemon": "on"|"off"|null,
  "zip": "auto"|"force"|null,
  "crypto": string|null,              // max:32
  "node_worker": int|null             // min:1, max:32
}

Response 200:
{
  "success": true,
  "message": "节点配置保存成功",
  "data": {
    "config_changed": boolean,
    "node_id": int,
    "vpn_type": string,
    "saved_config": object
  }
}
```

#### GET /nodes/{id}/config-bundle (v2 only)
```
Middleware: auth:sanctum, api.zone
Headers: If-None-Match (ETag 缓存)

Response 200:
{
  "success": true,
  "data": {
    "node_id": int,
    "zone_id": int,
    "version": string,                 // ISO 8601
    "files": {
      "node.conf": string,            // 纯文本 GNB 配置
      "address.conf": string,         // i|0|ip|port 格式
      "route.conf": string            // nodeId|ip|mask 格式
    },
    "keys": {
      "ed25519": {
        "{nodeId}": string             // hex 编码公钥
      },
      "security": {
        "private": string|null,        // hex 编码私钥
        "public": string               // hex 编码公钥
      }
    },
    "warnings": [                      // 非致命警告
      { "message": string }
    ]
  }
}

Response 304 (ETag 匹配):
(空响应体)

Response Headers:
  ETag: "{md5_hash}"
  Cache-Control: private, no-cache
```

> **重要**: config-bundle 内的 `files` 对象直接包含 node.conf/address.conf/route.conf 纯文本，
> 而非 `config`/`index_services`/`routes` 结构化数据。client 和 lua 都按 files 格式解析。

---

### 1.5 心跳监控端点 (NodeHeartbeatController)

#### POST /monitor/heartbeat
```
Middleware: node.heartbeat.auth (HMAC-SHA256, 非 Sanctum)

Request Headers:
  X-Node-Id: int                       // 节点 ID
  X-Timestamp: int                     // Unix 时间戳
  X-Node-Signature: string             // HMAC-SHA256 签名 (hex 或 base64)

签名算法:
  message = "{METHOD}|{path}|{timestamp}|{body_json}"
  key = hex_decode(node_public_key)    // 节点 Ed25519 公钥 hex
  signature = HMAC-SHA256(key, message)
  注: node_monitor 字段在签名前从 body 中剥离

Request Body:
{
  "node_id": int,                      // required, min:1
  "timestamp": int,                    // required, Unix 秒
  "cpu_usage": number,                 // required, 0.00-100.00
  "memory_usage": number,              // required, 0.00-100.00
  "disk_usage": number,                // required, 0.00-100.00
  "connection_count": int,             // required, min:0

  "vpn_interface": {                   // 旧格式 — 单个对象 (client 使用)
    "type": string,                    // in:gnb,wireguard
    "ifname": string,                  // max:50
    "status": "up" | "down",
    "ip": string|null,                 // max:50
    "tx_bytes": int|null,              // min:0
    "rx_bytes": int|null,              // min:0
    "peers_count": int|null            // min:0
  } | null,

  "vpn_interfaces": [                  // 新格式 — 数组 (lua 使用)
    {
      "type": string,
      "ifname": string,
      "status": "up" | "down",
      "ip": string|null,
      "tx_bytes": int|null,
      "rx_bytes": int|null,
      "peers_count": int|null
    }
  ] | null,

  "node_monitor": {                    // 可选扩展监控
    "node_self": {
      "is_running": boolean,           // required
      "connected_peers": int,          // required, min:0
      "total_peers": int,              // required, min:0
      "uptime_seconds": int,           // required, min:0
      "last_active_at": int,           // required, Unix 秒
      "rx_bytes": int,                 // required, min:0
      "tx_bytes": int,                 // required, min:0
      "wanip": string|null,            // max:100
      "wan_ipv4": string|null,         // max:100
      "wan_ipv6": string|null,         // max:150
      "addresses": [
        {
          "addr": string,              // required, max:150
          "type": "wan" | "local" | "available"
        }
      ]                                // max:3 items
    },
    "peers_status": [
      {
        "peer_id": int,                // required, min:1
        "conn_type": string,           // in:direct_ipv4,direct_ipv6,dual_stack,relay,offline,local,none
        "latency": number,             // required, min:0
        "wanip": string|null,          // max:100
        "rx": int,                     // required, min:0
        "tx": int,                     // required, min:0
        "seen": int                    // required, Unix 秒
      }
    ] | null
  } | null
}

Response 200:
{
  "success": true,
  "node_id": int,
  "node_status": int,                  // 节点当前状态码
  "server_time": int,                  // Unix 时间戳
  "commands": [
    {
      "id": string,                    // UUID
      "command": string,               // 原始命令类型
      "action": string,                // 映射后的 action
      "params": object|null,
      "created_at": int                // Unix 时间戳
    }
  ]                                    // 最多 1 条
}

Response 200 (VPN 接口全部 down):
{
  "success": true,
  "node_id": int,
  "node_status": int,
  "message": "VPN 接口全部 down，节点已标记为离线",
  "commands": []
}
```

**command action 类型**:
| action | 客户端行为 |
|--------|-----------|
| `config.refresh` | 重拉 config-bundle |
| `service.restart` | 重启完整服务 |
| `gnb.restart` | 仅重启 GNB |
| `gnb.start` / `gnb.stop` | 启停 GNB |
| `route.refresh` | 重新应用路由表 |
| `client.update` | 触发自更新 |
| `client.report` | 立即上报状态 |

#### PATCH /monitor/commands/{id}/result
```
Middleware: node.heartbeat.auth

Request Headers: 同 heartbeat

Request:
{
  "success": boolean,                  // required
  "message": string|null,             // max:10240 (超长自动截断)
  "result": object|null
}

Response 200:
{
  "success": true,
  "message": "指令状态已更新",
  "commands": [                        // 下一条待执行命令
    { "id": string, "command": string, "action": string, "params": object|null, "created_at": int }
  ]
}
```

---

### 1.6 路由端点 (RouteController)

#### POST /route
```
Middleware: auth:sanctum, api.zone

Request:
{
  "destination": string,               // required
  "gateway": string|null,
  "metric": int|null,                  // min:0
  "interface": string|null,
  "description": string|null,          // max:255
  "is_active": boolean
}

Response 201:
{
  "success": true,
  "message": "路由创建成功",
  "data": {
    "route_id": int,
    "destination": string,
    "gateway": string|null,
    "metric": int|null,
    "interface": string|null,
    "description": string|null,
    "is_active": boolean,
    "created_at": string
  }
}
```

#### GET /route/data
```
Middleware: auth:sanctum, api.zone

Response 200:
{
  "success": true,
  "data": {
    "total_routes": int,
    "active_routes": int,
    "pending_requests": int
  }
}
```

#### GET /route/statistics
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "data": { ...路由统计... } }
```

#### GET /route/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "data": { ...路由详情... } }

Response 403:
{ "success": false, "message": "Access denied", "error_code": "ZONE_ACCESS_DENIED" }
```

#### PUT|PATCH /route/{id}
```
Middleware: auth:sanctum, api.zone

Request (所有字段可选):
{
  "destination": string,
  "gateway": string|null,
  "metric": int|null,
  "interface": string|null,
  "description": string|null,          // max:255
  "is_active": boolean
}

v1: 仅 PUT
v2: PUT + PATCH 均支持
```

#### DELETE /route/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "message": "路由删除成功", "data": null }
```

#### POST /route/bulk/status
```
Middleware: auth:sanctum, api.zone

Request:
{
  "route_ids": [int],                  // required, array
  "is_active": boolean                 // required
}
```

#### DELETE /route/bulk
```
Middleware: auth:sanctum, api.zone

Request:
{ "route_ids": [int] }                // required, array
```

#### GET /route/node/{id} (render_conf 路由配置)
```
Middleware: auth:sanctum, api.zone

Query: ?render_conf=1

Response 200 (render_conf=1, Content-Type: text/plain):
# Route configuration
3722810996882027|10.182.236.178|255.255.255.0
8849123456789012|10.182.236.179|255.255.255.0
```

#### POST /route/send-request
```
Middleware: auth:sanctum, api.zone

Request:
{
  "source_node_id": int,               // required
  "target_node_id": int,              // required
  "target_user_id": int,              // required
  "vpn_type": string|null,            // in:gnb,wireguard
  "message": string|null              // max:500
}

Response 200:
{
  "success": true,
  "data": {
    "id": int,
    "source_node_id": string,
    "target_node_id": string,
    "target_user_id": string,
    "vpn_type": string,
    "message": string|null,
    "status": string,
    "created_at": string,
    "expires_at": string
  }
}
```

#### POST /route/accept-request/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "message": "路由申请已接受" }
```

#### POST /route/reject-request/{id}
```
Middleware: auth:sanctum, api.zone

Request:
{ "reason": string|null }

Response 200:
{ "success": true, "message": "路由申请已拒绝" }
```

---

### 1.7 Zone 服务端点 (ZoneServiceController)

#### GET /zones/services/indexes
```
Middleware: auth:sanctum, api.zone

Query: ?render_conf=1

Response 200 (render_conf != '1'):
{
  "success": true,
  "data": {
    "index_services": [
      {
        "service_id": int,
        "service_name": string,
        "service_type": "public" | "friend_shared",
        "service_address": string,
        "service_port": int,
        "service_protocol": string
      }
    ],
    "meta": {
      "zone_id": int,
      "total_services": int,
      "public_services": int,
      "friend_shared_services": int,
      "user_id": int,
      "zone_name": string|null
    }
  }
}

Response 200 (render_conf='1', Content-Type: text/plain):
i|0|52.221.22.241|9016
i|0|10.1.0.1|9001
```

#### POST /zones/services/create
```
Middleware: auth:sanctum, api.zone

Request:
{
  "service_name": string,              // required, max:100
  "service_type": string,             // required, in:music,video,netbridge (不可创建 gnb_index)
  "bind_mode": string|null,           // in:direct,dnat,lan,public,proxy
  "node_id": int|null,
  "service_address": string,           // required_without:service_url, max:255, 不含端口
  "service_url": string,              // required_without:service_address, max:255
  "service_port": int,                // required, 1-65535
  "service_protocol": string,         // required, in:tcp,udp,http,https,both
  "service_description": string|null,  // max:500
  "service_auth_required": boolean,
  "service_auth_type": string|null,   // in:none,basic,bearer,api_key,oauth
  "service_auth_username": string|null, // max:100
  "service_auth_password": string|null, // max:255
  "service_auth_token": string|null,   // max:500
  "service_priority": int|null,        // 0-999
  "service_max_connections": int|null  // 0-10000
}

Response 201:
{
  "success": true,
  "message": "服务创建成功",
  "data": {
    "service_id": int,
    "service_name": string,
    "service_type": string,
    "service_address": string,
    "service_port": int,
    "service_protocol": string,
    ...其他服务字段...
  }
}

Response 422 (端口含在地址中):
{
  "success": false,
  "message": "验证失败",
  "error_code": "VALIDATION_ERROR",
  "data": {
    "service_address": ["服务地址不应包含端口号，请将端口号填写到 service_port 字段"]
  }
}
```

#### PUT /zones/services/update/{id}
```
Middleware: auth:sanctum, api.zone

Request: 同 create (字段可选)

Response 200:
{ "success": true, "message": "服务更新成功", "data": null }
```

#### DELETE /zones/services/delete/{id}
```
Middleware: auth:sanctum, api.zone

Response 200:
{ "success": true, "message": "服务删除成功", "data": null }
```

#### POST /zones/services/share/{id}
```
Middleware: auth:sanctum, api.zone

Request:
{
  "friend_ids": [int]                  // required, exists:users,user_id
}

Response 200:
{ "success": true, "message": "服务分享成功", "data": null }
```

#### DELETE /zones/services/share/{id}
```
Middleware: auth:sanctum, api.zone

Request:
{
  "friend_id": int                     // required, exists:users,user_id
}

Response 200:
{ "success": true, "message": "取消分享成功", "data": null }
```

---

### 1.8 v1 vs v2 差异总结

| 功能 | v1 端点 | v2 端点 | 变更类型 |
|------|---------|---------|----------|
| 节点配置 | `GET /nodes/{id}/config` | `GET /nodes/{id}/configuration?section=` | 合并 |
| 网络配置 | `GET /nodes/{id}/network-config` | `GET /nodes/{id}/configuration?section=network` | 合并 |
| 密钥生成 | `POST /nodes/{id}/keys/generate` | `POST /nodes/{id}/keys` | 合并 |
| 密钥上传 | `POST /nodes/{id}/keys/upload` | `POST /nodes/{id}/keys` | 合并 |
| 状态检查 | `GET /nodes/{id}/status-check` | `GET /nodes/{id}/status` | 合并 |
| 状态信息 | `GET /nodes/{id}/status-info` | `GET /nodes/{id}/status` | 合并 |
| 批量设备信息 | `PUT /nodes/deviceinfo` | `POST /nodes/deviceinfo` | HTTP 方法改变 |
| 配置包 | ❌ | `GET /nodes/{id}/config-bundle` | v2 新增 |
| 统一配置 | ❌ | `GET /nodes/{id}/configuration` | v2 新增 |
| 路由统计 | `GET /route/stats` (桩) | ❌ | v2 移除 |

---

## 二、mynet_ctl API 完整规范

### 公开分发端点 (`/d/`, 无需认证)

| Method | Path | Content-Type | 说明 |
|--------|------|-------------|------|
| GET | `/d/apps.json` | application/json | 产品索引 |
| GET | `/d/conventions.json` | application/json | 命名规范 (SSOT) |
| GET | `/d/{product}/manifest.json` | application/json | 版本清单 |
| GET | `/d/{product}/plugins.json` | application/json | 插件清单 |
| GET | `/d/{product}/{filename}` | application/octet-stream | 二进制下载 (.tgz/.zip/.ipk) |
| GET | `/d/scripts/{name}` | text/plain | 通用脚本 (.sh/.bash/.py) |
| GET | `/d/scripts/{version}/{name}` | text/plain | 版本专属脚本 (fallback 到通用) |
| GET | `/d/plugins/{filename}` | application/octet-stream | 插件下载 |
| GET | `/d/plugins/mp/{name}` | text/plain | IP 列表 (interip.txt/chinaip.txt) |
| GET | `/d/detect/openwrt.sh` | text/plain | OpenWrt POSIX sh 检测脚本 |
| GET | `/d/proxy/install.sh` | text/plain | 代理安装脚本 |

### JSON API 端点 (`/api/v2/`)

#### Releases (版本管理)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/releases` | 无 | 版本列表 |
| GET | `/api/v2/releases/latest` | 无 | 最新版本 |
| GET | `/api/v2/releases/{id}/download/{filename}` | 无 | 版本下载 |
| POST | `/api/v2/releases` | `release:upload` | 上传版本 |
| DELETE | `/api/v2/releases/{id}` | `release:manage` | 下架版本 |

#### Plugins (插件管理)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/plugins` | 无 | 插件列表 |
| GET | `/api/v2/plugins/{id}/download/{filename}` | 无 | 插件下载 |
| POST | `/api/v2/plugins` | `plugin:upload` | 上传插件 |
| DELETE | `/api/v2/plugins/{id}` | `plugin:manage` | 下架插件 |

#### Scripts (脚本管理)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/scripts` | 无 | 脚本列表 |
| GET | `/api/v2/scripts/{id}/raw/{name}` | 无 | 脚本原文 |
| POST | `/api/v2/scripts` | `script:publish` | 发布脚本 |
| PUT | `/api/v2/scripts/{id}` | `script:publish` | 更新脚本 |
| DELETE | `/api/v2/scripts/{id}` | `script:manage` | 下架脚本 |

#### Detect (设备检测)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/detect/openwrt` | 无 | OpenWrt 硬件检测 |
| GET | `/api/v2/detect/arch-map` | 无 | 完整架构映射表 |

#### Proxy (代理)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/proxy/latest` | 无 | 代理最新版本 |
| GET | `/api/v2/proxy/iplists` | 无 | IP 列表下载 |
| GET | `/api/v2/proxy/iplists/meta` | 无 | IP 列表元数据 |

#### Setup (代理 mynet_back)
| Method | Path | 认证 | 参数 | 说明 |
|--------|------|------|------|------|
| GET | `/api/v2/setup/platforms` | 无 | — | 平台矩阵 |
| GET | `/api/v2/setup/zones` | Sanctum | — | Zone 列表 (代理 back) |
| POST | `/api/v2/setup/switch-zone` | Sanctum | `zone_id` (body) | 切换 Zone (代理 back) |
| GET | `/api/v2/setup/nodes` | Sanctum | `?zone_id=` (必填) | 节点列表 (代理 back) |
| GET | `/api/v2/setup/nodes/{id}` | Sanctum | `?zone_id=` (必填) | 节点详情 (代理 back) |
| GET | `/api/v2/setup/config-pack/{id}` | Sanctum | `?zone_id=`, `?platform=`, `?arch=` | 配置包 ZIP |

#### QuickConfig (快配, 不依赖 back)
| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/quickconfig/node-id` | 无 | 分配临时节点 ID |
| POST | `/api/v2/quickconfig/generate` | 无 | 生成快速配置 ZIP |

---

## 三、中间件矩阵

### mynet_back 中间件

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

### mynet_ctl 中间件

| 中间件 | 标识 | 作用 | 适用端点 |
|--------|------|------|----------|
| **API Token** | `auth.token:{ability}` | Bearer Token + abilities 验证 | 写操作 (upload/manage/publish) |
| **Admin Auth** | `auth.admin` | 管理员登录验证 | `/admin/*` |
| **无** | — | 公开访问 | `/d/*`, 读取 API, Setup 代理 |

---

## 四、客户端匹配分析

### 4.1 各客户端认证配置

| 项 | GUI (Quasar/Vue) | client (Node.js) | lua (LuCI) |
|----|-------------------|-------------------|-------------|
| HTTP 库 | axios | axios + 原生 http | curl CLI |
| User-Agent | 默认 | `mynet-client/{ver}` | `mynet-luci/2.1.0` |
| Base URL | `VITE_API_BASE_URL/api/v2` | `config.json:api_base_url` | `config.lua:api_base_url` |
| Token 存储 | localStorage | `credential.json` | `/etc/mynet/conf/credential.json` |
| X-Zone-ID 来源 | `localStorage:user_info.zone_id` | `zone.zone_id \|\| zone.id` | `credential.zone_id` |
| Token 刷新 | ✅ 自动 (401 拦截) | ✅ 自动 (401 拦截) | ✅ 手动 |
| X-Client-Request-ID | ❌ | ✅ `c-{ts}-{seq}` | ❌ |

### 4.2 登录字段匹配

| 字段 | back 返回 | GUI 解析 | client 解析 | lua 解析 |
|------|-----------|----------|-------------|----------|
| `data.auth_token` | ✅ string | ✅ | ✅ | ✅ |
| `data.refresh_token` | ✅ string | ✅ | ✅ | ✅ |
| `data.auth_token_expires_in` | ✅ int | ✅ | ✅ | ✅ |
| `data.refresh_token_expires_in` | ✅ int | ✅ | ✅ | — |
| `data.user.user_id` | ✅ int | ✅ (映射 id→user_id) | ✅ (fallback user.id) | ✅ (fallback user.id) |
| `data.user.name` | ✅ string | ✅ | ✅ → `user_name` | — |
| `data.user.email` | ✅ string | ✅ | ✅ | — |
| `data.user.zone_id` | ✅ int\|null | ✅ | ✅ | ✅ |
| `data.current_zone` | ⚠️ 仅 verify-token | — | ✅ (用于自动恢复) | — |
| `data.default_zone` | ⚠️ 仅 verify-token | — | ✅ (备选恢复) | — |

### 4.3 节点列表字段匹配

| 字段路径 | back 返回 | GUI | client | lua |
|----------|-----------|-----|--------|-----|
| `data.nodes` | ✅ array | ✅ | ✅ (多路径 fallback) | ✅ |
| `data.meta.total` | ✅ int | ✅ | — | — |
| `nodes[].node_id` | ✅ int | ✅ | ✅ (fallback .id) | ✅ |
| `nodes[].node_name` | ✅ string | ✅ | ✅ (fallback .name) | ✅ |
| `nodes[].vpn_type` | ✅ string | ✅ | ✅ | ✅ |
| `nodes[].node_status` | ✅ int | ✅ | ✅ (fallback .status) | ✅ |
| `nodes[].node_ip` | ✅ string\|null | ✅ | ✅ (fallback .virtual_ip) | ✅ |

### 4.4 config-bundle 字段匹配

| 字段 | back 返回 | client 解析 | lua 解析 | CTL ConfigPackService |
|------|-----------|-------------|----------|----------------------|
| `data.files["node.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.files["address.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.files["route.conf"]` | ✅ string | ✅ | ✅ | ✅ |
| `data.keys.ed25519.{nodeId}` | ✅ string(hex) | ✅ | ✅ | ✅ |
| `data.keys.security.private` | ✅ string\|null | ✅ | ✅ | ✅ |
| `data.keys.security.public` | ✅ string | ✅ | ✅ | ✅ |
| `data.warnings` | ✅ array | — | ✅ (显示警告) | — |
| `data.node_id` | ✅ int | ✅ | — | — |
| `data.zone_id` | ✅ int | ✅ | — | — |
| `data.version` | ✅ string | ✅ | — | — |

### 4.5 心跳字段匹配

| 字段 | back 期望 | client 发送 | lua 发送 |
|------|-----------|-------------|----------|
| `vpn_interface` (单数) | ✅ 接受 | ✅ 使用单数 | — |
| `vpn_interfaces` (复数) | ✅ 接受 | — | ✅ 使用复数 |
| `node_monitor` | ✅ 可选 | ✅ 发送 | — |
| `status` | — | — | ✅ 发送 "online"\|"offline" |
| `uptime` | — | — | ✅ 发送 int |

> back 同时接受 `vpn_interface`(单数) 和 `vpn_interfaces`(复数)，两端均匹配。

### 4.6 CTL 端点匹配

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

### 4.7 router-keys 字段匹配

| 特性 | back 期望/返回 | client | lua |
|------|---------------|--------|-----|
| 请求体 | `{ "router_node_ids": [int] }` | ✅ 发送 | ⚠️ 发送空 `{}` |
| 响应路径 | `data.keys[]` | ✅ (多路径 fallback) | ✅ (多路径 fallback) |
| key 结构 | `{ router_node_id, key_type, public_key }` | ⚠️ 期望 `{peerId: "hex"}` 格式 | ⚠️ 期望 `{peerId: "hex"}` 格式 |

---

## 五、API 调用矩阵

### 5.1 mynet_back API 使用总览

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

### 5.2 mynet_ctl API 使用总览

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

## 六、不匹配与 BUG 清单

### 🔴 BUG（必须修复）

| # | 项目 | 问题 | 位置 | 影响 |
|---|------|------|------|------|
| B1 | **mynet_lua** | `validator.lua` auto_repair 创建默认配置时写死 `/api/v1`，应为 `/api/v2` | `validator.lua:163,172` | 新用户默认配置指向 v1 API |
| B2 | **mynet_back** | config-bundle 缺少严格 `zone_id` 鉴权（用户可越 Zone 访问） | `NodeConfigController` | 安全漏洞 |
| B3 | **mynet_back** | router-keys 获取失败时静默继续，配置包缺少 peer 公钥 | `ConfigBundleService` | 节点间无法连接 |

### 🟠 不匹配（需要对齐）

| # | 项目 | 问题 | 详情 |
|---|------|------|------|
| M1 | **mynet_lua** | router-keys 发送空 body `{}` | back 期望 `{ "router_node_ids": [int] }`。lua 在 bundle fallback 时依赖 back 自动推导 |
| M2 | **client** | keys/upload 发送 `custom_public_key` | v2 合并为 `/keys`，字段名为 `custom_public_key`。需确认 v2 是否仍接受此字段 |
| M3 | **GUI** | 批量设备信息用 `PUT /nodes/deviceinfo` | v2 改为 `POST`。GUI 需更新为 POST |
| M4 | **GUI** | keys/generate 和 keys/upload 分开调用 | v2 合并为 `POST /nodes/{id}/keys`。GUI 仍使用 v1 路径 |
| M5 | **client** | router-keys 响应期望 `{peerId: "hex"}` 扁平格式 | back 返回 `{ keys: [{ router_node_id, public_key }], failed: [...] }`。client 需做格式转换 |

### 🟡 建议改进

| # | 项目 | 问题 | 建议 |
|---|------|------|------|
| S1 | mynet_lua | 未使用 config-bundle，每次同步 4 次 API | 升级到 config-bundle（已有 fallback 代码） |
| S2 | mynet_lua | 无心跳上报 | 实现 heartbeat 插件（已有 HMAC 签名代码） |
| S3 | mynet_client | 缺少 zone switch-zone 调用 | 检查是否真的不需要 |
| S4 | 全端 | 缺少 API 版本协商 | 参考 `api-versions.json`，客户端启动时检查 |

---

## 七、错误格式规范

### back API 错误格式

#### 格式 A: 一般错误
```json
{
  "success": false,
  "message": "用户名或密码错误",
  "error_code": "INVALID_CREDENTIALS"  // 可选
}
```

#### 格式 B: 验证失败 (422)
```json
{
  "success": false,
  "message": "验证失败",
  "errors": {
    "field_name": ["错误消息1", "错误消息2"]
  }
}
```

#### 格式 C: 业务异常 (DomainException)
```json
{
  "success": false,
  "message": "邀请码已过期或无效",
  "error_code": "DOMAIN_EXCEPTION",
  "data": { "context_key": "context_value" }
}
```

#### 已知 error_code 枚举
| error_code | HTTP | 含义 |
|------------|------|------|
| `INVALID_CREDENTIALS` | 401 | 登录凭证错误 |
| `4031` | 403 | 邮箱未验证 |
| `4032` | 403 | 验证链接已过期 |
| `INVALID_REFRESH_TOKEN` | 403 | Refresh Token 无效 |
| `ZONE_ACCESS_DENIED` | 403 | Zone 访问被拒 |
| `DOMAIN_EXCEPTION` | 422 | 业务逻辑异常 |
| `VALIDATION_ERROR` | 422 | 参数校验失败 |

### CTL API 错误格式
```json
{
  "error_type": "auth_invalid",
  "message": "Token 无效或已过期，请重新登录"
}
```

| error_type | 含义 |
|------------|------|
| `auth_invalid` | Token 无效/过期 |
| `permission_denied` | 无权限 |
| `not_found` | 资源不存在 |
| `validation_error` | 参数校验失败 |
| `backend_maintenance` | 后端维护中 |
| `backend_error` | 后端异常 |

### HTTP 状态码约定

| 码 | 含义 | 使用场景 |
|----|------|----------|
| 200 | OK | GET/PUT/PATCH/DELETE 成功 |
| 201 | Created | POST 创建成功 |
| 304 | Not Modified | config-bundle ETag 匹配 |
| 400 | Bad Request | 请求格式错误 |
| 401 | Unauthorized | Token 无效/过期 |
| 403 | Forbidden | 邮箱未验证、权限不足、Zone 隔离 |
| 404 | Not Found | 资源不存在 |
| 422 | Unprocessable Entity | 业务逻辑验证失败 |
| 429 | Too Many Requests | 频率限制 |
| 500 | Internal Server Error | 服务端错误 |
| 501 | Not Implemented | 功能未实现 |

---

## 数据类型约定

| 标记 | 含义 |
|------|------|
| `int` | 整数 |
| `number` | 浮点数 |
| `string` | 字符串 |
| `boolean` | `true` / `false` |
| `string\|null` | 可空字符串 |
| `[type]` | 数组 |
| `object` | JSON 对象 |
| `string (ISO 8601)` | 时间戳字符串 `2026-04-20T14:30:00+08:00` |
| `string (UUID)` | `550e8400-e29b-41d4-a716-446655440000` |
| `string (hex)` | 十六进制编码 `e9ca457c...` |
