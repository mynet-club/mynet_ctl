---
doc_id: mynet-manage-skill-kit
title: MyNet 管理技能生成包（Skill Kit）
version: 1.0.0
updated_at: 2026-10-01
status: 现行
audience:
  - user          # 拿到 mynet 二进制的人：建立目标、查配置、调设置
  - agent         # MiMo Desktop / Claude / Codex / 任意 agent：据此生成管理 skill
artifact: skill   # 本文件产出物是「管理 skill」，不是运行时二进制
upgrade:
  # 自升级只认下面这些地址；技能内嵌 version，不一致就按 §8 重生成
  # 实现：mynet_ctl DocsController → 读本文件（SSOT），路由 GET /d/docs/{name}
  canonical_path: mynet_ctl/docs/knowledge-base/skill-kit.md
  urls:
    - https://ctl.mynet.club/d/docs/skill-kit.md
  checksum_url: https://ctl.mynet.club/d/docs/skill-kit.md.sha256
  binary_index: https://ctl.mynet.club/d/apps.json   # 二进制升级走 apps.json / mynet update
security_class: public   # 本文可公开分发；绝不夹带账号、密码、私钥、token
---

# MyNet 管理技能生成包（Skill Kit）

> **一句话**：本文是「怎么用 `mynet` 二进制把一台节点管好」的**唯一事实源**。
> 人直接照做；agent 照着生成 / 自升级管理 skill。
>
> **升级地址**见上方 frontmatter `upgrade.urls`。技能发现 `version` 落后时按 §8 自升级。

---

## 0. 自描述（Self-description）

| 字段 | 含义 |
|---|---|
| **这是什么** | 管理技能生成包：目标定义、配置体检、设置调整、安全边界、CLI 能力面 |
| **给谁用** | ① 终端用户（有 `mynet` 二进制）② 任意 agent（要产出管理 skill） |
| **产出物** | 一个**管理 skill**（SKILL.md 或等价提示词），覆盖安装/认领/同步/密钥/启停/诊断 |
| **不是什么** | 不是连通性排错手册（见 `troubleshoot-connectivity.md`）；不是 API 全集；不是开发规范 |
| **权威链** | 本文 → `spec/paths.md` / `AGENTS.md` / `mynet.md`；冲突时以更细的 SSOT 为准并在技能里注明 |
| **版本** | `1.0.0`（frontmatter `version`）。改本文必须升版本号 |
| **自升级** | 见 §8。技能内嵌 `kit_version` + `upgrade.urls`，可检测并重生成 |

### 0.1 读者路径

```
用户：  §1 目标 → §3 安全 → §4 环境 → §5 查配置 → §6 调设置 → §9 速查
agent： §0 自描述 → §2 能力边界 → §7 生成规范 → §8 自升级 → 输出 SKILL.md
```

### 0.2 依赖的二进制

本文假定你已拿到 **`mynet`（执行器）**，可选 **`mynetd`（守护）**。
版本与构建戳：

```bash
mynet version
# 期望：v… + baseline/patches/builder 三行（B4 版本戳）
mynet platform
# 期望：os/arch，例如 darwin/arm64
```

二进制升级地址：`upgrade.binary_index`（`apps.json`），或本机 `mynet update --check`。

---

## 1. 建立目标（Goal）——先想清楚再动手

管理一台节点前，先回答三问。**答案写进你的操作记录**（或 skill 的「当前目标」段）。

### 1.1 三问

| # | 问题 | 合法答案示例 | 不合法 |
|---|---|---|---|
| **G1 这台机器是谁？** | 新节点上线 / 恢复备份 / 重装系统后找回身份 / 仅调试 | 「新节点，node_id=…，租户=…」 | 「随便搞搞」 |
| **G2 目标状态是什么？** | 隧道可通、配置与云端一致、仅本地实验、替换旧身份 | 「与云端 bundle 一致并 `service start`」 | 「装完再看」 |
| **G3 什么绝不能丢？** | 身份密钥、token、既有对等公钥、生产路由 | 「私钥与 peer 公钥原样保留」 | （空） |

### 1.2 目标模板（复制填写）

```text
目标声明
- 节点 ID:        <node-id 或 待 claim>
- 租户 / Zone:    <tenant>
- VPN 驱动:       gnb | wireguard
- 模式:           local | online
- 目标状态:       <一句话>
- 禁止事项:       <如：不换密钥 / 不卸载 / 不动 DNS>
- 验收命令:       mynet check && mynet config status && mynet service status
```

### 1.3 目标 → 动作映射

| 目标类型 | 主路径（按序） | 验收 |
|---|---|---|
| **A. 全新上线** | install → auth login → tenants → node claim → setup → keys → config sync → service start | check 全绿 + service status 运行 + 能拉到 bundle |
| **B. 恢复身份** | config export（旧机）→ 传包 → config import → config keys sync → config sync | `config keys` 的 public 与旧机一致；parity 正常 |
| **C. 对齐云端** | config status → config pull / sync → config parity | parity 无关键不一致 |
| **D. 调设置** | 先 §5 体检 → 改 conf/** → apply/refresh → 回归 | 目标字段生效且 check 不劣化 |
| **E. 卸载/退役** | config export 留底 → service stop → uninstall（保留 conf） | 人确认身份是否销毁 |

**禁止跳步**：G1 未答清不要 claim；G3 未答清不要 `keys generate` / `uninstall`。

---

## 2. 能力边界（agent 生成 skill 时必须写进负面触发）

**本 skill / 本文管：**

- 安装、登录、租户/节点认领、配置树（setup）
- 配置拉取与投影（`config pull/sync`、`refresh-*`、`apply`）
- 密钥导入/生成/上传公钥（`config keys *`）
- 备份恢复（`config export/import`）
- 进程生命周期（`service *`）
- 只读诊断与门禁（`status/check/subject/route doctor/firewall doctor/tree/gate`）
- 自更新与驱动管理（`update` / `driver`）
- 插件与 DNS/代理的**有意识**调整（先 status 留底）

**不在本文 / 不在管理 skill 内解决：**

- 隧道不通、ping 失败、单向通、zip 大包丢、密钥对不上 → **连通性 skill / `troubleshoot-connectivity.md`**
- 修改 `mynet_agent` 源码、修框架、写补丁 → 开发规则见 `AGENTS.md`
- 向用户索要或回显明文密码、私钥、token → **一律拒绝**（见 §3）

---

## 3. 安全总则（最高优先级，每次操作前复习）

> 账号、密码、密钥、token 是**身份**。管理操作可以失败，身份不能丢、不能泄露。

### 3.1 账号与密码

| 规则 | 做法 |
|---|---|
| **密码不进参数历史** | 优先 `mynet auth login … --password-stdin`；避免 `--password '…'` |
| **密码不进聊天/日志/文档** | agent 不得要求用户粘贴密码到对话；需要时引导用户本机输入 |
| **密码不进仓库** | 禁止写入 skill、脚本、README、示例配置 |
| **凭证走保管库** | 机器/服务凭证用 `crt` / `crtvault`（见全局 AGENTS.md），不直读凭证目录 |
| **Token 同等敏感** | `~/.mynet`、`conf/`、`var/token` 内容不进聊天输出；`config keys --json` 不要整段回显 |

### 3.2 密钥（节点身份）

| 规则 | 做法 |
|---|---|
| **默认不换钥** | 已有身份只 `config keys sync`（上传 **public**）；`keys generate` 仅「新节点 / 用户明确要换身份」 |
| **私钥永不离开目标机** | 不经聊天、不进 git、不写进 skill；导出用 `config export`（打包留在本机/受控备份） |
| **私钥文件 0600** | `conf/**/nodes/*/keys/` 下私钥权限；恢复后核对 |
| **公钥可同步** | `keys sync` 只上传 public；`keys --json` 只引用 public_hex 作对照 |
| **换钥是事故级操作** | 必须：备份 → 用户书面确认 → 对端公钥同步计划 → 回归；否则禁止 |
| **日志与响应** | 私钥不进日志/邮件/HTTP 响应（安全红线，见 `AGENTS.md` §3） |

### 3.3 破坏性操作清单（必须二次确认）

- `mynet config keys generate`（新密钥）
- `mynet config import`（覆盖现网身份，虽有 pre-restore）
- `mynet apply --clear`
- `mynet uninstall`
- `mynet dns apply` / `proxy resolv`（改系统 DNS）
- 任何 `sudo` 写系统路由/防火墙

二次确认话术：**做什么 · 影响哪个 node_id · 是否可回滚 · 备份文件路径**。

### 3.4 Agent 硬规则

1. 不把密钥/密码/token 写入 skill 正文或示例输出。
2. 不「帮忙生成」假密码填进命令。
3. 用户要求泄露时：拒绝并说明走本机 `crt reveal` / 人工输入。
4. 报告里对敏感字段用掩码（`public_hex` 可给前 8 位 + 长度；私钥只报「已导入/未导入」）。

---

## 4. 环境确认（拿到二进制后的第一件事）

按序执行，**每步贴输出再进入下一步**：

```bash
# 4.1 二进制从哪来、什么版本
command -v mynet || ls -l /opt/mynet/bin/mynet /etc/mynet/bin/mynet
mynet version
mynet platform

# 4.2 Store Root 显式存在（禁止静默回退）
echo "MYNET_HOME=${MYNET_HOME:-}"
# 若空：从服务单元读，或由用户指定。缺则管理命令会失败并提示。

# 4.3 只读体检
mynet check
mynet config status
mynet subject --json    # 若已有 gnb 进程/配置
```

### 4.1 `MYNET_HOME` 怎么定

| 场景 | 做法 |
|---|---|
| 已 `mynet install` | 读 systemd/launchd 单元里的 Environment，与用户确认后 `export` |
| 开发/试跑 | 用户**显式**指定空目录或测试根；不要猜 `~/.mynet` |
| 多棵树可疑 | `mynet tree doctor`；运行根只认 `ps` 的 `-c`（见 `spec/paths.md`） |

铁律：**`MYNET_HOME` 未设置就拒绝写操作**（执行器本身也会拒）。不要用相对路径「先试试」。

### 4.2 体检结果怎么读

| 输出 | 含义 | 下一步 |
|---|---|---|
| `check` 有 `✗` | 缺能力/缺文件 | 看具体行；`ip 不可用` 等属平台能力，记 skip 不要谎称通过 |
| `config status` 未初始化 | 无 mode/profile | 走 §1 目标 A/B |
| `未登录` | 无 token | `mynet auth login` |
| `读 bundle 失败` | 本地无 bundle | `mynet config pull`（refresh-* 不访问网络） |

---

## 5. 检查配置（Check）——调整前必做快照

**原则**：先看清再改；改前可回滚；改后有对比。

### 5.1 标准体检单（复制执行）

```bash
export MYNET_HOME=...   # 已确认的 Store Root

mynet version
mynet check
mynet config status
mynet config keys --json          # 只记录 public 前缀/长度，私钥状态用有/无
mynet config parity               # 对等一致性（zip/crypto/passcode…）
mynet subject --json
mynet service status              # 或 service status gnb
mynet tree doctor --json
# 可选门禁（再部署前）
mynet gate --json
```

### 5.2 快照与备份

```bash
mynet config export -o "pre-change-$(date +%Y%m%d-%H%M%S).tar.gz"
# 或先 dry-run 看将写入什么
mynet config import <file> --dry-run
```

- 导出包是「这台节点是谁」（`conf/**`、`mode.json`、token/system.json），**不含** `driver/**` 投影。
- 恢复前工具会打 `pre-restore-*.bak`；仍建议人手留一份 export。

### 5.3 检查什么（对照表）

| 检查项 | 命令 | 期望 | 异常时 |
|---|---|---|---|
| 版本/构建 | `mynet version` | 有 builder 时间戳 | 旧二进制可能缺 gate/firewall doctor |
| 身份归属 | `node list` / `config status` | node_id、tenant 明确 | 回 §1 G1 |
| 密钥在否 | `config keys` | 有 public；私钥按目标保留 | 缺则 keys set/generate（见 §3.2） |
| 配置源 | `config status` + bundle 时间 | pull 后与云端一致 | `config pull` 再查 |
| 对等一致 | `config parity` | zip/crypto 与对端同约定 | 按 checklist，不盲改 |
| 主体 | `subject` | bin/conf/map/ctl 同套件 | 转连通性/subject 排查 |
| 服务 | `service status` | 与目标状态一致 | 见 §6 |
| 配置树 | `tree doctor` | 无漂移告警 | `tree consolidate --dry-run` 先看 |
| 门禁 | `gate` | 五闸 pass（skip 须有原因） | **skip ≠ pass** |

---

## 6. 调整设置（Adjust）——最小变更 + 可回滚

### 6.1 谁能改、改哪里

| 层 | 路径 | 可否手改 | 说明 |
|---|---|---|---|
| **真相源** | `$MYNET_HOME/conf/**`、`mode.json` | ✅（有意图地改） | 进备份 |
| **投影** | `$MYNET_HOME/driver/**` | ❌ 禁止 | `apply` 会覆盖；改了当事故 |
| **运行态** | `$MYNET_HOME/var/**` | ❌ | 可再生 |

改意图/身份 → 在 `conf/**`；改投影结果 → 改 conf 后重新 `apply` / `config sync`。

### 6.2 常用调整配方

| 意图 | 命令序 | 备注 |
|---|---|---|
| 拉新配置并生效 | `mynet config sync` | = pull + 投影 + OS 路由 |
| 只拉配置不装路由 | `mynet config pull` | 需要时再 `apply --active --auto` |
| 本地重投影 | `mynet refresh-config` / `refresh-routes` / `refresh-address` / `refresh-keys` | **不访问网络**；要拉新先 pull |
| 切 local/online | `mynet config use local` 或 `use online --tenant T [--node N]` | 先 `config status` |
| 导入已有身份 | `config keys set --private … --public … --node-id …` 然后 `keys sync` | 私钥不进聊天 |
| 新节点新钥 | `config keys generate --vpn gnb|wireguard --node-id …` 然后 `keys sync` | 二次确认 |
| 启停隧道 | `mynet service start|stop|restart|reload [gnb|wireguard]` | 多实例勿按名杀进程 |
| 驱动安装/升级 | `mynet driver install|upgrade [gnb|wireguard]` | 与 `update` 分工：主程序 vs 驱动 |
| 自更新 | `mynet update [--check] [--dry-run]` | 校验和不符必须拒绝 |
| DNS 紧急恢复 | `mynet dns status` → 问题则 `dns restore` | 先留底 |
| 插件/分流 | `mynet plugin …` / `mynet proxy …` | 先 status；属高风险区 |

### 6.3 变更纪律

1. **一次只动一个变量**（只改密钥、或只改路由、或只升版本）。
2. **改完立刻回归**：`mynet check` + 相关 `status`/`doctor` + 目标验收命令。
3. **失败回滚**：`config import <pre-change.tar.gz>`，或文档化的逆操作；禁止「再乱改看看」。
4. **skip/未知命令**：二进制旧或能力未装——如实报告，不假装成功。

### 6.4 配置同步语义（最易用错）

| 命令 | 网络 | 投影 driver | OS 路由/防火墙 |
|---|---|---|---|
| `config pull` | ✅ | ✅ | ❌ |
| `config sync` | ✅ | ✅ | ✅ |
| `refresh-*` | ❌ | ✅（本地 bundle） | ❌ |
| `apply --active` | ❌ | ✅ | ❌ |
| `apply --auto` | ❌ | ❌ | ✅ |

云端 bundle **不保证**含 zip/crypto 等对等键——以 `config parity` 与本地约定为准。

---

## 7. 给 Agent：如何生成管理 Skill

> 目标：任意 agent（MiMo Desktop、Claude、Codex…）读完本文 + 本机 `mynet`，
> 能生成**一致、可升级、不泄密**的管理 skill。

### 7.1 产物形态

| 运行环境 | 产物 | 落盘 |
|---|---|---|
| MiMo Desktop | skill 目录 | `<project>/.mimocode/skills/mynet-cli/` 或 `~/.config/mimocode/skills/mynet-cli/` |
| Claude Code | skill / 项目指令 | 对应 skill 根或 `CLAUDE.md` 引用本文 |
| 其他 agent | 系统提示 / 技能文件 | 任意，但必须内嵌 §7.4 元数据 |

技能名建议：`mynet-cli`（稳定 ID，kebab-case）。

### 7.2 SKILL.md 必备结构

```markdown
---
name: mynet-cli
description: <WHAT> + Use when <触发短语列表> + 不要用于 <连通性排障/…>
---

# 标题
## 边界（链到连通性 skill / AGENTS.md）
## 开工三件事（version / MYNET_HOME / 只读探针）
## 任务 → 命令（对应本文 §6 配方，禁止杜撰命令）
## 安全硬规则（摘要 §3，必须保留）
## 输出要求（贴输出、skip≠pass、破坏性操作二次确认）
## 自升级（见 §7.4 / §8）
```

### 7.3 内容纪律（生成时）

1. **命令以本机 `mynet` 的 usage 与本文 §6 为准**，禁止臆造子命令。
2. **正文 ≤ 约 800 词**，细则回到本文或 SSOT；不要粘贴整本手册。
3. **描述必须含触发短语**（中文/英文），并含负面触发（连通性、开发改码）。
4. **安全段不可删**：密码 stdin、不换钥、私钥不外传、破坏性二次确认。
5. **证据要求**：结论带命令输出或 `file:line`；skip 不得写成 pass。

### 7.4 技能元数据（自升级用，必须写入 SKILL.md frontmatter 或独立 block）

```yaml
kit:
  doc_id: mynet-manage-skill-kit
  kit_version: 1.0.0          # 生成时抄本文 version
  source: mynet_ctl/docs/knowledge-base/skill-kit.md
  upgrade_urls:
    - https://ctl.mynet.club/d/docs/skill-kit.md
  generated_at: 2026-10-01
  generator: mimo-desktop    # 或 claude-code / codex / human
```

MiMo Desktop 的 `description` 保持面向触发；`kit` 块可放 frontmatter `metadata` 或文末「自升级」节——二者至少一处。

### 7.5 生成步骤（agent 执行序）

1. 读本文 frontmatter（`version` / `upgrade`）。
2. 跑 `mynet version` / `mynet help`（或无参 usage），核对命令面。
3. 按 §7.2 起草 SKILL.md，填 §7.4 元数据（`kit_version` = 本文 `version`）。
4. 自检：触发短语、负面触发、安全段、无密钥泄漏、命令可执行。
5. （MiMo）跑 skill 校验器；通过后宣布完成。

### 7.6 反模式（禁止）

- 把 `troubleshoot-connectivity.md` 正文复制进管理 skill
- 要求用户粘贴密码/私钥
- 写「应该是/大概是」而无输出
- 把 `gate`/`doctor` 的 skip 报成通过
- 私自 `keys generate` 或卸载
- 升级时静默改写用户目标/安全段

---

## 8. 自升级协议（Skill ⇄ Kit）

### 8.1 何时检查

| 触发 | 动作 |
|---|---|
| 用户说「升级 skill」「同步 skill-kit」「skill 自升级」 | 立即执行 §8.2 |
| skill 执行前发现命令面与 kit 可能不符（未知命令等） | 先版本对照，再升级 skill |
| 距上次检查 > 30 天或 major 版本可能跳变 | 建议执行 |

### 8.2 步骤

```text
1) 读本 skill 内嵌 kit.kit_version
2) 取 kit 正文：
   a. 工作区内: mynet_ctl/docs/knowledge-base/skill-kit.md
   b. 或 curl -fsSL <upgrade.urls[0]>
   c. 可选: curl -fsSL <checksum_url> 核对
3) 读新 kit frontmatter version
4) 若 version 相同 → 记录「已最新」，结束
5) 若 version 不同 → 按 §7.5 重生成/合并 SKILL.md：
   - 必须保留: 用户本地目标、非 kit 个性化备注
   - 必须更新: 命令面、安全段、kit_version、upgrade_urls
   - 禁止: 静默降低安全规则
6) 用新 skill 做一次只读冒烟: mynet version + mynet help
7) 向用户报告: 旧 version → 新 version，变更摘要，冒烟结果
```

### 8.3 冲突与降级

| 情况 | 处理 |
|---|---|
| 升级 URL 不可达 | 用工作区 canonical_path；都没有则保持现 skill，**明说无法升级** |
| 校验和不符 | **拒绝替换** skill 正文，报告风险 |
| 用户自改过 SKILL.md | 做合并：以 kit 的命令/安全为准，保留用户自定义章节 |
| 二进制超前于 kit | 以 `mynet` usage 为准补命令，并提示文档待更新；不杜撰 |

### 8.4 版本语义

- `major`：能力边界/安全规则变化 → skill 必须再生
- `minor`：命令配方/流程增强 → skill 应再生
- `patch`：措辞/索引 → 可选

---

## 9. 速查卡（打印级）

### 9.1 新节点最小闭环

```bash
sudo mynet install --gateways wss://…
mynet auth login --api https://… --email you@example.com --password-stdin
mynet tenants && mynet node list
mynet node claim <node-id>
mynet setup --online --tenant T --node-id <node-id>
mynet config keys generate --vpn gnb --node-id <node-id>   # 或 keys set 导入
mynet config keys sync
mynet config sync
mynet service start gnb
mynet check && mynet service status
```

### 9.2 体检五件套

```bash
mynet check
mynet config status
mynet config parity
mynet subject --json
mynet gate --json
```

### 9.3 安全三句（每次管理前）

1. 密码只 `--password-stdin`，不进聊天与仓库。
2. 已有身份不 `keys generate`，只 `keys sync` 上传公钥。
3. 私钥不离机、不进日志；破坏性操作二次确认 + 先 `config export`。

---

## 10. 参考（SSOT）

| 文档 | 回答 |
|---|---|
| `AGENTS.md` | 硬约束、安全红线、工作方法 |
| `spec/paths.md` | MYNET_HOME / conf / driver / var 路径铁律 |
| `mynet.md` | 客户端机制、配置文件 |
| `spec/auth.md` / `spec/wizard.md` / `spec/service.md` | 登录 / 向导 / 启停 |
| `troubleshoot-connectivity.md` | 连通性 A–E（**不在管理 skill**） |
| `node-connectivity-checklist.md` | 对等与 parity 速查 |
| `url-architecture.md` | `/d/` 分发与脚本版本化（升级地址来源） |
| `conventions.md` | 命名、产物前缀 `mynet_agent_*` |

---

## 附录 A. 机器可读摘要（agent 可直接解析）

```json
{
  "doc_id": "mynet-manage-skill-kit",
  "version": "1.0.0",
  "updated_at": "2026-10-01",
  "skill_id": "mynet-cli",
  "phases": ["goal", "check", "adjust"],
  "upgrade_urls": ["https://ctl.mynet.club/d/docs/skill-kit.md"],
  "checksum_url": "https://ctl.mynet.club/d/docs/skill-kit.md.sha256",
  "binary_index": "https://ctl.mynet.club/d/apps.json",
  "security_must": [
    "password_stdin_only",
    "no_secret_in_chat_or_repo",
    "no_regenerate_keys_on_existing_identity",
    "private_key_never_leaves_host",
    "destructive_ops_require_confirmation",
    "export_backup_before_change"
  ],
  "out_of_scope": ["connectivity_root_cause", "agent_source_patches"],
  "commands_whitelist_source": "mynet help + mynet_agent/mynet/commands.go"
}
```

## 附录 B. 变更记录

| version | 日期 | 变更 |
|---|---|---|
| 1.0.0 | 2026-10-01 | 首版：自描述、目标/检查/调整三相、安全总则、skill 生成规范、自升级协议 |
