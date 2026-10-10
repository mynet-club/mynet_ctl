# 文档盘点与梳理报告（2026-10-01）

> 方法：脚本全量扫描 `mynet-club/`（排除 `vendor/`、`node_modules/`、`.git/`），
> 对照 `knowledge-base/README.md` 索引与 `AGENTS.md` 开工必读表，
> 再抽查重复、漂移与项目卡匹配。原始清单 JSON 在 `/tmp/mynet_doc_census.json`。

---

## 0. 总量

| 范围 | 文件数 | 约行数 |
|---|---:|---:|
| 全仓 md（排除 vendor） | **384** | **~133k** |
| 其中 `knowledge-base/`（SSOT） | **71** | **~23.9k** |
| `mynet_ctl/docs/` 知识库之外 | 19 | ~18k |
| `mynet_gui` 活文档（非 archive） | ~57 | ~24k |
| `mynet_gui/docs/_archive/` | 72 | ~21k |
| 各 repo 自带 docs / AGENT | 其余 | — |

**按仓分布（排除 vendor）**：`mynet_gui` 152 · `mynet_ctl` 99 · `mynet_utc` 39 ·
`mynet_agent` 30 · `mynet_gnb` 25 · `mynet_openwrt` 23 · `research` 9 · 其余零星。

---

## 1. 最要紧的四个问题

### 1.1 索引严重缺页（P0）

`knowledge-base/README.md` 是对外索引，但 **69 份正文里有 46 份不在索引上**。
其中还包括 AGENTS 开工必读的几份：

| 文件 | README | AGENTS |
|---|---|---|
| `dev-plan.md` | ✗ | ✓ |
| `long-range-plan-deep.md` | ✗ | ✓ |
| `long-range-plan.md` / `-t.md` | ✗ | ✓ |
| `mynet-overview.md` | ✗ | ✓ |
| `repo-migration.md` | ✗ | ✓ |
| `upstream-dsplat-issues.md` | ✗ | ✓ |
| `spec/unified-platform-architecture.md`（L1–L7 权威） | ✗ | ✓（§5 提到） |
| `spec/proxy-split.md` / `control-plane.md` / `node-keying.md` | ✗ | ✓（§5 提到） |

另外 **完全没有进任何索引**（README/AGENTS 都没有）的还有：
`api-specification.md`、`troubleshoot-connectivity.md`、`node-connectivity-checklist.md`、
`mynet-back-namespace-drift.md`、全部 `refs/`、全部 `projects/`、
9 份带日期的执行报告、`spec/{paths,route-*,proxy-conf-design,debug-env,back-*}.md`。

> 问题不在「文件丢了」，而在**新会话按 README 找不到它们**，
> 只能靠翻目录或撞运气——这正是 AGENTS §2.1「凭文件名下判断」的温床。

### 1.2 项目速查卡与真实仓库对不上（P0）

`knowledge-base/projects/` 现有 10 张卡，workspace 实际 9 个 `mynet_*` 仓：

| 有卡无仓 | 有仓无卡 |
|---|---|
| `mynet_client` · `mynet_javis` · `mynet_lua` · `mynet_tui` | **`mynet_agent`** · **`mynet_desktop`** · **`mynet_openwrt`** |

缺卡的三个恰好是当前在建主力（agent / openwrt / desktop）；
多出的四张指向已不存在或已改名的仓。`ecosystem.md` 写「10 个子项目」，
和 AGENTS §5 项目地图（9 个）也不一致。

### 1.3 OpenWrt 文档 100% 双份（P1）

```
mynet_agent/openwrt/docs/*.md   16 份
mynet_openwrt/docs/*.md         17 份（多一份 SESSION_NOTES_20260930.md）
```

**16 个同名文件 MD5 全部一致**（实测 `identical: 16  different: 0`）。
谁是正本没有写明；改一处漏一处是必然的。

### 1.4 知识库内「规范」与「过程记录」混放（P1）

同一目录下并存两类性质完全不同的文档：

| 性质 | 例子 | 应有寿命 |
|---|---|---|
| **规范 / SSOT** | `spec/*`、`architecture.md`、`conventions.md`、`deployment-registry.md` | 长期，变更时改 |
| **计划** | `dev-plan.md`、`long-range-plan*.md`、`integration-plan-v2.md` | 随执行勾选 |
| **单次执行报告** | `overnight-u1-*`、`prod-cutover-20260926`、`reinstall-181-report-*`、`five-node-milestone-*`、`proxy-conf-cutover-*`、`core-remediation-plan-*`、`production-gates-handoff` | **归档**，不再改 |
| **已作废 / 旧线** | `integration-plan.md`（已被 v2 取代）、`client-migration-plan.md`（历史）、`spec/proxy.md`（旧线冻结） | 标记后归档 |

`README.md` 自己已经写了「integration-plan 已被 v2 取代」「client-migration-plan 历史」，
但文件仍留在「规划与迁移」主列表，和现行计划平起平坐。

---

## 2. 重叠与分叉（需人工裁定）

| 主题 | 并存文档 | 备注 |
|---|---|---|
| **系统架构** | `docs/architecture.md`（216 行，CTL/官网定位） vs `knowledge-base/architecture.md`（167 行，L1–L3 启动，标「最后更新 2026-03-26」） | **不是同一份**，但文件名相同、都在 `mynet_ctl/docs` 树下，极易混。且 kb 版日期停在 3 月，而 `spec/unified-platform-architecture.md`（L1–L7，2026-09-29）才是权威 |
| **统一规范** | `docs/conventions.md`（166 行） vs `knowledge-base/conventions.md`（306 行） | 同名双份 |
| **API** | `api-contracts.md` · `api-specification.md`（1865 行） · `docs/api/*` · `spec/local-api.md` · 各仓 `05-API文档.md` | 至少 5 条线，未标明谁是 SSOT |
| **代理 / 路由** | `spec/proxy.md`（旧线冻结） · `spec/proxy-split.md`（新线） · `spec/proxy-conf-design.md` · `spec/route-apply-unification.md` · `spec/route-firewall-split.md` · `archive/2026-09/proxy-conf-cutover-20260930.md` | 新旧已用标题区分，但索引没把「冻结/现行」写进表格 |
| **长程计划** | `long-range-plan.md`（R）· `-t.md`（T）· `-deep.md`（V） | AGENTS 说 V 段是「当前」，但 README 完全没这三份 |
| **上游缺陷** | `upstream-defect-report.md` vs `upstream-dsplat-issues.md` | 标题几乎同义，一份在 AGENTS 必读、一份不在 |
| **integration** | `integration-plan.md`（1659 行） vs `integration-plan-v2.md` | v1 已被取代但仍占最大篇幅之一 |

---

## 3. 分桶现状（便于定归档策略）

| 桶 | 文件数 | 约行数 | 建议 |
|---|---:|---:|---|
| `knowledge-base/` 核心+规范+refs+projects | 61 | ~22k | 留下，补索引 |
| `knowledge-base/` 带日期执行报告 | 9 | ~2.2k | → `knowledge-base/archive/` |
| `mynet_ctl/docs/api/` | 12 | ~13k | 留在 ctl；README 里链过去 |
| `mynet_ctl/docs/` 其它（architecture/conventions/todo/xuqiu/hotfix） | 5+ | ~7k | 同名的先裁定正本 |
| `mynet_gui/docs/_archive/` | 72 | ~21k | **已在归档，保持** |
| `mynet_gui/docs/` 活文档 | ~57 | ~24k | 建议再分 `plans/` `fixes/` `guides/`（现在 plan/fix/report 混在根） |
| `mynet_agent/openwrt/docs` + `mynet_openwrt/docs` | 16+17 | ~5k | **去重，留一份正本** |
| help 文档三份拷贝（`docs/help` · `dist/spa/docs/help` · `public/docs/help`） | 7×3 | ~4k | 构建产物里的两份应只读/生成，标明源 |

---

## 4. 建议动作（按优先级）

### P0 — 今天就能做，零风险

1. **重写 `knowledge-base/README.md` 索引**，覆盖全部 69 份正文，按四类分节：
   - 开工必读 / 规范 SSOT / 计划与台账 / 过程报告与归档
   - 每行加状态列：`现行` · `冻结` · `已被取代` · `归档`
2. **项目卡对齐仓库**：补 `mynet_agent` / `mynet_desktop` / `mynet_openwrt` 三张；
   `mynet_client` / `mynet_javis` / `mynet_lua` / `mynet_tui` 移入 `projects/_retired/`
   或在标题标明「历史名，现已并入 X」。
3. **同名文档裁定**：`architecture.md` / `conventions.md` 各留一份正本，
   另一份改名加前缀（如 `ctl-site-architecture.md`）或合并。

### P1 — 半天内

4. **OpenWrt 文档去重**：指定 `mynet_openwrt/docs/` 为正本
   （或反过来），另一边改为指向说明 + 指南链接，不再复制正文。
5. **过程报告归档**：把 §1.4 表中 9 份带日期报告移入
   `knowledge-base/archive/2026-09/`，README 留一行索引。
6. **旧线标记**：`integration-plan.md`、`client-migration-plan.md`、
   `spec/proxy.md` 文件头加 `> **状态：已被取代 / 旧线冻结**`，正文不再更新。

### P2 — 有空再收

7. `mynet_gui/docs/` 按 `plans/` `fixes/` `guides/` `reports/` 分子目录（对照 `_archive` 结构）。
8. help 文档单一来源（`docs/help/`），`dist/`、`public/` 由构建拷贝。
9. 为 `api-specification.md`（1865 行）与 `api-contracts.md`、`docs/api/` 划清边界：
   建议 `api-specification` 做总目录，其余做专章，避免三套 API 叙述并行。

---

## 5. 证据索引

| 结论 | 证据 |
|---|---|
| 384 md / 71 in kb | 脚本扫描 `mynet_doc_census.json` |
| README 缺 46 份 | 文件名对照 `README.md` 全文 |
| 项目卡 4 无仓 / 3 仓无卡 | `projects/*.md` stem vs workspace 目录名 |
| openwrt 16 份 MD5 全同 | `hashlib.md5` 逐文件对比 |
| 双 architecture / 双 conventions | `wc -l` + 首 15 行对照（内容不同） |
| integration-plan 被 v2 取代 | `README.md:54` 原文 |

---

## 6. 不在本次范围

- 未改任何现有文档正文（本报告是只读盘点）。
- 未合并/移动文件——等确认 §4 优先级后再动。
- `vendor/`、构建产物中的第三方 README 未计入。
