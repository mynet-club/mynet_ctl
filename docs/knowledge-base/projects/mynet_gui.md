# mynet_gui — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Quasar v2 / Vue 3 / TypeScript 5.9 / Pinia / ECharts |
| **域名** | my.mynet.club |
| **本地** | my.mynet.local (dev :5173) |
| **角色** | Web 管理前端（SPA + Desktop + Mobile） |

## 核心职责
- 节点管理（CRUD、状态监控、拓扑图）
- Zone 管理（多租户数据隔离）
- 路由管理（配置、可视化）
- IP Pool 管理
- 用户管理（个人设置、权限）
- 实时监控（WebSocket via Laravel Reverb）
- 国际化（中文/英文）
- 主题切换（明/暗）

## 构建模式
- **SPA** — 默认 Web 应用
- **PWA** — 离线支持
- **Electron** — macOS / Windows / Linux 桌面
- **Capacitor** — iOS / Android 移动

## 架构
```
src/
├── boot/          初始化（i18n, auth, axios, websocket, theme）
├── composables/   30+ 组合式函数
├── services/      API 服务层
├── stores/        Pinia 状态管理
├── pages/         页面组件
├── types/         TypeScript 类型定义
└── i18n/          国际化资源
```

## 数据流入
- ← mynet_api (REST API + WebSocket)

## 环境变量
```
VITE_API_BASE_URL    → API 端点
VITE_WS_URL          → WebSocket
VITE_PUSHER_APP_KEY  → Reverb 推送
```
