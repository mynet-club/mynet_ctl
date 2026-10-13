# 路由写入归一（L6 单入口）

> 问题：同一份 bundle，macOS/Linux 走 `mynet apply --auto`，OpenWrt 走
> `node.lua:apply_routes` / `route.mynet`，数据源（`network.routes` vs `route.conf`）、
> 删除语义、冲突策略三处分叉。上层不归一，平台分支就会长成三套产品。
>
> 目标态已写在 `spec/unified-platform-architecture.md` §6 与 `dev-plan.md` 轨道 F：
> **唯一写入者 = `mynet apply`（L6）**，平台只分叉最后几条命令。本文是接口边界与迁移步骤。

---

## 1. 归一原则

| 原则 | 含义 |
|---|---|
| **意图只有一份** | `bundle.network.routes` 是 OS 路由的 SSOT（后端从 `node_ips` 投影） |
| **计划只有一份** | `planFromBundle` → `ApplyPlan`（RouteOp），策略（所有权/冲突/网关钉扎）全在这里 |
| **执行才分平台** | darwin `route` / linux·openwrt `ip` / windows `netsh`；OpenWrt 额外只有 UCI 防火墙与策略表 |
| **只动自己装的** | `ReconcileRoutes`：精确 diff，删除前查归属，绝不按目的地盲删 |
| **gnb `route.conf` 不装路由** | 它是 L5 地址宣告；装 FIB 是 L6 的事。OpenWrt 禁止再从 `route.conf` 反推 OS 路由 |

---

## 2. 接口边界

```text
back (node_ips)
   └─ BundleEnvelopePublisher
         └─ bundle.json
               ├─ gnb.*            → L5  WriteIntent → route.conf   （gnb 读，不进内核）
               └─ network.routes   → L6  planFromBundle → ApplyPlan
                                            │
                                            ▼
                                      executeApply（策略）
                                       ├─ ① 滤：与本机直连网段冲突 → skip
                                       ├─ ② 闸：目的地归属 ≠ 本隧道口 → skip
                                       ├─ ③ 台词翻译：routeCmds / bsdRouteCmds / netshRouteCmds
                                       └─ ④ 钉：网关被劫持才 pin /32
                                            │
                         ┌──────────────────┼──────────────────┐
                      darwin              linux             openwrt
                    route/ifconfig        ip route           ip route
                                                          + uci firewall（仅此）
                                                          + route_policy.sh（策略表 200，代理专属）
```

### 2.1 Go 接口（L6 现状对齐 spec §6.1，先落地子集）

```go
// mynet/apply_plan.go 已有的三段，即 ReconcileRoutes 的最小实现
type RouteOp struct {
    Destination string // prefix
    Gateway     string
    Device      string // 逻辑名 gnb_tun，执行前 resolveDevice
    Metric      int
    Action      string // add | replace | del
}

// 策略（平台无关，只准在这里）
func filterHijack(routes []RouteOp, localNets []*net.IPNet) []RouteOp
func routeTouched(owner, ourDev string) bool   // 空闲 | 自己装的
func pinDefaultGateway(...)                    // 先查 gatewayHijacked 再钉

// 执行（平台方言，只准分叉这几行）
func routeCmds(snap, r) []routeCmd             // linux: ip route
func bsdRouteCmds(r) []routeCmd                // darwin: route -n
func netshRouteCmds(r) []routeCmd              // windows（待补）
```

与 spec §6.1 `NetworkBackend.ReconcileRoutes` 的对应：

| spec | 本方案落点 | 状态 |
|---|---|---|
| 精确 diff，不删非本 generation | `routeTouched`（归属 = 本隧道口） | ✅ 已有（2026-09-27） |
| Apply 全量协调 | `executeApply` + `planFromBundle` | ✅ 已有 |
| CleanupGeneration | `clear`：`ip route show dev <tun>` 再 del | ⚠️ Go 侧待补；OpenWrt `node.lua:clear_routes` 已是此形态 |
| Platform() 分方言 | `preferBSDNetTools` + `routeCmds` | ✅ darwin/linux；win/openwrt 待补 |

### 2.2 OpenWrt 边界

| 能力 | 归属 | 迁移后 |
|---|---|---|
| 对端 /32 + LAN 网段进 FIB | **L6** `mynet apply --auto` | 与桌面同一条命令 |
| 清路由 | **L6** `mynet apply --clear`（只清 `dev <tun>`） | 弃用 `node.lua:clear_routes` |
| 防火墙 zone / 转发 | OpenWrt 平台实现（UCI） | 仍由 Lua / `firewall.mynet`，**可**挂到 `Apply` 的 Firewall 段 |
| 策略路由 table 200 / fwmark / DNS 分流 | 产品面（代理）`route_policy.sh` | 不动；它不是「装对端路由」，是旁路策略 |
| `route.mynet` / `apply_routes` 读 route.conf | ❌ 与 L5 语义混淆 | **废除**，改读 bundle 的 `network.routes` |

---

## 3. 迁移步骤

### M1 — 策略收口（Go，已完成）
- [x] 冲突过滤：与本机直连网段重叠 → skip
- [x] 所有权闸：`routeTouched`，只动空闲或本隧道口
- [x] 网关钉扎：`gatewayHijacked` 才 pin，不无条件写
- [x] 单测：`TestOverlapsAnyDetectsHijack` / `TestRouteTouchedOnlyOwn`

### M2 — 执行方言补齐
- [x] `netshRouteCmds`（windows）
- [x] openwrt 与 linux 共用 `ip route`（`routeCmds`）；设备名解析走 `resolveDevice`
- [x] `CleanupGeneration`：`mynet apply --clear`（`routesOnDevice` 只列隧道口上的，再 del）

### M3 — OpenWrt 切到 L6
- [x] `node.lua:apply_routes/clear_routes` 改为调 `mynet apply --auto` / `--clear`
- [x] `route.mynet` 收成薄封装（start/stop → mynet apply；不再 parse conf/route.conf 装 FIB）
- [ ] e2e：OpenWrt 起隧道后 `ip route show dev gnb_tun` 条目数与 `network.routes` 一致；stop 后清空

### M4 — 数据源钉死
- [x] `ApplyPlan` / `cmd_apply` 注释标明：`network.routes` = OS 路由 SSOT；`route.conf` = L5 宣告
- [x] 后端契约：`test_network_routes_do_not_filter_by_local_subnet` —— 后端不过滤，冲突归 L6 闸

### M5 — 多写者收敛（F-5）
- [x] `mynet_agent/scripts/check-route-writers.sh`：执行点仅 `apply_plan.go` + `GUARD-OK` 例外
- [x] 例外已标记：`route_policy.sh`（策略表 200）、`proxy.lua`（DNS /32）
- [x] `make test` 挂上该审计

---

## 4. 验收（DoD）

1. **同一 bundle** 在 darwin / linux / openwrt 装出的 FIB 前缀集合一致（减去冲突过滤差异，过滤规则也一致）
2. 删只删自己：在物理口手动 `route add` 一条与 plan 相同目的地，`apply --auto` 不得改它
3. stop/`--clear` 后 `ip route show dev <tun>` 为空；直连/默认路由完好
4. `go test ./...` 绿；OpenWrt e2e（M3）有输出
5. 全仓唯一写入点：`rg 'ip route (add|replace|del)'` 命中仅 `apply_plan.go` 与文档

---

## 5. 明确不归一的部分

| 留在平台 | 原因 |
|---|---|
| UCI 防火墙 zone | OpenWrt 专有配置模型，无桌面对应物 |
| `route_policy.sh` 表 200 + fwmark | 代理产品的策略路由，不是对端路由 |
| Windows `netsh` / 服务注册 | 无 POSIX route(8) |
| gnb `route.conf` 渲染 | L5 驱动语义，三平台同一文件格式 |

---

相关文档：`spec/unified-platform-architecture.md` §6 · `spec/driver-abstraction.md` R5 ·
`dev-plan.md` 轨道 F · `node-connectivity-checklist.md`（路由劫持教训）
