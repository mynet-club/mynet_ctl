# MyNet 开发计划

> 依据：`docs/mynet-overview.md`（设计与决策）· `docs/repo-migration.md`（仓库结构与迁移）· 2026-09-23 三仓摸底实测
> 状态：**可执行**。架构与策略层面已无未决项（`back` 密钥轮换明确「后期可加」）。

---

## 0. 现状摸底总结

实测于 2026-09-23，三仓并行摸底 + 测试实跑。

| 层 | 完成度 | 一句话现状 |
|---|---|---|
| **mynet_gnb**（数据面） | **实装** | 三补丁 apply/revert 齐、版本链完整（1.6.8） |
| **mynet_agent** 控制面 | **~55%** | coordinator/domain/gateway/localapi 真实；**WireGuard 零实现**、`VPNLifecycleDriver` 无实现类 |
| **mynet_back** 数据层 | **~75%** | 模型齐全、全局 ID 主键基本统一；**全部 VPN/Monitor/Social 表无 create migration**（靠旧库） |
| **mynet_back** L1/L2/L3 | **~30%** | `BundleEnvelope` + Publisher + Projector 存在；**三个 DomainService 全缺** |
| **mynet_back** API / 心跳 | **~85% / ~80%** | 清单完整、中间件链清晰；路径漂移 + 公钥编码三假设 |
| **mynet_openwrt** | **实装** | 12 model 全实装、83 路由、`_gnb_peer_heal` 完整；**ubus 未落地**、postinst 仍 `pkill mynetd` + cron |
| **mynet_ui / mynet_gui** | **实装有硬伤** | 页面/API 齐全可跑；ui 无 token、gui 硬依赖远端 back、echarts 1MB chunk |
| **mynet_desktop** | **空心** | 9 文件、mynet_client 血统、systemd 跑 Node |
| **mynet_ctl** | **半实装** | 路由/表/管理后台在；Release 域仅命名 SSOT 一份 PHP |
| **测试体系** | **失效** | Go 全量不绿（e2e 混入 + **硬编码生产密码**）；PHP 全量 Fatal |

### 0.1 实测出的硬事实（决定计划顺序）

| # | 事实 | 证据 |
|---|---|---|
| 1 | **PHP 全量套件 Fatal 起不来** | `tests/Stubs/FakeToolRegistry.php:15` 签名缺 `string $risk = 'L1'`。⚠️ **根因是框架 AI/Workflow 残留**——对端 `app/Services/ToolRegistry.php` 引 `Modules\Ai\...\Dto\Tool`。**直接删残留比重修签名干净**（见 P0-1） |
| 2 | **`go test ./...` 不绿** | `mynet_tests` 是 e2e 打真实 `api.mynet.club`；`mynet_agent/tests/api_route_test.go:13-14` **硬编码生产凭据**（邮箱 + 明文密码，本表已脱敏） |
| 3 | **WireGuard 驱动零实现** | `driver/wireguard/` 空目录（2025-09-09 创建至今未填充）；`vpn_driver_factory.go:26-31` 是 `TODO(v0.10.0)` 占位 |
| 4 | **`VPNLifecycleDriver` 无实现类** | `gnb_driver.go:82` 注释「尚不足以让 GNBDriver 满足 domain.VPNLifecycleDriver」；消费点 `localapi_bridge.go:228-232` 类型断言失败即 `ErrUnsupported` |
| 5 | **gateway 四项能力全已实现，但鉴权方向已改** | `auth.go`（Ed25519 Authenticator）/ `server.go:307-398`(relay) / `registry.go:21-126`(presence) / `server.go:152-195`(notify) **均已实现** —— 轨道 C 是「**拆掉鉴权**」而非「新建 gateway」 |
| 6 | **WebUI embed / cookie / Origin 全无** | `embed.FS`/`http.FileServer`/`SameSite`/`HttpOnly`/`Origin` 全仓 grep 为 0 |
| 7 | **mynetd 强制 root** | `cmd/mynetd/main.go:98-100` `os.Geteuid() != 0 → log.Fatal` |
| 8 | **私钥有三处外泄出口** | `NodeKey.php:24` `$fillable`；`NodeController.php:823` 生成接口回显；`ConfigBundleService.php:141` 装进 config bundle |
| 9 | **公钥编码三种假设仍在** | `NodeKey.php:14`(hex) / `NodeHeartbeatAuthMiddleware.php:57-60`(base64) / `BundleEnvelopePublisher.php:335-349`(两种兼容转 hex) |
| 10 | **`detector` 与 `tools/{firewall,routing}` 是两套重复的探测（读）实现** | 两边函数全是 `Detect`/`Get`/`parse`，**无一 `Apply`/`Write`**；`parseIptablesOutput` 两边同名，`detectFirewallType`↔`detectLinuxFirewallType` 对应。**L6 需要的写入能力两边都没有**。真死代码只有 `interfaces`(335)、`checker`(292)、`legacy/statusmap`(20)；`tui/viewmodel` 是活的 |
| 11 | **`%!d(string=...)` 残留 5 个** | 仅 `driver/gnb/conf/4147069894021819/ed25519/`（另两个 conf 子目录干净）；与正常 `.public` 并存 |
| 12 | **命名/SSOT 三处不一致** | `conventions.md`= `agent_*`、`ReleaseDomainService` 无 `agent` 产品、实际产物 `mynet_*` |
| 13 | **`apps_index_url` 分裂** | Go 侧 `download.mynet.club`（`updater.go:162`）、OpenWrt 侧 `ctl.mynet.club` |
| 14 | **秒精度唯一键两处定义不一致** | `node_monitor_tables.sql:32` 有 `uk_node_snapshot`，`tests/Schema/MonitorModule.php:33-48` 无 |
| 15 | **L6 `NetworkBackend` 全仓不存在，且写入能力缺失** | grep `NetworkBackend`/`OSRoute`/`SystemNetwork` **0 命中**（已正确重验）。spec §1.4 说要收敛 `tools/{firewall,routing}` 的多写者——但实测那些**也只是读**；真正的写在 **shell 脚本**（`scripts/*/route-manager.sh`、`firewall-manager.sh`）与 **Lua**（`ensure_firewall_zone`/`apply_firewall_masq`） |
| 16 | **`system.json` 机制不存在** | 全仓无系统能力快照文件；`mynet detector` 子命令不存在 |
| 17 | **`mynet` CLI 无 `setup/install/uninstall/update` 子命令** | 现有仅 `config show\|check`/`logout`/`monitor`/`nodes`/`platform`/`refresh-*`/`status`/`chk_myself`/`print-config-sources` |

---

## 1. 计划结构

**P0 是阻塞项**——测试基建不修，「验证设计」无从谈起。
P0 之后是 **6 条可并行轨道**，按依赖标注。

```
P0 测试与安全地基（阻塞一切）
        │
   ┌────┼────────┬─────────┬─────────┬────────┐
   ▼    ▼        ▼         ▼         ▼        ▼
   A    B        C         D         E        F
  驱动  数据契约  控制面    产品面    平台面    L6网络层
 (主线)                                     (跨驱动路由)
   │    │        │         │         │        │
   └────┴────┬───┴─────────┴─────────┴────────┘
              ▼
      验收：双驱动设计验证通过
```

---

## 1.1 执行器架构（总则 —— 决定 mynetd 的体积边界）

**mynetd 只做通信；系统能力全在执行器。** 两个平台用**同一套 mynetd 通信层**，
只是执行器不同——这与已定的「OpenWrt：mynetd 命令交 Lua 执行」完全对称。

```
mynetd（纯通信层，常驻）            执行器（偶发，用完即抛）
   ├── 心跳组装与上报                    │
   ├── WS 长连接 + 身份密钥（X25519 DH）  │
   ├── 取指令 · 结果上报                  │
   ├── 读 $MYNET_HOME/var/system.json     │
   └── 命令分派 ───────────────────────→ 桌面/Linux/Windows：exec mynet <子命令>
                                          OpenWrt：lua -e "..."

mynet（配置工具 + 系统能力）
   ├── detector   探测系统能力 → 写 system.json
   ├── metrics    采集系统指标 + VPN/peer 状态（心跳数据源）
   ├── apply      应用配置：驱动原生配置 + OS 路由/防火墙
   ├── refresh-*  驱动配置刷新（gnb node/route/address/keys）
   ├── service    进程生命周期（委托 procd/systemd/launchd）
   ├── driver     驱动管理（列/装/升）
   ├── setup · install · uninstall · update · config
   └── status · monitor · check · nodes · platform
```

**`system.json` 有两个消费者**：`mynet apply`（决定 exec 哪些工具）与
**mynetd 的本机态**（WebUI 显示系统信息时直读文件，不必 fork）。
由 `mynet detector` 生成，带 `generated_at` 与接口指纹。

**边界**：mynetd **不含**探测解析、OS 路由/防火墙写入、驱动配置生成、
进程生命周期、`gnb_ctl -s` / `wg show` 的解析、gopsutil。
这些全在 `mynet`。**mynetd 因此不需要 `thin` 标签来裁——它本身就没有重活**；
桌面构建与 OpenWrt 构建的唯一差别是**是否 embed WebUI**。

**跨平台对称性**：

| | 桌面 / Linux / Windows | OpenWrt |
|---|---|---|
| mynetd | 纯通信 + WebUI embed | 纯通信（无 embed，LuCI 托管 UI） |
| 执行器 | `exec mynet <子命令>` | `lua -e "..."` |
| 系统探测 | `mynet detector` | Lua 已有 |
| 配置/写入 | `mynet apply` | Lua 已有 |
| 进程生命周期 | procd / systemd / launchd（`mynet service` 委托） | procd |

---

## P0 — 测试与安全地基（阻塞一切）

**目标**：让「验证设计」这件事有工具可用；同时消除最危险的安全泄漏。

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| P0-1 | **删框架残留**（更优解，比修签名干净）：`app/Services/ToolRegistry.php` + `tests/Stubs/FakeToolRegistry.php` + `tests/ActionNodeTest.php` + `tests/Conversation/`。它们属被裁掉的 AI/Workflow/Conversation 模块，与 VPN 无关 | `mynet_back` | `php vendor/bin/phpunit` 能启动、不 Fatal |
| P0-2 | **移除硬编码生产密码**；e2e 测试加 `//go:build e2e` 隔离 | `mynet_agent/tests/api_route_test.go:13-14` | `go test ./...` 不含 e2e；代码库无明文密码 |
| P0-3 | 清测试库脏状态（`customers` 表冲突等）；把 `app/Modules/VPN/tests` 纳入 suite | `mynet_back/phpunit.xml:8-16`、`tests/Schema/CoreModule.php:23` | VPN 模块测试可跑 |
| P0-4 | 私钥三处出口收口：`$fillable` 去 private_key、生成接口不回显、**旧 text 链的 config bundle 不装私钥** | `NodeKey.php:24`、`NodeController.php:823`、`ConfigBundleService.php:141` | 私钥不出现于 **API 响应与邮件**。⚠️ `BundleEnvelope` 下发含私钥是**设计如此**（迁移路径另计），不在本条范围 |
| P0-5 | 清**真死代码**：`interfaces`（335 行纯定义）、`checker`（292 行零调用）、`legacy/statusmap`（20 行）；清 `%!d(string=...)` 产物。⚠️ **`detector` 与 `tools/{firewall,routing}` 先别删**——它们是**功能重复的两套探测（读）实现**（`parseIptablesOutput` 两边同名、`detectFirewallType`↔`detectLinuxFirewallType`），需在 **F-1** 收敛成一套后才可删另一套 | `mynet_agent` | grep 无残留；重复实现待 F-1 定夺 |
| P0-6 | 修秒精度唯一键两处定义不一致；`IdPool` 自增、`NodeRouteRequestModel` docblock 矛盾 | back + agent | 定义一致 |

**出口判据**：
- `go test ./...`（排除 e2e）全绿
- `phpunit` 全量能跑，VPN 模块测试有断言
- 代码库 grep 无明文密码、无私钥出口（BundleEnvelope 的下发私钥除外，属设计）
- **此后所有轨道的验收都以「有测试用例」为准**——A-8 是验证设计的载体

---

## 轨道 A — WireGuard 驱动（验证设计的核心）

> **这是本计划的主线**。不实现 wg，「双驱动统一」的设计就无法验证——所有分支都悬空。

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| A-1 | X25519 密钥生成与落盘。⚠️ **编码两层要分清**：`node_keys` 存 **hex**（B-3 统一）；写 wg 配置/`wg set` 时转 **base64 44 字符**（wg 原生格式） | `mynet_agent/internal/utils/`（新） | 生成结果与 `wg genkey` 一致；边界转换在 A-3 的 WriteConfig |
| A-2 | `WireGuardDriver` 实现 `VPNDriver`（只读） | `internal/infrastructure/wg_driver.go` | 解析 `wg show`；peers/summary 正确 |
| A-3 | **驱动适配层（L5）的实现归 `mynet`**：`WireGuardDriver` + `GNBDriver` 都满足 `VPNLifecycleDriver`（写 + 生命周期），但实现落在 **`mynet`**（执行器），mynetd 不含 `internal/infrastructure`。⚠️ **同包不同构建**：OpenWrt 的对应能力在 Lua | `mynet` 内 + `gnb_driver.go:82`（现注释「尚不足以满足」） | 两侧 `WriteConfig`/`Start`/`Stop`/`Reload`/`Status`/`Capabilities()` 齐；`localOps.lifecycle()` 不再 `ErrUnsupported`；wg 的 `Capabilities()` 无 Discovery / 无 Proxy |
| A-4 | `vpn_driver_factory` 接上 `NewWireGuardDriver` | `internal/infrastructure/vpn_driver_factory.go:26-31` | 不再返回「v0.10.0 支持」 |
| A-5 | `BundleEnvelope.WireGuardIntent` 落地（fixture 驱动） | fixtures 已有：`wireguard-minimal.json` / `wireguard-multi-peer.json` | 双端解码一致 |
| A-7 | `nodes.vpn_type` 支持多值。⚠️ **改造面比「改列」大**：现为单值列，back 侧多处代码假设单值（配置生成、密钥分派 `generateGnbKeyPair`/`generateWireGuardKeyPair`、Envelope 的 `driver` 字段）。需先盘出全部假设点 | back migration + 调用点 | 双驱动节点可注册；单驱动行为不回归 |
| A-8 | **双驱动验证测试套件**（六项判据各一组用例） | `mynet_back/tests/` + `mynet_agent/**/*_test.go` | gnb 场景 / wg 场景 / 跨驱动场景三套全绿 |

 > 跨驱动路由验证已移到 **轨道 F-3**——它是 L6 的职责，不是驱动层的。

### 验证目标（用户的核心诉求：验证设计真行得通）

在 **gnb 网 / wg 网 / 跨驱动桥接** 三种场景各跑一遍：

| 验证点 | 判据 |
|---|---|
| **业务对象不分叉** | 同一套 `Friendship`/`Connection`/`Grant`/`KeyExchange` 命令在 gnb 与 wg 场景下行为一致 |
| **驱动差异不泄漏到业务** | 业务对象无 `node.conf`/`AllowedIPs`/`endpoint`/`gnb`/`wireguard` 字段 |
| **Envelope 互斥** | `driver=gnb` 不携带 `wireguard` intent，反之亦然 |
| **节点面 DH 一套逻辑** | gnb 走 edwards→montgomery、wg 直接 DH，**只差一个公钥格式转换** |
| **能力分叉正确** | gnb `Discovery`/`Proxy`=true，wg=false；调用方按 `Capabilities()` 分支 |
| **前端不感知驱动** | UI / 向导无 `vpn_type` 分支 |

---

## 轨道 B — 数据层与 L1/L2/L3 契约

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| B-1 | **补 create migration**（VPN/Monitor/Social 全部表，不再靠旧库）。⚠️ **必须先定 baseline 策略**：生产库已有这些表（旧库导入），直接 `migrate` 会因表已存在而失败。选项：`Schema::hasTable` 守卫 / 标记为「仅新库」/ `migrate:baseline` | `mynet_back/database/migrations/` | 新库可从零建；**生产库跑 `migrate` 不报错** |
| B-2 | `node_keys` 加 `algorithm` / `key_version` / `status` | migration | 三列存在 |
| B-3 | **公钥编码统一为 hex** + `algorithm` 列区分 | `NodeKey.php` / `NodeHeartbeatAuthMiddleware.php` / `BundleEnvelopePublisher.php` | 三种假设收敛为一种 |
| B-4 | `ConnectionGrant` 表 + `KeyExchange` 表（含 algorithm/key_version/status） | migration + model | spec §1.1 对象齐 |
| B-5 | `ConnectionDomainService` | `app/Modules/VPN/Domain/Services/` | request/approve/revoke/updateGrant/revokeGrant |
| B-6 | `KeyExchangeDomainService` | 同上 | registerPublicKey/rotateKey/revokeKey/getPeerKeyPair |
| B-7 | `TransportPlannerDomainService` | 同上 | plan / planAll；输出不含驱动原生语法 |
| B-8 | `Connection.status` 补 `suspended`/`revoked` | model + migration | spec §1.3 状态机完整 |
| B-9 | 三表定论：**全不建**——`route_permissions`（RBAC 删）/ `service_metrics`（死簇删）/ `social_accounts`（幽灵删）；替代：`node_access_rules`+`connection_grants` / `node_monitor_snapshots` / `social_connections` | migration | ☑ 有定论 |

**出口判据**（spec §1.5）：
- 同一套业务命令在 gnb / wg 测试场景下均通过
- 业务对象不含任何驱动术语字段
- PHP Domain Service 接口签名冻结，可作 API 契约基准

---

## 轨道 C — 节点面鉴权与 gateway 改造

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| C-1 | **back 持一对 X25519 密钥**（不轮换）；`mynet setup` 下发 `back_pub` **← 依赖 D-7** | back + `mynet setup` | 节点缓存于 `mynet.conf` |
| C-2 | **节点↔back 请求鉴权改 DH+HMAC**（复用驱动密钥） | `NodeHeartbeatAuthMiddleware` + agent 签名侧 | gnb/wg 同一套；按 `algorithm` 分支 `toMontgomery` |
| C-3 | **gateway 拆掉 Ed25519 握手**（relay/presence/notify 已实现，只需拆 auth） | 删 `internal/gateway/auth.go` 的 `Authenticator`/`Nonce` | gateway **零密码学** |
| C-4 | **gateway 改为连接状态表**；back 验证通过任意一次节点通道请求即置位。⚠️ **需定回传机制**：gateway 怎么知道 back 验证通过？建议 back 在响应头带一个信号（如 `X-Node-Verified: <node_id>`），gateway 解析后置位 | `internal/gateway/registry.go` + back 响应头 | 「已鉴权 = node X」状态置位；伪造 node_id 的连接不被置位 |
| C-5 | **推送 / presence 只按状态路由** | 同上 | 未置位的连接收不到推送 |
| C-6 | **presence 走 Redis pub/sub**（替代内存表）。⚠️ **前置：Redis 部署与配置**（新增基础设施依赖）；须定连接参数来源与故障降级（Redis 挂时是否退回单实例） | `internal/gateway/` + 部署 | 多实例下 presence 与推送都正确；Redis 不可用时行为明确 |
| C-7 | **gateway 加用户面 HTTPS 入口 `/v1/relay`** | `internal/gateway/server.go` | 无身份、鉴权归 back；console 与移动端共用 |
| C-8 | relay 白名单细粒度（放行 `auth/{login,refresh,logout,verify,me}`，删 `/auth`·`/login`·`/logout` 片段） | `internal/relaypolicy/relaypolicy.go` | **注意 `IsAllowedWith` 先查 deny 再查 allow，两动作必须同时做** |

---

## 轨道 D — mynetd 收口与 WebUI

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| D-1 | **本地 token**：首次 `GET /` 种 `HttpOnly`+`SameSite=Strict` cookie | `internal/localapi/` | 浏览器可调本机 API |
| D-2 | **Origin / Host 校验**（防 DNS rebinding） | 同上 | 恶意网页请求被拒 |
| D-3 | **WebUI embed**（`embed.FS` + `http.FileServer`） | `internal/localapi/` + `mynet_ui` 产物 | 装完即用，零配置 |
| D-4 | **UI 合并**（以 `mynet_gui` 的 Quasar 为基线，迁 `mynet_ui` 本机域页面） | 新仓库或 mynet_gui 扩展 | 一份 UI，两个功能域（本机 / 社区） |
| D-5 | **echarts 按需引入** | `mynet_gui` | 那个 1MB chunk 压到 100–200 KB |
| D-6 | **内化 `bin/mynet` 的 5 条命令链** | `conf/command.linux.json` + mynetd handler | `mynet` 降级为可选运维 CLI |
| D-7 | **`mynet` 子命令收口**（配置工具 + 系统能力） | `cmd/` | `setup`/`install`/`uninstall`/`update`/`config` + **`detector`**/**`metrics`**/**`apply`**/**`service`**/**`driver`** + `status`/`monitor`/`refresh-*`/`check`/`nodes`/`platform` |
| D-8 | **去掉强制 root**（console 模式） | `cmd/mynetd/main.go:98-100` | console 模式非 root 可跑 |
| D-9 | **补 `driver` 域**（列驱动 / 安装 / 升级）。与 D-7 **scope 互补**：`upgrade_service` 的 `Component` 已有 `mynet`/`gnb`/`wireguard` 三类，`mynet update` 走 `ComponentMynet`、`driver` 域走 `ComponentGNB/WG`。**共用一个实现，两个入口** | `internal/localapi/` + `internal/application/upgrade_service.go` | `POST /api/v1/driver/install` 与 CLI 都调 `upgrade_service` |
| D-10 | **补 `POST /api/v1/nodes/switch`** | `internal/localapi/` | 首次向导最后一步 |
| D-11 | **mynetd 默认即瘦**——它本身不含探测/写入/驱动/进程管理（见 §1.1）。只保留 **`embed` 构建开关**（是否 embed WebUI） | `internal/` | 桌面构建 = mynetd + embed；OpenWrt 构建 = mynetd 无 embed。**不再需要 `thin`/`noembed` 标签去裁业务** |
| D-12 | **console 模式完整实现** | `cmd/mynetd/main.go` + `internal/daemon/` | 不初始化 `ControlPlane`/`gatewayclient`（无 WS、无节点身份、无 presence、无 commands[]）；平台态经 gateway 的 `/v1/relay`；无驱动密钥；非 root |

---

## 轨道 E — OpenWrt、平台与分发

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| E-1 | **procd 接管 gnb 进程** | `mynet_openwrt/scripts/runtime/rc.mynet`（rc.common → procd） | respawn / reload(SIGHUP) / hotplug 生效 |
| E-2 | `node.conf` 生成改 `--systemd` 或 `daemon off` | Lua `node.lua` / Go config writer | gnb 前台跑，交给 procd |
| E-3 | **`_gnb_peer_heal` 改造判据后再搬** ⚠️ | 原判据 = `proxy_state.json` 存在 + `PROXY_GATEWAYS` 首个 peer VIP 不可达（`heartbeat.sh:61-128`），**完全依赖已下架的 `mynet_proxy`** → 判据须改为 `gnb_ctl -s` 的 PONG 状态 / ping 对端 VPN IP。procd respawn 替代不了它 |
| E-4 | **Lua 职责收窄为执行器**（结果回 mynetd，由 mynetd 经 WS 上报） | `luasrc/model/mynet/system.lua` 的 `_handle_heartbeat_commands` / `_report_command_result` | Lua 不发心跳、不报结果 |
| E-5 | **mynetd 瘦构建落地**（OpenWrt 产物）**← 依赖 E-8 命名统一** | `mynet_agent` + 打包 | `mynet_agent_openwrt_*.ipk` |
| E-6 | ubus 薄桥（可选） | `mynet_openwrt` | `mynet.*` 对象可注册 |
| E-7 | **mynet_desktop 补全**（systemd/launchd、二进制布局、打包） | `mynet_desktop` | 非 OpenWrt 平台可装 |
| E-8 | **命名统一 `mynet_agent_*`** | `conventions.md` + `ReleaseDomainService.php` + 打包脚本 | 三处一致 |
| E-9 | **`apps_index_url` 统一到 `ctl.mynet.club`** | `mynet_agent/internal/tools/updater/updater.go:162` | Go/OpenWrt 一致 |
| E-10 | **文档同步 + 代码改端口** | `control-plane.md` §5.3、`local-api.md`、`conventions.md`；**代码**：`internal/localapi/localapi.go` 的 `DefaultListen` 8720→**8096** | 文档与代码一致 |

---

## 轨道 F — L6 系统网络层（`NetworkBackend`）

> **为什么单独成轨**：`NetworkBackend` **全仓不存在**，但它是 mynet-overview 定的 L6，
> 且「跨驱动互通 = 一条路由」正落在这里。素材已散在三处，需要收敛而非重写。

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| F-1 | **探测移到 `mynet detector`**：收敛 `internal/detector/*` 与 `internal/tools/{firewall,routing}` 两套重复的探测（读）实现为一套，产出 `system.json` | `mynet`（执行器） | 保留一套、删另一套；`mynetd` 不再含这些解析代码 |
| F-2 | **写入移到 `mynet apply`**：OS 路由、防火墙、接口管理（读 `system.json` 决定 exec 什么）。⚠️ 现存代码无 `Apply`/`Write`——需从 shell 脚本（`scripts/*/route-manager.sh`、`firewall-manager.sh`）与 Lua（`ensure_firewall_zone`/`apply_firewall_masq`）迁移或新写 | `mynet`（执行器） | 对 gnb0 与 wg0 **一视同仁**（不感知驱动） |
| F-3 | **跨驱动路由**（原 A-6）：A(gnb)→C(双驱动)→B(wg) 就是 `mynet apply` 写的一条 `ip route` | `mynet apply` | 通；**驱动层不感知对方存在** |
| F-4 | **平台实现**：linux / darwin / windows / openwrt | `mynet` 内的平台分支 | 各平台 `ip route` / `route` / `netsh` / uci 落地 |
| F-5 | **多写者收敛**（spec §1.4）：CLI route tools 不再直接写路由 | `internal/tools/` | 唯一写入者 = `mynet apply` |

---

| F-6 | **`system.json` 机制**：`mynet detector` 生成（带 `generated_at` + 接口指纹）；mynetd 与 `mynet apply` 双消费；**网卡变化时 mynetd 触发重跑** | `$MYNET_HOME/var/system.json` | 指纹不匹配时自动重探测 |
## 2. 依赖与并行性

```
P0 ──┬─────────────────────────────────────────────────────┐
     │                                                     │
     ├─ A 驱动（主线）                                     │
     │    ├─ A-3 需 gnb 侧也补 LifecycleDriver             │
     │    ├─ A-7 依赖 B-2/B-4（表结构）                    │
     │    └─ A-8 验证测试 ← 汇合 A+B+C+F 的结果            │
     ├─ B 数据契约 ── B-3（编码统一）供 C-2 使用           │
     ├─ C 控制面                                          │
     │    ├─ C-1 依赖 D-7（`mynet setup` 下发通道）        │
     │    └─ C-2 依赖 A-1（wg 密钥）+ B-3（编码）          │
     ├─ D 产品面 ── D-1/D-2 先于 D-3；D-12 依赖 C-7       │
     ├─ E 平台面 ── E-8 先于 E-5；E-3/E-4/E-5 依赖 D-11   │
     └─ F L6 网络层                                       │
          ├─ F-1 依赖 P0-5（两套重复实现先别删）                │
          └─ F-3 供 A-8 跨驱动验证使用                    │
                                                     ▼
                                      验收：双驱动设计验证通过
```

**关键路径**：`P0 → A-1..A-3 → A-8 验证`。**A 是主线**；`F` 是跨驱动验证的前置。
**执行器架构（§1.1）是总则**——它决定 mynetd 的体积边界，A/B/C/D/E/F 各轨都要据此划归属。
**可与 A 并行**：B、D 的大部分（D-1..D-7 不依赖 A）、E 的大部分（E-7..E-10 不依赖 A）、F。
**跨轨依赖**（务必按序）：
- `C-1 ← D-7`（`mynet setup` 是下发通道）
- `C-2 ← A-1 + B-3`（wg 密钥 + 编码统一）
- `A-7 ← B-2 + B-4`（表结构）
- `D-12 ← C-7`（console 走 `/v1/relay`）
- `E-5 ← E-8 + D-11`（命名统一 + 瘦构建标签）
- `F-1 ← P0-5`（两套重复的探测实现先别删，等收敛）
- `A-8 ← F-3`（跨驱动验证需要 L6 的通路）
- `E-3` 判据改造后才搬（依赖 proxy 下架的结论）

---

## 3. 计划顺序表（可跟踪）

> **用法**：按序号推进，做完勾掉状态列。任务 ID 是唯一标识（`repo-migration.md` 的阶段引用它）。
> `M0` / `M1` 是两个硬门槛。

| # | 阶段 | 任务 ID | 内容 | 前置 | 状态 |
|---|---|---|---|---|---|
| 1 | **S0 立骨架** | S0-1 | 建 `mynet_agent` / `mynet_web` / `mynet_desktop` / `mynet_back` 四个空 repo | — | ☑ |
| 2 | | S0-2 | 挂载 `mynet_openwrt` / `mynet_ctl` / `mynet_gnb` / `mynet_utc` | — | ☑ |
| 3 | | S0-3 | 各 repo README + 目录骨架（对齐执行器架构与 L1–L7） | S0-1 | ☑ |
| 4 | **S1 地基** | P0-1 | 删框架残留（`ToolRegistry` / `ActionNodeTest` / `Conversation`）**= 搬运时不搬** | — | ☑ |
| 5 | | P0-2 | 移除硬编码生产密码；e2e 加 `//go:build e2e`（= 不搬 api_route_test；phpunit 无密码） | — | ☑ |
| 6 | | P0-3 | 清测试库脏状态；VPN tests 纳入 suite | P0-1 | ☑ |
| 7 | | P0-4 | 私钥三处出口收口 | — | ☑ |
| 8 | | P0-5 | 清真死代码（`interfaces`/`checker`/`legacy`）+ `%!d` 产物（= 不搬） | — | ☑ |
| 9 | | P0-6 | 修秒精度唯一键 / `IdPool` 自增 / `NodeRouteRequestModel` docblock | — | ☑ |
| 10 | | S1-1 | back 按需 module（31→3）+ 删 27 个模块的迁移 | P0-1 | ☑ |
| 11 | | S1-2 | `mynet_agent/shared/` 骨架（config/logger/errors/domain/utils） | S0-3 | ☑ |
| | | | **▶ M0 出口：两套测试全绿 · 无明文密码 · 无私钥出口** | | |
| 12 | **S2 执行器 mynet** | F-1 | 收敛两套重复探测 → `mynet detector` | P0-5 | ☑ |
| 13 | | F-6 | `system.json` 机制（`mynet detector` 产出 + 双端消费 + 指纹重跑） | F-1 | ☑ |
| 14 | | S2-1 | `mynet metrics`（心跳数据源：系统指标 + VPN/peer 状态） | S1-2 | ☑ |
| 15 | | F-2 | 写入移到 `mynet apply`（OS 路由/防火墙，从 shell/Lua 迁） | F-6 | ☑ |
| 16 | | F-4 | 平台实现（linux / darwin / windows / openwrt）—— darwin 路由已并入；windows/openwrt 细化待补 | F-2 | ☐ |
| 17 | | F-5 | 多写者收敛（唯一写入者 = `mynet apply`） | F-2 | ☑ |
| 18 | | D-7 | `mynet` 子命令收口 | S2-1 | ☑ |
| 19 | **S3 纯通信 mynetd** | D-11 | mynetd 默认瘦（只留 `embed` 开关） | S1-2 | ☑ |
| 20 | | D-6 | 内化 `bin/mynet` 的 5 条命令链 | D-7 | ☑ |
| 21 | | D-1 | 本地 token：首次 `GET /` 种 HttpOnly+SameSite=Strict cookie | — | ☑ |
| 22 | | D-2 | Origin / Host 校验（防 DNS rebinding） | D-1 | ☑ |
| 23 | | D-8 | 去掉强制 root（console 模式） | D-11 | ☑ |
| 24 | | S3-1 | mynetd daemon（心跳组装 + WS 上报 + 命令分派 → 执行器）—— 命令链/transport 骨架已通 | S2-1 | ☑ |
| 25 | **S4 gateway** | C-3 | 拆掉 Ed25519 握手 | — | ☑ |
| 26 | | C-4 | 连接状态表；back 验证通过即置位（响应头回传） | C-3 | ☑ |
| 27 | | C-5 | 推送 / presence 只按状态路由 | C-4 | ☑ |
| 28 | | C-8 | relay 白名单细粒度（放行 auth 会话 5 条，删 3 个 fragment） | — | ☑ |
| 29 | | C-7 | gateway 加用户面 HTTPS 入口 `/v1/relay` | C-8 | ☑ |
| 30 | | C-6 | presence 走 Redis pub/sub | C-4 | ☑ |
| 31 | **S5 back 业务层** | B-1 | create migration 全套 + baseline 策略 | S1-1 | ☑ |
| 32 | | B-2 | `node_keys` 加 `algorithm`/`key_version`/`status` | B-1 | ☑ |
| 33 | | B-3 | 公钥编码统一 hex | B-2 | ☑ |
| 34 | | B-4 | `ConnectionGrant` + `KeyExchange` 表 | B-1 | ☑ |
| 35 | | B-8 | `Connection.status` 补 `suspended`/`revoked` | B-1 | ☑ |
| 36 | | B-5 | `ConnectionDomainService` | B-4, B-8 | ☑ |
| 37 | | B-6 | `KeyExchangeDomainService` | B-4 | ☑ |
| 38 | | B-7 | `TransportPlannerDomainService` | B-5 | ☑ |
| 39 | | B-9 | 补缺表（`route_permissions` 等三表有定论） | B-1 | ☑ |
| 40 | **S6 驱动** | A-1 | X25519 密钥生成与落盘 | — | ☑ |
| 41 | | A-2 | `WireGuardDriver` 只读（解析 `wg show`） | A-1 | ☑ |
| 42 | | A-3 | 补足 `VPNLifecycleDriver`（gnb+wg 双侧，实现归 `mynet`） | A-2, F-2 | ☑ |
| 43 | | A-4 | `vpn_driver_factory` 接上 | A-2 | ☑ |
| 44 | | A-5 | `WireGuardIntent` 落地（fixture 驱动） | A-4 | ☑ |
| 45 | | A-7 | `nodes.vpn_type` 多值 | B-2, B-4 | ☑ |
| 46 | | C-1 | back X25519 密钥 + `mynet setup` 下发 | D-7, B-1 | ☑ |
| 47 | | C-2 | 节点↔back 改 DH+HMAC（复用驱动密钥） | A-1, B-3 | ☑ |
| 48 | **S7 验证** | F-3 | 跨驱动路由（A(gnb)→C(双驱动)→B(wg)） | F-4 | ☑ |
| 49 | | A-8 | 双驱动验证测试套件（六项判据，三场景） | A-5, B-7, C-2, F-3 | ☑ |
| | | | **▶ M1 出口：A-8 在 gnb / wg / 跨驱动三场景全绿** | | |
| 50 | **S8 mynet_web** | D-3 | WebUI embed | D-1, D-2 | ☑ |
| 51 | | D-4 | UI 合并（Quasar 基线骨架已立，页面迁移 S8） | D-3 | ☐ |
| 52 | | D-5 | echarts 按需引入 | D-4 | ☐ |
| 53 | | D-9 | `driver` 域（列/装/升，与 CLI 共用 `upgrade_service`） | D-7 | ☐ |
| 54 | | D-10 | `POST /api/v1/nodes/switch` | D-7 | ☑ |
| 55 | **S9 平台** | E-1 | procd 接管 gnb（含 `gnb_es` 孤儿前置验证） | — | ☐ |
| 56 | | E-2 | `node.conf` 改 `--systemd` / `daemon off` | E-1 | ☐ |
| 57 | | E-3 | `_gnb_peer_heal` **改造判据后**搬进 mynetd | E-1 | ☐ |
| 58 | | E-4 | Lua 职责收窄为执行器（结果回 mynetd，经 WS 上报） | E-1 | ☐ |
| 59 | | E-8 | 命名统一 `mynet_agent_*` | — | ☐ |
| 60 | | E-5 | mynetd 构建落地（OpenWrt 产物） | E-8, D-11 | ☐ |
| 61 | | E-6 | ubus 薄桥（可选） | E-5 | ☐ |
| 62 | | E-7 | mynet_desktop 补全 | S3-1 | ☐ |
| 63 | | E-9 | `apps_index_url` 统一到 `ctl.mynet.club` | — | ☐ |
| 64 | | E-10 | 文档同步 + 代码改端口（8720→8096） | — | ☐ |
| | | | **▶ M2 控制面完成 · M3 产品面可用 · M4 平台面收口** | | |

**里程碑对照**：M0 = #4–11 · M1 = #12–49 · M2 = #25–30 + #46–47 · M3 = #50–54 · M4 = #55–64。

## 4. 已知坑的应对（开发前必读）

`docs/mynet-overview.md` 第九节列了 13 条。计划里的对应处置：

| 坑 | 处置轨道 |
|---|---|
| 公钥编码三种假设 | **B-3**（统一 hex + `algorithm` 列） |
| 私钥明文在 back + 外发路径 | **P0-4**（三处出口收口） |
| 心跳 HMAC 用公钥当密钥 | **C-2**（改 DH+HMAC） |
| BundleEnvelope 含明文私钥 | 规范列为迁移路径；P0-4 先收口 API 出口 |
| DNS rebinding | **D-2**（Origin/Host 校验） |
| GNB 非标准 ed25519（三层） | **A-1/A-6**：gnb 侧按 GNB 布局取标量做 DH；编码与布局正交 |
| GNB 非标准掩码静默忽略 | 文档已记；实现侧在 `TransportPlanner` 输出校验加告警 |
| GNB 数据面 XOR 无 MAC | 超出本期范围（属驱动层改进） |
| `IsAllowedWith` 先 deny 后 allow | **C-8**：改白名单必须两动作同时做 |
| gnb conf 的 `%!d(string=...)` | **P0-5**（清理 + 修格式串） |
| 心跳路径与文档不符 | **E-10**（文档同步） |
| gnb_es 孤儿 | **E-1** 前置验证 |
| 文档与代码漂移 | **E-10** |
| `detector` 与 `tools/{firewall,routing}` 重复实现 | **F-1**（收敛成一套后删另一套）；`tools/firewall.go` 的 `parseIptablesOutput` 与 `detector/firewall_linux.go` 同名 |

---

## 5. 分阶段验收（可交付里程碑）

| 里程碑 | 内容 | 出口 |
|---|---|---|
| **M0** | 测试与安全地基 | 两套测试全绿；代码库无明文密码、无私钥出口 |
| **M1** | **双驱动验证通过** | A-1..A-5/A-7/A-8 + **F-1..F-3** + B-2/B-4/B-7；**A-8 测试套件**在 gnb / wg / 跨驱动三场景全绿 |
| **M2** | 控制面改造完成 | C 全部；gateway 零密码学；gnb/wg 同一套 DH |
| **M3** | 产品面可用 | D 全部；浏览器只谈本机、平台态经 relay |
| **M4** | 平台面收口 | E 全部；OpenWrt 跑 mynetd 瘦构建 + procd；命名统一 |

**建议节奏**：M0 → M1 是硬门槛（验证设计）；M2/M3 可并行推进；M4 收尾。

---

## 6. 遗留与后置

| 项 | 处置 |
|---|---|
| **back X25519 密钥轮换** | 明确「后期可加，对架构无影响」——下发通道（`mynet setup`）已存在，届时用双钥过渡 |
| **GNB 数据面加密升级**（XOR → AEAD） | 属 gnb 驱动层改进，超出本期 |
| **`mynet_proxy` 下架** | 按 `control-plane.md` §7.3：deprecated → 等 L3/L5 落地 → 从命令表摘除（3 条 `plugin.proxy.*`） |
| **多节点并行** | 目标并行；短期先做互斥切换（`nodes/switch`），并行作为后续增量 |
| **移动端用户面入口** | 已由 gateway 的 `/v1/relay` 覆盖（C-7） |
