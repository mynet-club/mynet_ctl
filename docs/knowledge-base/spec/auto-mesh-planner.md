# 自动组网 planner —— P3 开发计划（设计 + 待裁）

> **性质：P3 开发计划 + 设计决策包。** 实测在前、设计在中、待裁在后。
> **未裁决前不写运行时码**（现状一行不动）。
> **对应**：`mynet_agent/docs/MYNET_V1_PLAYBOOK.md` §7 P3（节点注册、自动组网和容量闸门）/ §10 M3（企业自动组网）/ §13 第 4 条。
> **边界**：不实现自研自动选路；不对开放租户默认开启无限全互通；不改 `mynet_gnb/src`。
>
> **✅ 裁定（2026-10-05）**：**自己的节点自动组网、不限制** —— 且**此前已实现**（`NetworkSemanticsProjector` 的 own-node 合成，见 §1-2）。⇒ 本计划的「容量闸门 / 超限拒绝」对自家节点**不做**；planner 不再需要决定「谁连谁」。详见 **§8**。
> **✅ 追加裁定（同日）**：并发/重复护栏**保持代码层逻辑判断、不加唯一索引、不动表结构**（软删语义会使唯一键更难缠）—— 见 **§8.1**。**P3 就此收口**（无新增运行时码）。

---

## 0. 一句话

现网的「组网」= **人工 `RouteRequest` 审批** + **一条隐式的「自家节点默认全互联」合成**（在 bundle 生成时现算，无闸门、无快照、无上限）。P3 要做的不是发明新选路，而是把这条隐式行为**显式化、可计数、可拒绝、可回滚**，并加一层 **planner 的 `shadow` 输出**——先在 shadow 里把「打算连谁 / 多少条 / 多大」算出来给人看，`enforce` 再落数据面。

---

## 1. 现状实测（八条，均带 `file:line`）

1. **组网入口是人工审批。** `RouteRequestDomainService::sendRouteRequest`（`mynet_api/app/Modules/VPN/Domain/Services/RouteRequestDomainService.php:49`）→ `acceptRouteRequest`（`:297`）；接受时 `createConnection()` **双向各写一条 `Route`**（`:571-599`），再 `republishBundles()` 让两端 bundle 失效重发（`:347`，实现 `:380-391`）。
2. **「自家节点默认互通」已存在，但是隐式合成。** `NetworkSemanticsProjector::fromDatabase()` 直接查「同租户、同 `user_id`、状态 enabled/online 的其它节点」并**合成 peer**（`.../NetworkSemanticsProjector.php:208-256`，`description='own-node'`）——**不落 `routes` 表、不经审批、无计数**。
3. **这份合成直接决定 `route.conf` 规模。** `BundleEnvelopePublisher::compose()` 由 `$semantics->peers` 生成 `gnb.nodes` / `gnb.routes`（`:281-315`），WG 侧生成 `wireguard.peers`（`:436-459`）。
4. **无任何大小 / 条数上限。** `compose()` 无对端数 / 路由条目 / 字节检查；`GnbConfigGeneratorDomainService::generateRouteConfig()`（`:218-239`）逐行拼接、不设阈值。
5. **无节点数配额。** 全仓无「每租户 / 每用户节点数」闸门（本轮 grep 无命中）。现有配额只有 IP 池用量（`IpPoolDomainService.php:119,140-145`，每池固定 6 IP）与 service 的 `service_max_connections`（`Models/Service.php:33`）。
6. **拓扑 / 规划层已存在但未接线。** `NodeCapabilityResolver`（能力画像 `canAcceptInbound/hasPublicEndpoint/canProxy/canRelay/supportsIpv6`，`:41-79`）与 `TransportPlannerDomainService::plan/planAll`（OS 路由语义，`:25-62`）**全仓零调用方**（本轮 grep 确认：后者只在自己文件内出现）。`NodeCapability` VO 也只被 `NodeCapabilityResolver` 使用。
7. **两套连接模型并存，只有一套进数据面。** 新 `ConnectionDomainService`（B-5，`request/approve/revoke/updateGrant`，`:11`）写 `connections` 表（**有**唯一键 `uk_connection_pair (source_node_id,target_node_id)`，迁移 `database/migrations/2026_09_23_000001_create_vpn_business_tables.php:113`），但 **bundle 生成不读 `connections`**——envelope 里 `'connections' => []` 恒空（`BundleEnvelopePublisher.php:198`）。数据面只认 `routes`（经 projector）。
8. **无 DB 级并发护栏。** `routes` / `node_route_requests` **无唯一索引**（迁移 grep 无命中），创建前只有**业务级**重入检查（`RouteRequestDomainService::checkRouteRequestReentry():179-288`），无 `FOR UPDATE` / 事务 → 并发可绕过。

---

## 2. M3 判据 → 需求映射

M3 原文：**认证租户 scoped enforce；容量 shadow 数据；超限拒绝；真实节点配置可回滚；route.conf 和 bundle 大小有证据。**

拆成五个能力：

| # | 能力 | 对应 M3 片段 |
|---|---|---|
| C-a | 租户 scoped planner（跨租户不可见） | 认证租户 scoped |
| C-b | shadow 输出（容量数据） | 容量 shadow 数据 |
| C-c | 容量闸门（超限拒绝） | 超限拒绝 |
| C-d | 可回滚（失败撤本轮、回上一代） | 真实节点配置可回滚 |
| C-e | 尺寸证据（route.conf / bundle 字节与条数） | 大小有证据 |

---

## 3. 设计骨架

### 3.1 三态开关（**租户级**，不是全局）
- `off`：不跑 planner，仅保留现状（人工审批 + projector 的 own-node 合成）。
- `shadow`：**算 + 记 + 暴露**，**不改数据面**（默认态）。
- `enforce`：算 + 落 `routes` + 重发布 bundle。

默认 `shadow`，且**只对显式开启的租户生效**（不扩大默认租户范围——playbook §13 第 4 条明写）。

### 3.2 输入
`NodeCapabilityResolver::resolveMany()`（能力）+ 租户内节点清单（`nodes` 按 `tenant_id`，含 `status/node_type/user_id`）+ 现有 `routes` / `connections` + `node_configs`（listen / ifname）+ 每节点 `node_ips` LAN 段。

### 3.3 shadow 输出（形状 **待裁 D2**）
```
MeshPlan {
  tenant_id, mode, generated_at,
  pairs: [ { a, b, reason(own-node|approved|relay), driver } ],
  per_node: { node_id: { peers, route_lines, route_bytes } },
  totals: { edges, route_bytes_max },
  warnings: [ { code, scope, detail } ]        // 掩码不支持、超限预估、无 endpoint…
}
```
默认**纯计算、不落库**，以只读端点暴露（最安全）。

### 3.4 容量模型（单位与初值 **待裁 D3**）
三个**可判定量**：
1. `max_peers_per_node`（每节点对端数）；
2. `max_edges_per_tenant`（每租户边数）；
3. `max_route_conf_bytes`（单节点 `route.conf` 字节上限）。

- `shadow`：超限进 `warnings` + `estimated_over_limit=true`，不拦。
- `enforce`：超限 **409 拒绝**，复用 `DomainException::conflict()`（与重入拒绝同口径）。

### 3.5 planner 落点
新建后端侧 `MeshPlannerDomainService`（domain 层、租户 scoped）——**不是** `TransportPlanner`（后者是「一条连接 → OS 路由」，不是「谁连谁」）。
`TransportPlanner` 在 `enforce` 里作为**第二跳**被调用（pairs → 每条连接的 OS 路由），从而把 §1-6 的零调用方**接上**，而不是新造一套。

### 3.6 应用面（enforce）
- author `routes` 行（数据面只读 `routes`，见 §1-7）→ 走 `BundleInvalidator::invalidateAfterRouteChange()`（`RouteRequestDomainService.php:347,380-391`）重发布两端 bundle。
- **不改 projector 的语义**（保留「自家节点默认互通」），planner 的目标与之**对齐**（对齐口径 **待裁 D1**）。
- 节点侧配置应用与回滚**复用 P2 已落物**：`mynet_agent/mynet/apply_plan.go` 的 `applyJournal` + `rollbackCmds()`（见 playbook §7 P2 实施记录）。

### 3.7 回滚
enforce 失败 → 撤销**本轮新建**的 `routes` → 重发布 → 节点侧退回**上一代 `config_revisions`**（bundle 上一版）。判据：**不误摘本轮之前已存在的授权边**（对应测试「旧连接不误摘新连接」）。

### 3.8 幂等 / 并发（**待裁 D4**）
`routes` 唯一键（`node_id,target_node_id,tenant_id`）+ `insertOrIgnore` 或 `DB::transaction` + `lockForUpdate`；`node_route_requests` 同加唯一键。现状仅业务级检查（§1-8），并发可绕。

---

## 4. 待裁（D1–D5，每条两案，后端不擅自选）

| # | 事项 | A 案 | B 案 | 取向备注 |
|---|---|---|---|---|
| **D1** | planner 与「自家节点默认互通」的关系 | planner 只做**闸门/计数**，own-node 合成保留（语义不变） | 把 own-node 合成**收进** planner（去掉 projector 隐式合成，统走 `routes`） | B 更干净但**动数据面语义**（现网已依赖它），风险高 |
| **D2** | shadow 输出载体 | 只读端点**现算**（零持久化） | 落 `mesh_plans` 表（可比较历史，需新表 + 清理策略） | — |
| **D3** | 容量单位与初值 | 只定义 3.4 三个量，初值由运维按实测 `route.conf` 现网最大值定 | 现在就写死默认值 | 初值应来自**证据**（M3 要求） |
| **D4** | 并发护栏范围 | 仅 `routes` 唯一键 + 事务（最小） | A + `node_route_requests` 唯一键 + `lockForUpdate` | — |
| **D5** | enforce 的作者对象 | 继续写 `routes`（与数据面一致） | 改走 `Connection` + 让 projector 读 `connections`（两套模型合一） | B 改动面大，牵动 §1-7 |

---

## 5. 分步计划（每步一个可验 gate，默认 `shadow`，不扩大租户面）

| 步 | 内容 | gate |
|---|---|---|
| **P3-1** | shadow 计算 + 只读端点 + 尺寸/容量计数 | 单测 + `route.conf` **字节/条数证据** |
| **P3-2** | 容量闸门（超限拒绝）接线到 enforce 路径 | 超限拒绝用例 |
| **P3-3** | 并发护栏（唯一键 + 事务 + 锁） | 并发/重复注册 race 用例 |
| **P3-4** | enforce 落 `routes` + 重发布 + 回滚 | 应用失败回滚、旧连接不误摘新连接 |
| **P3-5**（可选，取决于 D5） | 两套连接模型合一 | 迁移 + 双读回归 |

---

## 6. 测试计划（映射 §7 P3「必须测试」）

| P3 必测项 | 对应设计点 |
|---|---|
| shadow / enforce | §3.1 三态开关 |
| 重复注册 | §3.8 唯一键 |
| 并发节点加入 / race | §3.8 事务 + 锁 |
| route.conf 大小上限 | §3.4 `max_route_conf_bytes` |
| 超限拒绝 | §3.4 enforce 409 |
| 应用失败回滚 | §3.7 + P2 apply 回滚 |
| 旧连接不误摘新连接 | §3.7 回滚判据 |
| 认证租户 scoped | §3.1 只对开启租户 + 租户作用域（C-a） |

---

## 7. 不做什么（登记，别顺手）

- **不实现自研自动选路**（playbook §7 P3 明文禁止）。
- **不对开放租户默认开启无限全互通**（同上）。
- 不改 `mynet_gnb/src`、不新增驱动能力（gnb 职责边界）。
- 不动 projector 的软删 / 租户作用域语义（`NetworkSemanticsProjector.php:170-181` 那两条教训）。
- 不把 `TransportPlanner` 的 OS 路由语义与 planner 的「谁连谁」混为一层（两层职责不同，见 §3.5）。

## 8. 裁定记录（2026-10-05）

**裁定（用户原话要点）**：「自己的节点要不要自动互相连通、连多少、太多要不要拒绝 —— 不用问了，此前已经实现了，自己的所有节点，自动组网，**不限制**。」

**含义：**

1. **自家节点全自动组网、无数量上限**，且这是**既有行为**（§1-2 的 `NetworkSemanticsProjector` own-node 合成），不是新需求 ⇒ **不建容量闸门、不做超限拒绝，§3.4 的 gate 面作废、D3 的 i/ii/iii 全部不落。**
2. **planner 的「谁连谁」这一层不需要新建** —— 自家节点已默认全互联；`MeshPlannerDomainService` 对自家节点**不立项**（§3.5 取消）。
3. **与 playbook「不对开放租户默认开启无限全互通」不冲突** —— 那条讲**跨租户 / 开放租户**；本裁定讲**同一用户自己的节点**，作用域不同。
4. **仍然成立的两条**（与容量无关，是缺陷 / 证据）：
   - **并发 / 重复注册护栏**（§1-8：`routes` / `node_route_requests` 无唯一索引，仅业务级 check-then-insert，存在 race → 可能建重复边）。这是**真 bug**，不因「不限制」而消失。
   - **尺寸证据**（M3 的「`route.conf` 和 bundle 大小有证据」）：无上限 ≠ 不用观测；如需，只做**只读统计**，不做拦截。

**⇒ P3 的剩余面**：**不需要 planner、不需要容量闸门**；只剩「并发 / 重复注册护栏」一条缺陷，与（可选的）尺寸只读统计。§5 的分步计划（P3-1…P3-5）除 P3-3（并发护栏）外**全部作废**。

### 8.1 追加裁定（2026-10-05 同日）：并发/重复护栏的**修法**已定

**裁定（用户原话要点）**：「加索引有 bug，更麻烦，还有软删除这些，**就逻辑判断吧**。」

**含义**：§8 第 4 条第一项（并发 / 重复注册护栏）**不加唯一索引、不动表结构** —— 理由是 `routes` / `node_route_requests` 上若按「源+目标」建唯一键，会与**软删除语义**纠缠（软删行仍占唯一位、复连时 `insertOrIgnore` 静默失败），反而制造更难查的 bug。⇒ 重复防护**保持代码层逻辑判断**（现状 `RouteRequestDomainService::checkRouteRequestReentry()`，`:179-288`），仅靠应用层 check-then-insert，**不引入 DB 级约束**。

**⇒ P3 收口**：planner 不立项、容量闸门作废、并发护栏**保持逻辑判断（无新增运行时码）**；§5 全部作废。可选的「尺寸只读统计」仍未做，但**非阻塞**、无裁定需求时不主动做。

<!-- GUARD-OK 本文引述的是文件名、行号、表名与配置键，不含凭据 -->
