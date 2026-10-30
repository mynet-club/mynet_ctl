# SPEC: tips/小助手知识库蒸馏清单与 JSON 契约（tips-kb）

> **SSOT 位置**：`mynet_ctl/docs/knowledge-base/spec/`
> **机器可读形态**：[schemas/tips-kb.schema.json](schemas/tips-kb.schema.json)
> **适用**：`mynet_ctl`（分发）· `mynet_agent`（本机缓存透出）· `mynet_gui`（基线与消费）· `mynet_api`（云端透出）；小助手与 tips 两条消费线
> **状态**：契约定稿（2026-10-26）；实现批未开工，任务登记进 `dev-plan.md` §3 时以本合同为准

---

## 0. 范围

**定义**：知识库 md 源 → chunk 的**蒸馏清单**（§4）、`tips-kb.json` 的**载荷契约**（§2）、
**分发/缓存/合并语义**（§3）、**tips 联动字段**（§5）。

**不定义**（属小助手接入线，另行落 spec）：小助手 BFF/LLM 接入架构、工具调用与权限、
对话协议；tips 文案的 AI 生成；英文版蒸馏；chunk 向量化与语义检索；delta 增量协议。

**为什么是这份合同**：tips 体系是前端静态注册表（`mynet_gui/src/services/tips/TipsRegistry.ts`
约 18 条 i18n 文案 + `TipsEngine` 调度；后端智能建议端点已下架，见 `mynet_api/app/Modules/VPN/Routes/api.php:245`），
小助手需要的是**深知识**——排障手册、检查清单、帮助文档。二者分工：tips 主动引导、
小助手被动答疑；本合同的知识库是小助手的上下文来源，tips 经 `kbChunkIds` 深链到它。

---

## 1. 角色与数据流

```
知识库 md（SSOT） ──蒸馏（抽取+人工审校）──▶ tips-kb.json（构建产物，提交入 ctl 仓）
                                              │
                    ┌─────────────────────────┴─────────────────────────┐
                    ▼                                                   ▼
            ① 基线：打进 GUI 产物                                 ② 在线：ctl /d/kb/
            （离线兜底）                                          （发布脚本同步）
                    │                                                   │
                    └───────────────┬───────────────────────────────────┘
                                    ▼
                     客户端缓存（id-keyed 合并）
                     本机面：agent localapi 拉取+缓存（浏览器只打 127.0.0.1）
                     云端面：mynet_api 同形透出（浏览器只打业务后端）
                                    │
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
              小助手上下文注入                  tips「需要帮助」深链
              （检索 / 显式 ids）              （TipConfig.kbChunkIds）
```

---

## 2. JSON 契约（规范性）

载荷 = **manifest + chunk 记录**两层，单文件全量分发（v1 无 delta 协议）。完整 JSON Schema
见 [schemas/tips-kb.schema.json](schemas/tips-kb.schema.json)，该文件是字段合法性的唯一机器判据。

### 2.1 顶层（manifest）

| 字段 | 类型 | 必填 | 语义 |
|---|---|---|---|
| `version` | string | ✅ | 内容版本标识（建议 `v<YYYYMMDD>.<n>` 或全文 sha256 前 12 位）。**不参与合并判定**，仅用于新鲜度展示、日志与条件拉取 |
| `generated_at` | string | ✅ | ISO 8601（UTC），蒸馏产物生成时刻 |
| `lang` | string | ✅ | BCP-47；v1 固定 `zh-CN` |
| `chunks` | array | ✅ | chunk 全量列表（可为空数组；空数组也有权威性 = 撤回全部，同 `mynet_agent/shared/config/proxy_domain_baseline.go:10` 口径） |

### 2.2 chunk

| 字段 | 类型 | 必填 | 语义 |
|---|---|---|---|
| `id` | string | ✅ | 全库唯一；`^[a-z0-9]+(-[a-z0-9]+)*$`；**发布后不改语义、不复用**（改名 = 删旧增新） |
| `title` | string | ✅ | 单行标题（用户可读，中文） |
| `tags` | string[] | ✅ | 1–8 个，kebab-case，用于检索过滤（如 `connectivity` `troubleshoot` `zip`） |
| `body` | string | ✅ | Markdown 正文。**单一主题**，≤ 1500 汉字为初值（超限必须拆 chunk）；可含命令，不得含凭据/私钥/内网地址/个人数据 |
| `source` | object | ✅ | 蒸馏出处（证据可查）：`{"file": "docs/knowledge-base/troubleshoot-connectivity.md", "section": "2. 步骤 B —— 密钥正确交换"}`；`file` 为 workspace 相对路径 |
| `sha256` | string | ❌ | `body` 的 sha256 十六进制；建议给，供客户端跳过未变更 chunk |

### 2.3 删除 / 改名 / 回滚语义

- **manifest 的 `chunks` id 集是权威全量**。客户端合并后，本地缓存中不在此 id 集的 chunk 一律删除。
  删除与改名因此天然生效：源文档删节 → 蒸馏产物少 id → 客户端收敛，无需 tombstone。
- **回滚 = 服务端换回旧 JSON**。合并按 id 收敛、幂等，对 `version` 大小不做单调性门禁——
  客户端接受任何通过校验的 manifest，最终有效集跟随服务端。这同时覆盖 ctl 误发回滚。

### 2.4 内容安全

- 蒸馏源文件必须带 YAML frontmatter `security_class: public`（约定出处：`docs/knowledge-base/skill-kit.md:19`；
  `/d/docs/` 的准入注释同口径，见 `mynet_ctl/app/Http/Controllers/Api/V1/DocsController.php:20`）。
  **没有该前置的文件不得进入蒸馏清单**。
- chunk 生成后按 `make guard` A1/A2 同口径复审：无硬编码凭据、无私钥材料；内网 IP、生产主机名
  一律改写为占位符。复审记录跟蒸馏产物入库。

---

## 3. 分发与缓存合同

### 3.1 基线（打进 GUI 产物）

- 每个 GUI 发行版内含一份 `tips-kb.json` 作为**离线基线**，随构建产物分发。
  既有同型先例：`mynet_gui/package.json:14-15` 的 `build:copy-help` 把 `docs/help/` 拷进 `dist/spa/docs/`。
- 基线版本 = 该 GUI 构建时的最新蒸馏产物；无网络、无缓存时有效集 = 基线。

### 3.2 在线（ctl `/d/kb/`）

- **端点**：
  - `GET /d/kb/tips-kb.json` —— 全量载荷，`Content-Type: application/json; charset=utf-8`
  - `GET /d/kb/tips-kb.json.sha256` —— 同文校验和，`text/plain`，格式 `<sha256>  tips-kb.json\n`
- **语义照抄 `/d/docs/` 先例**（`mynet_ctl/routes/web.php:37` + `DocsController`）：无认证、可 CDN、
  `Cache-Control: no-cache`、响应头带校验和与版本（`X-KB-Checksum` / `X-KB-Version`）、
  allowlist 准入、读分发产物不做二次拷贝。
- **为什么并列 `/d/kb/` 而不并入 `/d/docs/`**：`DocsController::show()` 对非校验分支硬编码
  `text/markdown`（`mynet_ctl/app/Http/Controllers/Api/V1/DocsController.php:57`），JSON 件需要 `application/json`；
  并列新组不动 `/d/docs/` 既有合同。
- **条件拉取**：客户端带 `If-None-Match: "<全文sha256>"`（ETag = 全文 sha256，与 sidecar 同值），
  未变更回 304，跳过合并。
- **产物入库**：蒸馏产物提交入 ctl 仓（`scripts/tips-kb/tips-kb.json`，同 `scripts/proxy-lists/proxy-domains.txt`
  先例——发布后公开下载逐字节一致、内容 SHA 可比对），发布脚本同步到 `/d/kb/` 下载位。
  **md 源是 SSOT，JSON 是构建产物**：手改 JSON 不作为源，随源重建后整体替换。

### 3.3 浏览器侧获取路径（口径：用户面不直连 ctl）

| 形态 | 浏览器打谁 | 谁去拉 ctl |
|---|---|---|
| 本机面（embed GUI / WebUI，`mynetd` 127.0.0.1:**8096**） | agent localapi 新增只读端点（拟 `GET /api/v1/kb/tips-kb.json`，能力面登记进 [local-api.md](local-api.md) §3） | mynet_agent（拉取+校验+缓存） |
| 云端面（`my.mynet.club` SPA） | mynet_api **`GET /api/v1/kb/tips-kb.json`**（2026-10-31 定案，O1 关闭） | mynet_api（建议定时拉取 + storage 缓存，不请求时依赖 ctl；拉取失败回旧缓存，同本机面回退语义） |

载荷形状两面同形（本合同）。云端面口径三条：
- **工件名/路径后缀四面统一** `tips-kb.json`（ctl `/d/kb/tips-kb.json` → agent localapi → mynet_api 同后缀），
  日志/清单/工具只认一个工件名；
- **响应不包信封**（不套 `{success,data}`），与 ctl 公开下载逐字节同形；校验与条件拉取沿用
  `/d/docs/` 口径（`ETag` = 全文 sha256、`If-None-Match` → 304、`X-KB-Checksum` / `X-KB-Version`），
  本面**不另出** `.sha256` sidecar（那是裸分发面的产物，HTTP 校验子已够）；
- **公开读**：载荷与 ctl 公开下载同字节（§7 O2 已把内部分工口径挡在库外），加认证只挡自家
  SPA、挡不住 curl ctl —— 无额外泄露可言，不做事后门。

### 3.4 合并算法（规范性，按 chunk id 增量合并）

1. 完整拉取 `tips-kb.json`（或 304 跳过）；
2. 校验全文 sha256（sidecar 或 `X-KB-Checksum`），不匹配**丢弃本次载荷**；
3. **id-keyed reconcile**：对 `manifest.chunks` 逐 id upsert（同 id 以载荷为准）；
   本地缓存中 id 不在 `manifest.chunks` 的条目**删除**；
4. 基线与在线的关系：在线 manifest 成功合入后即权威（其 id 集为有效集，基线独有 id 随之删除）；
   拉取失败/无网时有效集 = 最后一次成功缓存，再退基线；
5. 幂等：同一 manifest 重复合并不改变有效集。

### 3.5 失败与回退

- 拉取失败、超时、校验不过：**保留此前成功缓存**（先例：域名清单「失败不替换基线」，见
  `mynet_agent/shared/config/proxy_domain_baseline.go` 一族与 `mynet/proxy_domain_baseline.go`），**永不清空等重拉**。
- 基线永远随 GUI 产物在场，保证「装完即有知识库」。

---

## 4. 蒸馏清单

### 4.1 蒸馏原则

1. **只进公开安全级**：源文件带 `security_class: public`（§2.4）；
2. **用户面知识**：排障、使用、概念；实现内部细节（驱动密钥布局、内部架构推演）不进；
3. **单一主题**：一 chunk 回答一类问题；命令可执行、判据可核对；结论带源节号（`source` 字段）；
4. **体量**：body ≤ 1500 汉字初值；超限按小节拆，不截断语义；
5. **时效**：带日期的对齐记录、一次性行动记录不蒸馏（不是知识是台账）。

### 4.2 v1 源清单

| 源（workspace 相对路径） | 行数 | security_class 现状 | id 前缀 | 计划 chunk | 状态 |
|---|---|---|---|---|---|
| `docs/knowledge-base/troubleshoot-connectivity.md` | 444 | **缺前置，蒸馏前补** | `tc-` | 见 §4.3 | 待蒸馏 |
| `docs/knowledge-base/node-connectivity-checklist.md` | 340 | **缺前置，蒸馏前补** | `nc-` | 见 §4.3 | 待蒸馏 |
| `mynet_gui/docs/help/**`（实存 6 份 md） | — | 随 GUI 帮助发布，公开 | `help-` | 见 §4.3 | 待蒸馏 |

⚠️ `mynet_gui/docs/help/index.json` 的导航树（7 大类）大于盘上实存文件（仅
`overview/{what-is-mynet,basic-concepts}`、`getting-started/{registration,login}`、
`user-guide/ip-pools/create-ip-pool`、`faq/general-faq` 共 6 份）——**蒸馏以实存文件为准**，
后续 help 文档补写后追加进本清单，不按 index.json 的承诺条目蒸馏。

### 4.3 chunk id 分配表（v1 计划，共 25 条）

**troubleshoot-connectivity.md → `tc-`（11 条）**

| id | 源节 | 主题 |
|---|---|---|
| `tc-overview` | §0.1 + §0.4 | 适用症状 + 五步总览 |
| `tc-collect-site` | §0.2 + §0.3 | 先决条件 + 一条命令拿现场 |
| `tc-step-a-config` | §1 | 步骤 A：软件与配置完整 |
| `tc-step-b-keys` | §2 | 步骤 B：密钥正确交换 |
| `tc-step-c-adjacency` | §3 | 步骤 C：邻接 / 网络宣告 |
| `tc-step-d-routing` | §4 | 步骤 D：系统路由可达（含 §4.4 回程） |
| `tc-step-e-firewall` | §5.1–5.2 | 三层放行 + 命令 |
| `tc-peering-zip` | §5.3–5.6 | 对等配置（zip/crypto/passcode）+ 大包二分 + 回程对称 |
| `tc-count-diagnosis` | §6 | 计数诊断法（进阶） |
| `tc-platform-quickref` | §7 + §10 | 平台速查 + 一句话速查 |
| `tc-decision-tree` | §8 | 决策树 |

**node-connectivity-checklist.md → `nc-`（8 条）**

| id | 源节 | 主题 |
|---|---|---|
| `nc-check-model` | §2 + §3 | 连通检查模型（四步+一）+ 快速排查清单 |
| `nc-q1-large-packet` | §6 Q1 | ping 通但 SSH/HTTP 不通（zip 不一致） |
| `nc-q2-stop-failed` | §6 Q2 | 停止后显示「启动失败」 |
| `nc-q3-old-pubkey` | §6 Q3 | 对端仍用旧公钥 |
| `nc-q4-restore-backup` | §6 Q4 | 恢复备份 / 手动导入后不通 |
| `nc-q5-peering-items` | §6 Q5 | 对等配置包含哪些项 |
| `nc-q6-commands` | §6 Q6 | 排障命令速查 |
| `nc-product-rules` | §4 | 与产品规则的对应 |

**mynet_gui/docs/help → `help-`（6 条）**

| id | 源文件 |
|---|---|
| `help-what-is-mynet` | `overview/what-is-mynet.md` |
| `help-basic-concepts` | `overview/basic-concepts.md` |
| `help-registration` | `getting-started/registration.md` |
| `help-login` | `getting-started/login.md` |
| `help-create-ip-pool` | `user-guide/ip-pools/create-ip-pool.md` |
| `help-general-faq` | `faq/general-faq.md` |

**不蒸馏（明示排除，防后人误捞）**：

| 源节 | 理由 |
|---|---|
| `node-connectivity-checklist.md` §1 GNB Ed25519 三端对照 | 驱动实现细节，dev 面 |
| `node-connectivity-checklist.md` §5 进程生命周期铁律 | 多实例杀进程规则，dev/ops 面；**待安全审查后定**（§7） |
| `node-connectivity-checklist.md` §7 / §8 平台对齐记录 | 带日期的一次性对齐台账，不是知识 |
| `troubleshoot-connectivity.md` §9 机测与人工分工 | 内部分工口径；**待定**（§7） |

### 4.4 生成管线

1. 蒸馏 = 从源 md 按 §4.3 切节、改写为单一主题正文（保留命令与判据，脱敏）；
2. 产物 `tips-kb.json` 过 [schemas/tips-kb.schema.json](schemas/tips-kb.schema.json) 校验 + §2.4 安全复审后，
   提交入 ctl 仓 `scripts/tips-kb/tips-kb.json`；**手改该 JSON 不算源变更**，源在 md；
3. 发布：同步到 `/d/kb/` 下载位（§3.2）；GUI 构建时拷入产物作基线（§3.1）；
4. 源 md 变更 → 重跑蒸馏 → 整体替换产物 → 发布；id 稳定性规则见 §2.2。

---

## 5. tips / 小助手联动

- **`TipConfig` 增可选字段 `kbChunkIds?: string[]`**，落点 `mynet_gui/src/types/tips.ts:146`
  的 `TipConfig` 接口（现有字段 id/type/stage/trigger/display/actions 等不动）。
- 在 `TipsRegistry.ts` 的各 tip 配置上填 `kbChunkIds`（如邀请码 tip 指向 `help-registration`）；
  tips UI 的「需要帮助？」动作携带该列表进小助手，作为**显式预置上下文**。
- 小助手检索（v1）= 显式 `kbChunkIds` + `title`/`tags` 关键词匹配（客户端过滤）；
  chunk 作为上下文注入，**不实时生成 tips 文案**（tips 文案仍归 i18n 静态注册表）。

---

## 6. 非目标

- 小助手接入架构（BFF/LLM/工具调用/配额审计）——属小助手接入线；
- tips 文案 AI 生成或实时生成；
- 英文版蒸馏（`lang` 字段已留位，v1 只有 `zh-CN`）；
- chunk 向量化 / 语义检索 / RAG 基础设施；
- delta 增量协议（v1 全量 + sha256 条件拉取已够；体量见 §4.1）。

---

## 7. 开放问题

| # | 问题 | 倾向 |
|---|---|---|
| O1 | 云端面透出端点命名（mynet_api 侧） | **已定案（2026-10-31）**：`GET /api/v1/kb/tips-kb.json`，口径回填 §3.3（工件名四面统一 / 不包信封 / 公开读 / 定时拉取+缓存） |
| O2 | `node-connectivity-checklist.md` §5 进程生命周期铁律、`troubleshoot-connectivity.md` §9 是否入公开库 | 安全审查后定；默认不入 |
| O3 | body 体量上限终值（1500 汉字为初值） | 首批蒸馏后按实际分布校准 |
| O4 | help 导航树其余文档的补写与纳入节奏 | 随 help 文档补写追加 §4.2/§4.3，不预蒸馏空条目 |
