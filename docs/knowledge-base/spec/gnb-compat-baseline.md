# GNB 兼容基线 —— 决策包

> **性质：决策包，不是裁定。** 本文只登记实测、列出取舍，交主线裁决。
> **未裁决前不改任何运行时行为**（现状一行不动）。
> 触发：`MYNET_V1_PLAYBOOK.md` §7 P0「把已落地 GNB 版本写入兼容基线」是全批最后一件未落项
> （同批的「密钥脱敏测试」已落，见 playbook §7 P0 实施记录与 dev-plan #75 行）。
>
> **关联裁定边界**：gnb 仓只做编译发行、补丁/proxy 代码归用户（见 dev-plan / memory「gnb 职责边界：只做编译发行」）⇒
> 本条只谈「版本/能力的**登记与消费**」，不碰 `mynet_gnb/src`、不新增驱动能力。
>
> **✅ 裁定（2026-10-05）**：**D1-A**（知识库文档）+ **D2-E2**（表）+ **D3-iv**（不动运行时）+ **D4**（现网只读取证工单）——**已落，见 §6**。

---

## 0. 一句话

今天**没有「兼容基线」这个物件**，但它的四个碎片散在四处：**构建基线是 ldflags 字符串、能力靠运行时嗅探、配置对等写死在代码常量、发行元数据在 ctl 数据库**。所谓「写入兼容基线」= 决定这四块要不要收口成一份**可判定、可消费**的登记物，以及谁来读它。

---

## 1. 实测：四个碎片各自是什么（都带 `file:line`）

| 碎片 | 位置 | 内容 | 形状 | 今天谁读 |
|---|---|---|---|---|
| **构建基线** | `mynet_agent/Makefile:59` | `GNB_BASELINE ?= 1.6.8-c411f14ee647`（+ `GNB_PATCHES ?= p1-hot-restart-v2,p2-route-proxy`，`:60`） | **构建期 `-ldflags` 注入的字符串**（`:63-68`） | `mynet version` 三句话（`mynet/commands.go:143`）；`PluginPackage.Version`（`mynet/plugin_proxy.go:103`）。默认值 `dev`（`shared/utils/constants.go:23`，注释 `:19` 自陈是「opengnb 基线」） |
| **能力判据** | `mynet/mynet/plugin_proxy.go:40-75` | 跑 `gnb -h/-v` 找机器可读的 **`Features: proxy`** 行；对 2026-09-30 之前出厂、**没有 Features 行**的旧构建，回退到 `proxy.conf` 行为串检测 | **运行时嗅探**（不是版本表） | proxy 插件可否启用（`:66` 命中 / `:69` 明确否定 / `:75` 兜底无能力） |
| **配置对等** | `mynet/mynet/parity.go:108` | `fallback := {zip:auto, zip-level:-1, crypto:xor, safe-index:off, multi-socket:on, unified-forwarding:auto}`，自陈是「工作正常的对端（10.182.236.180）实测基线」（`:106`） | **代码常量**（键是 *配置项*，不是 gnb 版本） | `CheckGNBParity()`：导入/恢复/本地配置时提示两端配置不一致（`parity.go:105`） |
| **发行存在** | `mynet_ctl/app/Services/Manifest/ManifestService.php`（gnb manifest，`schema_version: 1.1`，`:311`） | `versions[ver] = {released_at, channel, checksums, matrix, artifacts, scripts}` + `latest{stable,beta,nightly}`（`:299-325`）；版本导入入口 `app/Console/Commands/ImportGnbFromDownload.php:17` | **DB 派生的 manifest**（`/d/gnb/manifest.json`） | agent 升级检查 `mynet/upgrade`（`ComponentGNB`，`service_test.go:10`）、ctl 下载面 |

**顺带两条与「版本门」相邻、但不是本条的**：
- `min_agent_version`：**bundle 字段**，值硬编码 `'0.9.0'`（`mynet_api/app/Modules/VPN/Domain/Services/BundleEnvelopePublisher.php:181`）；解析/回写见 `BundleEnvelope.php:74,102`，agent 侧 `shared/domain/bundle_envelope.go:25`。它门的是 **agent** 版本，**不是 gnb**。
- gnb 仓自身双轨记版本：`src/version`（当前 `1.6.8`）+ `sources/history.ndjson`（据 `mynet-overview.md:174`）。该仓现为 bare（工作树在 `.wt/`），不在本条的写入面内。

---

## 2. 缺口：为什么这不是「照抄某处」能了事的

1. **没有一份东西同时回答「哪些 gnb 版本算已落地 / 各自什么能力」**。已落地的版本散落在旧文档（`archive/2026-10/integration-plan.md:760`、`archive/2026-10/api-contracts.md:195` 都是 `v1.6.0.a`；`mynet-overview.md:36` 是 `1.6.8 + 本地补丁`），**知识库无一处冻结现网各节点的 gnb 实际版本**（已 grep 确认：只有 ctl 的样例文件名与 upgrade 测试出现 `1.6.0.a`）。
2. **能力问题今天是用「嗅探」回答的，不是用「版本」回答的**（`plugin_proxy.go`）。嗅探对**没有 Features 行的旧构建**只能靠行为串兜底 —— 这正是「没有兼容基线」的直接代价。
3. **消费者尚未被点名**。「写入基线」写完之后**谁读它、读了做什么**（驱动能力判定 / 升级资格 / parity 提示 / bundle 版本门）没有裁定，而它决定写入面的**形状**。

---

## 3. 待裁四项（每项给选项 + 取舍）

### D1 —— 载体放哪
| 选项 | 好处 | 代价 |
|---|---|---|
| **A. 知识库 spec 文档（冻结文字件）** | 零代码风险、与 `v1-permission-matrix.md` / `v1-error-codes.md` 两份 P0 冻结件同形；立刻可写 | 不可被程序消费（只解决「登记」，不解决「判定」） |
| **B. agent 代码常量**（如 `mynet/compat.go` 的 `{version→capabilities}` 表） | 可被 `plugin_proxy.go` / `mynet/upgrade` 直接读，替代/补强嗅探 | 改行为；要动 agent 且须测试锁住；gnb 版本一发新版就要跟着改代码 |
| **C. ctl 发行面（manifest 加一层 compat 覆盖）** | 与「哪些版本真实存在」同源；可下发给所有客户端，改数据不改码 | 只覆盖「已登记为 release 的版本」，覆盖不到手工装的老二进制；要动 ctl + 前端/客户端读法 |
| **D. A + C（文档先冻结，manifest 后加字段）** | 登记与消费分两步、风险可控 | 两步之间要有「谁在什么时点升级 C」的约定 |

### D2 —— 形状
- **E1. 单一钉死版本**（如「基线的基线 = 1.6.8-c411f14ee647」）：最省，但回答不了「现网还有 1.6.0.a / 更老的 p2 构建共存」这一事实。
- **E2. 表**：`{version → {capabilities: [proxy,…], patch_stack, status: deployed|known-good|deprecated, platform}}`。能表达共存与能力差异，是唯一能替代嗅探的形状；代价是要维护。
- **建议取向**：若做，取 **E2**（E1 无法覆盖已观测到的多版本共存）。

### D3 —— 谁消费、做什么
| 选项 | 含义 |
|---|---|
| **i. 驱动能力判定**（proxy 等） | 用基线表**替代** `plugin_proxy.go` 的运行时嗅探（风险：老二进制可能不在表里）→ 更稳妥的是**嗅探优先、基线兜底** |
| **ii. 升级资格** | `mynet/upgrade` 判「当前 gnb 能否升到目标」 |
| **iii. parity 提示** | 给 `parity.go` 的 `fallback` 一个「按对端版本取期望值」的来源（现状是写死 .180 的值） |
| **iv. 不动运行时** | 只登记、只审计，消费留到有明确需求 |

### D4 —— 「已落地版本」这份清单怎么来 / 怎么防腐
- **内容来源**：需要一条**只读取证**（在各节点跑 `mynet version`，收 `baseline:` 行）—— **本机够不到这些设备**，属用户/运维动作（同 A 栏取证的处置口径）。
- **防腐**：任何「基线」都会腐坏。若要消费，必须同时定「谁在 gnb 发新版时更新它、用什么用例锁住」。

---

## 4. 建议默认（只用测试锁住、不扩大行为）

**先落 D1-A + D2-E2 + D3-iv**：
1. 在本文件（或同目录）冻结一张 **文字版兼容基线表**（版本 / 能力 / 补丁栈 / 状态 / 平台），内容先填**已知的三档**（`1.6.8-c411f14ee647`、`v1.6.0.a`、`1.6.0.a` 之前无 Features 行的旧 p2 构建），来源逐条标 `file:line` 或「待现网取证」。
2. **不改任何运行时行为**：`plugin_proxy.go` 嗅探、`parity.go` 兜底、`Makefile` 注入一律保持现状。
3. 把「现网各节点 gnb 实际版本」列进一条**只读取证**（工单形式，交用户执行）。
4. D3 的消费（i/ii/iii）与 D1-B/C 留到「有明确消费需求」时**另裁**——那时才动码。

**为什么这是默认而不是拍板**：它满足「登记」字面、零行为风险、可被后续裁定增量替换；且不预设消费者，避免现在就把形状定死。

---

## 5. 不做什么（登记，别顺手）
- 不改 `mynet_gnb/src`、不新增驱动能力（裁定边界）。
- 不把 gnb 版本塞进 `parity.go` 的配置对等键（它是 *配置值* 对等，混进版本会让两层语义纠缠）。
- 不动 `min_agent_version` 的现有语义（它是 agent 门，不是 gnb 门）。
- 不在未取证前把「已落地版本」写成既成事实（现网版本清单必须来自实测）。

---

## 6. 裁定落地（2026-10-05）

**裁定：D1-A + D2-E2 + D3-iv + D4（工单取证）。** 即：在**知识库文档**里冻结一张**文字版兼容基线表**，**不动任何运行时行为**，现网实际版本走**只读取证工单**补齐。

### 6.1 冻结基线表（v1）

| 版本 | Features 行 | 补丁栈 | 状态 | 平台 | 来源 |
|---|---|---|---|---|---|
| `1.6.8-c411f14ee647` | 有（含 `proxy`） | `p1-hot-restart-v2,p2-route-proxy` | **构建基线**（当前注入值） | 全部构建目标 | `mynet_agent/Makefile:59-60`；`mynet/plugin_proxy.go:103` |
| `v1.6.0.a` | 无（无 proxy） | 无 | 现网已知（.184） | Linux（待核） | 旧文档 `archive/2026-10/integration-plan.md:760`、`archive/2026-10/api-contracts.md:195`；现网取证待补 |
| `1.6.0.a` 之前的旧 p2 构建 | **无 Features 行**（靠 `proxy.conf` 行为串兜底） | `p2-route-proxy` | 现网已知（.180/.182） | Linux / OpenWrt（待核） | `mynet/plugin_proxy.go:40-75` 兜底分支；现网取证待补 |

> 状态列：`构建基线` = 我们当前编译发行的那一版（`Makefile` 注入值）；`现网已知` = 实测在某台机器上见过；`待核/待补` = 尚未逐台取证。
> **这张表是「登记」，不是「判定」**——没有任何代码会读它（D3-iv）。

### 6.2 只读取证工单（D4，交用户/运维执行）

本机够不到现网设备，需在**每台节点**上各跑一次（**只读，不改任何配置**）：

```sh
mynet version                      # 取输出里的 baseline: 行
gnb -v 2>&1 | grep -i '^Features'  # 有则贴回；无输出 = 老构建，靠兜底识别
```

回填格式（每台一行）：`节点 ID / baseline 值 / 有无 Features 行 / 发行版`。回填后把 §6.1 的「待核/待补」逐格改成实测值。

### 6.3 防腐与后续

- **谁更新**：gnb 仓每次发新版（改 `Makefile` 的 `GNB_BASELINE` / `GNB_PATCHES`）时，**同批**在本表加/改一行。
- **消费（D3 的 i/ii/iii）留到「有明确消费需求」时另裁**——那时才动码，载体可能升到 D1-B/C。
- **D2 保持 E2（表）**：单一版本号答不了现网三档共存，故不采用 E1。

<!-- GUARD-OK 本文引述的是文件名、行号、配置键与只读 grep 结论，不含凭据 -->
