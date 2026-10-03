# 节点状态门（node_status）— 决策包（**已裁决**）

> **2026-10-04 用户裁决：议题 1 = A（GET 改只读，已执行）；议题 2 = A（心跳保持无条件写 1，
> 不动代码），C 记为 v1 之后的方向。** 下面保留裁决前的取证与候选方案原文，
> 落地情况见「## 裁决与执行」。

起因：2026-10-01 现网 .184 被一次「只改描述」的 PUT 打成 `node_status = -1`，随后从所有对端的
bundle 里消失，且因为它不向 API 心跳，永久卡死。模型侧判据（把可选的 `node_configs` 当必备项）
已在 `mynet_api` `5e4936a` 修掉；本轮又修掉了服务侧判据与基本信息 PUT 两处。剩下的是**语义裁决**，
不是接线便利，所以不在这里自行拍板。

---

## 裁决与执行

| 议题 | 裁决 | 落地 |
|---|---|---|
| 1 `GET /nodes/{id}/status` 该不该写 | **A 改只读** | 已改：`NodeController::getStatus()` 的第一步从 `checkAndUpdateNodeStatus()` 换成 `describeNodeStatus()`。响应形状不变，`status_updated`/`status_changed` 恒 `false`，新增 `status_evaluated: false`。 |
| 2 心跳无条件写 1 | **A 保持现状** | 未改代码（按裁定）。C（`node_status` 与 `presence` 拆字段）记为 v1 之后方向，属数据模型变更，须走 `MYNET_V1_PLAYBOOK.md` §11 合同冻结流程。 |

**裁决前那个 ⚠️「GUI 是否依赖这个 GET 来纠正显示状态」已取证，答案是不依赖：**
`mynet_gui` 里唯一的调用点是 `src/services/node/nodeService.ts:287 getNodeStatusInfo()`，
调用者只有 `src/pages/node/NodeListPage.vue:185`，而它把响应存进 `nodeStatusInfo`（:186）之后
**全文件再没读过这个变量**（只有 :170 声明、:179 去重守卫、:186 写入三处）。
所以：① A 不会影响任何 GUI 显示；② 顺带发现这个请求目前是**纯浪费**——
`checkAndLoadUnconfiguredNodes()`（:198-207）对每个「未配置」节点并发打一次 GET，结果丢掉。
而且**两个方向都白跑**：触发条件 `isUnconfiguredNode()`（:284）只对 `node_status === -1`
为真（`src/utils/nodeStatus.ts:198-206`），所以它只打在**已经是 -1** 的节点上——
既不是降级的原因，也救不回来（旧的写副作用走 `updateStatusBasedOnConfig(null)`，
该分支只能降级不能升级，对 -1 节点是空转）。已记为 CPI-70。本轮没有动 GUI，
把「要么渲染 `next_steps`、要么删掉这次请求」留作待办，不在本次裁定范围内。

**测试证据**：`NodeStatusGateTest` 10 → 12 用例，新增
`test_get_status_endpoint_never_writes_node_status`（真请求，断言 `node_status` 与
`nodes.updated_at` 都不变 —— `updated_at` 也必须钉住，因为 `BundleRevisionSync` 拿它做脏判定，
一次 GET 改了它就会触发无谓的 bundle 重发）与 `test_get_status_endpoint_is_idempotent`。
覆盖率自证：把控制器临时还原成 `checkAndUpdateNodeStatus()`，新用例**失败 1 条**；还原修复后
`OK (12 tests, 52 assertions)`。VPN 模块全量 `OK (243 tests, 1008 assertions)`，无回归。

**行为变化：有。** 一个真缺 IP/密钥的节点被 GET 时不再自动变 -1，会停在原状态，直到
① 配置写入路径重新评估（`NodeConfigService.php:137/206`、`NodeKeyService.php:48`），
② 管理员 `forceUpdateStatus`，或 ③ 心跳把它写成 1（议题 2 裁定保留的正是这条）。

---

## 议题 1：`GET /nodes/{id}/status` 该不该写 `node_status`？

### 现状（取证）

- 路由：`mynet_api/app/Modules/VPN/Routes/api.php:136` → `NodeController::getStatus()`
  （`app/Modules/VPN/Controllers/Api/V1/NodeController.php:1348`），中间件
  `auth:sanctum, token.binding, throttle:api`。
- 该 GET 第一步就调 `checkAndUpdateNodeStatus($nodeId, $userId)`
  （`NodeController.php:1357`），`$requestedStatus` 为 `null`，即**跑配置判据并落库**。
  方法自己的 docblock 就写着「先检查并更新节点状态（**副作用**）」。
- 判据落库的唯一动作在 `Node::updateStatusBasedOnConfig()`（`Models/Node.php:298`）：
  - 配置不完整 → 无条件写 `STATUS_UNCONFIGURED (-1)`；
  - 配置完整 → 只有 `$requestedStatus !== null` 才写。
  - **推论：`$requestedStatus = null` 时这个函数只可能降级，永远不可能升级。**
    所以这个 GET 的写入没有任何「自愈」价值，纯粹是一条降级通道。
- 降级的爆破半径不止本节点：`NetworkSemanticsProjector.php:215` 按
  `whereIn('node_status', [ENABLED, ONLINE])` 挑对端，一个节点变 -1 就从**所有**对端的
  bundle 里消失。同一过滤还出现在 `SocialDomainService.php:387/540/788`、
  `NodeRebootService.php:231/277`、`MonitorHistoryService.php:472/504`、
  `RouteDomainService.php:247/275`。
- 另有三个 `node_status` 写入者，规则各不相同：
  | 写入者 | 位置 | 是否过配置判据 |
  |---|---|---|
  | `Node::updateStatusBasedOnConfig()` | `Models/Node.php:298` | 是 |
  | `NodeHeartbeatDomainService::markNodeOffline()` | `Monitor/Domain/Services/…:477` | **否**，无条件写 1 |
  | `NodeService::forceUpdateStatus()` | `Services/NodeService.php:484` | 管理员路径 |
  | `NodeDomainService::updateNode()` | 请求里带什么写什么 | 否 |

  另有 `NodeService::validateStatusChange()`（:426）也拿 `is_fully_configured` 当启用闸门，
  但**全仓无调用者**（死代码，里面还有个赋值后从未使用的 `$canEnable`）。本轮修好判据后它的
  语义自动跟着对了，所以没动它；若采议题 1 的方案 A，建议连同两个无路由控制器方法一起清理。

### 影响

- 一个**只读**端点能改变全网 bundle 成员关系。GUI 若轮询该端点，等于把降级判据挂在轮询频率上。
- 现在（本轮修复后）降级只在节点**真的**缺 VPN IP 或密钥时触发，所以不再是误判——但
  「GET 改状态」这个结构性问题原样保留，下一次有人给判据加一个必选项就会重演 .184。
- 反向风险很小：因为该写入只能降级不能升级，去掉它**不会**让任何节点少一次自愈机会。

### 候选方案

| | 方案 | 说明 |
|---|---|---|
| A | **改只读** | `NodeController.php:1357` 改调 `NodeService::describeNodeStatus()`（本轮已实现的只读快照，响应形状一致，`status_evaluated=false`）。状态转换只留给配置写入路径、心跳和管理员。 |
| B | 保持现状 | 什么都不改。 |
| C | 保留写入但禁止降级 | 给判据加「只升不降」模式。 |

**不推荐 B**：它把「GET 有写副作用」这个结构性缺陷留在轮询端点上，而这正是 .184 事故的机制本身；
本轮只是拆掉了误判的弹药，枪还上着膛。

**不推荐 C**：需要给 `updateStatusBasedOnConfig()` 加模式参数，一个函数两种语义；而 C 想保住的
「升级」能力该函数**本来就没有**（`$requestedStatus = null` 时不可能升级），所以 C 付出复杂度
换来的是零收益。

**推荐 A**：唯一能同时做到「GET 无副作用」和「不加模式参数」的方案，且改动是一行调用替换。

### 迁移影响

- 响应体**形状不变**，仅 `status_updated` / `status_changed` 恒为 `false`，新增
  `status_evaluated: false`。
- 行为变化：一个缺 IP/密钥的节点，被 GET 时不再自动变 -1。它会停在原状态，直到
  ① 配置写入路径重新评估（`NodeConfigService.php:137/206`、`NodeKeyService.php:48`），
  ② 管理员 `forceUpdateStatus`，或 ③ 心跳把它写成 1。
- ⚠️ 需要一并确认：GUI 是否依赖这个 GET 来「纠正」显示状态。若依赖，A 落地时 GUI 侧要改成
  看 `configuration_status` 而不是看 `current_status`。

### 测试影响

- 新增：GET 一个缺密钥的节点，断言 `node_status` 不变且 `status_evaluated === false`。
  → **已落地**，见「## 裁决与执行」的测试证据（另加了一条 `updated_at` 不变与一条幂等）。
- 现有 `NodeStatusGateTest`（10 用例）与 `BundleRevisionSyncTest` 不受影响——它们不走这个端点。
- `status-check` / `status-info` 两个 v1 路由**并未注册**，`NodeController::getStatusInfo()`（:1182）
  与 `::checkConfigurationStatus()`（:1322）是无路由死方法；若采 A，建议同批删掉，否则
  「同一个判据有三个出口、其中两个不可达」会再次成为藏污点。
  → **裁决后处置：没有删，改为在 `checkConfigurationStatus()` 的 docblock 上加了 ⚠️ 警告**
  （「未挂路由的死方法，仍有写副作用，不要直接给它挂 GET 路由」）。理由：删公有控制器方法
  超出本次裁定范围，且已确认全仓无调用者；警告足以挡住「顺手挂路由把缺陷装回去」。
  要删请单独下令，可与 `getStatusInfo()`、`NodeService::validateStatusChange()`（:426 死代码，
  内含赋值后从未使用的 `$canEnable`）一并清理。

### 需要谁决定

用户。属对外接口语义（GET 是否有写副作用）+ GUI 契约，一旦落地就是对外口径。

---

## 议题 2：心跳无条件写 1，要不要过判据？

### 现状

`NodeHeartbeatDomainService::markNodeOffline()`（:477）在 VPN 接口 down 时
`$node->update(['node_status' => Node::STATUS_ENABLED])`，**完全不看配置判据**。
这是 .182 能从 -1 自愈的原因（每 30s 一次），也是 .184 不能自愈的原因（它的 agent 根本不向 API
心跳，gateway notify 回 404）。

### 影响

- 正向：心跳是唯一的自愈通道，去掉它就没有兜底了。
- 负向：它让判据对**任何会心跳的节点**形同不存在——真缺密钥的节点也会被心跳一路顶回 1，
  然后进对端 bundle（虽然 projector 拿不到 key 也建不出 peer 条目）。
- 两个写入者规则不一致，是「同一个字段两套真相」的典型。

### 候选方案

| | 方案 | 说明 |
|---|---|---|
| A | 保持现状 | 心跳继续无条件写 1。 |
| B | 心跳也过判据 | 只在 `checkConfigurationStatus()['is_configured']` 为真时写 1。 |
| C | 拆字段 | `node_status`（治理意图）与 `presence`（在线事实）分开，判据只管前者，心跳只管后者。 |

**推荐 A（暂不动）**，并把 C 记为 v1 之后的方向。理由：B 会**移除唯一的自愈通道**，
而 .184 已经证明「卡在错误状态且无人纠正」比「状态偶尔偏乐观」危险得多；C 是正确终局但属
数据模型变更，得走 P0 合同冻结流程（见 `MYNET_V1_PLAYBOOK.md` §11：改冻结合同要先改合同和 fixtures）。

### 需要谁决定

用户。议题 1 与议题 2 建议一起裁——它们决定的是同一件事：`node_status` 到底谁说了算。

---

## 附：裁决前已落地的部分（只锁口径，没有扩大行为）

本轮**已实现**的是 2026-10-01 定案里「node_config 从必查项移除/改为可选」「状态检查只在配置类
字段变更时跑并透传 requestedStatus」两条，以及配套的判据同口径。逐条：

1. `NodeService::checkNodeConfigurationStatus()`（:342）——`has_config` 标 `optional => true`，
   不再计入 `is_fully_configured` / `missing_items` / `total_checks`；新增 `optional_items`。
   `details` 仍含全部四项（openapi 登记过该字段，删键是破坏契约）。
2. `NodeService::updateNodeBasicInfoWithStatusCheck()`（:210）——只有请求显式带 `node_status`
   才评估判据；否则走 `describeNodeStatus()`（:291）只读快照。
3. `NodeService::findOwnedNodeOrFail()` / `statusSnapshot()`——从两处重复的 owner 查询与
   结果构造里提出来，越权判定仍在写之前。

裁决前**没有**动的：议题 1、议题 2 涉及的任何行为（当时 `getStatus` 照旧写、心跳照旧无条件写 1）。
裁决后：议题 1 已按 A 改为只读，议题 2 按 A 保持不动。
