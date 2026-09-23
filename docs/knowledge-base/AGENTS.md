# MyNet 开发 — Agent 规则

> 适用范围：`mynet-club/` workspace 及其下 8 个 repo 的**全部实现工作**。
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
| `docs/dev-plan.md` | **建完做什么** —— 含 **64 项计划顺序表**（唯一跟踪入口） | 同上 |

**任务 ID 以 `docs/dev-plan.md` §3 计划顺序表为唯一标识。**
推进方式：按序号勾掉「状态」列；「前置」列告诉你什么时候能动它。
两个硬门槛：**M0**（测试基绿）· **M1**（双驱动验证通过）。

---

## 1. 硬约束（不可违反）

### 1.1 不破坏驱动的协议与目录结构

gnb / wireguard 各自的协议与目录结构**不能也不打算破坏**。
- gnb 的配置只写进 `driver/gnb/conf/<nodeId>/`；`gnb.map` 由 gnb 自己产出，**只读不搓**。
- gnb 源码的改动走 **`git format-patch` 补丁包**（`mynet_gnb/packages/`），`src/` 保持上游原样，升级用 `rebase.sh`。
- gnb 落盘的私钥是 `clamp(SHA512(seed))`（128 hex），公钥 64 hex——**这是驱动的事实，只能遵循**。

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

`relaypolicy` 是**纯路径白名单**，鉴权与业务校验**一律归 back**。
连接层的 Ed25519/X25519 是**寻址与 presence**（门牌号），不是授权。

⚠️ `IsAllowedWith` **先查 `deniedFragments` 再查 `AllowedPathPrefixes`**——
改白名单必须**两个动作同时做**，只加 prefix 不删 fragment 是无效的。
含 `%` 的路径一律拒（防 `%2f` 绕过），不要「优化」掉这条。

### 1.4 三层三套凭证

| 层 | 谁 ↔ 谁 | 认证 | 凭证 |
|---|---|---|---|
| **数据面** | 节点 ↔ 节点 | gnb / wg 自己的加密 | **驱动密钥**（gnb 非标准 ed25519 / wg X25519） |
| **节点面** | 节点 ↔ back | X25519 DH + HMAC | **复用驱动密钥**（同一套） |
| **用户面** | 用户/桌面 ↔ back | **cookie / 登录会话** | 无密钥 |

- 人用会话（cookie），机器用密钥。
- **gateway 不鉴权**——纯透传 + 连接状态表（像 TCP 握手），鉴权归 back。
- **X25519 签不了名**——所以节点面走 **DH 不走签名**，这样一套驱动密钥通吃 gnb/wg。
- **编码（hex）与布局（`seed‖pub` vs `SHA512`）是正交的两维**——别因为「统一 hex」就以为布局也要跟 gnb。
  `node_keys` 存 hex；写 wg 配置时转 base64 44 字符（wg 原生格式）。

### 1.5 命名与端口

- 产物前缀 **`mynet_agent_*`**（不是 `agent_*`）。
- 本机 API / WebUI 端口 **8096**（8192 被 IANA 的 `spytechphone` 与 `mynet_utc` 双重占用）。
- gateway 默认 8710；back 内网地址是**部署配置**，不下发。

### 1.6 mynet_back 按需 module

**31 个框架包只留 3~4 个**（主框架 / `infrastructure` / `auth` / 待定 `logging`）。
27 个零引用模块**全砍**（ai / billing / workflow / lottery / voting / coupon / payment /
operator / platform / plugin / ticket / sms / form / knowledge / conversation …）。

⚠️ 连带要删的残留：`app/Services/ToolRegistry.php`、`tests/Stubs/FakeToolRegistry.php`、
`tests/ActionNodeTest.php`、`tests/Conversation/`——它们属被砍模块。

### 1.7 整理式重建，不是从零重写

**新 repo + 新结构，从旧代码「搬运正确部分」**。旧代码里有踩坑得来的隐性知识，
从零重写会丢。搬运清单见 `repo-migration.md` §4；隐性知识清单见其 §0。
**不搬**：死代码、重复实现、bug 产物（如 `driver/gnb/conf/*/ed25519/%!d(string=...).pub`）。

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
| 入口 | `make check` | guard + lint + test | 随时可跑 |
| 闸 1 | `.githooks/pre-commit` → `make guard` | **轻量**：安全红线 + 文件名（秒级） | 每次提交 |
| 闸 2 | `.githooks/pre-push` → `make check` | **重量**：lint + 全量测试 | 每次推送 |

- **`make guard` 三类红线**（见 `templates/.githooks/guard.sh`，已功能实测 7 用例）：

  | 类 | 红线 | 为什么是红线 |
  |---|---|---|
  | **A 安全** | A1 硬编码凭据 · A2 私钥泄露 · A3 敏感文件名（`.env`/`*.pem`/`*.key`/`id_rsa*`/`*.private`） | 事后清 git 历史代价大 |
  | **B 结构** | **B1 `mynet_gnb/src/` 只读** · **B2 mynetd 不得依赖系统能力** · **B3 relaypolicy 不得含鉴权** · **B4 业务对象不得含驱动术语** | 架构约束违反后会**静默扩散**，事后重构成本高 |
  | **C 产物** | C1 格式化 bug 产物文件名 · C2 端口漂移（≠8096）· C3 命名漂移（≠`mynet_agent_*`） | 已发生过，静态可查 |

  B 类是**最重要的那一类**——「整理式重建」时新架构约束最容易在实现时被忘掉。
- **精确不激进**：误报过多的守护会被 `--no-verify` 绕过，反而失效。正则取窄，宁可漏报。
- **显式豁免**：在**违规那一行**写 `GUARD-OK <原因>`，该行即豁免；每次豁免记入 `.git/guard-exempt.log` 便于审计。
- 模板在 `knowledge-base/templates/`，各 repo 复制一份（`Makefile` + `.githooks/`），`make install-hooks` 装 hook。
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
| **`%!d(string=...)` 这类格式化 bug 产物不进代码库** | `driver/gnb/conf/4147069894021819/ed25519/` 下现有 5 个，搬运时**不搬** |

---

## 4. 坑（开发前必读）

完整 13 条见 `docs/mynet-overview.md` 第九节。**最容易踩的 6 条**：

| 坑 | 后果 |
|---|---|
| **公钥编码三种假设** | `node_keys.public_key` 上中间件要 base64、docblock 写 hex、bundle 又转 hex。**不一致表现为「心跳鉴权失败」而非编码错误，极难定位** |
| **GNB 非标准 ed25519（三层）** | 签名是标准 RFC 8032；私钥布局是 `clamp(SHA512(seed))` 非 `seed‖pub`；数据面另做 `ed25519_key_exchange`。**拿 stdlib 的 `seed‖pub` 去算会签错——R 对 S 错，极隐蔽** |
| **GNB 非标准掩码静默忽略** | 只认 /32（末位非 0）/24 /16 /8 /0。`/27` `/22` 等**不报错、不生效、不警告** |
| **GNB 数据面默认 XOR 无 MAC** | `--crypto` 默认 `xor`，64 字节固定周期重复密钥流；`verifycode` 不依赖密钥，**不是完整性保护** |
| **gnb_es 子进程不会自动退出** | Lua 现在靠 PPID 匹配清理——说明 gnb 崩溃时会留孤儿。procd 接管前必须验证 |
| **`_gnb_peer_heal` 的判据依赖已下架的 `mynet_proxy`** | 判据是 `proxy_state.json` + `PROXY_GATEWAYS`。**先改造判据**（改用 `gnb_ctl -s` 的 PONG / ping 对端 VPN IP）再搬 |

---

## 5. 项目地图

| repo | 角色 | 决策 |
|---|---|---|
| `mynet_agent` | Go — mynetd（纯通信）+ mynet（执行器）+ mynet_gateway + mynet-updater | **新建 + 整理式搬运** |
| `mynet_web` | Vue3+Quasar — 一份 WebUI（本机域 + 社区域） | **新建**（合并 `mynet_ui` + `mynet_gui`，Quasar 基线） |
| `mynet_desktop` | 非 OpenWrt 平台适配 | **新建** |
| `mynet_back` | Laravel — 业务权威 | **新建 + 整理式搬运**，**按需 module（31→3~4）** |
| `mynet_openwrt` | LuCI 壳 + ubus 薄桥 + ipk | **改造** |
| `mynet_ctl` | 分发中心 + **知识库中枢**（`docs/knowledge-base/`） | **挂载，不动** |
| `mynet_gnb` | opengnb fork + 补丁包 | **挂载，不动** ⚠️ git 历史必须保留（rebase 上游） |
| `mynet_utc` | UDP→TCP 隧道 | **挂载，不动** |

**mynetctl 的 `docs/knowledge-base/` 是文档中枢**——查规范先去那里：
`spec/unified-platform-architecture.md`（L1–L7 权威）、`spec/control-plane.md`、
`spec/driver-abstraction.md`、`spec/node-keying.md`、`spec/local-api.md`、`spec/proxy.md`。

---

## 6. 开工提示词（新会话用）

```
读 mynet-club/AGENTS.md 与 docs/ 下三份文档（mynet-overview / repo-migration / dev-plan），
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
