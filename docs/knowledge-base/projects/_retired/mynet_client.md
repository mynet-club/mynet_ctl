# mynet_client — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Node.js 18+ / Express 4 / Vue 3 / Vite / TailwindCSS |
| **版本** | 2.0.0 |
| **部署** | 本地 :7070 |
| **角色** | 跨平台统一客户端（CLI + WebUI），替代 mynet_tui |

## 核心职责
- 用户认证（登录/登出/Token 自动刷新）
- Zone 切换
- 节点管理（列表、详情、选择）
- 配置同步（config-bundle → 本地文件）
- VPN 服务启停（三层: GNB进程 → 路由注入 → 防火墙）
- 插件系统（heartbeat, updater）
- WebSocket 实时状态推送

## 目录结构
```
src/
├── api/         Axios API 客户端
├── cli/         Commander.js CLI
├── config/      配置管理
├── plugins/     插件（heartbeat, updater）
├── server/      Express WebUI 后端
└── service/     VPN 服务管理
    ├── manager.js       核心：安装/启停/状态
    ├── checker.js       配置校验
    ├── config-sync.js   配置同步
    ├── gnb-monitor.js   GNB 状态监控
    └── platform/        平台适配（darwin/linux/windows）
web/               Vue 3 前端
```

## 本地目录结构
```
~/.config/mynet/
├── config.json          用户配置
├── driver/gnb/bin/gnb   GNB 二进制
├── driver/gnb/conf/     节点配置
└── logs/                日志
```

## 关键技术点
- **Root 权限**：启动 VPN 需要 sudo（TUN 接口 + 路由 + 防火墙）
- **Driver 架构**：`driver/{vpnType}/` 支持多驱动扩展
- **自动迁移**：旧 `bin/` + `nodes/` 自动迁移到 `driver/gnb/{bin,conf}/`
- **daemon off**：自动 patch node.conf，Node.js 管理进程生命周期
- **Peer Keys**：启动时自动获取缺失的 ed25519 公钥

## 数据流入
- ← mynet_api (认证 + 配置)
- ← mynet_ctl (binary 下载)
