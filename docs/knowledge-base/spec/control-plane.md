# SPEC: 控制面与传输（control-plane）

> 更新：2026-09-21
> 适用：`mynet_agent`（`mynetd` + `mynet_gateway`）、`mynet_api`、`mynet_gui` / `mynet_ui`
> 依赖：[`node-keying.md`](node-keying.md)、[`driver-abstraction.md`](driver-abstraction.md)、[`local-api.md`](local-api.md)、[`unified-platform-architecture.md`](unified-platform-architecture.md)
> 状态：**已落地**（`mynet_gateway` 已在 `mynet_agent/cmd/mynet_gateway` 实现并端到端跑通；
> 本文档为传输面契约 SSOT。§6.3 记录了各项的落地状态。）

---

## 0. 一句话

**节点面唯一入口是 `mynet-gateway`；`mynet_api` 退到内网；GUI 只跟本机 `mynetd` 说话。**

```
浏览器 ──> 127.0.0.1:8096 (mynetd httpd) ──> gateway ──> back ──> mysql
mynetd ═══════ WebSocket ═══════> gateway ── 内网 HTTP ──> back
```

---

## 1. 通道拓扑

### 1.1 三跳分工

| 跳 | 组件 | 职责 | 不做什么 |
|----|------|------|---------|
| ① 本机 | `mynetd` httpd（`local-api.md`） | 对 GUI 提供本机能力面；对需要 back 数据的查询做中继 | 不直连 back；不持有 back 凭据 |
| ② 边缘 | `mynet-gateway` | 节点鉴权、连接注册、presence、通知推送、**字节中继** | 不解析业务载荷；不存业务数据 |
| ③ 内网 | `mynet_api` | 业务权威（L1/L2/L3）：节点/路由/密钥/好友/命令队列/BundleEnvelope | 对节点不可达 |

### 1.2 为什么这样切

| 收益 | 说明 |
|------|------|
| **back 攻击面归零（对节点）** | 节点只需知道 gateway 地址；`/api/v1/monitor/*`、admin、用户 API 全部退内网 |
| **鉴权边界唯一** | 节点身份验证只在 gateway 做一处（Ed25519） |
| **暴露面是小靶子** | 对外只剩一个 Go 二进制，不是整个 Laravel（含框架 vendor 数百条路由） |
| **限流/异常检测集中** | 心跳风暴、重连惊群、异常签名都在 gateway 拦 |
| **GUI 零信任边界** | 浏览器永不接触 back；凭据与会话都在本机 mynetd 侧 |
| **gateway 可水平扩展** | 无业务状态，可多点部署就近接入（§3） |

### 1.3 关键约束

- **back 仍是唯一业务权威**。gateway 不产生业务数据，不校验 BundleEnvelope 的 checksum，不理解 generation 语义。
- **L4 语义不变**（见 `unified-platform-architecture.md` §4）：`Fetch(nodeID, etag)` 仍然成立，只是 Fetch 经 gateway 中继。ETag / checksum / last-known-good 回滚原样保留。
- **gateway 挂了 → 节点退回 `last-known-good`**，链路不断（L4 离线策略）。
- **`mynet_gnb` 不被触碰**。gateway 对的是 `mynetd`，不是 gnb 进程。

---

## 2. mynet-gateway

### 2.1 三个面

| 面 | 面向 | 接口 | 行为 |
|----|------|------|------|
| **对外** | mynetd | `WS /v1/node/connect` | **Ed25519 challenge-response** 握手（目标方案，见 [`node-keying.md`](node-keying.md) §5）；建立长连接 |
| **对内** | mynet_api | `POST /v1/notify` | 命令入队 / generation 变更时调用；向目标节点推通知 |
| **查询** | mynet_api | `GET /v1/presence/{node_id}` | 节点是否在线（连接注册表） |
| **中继** | mynetd ↔ back | WS ↔ 内网 HTTP | mynetd 发 `fetch` → gateway 转 back → 回传响应；**不解析载荷** |

### 2.2 报文

**握手（对内 gateway 侧）**

> ⚠️ **不要照搬心跳现有的 HMAC 方案**。心跳现状是 `HMAC-SHA256(公钥, METHOD|path|ts|body)`
> ——公钥被当共享秘密用，且 back 侧尚未校验，强度不足（详见 [`node-keying.md`](node-keying.md) §4）。
> gateway 作为唯一鉴权边界，采用**真非对称**方案：

```
G: -> {"type":"challenge","nonce":"<hex32>","ts":<unix>}
C: -> sig = ed25519_sign(challenge_bytes, 本节点私钥)     // GNB 格式 ed25519
   WS /v1/node/connect?node_id=<id>&ts=<unix>&nonce=<hex>&sig=<hex>
G: 校验 ed25519_verify(sig, challenge_bytes, node_keys.public_key)
S: {"ok":true,"data":{"session_id":"...","server_time":...}}
   或 {"ok":false,"error":{"code":"unauthorized|stale_ts|bad_sig|replayed",...}}
```

- 私钥永不出节点；公钥可公开存储，泄露不影响认证强度
- `ts` 偏差 > 300s → `stale_ts`（防重放）；`nonce` 在 gateway 侧短时去重（如 5 分钟窗口）
- 节点公钥来源：back 下发的 `node_keys.public_key`，gateway 启动/定期同步到内存

**推送（gateway → mynetd）**

```json
{ "type": "work_available", "node_id": "…", "generation": "…", "reason": "command_queued|generation_changed|route_changed" }
```

> **只推通知，不推业务载荷。** mynetd 收到后触发一次拉取（§2.3）。

**拉取中继（mynetd → gateway → back）**

```
C:  {"type":"fetch","req_id":"…","path":"/api/v1/monitor/node/full-config","etag":"…"}
G:  → HTTP GET {back_internal}/api/v1/monitor/node/full-config
        Headers: X-Node-Id, X-Gateway-Session, If-None-Match: {etag}
B:  → 200 + Envelope + ETag   或   304
G:  {"type":"fetch_result","req_id":"…","status":304,"headers":{...},"body":"<opaque>"}
```

**gateway 处理原则**：`body` 对 gateway 是**不透明字节**，原样转发。gateway 不解析、不修改、不缓存业务语义（可选做字节级短缓存，但**不作为正确性依赖**）。

**心跳中继**同理：mynetd 的 heartbeat POST 经 gateway 转 back，back 响应的 `commands[]` 原样回传。

### 2.3 mynetd 收到通知后的行为：**event → handler**（不是 runOnce）

❌ **错误形态**：通知 → 跑一次完整心跳（`RunOnce`）。
心跳是「采集系统指标 → 上传遥测 → 取命令 → 执行」，把它挂在通知上意味着
收到 `generation_changed` 也要上传一遍遥测——浪费且语义混乱。

✅ **正确形态**：通知携带**具体事件**，节点按事件分派到**专用 handler**，
每个 handler 只做该事件真正需要的最小动作。

| event | handler | 只做这件事 |
|-------|---------|-----------|
| `command_queued` | `ExecuteCommandsHandler` | 取待执行命令并执行（**不含遥测采集**） |
| `bundle_changed` | `ApplyBundleHandler` | Fetch+ETag → Validate → Apply → HealthCheck/Rollback（L4 流程） |
| `route_changed` | `RefreshRoutesHandler` | 重新生成 route 配置并 reload |
| `service.*` | `ServiceHandler` | 起停 / 重载对应服务 |

**心跳的职责退回原处**：周期性遥测上报。心跳**不是通知丢失的兜底** ——
back 的响应里确实带 `commands[]`（`NodeHeartbeatController.php:150`），
但 agent 的 `cmd/mynetd/heartbeat.go` 只做采集+上报、**不解析该字段**，
bundle 更不在心跳路径上拉取。

**通知丢失的自愈在 WS 连接层，不在心跳**（2026-10-01 定稿）：

| 层 | 位置 | 作用 |
|----|------|------|
| ① 写路径推送 | `BundleInvalidator` → `NotifyGatewayBundleChanged` → gw `/v1/notify` | 内容真变才推；投递侧按 `gateway.notify_attempts` 重试，耗尽记 warning |
| ② 事件处理不丢 | agent `eventDispatcher.runExclusive` | 单飞守卫期间到达的事件**按名合并、事后补跑**，不再直接丢弃 |
| ③ 连接建立补拉 | agent `transport.Handlers.OnConnect` → `catchUpBundle` | 断线窗口里 gw 推过的事件不会补发，故**每次连上（含重连）对一次 ETag**：304 什么都不做，updated 走完整落地链 |
| ④ 判活重连 | agent `WebSocketDialer` + `IdleTimeout` | pong（或任何下行帧）超 `IdleTimeout` 即主动断开 → 触发 ③；否则半死连接会让节点静默收不到任何下发 |
| ⑤ pull 侧脏检查 | back `BundleRevisionSync::isStale` | 任何一次 bundle 拉取都会校验快照是否落后业务表，落后先重建再返回 |

页面「主动刷新加载」= 前端调 publish/pull 接口，走的仍是 ①③⑤，不是新层。

**①⑤ 覆盖的业务表**：`nodes` / `node_ips` / `node_keys` / `routes` / **`node_configs`**。
`node_configs` 是 2026-10-01 补上的最后一块：envelope 的 `node.interface`、
`gnb.options.ifname`、`network.routes[].dev` 与 proxy 段全部取自它
（`BundleEnvelopePublisher::compose` → `resolveTunIfname`），而
`saveCustomConfig` / `saveFullConfig` 两条写路径原本既不 invalidate 也不被脏检查看见
—— 症状是「改接口名 HTTP 200 成功，bundle 里永远是默认接口名」。
接口名是**每节点**事实（一台主机可能多个 zone 共存；现网网关 182 用 `gnb_tun_16`，
`gnb_tun` 被另一 zone 占），所以只能由该节点自己的 `node_configs.config.ifname` 决定，
不能改默认常量。

**非法值硬失败，不回退**（2026-10-01 用户裁定：阻断性配置不许回退）。
合法范围只有一份声明：`vpn.gnb.versions.default.modes.base.parameters.ifname`
的 `default` + `validation`（`^[a-zA-Z0-9_]{1,15}$`），由 `NodeConfig::ifnameSpec()`
读取；代码里不再复制长度/字符集常量。两道门禁同源：
① 写入 —— `NodeConfig` 的 `saving` 事件（挂在模型上是因为写 config 的路径有**三条**：
`saveCustomConfig` / `saveFullConfig` / `updateConfigField`，只有这一处全覆盖），
不合法抛 `DomainException::validation` → 422，控制器 `catch (DomainException)` 走
`domainErrorResponse`，库里不留半行脏值；② 发布 —— `resolveTunIfname` 对**历史脏值**
（直接改库、门禁上线前写入的）同样抛错，该节点停在上一版 bundle。
为什么不许回退：接口名决定「流量进哪条隧道」，回退出来的默认名在这类机器上往往是
**另一个 zone 正在使用的接口**，而 `ip route ... dev X` 只要目标接口存在就执行成功 ——
错配置不报错，比没配置更危险。缺省/空值不属此列：那是「这台不覆盖」的正常状态。

**读侧不许自己拼「node_configs + 默认名」**：生效名的唯一算法是
`BundleEnvelopePublisher::tunIfnameForNode(Node)`，bundle 的 compose 与 GUI 的
`getNodeNetworkConfig` 都调它。两处历史上各自拼错过 —— 一处读 `$node->ifname`
（nodes 表没这列，恒 null → 表单永远回显空），一处写死 `'gnb_tun'` 常量；
`quickCreateNode` 也不再种子接口名，少一处常量副本。默认名的入口签名收 **Node**
而不是类型字符串：`nodes.vpn_type` 自 A-7 是 `string[]`，调用点自己
`(string)` 转一次就炸 500（staging 的 getNodeBundle 与这条链的第一版都踩过）。
回归锁：`BundleEnvelopeGnbContractTest`（写入拒绝/脏值拒发布/缺省用默认名/非字符串
值带 node_id 拒绝）+ `NodeIfnameAuthorityTest`（字段级写路径真的落库、网络配置端点
写权威、读侧与 bundle 同源、wg 节点按主驱动取默认名）。

#### 报文

```json
{ "type": "event", "node_id": "…", "event": "command_queued", "data": { "command_id": "…" } }
{ "type": "event", "node_id": "…", "event": "bundle_changed", "data": { "generation": "gen-42" } }
{ "type": "event", "node_id": "…", "event": "route_changed",  "data": {} }
```

- `event` 是**稳定标识符**（handler 注册表的键），不是自由文本
- `data` 只带定位信息（id / generation），**不带业务载荷**——真实内容仍由节点按
  Fetch+ETag 从 back 拉取
- 节点侧维护 `map[event]Handler`；**未注册的 event 记 warning 并忽略**，
  不阻断其他事件（向前兼容：back 加了新事件，旧节点不会崩）

#### 并发与幂等

- 同一节点的 handler 串行执行（或按事件类型串行），避免 `ApplyBundle` 并发跑两份
- 执行期间再来通知 → 记 pending，当前动作结束后**合并成一次**再跑（去重）
- 所有 handler 必须**幂等**：重复触发只是重复一次拉取，不产生副作用差异

### 2.4 容量目标（2G 内存节点）

| 项 | 预算 |
|----|------|
| OS + 系统守护 | ~300 MB |
| gateway 基础（Go runtime + 应用） | ~30 MB |
| **可用** | **~1.7 GB** |
| 单连接（goroutine 栈 4–8KB + 状态 ~2KB + 读写缓冲 8–16KB + conn 开销） | **~20–40 KB** |

- 1.7GB / 40KB ≈ **4.2 万连接**（悲观）；/20KB ≈ **8.5 万**（乐观）
- **「几千个 client」仅占 100–200 MB**，2G 机器余量极大
- 吞吐：5000 节点 × 10KB 心跳 / 300s ≈ **167 KB/s**，可忽略
- 真正瓶颈更可能是 **fd 上限**（`ulimit -n ≥ 65535`）与心跳风暴时的 CPU

**硬指标**：push p99 < 100ms；1 万连接 RSS < 400MB；重连用带抖动的指数退避（防惊群）。

### 2.5 gateway 明确不做的事

- 不解析 BundleEnvelope / 不校验 checksum / 不理解 generation
- 不存业务数据（连接注册表 + presence 只在内存，可随时重建）
- 不做业务授权决策（谁能否问节点 —— 由 back 判）
- 不直连 gnb 进程

---

## 3. 多 gateway 部署

### 3.1 目标

**让大家尽快能接入**：多个 gateway 节点就近接入，降低时延与单点封锁风险。

### 3.2 拓扑

```
                    ┌── gateway-A (公网, 区域1) ──┐
mynetd ──选择──>    ├── gateway-B (公网, 区域2) ──┼──> back（内网，唯一）
                    └── gateway-C (overlay, 见§4) ┘
```

- 多个 gateway **对等**，无主从；各自独立持连接，**不互相同步连接状态**
- presence 查询：back 可向**任一** gateway 查询；或向**全部**并集（节点可能连在任一个上）
- 通知下发：back 需知道节点连在哪个 gateway → 见 §3.3

### 3.3 节点↔gateway 归属

gateway 在握手成功后回调 back 登记：`POST {back}/internal/gateway/bindings` `{node_id, gateway_id, connected_at}`；断开时注销。

back 的 `POST /v1/notify` 因此可以**定向投递**到节点所在的 gateway；若登记缺失，则广播到全部 gateway（gateway 发现本地无此连接则忽略）。

> 该登记是**路由提示**，不是业务状态。丢失时退化为广播，不影响正确性。

### 3.4 发现与选择

mynetd 配置持 gateway 列表（有序）：

```
gateways:
  - wss://gw1.mynet.club/v1/node/connect
  - wss://gw2.mynet.club/v1/node/connect
  - wss://10.1.0.1/v1/node/connect        # overlay 地址，见 §4
```

选择策略：
1. 按序探测（握手超时如 5s），取第一个成功的
2. 连接成功后**粘滞**，断开才换下一个
3. 重连时从上次成功的下一个开始（避免每次都打同一台）
4. 全部失败 → 退回心跳轮询直连 back（过渡期）或保持 last-known-good（终态）

列表来源：安装时由 `mynet_ctl` 下发；后续可经已连 gateway 热更新。

---

## 4. 通道韧性：借 node 访问 gateway

### 4.1 场景

客户端（mynetd / GUI 用户）**公网路径被封，直连 gateway 不可达**；但某个 MyNet 节点活着且已连上 gateway。此时**借该节点的隧道访问 gateway**。

### 4.2 机制：gateway 双宿

gateway 同时持有两个入口：

| 入口 | 地址示例 | 服务对象 |
|------|---------|---------|
| **公网** | `wss://gw1.mynet.club/...` | 未被封的客户端，就近接入 |
| **overlay** | `wss://10.1.0.1/...`（gateway 在 MyNet 网段内的地址） | 已在 overlay 内的节点/客户端 |

gateway 在 overlay 内以一个普通节点身份存在（有 tun IP、在 `route.conf`/`address.conf` 中可见），因此：

- **已连上的节点**可经隧道直达 gateway 的 overlay 入口
- **被封但已在 overlay 内的客户端**（例如在某节点 LAN 下、或自身经某节点接入）同样可达
- 被封且完全在 overlay 之外的客户端，需先借任一可达节点进入 overlay，再走 overlay 入口

### 4.3 客户端行为

mynetd 的 gateway 列表把 overlay 地址排在公网地址之后：

```
1. 公网 gw1 / gw2 ...     ← 正常路径，优先
2. overlay 10.1.0.1       ← 公网全不可达时的兜底
```

全部公网失败时：
- 若本机已有可用隧道（自己是节点且 gnb/wg 在跑）→ 试 overlay 入口
- 若本机无隧道但 LAN 内有活着的 MyNet 节点 → 走该节点网关（§4.2 的「借 node」），再试 overlay 入口
- 仍失败 → 保持 last-known-good，按 §3.4 退化策略处理

### 4.4 约束

- overlay 入口依赖**至少一个已建立的隧道**——它是韧性兜底，不是冷启动路径
- 冷启动仍需至少一个**公网可达**的 gateway（或多点部署 + 就近选择，§3）
- gateway 的 overlay 地址应**稳定**（固定 tun IP），避免随拓扑漂移导致兜底失效

---

## 5. mynet_gui 的接入模型

### 5.1 原则

**GUI 只跟本机 mynetd 说话**（`local-api.md`）。浏览器不持有 back 凭据，不直连 back/gateway。

```
浏览器 ──> 127.0.0.1:8096/api/v1/... ──> mynetd
```

### 5.2 两类查询

| 类型 | 数据来源 | 处理方 |
|------|---------|--------|
| **本机态** | gnb/wg 进程、tun、本机配置、proxy 开关、日志 | mynetd 直接答（`local-api.md` 现有能力面） |
| **平台态** | back 上的节点列表、路由、好友、统计 | mynetd 经 gateway 中继到 back，再回给 GUI |

### 5.3 中继语义

`local-api.md` 需扩一个 **`relay` 域**（本文档定义，实现归属 mynetd）：

```
GET /api/v1/relay?path=<back-api-path>
  → mynetd 经 WS 向 gateway 发 fetch
  → gateway 内网 HTTP 转 back
  → 响应原样回给 GUI（保持 back 的信封或转成本机信封，见下）
```

约定：
- **鉴权**：GUI → mynetd 用本机 local token（`local-api.md` §1）；mynetd → gateway 用节点 Ed25519 身份。GUI 永不接触后两者。
- **路径白名单**：mynetd 只放行 back 的**用户层只读/业务接口**（`api/v1/nodes|route|social|...`），**禁止** relay 到 `admin/*` 与任何身份生命周期接口。
- **信封**：建议 mynetd 把 back 响应包进本机信封 `{ok, data, error}`，GUI 只面对一种格式。

> 这条的意义：**GUI 的信任边界止于本机**。平台态数据只是「本机代理去取回来的东西」。

---

## 6. 心跳链路

### 6.1 现状（拉取式）

| 环节 | 现状 |
|------|------|
| 契约 | `mynet_agent/docs/HEARTBEAT_API_SPEC.md`（stats / `vpn_interfaces[]` / `node_monitor{node_self,peers_status}`） |
| agent | `daemon.go` 每 300s 采集 → POST 心跳，响应取 `commands[]`，执行后 `PATCH result` |
| back | `HeartbeatRequest` 校验 → `NodeStatusService::recordHeartbeat` + `NodeMonitorService::processMonitorData` → 取 `monitor_rt_cmds` → 响应 |
| 命令来源 | `NodeConfigService` 在配置变更时创建 `node_refresh` 等命令 ✅ |

### 6.2 缺口（含落地状态）

| # | 缺口 | 影响 | 状态 |
|---|------|------|------|
| 1 | **鉴权是空壳**：`NodeHeartbeatAuthMiddleware` 只有 `// TODO: Implement Ed25519 signature verification`，直接放行 | 节点通道无认证。⚠️ agent 实际签的是 **HMAC-SHA256**（密钥 = GNB 派生的公钥），**不是** ed25519 签名——见 [`node-keying.md`](node-keying.md) §4 | ✅ 已按现状方案实现（过渡）；gateway 上线后退役 |
| 1b | 节点面 monitor 路由被模块级 `auth:sanctum` 覆盖，节点无 token → **任何 monitor 调用都在到达验签中间件之前 401** | 节点通道整体不可用（含心跳） | ✅ 已用 `withoutMiddleware('auth:sanctum')` 只摘该组 |
| 1c | **请求体 gzip 从未被解压**（全仓无 `gzdecode`），而真实心跳必然 >1KB 故必然 gzip | 心跳在表单验证阶段就 422 | ✅ 已加 `InflateNodeRequestBody`，排在验签之前 |
| 1d | 节点通道没有租户上下文，而 `TenantScope` 是 **fail-closed**（无租户 → `WHERE 1=0`） | `Node::where('node_id',…)` 查不到 → **状态永远同步不进去**，node_monitor 处理全空转 | ✅ 验签后按**节点自身租户**建立请求上下文 |
| 2 | **响应性联动断了**：事件系统删除后，节点上线不再触发对端 `route.refresh` | 拓扑变化不能自愈 | ✅ 已补回（见 §6.3 第 2 步） |
| 3 | 命令时延最坏 5 分钟 | 配置变更/紧急撤销不及时 | ✅ gateway 通知路径已通，实测秒级 |

### 6.3 迁移路径

1. ✅ **补心跳验签**（不依赖 gateway）：`NodeHeartbeatAuthMiddleware` 按**现状方案**实现——`HMAC-SHA256(base64_decode(node_keys.public_key), METHOD|path|timestamp|body)` → base64，与 `X-Node-Signature` 比对（格式见 [`plugins.md`](plugins.md)），另加 300s 时间戳窗口（签名里带 ts 却不设窗口 = 签名永不过期）。
   两处必须与 Go 对齐：签名体是「先置 `node_monitor=nil` 再 Marshal」的那份，重编码需 `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`；其余端点直接用**原始字节**（命令回报的 `result` 含 `<>&`，Go 会转义而 PHP 不会）。⚠️ 方案强度有限（公钥被当共享秘密用），只作为**堵洞的过渡**，不值得强化
2. ✅ **补响应性联动**：`NodeMonitorService::processMonitorData` 检测状态跃迁（`nodes.node_status` 真的变了）→ `RelatedNodeRouteRefresher` 为对端（`routes.target_node_id` 指向本节点、且 `node_status >= 启用` 的那些 `node_id`）入队 `mynet.route.update`。
   语义沿用被删除事件系统里的 `RefreshRelatedNodeRoutesHandler`。**入队放在 `DB::commit()` 之后**：命令入队会经网关立刻推事件，而节点是另一个进程、看不到未提交的行
3. ✅ **gateway 上线**：节点已建立到 gateway 的 WS 长连接、Ed25519 challenge-response 握手、事件推送、经中继回源都已落地并**真环境端到端跑通**；心跳也经中继（`mynetd.json` 的 `relay_heartbeat`，**默认开**；设 false 可退回直连，供第 4 步之前的过渡）。节点面路由的限流也相应改为**按已验签节点**计（原 `throttle:api` 按 IP 60/分，经中继后所有节点会挤网关那一个 IP，约 300 节点即打满）
4. ⬜ **back 节点接口收内网**：确认无直连流量后关闭公网暴露。
   ⚠️ 这是**部署侧动作**，需要现场观测确认；代码侧已具备条件（心跳/取命令/回报三条路径都可经中继）
   ⚠️ 另需注意：节点通道已不再有按 IP 的兜底限流（见上），未验签的洪泛须由边缘（网关 / nginx）承担

> 步骤 1、2 与 gateway **无依赖**，可先行；3、4 是 gateway 落地后的收敛。
> HMAC 方案只在 1→3 的窗口期存在，gateway 上线后自然退役。

> 补充（落地时发现的既有缺陷，不属本节缺口但会掩盖它们）：
> `Monitor/Services/NodeMonitorDomainService` 是个 8 行空壳，真实现同名地放在
> `Monitor/Domain/Services/` 下，两个消费者在同一个命名空间里不带限定名引用它
> → 注入的是空壳，带 `node_monitor` 的心跳必 500。
> `node_monitor_snapshots` 上 `UNIQUE(node_id, snapshot_at)` 而 `snapshot_at` 精度是秒，
> 原实现用 `create()`：同一秒的第二次心跳撞唯一键，把整个事务连**状态同步**一起回滚。

---

## 7. mynet_proxy 下架

### 7.1 结论

**坚决下架。** `mynet_proxy` 是节点侧手写配置的 CLI，其最重的职责已被 GNB 原生 `p2-route-proxy`（`node.conf` 的 `proxy on`）取代——未登记目的地兜底给**指定出口节点**（下一跳，等同 route.conf 加 `出口|目的|mask`），`route.conf` 不再需要灌海量路由。

### 7.2 能力归宿（不是「换地方调 gnb」，而是「业务意图 → envelope → driver 各自落地」）

| mynet_proxy 的能力 | 归宿 | 说明 |
|-------------------|------|------|
| 未登记目的地走代理 | **L2 TransportPolicy → L3 BundleEnvelope → L5 Driver** | gnb：`proxy on` + **指定出口节点（下一跳）**；wg：`AllowedIPs`（双向 cryptokey routing）。两者语义不同构，**不可压成扁平结构**（见 `driver-abstraction.md` §3） |
| OS 策略路由（`proxy_route.conf` / `route_policy.sh`） | **L6 NetworkBackend** | 驱动无关：`ip route` / pf 对 gnb0 与 wg0 一视同仁 |
| IP 段列表 / 地域分流（chinaip/interip、NODE_REGION） | **L2 TransportPolicy 的输入** | 谁走谁是业务决策，应由 back 算好随 envelope 下发，不在节点上现算 |
| `proxy_role.conf` / `proxy_peers.conf` 等节点本地状态 | **消亡** | 角色与拓扑是 back 的 L1/L2 数据 |

> 用户原话的机制澄清：**不是「mynet_proxy 去和 driver 抽象层通信」，而是「proxy 是业务意图，由 driver 各自解释落地」**。底层可能是 gnb 也可能是 wg —— 所以意图在上、落地在 driver，中间经 envelope 传递。

### 7.3 节奏

1. 标记 deprecated，冻结新功能
2. 待 L3 BundleEnvelope 与 L5 双 driver 落地
3. 节点侧策略路由改由 `NetworkBackend.Apply` 接管
4. 从 `scripts/proxy/*`、`conf/command.*.json`、安装链路整体摘除

不为它单做一次迁移。旧线 `spec/proxy.md`（mynet_lua / mynet_client）已随该线冻结。

---

## 8. 与既有 spec 的关系

| 文档 | 关系 |
|------|------|
| [`local-api.md`](local-api.md) | 本机能力面 SSOT。本文档 §5 定义其 `relay` 域与「GUI 只谈本机」模型 |
| [`driver-abstraction.md`](driver-abstraction.md) | L5/L6 边界与 driver-specific intent。本文档 §7 的 proxy 能力归宿依此 |
| [`unified-platform-architecture.md`](unified-platform-architecture.md) | L1~L7 分层与实施计划。本文档补充**传输/控制面**（gateway）一层，不改变 L1~L7 的业务分层 |
| [`proxy.md`](proxy.md) | 旧线（mynet_lua/mynet_client）代理分流操作契约，**已冻结**；新线见本文档 §7 |
| [`data-models.md`](data-models.md) | JSON/conf 格式唯一定义处。gateway 报文若被多端消费，应上收至此 |

---

## 9. 实现归属

| 组件 | 仓 | 说明 |
|------|----|------|
| `mynet_gateway` | `mynet_agent`（`cmd/mynet_gateway`） | 同 Go module，可复用 `types` 与签名工具；将来拆独立仓随时可拆 |
| gateway 集成（notify 调用） | `mynet_api` | 命令入队 / generation 变更处调用；失败静默降级 |
| 长连接协程 | `mynet_agent`（`mynetd`） | 收到通知触发 `RunOnce`；fetch 中继 |
| GUI 接入 | `mynet_gui` / `mynet_ui` | 只调 `127.0.0.1:<port>/api/v1`，业务零持有 |
| 部署/分发 | `mynet_ctl` | gateway 列表下发、安装时配置 |

---

## 10. 待决

- [ ] gateway 列表的签发与热更新机制（谁有权改、如何防篡改）
- [ ] presence 归属查询在多 gateway 下的一致性策略（广播 vs 定向，§3.3）
- [ ] overlay 入口的地址规划（gateway 在 MyNet 网段内的固定 IP 段）
- [ ] gateway challenge 参数（nonce 长度、ts 窗口、重放缓存 TTL）——见 [`node-keying.md`](node-keying.md) §8
- [ ] 控制面身份密钥是否独立于驱动密钥（建议独立，避免 wg/gnb 混用）——见 [`node-keying.md`](node-keying.md) §6
- [ ] GUI `relay` 域的路径白名单具体清单


## apps_index_url（E-9 SSOT）

分发索引统一 **`https://ctl.mynet.club`**（不是 `download.mynet.club`）。

- Go 侧 `updater` 与 OpenWrt 侧取同一基址
- `download.mynet.club` 兼容层保留给旧客户端，新链路一律 `ctl.mynet.club`
