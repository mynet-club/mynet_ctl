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
| [url-architecture.md](url-architecture.md) | URL 架构：分发路径、脚本版本化、Nginx 配置 | 路由变更时 |
| [brand-guide.md](brand-guide.md) | 品牌规范：色彩、字体、术语统一、组件约定 | 品牌变更时 |

### 规划与迁移

| 文件 | 内容 | 状态 |
|------|------|------|
| [client-migration-plan.md](client-migration-plan.md) | 客户端迁移计划：TUI→Client 策略、架构决策 | 执行中 |
| [integration-plan.md](integration-plan.md) | 生态集成计划：瓶颈分析、里程碑、ADR | 执行中 |
| [v2-upgrade-plan.md](v2-upgrade-plan.md) | V2 升级计划：API 迁移、URL 统一、时间线 | 执行中 |

## 使用方式

各子项目 `.github/copilot-instructions.md` 应包含：
1. **本项目概要**（从 `projects/{name}.md` 提取）
2. **生态关系引用**：指向本知识库
3. **项目自身特有规则**
