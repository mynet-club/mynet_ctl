# MyNet 生态系统知识库

> **维护位置**：`mynet_ctl/docs/knowledge-base/`  
> **维护原则**：本知识库是 MyNet 项目群的 **Single Source of Truth（SSOT）**，所有子项目的 `.github/copilot-instructions.md` 都应引用本知识库。

## 文件索引

### 核心参考（常用）

| 文件 | 内容 | 更新周期 |
|------|------|---------|
| [ecosystem.md](ecosystem.md) | 项目全景：10 个子项目定位、技术栈、域名、关系图 | 新增/删除项目时 |
| [architecture.md](architecture.md) | 系统架构：$MYNET_HOME 目录结构、认证、配置同步、分发协议 | 架构变更时 |
| [conventions.md](conventions.md) | 统一规范：命名、平台/架构矩阵、版本格式、目录结构、代码风格 | 规范变更时 |
| [mynet.md](mynet.md) | GNB 驱动与客户端技术参考：文件格式、安装流程、运行机制 | 协议/格式变更时 |
| [projects/](projects/) | 每个子项目的核心速查卡（10 个） | 各项目重大变更时 |

### 跨项目协议

| 文件 | 内容 | 更新周期 |
|------|------|---------|
| [api-contracts.md](api-contracts.md) | API 契约：config-bundle、心跳、apps.json、插件分发 | 接口变更时 |
| [../api/references/alignment-backlog.md](../api/references/alignment-backlog.md) | **API 偏差待办清单**（integration-plan-v2 §3 W3 的唯一执行清单） | 修复后回填 |
| [url-architecture.md](url-architecture.md) | URL 架构：分发路径、脚本版本化、Nginx 配置 | 路由变更时 |
| [brand-guide.md](brand-guide.md) | 品牌规范：色彩、字体、术语统一、组件约定 | 品牌变更时 |

### 运维与治理

| 文件 | 内容 | 更新周期 |
|------|------|---------|
| [deployment-registry.md](deployment-registry.md) | **部署清单 SSOT**：项目 → 服务器 → 路径 → 命令 | 部署变更时 |
| [cross-project-issues.md](cross-project-issues.md) | **跨项目一致性问题追踪簿**：偏差、待修复项 | 审计 / 修复时 |
| [audit-2026-05.md](audit-2026-05.md) | 2026-05 全站审计总报告 | 每次审计后 |
| [`../../.credentials/`](../../.credentials/) | **凭证集中目录**（模板入仓，真实凭证不入仓） | 凭证变更时 |

### 操作契约规范（跨平台 SSOT）

| 文件 | 内容 | 更新周期 |
|------|------|---------|
| [spec/README.md](spec/README.md) | 规范格式说明 + 维护工作流 | — |
| [spec/auth.md](spec/auth.md) | 登录 / 登出 / Token 刷新 | 认证流程变更时 |
| [spec/wizard.md](spec/wizard.md) | 安装向导：首次配置、Zone/节点选择、配置同步 | 向导流程变更时 |
| [spec/service.md](spec/service.md) | 服务三层启停（GNB → 路由 → 防火墙） | 启停逻辑变更时 |
| [spec/proxy.md](spec/proxy.md) | 代理分流：策略路由 + DNS 分流 | 代理逻辑变更时 |
| [spec/plugins.md](spec/plugins.md) | 插件生命周期（heartbeat、updater、proxy） | 插件接口变更时 |
| [spec/data-models.md](spec/data-models.md) | 所有 JSON/conf 数据结构（唯一定义处） | 数据格式变更时 |
| [spec/driver-abstraction.md](spec/driver-abstraction.md) | VPN driver 抽象层：抽象 bundle、驱动接口、gnb↔wg 映射 | 驱动/配置格式变更时 |

### 规划与迁移

| 文件 | 内容 | 状态 |
|------|------|------|
| **[integration-plan-v2.md](integration-plan-v2.md)** | **全生态整合规划 v2（当前权威）**：五层模型、四条工作流、SSOT 归属表 | **P0 执行中** |
| [integration-plan.md](integration-plan.md) | v1 集成计划：瓶颈分析、里程碑、ADR（旧线口径） | 已被 v2 取代 |
| [back-upshift-design.md](back-upshift-design.md) | back 业务上移设计：状态归属矩阵、上移/下沉判据 | 设计稿 |
| [client-migration-plan.md](client-migration-plan.md) | 客户端迁移计划（旧线 TUI→Client） | 历史 |
| [v2-upgrade-plan.md](v2-upgrade-plan.md) | V2 升级计划：API 迁移、URL 统一、时间线 | 执行中 |

## 使用方式

各子项目 `.github/copilot-instructions.md` 应包含：
1. **本项目概要**（从 `projects/{name}.md` 提取）
2. **生态关系引用**：指向本知识库
3. **项目自身特有规则**
