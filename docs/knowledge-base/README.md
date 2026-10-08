# MyNet 生态系统知识库

> **维护位置**：`mynet_ctl/docs/knowledge-base/`  
> **维护原则**：本知识库是 MyNet 项目群的 **Single Source of Truth（SSOT）**，所有子项目的 agent 说明都应引用本知识库。
> 
> **状态列**：`现行` = 以它为准 ｜ `冻结` = 不再改，仍可查 ｜ `已被取代` = 有后继 ｜ `归档` = 过程记录，不再改
> 
> 目录级完整盘点见 [docs-census-20261001.md](docs-census-20261001.md)。

---

## 0. 开工必读（新会话从这里进）

| 文件 | 回答的问题 | 状态 |
|------|-----------|------|
| [project-memory.md](project-memory.md) | **当前状态与持续约定** — UTC 搁置、API 更名、小闭环入口 | 现行 |
| [spec/proxy-permission-loop-playbook.md](spec/proxy-permission-loop-playbook.md) | **当前小闭环** — 出口授权到应用与撤销的验收 | 现行 |
| [AGENTS.md](AGENTS.md) | **怎么干活** — 硬约束 · 工作方法 · 安全红线 · 坑 · DoD | 现行 |
| [mynet-overview.md](mynet-overview.md) | **为什么这么设计** — 九条主线、L1–L7、决策记录 | 现行 |
| [repo-migration.md](repo-migration.md) | **仓库怎么建、代码怎么搬** — 9 阶段 + 搬运清单 + 隐性知识 | 现行 |
| [dev-plan.md](dev-plan.md) | **建完做什么** — 64 项计划顺序表（S 段跟踪入口） | 现行 |
| [long-range-plan-deep.md](long-range-plan-deep.md) | **现在真正该做什么** — V0–V5 波次 + 人工闸门 + 反纸面达成五条 | 现行 |
| [cross-project-issues.md](cross-project-issues.md) | **欠了什么债** — CPI 台账（发现即记，修好移入已修复） | 现行 |
| [upstream-dsplat-issues.md](upstream-dsplat-issues.md) | **哪些是框架的锅** — 上游缺陷 + 可粘贴提示词 | 现行 |

任务 ID 唯一标识在 `dev-plan.md` §3（S 段）；R/T 段见 `long-range-plan.md` / `long-range-plan-t.md`；**V 段（当前）见 `long-range-plan-deep.md` §4**。

---

## 1. 规范 SSOT（长期有效，变更时改这里）

### 1.1 架构与约定

| 文件 | 内容 | 状态 |
|------|------|------|
| [spec/unified-platform-architecture.md](spec/unified-platform-architecture.md) | **L1–L7 统一平台架构权威** + 实施计划 | 现行 |
| [architecture.md](architecture.md) | 系统架构总览：启动三层 / `$MYNET_HOME` / 认证 / 同步 / 分发 / 数据库 | 现行 |
| [conventions.md](conventions.md) | **命名/平台/架构/版本/代码风格 SSOT** | 现行 |
| [brand-guide.md](brand-guide.md) | 品牌视觉与术语：色彩、字体、术语统一、组件约定 | 现行 |
| [ecosystem.md](ecosystem.md) | 项目全景、技术栈、域名、关系图 | 现行 |
| [mynet.md](mynet.md) | GNB 驱动与客户端技术参考：文件格式、安装流程、运行机制 | 现行 |
| [url-architecture.md](url-architecture.md) | URL 架构：分发路径、脚本版本化、Nginx 配置 | 现行 |

### 1.2 操作契约（`spec/`，跨平台）

| 文件 | 内容 | 状态 |
|------|------|------|
| [spec/README.md](spec/README.md) | 规范格式说明 + 维护工作流 + 实现归属映射 | 现行 |
| [spec/control-plane.md](spec/control-plane.md) | 控制面与传输 | 现行 |
| [spec/node-keying.md](spec/node-keying.md) | 节点密钥与鉴权 | 现行 |
| [spec/driver-abstraction.md](spec/driver-abstraction.md) | VPN driver 抽象层：抽象 bundle、驱动接口、gnb↔wg 映射 | 现行 |
| [spec/local-api.md](spec/local-api.md) | 本机能力面（local-api） | 现行 |
| [spec/paths.md](spec/paths.md) | **路径 SSOT** — 存储 / 运行 / 投影 / 运行态 | 现行 |
| [spec/data-models.md](spec/data-models.md) | 所有 JSON/conf 数据结构（唯一定义处） | 现行 |
| [spec/proxy-split.md](spec/proxy-split.md) | **代理分流（新线）** | 现行 |
| [spec/route-apply-unification.md](spec/route-apply-unification.md) | 路由写入归一（L6 单入口） | 现行 |
| [spec/route-firewall-split.md](spec/route-firewall-split.md) | 路由 / 防火墙分工 — L5 驱动 vs L6 系统 | 现行 |
| [spec/proxy-conf-design.md](spec/proxy-conf-design.md) | proxy.conf 设计 — route.conf 延续 + 热读取（v3） | 现行 |
| [spec/auth.md](spec/auth.md) | 登录 / 登出 / Token 刷新 | 现行 |
| [spec/wizard.md](spec/wizard.md) | 安装向导：首次配置、Zone/节点选择、配置同步 | 现行 |
| [spec/service.md](spec/service.md) | 服务三层启停（GNB → 路由 → 防火墙） | 现行 |
| [spec/plugins.md](spec/plugins.md) | 插件生命周期（heartbeat、updater、proxy） | 现行 |
| [spec/debug-env.md](spec/debug-env.md) | macOS 本地调试环境（vm / vml） | 现行 |
| [spec/node-status-gate.md](spec/node-status-gate.md) | `node_status` 谁说了算 — GET 只读（已执行）/ 心跳无条件写 1（保持） | **已裁决**（2026-10-04） |
| [spec/zone-removal.md](spec/zone-removal.md) | Zone 删除 — 实为「改名 + 两件残留物」；裁定改名保留、假租户兜底改指真实平台租户 | **已裁决**（2026-10-04） |
| [spec/proxy.md](spec/proxy.md) | 代理分流（旧线） | **冻结** → 改用 `proxy-split.md` |
| [spec/back-baggage-cleanup.md](spec/back-baggage-cleanup.md) | mynet_api 历史包袱清理 | 归档 |
| [spec/back-primary-key-migration.md](spec/back-primary-key-migration.md) | 主键改造 — 全局 ID 直接作 PK | 归档 |

### 1.3 运维与治理

| 文件 | 内容 | 状态 |
|------|------|------|
| [deployment-registry.md](deployment-registry.md) | **部署清单 SSOT**：项目 → 服务器 → 路径 → 命令 | 现行 |
| [skill-kit.md](skill-kit.md) | **管理技能生成包**：目标/查配置/调设置 + 安全总则 + skill 生成与自升级（自描述，含升级地址） | 现行 |
| [troubleshoot-connectivity.md](troubleshoot-connectivity.md) | 连通性排错手册 — A–E 五步法 | 现行 |
| [node-connectivity-checklist.md](node-connectivity-checklist.md) | GNB 密钥实现对照 + 节点连通性检查模型 | 现行 |
| [`../../.credentials/`](../../.credentials/) | 凭证集中目录（模板入仓，真实凭证不入仓） | 现行 |

### 1.4 API

| 文件 | 内容 | 状态 |
|------|------|------|
| [api-contracts.md](api-contracts.md) | 升级 API 契约：config-bundle、心跳、apps.json、插件分发 | 现行 |
| [api-specification.md](api-specification.md) | 生态系统 API 完整规范（1800+ 行总目录） | 现行 |
| [../api/references/alignment-backlog.md](../api/references/alignment-backlog.md) | **API 偏差待办清单**（integration-plan-v2 §3 W3 唯一执行清单） | 现行 |
| [../api/README.md](../api/README.md) | API 目录导航（routes / endpoints / diff matrix） | 现行 |

---

## 2. 项目速查卡（`projects/`，一仓一张）

| 仓库 | 速查卡 | 角色 |
|------|--------|------|
| `mynet_agent` | [projects/mynet_agent.md](projects/mynet_agent.md) | 节点侧控制面（mynetd + mynet + gateway） |
| `mynet_api` | [projects/mynet_api.md](projects/mynet_api.md) | 业务权威（Laravel） |
| `mynet_ctl` | [projects/mynet_ctl.md](projects/mynet_ctl.md) | 分发中心 + **知识库中枢** |
| `mynet_desktop` | [projects/mynet_desktop.md](projects/mynet_desktop.md) | 非 OpenWrt 平台适配（安装/服务/打包） |
| `mynet_gnb` | [projects/mynet_gnb.md](projects/mynet_gnb.md) | GNB P2P VPN 核心（上游 fork） |
| `mynet_gui` | [projects/mynet_gui.md](projects/mynet_gui.md) | 用户交互前端（社区域） |
| `mynet_openwrt` | [projects/mynet_openwrt.md](projects/mynet_openwrt.md) | OpenWrt 平台适配（LuCI 壳） |
| `mynet_utc` | [projects/mynet_utc.md](projects/mynet_utc.md) | UDP→TCP 隧道 |
| `mynet_web` | [projects/mynet_web.md](projects/mynet_web.md) | 官网 / 项目主页 |

已退役：`mynet_client` · `mynet_tui` · `mynet_lua` · `mynet_javis` → 见 [projects/_retired/](projects/_retired/)。

---

## 3. 计划与台账（执行时勾选，完成后仍可查）

| 文件 | 内容 | 状态 |
|------|------|------|
| **[dev-plan.md](dev-plan.md)** | **S 段 64 项计划顺序表**（任务 ID 唯一标识） | 现行 |
| **[long-range-plan-deep.md](long-range-plan-deep.md)** | **V 段（当前）** 波次编排 + 人工闸门 + D1–D5 | 现行 |
| [long-range-plan.md](long-range-plan.md) | R 段 R0–R5 | 现行 |
| [long-range-plan-t.md](long-range-plan-t.md) | T 段 T0–T5 | 现行 |
| [integration-plan-v2.md](integration-plan-v2.md) | **全生态整合规划 v2（当前权威）**：五层模型、四条工作流、SSOT 归属表 | 现行 |
| [v2-upgrade-plan.md](v2-upgrade-plan.md) | V2 升级计划：API 迁移、URL 统一、时间线 | 现行 |
| [back-upshift-design.md](back-upshift-design.md) | back 业务上移设计：状态归属矩阵、上移/下沉判据 | 现行 |
| [cross-project-issues.md](cross-project-issues.md) | CPI 台账 | 现行 |
| [upstream-dsplat-issues.md](upstream-dsplat-issues.md) | 上游缺陷反馈 | 现行 |
| [upstream-defect-report.md](upstream-defect-report.md) | 框架缺陷报告（下游使用方提交） | 现行 |
| [mynet-back-namespace-drift.md](mynet-back-namespace-drift.md) | mynet_api 命名空间漂移映射（T31a） | 现行 |
| [integration-plan.md](integration-plan.md) | v1 集成计划：瓶颈分析、里程碑、ADR | **已被取代** → v2 |
| [client-migration-plan.md](client-migration-plan.md) | 客户端迁移计划（旧线 TUI→Client） | **已被取代** → agent 线 |

---

## 4. 过程报告（归档，不再改）

带日期的一次性执行记录，在 [`archive/2026-09/`](archive/2026-09/)。
**结论要看这里的证据；不要再往里追加进度。** 索引见 [archive/2026-09/README.md](archive/2026-09/README.md)。

| 文件 | 记录 |
|------|------|
| [archive/2026-09/core-remediation-plan-20260930.md](archive/2026-09/core-remediation-plan-20260930.md) | 核心问题修复执行计划（四条主线重排） |
| [archive/2026-09/five-node-milestone-20260930.md](archive/2026-09/five-node-milestone-20260930.md) | 五节点全平台联调里程碑 |
| [archive/2026-09/proxy-conf-cutover-20260930.md](archive/2026-09/proxy-conf-cutover-20260930.md) | proxy.conf 新管理方案切换 + 0.2↔188 排障实录 |
| [archive/2026-09/reinstall-181-report-20260930.md](archive/2026-09/reinstall-181-report-20260930.md) | 181 重装实验报告 |
| [archive/2026-09/evidence/20260930-subject/INDEX.md](archive/2026-09/evidence/20260930-subject/INDEX.md) | 主体快照证据索引 |
| [archive/2026-09/prod-cutover-20260926.md](archive/2026-09/prod-cutover-20260926.md) | 正式发布切换计划 |
| [archive/2026-09/production-gates-handoff.md](archive/2026-09/production-gates-handoff.md) | 生产闸门交接包（H5 / H6 / V2-8b） |
| [archive/2026-09/overnight-u1-plan.md](archive/2026-09/overnight-u1-plan.md) | 过夜计划 U1 |
| [archive/2026-09/overnight-u1-report.md](archive/2026-09/overnight-u1-report.md) | 过夜报告 U1 |
| [archive/2026-09/audit-2026-05.md](archive/2026-09/audit-2026-05.md) | 2026-05 全站审计总报告 |
| [docs-census-20261001.md](docs-census-20261001.md) | 2026-10-01 文档盘点与梳理 |

---

## 5. 协议参考（`refs/`，外部协议资料）

| 文件 | 内容 | 状态 |
|------|------|------|
| [refs/opengnb.md](refs/opengnb.md) | OpenGNB 协议与架构原理 | 现行 |
| [refs/wireguard.md](refs/wireguard.md) | WireGuard 协议与架构原理 | 现行 |
| [refs/opengnb-vs-wireguard.md](refs/opengnb-vs-wireguard.md) | OpenGNB 与 WireGuard 对比 | 现行 |

---

## 6. 知识库之外（本仓其它文档）

| 位置 | 内容 | 说明 |
|------|------|------|
| [../ctl-product-architecture.md](../ctl-product-architecture.md) | CTL 站点产品与页面规划 | 曾名 `architecture.md`，与系统架构撞名，2026-10-01 改名 |
| [../conventions.md](../conventions.md) | 命名规范（**已让位**） | 正本是 [`conventions.md`](conventions.md) |
| [../api/](../api/) | API 对照与偏差清单 | 与 `api-specification.md` 配合 |
| [../marketing/](../marketing/) | 关键词/SEO 手册 | 营销侧 |
| [../todo.md](../todo.md) · [../xuqiu.md](../xuqiu.md) · [../mynet_api-hotfix-tasks.md](../mynet_api-hotfix-tasks.md) | 杂项目录 | 待并入 CPI / dev-plan |

---

## 7. 使用方式

各子项目 agent 说明应包含：

1. **本项目概要**（从 `projects/{name}.md` 提取）
2. **生态关系引用**：指向本知识库
3. **项目自身特有规则**

查规范先来本 README；改规范改 SSOT 原文，不要在下游仓复制。
