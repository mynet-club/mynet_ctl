# 实体网关 / 转发 / Proxy —— P4 开发计划（设计 + 待裁）

> **性质：P4 开发计划 + 设计决策包。** 实测在前、缺口在中、待裁在后。
> **未裁决前不写运行时码**（现状一行不动）。
> **对应**：`mynet_agent/docs/MYNET_V1_PLAYBOOK.md` §6（Proxy / 自动组网 决策）、§7 P4、§10 M4、§13 第 5 条。
> **边界**：不实现用户贡献节点市场（只留接口）；不把 Proxy 命名成第三种 VPN 协议；不改 `mynet_gnb/src`。

---

## 0. 一句话

P4 的能力**大部分已经在跑**（GNB 转发、Proxy 策略路由/DNS/清理、OpenWrt LAN 接入），但它们分成**两条互不相识的路径**：云侧 bundle 走 `GnbForwarding` + `ProxyPolicy` 下发，而 OpenWrt 的 `proxy.lua` **自读自写本地 `proxy_role.conf`/`proxy_route.conf`，不看 bundle**。P4 要做的不是从零造，而是**把两条路径收口到统一合同**（§13 第 5 条的字面要求），并补上三处真缺口：回程路由、fail-closed/网关离线无证据、`Acceleration` 契约缺位。

---

## 1. 现状实测（均带 `file:line`）

**已存在（能力面）**
1. **网络到网络的转发合同 = `GnbForwarding`**（`direct/standard/unified/proxy` + `relay_routes[via_node_id,prefix]`）：api 侧 `app/Modules/VPN/Domain/Bundle/GnbForwarding.php:7-53`，agent 镜像 `shared/domain/bundle_envelope.go:171-177`，消费点 `mynet/driver/gnb.go:688-730`。
2. **转发开关**在节点配置面：`NodeConfigController.php:384-386`（`direct_forwarding`/`unified_forwarding`/`standard_forwarding` + 同块 `forward`、原生 `proxy`，`:388,404`），枚举 `Config/vpn.php:89-120`；另有每设备 `ip_forwarding` 0/1（`NodeController.php:421`）。
3. **实体网关（LAN 接入）**是**隐式**的 —— 没有 `peer_gateway`/`lan_gateway` 模型；表达为一个带 `source:"lan"` 路由的节点。装配在 OpenWrt 侧：安装器 `mynet/cmd_install_openwrt.go:101,248,268,272`，LAN 路由合成 `mynet_openwrt/luasrc/model/mynet/node.lua:1088,1101,1143`。
4. **Proxy 合同**：`shared/domain/proxy_policy.go:12-44`（`Enabled/Role/Policy/Split/Exits/DNS/DomainRules/ACL/Fwmark/Table`），split 矩阵 `:199-223`，DNS 三选一 `off|peer|smart|peer_smart` `:75-125`（含旧 `none|redirect|resolv|split`）。
5. **Proxy 落地**：策略路由 fwmark/table/`ip rule`（`mynet/proxy_apply.go:65-146`）、DNS 执行 `:324-360`、**停止清理** `proxyCleanupCmds` `:394-420`（删 DNS /32、`ip rule del`、`route flush table`，全 `optional`；在 `mynet/apply_plan.go:285` 调用、`mynet/apply_plan.go:308` 刻意排除出回滚）、DNS 劫持 `mynet/proxy_dns_hijack.go`、**fail-closed ACL**（api `ProxyAuthDomainService.php:19-69`，agent `ProxyACLCmds` `mynet/proxy_apply.go:546-590`，隧道方向默认 `drop`）。
6. **ProxyPolicy 注入 bundle**：`BundleEnvelopePublisher.php:474-485`（写 `network.proxy` 并翻 `gnb.forwarding.proxy`）。

**分成两条路径 / 缺位**
7. **OpenWrt `proxy.lua` 是并行旧路径**：自读 `PROXY_ROLE_CONF`/`proxy_route.conf`（`mynet_openwrt/luasrc/model/mynet/proxy.lua:16,60,203,411,439`，bash `KEY=VALUE`），并直接改 GNB `route.conf` 标记（`:766-802`），**不消费 bundle 的 `network.proxy`** ⇒ §13 第 5 条**未达标**。
8. **无 `Acceleration`、无 `service_policy`**：全仓 grep（`app/`、`mynet_gui/src`、`mynet_agent`）零命中。现有 `Service` 是**已发布服务目录**（`Models/Service.php:28-31`，枚举 `gnb_index,music,video,netbridge` 在 `Console/V1/ServiceController.php:56`），不是转发合同。
9. **回程路由无实现**：全仓只有**诊断文案**（`mynet/cmd_route_doctor.go:99-102`、`mynet/cmd_gate.go:125`），无「往对端写回程」的代码。
10. **无 fail-closed / 网关离线用例**：grep 测试无命中；OpenWrt 侧**无可执行测试**（只有报告 `mynet_openwrt/docs/TEST_REPORT_PROXY_20260503.md`）。
11. **GUI 无 service/acceleration store**（`mynet_gui/src/stores/` 仅 `auth.ts`/`onboarding.ts`）；服务页走 API（`src/services/tenant/tenantService.ts:69`）；转发字段在 `src/types/nodeConfig.ts:236-243`。

**已有测试（能力面覆盖）**：Go `mynet/proxy_apply_test.go`（cleanup `:162`、server `:136`、DNS `:191`、fixture e2e `:301`、client `:59`）、`mynet/proxy_dns_hijack_test.go`、`shared/domain/proxy_policy_test.go`、`mynet/driver/gnb_conf_render_test.go:339`；PHPUnit `Console/ConsoleServicePolicyTest.php`（真 HTTP）、`Feature/Api/TenantServiceApiTest.php`、`tests/BundleEnvelopeV2Test.php:100`。

---

## 2. M4 判据 → 需求映射

M4 原文：**真实 peer 网关完成节点到网络、网络到网络、回程和 Proxy 启停；错误出口、规则清理和敏感日志测试通过。**

| # | 能力 | 对应 M4 片段 | 现状 |
|---|---|---|---|
| C-a | 节点到网络 / 网络到网络 | 「节点到网络、网络到网络」 | ✅ 有（GnbForwarding） |
| C-b | 真实 peer 网关（LAN 接入） | 「真实 peer 网关」 | ◐ 隐式（`source:"lan"`），OpenWrt 装配 |
| C-c | **回程路由** | 「回程」 | ✗ 无实现（§1-9） |
| C-d | Proxy 启停 + 规则清理 | 「Proxy 启停」「规则清理」 | ✅ agent 侧有；OpenWrt 并行路径 |
| C-e | **错误出口 fail-closed** | 「错误出口」 | ◐ 有 ACL 逻辑，**无用例**（§1-10） |
| C-f | 敏感日志脱敏 | 「敏感日志」 | ◐ 见 P0 脱敏（envelope 面）；Proxy 面待核 |
| C-g | **§13.5 统一合同** | （§13 第 5 条） | ✗ OpenWrt 未接（§1-7） |

---

## 3. 待裁（D1–D5，每条两案，back 不擅自选）

| # | 事项 | A 案 | B 案 | 取向备注 |
|---|---|---|---|---|
| **D1** | 「统一合同」的形状 | 以**现有** `GnbForwarding` + `ProxyPolicy` 即统一合同，OpenWrt 改成**消费它**（最少改动） | 新建 `Forwarding`/`Acceleration` domain 模型 + 迁移到新表/新端点 | A 零数据面风险、与 bundle 现形一致；B 更「干净」但动数据面且要迁移 |
| **D2** | OpenWrt `proxy.lua` 的处置 | **保留 lua 壳**，改为读 bundle `network.proxy`（删本地 `proxy_role.conf` 依赖） | 转发能力**下沉到 agent**，lua 退化纯壳（对齐 §1.2「mynetd 纯通信、系统能力归执行器」） | B 与架构原则一致但牵动 OpenWrt 执行路径 |
| **D3** | `Acceleration` 是否立项 | **v1 不建** —— 现有 Service 目录 + Forwarding + Proxy 已覆盖 §13.5 字面（「Service/Forwarding/Acceleration 合同」中的 Acceleration 无消费者） | 建 Acceleration 契约（新模型/端点/GUI） | 先问「有没有消费者」，无消费者则不建（反纸面达成） |
| **D4** | 回程路由 | 视其为 gnb **双向路由的固有属性**，只补**证据**（诊断 + 用例），不新写注入 | 新建「回程注入/检测」逻辑 | A 更可能正确（overlay 双向），B 有重复造轮子风险 |
| **D5** | fail-closed / 网关离线用例落点 | Go（agent 侧命令层，`proxy_apply_test.go` 同族） | PHPUnit Feature（真 HTTP 走 API 合同） | 取决于 D1：合同定在 bundle 则 Go，定在 API 则 PHPUnit |

---

## 4. 分步计划（每步一个可验 gate）

| 步 | 内容 | gate |
|---|---|---|
| **P4-1** | 现状「路径分叉」成文 + 选定 D1/D2（**决策包**，不动码） | 本文 + 主线裁定 |
| **P4-2** | OpenWrt `proxy.lua` 消费 bundle `network.proxy`（按 D2） | 同一份 proxy 策略，OpenWrt 与桌面出**等义**的 `ip rule`/DNS |
| **P4-3** | 回程路由：按 D4 补**证据**（诊断用例 + route doctor 断言） | 「正向/回程」用例 |
| **P4-4** | fail-closed / 网关离线用例（按 D5） | 错误出口 fail-closed、网关离线 |
| **P4-5** | 敏感日志脱敏复检（Proxy 面） | 脱敏用例 |
| **P4-6**（可选，取决于 D3） | Acceleration 若立项 | 迁移 + 回归 |

---

## 5. 测试计划（映射 §7 P4「必须测试」）

| P4 必测项 | 对应设计点 | 现状 |
|---|---|---|
| 正向/回程路由 | P4-3 / C-c | ✗ |
| 转发开关 | §1-2 现有 | ◐ 有字段，用例待核 |
| 网关离线 | P4-4 / C-e | ✗ |
| 错误出口 fail-closed | P4-4 / C-e | ✗ |
| DNS 模式 | §1-5 proxy_apply | ✅ `proxy_apply_test.go` |
| 重启恢复 | OpenWrt `proxy.lua:576-604` | ◐ 无测试基建 |
| 规则清理 | `proxyCleanupCmds` | ✅ `:162` |
| 敏感日志脱敏 | P4-5 / C-f | ◐ |

---

## 6. 不做什么（登记，别顺手）

- 不实现用户贡献节点市场（只留接口和未来合同）。
- 不把 Proxy 命名成第三种 VPN 协议。
- 不改 `mynet_gnb/src`、不新增驱动能力（gnb 职责边界）。
- 不把 `Acceleration` 当「必须有」而造一个无消费者的契约（反纸面达成 D1）。
- 不在未取证前把「回程缺失」直接判成 bug —— 先按 D4 取证（overlay 本就双向）。

---

## 7. 实施记录（2026-10-05 用户裁定 + 首批落地）

> 本节为**追加**记录，不改上文正文（正文的 `:474-485` 等行号是裁定前快照）。

### 7.1 裁定（用户原话要点）

1. **bundle 对 proxy 只表达一件事**：是否允许为对方代理（一个许可位）。其余（role / exits /
   DNS 三选一 / split / 域名规则）全归本地节点 —— WebUI 配置 + mynet 工具生成。
2. **`gnb.forwarding` 与 proxy 设计无关**：它是 node.conf 的 direct/standard/unified 开关组
   （node.conf 无名为 `forwarding` 的单一键），撞词 gnb 的 `f`（forward 节点属性，在 address.conf）
   纯属误导 —— 删其 `.proxy` 位。
3. **`proxy on` 属独立 `proxy.conf`**（`driver/gnb/conf/<id>/proxy.conf`），**不在 node.conf**。

### 7.2 已落地（A 案：只删 `.proxy` 位 + node.conf 渲染）

| 位置 | 动作 |
|---|---|
| `mynet_agent/shared/domain/bundle_envelope.go` `GnbForwarding` | 删 `Proxy bool` 字段 |
| `mynet_api/app/Modules/VPN/Domain/Bundle/GnbForwarding.php` | 删 `$proxy` 属性 + fromArray/toArray |
| `mynet_agent/mynet/driver/gnb.go` `RenderGNBNodeConf` | 删 `proxy on` 渲染块 |
| `mynet_api/.../BundleEnvelopePublisher.php` | 删 `gnb.forwarding.proxy=true` 与 forwarding 默认 `proxy:false` |
| `mynet_agent/mynet/plugin_proxy.go` `applyProxyForwarding` | 删 `Forwarding.Proxy=true`（只留 direct-forwarding） |
| `mynet_agent/mynet/project_active.go` | 删 `Proxy: li.Listen.Proxy` |
| `mynet_ctl/.../spec/schemas/bundle-envelope-v2.schema.json` | 删 `gnb.forwarding.proxy` 声明（SSOT 合同） |
| `mynet_ctl/.../spec/fixtures/bundle-envelope-v2/` | 6 个 gnb fixture 删除 `gnb.forwarding.proxy` 行并重算 `checksum`（`network.proxy` 保留不动） |
| 测试 | agent `plugin_proxy_test.go` / `project_active_test.go` 两断言翻成**反向不变量**（node.conf 不得含 `proxy on`）；api `BundleEnvelopeV2Test` 改断言（forwarding 不再带 proxy） |

**门禁**：`go build ./...` + `go test ./mynet/... ./shared/...` 全绿；api `phpunit` 441/441 全绿；
`pint --test` / `gofmt -l` 干净。

### 7.3 待裁 / 遗留

- **D1 收窄**：用户倾向「bundle 只留一个许可位」⇒ 需决定 `network.proxy`（整个 `ProxyPolicy`，
  含 role/exits/DNS/split/域名规则/ACL）与 `LocalListen.Proxy`（现已无消费者）的去留。
- **另一条 node.conf `proxy on` 路径未动**：`config/vpn.php:96-108` 的 expert `proxy` 开关
  （描述写「写入 node.conf 的 proxy on|off」）+ `NodeConfigController.php:399` 校验，经
  `GnbConfigGeneratorDomainService` 仍可能渲染 node.conf `proxy on`。与裁定 3 冲突，待裁。
- **fixture 已清净**：6 个 gnb fixture 的 `gnb.forwarding.proxy` 行删除并重算 `checksum`；
  Go `shared/domain` 与 api `BundleEnvelopeV2Test` 双端 fixture 用例全绿。
- **`LocalListen.Proxy` 已无消费者**（`project_active.go` 不再读），字段本体留待与 D1 一并对齐。
- **残留 node.conf `proxy on` 路径**（见上条 expert 开关）尚未收口，属 D1 收窄的连带项。

### 7.4 第二轮裁定与落地（2026-10-05，追加）

对 §7.3 两条待裁的用户裁定：

1. **残留 node.conf `proxy on` 路径 → 改指 proxy.conf**。已落地：
   - `mynet_api/config/vpn.php` 删除 gnb expert 的 `proxy` 字段定义；
     `NodeConfigController.php` 删除对应 `'proxy' => 'sometimes|string|in:on,off'` 校验。
   - node.conf 不再可能渲染 `proxy on`。gnb 代理开关归**独立 proxy.conf**，该文件由节点侧
     （proxy 插件 / 策略投影）生成，云端不渲染（依据 `spec/proxy-conf-design.md` §5 能力矩阵
     + §8「删 node.conf proxy」）。
   - api 侧本就无 proxy.conf 渲染器（`ConfigBundleService` 只产出 node.conf/address.conf/
     route.conf），故无需新增渲染；node.conf 字段消失后表单字段同步消失
     （`ConfigFormService` 由 config/vpn.php 驱动）。
   - 门禁：`php -l` 两文件通过；api `phpunit` 441/441 全绿。

2. **D1 粒度 = 对端级 ⇒ 保 `ACL`**。`shared/domain/proxy_policy.go:31-32` 的 `ProxyACL`
   （「出口授权：对谁开放」T11）；云端对应 `ConnectionGrant.php:29` 的 `RESOURCE_PROXY`。
   即「许可」保留对端级表达，不塌缩为网络区级布尔。

**D1 收窄的下一步（未执行，待 GO）**：bundle 的 `network.proxy` 只保留许可部分
（`enabled` + `acl`），客户端策略（`role` / `exits` / `dns` / `split` / `domain_rules` /
`region` / `policy` / `foreign_dns_route` / `full_tunnel` / `doh_mode` / `fwmark` / `table`）
移出 wire，改由节点侧 Local 轨承载（`ProxyIntentState`，`proxy_unify.go`）。涉及
Go `ProxyPolicy` / PHP `ProxyPolicy` / schema / 6 fixture / `proxy_apply.go` 的
`planFromBundle` 与 `StampCloudIntent` 取数来源 —— 属 wire 契约变更，另列步骤表执行。

### 7.5 D1 收窄落地（2026-10-05，第三轮，GO 后执行）

用户在第三轮裁定两点：**①客户端策略「靠 WebUI 本地配置生成」**（无云端→节点迁移通道）；
**②wire 承载方式 = 新增独立 `ProxyPermission` 类型**（Cloud 轨存许可，Local 轨存客户端策略，
L6 合成）。据此 `network.proxy` 收窄为 `{enabled, acl}`，许可能力权威 = `ConnectionGrant
(resource_type=proxy)`（`ProxyAuthDomainService`）。

| 仓 | 改动 | 关键落点 |
|---|---|---|
| mynet_agent | 新增 `ProxyPermission`（`shared/domain/proxy_policy.go`）+ `NetworkIntent.Proxy` 收窄 | `AsCloudPolicy()` 把许可投影为 server 角色；`proxy_intent.go` 的 `Effective()` 由「按 revision 仲裁」改「按职责合成」（Local client ⊕ Cloud 许可） |
| mynet_agent | 取数改向 | `StampCloudIntent(*ProxyPermission)`；`planFromBundle` 客户端策略取 Local 轨；`localapi.ApplyCloudIntent(ProxyPermission)`；`SetProxyEnabled/Exits/CustomDomains` 基座改 Local 轨 |
| mynet_agent | 测试重建 | `proxy_intent_test.go` 合成语义用例；`proxy_apply_test.go` fixture e2e 改为「Local 轨播种客户端策略 + fixture 只带许可」 |
| mynet_api | 新增 `Domain/Bundle/ProxyPermission.php`；`NetworkIntent::$proxy` 改 `?ProxyPermission` | 发布侧 `BundleEnvelopePublisher` 不再读 `node_configs.config.proxy`，改由 `ProxyAuthDomainService::resolvePermission(nodeId)`（无 active 授权 → 不下发 `network.proxy`） |
| mynet_ctl | schema `network.proxy` → `$defs/ProxyPermission`；删客户端策略 `$defs`（ProxyPolicy/Split/DNS/Exit/IPListRef/DomainRules，保 ProxyACL） | 契约面与裁定一致，不留纸面字段 |
| mynet_ctl | `gnb-proxy-split.json` 收窄为 `{enabled: true}` 并重算 `checksum`（SSOT + agent testdata 双份 **byte-identical**） | 新 checksum `sha256:de373a12…` |

**未改 / 明确保留**：节点回写端点 `PUT /monitor/node/proxy-config` 仍存 `node_configs.config.proxy`
（云端留档，**不再作为 bundle 许可来源**）；`ProxyPolicy` 类型本身保留（Local 轨与云端留档仍用）。

**门禁**：agent `go build ./...` + `go test ./...` 全绿（19 包）；api `phpunit` 441/441 +
`pint --test` 干净；两端 fixture 互解（Go `shared/domain` 与 api `BundleEnvelopeV2Test`）通过。

### 7.6 第四轮口径裁定 + 出口授权面遗留（2026-10-05）

用户第四轮裁定：**空白名单 = 组织内放开**（默认全放开），确认既有行为、无需改动。

- 证据：`ProxyAuthDomainService::resolveForExit`（`ProxyAuthDomainService.php:51-54`）
  未配置授权即返回 `MODE_OPEN`；`ProxyACL::isAllowed`（`ProxyACL.php:76-82`）
  open/denylist 分支返回 `true`；出口侧 L6 `ProxyACLCmds`（`proxy_apply.go:554-556`）
  在无 `allowed_cidrs` 时直接不装规则 = 不设限。三处一致，口径已锁。

**遗留（登记，未执行）** —— 出口授权面两个真缺口，均需另裁：

① **许可无写入面**。`ConnectionGrant(resource_type=proxy)` 目前**无任何写入路径**：
`ConnectionDomainService::updateGrant`（`ConnectionDomainService.php:75-81`）白名单只收
`network|node|service`，且是唯一的 `ConnectionGrant::create` 调用点（`:94`）。故
`resolvePermission` 恒返回 `null`，`network.proxy` 实际**永不下发** —— 许可「架构上对、链上悬空」。

② **节点级授权未落到包过滤**。`ProxyACL.AllowedNodeIDs`（「允许谁」，对端级）在 L6
无任何命令消费（`ProxyACLCmds` 只吃 `AllowedCIDRs`，`proxy_apply.go:565-569`），
目前仅在业务层 `isAllowed`/`canUse`（`ProxyAuthDomainService.php:88-93`）表达，包层不设限。

### 7.7 出口授权写面落地（2026-10-05，第五轮裁定 + 执行）

用户在第五轮就 §7.6 的两处遗留裁定：①「许可无写入面」→ **单开一个**（节点级专表，
**不复用** `connection_grants`）；②「允许谁」口径 → **出口侧 L6 拦**（云端把消费端解算成
overlay CIDR 下发，出口防火墙 allowlist 真正生效，非纯业务层）。

**为何专表**：出口授权是节点级语义，`connection_grants` 装不下 —— `permission` 仅
`varchar(32)`（消费端 node_id 是 bigint，一多即溢出），`connection_id` 非空又进唯一键
`uk_grant_scope`，节点级授权没有天然连接。

| 仓 | 改动 | 关键落点 |
|---|---|---|
| mynet_api | 新表 `proxy_exit_grants` | 迁移 `2026_10_05_000001` + `tests/Schema/VpnModule.php` + DBA `database/sql/2026_10_05_create_proxy_exit_grants.sql`（生产禁 migration，走手工 SQL） |
| mynet_api | `ProxyExitGrant` 模型 | 节点级：`consumer_node_id` NULL = 全放开；唯一键 `(exit_node_id, consumer_node_id)` |
| mynet_api | `ProxyAuthDomainService` 读写改向专表 | `resolvePermission`/`resolveForExit` 读专表；新增写面 `grant()`（替换语义）/`revoke()`；`resolveForExit` 把白名单消费端解算成 overlay `/32` 写进 `acl.allowed_cidrs`（喂 L6） |
| mynet_api | 撤 `ConnectionGrant::RESOURCE_PROXY` | 专表后无用；`ConnectionGrant` 只表达 network\|node\|service |

**测试**：`tests/Unit/Domain/ProxyExitGrantTest.php` 5 例（放开 / 白名单+CIDR / 替换语义 /
撤销单个与整体 / 未授权不发许可）；api 全量 `phpunit` **446 全绿**（原 441 + 5）。
**未改**：Go 侧 L6 `ProxyACLCmds` 本就消费 `allowed_cidrs`，无需动；wire/schema 不变。

**待办**：HTTP 写面（`PUT/POST`）尚未开。console 面 write 有既有闸门
（`Controllers/Console/V1/NodeController.php:23-24`：「写路径要带审计与操作者身份」），
故写面落在用户面还是 console 面、审计如何接，需另裁后再开。

→ 已落：用户面写面见 §7.8（第六轮裁定）；console 面「网络区提供 proxy 公共服务」仍是独立线，未开。

### 7.8 出口授权用户面写面落地（2026-10-05，第六轮裁定 + 执行）

用户在第六轮裁定：写面落**用户面**，语义 = **对朋友连接授权**（自己的节点不用授权）；
console 面「网络区提供 proxy 公共服务、可授权出口」是**另一条线**，不在本轮。

| 仓 | 改动 | 关键落点 |
|---|---|---|
| mynet_api | 新控制器 `ProxyExitController`（用户面 V1） | `GET/PUT/DELETE /api/v1/nodes/{id}/proxy-exit`；`{id}` 经 `OwnerGuard::authorize('update', …)` 限属主（缺失 404、非属主 403）；`PUT` 收 `friend_ids` |
| mynet_api | 路由挂载 | `Routes/api.php` 的 `Route::prefix('nodes')` 认证组内三条 |
| mynet_api | 写面语义 | 好友关系校验（`Friendship` + `tenant_id`，仿 `TenantScopedServiceService::shareServiceWithFriends`）；好友 user_id → 其**启用/在线**节点 → `ProxyAuthDomainService::grant()`（替换语义）；`friend_ids: []` = 清空 → 默认开放 |
| mynet_api | 领域：自己的节点不用授权 | `ProxyAuthDomainService::resolveForExit()` 在白名单模式下并入出口属主自己名下节点（`ownerNodeIds()` = `nodes.user_id`）；否则设白名单会把属主自己挡在门外 |
| mynet_ctl | OpenAPI `back.yaml` | 新增 `node-proxy` tag + `/nodes/{id}/proxy-exit` 三方法 |

**fail-closed 两条**（写面）：① 名单含非好友 ⇒ 整体拒（不部分放行）；② 好友名下**无可连节点** ⇒ 拒。
否则 `grant()` 收空集合会按「空白名单 = 放开」把出口**误开给全网络区** —— 授权几个好友却全放，方向最坏。

**测试**：`ProxyExitApiTest` 7 例（写读闭环 / 清空回开放 / 非好友拒 / 好友无可连节点拒 / 撤销 / 非属主读写 403 / 缺失 404）
+ `ProxyExitGrantTest` 补 1 例（属主节点自动并入白名单）。api 全量 `phpunit` **454 全绿**、`pint` 干净。

**未改**：Go 侧 L6 与 wire/schema 不变；`grant()`/`revoke()` 原样复用。


<!-- GUARD-OK 本文引述的是文件名、行号、表名与配置键，不含凭据 -->
