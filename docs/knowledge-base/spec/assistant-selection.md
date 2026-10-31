# SPEC: 小助手接入架构与 provider 选型（BYOK 桌面直连）

> **SSOT 位置**：`mynet_ctl/docs/knowledge-base/spec/`
> **适用**：`mynet_gui`（桌面/embed 面小助手 UI 与 provider 直连）· `mynet_agent`（工具面 localapi）。
> **mynet_api 不加 AI 面**（架构裁定，见 §1）。
> **关联**：[tips-kb-contract.md](tips-kb-contract.md)（小助手知识源，已闭环）· [local-api.md](local-api.md)（工具面能力表）
> **状态**：2026-10-31 口径成文。选型基线为**当次探测**证据（§2.3），非静态事实。

---

## 0. 范围与非目标

**定义**：桌面端小助手的接入架构口径（§1）、provider 选型矩阵与准入/排序依据（§2）、
工具面 v1（§3）、本机可达性复测方法（§4）。

**非目标**：
- **web 面小助手**（`my.mynet.club`）——另立议题，不混进本批；
- **云端 AI/BFF**——架构裁定不开（§1），不是待选项；
- **写操作工具**——v1 全只读（§3 冻结口径）；
- 对话协议、UI 交互、提示词工程——另行 spec。

---

## 1. 架构口径（用户已裁定，不复议）

1. **AI 留在桌面端**：小助手 UI 在 `mynet_gui`，推理请求由**前端 JS 直连 provider**；
2. **BYOK（用户自带 token）**：用户自己填 provider token；token **只存本机**
   （不进服务器、不进仓库、不进日志；存储载体见 §5 O4）；
3. **无云端 BFF**：`mynet_api` **不加 AI 面**，桌面端不出网到自家后端做推理；
4. **工具调用只走本机 localapi**（127.0.0.1:8096）：小助手的诊断/查询能力全部经
   `mynetd` localapi（§3），「用户面只打本机」口径不破；
5. **知识源**：tips-kb（`kbChunkIds` 深链 + 检索注入，见 tips-kb-contract.md §5）。

```text
┌────────────────────────── 桌面端 ──────────────────────────┐
│  mynet_gui 小助手 UI                                       │
│    │ HTTPS + 浏览器直连（BYOK token，纯 JS）                 │
│    ├──▶ provider API（DeepSeek / Anthropic / …）            │
│    │                                                        │
│    └──▶ 127.0.0.1:8096 localapi（只读工具面，§3）            │
│              └── mynetd → 执行器（mynet check 等）           │
└────────────────────────────────────────────────────────────┘
mynet_api：不参与（无 AI 面）；tips-kb 云端透出与本议题无关，已闭环
```

---

## 2. provider 选型矩阵

### 2.1 准入项（一票否决）

| 准入 | 判据 |
|---|---|
| **浏览器直连可行** | CORS 预检（OPTIONS）必须过，且请求头方案在浏览器可设。Anthropic 必须带 `anthropic-dangerous-direct-browser-access: true`（官方文档口径） |
| **BYOK 可行** | 用户自带 key 直接调用，不要求服务端签名代理 |
| HTTPS API | 纯 HTTPS JSON API，可流式（SSE/流式响应为加分项） |

### 2.2 排序依据

1. **本机可达性（首选）**——随网络环境与**加速开关**漂移，任何可达性结论必须带
   探测时间 + 网络态（§4）；不可达 = 直接排后，不进默认位；
2. **默认 provider = DeepSeek**（2026-10-31 实测最快，见 §2.3）；
3. 其余按延迟、价格、模型能力排序（本 spec 不展开模型评测）。

### 2.3 基线证据（2026-10-31 探测 · 网络态：本机加速**关**）

| endpoint | 结果 | 计时 | 判读 |
|---|---|---|---|
| `api.deepseek.com` | 可达 | **401 @ 0.39s** | 端点活（401=未带鉴权，正常） |
| `api.anthropic.com` | 可达 | 403 @ 2.8s | 端点活；浏览器直连需 §2.1 直连头 |
| `api.openai.com` | **超时** | — | 推断与加速关有关，**开加速后必须复测**（待验证） |
| `github.com` | **超时** | — | 同上（对照项，非 provider） |

> ⚠️ 本表是**探测时刻快照**，不是静态事实。换网络态一律按 §4 重测并更新本表（保留旧快照注明被替代）。

### 2.4 各 provider 直连要点

| provider | API 形状 | 浏览器直连 | 可达性现状 |
|---|---|---|---|
| **DeepSeek（默认）** | OpenAI 兼容 `POST /chat/completions`（base `https://api.deepseek.com`） | CORS **待逐项预检**（§4.2；推断可过，未验） | 2026-10-31 可达（§2.3） |
| **Anthropic** | `POST https://api.anthropic.com/v1/messages` | 需 `anthropic-dangerous-direct-browser-access: true` 头；CORS 官方支持 | 2026-10-31 可达（§2.3） |
| **OpenAI（备选）** | `POST https://api.openai.com/v1/chat/completions` | CORS **待逐项预检**；BYOK 下 key 在浏览器，泄露面由用户自担 | 2026-10-31 **超时**（加速关；待复测） |

---

## 3. 工具面 v1（全只读；工具调用只走本机 localapi）

| 工具（小助手侧命名） | localapi 入口 | 用途 | 证据 |
|---|---|---|---|
| `config_identity` | `GET /api/v1/config/identity` | 本机身份/节点归属 | [local-api.md](local-api.md) §3 |
| `config_tenants` | `GET /api/v1/config/tenants` | 可选网络区列表 | 同上 |
| `acceleration_status` | `GET /api/v1/acceleration/status` | 加速状态 + **出口探测**（mynetd 15s 循环的 ExitProbe；缺内存态时回退解析 stdout） | `mynet_agent/mynetd/localapi/server.go:64-65` |
| `node_status` | `GET /api/v1/node-status` | 节点六态机 + 明细（按钮权威在此） | `mynet_agent/mynetd/localapi/node_status.go:36-68` |
| `logs` | `GET /api/v1/logs?lines=N` | 本机事件环 tail（逐行脱敏） | `mynet_agent/mynetd/localapi/logs.go:21-58` |
| `run_check` | `POST /api/v1/command`，`argv:["check",…]` | **排障主力**：`mynet check` 机测报告（子命令白名单含 `check`） | `mynet_agent/mynet/commands.go:147` + `mynetd/localapi/handlers.go:106-118` |

**冻结口径**：v1 **只读**。写操作（apply/service start|stop/config 写侧/加速开关等）不开给小助手；
将来若开，必须走**显式确认**（UI 二次确认 + 用户可见的将执行命令），且另行修订本 spec。

---

## 4. 可达性复测方法（换网络态必重测）

### 4.1 探测（可达性 + 延迟）

对每个候选 provider 的存活端点做无鉴权 GET，**只看连通与耗时，不看状态码**
（401/403 都算「端点活」）：

```bash
curl -o /dev/null -s -m 8 -w '%{http_code} %{time_total}\n' \
  https://api.deepseek.com/v1/models
# 形如：401 0.39 —— 可达；空/超时 = 不可达
```

**必记四项**：探测时间（UTC）· 网络态（加速开/关 + 网络环境）· 逐 provider 结果（码+耗时/超时）·
当时的默认 provider 是否仍成立。

### 4.2 CORS 预检（准入项）

用**真实部署源**（embed 面 = `http://127.0.0.1:8096`）做 OPTIONS 预检；
Anthropic 记得带直连头：

```bash
curl -i -m 8 -X OPTIONS https://api.deepseek.com/v1/chat/completions \
  -H 'Origin: http://127.0.0.1:8096' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type,authorization'
# 看 Access-Control-Allow-* 是否放行；Anthropic 另测 anthropic-dangerous-direct-browser-access
```

### 4.3 何时重测

| 触发 | 动作 |
|---|---|
| 换网络环境 | §4.1 全量重测，更新 §2.3（旧快照注明被替代） |
| 切换加速开关 | 至少重测「超时」过的 provider（OpenAI/GitHub 线） |
| 新增/更换 provider | §4.1 + §4.2 都测，过准入才进矩阵 |
| 默认 provider 异常 | 重测并按 §2.2 重排默认位 |

---

## 5. 开放问题

| # | 问题 | 倾向 |
|---|---|---|
| O1 | web 面（`my.mynet.club`）要不要小助手 | **另立议题**，不混进本批（架构 §1-3） |
| O2 | 写操作工具与确认流 | v1 冻结只读；将来开须显式确认并修订本 spec |
| O3 | DeepSeek / OpenAI 的 CORS 逐项预检记录 | 按 §4.2 实测后回填 §2.4（现为待验） |
| O4 | BYOK token 的本机存储载体（浏览器存储 vs 系统钥匙串） | v1 倾向浏览器存储（仅本机、可清除）；定案后回填 §1-2 |
