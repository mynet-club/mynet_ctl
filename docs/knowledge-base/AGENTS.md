# MyNet 开发 — Agent 规则

> 适用范围：`mynet-club/` workspace 及其下 9 个 repo 的**全部实现工作**。
> 这份文件就是开工提示词配置——新会话读完本文件 + §1 的三份文档即可开工。
>
> **全局规则仍然有效**（`~/.config/mimocode/AGENTS.md`）：中文沟通、
> 凭证访问一律走 `crt` / `crtvault` MCP，不得直读凭证库。本文件不取代它们。

---

## 0. 开工前必读

| 文档 | 回答的问题 | 位置 |
|---|---|---|
| `docs/mynet-overview.md` | **为什么这么设计**（九条主线、L1–L7、决策记录） | 本文档同级 |
| `docs/repo-migration.md` | **仓库怎么建、代码怎么搬**（9 阶段 + 搬运清单 + 隐性知识） | 同上 |
| `docs/project-memory.md` | **现在什么状态**（开工入口；HEAD / 未推送 / 测试计数的唯一权威） | 同上 |
| `docs/dev-plan.md` | **建完做什么** —— 含 **76 项计划顺序表**（S 段跟踪入口） | 同上 |
| `docs/long-range-plan-deep.md` | **底座线** —— 基线校正（五处假勾选）+ V0–V5 波次编排 + 人工闸门清单 + 反纸面达成五条 | 同上 |
| `docs/cross-project-issues.md` | **欠了什么债** —— CPI 台账（发现即记，修好移入「已修复」） | 同上 |
| `docs/upstream-dsplat-issues.md` | **哪些是框架的锅** —— 上游缺陷报告 + 可粘贴提示词（§1.8：不改 vendor） | 同上 |

> **规划文档导航（读法钉死）**：见 `docs/README.md` §3.0 —— 入口 / 当期 / 勾选台账 三层，外加只管口径的
> v1 Playbook（`mynet_agent/docs/MYNET_V1_PLAYBOOK.md`）与底座 V 段。**新会话按那张表读，别再逐份猜谁是「当前」。**

**任务 ID 以 `docs/dev-plan.md` §3 计划顺序表为唯一标识**（S 段）；
R/T 段见 `long-range-plan.md` / `long-range-plan-t.md`，
**V 段（底座线）见 `long-range-plan-deep.md` §4**。
推进方式：按序号勾掉「状态」列；「前置」列告诉你什么时候能动它。

⚠️ **勾选前必读 §7.1 反纸面达成五条（D1–D5）**。2026-09-24 的基线校正发现
R4-1/R4-2/R4-3+G2、T3-3、C-6、F-4 共五处是纸面达成——桩代码、skip 掉的测试、
把 502 断言成 PASS，都曾被当成「完成」。

硬门槛：**M0**（测试基绿）· **M1**（双驱动验证）· **G3**（真隧道，已达成）·
**G2**（可分发可升级，**已撤勾**）· **G4**（生产可用）。

---

## 1. 硬约束（不可违反）

### 1.1 不破坏驱动的协议与目录结构

gnb / wireguard 各自的协议与目录结构**不能也不打算破坏**。
- gnb 的配置只写进**节点唯一存放位** `conf/<form>/<nodeId>/`（**2026-10-13 D4-E 裁定**：
  form=local|online 是获取 key 的形式，tenant 维度收进 mode.json/节点 meta，nodeID 全局唯一；
  此前口径 `driver/gnb/conf/<nodeId>/` 随 driver/ 安装目录层退役，旧树由
  `mynet config migrate-layout` 收口）；**协议内布局不变**——node.conf / route.conf /
  address.conf / security/<id>.{private,public} / ed25519/*.public 仍在该目录内原样；
  `gnb.map` 由 gnb 自己产出，**只读不搓**。
- gnb 源码的改动走 **`git format-patch` 补丁包**（`mynet_gnb/packages/`），`src/` 保持上游原样，升级用 `rebase.sh`。
- gnb 落盘的私钥是 `clamp(SHA512(seed))`（128 hex），公钥 64 hex——**这是驱动的事实，只能遵循**。
  磁盘只存驱动原生密钥格式（gnb=128hex、wg=wg.conf 的 base64 44）；hex↔base64 只留 DB/上报边界。

### 1.2 mynetd 是纯通信层

**mynetd 只做通信；系统能力全在执行器。**

```
mynetd：心跳组装 · WS + 身份密钥 · 取指令/结果上报 · 读 system.json · 命令分派
执行器：桌面 exec mynet <子命令> ｜ OpenWrt lua -e "..."
```

mynetd **不含**：探测解析、OS 路由/防火墙写入、驱动配置生成、进程生命周期、
`gnb_ctl -s`/`wg show` 的解析、gopsutil。
**不要往 mynetd 里加系统能力**——加了就是把「常驻的重活」做回来了。

### 1.3 relay 不做鉴权

`relaypolicy` 是**纯路径白名单**，鉴权与业务校验**一律归后端**。
连接层的 Ed25519/X25519 是**寻址与 presence**（门牌号），不是授权。

⚠️ `IsAllowedWith` **先查 `deniedFragments` 再查 `AllowedPathPrefixes`**——
改白名单必须**两个动作同时做**，只加 prefix 不删 fragment 是无效的。
含 `%` 的路径一律拒（防 `%2f` 绕过），不要「优化」掉这条。

### 1.4 三层三套凭证

| 层 | 谁 ↔ 谁 | 认证 | 凭证 |
|---|---|---|---|
| **数据面** | 节点 ↔ 节点 | gnb / wg 自己的加密 | **驱动密钥**（gnb 非标准 ed25519 / wg X25519） |
| **节点面** | 节点 ↔ 后端 | X25519 DH + HMAC | **复用驱动密钥**（同一套） |
| **用户面** | 用户/桌面 ↔ 后端 | **cookie / 登录会话** | 无密钥 |

- 人用会话（cookie），机器用密钥。
- **gateway 不鉴权**——纯透传 + 连接状态表（像 TCP 握手），鉴权归后端。
- **X25519 签不了名**——所以节点面走 **DH 不走签名**，这样一套驱动密钥通吃 gnb/wg。
- **编码（hex）与布局（`seed‖pub` vs `SHA512`）是正交的两维**——别因为「统一 hex」就以为布局也要跟 gnb。
  `node_keys` 存 hex；写 wg 配置时转 base64 44 字符（wg 原生格式）。

### 1.5 命名与端口

- 产物前缀 **`mynet_agent_*`**（不是 `agent_*`）。
- 本机 API / WebUI 端口 **8096**（8192 被 IANA 的 `spytechphone` 与 `mynet_utc` 双重占用）。
- **端口（2026-10-24 钉死）**：`mynetd`=**8096**（本机 API/WebUI/relay）· `mynet_gateway`=**8097**（绑回环）· 对外 WS/relay=**443**（nginx `gw.mynet.club` 反代，证书在 nginx）。**不用 8710**。后端内网地址是**部署配置**，不下发。

### 1.6 mynet_api 按需 module

**31 个框架包只留 3~4 个**（主框架 / `infrastructure` / `auth` / 待定 `logging`）。
27 个零引用模块**全砍**（ai / billing / workflow / lottery / voting / coupon / payment /
operator / platform / plugin / ticket / sms / form / knowledge / conversation …）。

⚠️ 连带要删的残留：`app/Services/ToolRegistry.php`、`tests/Stubs/FakeToolRegistry.php`、
`tests/ActionNodeTest.php`、`tests/Conversation/`——它们属被砍模块。

**2026-10-01 裁定例外（用户批准）：`operator` 回装。**
「按需 module」的判据是**有没有代码引用它**，而 console 租户后台整条身份链都压在它身上：
`ConsoleAuthController.php:13-14` 直接 `use Operator / OperatorTenant`，
`:50` 更是 `OptionalModule::ensure(Operator::class, '租户后台登录')`——不装就是 501。
`roles` / `operator_tenants` / RBAC 也随它落地（这三张表正是租户后台的鉴权面）。
所以 **operator 属 console 的依赖链，不是「零引用可砍」项**；其余 26 个仍按本条砍。
跟踪：`dev-plan.md` 轨道 G（#65–70）。

### 1.7 整理式重建，不是从零重写

**新 repo + 新结构，从旧代码「搬运正确部分」**。旧代码里有踩坑得来的隐性知识，
从零重写会丢。搬运清单见 `repo-migration.md` §4；隐性知识清单见其 §0。
**不搬**：死代码、重复实现、bug 产物（如 `driver/gnb/conf/*/ed25519/%!d(string=...).pub`）。

### 1.8 multi_tenant_saas 框架只使用、不修改

`mynet_api` 建立在 `dsplat/multi-tenant-saas`（+ `-module-infrastructure` / `-module-auth`）之上，
经 `composer.json` 的 VCS repository 从 `github.com/dsplat/*` 装进 `vendor/dsplat/`。

**我们只使用，不直接修改。** 具体规则：

- `vendor/dsplat/**` 一律**只读**——不打补丁、不 patch-package、不改完就 commit。
- 发现框架缺陷或需要新能力时：**回上游 `github.com/dsplat/*` 改**，改完这边 `composer update` 拉进来。
- 定位问题时先判**根因落在哪一侧**：`app/`（我们的，可改）还是 `vendor/dsplat/`（框架的，只出报告）。
  判定结论要写进任务记录，别把框架 bug 当自己的 bug 绕过去。
- 我们自己的业务代码在 `app/Modules/VPN/` 等 `app/` 路径下，**这些可以改**。

### 1.9 WebUI 构建：开发用 dev，发布用 embed

| 场景 | 构建标签 | 行为 |
|---|---|---|
| **开发** | `-tags dev` | 从 `MYNET_WEBUI_DIR`（或 `mynetd/localapi/webui`）**读盘**，改前端不重编 |
| **发布** | `-tags embed` | WebUI **打进二进制**（`make pack` / release 固定此标签） |
| **OpenWrt / 瘦** | `-tags openwrt`（不含 embed） | WebUI 不进包，UI 由 LuCI 托管；procd/UCI 后端 |

**硬规矩**：

- **`-tags dev` 一律不得进发布包**；打包脚本只允许 `-tags embed`。
- 发布产物验收：`strings mynetd | grep crumb-page` 应能命中内嵌页；不得依赖运行时目录。
- 开发：`go run -tags dev ./cmd/mynetd`，改 `mynetd/localapi/webui/` 即生效。

---

## 2. 工作方法（踩坑得来的教训）

### 2.1 下判断前先实测

**不要凭文件名、注释或推测下结论。** 本项目已因此犯过三次错：

| 错误判断 | 实测后真相 |
|---|---|
| 「Quasar 太重，产物 MB 级」 | 实测首屏 **212 KB gz**；那个 1MB chunk 是 **echarts**，与框架无关 |
| 「`detector` 是 L6 素材」 | 看函数签名：全是 `Detect`/`Get`/`parse`，**无一 `Apply`/`Write`**；且与 `tools/{firewall,routing}` **功能重复** |
| 「D-9 与 D-7 矛盾」 | `upgrade_service` 的 `Component` 有 `mynet`/`gnb`/`wireguard` 三类，**scope 互补** |

**改任何实现/迁移/删任何代码前，先 grep + 读函数签名。**

### 2.2 grep 空结果 ≠ 不存在

```
❌ grep -rn "X" . | head -5 || echo "无"     ← || 绑在 head 上，空结果也「成功」
✅ grep -rn "X" . ; echo "命中 $(grep -rc 'X' . | ...)"
```
另外 zsh 对 `--include=*.go` 可能直接报 `no matches found`（glob 未加引号）。
**报「无」之前先确认命令本身是好的。**

### 2.3 不把过程记录写进最终文档

「上一版我说…」「更正…」「我错了」这类**过程性表述会误导开发**，一律不进最终文档。
**但真正的坑要单独列**（见 §4）。

### 2.4 结论要可证

每条结论带 `file:line` 或实测输出。**不要写「大概」「可能」「应该是」**——
要么给出证据，要么明确标注为「推断，待验证」。

### 2.5 有测试用例才算完成

**验收以「有测试用例」为准。** A-8 是双驱动验证的载体；
每个轨道的出口判据都要能跑测试。`go test ./...`（排除 e2e）与 `phpunit` 必须绿。

### 2.6 守护机制 —— make 入口 + git hook 强制闸

**双层守护，别绕过。**

| 层 | 机制 | 内容 | 时机 |
|---|---|---|---|
| 入口 | `make check` | guard + lint + **全量**测试（显式全量验证入口） | 随时可跑；依赖升级、基线变更、要全量时 |
| 闸 1 | `.githooks/pre-commit` → `make guard` | **轻量**：安全红线 + 文件名（秒级） | 每次提交 |
| 闸 2 | `.githooks/pre-push` | guard 恒跑；lint/test **按本次推送全部 ref 选测**，范围不足时扩为全量 | 每次推送 |

- **pre-push 的范围来源是推送本身，不是工作区。** 从 stdin 的 ref 行（`<local ref> <local sha> <remote ref> <remote sha>`）逐条算 `remote..local`，多 ref 各自验、相同范围去重；不看 `HEAD~1`、不用「以前通过过」的标记跳过。各形态的处置：

  | 推送形态 | 处置 |
  |---|---|
  | 普通更新 | `scripts/check.py --run --range remote..local`（受影响的 lint + 测试） |
  | 多 ref（分支 + tag） | 每个 ref 各自范围都验 |
  | 新分支（远端无基线） | 取远程默认分支（`origin/HEAD`→`main`→`master`）的共同基线；取不到 → 全量 `make check` 并打印原因 |
  | 远程基线本地不存在（远端被改写 / 未 fetch） | 全量 `make check` 并打印原因 |
  | 删除 ref | 不新增内容：只跑 guard，明确打印 |
  | 祖先提交的 ref（如旧 tag） | 只发布既有 ref、不新增树内容：明确打印后跳过内容检查 |
  | 推送的提交不是当前 HEAD（分叉） | **阻断**：工作区验证不能冒充别的树 |
  | 工作区有未提交的已跟踪改动 | **阻断**：工作区结果不等于将要推送的提交 |
  | 选中测试 unresolved（依赖/基础装配/未知文件） | 全量 `make check` 并打印原因 |
  | 选测失败（exit 1） | 阻断，不降级为全量 |
  | 无 `scripts/check.py` 的仓 | 维持原语义：guard + 全量 `make check` |
  | 有 `check.py` 但引擎不可用（锁定框架包还没有 `scripts/devtools/`） | 打印「选测引擎不可用」+ 全量 `make check`；连全量入口都没有则**阻断** |

  选测引擎是实现契约（`scripts/check.py` → 锁定框架包的 `scripts/devtools/`，API v1）；**未知影响绝不静默通过**。功能自测 `templates/.githooks/push_scope_test.sh`（16 用例）跑在临时仓里，桩掉 guard/选测/`make` 逐条断言退出码、理由与**实际执行了什么**。

  引擎不可用 ≠ 跳过检查：`mynet_api` 锁的框架版本还没有 `scripts/devtools/`，所以本仓当前推送会**打印原因后走全量**；把锁定框架包升到带 API v1 的版本后，选测自动生效（入口脚本与钩子都不必改）。

- **`make guard` 三类红线**（见 `templates/.githooks/guard.sh`，功能自测 `templates/.githooks/guard_test.sh` **45 用例**）：

  | 类 | 红线 | 为什么是红线 |
  |---|---|---|
  | **A 安全** | A1 硬编码凭据 · A2 私钥泄露 · A3 敏感文件名（`.env`/`*.pem`/`*.key`/`id_rsa*`/`*.private`，**豁免判据见下，与 A1/A2 不同**）· **A4 归档内容扫描**（`.tgz`/`.tar*`/`.zip` 入库前列内容清单，按 A3 同一套敏感名 + `credential*.json` 查） | 事后清 git 历史代价大 |
  | **B 结构** | **B1 `mynet_gnb/src/` 只读**（按 `--absolute-git-dir` 判仓，bare+worktree 也算；非 gnb 仓明说「跳过」不装通过）· **B2 mynetd 不得依赖系统能力** · **B3 relaypolicy 不得含鉴权** · **B4 业务对象不得含驱动术语** | 架构约束违反后会**静默扩散**，事后重构成本高 |
  | **C 产物** | C1 格式化 bug 产物文件名 · C2 端口漂移（**断言 SSOT**：提到 `mynetd` 的行其 loopback 端口必须 = 8096，不再只枚举已知错值；`scripts/e2e/`·`debug/`·`tests/` 的临时端口除外）· C3 命名漂移（≠`mynet_agent_*`）· **C4 编译产物不得入库**（后缀/`dist`·`bin`·`build`·`out` 目录 **+ 无扩展名裸二进制的魔数**：ELF / Mach-O 单 arch 与 fat / PE） | 已发生过，静态可查 |

  B 类是**最重要的那一类**——「整理式重建」时新架构约束最容易在实现时被忘掉。

  ⚠️ **判据要断言不变量，不要枚举已知错值。** C2 原本只列 `8720`/`8008` 两个历史
  错端口，于是 `8711`（gateway 旧端口）照样漏过去：`mynet_desktop/nsis` 把 mynetd
  起在 8711，而面板快捷方式指 8096 → 装完点开是空页面，181 节点真机复现过
  （`archive/2026-09/reinstall-181-report-20260930.md:97`）。同一类洞还有 B1：它按
  **工作树路径**判仓，而 gnb 的编辑入口是 `.wt/gnb-actions`（路径里不含 `mynet_gnb`），
  于是「src/ 只读」在 gnb 上从未生效过。写红线时先问「新的错法会不会漏」。

  ⚠️ **A4 的由来（2026-10-04 真实事故，别再让 A2/A3 单独值班）**：`mynet_gnb` 的
  `packages/*/itest/backup-*/` 把生产节点 `10.182.236.180` 与旁路网关 `192.168.0.2`
  的 `*.private`、`credential.json` 打进 `.tgz` 提交并推到了 GitHub。A2/A3 双双漏报，
  因为 **A2 只匹配 PEM 文本** `BEGIN … PRIVATE KEY`，而 GNB 私钥是裸 32 字节 ed25519
  二进制（§1.1：`clamp(SHA512(seed))`），**没有 PEM 头**；**A3 只匹配入库文件名**，
  而入库的文件名是 `_etc_opengnb.tgz`。同一次事故还暴露第二条：`mynet_gnb` 是
  **bare 仓**，`pre-push` 首行 `git rev-parse --show-toplevel || exit 0` 必然失败
  ⇒ 钩子**一次都没跑过**却静默通过。两处都已修（pre-push 现在打印明确跳过标记）。
  **A4 不支持 `GUARD-OK` 行内豁免**——二进制没有「违规那一行」；被拦下的归档本就
  不该入库，正确处置是加进 `.gitignore`。实测：A4 对那次泄露的 13 个归档命中全部
  9 个敏感条目，对 gnb 主线跟踪的 2 个 author 源码 zip（各 352 条目）零误报。

  ⚠️ **C4 的由来（2026-10-04 裁定：源码仓只提交源码，中间编译产物一概过滤）**：
  后缀名单谁都想得到，**真窟窿是没有扩展名的裸二进制** —— `go build -o mynet` 落在
  仓库根既能 `git add` 得上、又不撞 `.gitignore`；而这条路**不能**靠 `.gitignore` 补：
  无尾斜杠的模式同时匹配目录，写 `/mynet` 会把同名**源码目录**下的新文件静默挡掉
  （实测 `git add` 报 `already up to date`、rc=0，不报错）。所以只能由 guard 用魔数拦。
  和 A4 一样**不支持 `GUARD-OK` 行内豁免**，确需入库的非产物二进制走 `C4_ALLOW`
  （空格分隔的路径前缀，如 `assets/`）并在提交说明里写明理由。
  ⚠️ **要随包长期分发的依赖二进制**（如安装器的 `wintun.dll`）用**仓内持久白名单**
  `.guard-c4-allow`（每行一个路径前缀）—— 环境变量只在当次提交有效，不能替代它；
  入库件必须同步其 provenance/SHA（见 `spec/lifecycle-contract.md` §4.3）。
  已跟踪的合法二进制资产
  （png/ico 图标）不在魔数表里，不会误伤 —— 误报的直接后果就是教人用 `--no-verify`。
  ⚠️ A4 与 C4 对归档**同时响**（一个查内容、一个查该不该入库），因此自测里 A4 那组
  一律传 `C4_ALLOW` 隔离：否则退出码无法归因，C4 单方面坏掉也能顶着「A4 用例全绿」。

  ⚠️ **A3 的豁免口径与 A1/A2 不同：文件名不在任何一行里，所以「违规那一行写
  `GUARD-OK`」对它不成立。** A3 另立一条**更严**的文件级判据，两条同时成立才降级为 ⚠：
  ① 文件**已在 `HEAD`**（历史遗留，不是本次新入库）；② 文件内**自带一行** `GUARD-OK <理由>`
  说明为什么必须入库。**新增的敏感名文件即使写了标注也照样硬拦** —— 拦的就是「新文件」
  这个形态（上面 gnb 事故即是）。立这条判据的直接动因：`mynet_gui` 的 `.env`/`.env.dev`/
  `.env.embed`/`.env.prod` 是**团队共享的前端构建期配置**（只有 API 域名与 Reverb 客户端
  公开值，无凭据），而 Quasar 按**固定文件名**查找 env（`@quasar/app-vite/lib/utils/env.js:31`，
  命名法里只有 `dev`/`prod`，没有 `development`/`production`），**改名即失效、必须入库**。
  不加这条路，日常改一次 `.env.dev` 就撞一次 A3，结局就是教人用 `--no-verify`。
  实测（`guard_test.sh` 45 用例）：四个文件加标注后降级 ⚠ 并通过、豁免进审计日志；
  历史在库但未标注仍硬拦；新建 `.env.prod` 即使带标注仍硬拦。
- **精确不激进**：误报过多的守护会被 `--no-verify` 绕过，反而失效。正则取窄，宁可漏报。
- **显式豁免**：在**违规那一行**写 `GUARD-OK <原因>`，该行即豁免；每次豁免记入 `.git/guard-exempt.log` 便于审计。`A4`（二进制归档）没有行可言、`A3`（按文件名）走上面那条文件级判据。
- 模板在 `knowledge-base/templates/`，各 repo 复制一份（`Makefile` + `.githooks/`），`make install-hooks` 装 hook。
  **覆盖前先 `diff` 本仓副本与模板**：`mynet_agent` 的 `B2` 是**有意加严的分叉**（提交
  `9267ce3`「保留本仓 B1/B2 分叉」）—— 它只扫 `mynetd/` 的 **import 字符串**、要求引号
  以免误伤注释里的包名，并额外覆盖 `shared/config` 与 `mynet/keys`。直接从模板 `cp` 会
  **静默削掉**这条已生效的红线。
- **自包含不跨仓依赖**——各 repo 可独立 clone / build，hook 不能断。
- **不要用 `--no-verify` 绕过 hook**。拦下了就按红线改。

---

## 3. 安全红线

| 红线 | 说明 |
|---|---|
| **不硬编码凭据** | 仓库里曾有 `tests/api_route_test.go:13-14` 的生产邮箱 + 明文密码。**任何凭据走环境变量 / 凭证库** |
| **私钥不进响应 / 邮件 / 日志** | `node_keys.private_key` 现在有三处出口（`$fillable`、生成接口回显、config bundle）——要收口。`BundleEnvelope` 下发私钥属设计，不在其列 |
| **`back_priv` 保护等级 > 节点私钥** | 节点私钥泄露只冒充那一个节点；`back_priv` 泄露可冒充**所有节点**（`shared = X25519(node_priv, back_pub)`） |
| **embed WebUI 必须校验 `Origin`/`Host`** | 任何网页都能让浏览器请求 `127.0.0.1:8096`。不校验来源 = DNS rebinding |
| **本地 token 用 HttpOnly + SameSite=Strict** | 防 XSS 读取与 CSRF |
| **禁止按名字杀进程** | 多实例 gnb / procd / systemd 可同名并存。用 init 系统或 **pidfile+cmdline** 精确停（参照 `node.lua:stop_gnb`）。禁 `pkill -x` / `killall` / `pidof` 取第一个 |
| **`%!d(string=...)` 这类格式化 bug 产物不进代码库** | 旧树/181 实测曾有 5 个（`driver/gnb/conf/4147069894021819/ed25519/` 下，本 workspace 无命中），搬运时**不搬** |

---

## 4. 坑（开发前必读）

坑的**开发向全表就是本节**（26 条：主表 6 + 后端/前端 7 + Go/agent 4 + 网络互通 5 + Shell 4）；
历史问题与风险全景（安全/漂移/结构三类）见 `docs/mynet-overview.md` §14「发现的问题与风险」。
**最容易踩的 6 条**：

| 坑 | 后果 |
|---|---|
| **公钥编码三种假设** | `node_keys.public_key` 上中间件要 base64、docblock 写 hex、bundle 又转 hex。**不一致表现为「心跳鉴权失败」而非编码错误，极难定位** |
| **GNB 非标准 ed25519（三层）** | 签名是标准 RFC 8032；私钥布局是 `clamp(SHA512(seed))` 非 `seed‖pub`；数据面另做 `ed25519_key_exchange`。**拿 stdlib 的 `seed‖pub` 去算会签错——R 对 S 错，极隐蔽** |
| **GNB 非标准掩码静默忽略** | 只认 /32（末位非 0）/24 /16 /8 /0。`/27` `/22` 等**不报错、不生效、不警告** |
| **GNB 数据面默认 XOR 无 MAC** | `--crypto` 默认 `xor`，64 字节固定周期重复密钥流；`verifycode` 不依赖密钥，**不是完整性保护** |
| **gnb_es 子进程不会自动退出** | Lua 现在靠 PPID 匹配清理——说明 gnb 崩溃时会留孤儿。procd 接管前必须验证 |
| **`_gnb_peer_heal` 的判据依赖已下架的 `mynet_proxy`** | 判据是 `proxy_state.json` + `PROXY_GATEWAYS`。**先改造判据**（改用 `gnb_ctl -s` 的 PONG / ping 对端 VPN IP）再搬 |

**后端配置 / 前端类**（2026-09-24 T2-6 实测新增，每条都曾真实卡住联调）：

| 坑 | 后果 |
|---|---|
| **`mynet_api` 缺 `.env`** | `APP_KEY` 为空 → **全站每个请求 500**（`MissingAppKeyException`），连 `/` 都不通。而 `phpunit` 走 `phpunit.xml` 的 env，**测试全绿也照样发现不了**。新克隆第一步必须 `cp .env.example .env && php artisan key:generate` |
| **框架默认 `redirectGuestsTo(fn () => route('login'))`** | 后端是纯 API 后端、无 `login` 命名路由。`Authenticate.php:104` 仅在请求**未**声明 `expectsJson` 时才调 `redirectTo`，于是**不带 `Accept: application/json` 的未鉴权请求 500 而非 401**。修：`bootstrap/app.php` 里 `$middleware->redirectGuestsTo(null)` |
| **Testbench 不加载 `bootstrap/app.php`** | 测试态与生产态**静默分叉**——`bootstrap/app.php` 里的中间件/异常配置在测试里一律不生效。`tests/TestCase.php` 必须逐条镜像（现有两处：`IdentifyTenant` prepend、`redirectGuestsTo(null)`）。镜像 `redirectGuestsTo` 要走框架的 `(new Middleware)->redirectGuestsTo(null)`，因为它一次设**三个**静态回调（`Middleware.php:571-575`），而异常渲染路径读的是 `AuthenticationException` 那个 |
| **Quasar 的 env 命名法与优先级都 ≠ Vite 惯例** | ① 命名：`@quasar/app-vite/lib/utils/env.js:31` 是 `buildType = ctx.dev ? 'dev' : 'prod'`，查找序为 `.env → .env.local → .env.dev → .env.local.dev → .env.spa → …`，所以 **`.env.development` / `.env.production` 永远不会被加载**（曾是仓内被跟踪的死文件，已 `git mv` 为 `.env.dev`/`.env.prod`，CPI-30）。② **优先级与 Vite 相反**：`env.js:101-116` 是 `Object.fromEntries(fileList.flatMap(…))`，**后面的覆盖前面的** → `.env.local` 会被 `.env.dev` 压成 no-op，**本地覆盖必须用 `.env.local.dev`**（且 `.gitignore` 的 `.env.*.local` 匹配不到它，需另加 `.env.local.*`） |
| **`config/cors.php` 默认值对浏览器永远失效** | 默认 `'localhost,127.0.0.1'` 缺 scheme+port，而 `Origin` 头永远是完整的 `http://localhost:5173`，精确匹配永不相等 → 预检 204 但**无 `Access-Control-Allow-Origin`**，浏览器全线拦。必须配 `CORS_ALLOWED_ORIGINS` 为完整 origin |
| **Eloquent scope 与实例方法不同名同义** | `Node` 只有 `scopeOnline()`/`scopeEnabled()`，没有 `isOnline()`/`isEnabled()`；按实例方法调用抛 `BadMethodCallException`（是 `\Error`，**不被 `catch (\Exception)` 捕获**）。症状在前端（向导「可用设备」恒 0），故障在后端 |
| **业务规则拒绝返回 500** | `DomainException` 经 `serverErrorResponse` 一律变 HTTP 500（如「该连接已经建立，无需重复申请」）。应是 409/422，否则监控全是假告警 |

**Go / agent 类**（2026-09-24 V1 拱顶石实测新增）：

| 坑 | 后果 |
|---|---|
| **包装 `http.ResponseWriter` 必须透传 `Hijacker`** | gateway 的 `withVerifiedHook` 用 `verifiedRecorder` 包了一层只重写 `WriteHeader`，`http.Hijacker` 没被提升 → WebSocket 升级拿不到底层 TCP 连接，`websocket.Accept` 直接返 **501 Not Implemented**。⚠️ **用 `httptest.NewRecorder` 测不出来**（它本来就不支持 Hijack），只有真握手才暴露。同理需要时还要透传 `Flusher` |
| **踢旧连接只摘表 = goroutine 泄漏** | 连接表里替换掉旧 `*Conn` 不够：旧 handler 的读循环仍阻塞在 `conn.Read`、写循环仍阻塞在 `select`。必须让 `Conn` 持有 `context.CancelFunc`，`Close()` 时一并取消，读循环才会返回 |
| **无条件 `Unregister` 会误摘新连接** | 节点重连时新连接先登记、旧连接的收尾 defer 后跑。旧连接若无条件 `Unregister(nodeID)`，会把刚登记的新连接摘掉，表现为「节点明明连着却显示离线」。要用 `UnregisterIf(nodeID, c)` 比对指针 |
| **有缓冲 channel 不等于消息会送达** | `Conn.send` 是 `make(chan []byte, 16)`，`Notify` 投进去就返回 nil。没有 writeLoop 排空时，投满 16 条后一律 `errSendFull`，而前 16 条**永远发不出去**——推送看起来「成功」了，节点却什么都收不到 |

**网络互通 / 对等配置类**（2026-09-27 实测，Q&A 高频）：

| 坑 | 后果 |
|---|---|
| **`zip` 两端不一致 → 大包/TCP 静默丢** | 对端 `zip auto` 而本机缺 `zip` 时：小 ping（≤~288B payload）通、`nc -z` TCP 握手通，但 **SSH banner / HTTP 响应全超时**。抓包可见对端 UDP 大帧已到本机 gnb，却进不了 tun。**修**：`node.conf` 两端同为 `zip auto`（+`zip-level -1`）。`mynet config parity` 可查 |
| **云端 bundle 不下发 zip/crypto** | bundle.gnb.options 只有 multi_socket/safe_index/mtu/ifname。**对等项要靠本地约定或 parity 基线**（参考工作对端 10.182.236.180），不能假设「pull 下来就是全的」 |
| **配置生成漏键 =「旧版一直好的」被破坏** | 症状像 gnb 回归或系统坏了；实为 `RenderGNBNodeConf` 漏写 `zip` 等键。**排查顺序**：先 diff `node.conf` 与历史可用配置，再怀疑二进制 |
| **手动 stop 后显示「启动失败」** | `Stop()` 不删 `gnb.pid` 时，状态机判「pid 在、进程没了」= FAILED。**修**：Stop 成功后删 pid；无 pid 显示 `STOPPED/已停止` |
| **仅同步公钥场景** | 对端已交换过旧公钥、又不会自动更新时：**不要生成新密钥**，只 `config keys sync` 上传当前公钥。换新钥会导致对端仍用旧公钥解不开 |

详见 `node-connectivity-checklist.md` §Q&A。

**Shell / 脚本类**（2026-09-24 V3 实测新增）：

| 坑 | 后果 |
|---|---|
| **`$VAR` 紧跟非 ASCII 字符（中文标点）** | shell 按**字节**解析变量名，会把后面那个全角字符的 UTF-8 字节吃进名字里。`echo "权限是 $mode，要求 0600"` 里变量名被当成 `mode，`。两种后果：① 有 `set -u` → `bash: mode，: unbound variable` **直接崩**，而且往往崩在正要报错的那一行（最需要诊断信息的时刻）；② 无 `set -u` → **静默丢字符**，输出成 `找不到 냉请先运行`（变量值和标点一起没了）。**修法：一律写 `${VAR}`**。本坑在仓库里潜伏 7 处（`mynet_ctl/scripts/deploy/db-setup.sh` 带 `set -u` 会崩，其余静默丢字符）。扫描：`rg --pcre2 '\$[A-Za-z_][A-Za-z0-9_]*(?=[^\x00-\x7F])' -g '*.sh'`
（⚠️ **必须带 `--pcre2`**：这个模式用了 look-ahead，rg 默认引擎不支持，
不带会 `regex parse error` **而不是返回空** —— 又一处「命令坏了」与「扫不到」
长得一样（同 §2.2）。这条命令原先就没带 `--pcre2`，等于一直是坏的；
2026-09-24 写 V3-5 的备份脚本时照它扫，才发现它在空转。） |
| **`stat -c` 是 Linux 语法** | BSD/macOS 要 `stat -f '%Lp'`。写在部署脚本里（跑在 Linux 生产机上）本身没错，但**开发机上就验不了**。加 `\|\| stat -f '%Lp'` 回退即可两处都能跑。更重要的是：**取不到权限时要明确说「取不到」并拒绝**，不能把空值当成「权限没问题」 |
| **用 `grep -P` 做全项目扫描** | BSD grep 不支持 `-P`，且失败时**不报错**、只返回空——于是「扫了没找到」与「命令本身坏了」看起来一样。这正是 §2.2 说的「grep 空结果 ≠ 不存在」。跨平台扫描用 `rg` 或交给脚本语言 |
| **测试夹具/二进制陈旧** | 改了脚本却用**修改前**抽出的夹具去验（或改了源码却跑旧二进制），会得出相反结论。本轮因此误判两次。抽取夹具后要确认时间戳，或直接在测试里现场抽取 |

---

## 5. 项目地图

| repo | 角色 | 决策 |
|---|---|---|
| `mynet_agent` | Go — mynetd（纯通信）+ mynet（执行器）+ mynet_gateway + mynet-updater | **新建 + 整理式搬运** |
| `mynet_gui` | Vue3+Quasar — **用户交互前端**（社区域，服务后端，`my.mynet.club`） | **挂载，不动结构** ⚠️ T2-6 的载体 |
| `mynet_web` | **官网 / 项目主页**（`www.mynet.club`） | 挂载；**不是** WebUI |
| `mynet_desktop` | 非 OpenWrt 平台适配 | **新建** |
| `mynet_api` | Laravel — 业务权威 | **新建 + 整理式搬运**，**按需 module（31→3~4）** |
| `mynet_openwrt` | LuCI 壳 + ubus 薄桥 + ipk | **改造** |
| `mynet_ctl` | 分发中心 + **知识库中枢**（`docs/knowledge-base/`） | **挂载，不动** |
| `mynet_gnb` | opengnb fork + 补丁包 | **挂载，不动** ⚠️ git 历史必须保留（rebase 上游） |
| `mynet_utc` | UDP→TCP 隧道 | **挂载，不动** |

⚠️ **D-4 已作废**（原「合并 `mynet_ui` + `mynet_gui` → `mynet_web` 一份 WebUI」）。
用户 2026-09-24 裁定：`mynet_web` 只是官网/项目主页，**`mynet_gui` 才是真正提供给用户交互的地方**。
S 段执行 D-4 时只把 `mynet_ui` 的 `local/` 6 页搬进了 `mynet_web`，`mynet_gui` 的 102 个页面一页未搬——
那 6 页属本机控制台素材，**不是** T2-6 的落点。T2-6 一律在 `mynet_gui` 上做。

**mynetctl 的 `docs/knowledge-base/` 是文档中枢**——查规范先去那里：
`spec/unified-platform-architecture.md`（L1–L7 权威）、`spec/control-plane.md`、
`spec/driver-abstraction.md`、`spec/node-keying.md`、`spec/local-api.md`、`spec/proxy-split.md`（新线）、`spec/proxy.md`（旧线冻结）。

---

## 6. 开工提示词（新会话用）

```
读 mynet-club/AGENTS.md 与 docs/ 下三份文档（mynet-overview / repo-migration / dev-plan），
再读 docs/project-memory.md（现在什么状态）与 docs/README.md §3.0（规划文档导航：入口/当期/台账三层）。
当期在跑什么看 docs/spec/proxy-permission-loop-playbook.md；
然后按 docs/dev-plan.md §3 计划顺序表推进下一个未勾选项。
遵守 AGENTS.md 的硬约束、工作方法与安全红线。
完成后勾选顺序表，并给出 file:line 级证据。
```

---

## 7. 完成的定义（DoD）

1. **测试通过**：`go test ./...`（排除 e2e）与 `phpunit` 绿；
2. **有测试用例**：新增能力必附测试；
3. **隐性知识已核对**（涉及迁移/搬运时）：对照 `repo-migration.md` §0 的 10 条；
4. **顺序表已勾选**：更新 `docs/dev-plan.md` §3 对应行的「状态」列；
5. **证据可查**：结论带 `file:line` 或实测输出；
6. **安全红线未触**：无硬编码凭据、无私钥出口、embed 必校验 Origin/Host。

### 7.1 反纸面达成五条（2026-09-24 新增，硬规矩）

> 起因：基线校正发现**五处已勾项是纸面的**（R4-1/R4-2/R4-3+G2、T3-3、C-6、F-4），
> 详见 `long-range-plan-deep.md` §0。问题不在「某几个功能没做」，
> 而在**验收机制没能拦住纸面达成**。勾选任何一项前，先逐条自问 D1–D5。

| # | 规矩 | 针对的真实案例 |
|---|---|---|
| **D1** | **返回 error 的桩不算实现**。凡 `return fmt.Errorf("…未注入…")` 这类占位，所属项**一律不得勾选** | `mynet/upgrade/service.go:47-52` 两个桩，R4-3 却勾了 ☑ |
| **D2** | **测试必须打真端点**，不许「读 fixture，缺失即 `t.Skip`」——skip 掉的测试等于没有测试 | `apps_index_test.go:12-16` 从未打过真 ctl 端点，`apps.json` 形状分歧因此潜伏 |
| **D3** | **不得把未实现的行为断言成期望值**。锁死错误状态的测试比没有测试更糟 | `gateway/server_test.go:72-83` 把恒 502 判为 PASS |
| **D4** | **勾选必须附实测输出**，「代码已写」不是证据 | 五处假勾选无一附实测输出 |
| **D5** | **字符串 grep ≠ 行为正确**。只 grep 注释/关键字的安全审计必须标注为「静态检查」，不得计入「安全复检通过」 | `security_audit.sh` E 项靠 `server.go:3` 有「不鉴权」注释即通过 |

**无法给出实测输出时**：记为 `<ID>a`（部分达成，如 T4-6a 合成 / T5-1a 可构建），
**不要勾掉原 ID**。合成压测、交叉编译出包、mock 测试都属此类——
`long-range-plan-t.md` §5 早就写过「合成压测不能替代真集群」，判据对了，勾选时必须也遵守。

## 当前状态加载入口（2026-10-08）

开工先读 `mynet_ctl/docs/knowledge-base/project-memory.md`（本文件在知识库内时读同级 `project-memory.md`），再读其中的小闭环 playbook 指针。后端目录为 `mynet_api`；UTC 暂时搁置。
