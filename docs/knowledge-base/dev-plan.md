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
| **mynet_api** 数据层 | **~75%** | 模型齐全、全局 ID 主键基本统一；**全部 VPN/Monitor/Social 表无 create migration**（靠旧库） |
| **mynet_api** L1/L2/L3 | **~30%** | `BundleEnvelope` + Publisher + Projector 存在；**三个 DomainService 全缺** |
| **mynet_api** API / 心跳 | **~85% / ~80%** | 清单完整、中间件链清晰；路径漂移 + 公钥编码三假设 |
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
| P0-1 | **删框架残留**（更优解，比修签名干净）：`app/Services/ToolRegistry.php` + `tests/Stubs/FakeToolRegistry.php` + `tests/ActionNodeTest.php` + `tests/Conversation/`。它们属被裁掉的 AI/Workflow/Conversation 模块，与 VPN 无关 | `mynet_api` | `php vendor/bin/phpunit` 能启动、不 Fatal |
| P0-2 | **移除硬编码生产密码**；e2e 测试加 `//go:build e2e` 隔离 | `mynet_agent/tests/api_route_test.go:13-14` | `go test ./...` 不含 e2e；代码库无明文密码 |
| P0-3 | 清测试库脏状态（`customers` 表冲突等）；把 `app/Modules/VPN/tests` 纳入 suite | `mynet_api/phpunit.xml:8-16`、`tests/Schema/CoreModule.php:23` | VPN 模块测试可跑 |
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
| A-8 | **双驱动验证测试套件**（六项判据各一组用例） | `mynet_api/tests/` + `mynet_agent/**/*_test.go` | gnb 场景 / wg 场景 / 跨驱动场景三套全绿 |

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
| B-1 | **补 create migration**（VPN/Monitor/Social 全部表，不再靠旧库）。⚠️ **必须先定 baseline 策略**：生产库已有这些表（旧库导入），直接 `migrate` 会因表已存在而失败。选项：`Schema::hasTable` 守卫 / 标记为「仅新库」/ `migrate:baseline` | `mynet_api/database/migrations/` | 新库可从零建；**生产库跑 `migrate` 不报错** |
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
| D-4 | ~~**UI 合并**（以 `mynet_gui` 的 Quasar 为基线，迁 `mynet_ui` 本机域页面）~~ **已作废（2026-09-24）**：`mynet_web` = 官网/项目主页，**`mynet_gui` 才是用户交互前端**，不合并。见 `mynet-overview.md` §15 | — | — |
| D-5 | **echarts 按需引入** | `mynet_gui` | 那个 1MB chunk 压到 100–200 KB |
| D-6 | **内化 `bin/mynet` 的 5 条命令链** | `conf/command.linux.json` + mynetd handler | `mynet` 降级为可选运维 CLI |
| D-7 | **`mynet` 子命令收口**（配置工具 + 系统能力） | `cmd/` | `setup`/`install`/`uninstall`/`update`/`config` + **`detector`**/**`metrics`**/**`apply`**/**`service`**/**`driver`** + `status`/`monitor`/`refresh-*`/`check`/`nodes`/`platform` |
| D-8 | **去掉强制 root**（**仅 console 模式**） | `cmd/mynetd/main.go` | console 模式非 root 可跑。⚠️ **2026-09-25 澄清适用范围**：本条只覆盖 console 模式（D-12：无 WS/无节点身份/不碰驱动）。**节点模式**（配了 `MYNET_GATEWAYS`）要起 gnb（建 tun）、写内核路由、读 `0600 root:root` 的 `gnb.map`，**必须 root**（用户裁定，见 CPI-63）。此前代码里那条「mynetd 无需 root」的提示是**模式盲**的，把部署错误伪装成了「设计如此」 |
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
| F-6 | **`system.json` 机制**：`mynet detector` 生成（带 `generated_at` + 接口指纹）；mynetd 与 `mynet apply` 双消费；**网卡变化时 mynetd 触发重跑** | `$MYNET_HOME/var/system.json` | 指纹不匹配时自动重探测 |

---

## 轨道 G — console 租户后台（`console.mynet.club`）

> **定性（用户 2026-10-01 裁定）**：租户后台是 **dsplat 框架自带能力**（vendor
> `multi-tenant-saas` 的 `/console` SPA 路由 + menu/dashboard API + Operator/RBAC 门禁，
> 见 `vendor/dsplat/multi-tenant-saas/routes/{web,api}.php`）。**Zone 就是租户**，
> RBAC/租户 CRUD 都是框架的层，back **不新建这些模块**。
> 真正的缺口：此前代码**有前台用户操作的部分，没有租户操作的部分**——
> back 业务接口全部按 `user_id` 作用域（`NodeController.php:86` 列表硬过滤 `user_id`），
> 没有租户作用域那组接口，框架脚手架也不覆盖业务页。
>
> ⚠️ **监控现状**：前台用户面的 `/api/v1/monitor/*` **已归档下线（2026-09-28，
> `VPN/Routes/api.php:92-94`）**，心跳/bundle 迁到节点面 `/api/v1/node/*`（HMAC，机器身份）。
> console 的「节点监控汇总」是**新建只读面**，不是复用旧 monitor。
>
> **硬约束**：console SPA 直连 back（登录/operator 路径在 relay `deniedFragments` 黑名单里），
> **不走 `/v1/relay`**——与 mynetd `-console` 模式（D-12）是两条不相干通路。

| # | 任务 | 落点 | 验收 |
|---|---|---|---|
| G-1 | **vendor 迁移落库**：`operators`/`operator_tenants` + auth module 的 `permissions`/`roles`/`role_permissions`（`multi-tenant-saas-module-auth/Database/migrations/2025_01_01_000004_auth_module.php:133,145,164`）。⚠️ `framework_core` 整包跑会撞 app 已建的 13 张表（`spec/back-baggage-cleanup.md:59`）→ **按缺口补、带 `hasTable`/`hasColumn` 守卫**。零业务代码 | `mynet_api/database` | ☑ 2026-10-01：MySQL `mynet_api_dev` 73 表，五张目标表齐（`operator_tenants` 外键已生效）。⚠️ vendor DDL 是 **MySQL-only**（`MySqlOnlyMigration::skip()` 在非 MySQL 驱动**抛异常**，不是跳过），本地必须用 MySQL，SQLite 跑不了 |
| G-2 | **console SPA 资产落地**：`mynet_api/public/console/` 当前缺（`public/` 只有 `index.php`），构建产物从框架参考仓取（`framework/scrm-platform/public/console/`） | `mynet_api/public/console/` | ☑ 2026-10-01：浏览器登录 → `auth/user` 200 → 工作台出租户信息。⚠️ 这份产物是 **scrm 产品的 console**（品牌「内行私域通」、侧栏走 module-loader），只用来验证通路；back 自己的页面是 G-4 |
| G-3 | **租户作用域 API**（按 `tenant_id` 过滤，供 Operator 用）：① 节点列表+监控汇总（只读聚合）**← 两条都已落 2026-10-01/10-02，见下「G-3 首条竖切片」** ② 路由授权审批 **← 只读审批台已落 2026-10-02，见下；Operator 代批待裁定** ③ 社交关系管理 **← 只读总览已落 2026-10-04，见下「G-3 ③」；应答/撤销不给 Operator**（那条「他会话正在改」的避让提示可解除：`SocialController` 未被动过（Sep 23 mtime），`SocialDomainService` 今日 08:44 有写入但**已随 `5aa8f34` 提交、工作树与 HEAD 一致**；正因它动过，本切片引用的行号在提交后逐条重核过 —— `Invitation.php` 还因本会话 +1 行 import 整体下移，已同步更正）** ④ 服务策略 **← 只读总览已落 2026-10-02，见下；顺带取证 `node_services` 无可用写入方**。**命名已钉死（2026-10-01 用户裁定）：`/api/v1/console/<域>`**——业务段直接叠在框架 console 段下，不用产品名 `scrm`（那是 scrm-platform 自己的段，见本节末「命名与门禁」），也不新开 `tenant` 段 | `app/Modules/*/Controllers/Console/V1/` + `routes/api.php` 的 `v1/console` 组（`auth:sanctum` + `domain.console` + `tenant.identify` + **`tenant.permission:tenant_admin`**） | 四组接口可查可操作本租户资源；跨租户越权有测试挡住；**User 的 Bearer 打进来一律 403**（门禁靠「面声明 + role 参数」，路径与域名都判不出 console 面，见「命名与门禁」） |
| G-4 | **console 业务模块页**：挂进框架 module-loader（框架侧 `resources/js/console/` 只有挂点骨架，无业务页），对着 G-3 做四组页面 | 前端模块（参照 `scrm-platform-front` 范式） | 租户管理员在 console 完成节点/路由/社交/服务的典型管理动作 |
| G-5 | **菜单与权限项注册**：业务权限进框架 `permissions`/菜单表，`tenant_admin` 角色可见 | 迁移/seed 或 G-3 配套注册 | ⚠️ 落点校正（2026-10-01 实测）：侧栏菜单来自**前端 module-loader 的静态路由表**，scrm 产物**根本不调** `/api/v1/console/menu`（该端点本身可用：带 token/会话返回 工作台·成员·积分·三方登录·支付·短信…）。所以 G-5 实际是「前端模块注册（随 G-4 做）+ 权限项入库」，后端 menu API 只是自研 host 的可选面 |
| G-6 | **部署**：`console.mynet.club` nginx server block（现落泛解析，`cross-project-issues.md:113`）+ 证书 SAN 确认（H3） | 生产机 nginx + certbot | 域名单独出 console SPA，不与 api 混 block。**两条 Cookie 会话前置**（2026-10-01 本地实测出来的）：① `SANCTUM_STATEFUL_DOMAINS` 只列 console 域、**不要**列 `my.mynet.*`（api 组挂了 `EnsureFrontendRequestsAreStateful`，命中域的写请求会吃 CSRF 校验，GUI 不带 `X-XSRF-TOKEN` → 全 419；模板已改 `.env.example:67-71`）；② console 与 api 跨域，`config/cors.php:11` 的 `supports_credentials` 现为 **false**，Cookie 会话发不过去 —— 需 true + `CORS_ALLOWED_ORIGINS` 精确列 console 源（不能用 `*`） |

**本地跑通补记（2026-10-01 实测，五项都不是可选的）**

| 需要的接线 | 为什么 |
|---|---|
| 装 `dsplat/multi-tenant-saas-module-operator`（composer VCS `dev-main as 1.99.0`） | `ConsoleAuthController.php:50` `OptionalModule::ensure(Operator::class,'租户后台登录')`——不装登录直接 501。AGENTS.md §1.6 已记该裁定例外 |
| `config/auth.php` 定义 `operator-web` guard + `operators` provider，`config/sanctum.php` 的 `guard` 改 `['web','operator-web']` | console SPA 是 **Cookie 会话模式**（bundle 里无 `Bearer`，只带 `withCredentials`）；`ManagesAuthSessions.php:31` 写死 `Auth::guard('operator-web')`。back 原本没 `config/auth.php`（走框架默认，只有 `web`）→ 登录接口 200 但 `auth/user` 恒 401，前端无限回登录页 |
| `bootstrap/app.php` 给 api 组 prepend `EnsureFrontendRequestsAreStateful`，`.env` 的 `SANCTUM_STATEFUL_DOMAINS` **改成只留 console 域**（本地 `127.0.0.1:8080`） | 会话中间件只对 Origin/Referer 命中 stateful 域的请求启用（`EnsureFrontendRequestsAreStateful.php:73-91` 是 `host[:port]` 全等匹配）。⚠️ 它是**双刃**：命中域的非幂等请求同时被加 CSRF 校验，所以 `my.mynet.*`（GUI，Bearer、不带 `X-XSRF-TOKEN`）**必须留在名单外**，否则 GUI 写请求全 419。实测：console 域内 `login/auth/user/menu` 全 200，`my.mynet.local:5173` 来源的 `POST /api/v1/nodes/quick-create` 仍是 401 而非 419 |
| 本地 MySQL 库除 `php artisan migrate` 外，还要手工执行 `database/sql/*.sql` | `database/sql/` 不在迁移器路径里（全仓无 loader，只有注释引用），而 `token.binding` 中间件**无条件**读 `personal_access_tokens.device_fingerprint`（`DeviceFingerprintMiddleware.php:33-35`）。漏执行 → **任何** Bearer 用户面请求 500（`Unknown column 'device_fingerprint'`）；sqlite 测试库由 `tests/Schema/CoreModule.php` 直接建列，所以**测试全绿、本机 MySQL 全红**，漂移只在真库现形。G-6 部署清单必须含这一步。⚠️ 该文件自称「幂等：重复执行无害」是**错的**：`ADD COLUMN IF NOT EXISTS` 是 MariaDB 语法，MySQL 8.4 直接 1064（本机实测）——重跑要先 `SHOW COLUMNS ... LIKE` 判在再执行 |
| 本地服务用 `php -S 127.0.0.1:8080 -t public server.php`（项目根那份），**不要用 `php artisan serve`** | PHP 内置服务器在请求命中真实目录 `public/console/` 时把 `SCRIPT_NAME` 报成 `/console/<x>`，Symfony 算出 `baseUrl=/console`，Laravel 看到的 path 只剩 `members` → SPA 深链接与整页跳转一律 404（实测报 `The route members could not be found.`）。nginx 下无此问题 |

> `platform:init` **不要跑**：`PlatformTenantSeeder.php:55` 裸 `new SlugService`，而 `domain` 包未装，
> 直接 fatal（框架自己在 `TenantService.php:33` 是用 `OptionalModule::ensure` 拦的，两处不一致，
> 已记 `upstream-dsplat-issues.md`）。本地演示租户改由 `database/seeders/ConsoleDevSeeder.php` 种。
> 另：`operators.operator_id` 无自增（主键由 `IdGenerator` 产出），用 `insertGetId` 会拿到 `0` 撞外键。

**命名与门禁（2026-10-01 用户裁定，G-3 前置）**

- 租户面 = **`/api/v1/console/<域>`**（`console/nodes`、`console/route-requests`、`console/social`、
  `console/services`），业务直接叠在框架 console 段上；**不用 `scrm`**：那是 scrm-platform 自己的
  产品段，抄进 mynet_api 只会在日志和代码里留下「另一个产品」的影子。也不新开 `tenant` 段——
  框架的分面口径本来就是 console。
- 已占段要避开：`console/auth/*`（module-auth `routes/api.php:38`）、`console/menu`、`console/dashboard`。
- ⚠️ **路径不是鉴权判定依据，域名也不是（back 两条都拿不到 console 面）** —— 2026-10-01 实测：
  `IdentifyDomain.php:112-120` 的 `platformPathType` 按 `str_starts_with` 依次匹配
  `/admin` → `/console` → `/api`，而 API 路径是 `/api/v1/console/*`，**先命中 `/api` 就被判成 `api` 面**；
  抬到 `console` 面只有靠域名（`:64-68` 命中 `config('domain.platform_domains.console')`），
  而 `domain.*` 这组配置属**未安装的 module-domain**（vendor 与本仓都没有 `config/domain.php`）→ 恒 null。
  另：back 目前压根没挂 `IdentifyDomain`（`bootstrap/app.php` 的别名只有 `tenant.ensure`；
  `tenant.identify` = `IdentifyTenant`，由 `VpnServiceProvider.php:49-53` 注册）。
- **落地做法（G-3 切片，已跑通）**：面归属是**部署期决定**，框架无从知晓本项目哪条路由属于哪个后台，
  所以由路由组自己声明 —— 新增 `app/Http/Middleware/IdentifyConsoleDomain.php`
  （别名 `domain.console`，只 `TenantContext::setDomainType('console')`），
  与框架 `tenant.permission`（= `CheckPermission`）成对挂在 `v1/console` 组上。
  **两条必须同时出现**：`domainType` 为 null 时 `CheckPermission.php:33-38` 走 default 分支**直接放行**（fail-open）。
- 门禁口径：`console` 面分支 `checkConsoleAccess`（`:56-97`）要求 Operator 在 `operator_tenants`
  里属于当前租户且 `role_id` 指向 `name='tenant_admin'` 的角色行；**User 无条件 403**（`:96`，
  「开放注册后的业务用户原则上不允许进 console」）。role 参数（`:tenant_admin`）照挂——
  它不改变 console 分支的判定，但保留「哪一级 admin 能进」的表达位，且与将来可能出现的
  `api` 面复用同一写法。用户面另有 `checkTenantAccess`（`:102-146`），那里 User 靠 `tenant_users` 放行、
  且 `tenantRoleName` 恒 null（User 不拥有角色）。
- ⚠️ **不得把 `domain.console` 挂到整个 api 组**：那会把用户面（`/api/v1/nodes` 等）一起抬成 console 面，
  普通 User 全部 403。实施后跑全量回归确认用户面零变化（`ConsoleNodeAccessTest::test_user_face_still_scopes_to_owner`）。
- 裁定全文见 `spec/unified-platform-architecture.md`（`tenant.permission` 对 back 自有路由解禁；
  `rbac.permission` / `mcp.auth` 仍禁）。

**G-3 首条竖切片（2026-10-01 已落，只读一条，用来验闸门成色）**

| 落点 | 内容 |
|---|---|
| `app/Http/Middleware/IdentifyConsoleDomain.php` | 新中间件，把所在路由组声明为 console 面（理由见上） |
| `bootstrap/app.php` 别名 | `domain.console` + `tenant.permission`（后者自本日起对 back 自有的 console 面路由解禁） |
| `routes/api.php` 的 `v1/console` 组 | 中间件补为 `auth:sanctum` + `throttle:api` + `domain.console` + `tenant.identify` + `tenant.permission:tenant_admin`；`menu`/`dashboard` 一并纳入门禁（此前谁带 token 都能 200） |
| `app/Modules/VPN/Controllers/Console/V1/NodeController.php` | `GET /api/v1/console/nodes`：租户作用域只读列表。隔离逻辑不复制，仍走 `NodeDomainService::getNodeList()`（`tenant_id` 过滤 + 只在 filters 带 `user_id` 时才追加用户过滤，`:57-72`）——这里**不传** `user_id`；`tenant_id` 只取 `TenantContext::getId()`，**不吃请求参数** |
| `app/Modules/VPN/tests/Feature/Console/ConsoleNodeAccessTest.php` + `tests/Schema/OperatorModule.php` | 7 条：租户管理员看全租户、跨租户只看自己、query 塞 `tenant_id` 不能扩权、同租户普通 User 403、非 `tenant_admin` 的 Operator 403、未鉴权 401、**用户面仍按 owner 收敛**。全量 `php artisan test` 259 通过 |
| 真机（MySQL + `php -S`）复核 | Operator 会话 `POST console/auth/login` → `GET console/nodes` = **200**（带出演示节点与 `meta.tenant_id`）；成员 Bearer + `X-Tenant-ID` 打同一路径 = **403 `仅团队管理员可以访问管理后台`**；同 Bearer 打 `/api/v1/nodes` = **200** |

**G-3 ① 后半条：监控汇总（2026-10-02 已落，仍只读）**

| 落点 | 内容 |
|---|---|
| `routes/api.php` 的 `v1/console` 组 | 新增 `GET /api/v1/console/monitor/overview`。**同组即同门禁**——闸门是组级中间件，新接口挂在里面就自动继承 `domain.console` + `tenant.permission:tenant_admin`，不需要再来一套 |
| `app/Modules/VPN/Controllers/Console/V1/MonitorController.php` | 汇总 = 按 `node_status` 计数 + 每节点**最近一条** `node_monitor_snapshots`。`?status=` 过滤时**列表与汇总同口径**（各算一遍会出「总数 1、从未报到 1」这种自相矛盾的页面） |
| `tests/Concerns/BuildsConsoleFixtures.php` | console 测试的 fixture 地基（两租户 × 多成员 × Operator/roles）从 `ConsoleNodeAccessTest` 抽出，与 `tests/Schema/*` 同级。抽的理由只有一条：每条竖切片都要证明同一件事，复制必然各自漂（漏一处 `tenant_users.is_active`，归属校验就白测了） |
| `ConsoleMonitorOverviewTest`（6 条）+ 原 `ConsoleNodeAccessTest`（7 条） | 全量 `php artisan test` **281 通过**。断的是**值**不是状态码：新旧两条快照并排种，取错那条照样 200 |
| 真机（MySQL）复核 | Operator 会话 → **200**，`summary` 给出 `nodes_total=1 / online=0 / enabled_offline=1 / never_reported=1 / peers_connected_sum=2 / rx=111 / tx=222`，`nodes[0].snapshot` 带出 `uptime_seconds=3600`；成员 Bearer + `X-Tenant-ID` → **403**（与 `console/nodes` 同一文案）；无凭证 → **401**；同 Bearer 打 `/api/v1/nodes` → **200** |

> 监控汇总的三个**口径约束**（都是读代码定下来的，不是这里发明的）：
> · **在线 = `node_status === STATUS_ONLINE`**，由 `NodeMonitorService::syncNodeStatus()`（`:245-300`）
>   从心跳反推：mynetd 在跑 → ONLINE，停了 → 退回 ENABLED，`DISABLED/UNCONFIGURED` 尊重人工设置不改。
>   这里**不加**「N 秒没心跳算掉线」的阈值——back 全仓没有这个窗口定义，凭空造一个必与 mynetd
>   实际上报节奏漂；只把 `last_seen` 是否为空作为「有没有报过到」的事实摊给前端。
> · **`node_monitor_snapshots` 没有 `tenant_id` 列** → 租户收敛只能走「先按 `tenant_id` 取节点、
>   再用这批 `node_id` 取快照」。直接对快照表聚合就是跨租户串数，而且接口照样 200。
>   取「最近一条」用 `MAX(snapshot_at)` 而非 `MAX(snapshot_id)`：`HasGlobalId` 明写 ID **完全无序**。
> · **不聚合 `nodes.node_traffic`**：全仓只有读它回显的地方（`NodeDomainService.php:408`），
>   **没有任何地方写它**，是旧库残留列，拿它当租户流量指标会恒 0 或恒旧数。
> 另：`peers_connected_sum` 是「各节点视角的 peer 数之和」，链路两端各计一次，**不是**租户内链路条数。

> 两处断言容易测出**假阳性**，已在用例里注明：① 只断 403 不够，`missing_tenant` 分支也返 403，
> 必须断文案；② User 那条必须显式带 `X-Tenant-ID`，否则根本没认出租户。
> 另注意响应形状两面不一致是**存量事实**：用户面把 domain service 的整个分页数组塞进 `nodes`
> （`NodeController.php:97-105` → 列表在 `data.nodes.data`），console 面给 `data.nodes`（列表）+ `data.meta`（分页）。
> G-4 前端按后者写；用户面那形状不改（有现网客户端在读）。


**G-3 ② 路由授权审批（2026-10-02 已落，只读审批台）**

| 落点 | 内容 |
|---|---|
| `routes/api.php` 的 `v1/console` 组 | `GET /api/v1/console/route-requests`，同组同门禁（组级中间件自动继承，不必再来一套） |
| `Console/V1/RouteRequestController.php` | 列表（`source_*` / `target_*` 摊平标签，沿用用户面 `formatRouteRequest()` 命名）+ 全租户状态汇总 + 分页（`per_page` 上限 100）。**不提供 accept/reject**：`acceptRouteRequest()` 硬校验 `target_user_id === 当前用户`（`RouteRequestDomainService.php:322-325`），这条不是权限装饰而是**被申请方的同意语义本身**；Operator 代批要另写「以成员身份应答」的域入口并配审计 → **待用户裁定** |
| 口径一：`status='expired'` 是**死值** | 全仓无人写它（无命令类、`routes/console.php` 只有 inspire、`bootstrap/app.php` 无 `withSchedule()`），而 `expired_at`（建单时 `now()->addDays(7)`）也没有任何读方。**2026-10-02 已在模型层修掉，见下「修雷」行**；接口侧只把它摊开：`is_overdue` 用模型的 `isAwaitingResponse()` 反面、`pending_overdue` 用 `expiredPending()` scope，两处都不在控制器重写时间比较 |
| 口径二：汇总**不随 `?status=` 归零** | 与监控汇总**相反**，是刻意的：审批台的 pending 数回答「还有多少要处理」，跟着筛选归零会读成假平安 |
| `ConsoleRouteRequestTest`（11 条） | 全量 `php vendor/bin/phpunit` **292 通过**（原 281）。其中一条是**变异测出来的**：删掉控制器里那句显式 `where('tenant_id')`，前 10 条**全绿**（本表模型带框架 `BelongsToTenant`，平时是 TenantScope 在挡，删了等于没测）。补的 `test_tenant_filter_survives_framework_unscoped_bypass` 用 `TenantScope::allowUnscoped()` 把框架豁免整条打开（队列/CLI/系统回调的正道，也是标记泄漏态），此时只剩显式 where —— 删它立刻红（多出别租户一行）。那句 where 在豁免场景下是唯一防线，所以必须留 |
| 真机（MySQL，`sql_mode` 含 `ONLY_FULL_GROUP_BY`） | 聚合与排序**分成两条查询**（`base()` 不带 `orderBy`，`query()` 才带）：合一条会 1055（排序列不在 GROUP BY），而 sqlite 测试库不报 —— 又一处「测试全绿、真库全红」。Operator → **200**：`summary = {total:3, pending:2, pending_overdue:1, accepted:1, rejected:0, expired:0}`，平台租户那 1 条**没进列表**；`?status=pending` → 列表 2 条而 `summary.accepted` 仍 1；`?status=bogus` → **422**；成员 Bearer + `X-Tenant-ID` → **403**（文案同 `console/nodes`），同 token 打用户面 `/api/v1/route/requests` → **200**；无凭证 → **401** |

**顺带修掉的雷：路由申请「过期」改为读时判时间（2026-10-02，mynet_api `bf17e3b`）**

上一条只说了「接口摊开 `is_overdue`」，但雷在域里，不在接口上。修完的形状：

| 落点 | 内容 |
|---|---|
| 判据收口 `NodeRouteRequestModel` | 新增 `TTL_DAYS`（建单与判过期共用一个数）、`isAwaitingResponse()`、`awaitingResponse()` / `expiredPending()` 两个 scope，并把 `isExpired()` 从「只比 status」改成「status=pending **且** `expired_at` 已过」。`status` 回到能兑现的语义——只记人工应答态；过期是**时间事实**，不改写 status |
| 为什么不补定时任务写 `status='expired'` | 扫描要等一轮，**窗口内照样能批**，还把部署依赖（schedule/queue）压进一条业务规则。读时判时间没有这两种代价 |
| 四路调用点（各错一次，故各改一处） | ① `acceptRouteRequest()` 的闸门（原恒 false）；② `checkRouteRequestReentry()` 第 1、2 步 —— 旧按 `status='pending'` 挡，**一条死申请永久挡住同方向与反方向的再申请**；③ `getUserRouteRequests()` 的「待我处理」/「我发出的」**两条列表**（各写一遍 where，缺一条只错一半）；④ `TipsSuggestionsService` 首页「你有 N 个待处理的连接请求」（原来是裸 `DB::table`，改成走模型以便复用 scope） |
| `expired_at` 为 NULL = **不过期** | 存量行没这值；新语义不能反过来把历史数据判成过期（这条最容易在「顺手加个 `whereNotNull`」时写反） |
| 用例位置：`tests/Unit/Domain/RouteRequestExpiryTest.php`（9 条） | ⚠️ **不是随手放这的**：`phpunit.xml` 的 Default/Unit 只收 `app/Modules/VPN/tests/{Unit,Feature}` 与 `tests/Unit/Domain`。最初写在 `app/Modules/VPN/tests/Domain/Services/` 下 —— **全套 292 绿、一条都没跑**（`--filter` 报 `No tests executed`）。新增域测试认准这两个可发现路径 |
| 变异验证（每条都单独红过） | `isExpired()` 退回只比 status → 2 红（含「逾期的必须被拦下」）；删重入第 1 步的 `awaitingResponse()` → 同方向红；删第 2 步 → 反方向红；删 :476（待我处理）与删 :488（我发出的）分别红；TipsSuggestions 退回裸 `status='pending'` → 「逾期的不该再催我处理」红。全套 **301 绿**，pint 5 文件 PASS |
| 真库（MySQL 8.4）核对 | `total=4 / pending=3 / awaiting=2 / expiredPending=1`（两 scope 互斥且并集 = 全部 pending），`status='expired'` 存量 **0 行** —— 枚举值确实无人写。另把审批台依赖的两条易撞 `ONLY_FULL_GROUP_BY` 的形状（`groupBy` 聚合、`paginate` + `with`）在真库上过了一遍 |
| 未纳入本次 | 是否把存量 `status='pending'` 且逾期的行**回填/清扫**成 `expired`：现在读时判定已让功能正确，写回只是让库里的枚举值变好看 —— **待裁定**（与「Operator 代批」一并） |

**G-3 ④ 服务策略（2026-10-02 已落，只读总览，mynet_api `b2ed6d4`）**

| 落点 | 内容 |
|---|---|
| `routes/api.php` 的 `v1/console` 组 | `GET /api/v1/console/services`，同组同门禁。**不提供 create/update/delete/share**：写路径要审计，且服务归属是成员本人 |
| `Console/V1/ServiceController.php` | 列表（`owner` 摊成员身份、`endpoint` 摊地址端口、`bound_node_count` / `shared_with_count` 走真关联）+ 全租户汇总 + 分页（`per_page` 上限 100，同 ②）。顺序 = `service_priority` 升序 + `service_id` 唯一次级键（用户面按 priority+名字，分页必须有唯一收尾键，否则页边界会跳动） |
| 口径一：`user_id = 0` 是**系统/公共服务哨兵**，不是「无归属」 | 用户面 `/services/public` 就是 `where('user_id', 0)`（`TenantScopedServiceService.php:31-33`）。console 侧因此给 `owner = null` + `is_system_service = true`，并分档计 `system` / `member`。当成普通成员处理会（a）让页面点进一个不存在的成员，（b）成员计数多 1 |
| 口径二：**不可信列不摊成指标** | `service_current_connections` **不输出** —— 全仓只有 `?? 0` 的读、无任何写方，恒 0，摊出来会被 G-4 当活跃连接画进图。`service_approvals` **不给「待审批数」** —— 见下行。`service_bind_mode` 摊真值但注明「无任何查询读它」（零命中 `where('service_bind_mode')`） |
| 口径三：服务**审批流是死的** | `POST services/request-approval/{id}` 直接返 **410**（`TenantServiceController.php:363-367`，「暂时关闭普通用户 public/open」）、`ServiceApprovalService` 全仓**零引用**、其转调的 `ServiceApprovalDomainService` 是空 stub —— 给这张死表配计数等于造一个不存在的流程。用户面 create/update 还一律硬写 `service_is_public = 0`（`:186,272`），所以该旗标只对 bundle 下发有意义，不是后台可拨的策略 |
| ⚠️ `node_services` **没有可用写入方**（新发现，待裁定） | `bound_node_count` 现网几乎必然恒 0：唯一写路径 `PUT /nodes/{id}/network-config` 在 `Api\V1\NodeController.php:1463,1562` 把**应用服务类** `Services\NodeService` 当 Eloquent 模型用（`NodeService::where(...)` / `::addServiceToNode(...)`，两个方法都不存在），抛的是 `Error` 不是 `Exception`，故其下 `catch (\Exception)` 接不住 → 该接口是 **500** 而非优雅降级。真实的「哪些节点提供该 index 服务」实际从 `services` 直接解析（`BundleEnvelopePublisher::resolveIndexServers()` `:823-850`）。**本接口只摊这个事实，不在 ④ 里顺手修** |
| `ConsoleServicePolicyTest`（14 条） | 全套 **315 绿**。逐条变异：删显式 `where('tenant_id')` → 仅 `allowUnscoped` 那条红（6 vs 5，同 ② 的两层隔离结论）；汇总按 `status=0` 数（NULL 落空）→ 2 红；筛选只认 `status=0` → 1 红（`service_status=NULL` 的存量行两边都不出现）；少校验 `owner` → 422 那条红（三个参数各测一条：静默返空列表与「确实没有服务」长得一模一样）；把 `current_connections` 摊出来 → 死列那条红；`user_id=0` 不当系统服务 → owner 那条红 |
| 真机（MySQL 8.4，`ONLY_FULL_GROUP_BY`） | 临时凭证打真服务：Operator → **200**，`summary = {total:2, active:1, disabled:1, system:1, member:1, shared:1, members_with_services:1}`（`disabled` 那条正是 `service_status=NULL` 的存量形状），`bound_node_count` / `shared_with_count` 各 1；`?status=`/`?owner=system`/`?type=music` 正确收窄而 `summary.total` 不动；`?status=bogus`、`?owner=bogus` → **422**；无凭证 → **401**；同租户成员 Bearer → **403**。验证用的 4 行 fixture 与 2 个临时 token 已删净（`services` 回到 0 行） |

**G-3 ③：社交关系与邀请总览（2026-10-04 已落，只读，G-3 四条至此齐）**

| 落点 | 内容 |
|---|---|
| `routes/api.php` 的 `v1/console` 组 | `GET /api/v1/console/social/friends` + `GET /api/v1/console/social/invitations`，同组同门禁。**不提供 accept/reject/remove/revoke**：好友应答是**当事人自己的同意语义**（同 ② 不给 Operator 代批），撤销别人的人际关系还要审计 |
| `Console/V1/SocialController.php` | 两张表各一个列表 + 全租户汇总 + 分页（`per_page` 上限 100，同 ②④）。形状仍是 `data.<x>` + `data.meta`，不是用户面的 `data.nodes.data` |
| 口径一：**隔离只剩一层** | `Friendship`/`FriendRequest`/`Invitation` 都**没有**框架 `BelongsToTenant`（只有 `HasGlobalId`）→ **没有 TenantScope 兜底**，控制器里那句显式 `where('tenant_id')` 是唯一一层。与 ②④ 不同：那里删掉显式过滤还有 scope 挡着（要开 `allowUnscoped()` 才测得出来），这里删它直接红，所以不需要那条额外用例 |
| 口径二：**列表是有向条目，不去重成「对」** | 接受好友时双向各 `create` 一次（`SocialDomainService.php:209,216`，`removeFriend` 也成对删 `:348,353`）。去重要嘛靠 `user_id < friend_user_id` 规范方向（库里躺着单向残留时那条边**整条消失**、接口静默少数据），要嘛靠 `LEAST/GREATEST`（MySQL 有 `LEAST`、sqlite 只有标量 `MIN(a,b)`，无共同拼法，而判据必须能在真库上验）。摊全的有向行按对合并是画布侧的事 |
| 口径三：`tenant_id` = **关系发生地**，不是「成员当前归属」 | 写入方取发起人的 `users.tenant_id`（`SocialDomainService.php:83,150,161,169,212,219,228`），而 `switchTenant` 只改 `users.tenant_id` 一列、不回写历史社交行（`UserTenantController.php:70-71`）→ 成员换了网络区，**旧关系仍留在旧区**。既有写入口径，本接口不改只摊出来 |
| 口径四：档位与模型**同源**，完整码不外发 | 过期/用满的判定一律回读 `Invitation::isValid()`（`:82-100`）/`scopeNotExpired()`（`:147-151`），控制器不重写时间比较；`expires_at IS NULL` = **不过期**（判反会把历史邀请码全打成过期）；四档互斥且优先级 `disabled > expired > used_up > available`（过期又用满算 expired，与 `isValid` 的闸序一致），测试断 `available+expired+used_up+disabled = total`。响应**只有 `code_tail`（后 4 位）**：完整码是「加入本网络区」的凭据，而 console 操作者不是这张码的归属者，back 也没有给 Operator 代查完整码的端点 |
| ⚠️ 修掉的潜伏 bug：**社交三模型引用 `User::class` 却没 import** | `Friendship`/`FriendRequest`/`Invitation` 的 `belongsTo(User::class, …)` 解析到**不存在的** `App\Modules\Social\Models\User` → 任何走关联的调用直接 `Error: Class ... not found`（实测：console 侧 `with(['user','friend'])` 一跑就炸，14 条用例 11 红）。全仓此前**无人**调用过这三张表的关联，所以一直没现形。已补 `use App\Models\User;`（与 `VPN/Models/Route.php:6` 同一口径）。`SocialConnection` **不动**：它和 `friendships` 一样缺 import，但表和模型根本对不上（表是 `user_id/target_user_id/type/status/invitation_code`，模型 fillable 是 `platform/platform_user_id/access_token/is_active`），唯一调用方 `SocialDomainService::createSocialConnection()` 全仓零引用 —— 属于「死代码」不是「漏 import」，另入决策包 |
| ⚠️ 记下不改：这三张表的模型**没把主键放进 `$fillable`**（`Service` 放了 `service_id`） | 于是 `create(['invitation_id' => …])` 被质量赋值**静默丢掉**，`HasGlobalId` 现生成随机 16 位 id。测试要可断言的固定 id，只能在 fixture 里直接写属性（`store()` 助手），**不改生产模型口径**：生产走自动生成是对的，让调用方塞 id 反而是扩大面 |
| `ConsoleSocialRelationTest`（14 条） | 全套 **347 绿**（基线 333 + 本切片 14）。逐条变异：删显式 `where('tenant_id')` → 跨租户那条直接红；档位判据改成 `! lessThan` → NULL 过期那条红；`user_id` 筛选写成 `orWhere` 双侧 → 只看自己那侧那条红（反方向行的备注是别人的视角）；完整码摊出来 → 整份响应搜索那条红。**自己写的控制器也被测出一条**：`if ($userId = $request->query('user_id'))` 里 `'0'` 是 falsy，`?user_id=0` 既跳过校验又跳过过滤 → 回 200 全量，改用 `filled()` 判存在（`status` 同口径） |
| 真机（MySQL 8.4，`ONLY_FULL_GROUP_BY`，社交三表 0 行） | 临时凭证打真服务：Operator → **200**，`summary`/`meta.tenant_id=7657309242713752` 形状全对；`?status=` 四档 + `?user_id=` 各自 200（`selectRaw('status, COUNT(*)')`+`groupBy`、`whereRaw('used_count >= max_uses')`、`paginate`+`with` 三处在真库过了一遍 —— 1055/1054 类错误在解析期就报，空表同样挡得住）；`?status=bogus`、`?user_id=abc`、**`?user_id=0`**、`?user_id=-3` → **422**；无凭证 → **401**；同租户成员 Bearer + `X-Tenant-ID` → **403**（文案同 `console/nodes`），同 token 打用户面 `/api/v1/social/friends` → **200**。空表不会真跑 `with()` 的关联查询，故另种 **1 行**临时 friendship 验证 eager-load：`owner`/`friend` 两个标签都按 `user_id` 正确带出邮箱、`members_with_friends=2`，并顺手证实真库 `friendships` 列只有 `friendship_id,user_id,friend_user_id,tenant_id,alias,remarks`（**没有 `created_at`**，与迁移文件一致 → 不输出建立时间是对的）。验证用的 1 行与 2 个临时 token 已删净（三张表回 0 行，tokens 回 18 行） |

**框架侧问题与待裁定（2026-10-04 提出；用户要求「框架有问题单独提出来」，以下每条都带实测出处，back 未自行拍板）**

| # | 事项 | 实测证据 | 要谁定 / 定什么 |
|---|---|---|---|
| F-1 | **`rbac.permission` 96 条框架路由在 back 不可调用（命中即 500）** —— 但**这不是「要不要做 RBAC」的裁定，是「少注册了一个别名」**，且 back 一行就能补 | `bootstrap/app.php:69-73` 只 alias 了 `tenant.ensure`/`tenant.permission`/`domain.console`，注释明文「`rbac.permission` / `mcp.auth` —— 仍然不注册」。`route:list` 实测 96 条路由的别名串保持未解析，分布：`api/v1/admin` 55、`api/v1/tenants` 17、`api/v1/tenant` 10、`api/v1/operators` 9、`api/v1/feature-flags` 5，共 22 个权限名。**本会话纠正上一条的口误**：原判「注册它 = fail-open」是把别名映射到了 `CheckPermission`（那个类只比角色名、没有权限表可查）。框架其实自带专用实现类 **`CheckRbacPermission`**（vendor auth 模块 `Http/Middleware/CheckRbacPermission.php:17-32`），它转 `RbacService::check()`：无鉴权主体 false、**非 Operator（即 User）false**、`scope!=platform` 且无租户上下文 false（`RbacService.php:32-70`、`:78-101` 注释明写「全域 fail-closed」），最后才 `role_permissions ⋈ permissions` 取名（`:104-118`）。**已在本地 `mynet_api_dev` 实测**（临时补别名，验完已还原，`git diff` 空）：<br>· 补前 `GET /api/v1/tenant/invite-codes` → **500 `Target class [rbac.permission] does not exist.`**<br>· 补后同一条 → **200**，`POST` 建码 → **201**；`route:list` 里 96 条全部解析成 `CheckRbacPermission:<权限名>`<br>· 越负面：同一 tenant-scoped Operator 打 `GET /api/v1/admin/auth/identity/merge-revocations`（闸 `platform.rbac.manage`）→ **403**，body 只回 `{permission: "platform.rbac.manage"}` —— admin 面照旧关着<br>· 跨租户：`X-Tenant-ID` 指向该 Operator 未关联的租户时，框架 `IdentifyTenant.php:225-232` **校验 `operator_tenants` 后忽略该头**，实测仍只返回自己租户的码 | **待点头的配置就一行**：`bootstrap/app.php` 别名表加 `'rbac.permission' => CheckRbacPermission::class`（+ import）。back 自有 15 条 `tenant.permission` 路由不受影响（不同别名）。补之前 F-3 那 5 条框架邀请码路由一律 500，界面接不了线<br>⚠️ 两条已知边界，别当它不存在：① `RbacService` 把角色权限集缓存 1h（`CACHE_PREFIX`/`CACHE_TTL` `:24-27`），改授权要配套 forget；② `domainType` 在 back 仍恒 null（`IdentifyDomain` 没挂），效果是 `platform.*` 权限名**处处被拒**（`:56-64` 的分域过滤），即 fail-closed 方向 —— 平台 Operator 走 `scope==='platform'` 短路（`:46-48`）不受此影响 |
| F-2 | **菜单确实有两套口径，但不是「打架」—— 一套没人消费，另一套不可注入** | 服务端那套**是活的**：`GET /api/v1/console/menu` → `MultiTenantSaas\Http\Controllers\ConsoleMenuController@index`（core 包 `src/Http/Controllers/ConsoleMenuController.php:71-100`），内容是**硬编码 `$defaultMenu` 8 条**（工作台/成员管理/积分管理/第三方登录/支付配置/短信配置/API Token/邮件-认证-注册）再 `array_merge` 上 `config('tenancy.console_menu')`。而 back 的 `config/tenancy.php` **压根没有 `console_menu` 这个键**（全 vendor grep 只在控制器里出现，即取默认 `[]`）—— 更关键：**SPA 从不调它**（`grep -rn "console/menu" vendor/dsplat/multi-tenant-saas/resources/js/` 零命中）。SPA 侧真正渲染的是 `getConsoleNavSections()`（`module-loader.ts:490-`）：条目**标签**取 `route.meta.title`（`:502`、`:515`，← **这条 back 可控**，走自己的 `routes.ts`），**分组标题**取私有常量 `MODULE_LABELS[moduleName]`（`:275`，表里已写死 40+ 项含 scrm 下游模块名，**没有 `VPN`**），`:532-533` 的兜底只做「去连字符 + 首字母大写」⇒ 我们 `app/Modules/VPN` 的组头就是字面 **`VPN`**。分组键本身是可写的：`loadModuleRoutes():332-336` 只在 `meta.module` **缺失时**才注入目录名，所以 back 能被指到某个既有中文组名下，但那要借用别人的语义（如 `Infrastructure`⇒「基础设施」），属滥用而非配置 | G-5 三条路，back 不擅自选：<br>① **建议框架**导出 `setModuleLabel(module, label)` 或直接把 `MODULE_LABELS` 变成可注入 map —— back 侧零 hack，一处声明（改动最小、语义最正）<br>② 框架不动：back 的 `routes.ts` 全用 `meta.title` 摊平到**单个组**，接受组头显示 `VPN`（英文界面 Network 也救不了组头，因为它取的是模块名不是标题）<br>③ 迁到服务端口径：back 补 `config('tenancy.console_menu')` 且要求 SPA 改调 `/console/menu` —— **框架侧要改 SPA**，且得能覆盖/剔除那 8 条 dsplat 自带项（积分/支付/短信这些 mynet 没有）<br>现状：#26 的页面标签不受阻，只有组头等裁定 |
| F-3 | **裁定已下：用框架的 invite，console 界面可接线** —— 但取证后要说清「框架那套管不到的部分」 | 用户 2026-10-04 裁定「使用框架的 invite，界面可以接线」；随 F-5 一并执行。<br>**框架 invite-codes 是什么**（core + auth 拆包，5 条 `api/v1/tenant/invite-codes*` 闸 `rbac.permission:invite_code.manage`，另有 `api/v1/app/invite-code`(+`/usages`) 走 `VerifyOperatorTenant`、`api/v1/auth/invite-code/verify` 公开校验）：**注册门禁 + 老带新归因**。两表 `invite_codes` / `invite_code_usages`，三态 `registration.mode`（open/closed/invite_only，默认 open），核销**只发生在建号路径**（邮箱 `UserAuthController.php:127,149`、短信 `:291,323`、OAuth `GatesOAuthRegistration.php:38,53`），`consume()` 事务 + `lockForUpdate` + 唯一键幂等 + 自邀拦截 —— **没有任何端点能让已注册成员核销一张码**，也不写 memberships（成员行由注册本身写）。决策文 `docs/decisions/2026-10-01-invite-code-registration-infrastructure.md` 且明文**禁止并行邀请码表**<br>**back `invitations` 表管什么，框架覆盖不了的列级差**：`user_id` NOT NULL 的**成员归属**（框架 `owner_user_id` 可空）、**全局唯一**码（框架是 `(tenant_id, code)` 租户内唯一）、`description`（框架只有 `metadata` json）、`invitation_status` tinyint（框架字符串 enum）、**软删除**（框架只作废）、以及最重的「**入区动作**」：`SocialDomainService.php:1175` 拿 `invitation.tenant_id` 当目标租户并 upsert `TenantUser`（`:1198-1213`）——框架零对应路径<br>⇒ 结论：**框架 invite 今天替换不掉 back 的 `invitations`**，两套只能并存且语义不同。用词上框架已给官方口径 —— 权限位 `invite_code.manage` 的 `display_name` 实测就是「**管理注册邀请码**」（`permissions` 表 permission_id 6805018806189879，group `invite_code`），所以「注册邀请码」归框架，back 那套成员发出的入区凭据需要另一个词 | **back 侧待办**（不等 F-1 之外的裁定）：① console 接 `api/v1/tenant/invite-codes`（生成/列表/统计/作废）—— **前提是 F-1 那一行别名配置**，否则 5 条全 500；② back 的 `console/social/invitations` 与用户面 `/api/v1/social/invitation*` 保留，界面词换成不含「邀请码」的说法（提案：**入区邀请函 / 邀请入区**，走 `term_zone()` 取词单点，不硬编码）—— 用词属界面裁定，back 不自行改文案；③ 两套码各管各的：框架那套是**运营者建的批量注册门票**（`created_by` = Operator，所以它把完整码返给 console 操作者是自持语义，与 back 只给 `code_tail` 的**他人之码**判据不冲突，见 `console/SocialController::presentInvitation()`）|
| F-4 | **console SPA 的入口控制器住在 vendor 的骨架残留里** —— 一句话：`/console` 能打开，靠的是框架包里一份本该被排除掉的文件 | 拆开讲三件事：<br>① **谁在处理 `/console`**：`routes/web.php:3` import `App\Http\Controllers\SpaController`，`:23-26` 把 `console` 与 `console/{any}`（catch-all，SPA 深链接用）都指到它的 `@console`。`route:list` 实测这两条的 action 就是 `App\Http\Controllers\SpaController@console`。<br>② **这个类在哪儿定义的**：back 自己的 `app/Http/Controllers/` 里**只有 `Controller.php` 和 `Concerns/`，没有 `SpaController.php`**（`ls` 实测）。全仓唯一定义在 **`vendor/dsplat/multi-tenant-saas/app/Http/Controllers/SpaController.php`** —— 那是框架当 Laravel 应用骨架时期留下的 `app/` 目录，拆包文档 `downstream-architecture.md:55-85` 明文要求下游用 `exclude-from-classmap` 把 `vendor/dsplat/multi-tenant-saas/{app,src/Modules}` 排掉，参考项目 scrm-platform 两份都排了。**我们没排**，于是 autoload 落到了那份残留上，页面照常渲染 —— 所以这不是「已经坏了」，是「入口挂在一个官方要求你排掉的文件上」。<br>③ **为什么不能直接照文档补排除**：同一 `app/` 目录里的 `Controller.php`（基类）**不能没有** —— 框架的活控制器就是继承它，例如本次 F-3 用的 `.../Auth/Http/Controllers/InviteCodeController.php:5` 写的正是 `use App\Http\Controllers\Controller;`。back 侧 `app/Http/Controllers/Controller.php` 与 `AddSecurityHeaders` **存在且实际胜出**（反射实测），所以补排除对基类安全；**只有 `SpaController` 一个类 back 没有本地对应物** ⇒ 只补 `exclude-from-classmap` 不补本地类，`/console` 立刻「Class not found」500 | 裁定即 #25 的前置，两个选项：<br>**A（推荐）** back 自建 `app/Http/Controllers/SpaController.php`（职责就一件：返回 SPA 外壳视图/资产根），再补 `composer.json` 的两条 `exclude-from-classmap` —— 满足文档、入口归 back 控制、框架日后删骨架不影响我们<br>**B** 继续吃 vendor 残留，不加排除 —— 少写一个文件，代价是框架哪天挪走那份文件（它已明令下游排除，属迟早）`/console` 直接 500，且我们 `public/console/` 的构建还得靠它<br>⚠️ 无论选哪条，`/console` 的**资产**都还没落地（#25 的另一半：SPA 基座源码 + 构建到 `public/console/`，现在那份是从 scrm-platform 拷来的成品、被 `.gitignore:36` 挡在版本库外） |
| F-5 | **3 条 Pending 迁移 —— 已按裁定执行（「F-5, 跟F-3」）** | 用户 2026-10-04 点头后，对本地 `mynet_api_dev`（MySQL 127.0.0.1:3306）跑 `php artisan migrate --force`：`2026_10_01_000001_add_invite_code_tables` 151ms DONE、`..._000002_add_invite_code_permission` 9ms DONE、`..._000003_make_users_email_nullable` 49ms DONE。**执行前后取证**：`users` 行数 2、`tenants` 行数 2 不变；`users.email` 变为 `Null=YES Key=UNI Default=NULL`（唯一键保留）；`invite_codes` 12 列 / `invite_code_usages` 建成、执行后各 0 行；权限位 `invite_code.manage`（permission_id 6805018806189879，display_name「管理注册邀请码」，group `invite_code`）授权落在 **3 条全局角色**（`super_admin`/`platform_admin`/`tenant_admin`，`tenant_id` 均 NULL），实测 back 现存的 2 条 `operator_tenants` 绑定（1 条 super_admin→平台租户、1 条 tenant_admin→租户 7657309242713752）**都指向已授权角色**，故 console 侧不需要额外授权动作<br>**行为零变化**：`settings` 里没有任何 `%registration%` 行（dev 库 `settings` 表甚至不存在，查询 1146），即 `registration.mode` 仍取默认 `open`，注册门禁处于休眠；执行后全量套件 **352 passed** 不变 | 生产侧仍走 G-6 部署清单，且必须含「`database/sql/*.sql` 手工执行」那步 —— 注意该文件自称幂等是错的（`ADD COLUMN IF NOT EXISTS` 在 MySQL 8.4 报 1064）。另：本次实测过程中在 dev 库建过 1 条 `invite_codes` 试数据与 1 个探针 token，**均已删除并复核**（`invite_codes=0`、`usages=0`、`personal_access_tokens` 回到 18 条），`bootstrap/app.php` 的临时别名也已还原、`git diff` 空 |
| F-6 | 复核后**关闭**一项旧疑点 | `platform:init` 路径上的 `new SlugService`（`PlatformTenantSeeder.php:55`、`TenantOnboardingService.php:362`）**不是**绕过闸门的特例 —— 框架自己在 `TenantService.php:35` 同样直接 `new`，属既有约定 | 无需裁定，记录以免重复排查 |

**back 侧本轮新发现的潜伏 bug（只上报，未顺手改）**

| # | 事实 | 证据 | 处置 |
|---|---|---|---|
| B-1 | 三个社交模型 `belongsTo(User::class)` 却**没 import User** ⇒ 解析到不存在的 `App\Modules\Social\Models\User` | 实测：console 侧 `with(['user','friend'])` 一跑就 `Class not found`，14 条用例 11 红。全仓此前无人调用过这三张表的关联，所以一直藏着 | **已修**（本会话，随 G-3 ③）：`Friendship`/`FriendRequest`/`Invitation` 各补 `use App\Models\User;`，与 `VPN/Models/Route.php:6` 同口径 |
| B-2 | `DELETE /api/v1/social/invitation/{id}` **永远删不掉码**，而且**两个驱动的症状不一样** | `SocialDomainService::deleteInvitation():1070` 写的是 `Invitation::where('id', …)`，而表主键是 `invitation_id`、库里**没有 `id` 列**（`create_social_tables.php:63` 与 `tests/Schema/SocialModule.php:41` 两边一致）。实测两端：<br>· **sqlite**（测试态）不报错，静默返回 null ⇒ 回 400「邀请码不存在」，看着像数据问题。原因：Laravel 生成 `where "id" = ?`，而 SQLite 把匹配不到标识符的双引号串**退化成字符串常量**，条件恒假。<br>· **MySQL**（生产态）在 `mynet_api_dev` 上实测抛 `QueryException 1054 Unknown column 'id' in 'where clause'`，再被控制器 `catch (\Exception $e)` 原样回显 ⇒ **400 的 message 里带着 SQL 语句和连接名**。`git blame` = `fdac611`（2026-09-23），修前该路径全仓零测试 | **已修**（本会话，用户指令「B-2 你直接修复」）：改 `Invitation::find($invitationId)`（走声明主键，与同文件 `FriendRequest::find():179,260,298` 同口径）。回归网 `tests/Feature/Api/InvitationDeleteTest.php` 5 条：归属/用量/存在性三道闸各钉一条 + **message 里不许出现 `SQLSTATE`** 当哨兵 + 软删除落地（行在、`deleted_at` 有值、`find()` 查不到）。修前先跑确认 3 红，修后全绿 |
| B-5 | 同一类错列名还有两处（B-2 修复时顺同一判据扫出来的） | 判据：模型声明了自定义 `$primaryKey`，代码却按 `id` 查。① `NodeController::resolveIndexServerToService():1652` `Service::where('id', …)` —— `services` 表只有 `service_id`（`create_vpn_business_tables.php:174`），**可达**：调用点 `:1490`，数字形式的索引服务器输入在 sqlite 恒「无法解析索引服务器」、MySQL 1054。② `RouteDomainService::routeExists():572` `Route::where('id', …)` —— `routes` 表只有 `route_id`（`:119`），但 `routeExists` **全仓零调用**，是死方法里的死 bug。另：`personal_access_tokens` 那几处 `where('id', …)`（`DeviceFingerprintMiddleware` / `SessionRefreshService` / `RefreshTokenController`）**不是同类**，Sanctum 表真有 `id` | **已修**：两处都改成各自的真实主键列名，未新增行为。②没删方法（删公开方法属范围决定，留给 `back-baggage-cleanup` 那条线）<br>⚠️ **测试态盲区**：套件只跑 sqlite，而 sqlite 会把这类「查不存在的列」吞成恒假条件 —— MySQL 才硬报错。要不要给 Feature 套件加一条 MySQL 跑法，属**待裁定**（不擅自加 CI，CI 按分钟计费） |
| B-3 | `SocialConnection` 表与模型对不上且零调用 | 表是好友/邀请中转形状，模型 fillable 是 `platform/platform_user_id/access_token/is_active`（`:22-37`）；唯一调用方 `SocialDomainService::createSocialConnection()` 全仓零引用。它和 B-1 一样缺 `User` import，但那是死代码不是漏 import | **不动**，`console/social` 明确不摊这张表（见控制器 ⚠️ 三条） |
| B-4 | `app/Modules/Social/Controllers/Api/V1/SocialController.php` 是**零路由的死副本** | `SocialServiceProvider` 无 `loadRoutes`/路由注册（grep 无命中）；真正在跑的是 `VPN/Routes/api.php:204` import 的 `App\Modules\VPN\Controllers\Api\V1\SocialController` | 待用户确认后删除（属 `spec/back-baggage-cleanup.md` 那条线，不混进 console 切片） |

**缺口尺寸（给 G-3/G-4 估量用）**：scrm console 产物登录后自动打
`/api/v1/scrm/{dashboards/overview,dashboards/trend,dashboards/channels-distribution,dashboards/setup-checklist,agents,notifications/unread-count,scene-context}` —— 全 404。
一个 console 构建要的接口面就是这个量级。

---

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
**可与 A 并行**：B、D 的大部分（D-1..D-7 不依赖 A）、E 的大部分（E-7..E-10 不依赖 A）、F、**G（console 轨）——G 与驱动主线无依赖，可独立推进**。
**跨轨依赖**（务必按序）：
- `C-1 ← D-7`（`mynet setup` 是下发通道）
- `C-2 ← A-1 + B-3`（wg 密钥 + 编码统一）
- `A-7 ← B-2 + B-4`（表结构）
- `D-12 ← C-7`（console 走 `/v1/relay`）
- `E-5 ← E-8 + D-11`（命名统一 + 瘦构建标签）
- `F-1 ← P0-5`（两套重复的探测实现先别删，等收敛）
- `A-8 ← F-3`（跨驱动验证需要 L6 的通路）
- `E-3` 判据改造后才搬（依赖 proxy 下架的结论）
- `G-2 ← G-1`（SPA 先有门禁才能登录）· `G-4 ← G-3`（业务页对着租户作用域 API 做）· `G-5 ← G-3` · `G-6` 独立可并行

---

## 3. 计划顺序表（可跟踪）

> **用法**：按序号推进，做完勾掉状态列。任务 ID 是唯一标识（`repo-migration.md` 的阶段引用它）。
> `M0` / `M1` 是两个硬门槛。

| # | 阶段 | 任务 ID | 内容 | 前置 | 状态 |
|---|---|---|---|---|---|
| 1 | **S0 立骨架** | S0-1 | 建 `mynet_agent` / `mynet_web` / `mynet_desktop` / `mynet_api` 四个空 repo | — | ☑ |
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
| 16 | | F-4 | 平台实现（linux / darwin / windows / openwrt） | F-2 | ⚠️ 部分 |
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
| 30 | | C-6 | presence 走 Redis pub/sub | C-4 | ☐ **撤勾** |
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
| 51 | | D-4 | UI 合并（Quasar 两域骨架已立；mynet_ui 页面批量迁移待续） | D-3 | ☑ |
| 52 | | D-5 | echarts 按需引入 | D-4 | ☑ |
| 53 | | D-9 | `driver` 域（列/装/升，与 CLI 共用 `upgrade_service`） | D-7 | ☑ |
| 54 | | D-10 | `POST /api/v1/nodes/switch` | D-7 | ☑ |
| 55 | **S9 平台** | E-1 | procd 接管 gnb（含 `gnb_es` 孤儿前置验证）—— 样例已立，待孤儿验证 | — | ☑ |
| 56 | | E-2 | `node.conf` 改 `--systemd` / `daemon off` | E-1 | ☑ |
| 57 | | E-3 | `_gnb_peer_heal` **改造判据后**搬进 mynetd | E-1 | ☑ |
| 58 | | E-4 | Lua 职责收窄为执行器（结果回 mynetd，经 WS 上报） | E-1 | ☑ |
| 59 | | E-8 | 命名统一 `mynet_agent_*` | — | ☑ |
| 60 | | E-5 | mynetd 构建落地（OpenWrt 产物） | E-8, D-11 | ☑ |
| 61 | | E-6 | ubus 薄桥（可选） | E-5 | ☑ |
| 62 | | E-7 | mynet_desktop 补全 | S3-1 | ☑ |
| 63 | | E-9 | `apps_index_url` 统一到 `ctl.mynet.club` | — | ☑ |
| 64 | | E-10 | 文档同步 + 代码改端口（8720→8096） | — | ☑ |
| | | | **▶ M2 控制面完成 · M3 产品面可用 · M4 平台面收口** | | |
| 65 | **S10 console 租户后台** | G-1 | vendor 迁移落库（operators/RBAC，按缺口补） | — | ☑ |
| 66 | | G-2 | console SPA 构建资产进 `public/console/` | G-1 | ☑ |
| 67 | | G-3 | 租户作用域 API（节点/路由审批/社交/服务策略）——**①②④ 已落（②④只读；②的「过期」死值雷已于 2026-10-02 在模型层修掉，读时判时间、四路调用点同口径），剩 ③ 社交（避开他会话的 SocialController 冲突）** | B-1 | ◐ |
| 68 | | G-4 | console 业务模块页（挂框架 module-loader） | G-3 | ☐ |
| 69 | | G-5 | 菜单 + 权限项注册进框架 `permissions`/菜单表 | G-3 | ☐ |
| 70 | | G-6 | 部署：`console.mynet.club` server block + 证书 SAN（H3） | G-2 | ☐ |
| | | | **▶ M5 console 租户后台可用** | | |

**里程碑对照**：M0 = #4–11 · M1 = #12–49 · M2 = #25–30 + #46–47 · M3 = #50–54 · M4 = #55–64 · M5 = #65–70。

> ⚠️ **2026-09-24 基线校正：C-6 撤勾、F-4 降为「部分」。** 依据见 **`long-range-plan-deep.md` §0**。
>
> - **C-6（presence 走 Redis pub/sub）**：代码写了但**全是死代码**——`Registry.SetPresence` 无调用方、
>   `Registry.Notify`（`gateway/server.go:112-130`）只查本地 `conns`、**从不调 `presence.Publish`**、
>   `NewRedisPresence`（`presence_redis.go:22`）仅被测试调用 → 实际恒为 `NewMemoryPresence()`（`server.go:44`）。
>   且 `presence_multi_test.go:6` 断言的恰恰是两实例**互不共享**。真 Redis 的 HSet/Publish/Subscribe **零测试**。
>   更根本的是：**gateway 二进制不存在**（`gateway/` 无 `package main`，`cmd/` 只有 `mynet`/`mynetd`）。
>   重做见 `long-range-plan-deep.md` §4 的 **V4-5/V4-6/V4-7**。
> - **F-4（平台实现）**：**Windows 是纸面**——`Makefile:43` dist 矩阵不构建 windows；
>   `detector/routing_win.go:46` 硬编码 `NetworkInterface{Name:"stub"}`、`:45` netstat 输出被丢弃；
>   无 `firewall_win.go`/`service_win.go`；`cmd_install.go:55-64` 的 `unitPath` 只有 linux/darwin。
>   darwin 仅能构建（`metrics_system.go:55-60` 读了就丢 → **macOS 指标全 0**）。
>   真跑过的只有 linux（U1 三机）与 openwrt（VM）。重做见 **V5-1/V5-2**。

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
| `node_services` 表**无可用写入方**（`PUT /nodes/{id}/network-config` 是 500） | 2026-10-02 取证于 G-3 ④：`Api\V1\NodeController.php:1463,1562` 把应用服务类 `Services\NodeService` 当 Eloquent 模型用（`::where` / `::addServiceToNode` 均不存在），抛 `Error` 不被 `catch (\Exception)` 接住。真实读路径不依赖这张表（`BundleEnvelopePublisher::resolveIndexServers()` 从 `services` 解析）。**待裁定**：修 import（改指 `Models\NodeService`）还是整条路径重做——若判「表本就废弃」，G-4 界面不该出现节点-服务绑定视图 |

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
