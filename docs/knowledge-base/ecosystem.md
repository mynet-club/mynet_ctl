# MyNet 生态系统全景

> 最后更新：2026-10-01（按 workspace 实际 9 仓对齐）  
> **分层与仓角色的权威定义**：见 [`integration-plan-v2.md`](integration-plan-v2.md) §1 与 [`AGENTS.md`](AGENTS.md) §5 项目地图。  
> 一仓一张速查卡：[`projects/`](projects/)。

## 现状（目标线已落地）

**产品本体与平台适配分离**——业务只写一份，平台壳一平台一份：

| 层 | 仓 | 说明 |
|----|----|------|
| 产品本体 | **mynet_agent**（`mynetd` 通信 + `mynet` 执行器 + `mynet_gateway`） | 只写一份 |
| 平台适配 | **mynet_desktop**（Linux/macOS/Windows）、**mynet_openwrt**（LuCI 壳） | 一平台一份 |
| 业务权威 | **mynet_api** | 策略与状态归属 |
| 分发 / 知识库 | **mynet_ctl** | 产物分发 + `docs/knowledge-base/` SSOT |
| 用户界面 | **mynet_gui**（社区域）、**mynet_web**（官网） | gui 才是用户交互落点 |
| 底层能力 | **mynet_gnb**（opengnb fork）、**mynet_utc**（UDP→TCP） | 协议与隧道 |

**已退役**（文档在 [`projects/_retired/`](projects/_retired/)）：
`mynet_client` · `mynet_tui` · `mynet_lua` · `mynet_javis`（独立产品，已出 workspace）。

---

## 产品定位架构

```
┌─────────────────────────────────────────────────────────────────┐
│  应用层 — 面向终端用户                                             │
│  ┌──────────┐ ┌──────────┐                                      │
│  │ mynet_gui│ │ mynet_web│                                      │
│  │ 社区域/交互│ │ 官网/内容 │                                      │
│  │ Quasar   │ │ Vue3     │                                      │
│  └────┬─────┘ └──────────┘                                      │
│       │ cookie 会话                                              │
│       ▼                                                          │
├─────────────────────────────────────────────────────────────────┤
│  平台层 — 业务权威与分发                                           │
│  ┌──────────┐ ┌────────────────┐                                │
│  │mynet_api│ │ mynet_ctl      │                                │
│  │ 业务权威 │ │ 分发 + 知识库   │                                │
│  │ API v2   │ │ ctl.mynet.club │                                │
│  └────┬─────┘ └────────────────┘                                │
│       │ 节点面：X25519 DH + HMAC（复用驱动密钥）                    │
│       ▼                                                          │
├─────────────────────────────────────────────────────────────────┤
│  节点层 — 控制面一份源码，平台壳各一份                               │
│  ┌────────────────────────────────────────────┐                 │
│  │ mynet_agent                                 │                 │
│  │  mynetd(通信) · mynet(执行器) · gateway     │                 │
│  └───────────┬────────────────────┬───────────┘                 │
│              │                    │                              │
│  ┌───────────▼──────┐  ┌─────────▼─────────┐                   │
│  │ mynet_desktop    │  │ mynet_openwrt     │                   │
│  │ systemd/launchd  │  │ LuCI 壳 + ubus    │                   │
│  │ /NSIS 安装器      │  │ ipk               │                   │
│  └──────────────────┘  └───────────────────┘                   │
├─────────────────────────────────────────────────────────────────┤
│  基础设施层 — 底层网络能力                                         │
│  ┌──────────┐ ┌──────────┐                                      │
│  │ mynet_gnb│ │ mynet_utc│                                      │
│  │ GNB P2P  │ │ UDP→TCP  │                                      │
│  └──────────┘ └──────────┘                                      │
└─────────────────────────────────────────────────────────────────┘
```

## 项目速查表

| 项目 | 技术栈 | 域名/部署 | 核心职责 |
|------|--------|----------|---------|
| **mynet_agent** | Go | 节点本地（API :8096） | mynetd 通信 + mynet 执行器 + gateway + updater |
| **mynet_api** | Laravel 12 / PHP 8.2 / MySQL | api.mynet.club, console.mynet.club | 用户·Zone·节点·路由·心跳·密钥（业务权威） |
| **mynet_ctl** | Laravel 12 / PHP 8.2 / SQLite | ctl.mynet.club | 软件分发 + **知识库中枢** |
| **mynet_desktop** | systemd / launchd / NSIS | 三平台安装器 | 非 OpenWrt 平台适配：安装·服务·打包 |
| **mynet_openwrt** | Lua / LuCI / ubus / ipk | OpenWrt 路由器 | OpenWrt 平台适配（壳，业务迁往 mynetd） |
| **mynet_gui** | Quasar v2 / Vue 3 / TS | my.mynet.club | **用户交互前端**（社区域） |
| **mynet_web** | Vue 3 / TS / Vite | www.mynet.club | 官网 / 项目主页（**不是** WebUI） |
| **mynet_gnb** | C / GNU Make | 由 CTL 分发 | GNB P2P VPN 核心（上游 fork + 补丁包） |
| **mynet_utc** | C / GNU Make | 由 CTL 分发 | UDP→TCP 隧道转换器 |

## 项目间关系

### 认证链（三层三套凭证）

```
数据面   节点 ↔ 节点        gnb/wg 自身加密（驱动密钥）
节点面   节点 ↔ mynet_api   X25519 DH + HMAC（复用驱动密钥）
用户面   用户/gui ↔ back     cookie / 登录会话（无密钥）

mynet_gateway  ← 不鉴权，纯透传 + 连接状态表
```

### 配置数据流

```
mynet_api (Zone/节点/路由配置源)
  ↓ GET /api/v2/nodes/{id}/config-bundle
  mynet_agent（mynet 执行器）
  ↓ 写入本地
  driver/gnb/conf/{nodeId}/
    node.conf · address.conf · route.conf
    ed25519/*.public · security/*.private
```

### 软件分发流

```
mynet_ctl
  ↓ /d/apps.json · /d/{product}/manifest.json · /d/{product}/{file}.tgz
  mynet_agent · mynet_gnb · mynet_utc（产物前缀 mynet_agent_*）
```

### 心跳与指令

```
mynetd（心跳组装 · WS）
  ↓ POST /api/v2/monitor/heartbeat（HMAC-SHA256）
  mynet_api
  ↓ 下发指令（config.refresh / service.restart / …）
  mynetd 分派 → mynet 执行器
```

## 域名架构

| 域名 | 项目 | 环境 |
|------|------|------|
| api.mynet.club / api.mynet.local | mynet_api | prod / dev |
| console.mynet.club | mynet_api（**租户管理**） | prod |
| gw.mynet.club | mynet_gateway（节点面 WS + `/v1/relay`） | prod |
| admin.mynet.club | 框架 platform/operator SPA（**不是** back 管理面） | prod |
| ctl.mynet.club / ctl.mynet.local | mynet_ctl | prod / dev |
| my.mynet.club / my.mynet.local | mynet_gui | prod / dev |
| www.mynet.club / www.mynet.local | mynet_web | prod / dev |

> 本机 API / WebUI 端口 **8096**（8192 已被占用，勿漂移）。gateway 默认 8710。

## API 版本

- **mynet_api**: `/api/v2/` — 业务 API
- **mynet_ctl**: `/api/v2/`（历史路由前缀）— 配置/分发 API
- **mynet_agent localapi**: `127.0.0.1:8096` — 本机能力面，见 [`spec/local-api.md`](spec/local-api.md)

> 完整 API 规范 SSOT：[api-specification.md](api-specification.md) · 旧线升级契约（已归档）：[archive/2026-10/api-contracts.md](archive/2026-10/api-contracts.md)
