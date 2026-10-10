# 归档 — 2026-10 文档收编

> 2026-10-10 一次性收编：把**已自声明被取代**与**一次性快照**两类文档从知识库顶层移入本目录，
> 依据是 [`docs-census-20261001.md`](docs-census-20261001.md) §82/§137 已给出的口径
> （「已作废 / 旧线 → 标记后归档」）与各文件正文的自声明。
>
> **仍被现行文档引用的结论**（引用请写 `archive/2026-10/…` 路径，别再写顶层名）：
>
> - `spec/gnb-compat-baseline.md` ← `integration-plan.md:760`（`v1.6.0.a`）、`api-contracts.md:195`
> - `long-range-plan-deep.md` ← `v2-upgrade-plan.md:18`（CI/CD 缺失取证）
> - `ecosystem.md` ← `api-contracts.md`（升级契约）
> - `README.md` 本目录索引

## 已自声明被取代

| 文件 | 原状态 | 被判定的依据 | 后继 |
|---|---|---|---|
| [integration-plan.md](integration-plan.md) | 顶层标「已被取代」 | 文首横幅：「本文件已被 integration-plan-v2.md 取代（2026-09-21）」，保留作 ADR 与历史参考 | `integration-plan-v2.md` |
| [client-migration-plan.md](client-migration-plan.md) | 顶层标「已被取代」 | 文首：「状态：已被取代（历史）（2026-10-01 标注）」，tui→client 路线整体谢幕 | `mynet_agent`（agent 线） |
| [v2-upgrade-plan.md](v2-upgrade-plan.md) | 顶层标「现行」（与实际不符） | 文首：「状态：历史（2026-10-01 标注）」，M3/M4 指向的 `mynet_client`/`mynet_lua` 已退役 | `integration-plan-v2.md` · `dev-plan.md` · `long-range-plan-deep.md` |

## 一次性快照

| 文件 | 记录 | 为什么不再放顶层 |
|---|---|---|
| [docs-census-20261001.md](docs-census-20261001.md) | 2026-10-01 全库文档盘点与梳理报告（原始清单 JSON 在 /tmp） | 单次普查快照；其结论已落成本次收编动作 |
| [mynet-back-namespace-drift.md](mynet-back-namespace-drift.md) | T31a 命名空间漂移映射清单（2026-09-21，决策材料） | 一次性决策材料，正文自述「本批未动任何代码」；对应迁移已执行 |
| [upstream-defect-report.md](upstream-defect-report.md) | 2026-09-25 首批 5 条框架缺陷（只装 core+auth+infrastructure 必现） | 内容已被 `upstream-dsplat-issues.md` 覆盖（AiStreaming / FOREIGN_KEY_CHECKS / ModuleRegistry / billing 均在），后者是 AGENTS 必读表里的口径；本文件留作提交原件 |
| [api-contracts.md](api-contracts.md) | 2026-03-24 四项目同步升级接口契约（config-bundle v2 / 心跳 action / apps.json v2.1 / 插件分发） | 3 月提案口径，与现行 SSOT 并存而非其一部分。现行契约以 `api-specification.md` + `docs/api/openapi/back.yaml` 为准 |

## 收编时同步做的指针修正

- `README.md`：§3 现行表移除本目录各件；§4 归档表补索引。
- `spec/gnb-compat-baseline.md`、`long-range-plan-deep.md`、`ecosystem.md`、`integration-plan-v2.md`
  里指向上述文件的路径改为 `archive/2026-10/…`。
- 顶层 `spec/proxy.md` **不移入**：按 README 状态词它是「冻结」（不再改、仍可查），其冻结横幅本就在（指向 `spec/proxy-split.md`），本次未改。

**不要再往本目录追加进度。** 要知道「现在该做什么」去 `../../project-memory.md`（开工入口）与 `../../dev-plan.md`。
