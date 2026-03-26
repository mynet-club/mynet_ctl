# mynet_tui — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Go 1.21+ / tview / tcell / Cobra |
| **部署** | 本地 CLI 工具 |
| **角色** | Go 终端客户端（TUI + CLI） |
| **状态** | 计划迁移至 mynet_client (Node.js) |

## 核心职责
- 用户认证（登录/登出）
- Zone 切换
- 节点配置同步
- GNB 进程启停与监控
- 系统状态监控（CPU/内存/磁盘）
- 交互式 TUI 界面

## 架构（DDD 五层）
```
cmd/              CLI 命令（Cobra）
internal/
├── tui/          展示层（tview/tcell）
├── application/  业务逻辑
├── domain/       纯业务模型
├── infrastructure/ 外部依赖（API, storage, system）
└── composition/  依赖注入
```

## 平台支持
- macOS (x86_64, ARM64)
- Linux (x86_64, ARM64, ARM32)
- OpenWrt (MIPS, ARM, x86)
- Windows (部分支持)

## 脚本系统
```
scripts/
├── common/    网络管理、VPN 控制、配置
├── linux/     iptables/nftables、路由
└── openwrt/   UCI 网络管理
```

## 数据流入
- ← mynet_back (认证 + 配置)
- ← mynet_ctl (binary 下载)
