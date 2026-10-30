# MyNet 操作契约规范（Operation Contract Spec）

> **SSOT 位置**：`mynet_ctl/docs/knowledge-base/spec/`  
> **上次更新**：2026-10-04  
> **适用项目**：**mynet_agent**（`mynetd`，跨端一致逻辑的唯一实现）；`mynet_gui`（浏览器壳）/ `mynet_openwrt` / `mynet_desktop`（只调不改的壳）。`mynet_ui` 是历史仓（不在 workspace，页面素材已进 `mynet_web`）  
> 旧线口径（`mynet_lua` / `mynet_client`）已冻结，见 [`../integration-plan-v2.md`](../integration-plan-v2.md) §2。

---

## 目的

本目录是跨端实现的**操作契约 SSOT**。  
所有跨平台必须一致的业务逻辑都定义在这里，各实现只管同步。

### 实现归属（目标线）

| 角色 | 仓 | 职责 |
|------|----|------|
| **唯一实现** | `mynet_agent`（`mynetd`） | spec 定义的业务逻辑在此实现 |
| 桌面 UI | `mynet_gui` | 只调 agent 本机 API（HTTP/ubus），不含业务。`mynet_ui` 为历史仓（不在 workspace，页面素材已进 `mynet_web`） |
| 路由器壳 | `mynet_openwrt` | LuCI 菜单 / rpcd ACL，业务经 ubus 转 mynetd |
| 平台胶水 | `mynet_desktop` | 安装 / 服务 / 打包，不含业务 |

---

## 文件索引

| 文件 | 操作域 | 适用线 |
|------|--------|--------|
| [v1-delivery-closure-playbook.md](v1-delivery-closure-playbook.md) | **2026-10-15交付收口**：路径、发行资产/来源、真实流量与四平台验收；承接H/O/L既有任务 | 当前实施 |
| [control-plane.md](control-plane.md) | **控制面与传输**：gateway 通道、多点部署、借 node 访问、GUI 接入、心跳链路、proxy 下架 | 目标线 |
| [node-keying.md](node-keying.md) | **节点密钥与鉴权**：GNB ed25519 密钥派生 / `ed25519_key_exchange` DH / 心跳 HMAC 现状 / gateway 鉴权方案 | 目标线 |
| [unified-platform-architecture.md](unified-platform-architecture.md) | 统一平台架构 L1~L7 与实施计划（业务与驱动分层） | 目标线 |
| [driver-abstraction.md](driver-abstraction.md) | VPN Driver 抽象层：公共控制面 + 驱动专属 intent（gnb / wireguard） | 目标线 |
| [lifecycle-contract.md](lifecycle-contract.md) | **生命周期合同（H0）**：安装/卸载/服务/应用/升级的 A–F 分层、默认路径/服务名、原生包与归档矩阵、现状差距表 | **现行**（目标合同） |
| [node-upgrade-plan-180-182.md](node-upgrade-plan-180-182.md) | **现网升级部署计划（H6）**：`.180`(proxy server)/`.182`(proxy client) 的取证、产物、步骤、验证、回滚、Go-NoGo、待裁定 | **计划，未执行**（2026-10-10） |
| [node-upgrade-runbook-180-182.md](node-upgrade-runbook-180-182.md) | **今晚升级 Runbook（H6）**：开工前决策、commit 锚点、出包/取证/备份/步骤/验证/回滚/Go-NoGo/记录表 | **可执行清单，未执行**（2026-10-10 夜） |
| [schemas/bundle-envelope-v2.schema.json](schemas/bundle-envelope-v2.schema.json) | R2 线格式 SSOT：BundleEnvelope + NetworkIntent + GnbIntent + WireGuardIntent | 机器可读契约 |
| [schemas/tips-kb.schema.json](schemas/tips-kb.schema.json) | tips/小助手知识库载荷 SSOT：manifest + chunk 记录（见 [tips-kb-contract.md](tips-kb-contract.md)） | 机器可读契约 |
| [fixtures/bundle-envelope-v2/](fixtures/bundle-envelope-v2/) | R2 双端互解 fixtures（Go/PHP 共用，7 场景） | 共享测试数据 |
| [v1-permission-matrix.md](v1-permission-matrix.md) | **权限矩阵（P0 冻结件）**：R1–R6 边界规则 + 六表面×操作域判据 + 9 条实测洞 | 全线（后端 / agent / 壳） |
| [v1-error-codes.md](v1-error-codes.md) | **稳定错误码清单（P0 冻结件）**：7 套信封现状 + 现有码注册表 + 死码与分叉 + 冻结口径 | 全线（后端 / agent / 壳） |
| [local-api.md](local-api.md) | 本机能力面：mynetd HTTP/ubus 契约（GUI/OpenWrt 壳只调不改） | 目标线 ⚠️ 信封口径与代码不符，见 [v1-error-codes.md](v1-error-codes.md) §3-5 |
| [data-models.md](data-models.md) | 所有数据结构（JSON/conf 格式，唯一定义处） | 目标线 |
| [tips-kb-contract.md](tips-kb-contract.md) | **tips/小助手知识库**：蒸馏清单（md→chunk）+ tips-kb.json 载荷契约 + `/d/kb/` 分发与 id 合并语义 + tips 联动字段 | **现行**（契约定稿，实现批未开工） |
| [auth.md](auth.md) | 登录 / 登出 / Token 刷新 | ⚠️ **冻结（旧线）** |
| [wizard.md](wizard.md) | 安装向导：首次配置、Zone/节点选择、配置同步 | ⚠️ **冻结（旧线）** |
| [service.md](service.md) | 服务三层启停（GNB 进程 → 路由注入 → 防火墙） | ⚠️ **冻结（旧线）** |
| [proxy-split.md](proxy-split.md) | **代理分流（新线）**：三角色、双控、DNS 驱动域名分流、IP 兜底 | **现行** |
| [proxy.md](proxy.md) | 代理分流操作契约 | ⚠️ **冻结（旧线）**，新线见 [proxy-split.md](proxy-split.md) |
| [plugins.md](plugins.md) | 插件生命周期（heartbeat、updater、proxy）；心跳 HMAC 签名细节 | ⚠️ **冻结（旧线）** |
| [debug-env.md](debug-env.md) | macOS 本地调试环境：vm（OpenWrt）/ vml（Debian+client） | ⚠️ **冻结（旧线）** |

> ⚠️ 标注「冻结（旧线）」的文件描述的是 `mynet_lua` / `mynet_client` 的行为，
> **不是当前实现的依据**——它们保留下来只为归档与对照。每个文件顶部都写明了目标线的
> 对应契约在哪。冻结策略见 [`../integration-plan-v2.md`](../integration-plan-v2.md) §2.2。
>
> 这层标注是必须的：本索引声明唯一实现是 `mynet_agent`，而此前翻到 `service.md`
> 之类会读到一个已经没人维护的旧线做法，照着实现就会做出旧形态。

### 后端侧专项（实现进度类）

| 文件 | 内容 |
|------|------|
| [back-baggage-cleanup.md](back-baggage-cleanup.md) | 后端历史包袱清理执行进度与决策记录 |
| [back-primary-key-migration.md](back-primary-key-migration.md) | 主键改造：全局 ID 直接作 PK |

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
