# mynet_lua — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Lua 5.1 / LuCI v3 / OpenWrt |
| **包名** | luci-app-mynet v1.0.0 |
| **部署** | OpenWrt 路由器（.ipk 包） |
| **角色** | OpenWrt 专用管理界面 |

## 核心职责
- 登录认证（email/password, Token 持久化）
- Zone 管理（列表、选择）
- 节点管理（列表、详情、状态）
- 配置同步（下载 node.conf/route.conf/address.conf）
- GNB 服务启停
- 系统状态监控
- GNB 自动检测与安装

## 架构（LuCI MVC）
```
luasrc/
├── controller/mynet.lua       URL 路由
├── model/mynet/
│   ├── util.lua               文件 I/O、JSON、Shell
│   ├── api.lua                HTTP 客户端（curl）
│   ├── auth.lua               认证
│   ├── config.lua             配置管理
│   ├── zone.lua               Zone 操作
│   ├── node.lua               节点操作
│   ├── system.lua             平台检测
│   └── gnb_installer.lua      GNB 安装
└── view/mynet/                HTM 模板
```

## 数据流入
- ← mynet_api (认证 + 配置, via curl)
- ← mynet_ctl (binary 下载)

## 特殊说明
- OpenWrt 路由器无法运行 Node.js/Go，故保留 Lua 实现
- 使用 curl 调用 REST API（非 HTTP 库）
- UCI 集成用于 OpenWrt 网络配置
