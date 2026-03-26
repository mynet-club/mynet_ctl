# mynet_ctl — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Laravel 12 / PHP 8.2 / SQLite(dev) MySQL(prod) |
| **域名** | ctl.mynet.club |
| **本地** | ctl.mynet.local |
| **角色** | 软件分发中心 + 配置部署桥 |

## 核心职责
- Binary 分发（gnb, utc, tui, client）
- 脚本分发（install.sh, upgrade.sh, service scripts）
- 插件管理（heartbeat, proxy, updater）
- Manifest 接口（apps.json, manifest.json）
- OpenWrt 设备检测（1930+ 设备库）
- 配置部署（config-pack 一键下载）
- 管理后台（发布管理、Token 管理）

## URL 分类
```
/d/*         文件分发（CDN-ready, 无认证）
/api/v2/*    业务接口（JSON, 写操作需 Token）
/*           HTML 页面（CSRF 保护）
```

## 架构
- DDD: `Domain/` (Release/Script/Plugin) → `Services/` → `Controllers/`
- 模型: Release, Script, Plugin, ApiToken, OpenwrtDevice
- Migration: 部分表有 migration, 部分用 `ctl_tables.sql`

## 知识库
`docs/knowledge-base/` — MyNet 生态系统 SSOT 知识库所在地
