# MyNet 生态系统全景

> 最后更新：2026-03-26

## 产品定位架构

```
┌─────────────────────────────────────────────────────────────────┐
│  应用层 — 面向终端用户的产品界面                                    │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────────────┐  │
│  │ mynet_gui│ │ mynet_web│ │mynet_tui │ │ mynet_lua          │  │
│  │ 管理前端  │ │ 官网     │ │ Go TUI   │ │ OpenWrt LuCI       │  │
│  │ Quasar   │ │ Vue3     │ │ 终端客户端│ │ 路由器客户端         │  │
│  └────┬─────┘ └─────┬────┘ └────┬─────┘ └────────┬───────────┘  │
│       │             │           │                 │              │
│  ┌────┴─────────────┴───────────┴─────────────────┴───────────┐  │
│  │              mynet_client (Node.js CLI + WebUI)             │  │
│  │              替代 tui 的跨平台统一客户端                       │  │
│  └─────────────────────────┬──────────────────────────────────┘  │
├─────────────────────────────┼────────────────────────────────────┤
│  平台层 — 后端服务与分发      │                                    │
│  ┌──────────┐ ┌─────────────┴──┐ ┌──────────────────────────┐   │
│  │mynet_back│ │ mynet_ctl      │ │ mynet_javis              │   │
│  │ API 后端 │ │ 分发中心       │ │ P2P AI 穿透（独立场景）     │   │
│  │ Laravel  │ │ Laravel        │ │ Laravel + OpenResty      │   │
│  └──────────┘ └────────────────┘ └──────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│  基础设施层 — 底层网络能力                                         │
│  ┌──────────┐ ┌──────────┐                                      │
│  │ mynet_gnb│ │ mynet_utc│                                      │
│  │ GNB P2P  │ │ UDP→TCP  │                                      │
│  │ C binary │ │ C binary │                                      │
│  └──────────┘ └──────────┘                                      │
└─────────────────────────────────────────────────────────────────┘
```

## 项目速查表

| 项目 | 技术栈 | 域名/部署 | 核心职责 |
|------|--------|----------|---------|
| **mynet_back** | Laravel 12 / PHP 8.2 / MySQL | api.mynet.club, admin.mynet.club | 用户·Zone·节点·路由·心跳·密钥管理（REST API） |
| **mynet_ctl** | Laravel 12 / PHP 8.2 / SQLite | ctl.mynet.club | 软件分发中心：binary/脚本/插件/manifest/设备检测 |
| **mynet_gui** | Quasar v2 / Vue 3 / TypeScript | my.mynet.club | Web 管理前端（SPA + Electron + Capacitor） |
| **mynet_web** | Vue 3 / TypeScript / Vite | www.mynet.club | 官网落地页：产品介绍·下载·文档 |
| **mynet_client** | Node.js 18+ / Express / Vue 3 | 本地 :7070 | 跨平台客户端（CLI + WebUI），替代 tui |
| **mynet_tui** | Go 1.21+ / tview / Cobra | 本地部署 | Go 终端客户端（TUI + CLI），计划迁移至 client |
| **mynet_lua** | Lua 5.1 / LuCI v3 | OpenWrt 路由器 | OpenWrt 专用管理界面（.ipk 包） |
| **mynet_gnb** | C / GNU Make | 由 CTL 分发 | GNB P2P VPN 核心（跨 9+ 平台编译） |
| **mynet_utc** | C / GNU Make | 由 CTL 分发 | UDP→TCP 隧道转换器 + 加速器（17-20 Gbps） |
| **mynet_javis** | Laravel 12 / OpenResty / Lua | myjarvis.ai | 基于 GNB 的个人 AI 反向代理服务（独立产品） |

## 项目间关系

### 认证链
```
mynet_back (Sanctum Token 发行方)
  ├── mynet_gui      → Bearer Token 认证
  ├── mynet_client   → Bearer Token 认证
  ├── mynet_tui      → Bearer Token 认证
  ├── mynet_lua      → Bearer Token（curl 调用）
  └── mynet_javis    → 复用 mynet_back 账号体系
```

### 配置数据流
```
mynet_back (Zone/节点/路由配置源)
  ↓ GET /api/v2/nodes/{id}/config-bundle
  mynet_client / mynet_tui / mynet_lua
  ↓ 写入本地
  driver/gnb/conf/{nodeId}/
    ├── node.conf
    ├── address.conf
    ├── route.conf
    ├── ed25519/*.public
    └── security/*.private
```

### 软件分发流
```
mynet_ctl (分发中心)
  ↓ GET /d/apps.json             → 产品索引
  ↓ GET /d/{product}/manifest.json → 版本清单
  ↓ GET /d/{product}/{file}.tgz    → 二进制下载
  mynet_client / mynet_tui / mynet_lua
```

### 心跳上报链
```
mynet_client (heartbeat plugin)
  ↓ POST /api/v2/monitor/heartbeat (HMAC-SHA256 签名)
  mynet_back
  ↓ 下发指令 (config.refresh / service.restart / gnb.start / gnb.stop)
  mynet_client (执行指令)
```

## 域名架构

| 域名 | 项目 | 环境 |
|------|------|------|
| api.mynet.club / api.mynet.local | mynet_back | prod / dev |
| admin.mynet.club | mynet_back (admin) | prod |
| ctl.mynet.club / ctl.mynet.local | mynet_ctl | prod / dev |
| my.mynet.club / my.mynet.local | mynet_gui | prod / dev |
| www.mynet.club / www.mynet.local | mynet_web | prod / dev |
| myjarvis.ai | mynet_javis | prod |
| *.myjarvis.ai | 用户子域名（动态路由） | prod |

## API 版本

- **mynet_back**: `/api/v1/` (legacy) + `/api/v2/` (current)，共享路由闭包
- **mynet_ctl**: `/api/v2/` (current)
- **mynet_javis**: `/api/v1/` + `/api/v2/`（共享实现）
