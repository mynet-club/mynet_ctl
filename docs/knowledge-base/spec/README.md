# MyNet 操作契约规范（Operation Contract Spec）

> **SSOT 位置**：`mynet_ctl/docs/knowledge-base/spec/`  
> **上次更新**：2026-09-21  
> **适用项目**：**mynet_agent**（`mynetd`，跨端一致逻辑的唯一实现）；`mynet_ui` / `mynet_openwrt` / `mynet_desktop`（只调不改的壳）  
> 旧线口径（`mynet_lua` / `mynet_client`）已冻结，见 [`../integration-plan-v2.md`](../integration-plan-v2.md) §2。

---

## 目的

本目录是跨端实现的**操作契约 SSOT**。  
所有跨平台必须一致的业务逻辑都定义在这里，各实现只管同步。

### 实现归属（目标线）

| 角色 | 仓 | 职责 |
|------|----|------|
| **唯一实现** | `mynet_agent`（`mynetd`） | spec 定义的业务逻辑在此实现 |
| 桌面 UI | `mynet_ui` | 只调 agent 本机 API（HTTP/ubus），不含业务 |
| 路由器壳 | `mynet_openwrt` | LuCI 菜单 / rpcd ACL，业务经 ubus 转 mynetd |
| 平台胶水 | `mynet_desktop` | 安装 / 服务 / 打包，不含业务 |

---

## 文件索引

| 文件 | 操作域 |
|------|--------|
| [auth.md](auth.md) | 登录 / 登出 / Token 刷新 |
| [wizard.md](wizard.md) | 安装向导：首次配置、Zone/节点选择、配置同步 |
| [service.md](service.md) | 服务三层启停（GNB 进程 → 路由注入 → 防火墙） |
| [proxy.md](proxy.md) | 代理分流：策略路由 + DNS 分流完整流程 |
| [plugins.md](plugins.md) | 插件生命周期（heartbeat、updater、proxy） |
| [data-models.md](data-models.md) | 所有数据结构（JSON/conf 格式，唯一定义处） |
| [debug-env.md](debug-env.md) | macOS 本地调试环境：vm（OpenWrt）/ vml（Debian+client） |

---

## 规范格式（每个操作契约的模板）

```
OPERATION: module.methodName()

PRECONDITION:
  - 前置条件 1
  - 前置条件 2

STEPS:
  1. 第一步（平台无关的描述）
  2. 第二步
     2a. 子步骤

POSTCONDITION:
  - 操作成功后的系统状态

ERROR PATHS:
  - 条件 → 错误码（Error("ERROR_CODE")）
  - 条件 → 回滚步骤

PLATFORM DIVERGENCE:
  - OpenWrt/Lua: 平台特有实现描述
  - Linux/macOS/Node: 平台特有实现描述
```

---

## 维护工作流

### 判断：这次改动需要更新 spec 吗？

```
改动的是...
├── 业务逻辑（操作步骤、状态机、错误码、数据格式）
│   └── YES → 必须更新 spec，然后同步另一端
├── 内部实现（换库、重构函数、改日志）
│   └── NO → 不需要更新 spec
└── 新功能
    └── YES → spec 先行，两端分别实现
```

### 场景 A：在某一端修了逻辑

```
1. 实现改动（lua 或 client）
2. 问：这改变了"操作契约"吗？
3. 是 → 更新 spec/ 对应文件
4. 在另一端的 Copilot session 中，说："参照 spec/xxx.md 同步这个操作"
```

### 场景 B：新功能规划

```
1. 在 spec/ 写操作契约（够指导实现即可，不需要完整）
2. 在 lua 实现
3. 在 client 实现
```

### 提交惯例

涉及契约变更的 commit，在 message 中加 `[spec]` 标签：

```
[spec] service: 三层启动增加 TUN 超时等待
feat(lua): 实现 TUN 接口等待逻辑
```

---

## 什么不写进 spec

- 平台特有的系统调用（`modprobe tun` vs `kextload`）
- 库选型（axios vs luci.http）
- UI 布局和交互细节
- 日志格式

这些放在各项目自己的文档里。
