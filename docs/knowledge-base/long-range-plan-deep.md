# MyNet 长程计划 · 深化编排（V 段）

> 前置：`dev-plan.md`（S 段 64 项）· `long-range-plan.md`（R 段）· `long-range-plan-t.md`（T 段）。
> **本文不取代它们，而是对其做基线校正 + 把 T3–T5 拆到可执行粒度。**
>
> 触发原因：T2-6 联调（2026-09-24）撞出一串「已勾但纸面」的项。随后做了三路只读实测勘察
> （生产部署就绪度 / CI 与分发链路 / 规模化与多平台），并对最吃重的断言逐条亲自复核。
> 结论是：**按现有计划表继续往下推，会在 G4 上撞墙**——因为 G4 的前置能力有一半不存在。
>
> 纪律：本文每条判断都带 `file:line` 或实测输出。凡属推断，显式标注「推断，待验证」。

---

## 0. 基线校正：五处「已勾但纸面」

这是本文存在的理由。**勾选项的可信度本身是需要维护的资产**——一旦计划表开始说谎，
后续每个会话都会基于假基线做决策。

| # | 计划表状态 | 实测真相 | 证据 |
|---|---|---|---|
| 1 | `long-range-plan.md:63` **R4-3 ☑**「`mynet update` 升级一轮：旧版→新版→健康检查」 | `Fetch`/`Install` 是**返回 error 的桩**，无 HTTP 拉取、无下载、无校验、无替换、无备份、无回滚。**真升级一轮从未跑过** | `mynet_agent/mynet/upgrade/service.go:47-52`（`"upgrade: Fetch 未注入（接 ctl apps 索引）"` / `"upgrade: Install 未注入"`）；`Artifact.Checksum:30` 定义了但**无人读取**；`upgrade_roundtrip_test.go:9-18` 注入的是假 Fetch/Install |
| 2 | `long-range-plan.md:61-62` **R4-1/R4-2 ☑**「make dist 三平台产物 + ctl apps 索引」 | ctl 侧**根本没接通**：`PRODUCTS` 白名单里没有 `agent`，`validateProduct()` 会直接拒收 agent 产物 | `mynet_ctl/app/Domain/Release/Services/ReleaseDomainService.php:19-26`（只有 `tui/gnb/utc/client/proxy/lua`）、`:122-125` |
| 3 | `long-range-plan-t.md:63` **T3-3 ☑**「备份/恢复：DB + 密钥（back_priv 高保护）」 | `backup.sh` **无加密**（明文 tar.gz，而计划原文要求「备份加密」）、**无恢复脚本、无恢复演练**、cron 只是 README 注释、DB 密码走命令行（进 `ps`） | `mynet_back/deploy/backup.sh:10-14,17-20,23-24`；`deploy/README.md:34`；`long-range-plan-t.md:164` 的要求原文 |
| 4 | `dev-plan.md:330` **C-6 ☑**「presence Redis pub/sub + 降级」 | Redis presence **全是死代码**：`SetPresence` 无调用方、`Notify` 从不调 `presence.Publish`、`NewRedisPresence` 仅被测试调用 → 实际恒为内存 presence。且 `presence_multi_test.go:6` 断言的恰恰是两实例**互不共享** | `gateway/server.go:44,48,112-130`；`presence_redis.go:22`；`presence_test.go:47,61` |
| 5 | `dev-plan.md:316` **F-4 ☑**「linux/darwin/windows/openwrt」 | **Windows 是纸面**：dist 矩阵不构建 windows、路由探测硬编码 stub、无 `firewall_win.go`/`service_win.go`、install/uninstall 无 windows 分支 | `mynet_agent/Makefile:43`（矩阵仅 darwin arm64/amd64 + linux amd64/arm64/mipsle）；`detector/routing_win.go:46`（`NetworkInterface{Name:"stub"}`）、`:45`（netstat 输出被丢弃）；`mynet/cmd_install.go:55-64` |

### 附带发现：一个把 502 判为 PASS 的测试

`gateway/server_test.go:72-83` 对 `/v1/relay` 断言的是 **502**——而 `handleRelay`
（`gateway/server.go:241-247`）**恒返回 502**「relay 上游未配置或不可达」。
即该测试锁死了一个未实现的行为，并让它看起来是绿的。
同时 `relaypolicy` 白名单**完全未接线**：它在 `gateway/` 里只出现在注释
（`server.go:242`），从未被 import。

> **这类「测试通过 ≠ 功能存在」是本轮最重要的教训**，处置见 §6。

---

## 1. 真实起点盘点

按能力域给出实测状态。「纸面」= 有文档/有类型定义/有测试，但无可运行实现。

> ⚠️ 本表是 **V 段开工时的基线快照**，不是「现状」。V 段推进中已解决的行，就地在行内
> 划掉并补上**现证**——读的时候别只看前半句，后半句才是现在。

| 能力域 | 真实状态 | 关键证据 |
|---|---|---|
| **数据面隧道** | ✅ **真通**。gnb + wg + 跨驱动桥三场景 e2e PASS，三节点拓扑（2×LAN + 1×QEMU OpenWrt） | `scripts/e2e/g3_tunnel.sh`、`u1_mesh.sh` 6/6 |
| **社区业务面（back）** | ✅ **真通**。注册/登录/节点/密钥/好友/连接申请/服务 CRUD 实测 200/201/422/410 各就各位；`make check` **154 tests / 2503 assertions**（2026-09-24 清理时实测；基线为 126/2398，差额来自 CPI-28 把 Feature 套件并入门禁） | 本文同日 T2-6 实测；`long-range-plan-t.md` §3 T2-6 证据表；`mynet_back` 的 `make check` 输出 |
| **用户前端（mynet_gui）** | ✅ **四页真打通**，连接向导四步走通并落库 | `long-range-plan-t.md` §3 T2-6 |
| **gateway** | ✅ **可执行（V1 已完成，2026-09-24）**。V1 开工时的基线是「无 main 的库包 + `handleConnect` 占位 + `handleRelay` 恒 502 + `Makefile` dist 不产它 + compose 里 `alpine sleep infinity`」，V1-1~V1-5 已逐项解决（实测证据见 §4 各段） | 现证：`cmd/mynet_gateway/`、`gateway/ws.go`（真握手）、`Makefile:95`（五平台产 `mynet_gateway`）。⚠️ 残留：`scripts/e2e/up.sh:78` 仍自陈「cmd 待接 — 跳过」，该自陈**已过期** |
| **在线升级** | ~~❌ 纸面（`upgrade.Fetch`/`Install` 是返回 error 的桩）、版本比较是**字典序非 semver**（`0.10.0 < 0.9.0`）~~ → ✅ **V2 已解决**（V2-5 两步协议 Fetch、V2-6 Install 含校验+备份+原子替换+回滚、V2-7 改语义比较、V2-8a 真二进制升一轮） | 现证见 §4「V2 实测证据」：真 `mynet` v0.0.1→v0.0.2；负路径校验和不符被拒且不落 `.bak` |
| **版本与校验** | ~~❌ 产物版本**恒为 `dev`**（无 `-ldflags`）；agent 侧无 checksum 生成；客户端从不校验~~ → ✅ **V2 已解决**（V2-3 版本注入 + strip、V2-4 `make dist` 出 5 平台 × 3 二进制 + `checksums.txt`、V2-6 客户端校验且不符即拒） | 现证见 §4：三个二进制分别报出注入版本；`-s -w` 让 mynetd 由 9.7MB 降到 7.2MB |
| **分发中心（ctl）** | ⚠️ **服务端完备**（`releases` 表有 `sha256`、上传即算校验和、manifest 输出 `artifacts[].sha256` + `checksums`）。原先「客户端未接」的五项里，**V2-1/V2-2 已解决两项**：`PRODUCTS` 注册 `mynet_agent`、`apps.json` 形状分歧修正（Go 解析器改打**真端点**，不再读 fixture 后 `t.Skip`）。仍缺：节点面 bundle 拉取与心跳上报（V1-10）、ctl 侧真拉两版（V2-8b） | `ReleaseService.php:32-38`；`ManifestService.php:268-270,279,282,300`；§4 V2 各项 |
| **可观测性** | ⚠️ **采集与上报已通（V4-1a / V4-2），出口侧仍缺**。~~`mynet metrics` 的 `CPUPercent`/`InterfaceUp`/`Peers`/`PeersUp`/`Addrs` 全仓从未被赋值~~ → darwin 已实测非 0 且与 sysctl/ifconfig 对照一致；~~`metrics_vpn.go` 解析循环空转~~ → 已改为**调 driver 包**；~~心跳采完 `_ = out` 直接丢弃~~ → **已真上报**，back 的 Redis 与 sqlite 都核对到了真指标（V4-2）。仍缺：无 Prometheus/metrics 端点（V4-3）；告警全仓 0 处（V4-8）；`ApiRequestLogger` 仍是死代码。~~`shared/logger` 零消费者~~ → 已重写为 slog 封装并接入两个常驻进程（V4-4） | 实测 `mynet metrics --json`；`MYNET_LOG_FORMAT=json` 的日志样本；Redis `node:status:<id>` |
| **back 侧指标** | ⚠️ 有一条真链路：`POST /api/v1/monitor/heartbeat` → Redis（pipeline + 全节点 zset + 离线扫描）。但 `ApiRequestLogger` **未在任何地方注册**（死代码） | `VPN/Routes/api.php:49`；`Monitor/Domain/Services/NodeHeartbeatDomainService.php:54,138,182`；`VPN/Middleware/ApiRequestLogger.php:92` |
| **压测** | ⚠️ `bench_1k.go` 是**纯进程内合成**（map attach + 桩执行器 `return "ok"`），无网络/无 WS/无心跳/无 back。224k/s 测的是 map+分派吞吐，**与心跳无关** | `scripts/load/bench_1k.go:26-34,37,49,93` |
| **多平台** | linux ✅ 真跑（U1 三机）· openwrt ✅ 真跑（VM）· darwin ⚠️ 仅能构建（metrics 全 0）· windows ❌ 从未构建。`mynet_desktop` 只有 12 行 plist + 21 行 unit + 22 行脚本，**无任何 .msi/.nsis/.pkg/.dmg/.deb/.rpm**；`mynet install` 只拷二进制、不装 unit、不 enable；`uninstall` 是 print-only | `mynet_desktop/`；`mynet/cmd_install.go:12-48,50-53,55-64` |
| **移动 relay** | ⚠️ ~~`/v1/relay` 恒 502、白名单未接线~~ → **V1-4 已解决这两项**（现真转发、`relaypolicy` 已接线，见 §4）。仍缺：① **移动端 / GUI 侧零消费**（`mynet_gui` grep `v1/relay` 0 命中，仍直连 back）——注意这与**节点面**不同，节点面已复用 `/v1/relay`（V1-9）；② spec 要求的 **mynetd 侧** `GET /api/v1/relay?path=` 在 localapi 路由表里不存在（V1-8）；③ 移动端载体：**Capacitor 连产物都出不来**（仓里无 `@capacitor/*` 依赖、无 `src-capacitor/`，`npm run build:capacitor -T android` 会停下来交互索要 appId——Quasar 的 capacitor 模式不会自动兜底）。注意与**桌面 Electron 侧**不同：后者实测 `npm run build:electron` **退出码 0 并产出 `dist/electron`**，但那是 Quasar 在构建时**自动补依赖并生成 `src-electron/` 模板**（本仓原本都没有），且从未发布过（无签名/分发配置） | `spec/control-plane.md:278-289`；`mynetd/localapi/server.go:55-67`；`mynet_gui/quasar.config.ts:205,210`；V1-4 证据见 §4 |
| **用户文档** | ❌ `mynet_gui/docs/help/index.json` 引用 **47** 个文件、实际只有 **5** 篇正文（`lastUpdated: 2025-09-15`），`HelpPage.vue` 消费它 → 绝大多数链接悬空。**无任何安装指南**。全仓**无隐私政策** | `mynet_gui/docs/help/index.json`；`mynet_desktop/README.md`（8 行，开发向） |
| **生产环境** | ⚠️ **服务器与证书已存在**：`10.182.236.180`（公网 `52.77.179.176`），ctl/back/gui 共置同机不同 nginx server block，证书 `/etc/letsencrypt/live/mynet.club/{fullchain,privkey}.pem`。但 **back 尚未部署过**（无部署记录）——V3-1/V3-2 已把入口备好（`manage-prod.sh` + `deploy/nginx.conf`），剩余前置是 H5（DBA 建库）与 H6（`back_priv` 入凭证库） | `deployment-registry.md:12-15,20`；`mynet_back/manage-prod.sh`；`mynet_back/deploy/`（4 文件：`.rsyncignore` / `backup.sh` / `nginx.conf` / `README.md`） |
| **CI/CD** | ❌ `mynet_agent`/`mynet_back` **无 `.github`**；ctl/gui/utc 只有 `copilot-instructions.md`、**无 workflows**；`mynet_openwrt/release.yml` 存在但**该 repo tag 为空 → 从未跑过**；gnb 的 10 个 workflow 产物 rsync 到 **`download.mynet.club`（已废弃链路）不是 ctl** | `mynet_openwrt/.github/workflows/release.yml:1-35`；`mynet_gnb/.github/workflows/build_linux.yml:11,548-555`；`v2-upgrade-plan.md:18` |

---

## 2. 拱顶石与关键路径

**拱顶石已通（V1，2026-09-24）。** gateway 可执行化已完成：`cmd/mynet_gateway` 在、
`gateway/ws.go` 真握手、`handleRelay` 真转发且 `relaypolicy` 已接线、`Makefile:95`
五平台产 `mynet_gateway`（V1-1~V1-5 / V1-7 / V1-9 的实测证据见 §4）。

T3-5（G4 外网可用）、T4-4（双 gateway）、T4-6（真节点压测）、T5-3（移动 relay）
四项共同的前置——「有一个能跑、能连、能转发的 gateway 进程」——**现已具备**。
它们各自的剩余前置（含人工闸门）见 §3 与 §4。

```
                    ┌─────────────────────────────────────┐
                    │  V1 拱顶石：gateway 可执行化          │
                    │  cmd/gateway main + ws.go 真握手      │
                    │  + relay 上游转发 + relaypolicy 接线  │
                    └───────────────┬─────────────────────┘
                                    │
          ┌─────────────────────────┼─────────────────────────┐
          │                         │                         │
          ▼                         ▼                         ▼
  ┌───────────────┐        ┌────────────────┐        ┌────────────────┐
  │ V2 交付链真跑  │        │ V3 生产落地     │        │ V4 规模化       │
  │ 版本注入/打包  │        │ 主机部署入口    │        │ 双 gateway      │
  │ 校验/真升级    │        │ nginx 反代      │        │ presence 接线   │
  │ ctl 接通 agent │        │ back_priv 文件  │        │ 指标采集→暴露   │
  └───────┬───────┘        └───────┬────────┘        └───────┬────────┘
          │                        │                         │
          │              ┌─────────┴──────────┐              │
          │              │ 人工闸门（§3）      │              │
          │              │ DNS·证书·DBA·真机   │              │
          │              └─────────┬──────────┘              │
          └────────────────────────┼─────────────────────────┘
                                   ▼
                          ┌────────────────┐
                          │ G4 外网可注册   │
                          │ 可互通、可升级  │
                          └────────┬───────┘
                                   ▼
                          ┌────────────────┐
                          │ T5 生态 / 公测  │
                          └────────────────┘
```

**关键路径**：`V1 gateway 可执行化` ✅ → `V2 ctl 接通 + 真升级一轮` ✅ → `V3 生产部署`（**进行中**）→ `G4`。
其中 V3 有一半是人工闸门，**必须尽早启动人工流程**（见 §3），否则工程做完了也上不了线。

**可并行的独立轨道**（不依赖拱顶石，可立即开工，适合派子代理）：

- **轨道 A**：指标采集补全（`CPUPercent`/`Peers`/`InterfaceUp` 真赋值、`metrics_vpn.go` 解析、darwin 分支）→ V4 的前置，但本身独立
- **轨道 B**：版本注入 + 打包 + checksum（Makefile ldflags / strip / .tgz / 文件名带版本）
- **轨道 C**：用户文档与安装指南（纯工作量，无技术障碍）
- **轨道 D**：CPI-30（`.env.production` 死文件）+ CPI-32（业务拒绝返 500）—— 两条 P1，都是生产前置
- **轨道 E**：OpenWrt ipk 的 `mynet-agent` 包（现在 `files/mynetd` 缺失时**静默产出空 ipk**）

---

## 3. 人工闸门清单（我做不了、也不该做的）

**这些必须尽早启动**——它们的交付周期比工程长，且全部卡在关键路径上。
红线（`AGENTS.md` §3 + U1 授权）：不碰生产密钥/crt、不改 DNS、不动 GitHub org 设置。

| # | 需要你做的事 | 为什么必须人做 | 阻塞 | 建议时机 |
|---|---|---|---|---|
| **H1** | ~~裁定 web server 方案~~ ✅ **已裁定（2026-09-24）：复用现有 nginx，不引入 Caddy** | — | — | 已决 → V3 改走 nginx server block |
| **H2** | **DNS 记录**：`api` / `gw` / `console` / `admin` → `52.77.179.176` 或新入口 | 红线「不改 DNS」 | T3-2 → T3-4 → T3-5 → G4 | H1 已定，**可立即办** |
| **H3** | **确认证书 SAN**：`/etc/letsencrypt/live/mynet.club/` 现有证书是否已含 `api.` / `gw.` / `console.` / `admin.` 子域；不含则需重签 | 需生产机 root + ACME 交互 | T3-2 | 与 H2 同时 |
| **H4** | ~~裁定子域命名冲突~~ ✅ **已裁定（2026-09-24）：`console` = 租户管理（back 管理面）、`admin` = 平台管理（框架 platform/operator SPA）、`adm` 弃用**。用户原话：「back管理是租户管理，租户管理是console，admin 是平台管理」 | — | — | 已决 → 待同步 `deployment-registry.md:13` / `ecosystem.md:125` / `brand-guide.md:152` |
| **H4b** | **`gw` vs `gw1`/`gw2` 未裁定**（H4 只答了 admin/adm）。建议：现阶段单入口 `gw`，等 V4-5/V4-6（presence 真接线）做完再开 `gw1`/`gw2`——现在开两个入口是空的 | 命名与多实例时机是产品决策 | T4-4 | V4 前 |
| **H5** | **DBA 建库建表**：生产是 MySQL 8，而 `deployment-registry.md:63` 明写「**mynet_back 禁止 migration，需 DBA 走 database/sql/**」 | 生产库变更权限 | T3-4 | H1 之后 |
| **H6** | **生成 `back_priv` / `back_pub` 并入凭证库**：当前 `.env` 无 `MYNET_BACK_FACE_*` 任何键 → 节点面 DH+HMAC **一律拒绝**（fail-closed：`BackFaceKeys::resolve()` 返 null → `NodeHeartbeatAuthMiddleware.php:192-196` 记 `Log::error` 并 return null → 调用方 `:73-75` `reject()`）。⚠️ 保护等级 **高于**节点私钥（泄露可冒充**所有**节点） | 红线「不碰生产密钥/crt」；且必须走 `crt` 凭证库 | T3-4 · T3-5 | H5 同时 |
| **H7** | ~~裁定 `back_priv` 形态~~ ✅ **已裁定（2026-09-24）：0600 文件，按原规范**。原实现是 env 注入（`config/mynet.php`）**已改**（V3-6a：`BackFaceKeys`，文件优先且权限过宽即拒绝）；`spec/node-keying.md` 现无 back_priv 条目，**已补**（V3-6b：§4.6） | — | — | 已决 → V3-6a / V3-6b 均已完成 |
| **H8** | **一台非 VM 真机** + 外网可达的 api 域名 | 硬件 | T3-4 | G4 前 |
| **H9** | **一台 Windows 主机**（且需先确认 gnb/wireguard 在 Windows 的驱动可用性） | 硬件。无它则 Windows 只能交叉编译出包、**运行不可测** | T5-1 | T5 开工前 |
| **H10** | **50+ 节点或等价容器集群** | 物理约束。现有真实资产仅 **3 台**（`scripts/inventory/hosts.sh:3`） | T4-6 | 见 §5 |
| **H11** | **GitHub 单 repo 级 secrets**（ctl 上传 token 或 rsync 凭证）+ **打 tag** 触发 CI。🔴 **org 级设置一律不动**（org secrets/variables、Actions 策略、repo 创建与可见性、branch protection、org token 权限） | 需网页操作 + 权限 | T4-1 · T4-2 | V2 末 |
| **H12** | **隐私政策与法务**：全仓无隐私政策（判据 `long-range-plan-t.md:128` 要求「安全复检 0 高危 + 隐私说明」） | 法务 | T5-5 | 公测前 |

---

## 4. 波次编排

每波给出：项目表（ID / 内容 / 前置 / **验收判据** / **证据形式**）+ 可并行度。
**证据形式是硬要求**——没有它就无法区分「真做完」和「纸面做完」（见 §6）。

### V0 · 基线校正（本轮，已基本完成）

| ID | 内容 | 状态 |
|---|---|---|
| V0-1 | 三路实测勘察 + 亲自复核吃重断言 | ✅ 本文 §0/§1 |
| V0-2 | 五处假勾选撤销（`long-range-plan.md` R4-1/R4-2/R4-3+G2、`long-range-plan-t.md` T3-3、`dev-plan.md` C-6/F-4） | ✅ 已完成，每处附依据 |
| V0-3 | 本轮缺陷入册 CPI-23 ~ CPI-35 | ✅ `cross-project-issues.md` |
| V0-4 | 框架上游缺陷成文（CPI-34/35） | ✅ `upstream-dsplat-issues.md` |
| V0-5 | 人工闸门 H1/H4/H7 三个**裁定**类 | ✅ 已裁定（见 §3 与 `mynet-overview.md` §15） |
| V0-6 | 反纸面达成五条 D1–D5 写进 `AGENTS.md` §7.1 DoD | ✅ 已完成 |

---

### 🎯 0→1 达成（2026-09-25，真 staging + 两台真节点机）

**判据**：注册 → 建节点 → 交换公钥 → 加好友 → 授权连接 → **两节点真互通** → 心跳/监控有数据。
**七项全部达成**，且互通附三重独立证据（不是只看 ping 通）。

| 环节 | 实测证据 |
|---|---|
| 注册 / 登录 / 租户上下文 | `zero_to_one.sh` Phase 0-1b 全绿（`--preflight-only` 复验 4/4，gateway `conns=2`） |
| 建节点 + **IP 池分配** | `POST /ippools` → 201，A 得序号 1 / `10.0.0.10-15`，B 得序号 2 / `10.0.0.16-21`；建节点后 `node_ip` = `10.0.0.10` / `10.0.0.16`（此前 19 个节点全 `null`，见 CPI-52） |
| 交换公钥 | 密钥在**节点机上**用 `gnb_crypto` 生成，只上传公钥（`PUT /nodes/{id}/keys`，`key_type=gnbkey`）；响应不含 `private_key`，私钥全程未出节点 |
| 加好友 | A/B 已是好友（`social/data` 里 `friend_user_id` 对得上） |
| 授权连接 | `send-request` → id `5028568742677534`，`accept-request/{id}` → 200 `status=accepted`。**这一步此前任何脚本都没调过**（库里两条旧申请至今 `pending`） |
| bundle 下发 | 两节点各拉到 1495 字节 envelope，含 `peer_public_keys`(1) / `static_nodes`(1) / `network.routes` / `gnb.nodes`(self+peer) |
| 渲染 gnb 原生配置 | `var/gnb/` 下 `node.conf`(`nodeid`) + `route.conf`(2 行三段点分) + `address.conf`(4 段) + `security/*.private`(128B) + `security/*.public`(64B) + `ed25519/<peer>.public`(64B) |
| 起隧道 | `mynet service start gnb` → `started gnb`（0-1s 返回）；`service status` → `running=true`；`gnb_tun` UP 且持有平台下发的 overlay 地址 |
| 装 OS 路由 | `refresh-routes` → plan → `sudo mynet apply` → `ip route get <对端>` = `dev gnb_tun src <本机>` |
| **两节点真互通** | 双向 `ping -c 5` **0% 丢包**（A→B avg 0.551ms，B→A avg 0.535ms） |
| 心跳/监控有数据 | `node_monitor_snapshots` 各 8 行；`nodes.node_status` 1→**2**、`last_seen` 已写；Redis `status=**online**` + `node:vpn_interfaces` = `{"type":"gnb","ifname":"gnb_tun","status":"up","ip":"10.0.0.10/32"}` |

**互通的三重独立证据**（缺一条都不算数）：

1. **双向 ping 0% 丢包**
2. **在 `gnb_tun` 上 tcpdump 抓到成对的 ICMP request/reply** —— 抓的是隧道接口本身，
   绕过隧道就不会有包。overlay 的 `10.0.0.0/8` 与 underlay 的 `192.168.0.0/24` 不重叠，
   所以也不存在「从底层绕过去」的捷径。
3. **gnb 自己的 in/out 计数器 ping 前后增长**（`3024/3024 → 3864/3864`，两端一致）

另附 `gnb_ctl -b <map> -c -s` 双向报 `ipv4 Direct Point to Point`
（gnb 自己的在线判据，PONG 位置位才打这行；`InDirect` 表示走中继）
与 `addr4_ping_latency_usec` 实测值。

**可重跑**：`mynet_agent/scripts/e2e/phase6_tunnel.sh`，实测 **38 通过 / 0 失败**。

```sh
P6_NODE_A=192.168.0.32 P6_NODE_B=192.168.0.16 \
P6_ID_A=<A 的 node_id> P6_ID_B=<B 的 node_id> \
P6_IP_A=10.0.0.10 P6_IP_B=10.0.0.16 \
  sh scripts/e2e/phase6_tunnel.sh
```

**这一轮撞出 12 个缺陷（CPI-52 ~ CPI-63），其中 5 个是 0→1 硬阻断**
（CPI-52 IP 池未迁移 / CPI-53 gnb 最后一公里未实现 / CPI-57 心跳被判离线 /
CPI-62 缺 OS 路由 / CPI-54 授权绕过-安全）。
**本地 189 个单元测试一个都没报** —— 因为它们全部停在「配置写出来了 / 接口返回 200 了」，
没有一个真去起隧道、真去 ping、真去查库。这是 §7.1 D2/D4 最直白的一次印证。

**仍未达成 / 已知缺口**（不假装完成）：

- **CPI-63**：mynetd 非 root 读不到 `0600 root:root` 的 `gnb.map`，
  心跳里 `connected_peers` / `rx_bytes` / `tx_bytes` 恒 0（接口状态正常）。监控面缺数据。
- **CPI-60**：`RouteController::getRequests()` 是 TODO 桩，GUI 里看不到待批准申请。
- **CPI-61**：`mynet setup` 对 gnb 节点生成 x25519 密钥（类型错），Phase 6 是用
  `gnb_crypto` 外部生成后写进 `conf/mynet.conf` 的。
- **wireguard 那条链**：`refresh-config` 写 `var/wg.conf` 而 `wg-quick` 读
  `/etc/wireguard/<iface>.conf`，路径断点与 gnb 同构（gnb 侧已修，wg 侧待 install 阶段）。
- **V1-6 命令闭环**未在真环境复验（H6 的 back_face 密钥已就位、`X-Node-Verified` 已修，
  前置条件满足了，但这一轮没跑命令下发）。
- **真实 NAT 穿透 / path MTU / 吞吐**未验：两台节点机在同一 LAN，属 1→10 范畴。

---

### V1 · 拱顶石：gateway 可执行化

**出口判据 G-V1**：`mynet_gateway` 二进制能起、能监听 8710、节点能建 WS 连接、
back 能通过它下发命令并收到结果、`/v1/relay` 能真转发且白名单生效。

| ID | 内容 | 前置 | 验收判据 | 证据形式 | 状态 |
|---|---|---|---|---|---|
| V1-1 | `cmd/mynet_gateway` main：配置加载 + 监听 8710 + 优雅退出 | — | 进程可起、`/healthz` 返回真实连接数、SIGTERM 干净退出 | 实测终端输出 + `lsof` | ✅ 2026-09-24 |
| V1-2 | `gateway/ws.go`：真 WS 握手（`server.go:250` 自陈「真实握手在 ws.go」，**该文件不存在**） | V1-1 | 节点侧 `transport/dial_websocket.go` 能连上并保活 | e2e 脚本 + 双端日志 | ✅ 2026-09-24 |
| V1-3 | `handleConnect` 去占位（`server.go:249-266`） | V1-2 | 连接状态表真实反映节点在线/已鉴权 | 单测 + e2e | ✅ 2026-09-24 |
| V1-4 | `handleRelay` 真转发（现在恒 502）+ **`relaypolicy` 接线**（现在只在注释里） | V1-1 | 白名单内路径转发成功、白名单外 403、含 `%` 一律拒 | **改掉 `server_test.go` 里把 502 判为 PASS 的 `TestC7_RelayExists`**，改为断言真实转发 | ✅ 2026-09-24 |
| V1-5 | `Makefile` dist 产出 `mynet_gateway`（现在只 build mynet + mynetd） | V1-1 | `dist/` 下五平台均有 gateway 二进制 | `file` 输出 | ✅ 2026-09-24 |
| V1-6 | back 侧打开 `MYNET_GATEWAY_ENABLED`（`config/gateway.php:17` 默认 false）并跑通命令下发 | V1-2..4, V1-9 | WS 命令闭环：back → gateway → mynetd → mynet 执行 → 结果回传 | e2e 脚本断言结果内容 | ⚠️ **V1-6a 部分**：闭环已用**假 back** 真二进制跑通；真 back 需 H6（生成 `MYNET_BACK_FACE_PRIV/PUB`）才能验签，按 §5 纪律不拿等价物冒充达成 |
| V1-7 | mynetd 侧结果回传：`transport/client.go:169` 曾是 `_ = res`，命令执行结果**被直接丢弃** | V1-2 | 节点执行完命令后，结果帧真的发到 gateway 并被转发到 back | gateway 侧收到结果帧的日志/断言 | ✅ 2026-09-24（管道两端均已验证；**触发方**待 V1-9） |
| V1-8 | mynetd localapi 缺 `relay` 域：`spec/local-api.md:19` 声称「✅ 已实现…已对真 back 实测」，但 `mynetd/localapi/server.go:56-65` 路由表里**没有** `/api/v1/relay`，且 `local-api.md:8` 指向的 `internal/localapi` 路径在 V2 树中**根本不存在** | V1-4 | GUI 经 `127.0.0.1:8096/api/v1/relay?path=` 能拿到平台态数据；`admin/*` 被拒 | 真浏览器 + 真 back 实测 | ☐ |
| **V1-9** | 节点面 HTTP 客户端：节点侧此前**没有任何**「签名请求经 gateway 到 back」的能力，导致三处同时断（`refresh-*` 无 bundle、`command_queued` 拉不到命令、心跳采完即丢） | V1-4, V1-7 | 节点能签名拉待执行命令并回报结果 | e2e：命令闭环三端日志对齐 | ✅ 2026-09-24（命令链已通；bundle 拉取与心跳上报见 V1-10） |
| **V1-10** | **（V1-9 的剩余面）** 节点面客户端目前只接了「拉命令 + 回报结果」两条。另两条仍缺：① bundle 拉取（`refresh-*` 实测报 `读 bundle 失败: …/var/bundle.json: no such file`）② 心跳上报（`cmd/mynetd/main.go:157` 采完 `_ = out` 丢弃；WS 只发 `{"t":"ping"}`） | V1-9 | 节点能拉到 bundle 并落盘；back 的 `monitor/heartbeat` 收到真指标 | e2e + back 侧 Redis 数据核对 | ✅ 2026-09-25 两面都合上了。② 心跳见 V4-2（`mynet_agent 141b9ed`）。① bundle 拉取见 `mynet_agent aa7090d`：`backclient.fetchBundle`（条件请求 + ETag）→ `bundleStore` 校验后原子落盘 `var/bundle.json` → 执行器只读；触发点是「启动即拉」+「`bundle_changed` 先拉后刷」。证据 `scripts/e2e/bundle_pull.sh` **11/11**（真 gateway + 真 mynetd + 真 mynet，非 mock）：起点确认文件不存在 → 落盘 generation=gen-e2e-1 → 假 back 收到带 `X-Node-Signature` 的请求 → `refresh-config` 退出码 0 并写出 `var/gnb/{node.conf,route.conf}` → 二次启动走 304 不重写。⚠️ 顺带查出 **CPI-44**：`OpNodeRefresh` 曾映射到执行器**不存在**的 `refresh-nodes` 域，故这条链即使拉到 bundle 也会以退出码 2 收尾；已改 `refresh-config` 并补跨包守护 `TestPlanRefreshDomainsExistInExecutor` |

**可并行**：V1-8 / V1-10 与轨道 A（指标采集）、轨道 B（版本注入）互不依赖，可同时开。

#### V1-9 实测证据（2026-09-24）

**真二进制全链 e2e**（真 `mynet_gateway` + 真 `mynetd` + 真 `mynet`，上游是会下发命令并接收结果的假 back）：

```
mynetd : transport: 节点面签名就绪（node_id=1245816621804034, conf=…/conf/mynet.conf）
mynetd : transport: connected ws://127.0.0.1:18711/v1/node/connect
relay  : HTTP 200 → presence {"online":true,"verified":true}          ← C-4 置位
notify : {"event":"command_queued","data":{"command_id":"9007199254740993"}} → 200
mynetd : command: 执行 mynet.metrics → op=metrics (id=9007199254740993) ← 词汇映射生效
gateway: result 已转发 path=/api/v1/monitor/commands/9007199254740993/result → 200
假 back 收到：
  GET   /api/v1/monitor/commands                            sig=有   ← 拉取（第 1 次）
  GET   /api/v1/monitor/commands                            sig=有   ← 再拉确认队列已空
  PATCH /api/v1/monitor/commands/9007199254740993/result    sig=有
        body={"success":true,"message":"命令执行成功","result":{"stdout":"{…hostname: MacBook-Pro-3…}"}}
```

**关键设计**：节点面 HTTP **复用 gateway 现成的 `/v1/relay`**，不需要给 gateway 加新面——
`relaypolicy.go:19-20` 的白名单本就包含 `/api/v1/monitor` 与 `/api/v1/heartbeat`
（注释即「含节点自身通道」），而 `ReverseProxy` 会逐字转发请求头。
签名口径两端已核对：back 的 `NodeHeartbeatAuthMiddleware::signedPath()` 返回
`'/'.$request->path()`（带前导斜杠），agent 侧签同一路径；**差一个斜杠就全 401，
且症状是「鉴权失败」而非「路径写错」**。测试里有一条反面断言专门锁这点。

**命令词汇映射**（用户裁定：agent 侧显式映射表）：back 的 `command` 是 `mynet.<op>`，
13 条里 9 条严格吻合，4 条历史漂移（`mynet.start`/`stop`/`restart`/`service.restart`）
走显式别名表。映射放在 `mynetd/commands/backmap.go`（命令名真值所在处）。
**不认识的命令必须按失败回报，不能静默丢弃**——这是 CPI-4 的核心教训
（`mynet.reboot` 曾让「重启节点」整整失效，且只在节点本地日志留一行）。

**词汇守护换载体**（用户裁定：退休 PHP 脚本、改用 Go 测试）：
删除 `mynet_ctl/scripts/check-command-{vocabulary,invocations}.php`——
理由是**节点/客户端没有 PHP 环境**，而命令名真值在 agent，守护必须长在真值旁边；
且这两个脚本此前已永久 `exit 2`（默认路径是旧布局的 `-integration` 后缀，
依赖的 SSOT `conf/command.*.json` 在 D-6 内化后已不存在），
并且**从未被任何 CI / Makefile / hook 引用**——这正是「永久失效却无人发现」的原因。
新守护 `mynetd/commands/backmap_test.go` 随 `make check` 每次跑，
用 `go/ast` 解析 `commands.go` 强制 `knownOps` 与 `Plan()` 的 switch **双向同步**。
**已做变异测试验证**：往 `Plan()` 塞一个字面量 case、或从 `knownOps` 删一项，守护都失败
（第一版只收 `ast.Ident` 漏了 `ast.BasicLit`，变异测试当场抓出，已修）。

**back 侧同步**（用户裁定：删掉 `action` 字段）：`NodeHeartbeatController` 的
`COMMAND_ACTION_MAP` 与 `mapCommandToAction()` 已删除，响应不再带 `action`。
删除前已确认无消费方（mynet_gui 零命中、mynet_agent 零命中、back 测试零断言）。
新增 `PendingCommandsContractTest`（5 测试 / 19 断言）锁响应形状，
与 agent 侧 `TestPullPendingCommandsParsesNumericIDs` **两端互锁**。

**门禁**：`mynet_agent` `make check` ✓（16 包，本轮相关包 98 项 PASS，`-race` 干净）；
`mynet_back` `make check` ✓ **144 tests / 2484 assertions**（连跑 3 次 + `--order-by=reverse` 均稳定）。

> 过程中修掉一个自己的测试缺陷：`test_returns_one_command_and_marks_executing`
> 单独跑过、进全量套件就失败——两条命令都用 `now()`，`created_at` 相同导致
> `order by created_at asc` 取哪条不确定。改为显式递增时间戳。**教训：测试自身的
> 时序假设也是缺陷**，「单独跑过」不等于「稳定」。

#### V1-7 实测证据（2026-09-24）与一处架构澄清

**单元测试**：`cmd/mynetd` 15 项（含用 **C-2 双端互认向量密钥**验签，证明结果帧的签名就是
back 会接受的那一个，而非自证）、`mynetd/transport` 全绿、`gateway` 33 项，**`-race` 干净**，
`make check` 通过（16 包 ok）。

**真二进制冒烟**（真 `mynet_gateway` + 真 `mynetd` + 真 `mynet`，上游用会回 `X-Node-Verified` 的假 back）：

```
mynetd : transport: 节点面签名就绪（node_id=1245816621804034, conf=…/conf/mynet.conf）
mynetd : transport: connected ws://127.0.0.1:18710/v1/node/connect
gateway: 节点接入 node=1245816621804034 conns=1
relay  : HTTP 200 → presence {"online":true,"verified":true}     ← C-4 闭环
notify : {"event":"route_changed"} → mynetd: event: route_changed → route.refresh 执行
mynetd : transport: 已回传命令结果（293 字节）                     ← V1-7 管道通
gateway: result 目标不在节点面白名单内 path=/api/v1/monitor/commands//result，丢弃
```

**⚠️ 架构澄清（冒烟测出来的，此前无人记录）**：网关下发的是**事件信封** `{event, data}`
（back 的 `GatewayNotifier` 只发 `command_queued` / `bundle_changed` / `route_changed`，
且 `data` 仅带定位信息），**不是命令本身**；命令要节点自己回 back 拉
（`GET /api/v1/monitor/commands`，即 pull 模型）。

而 `transport.HandleFrame` 此前把**每一帧**都当 `commands.Request{id,op,args}` 解析，
于是 `id`/`op` 全空、执行结果恒为 `unknown op: `、回报路径变成
`/api/v1/monitor/commands//result`——**WS 命令链从未真通过**（`long-range-plan.md:49`
的 R1-5「WS 命令下发 → mynet 执行 → 结果回传」☑ 亦属纸面）。同时
`Handlers.OnEvent` 这个本该处理事件的回调**从未被调用过**。

已修：`HandleFrame` 改为信封优先分派 + 保留裸命令帧兼容路径 + 无法识别的帧明确报错
（不再静默产出假结果）；`cmd/mynetd/events.go` 新增事件分派器，把 `route_changed` /
`bundle_changed` 映射到既有 `commands.Plan` 的 argv（不重抄命令表），
`command_queued` **明确记日志说明缺什么**而非静默忽略；并加单飞守卫——
多个 `mynet apply`/`refresh-*` 并发写 OS 路由是本项目踩过的坑。

上面那条 `gateway: … 丢弃` 日志正是**白名单在正确地拦一个畸形路径**（空命令 ID），
不是故障。

#### V1-4 / V1-5 实测证据（2026-09-24）

**relay 单元测试 12 项全绿**（`-race` 干净）：白名单内转发、method/body/头透传、上游 4xx 原样透传、
白名单外 403 **且上游不得被打到**、含 `%` 一律拒、缺 `path` 400、上游不可达 502、
**C-4 闭环**（上游回 `X-Node-Verified` → 该连接被置位）、SSRF host 逃逸拒绝、query 保留。
`TestC7_RelayExists`（把恒 502 判为 PASS，D3 反面教材）已删除并替换。

**对真 back 实测**（gateway `-upstream http://127.0.0.1:8080`，非 mock）：

```
POST /v1/relay?path=/api/v1/auth/login      → 200，拿到真 token（51 字符）
GET  /v1/relay?path=/api/v1/nodes  (带 token) → 200，真数据（node_name "real-0.32"）
GET  /v1/relay?path=/api/v1/nodes  (无 token) → 401 {"message":"Unauthenticated."}
GET  /v1/relay?path=/admin/dashboard         → 403，gateway 侧就拦掉并记日志：
     gateway: relay 拒绝（白名单外）path=/admin/dashboard from=127.0.0.1:62694
```

> 注：这四条正是 `spec/local-api.md:19` 声称「已对真 back 实测」的判据。
> **该声明此前是假的**（V2 树里 relay 域根本不存在，见 V1-8）；gateway 侧现在才真的成立。

**dist 产物**（`make dist`，五平台均含三个二进制）：

```
mynet_agent_darwin_amd64   mynet mynet_gateway mynetd webui.html   Mach-O x86_64
mynet_agent_darwin_arm64   mynet mynet_gateway mynetd webui.html   Mach-O arm64
mynet_agent_linux_amd64    mynet mynet_gateway mynetd webui.html   ELF x86-64 static
mynet_agent_linux_arm64    mynet mynet_gateway mynetd webui.html   ELF ARM aarch64 static
mynet_agent_linux_mipsle   mynet mynet_gateway mynetd webui.html   ELF 32-bit MIPS static
```

同时去掉了 dist 里的 `|| continue` / `|| true`——它们会让构建失败被静默吞掉、
`dist/` 看着齐全实际缺件（正是 D1 要防的纸面达成形态）。

#### V1-1 ~ V1-3 实测证据（2026-09-24）

**单元测试**：`gateway` 包 15 项全绿（新增 7 项真 WS 测试：C-3 无鉴权握手、缺 `X-Node-Id` 拒绝、
断开摘表、重连踢旧、`UnregisterIf` 竞态保护、Notify 真达节点、ping→pong）；
`cmd/mynet_gateway` 6 项全绿。两包 **`-race` 干净**。`make check` 通过。

**真端到端**（用真的 `mynetd` 二进制连真的 `mynet_gateway` 二进制，非 mock）：

```
gateway: mynet_gateway → http://127.0.0.1:18710/ (upstream=http://127.0.0.1:8080 conns=0)
mynetd : transport: connected ws://127.0.0.1:18710/v1/node/connect as node smoke-node-1
gateway: 节点接入 node=smoke-node-1 conns=1
healthz : {"data":{"conns":1},"success":true}
presence: {"data":{"node_id":"smoke-node-1","online":true,"verified":false},"success":true}
notify  : HTTP 403 {"message":"node not verified"}        ← C-5 门禁生效
（杀掉 mynetd）
gateway: 读结束 node=smoke-node-1: … EOF
gateway: 节点断开 node=smoke-node-1 conns=0
healthz : {"data":{"conns":0},"success":true}
presence: online:false
（SIGTERM）
gateway: 已关闭（退出时连接数 0）                          ← 优雅退出
```

**过程中修掉的真 bug**：`withVerifiedHook` 的 `verifiedRecorder` 只重写了 `WriteHeader`、
**没有透传 `http.Hijacker`** → WS 升级拿不到底层 TCP 连接 → `websocket.Accept` 返 **501**。
⚠️ 这个 bug **用 `httptest.NewRecorder` 测不出来**（它本来就不支持 Hijack），
只有真握手才暴露——正是 D2「测试必须打真的」的价值证明。已记入 `AGENTS.md` §4「Go / agent 类」坑表。

### V2 · 交付链真跑

**出口判据 G-V2 = 重做 G2**：`make dist` 出带版本号的打包产物 → 上传 ctl → ctl manifest 可拉、
校验和可核 → `mynet update` **真升级一轮**（旧版→新版→健康检查→失败可回滚）。

| ID | 内容 | 前置 | 验收判据 | 证据形式 |
|---|---|---|---|---|
| V2-1 | ctl `PRODUCTS` 增 `mynet_agent` | — | `validateProduct('mynet_agent')` 通过 | phpunit | ✅ 2026-09-24 |
| V2-2 | 修 `apps.json` 形状分歧 | V2-1 | Go 解析器与服务端一致 | Go 测试打真端点 | ✅ 2026-09-24 |
| V2-3 | 版本注入 + strip | — | 产物报真实版本而非 `dev`；体积下降 | 实测输出 | ✅ 2026-09-24 |
| V2-4 | 打包 + `checksums.txt` | V2-3 | 文件名与 ctl 一致；sha256 可核 | `make dist` 实测 | ✅ 2026-09-24 |
| V2-5 | 真 `upgrade.Fetch`（两步协议） | V2-2 | 拉到 artifact 列表 | e2e | ✅ 2026-09-24 |
| V2-6 | 真 `upgrade.Install`（校验+备份+原子替换+自检+回滚） | V2-5 | 正常升级成功；错 checksum 必须拒绝且不回滚 | e2e 两条 | ✅ 2026-09-24 |
| V2-7 | 版本比较改语义 | — | `0.9.0 < 0.10.0` | 单测表驱动 + 变异 | ✅ 2026-09-24 |
| V2-8 | 真升级一轮（重做 R4-3） | V2-5..7 | 旧版 → 新版，版本号确实改变 | e2e | ✅ **V2-8a**：agent 侧真二进制跑通（见下）。**ctl 侧**（上传两版 + 真 ctl 拉取）待 V2-8b |
| V2-9 | OpenWrt `mynet-agent` ipk：原先 `[ -f ./files/mynetd ] && … \|\| true` → **缺文件时静默产空包**；`PKGARCH:=all` 与编译产物不符 | V2-4 | 缺 `files/mynetd` 时**构建失败**；按 arch 分包 | 构建日志 + `tar tf` | ✅ 2026-09-24（静态+行为校验；真出 ipk 仍需 SDK） |
| V2-10 | CI：`mynet_agent` 新建 `.github/workflows/`（该 repo 目前**一个都没有**） | V2-4, H11 | tag 触发后 ctl 能拉到该版本 | Actions 记录 + ctl manifest | ☐（需 H11） |

#### V2 实测证据（2026-09-24）

**真升级一轮（V2-8a）**——本机静态服务提供两步协议索引，跑**真的 `mynet` 二进制**
（非注入假函数）：

```
升级前  mynet v0.0.1 / mynetd v0.0.1
$ MYNET_APPS_INDEX=http://127.0.0.1:18090 mynet update
  latest mynet v0.0.2 darwin/arm64        退出码 0
升级后  mynet v0.0.2 / mynetd v0.0.2
        bin/ 留下 mynet.bak / mynetd.bak（mynet_gateway 本次新增故无 .bak —— 正确）
```

负路径也验过：
1. **旧 TUI 被忽略**：索引里另挂 `mynet`(TUI) 的 v9.9.9（版本号故意更高）→
   未被选中，仍升到 v0.0.2。（`mynet` 是 tui 的别名且已 deprecated，
   映射到 ComponentMynet 会去装那个废弃客户端 —— 这是刻意不映射，不是遗漏。）
2. **校验和不符被拒**：篡改 v0.0.2 产物一个字节 → `sha256 不匹配`、
   退出码 1、mynet **仍是 v0.0.1**、且无 `.bak` 残留（在动任何文件之前就拒了）。

**版本注入**：三个二进制分别报出注入版本；`-s -w` 让 mynetd 由 9.7MB 降到 7.2MB。

**打包**：`make dist` 产出 5 平台 × 3 二进制 + 5 行 `checksums.txt`。

**过程中修掉的真问题**：
- `shared/utils/constants.go` 的 `AppVersion` 是 **const**，而 `-X` 只能覆盖变量、
  对常量无效**且不报错** —— 那句「构建时由 -ldflags 覆盖」从来没成立过。
- `KeepBackup bool` 的零值是 false，会让默认行为（删备份）与字面意思（保留）相反；
  已改为 `RemoveBackup`。
- `safeJoin` 原先靠 `filepath.Clean` 把 `..` 归一化掉（逃不出去但静默接受畸形归档），
  改为显式拒绝含 `..` 分量。
- `DIST_MATRIX` 用空格分隔时，`for x in $(VAR)` 会按 IFS 切词、变量里的引号是字面量，
  只得到一堆单词而非三元组；改用冒号分隔。

**门禁**：`mynet_agent` `make check` ✓（16 包）；`mynet_ctl` `make check` ✓
（**50 测试 / 378 断言**，1 项显式跳过 —— 见下）。

#### 顺带修掉：mynet_ctl 的门禁此前是**假绿**

装 `composer install` 后发现两处：
1. **lint 从未跑过**：`make lint` 是 `if [ -x vendor/bin/pint ]; then pint --test;
   else echo "(composer install 后启用 pint)"; fi` —— 本仓没有 `vendor/`，
   一直走 else 分支打印一句话就算通过。pint 首跑：44 个文件不符合风格。
2. **test 从未跑过**：`make test` 同样是「无 phpunit 就跳过」。首跑 50 项里
   **20 个 error，全部是 `MissingAppKey`** —— 本仓也没有 `.env`。

这与 mynet_back 的 **CPI-23 同源**：**两个 PHP 仓都缺 `.env`，而两边的门禁都发现不了**
（back 是 phpunit.xml 自带 env 所以测试全绿；ctl 是静默跳过）。
已分别建本地 dev `.env`（两个仓的 `.gitignore` 都已挡住）。

> ⚠️ **教训**：「无工具时跳过」型门禁等于没有门禁 —— 它把「环境没装好」伪装成「检查通过」。
> 新增门禁时，缺依赖应当**报错**而不是跳过；确实要跳过也必须打印明确的「已跳过」标记。


### V3 · 生产落地

**出口判据 G-V3**：生产 `https://api.mynet.club` 可注册登录；`gw.mynet.club` 可连；
备份**加密**且**恢复演练过**。

| ID | 内容 | 前置 | 验收判据 | 证据形式 |
|---|---|---|---|---|
| V3-0 | ~~Docker 路线~~ **已撤**：`deploy/{docker-compose.yml,caddy-compose.yml,Caddyfile}` 删除；路线改为**主机 + nginx + rsync** | — | — | ✅ 2026-09-24（用户裁定；见下「为什么撤 Docker」） |
| V3-1 | ~~`deploy/Dockerfile`~~ → **改为补主机部署入口 `manage-prod.sh`**（registry 称其为推荐路径但**文件不存在**） | — | `./manage-prod.sh deploy/sync/status/rollback` 可用；远端前置守卫生效 | ✅ 2026-09-24（守卫矩阵实测：合规→0；priv 0644/0640、APP_KEY 空、无 .env→各 1） |
| V3-2 | **按 H1 裁定改走 nginx**：`deploy/Caddyfile` 已删，其意图（TLS + api/gw 反代 + 安全头）转为 `deploy/nginx.conf` | H1 ✅, H2, H3 | `nginx -t` 通过 | ✅ 2026-09-24（语法实测通过，仅缺生产机的证书文件）；四个子域 curl 实测待 H2/H3 |
| V3-3 | ~~compose 收敛~~ **随 V3-0 一并作废**。gateway 在主机上以 systemd 运行 | V1-5 | gateway 主机可跑 | ✅ **V3-3a**：`mynet_agent/deploy/{systemd/mynet_gateway.service, gateway.env.example, README.md}` 备好，并按**与 unit 完全相同的参数**本地实测（构建→起→`/healthz` 返回 `{"data":{"conns":0}}`→`SIGTERM` exit 0；`MYNET_GATEWAY_UPSTREAM` 经环境变量生效，证明 unit 的 `EnvironmentFile` 那条路可用）。**生产安装待 H8**。⚠️ 原判据里的「redis 可达」**已作废** —— 见下方更正 |
| V3-4 | ~~compose healthcheck~~ **随 V3-0 作废**；健康检查改由 nginx 的 `/up` 与脚本 verify 承担 | V3-0 | `curl https://api.mynet.club/up` → 200 | ✅ 已并入 V3-1 的 verify_deployment |
| V3-5 | **重做 T3-3**：备份加密 + 恢复脚本 + **恢复演练** + 密码不走命令行 + cron 真装（原先是明文 tar.gz、无 restore、`-p"$DB_PASSWORD"` 进 `ps`、cron 只在 README 注释里） | — | 从备份**真恢复出一个可用库**；`ps` 里看不到密码 | ✅ 2026-09-24。新增 `deploy/{restore.sh,backup-drill.sh,install-cron.sh}` 并重写 `backup.sh`。演练在**隔离临时 MySQL 实例**里跑：造库→加密备份→恢复→**逐表 7/5/2 行数一致 + 内容一致**；另两条断言（备份确实加密、DB 口令不在 `mysqldump` argv）各做了一次**变异测试**，注入「不加密」与「口令进 argv」后都被抓住（见 §V3-5 实测证据） |
| V3-6a | **按 H7 裁定把 `back_priv` 改为 0600 文件**：文件优先，**权限过宽即拒绝且不回退 env** | H7 ✅ | 代码只从文件读；权限实测；缺/空文件明确报错 | ✅ 2026-09-24（10 测试；「不回退」那条是被测试抓出来的） |
| V3-6b | **补 `spec/node-keying.md` 的 back_priv 条目**（原先该文件 grep 0 命中 → 规范缺位）：形态、权限、轮换、泄露处置、为什么保护等级高于节点私钥 | V3-6a | 规范与实现一致 | ✅ 2026-09-24。新增 **§4.6 `back_face` 密钥**：形态与权限（含「权限过宽即拒绝」）、**不回退 env 的四种走向**（这条是控制成败所在）、轮换、泄露处置、以及为什么它比节点私钥更该保护。⚠️ 明确写出**轮换能力尚未实现**（无新旧并存、无工具），不含糊过去。顺带修 §5.2「back 侧尚未校验」（与 §4.5 矛盾）与 §8 一条已落地的待决 |
| V3-6 | `back_priv`/`back_pub` 生成并入 `crt` 凭证库、注入生产 | H6, V3-6a | 节点面 DH+HMAC 在生产真验通过 | 真机心跳 200 | ☐（需 H6） |
| V3-7 | 补 `deployment-registry.md` 的 stale 项 + 按 H4 把 back 管理面从 `admin` 改称 `console`（原估要同步 3 处） | V3-1..6 | 文档每条都能在盘上找到对应物；子域命名全库一致 | ✅ 2026-09-24。registry **重写**：逐条 `ls` 核对，**5 条路径根本不存在**（含两个退役仓的）→ 删除；矩阵补上 agent / openwrt / desktop / gnb / utc 与「分发到节点」这一类；退役仓单列并写明承接者；**7 处 `<待补>`** 改为如实描述（监控面整体**未建立**，不是「已有什么只是没填」）。命名同步 **5 处**（比预估多 2 处：`ecosystem.md:65` 与 `projects/mynet_back.md:6`）；两个历史文档只加说明、不改原文 |
| V3-8 | **T3-4 真机注册**：非 VM 真机走完「注册→密钥→连接→互通」 | H2, H5, H6, H8, V1, V3-6 | 真机 `node_id` 落库、心跳在线、与另一节点互通 | 真机实测 + back 数据 | ☐（需人工闸门） |
| V3-9 | **G4**：外网可注册、可互通、`mynet update` 升级不断线 | V2-8, V3-8 | `long-range-plan-t.md:121-123` 三条判据全过 | 外网实测 | ☐（需人工闸门） |

> **V3-3 的一处判据更正（2026-09-24，做 V3-3a 时核出来的）**：
> 原判据是「gateway 主机可跑 + **redis 可达**」，等于把 **V4 的前置写进了 V3**。
> 核查 `NewRedisPresence` 的调用点：**没有任何非测试调用** ——
> `cmd/mynet_gateway/main.go` 根本不接 redis（`grep -c -i redis cmd/mynet_gateway/main.go` → 0），
> 进程跑的是内存 presence。这与 V4-5 记的是同一件事。
> 所以「redis 由主机提供」在**当前实现下不成立**：给 systemd unit 配了也不会被使用。
> 只有多实例才需要它，那是 V4-5 / V4-6 —— V3 阶段不必背这个前置。

#### 为什么撤 Docker（V3-0，2026-09-24）

`deployment-registry.md`（部署元数据 SSOT）写的是：

| 项目 | 服务器 | 部署路径 | 部署命令 |
|---|---|---|---|
| mynet_ctl | `10.182.236.180`（公网 `52.77.179.176`） | `/data/app/mynet.club/ctl` | `bash scripts/deploy/deploy.sh`（rsync + 远程 artisan） |
| mynet_back | 同上 | `/data/app/mynet.club/api` | `./manage-prod.sh deploy` |
| mynet_gui | 同上 | `/data/app/mynet.club/my/public` | `bash deployment.sh`（本地 build + rsync dist/） |

三者**共置同机、各是独立 nginx server block**，证书在
`/etc/letsencrypt/live/mynet.club/`，**主机 nginx 终结 TLS**。部署方式是 **rsync**。

而那三个已删的文件（`deploy/docker-compose.yml` 等）把 app/db/gateway 装进容器、暴露 8080，
与这个拓扑不符（caddy-compose 更直接占宿主 80/443，与现有
`listen 172.26.3.227:443` + 前置公网 LB 冲突 —— H1 已裁定复用现有 nginx）。

**原计划条目「V3-1: deploy/ 下没有 Dockerfile、`build: .` 必失败」把问题定错了**：
它把「compose 无法构建」当成缺陷，而正确判断是**这个 compose 与该项目的部署模型不符**。
修法不是补 Dockerfile 去迎合一个本不该存在的 compose，而是撤掉它、补上真正缺的
`manage-prod.sh`。

> 教训：**先确认技术选型是否成立，再补实现**。我照着计划表写了 Dockerfile
> 之后才被问「为什么用 Docker」—— 那一步本该在动手前做。
> 计划表里的条目也是可能定错的，不是免检的。
>
> 相关：`repo-migration.md` 里 mynet_back 的 Dockerfile 若曾被列入搬运清单，同样作废。

### V4 · 规模化与可观测

| ID | 内容 | 前置 | 验收判据 | 证据形式 |
|---|---|---|---|---|
| V4-1 | **先补采集**（指标源头现在是空的）：`CPUPercent`/`InterfaceUp`/`Peers`/`PeersUp`/`Addrs` 真赋值；`metrics_vpn.go:20-30` 解析落地（现在 `_ = fields` 空转）；darwin 分支真读（现在读了就丢 → macOS 全 0） | — | 五个平台字段在 linux/darwin 上均非 0 且与 `gnb_ctl -s`/`wg show` 一致 | ⚠️ **V4-1a 部分达成**（2026-09-24）。**darwin 侧真机实测通过**：`mynet metrics --json` 六字段全非 0，且 `mem_total_mb=16384` 与 `sysctl hw.memsize` 一致、`load1` 与 `vm.loadavg` 一致、`interface_up=27` 与 `ifconfig -lu` 去掉 lo0 后一致；两次变异测试（换回空壳 / 不跳过本机）都被测试抓住。**未达成**：① linux 侧代码写了但开发机是 macOS，未实测；② `gnb_ctl -s` 的解析只有**按上游源码 `gnb_ctl_dump.c:38-180` 推导的夹具**（gnb_ctl 是 Linux 二进制，本机 `exec format error`）；③ wg 侧本机 `wg show <iface> dump` 需 root（实测 Permission denied），只复用了 driver 已有的 `TestParseWGDump`。按 §7.1 纪律**不勾原 ID** |
| V4-2 | **心跳真上报**：`cmd/mynetd/main.go` 原来采完 `_ = out` 丢弃；WS 只发 `{"t":"ping"}` | V4-1, V1-2 | back `monitor/heartbeat` 收到真指标并落 Redis | ✅ 2026-09-24（agent `141b9ed`）。真 back（Laravel + sqlite）+ 真 gateway + 真 mynetd **全链路跑通**，状态码演进 `401 → 422 → 500 → 200` 每一步都对应一个真问题（见 CPI-37 / CPI-38 与提交说明）。**Redis DB1 核对**：`node:status:<id>` 里 `status=online`、`cpu_usage=31.78`、`memory_usage=75.1`、`disk_usage=6`、`TTL=1103`，zset `node:status:all` 含该节点；sqlite `node_monitor_snapshots` 落 `uptime_seconds=179293`（本机真值）。15 个新测试 + `go test -race ./...` 全绿 |
| V4-3 | metrics 端点：localapi 现在只绑 `127.0.0.1:8096`（`server.go:18`）→ 远端不可抓取。需决定暴露方式（gateway 代理 / 独立端口 / push） | V4-1 | 远端可抓到指标 | 跨机 `curl` 实测 |
| V4-4 | 结构化日志：`shared/logger` 原来**零消费者**（各处实际都是裸的 `fmt.Fprintf(os.Stderr,…)`）；back `config/logging.php` 无 json 通道 | — | 关键路径日志可被机器解析 | ✅ 2026-09-24。**agent 侧**：`shared/logger` 重写为 `log/slog` 封装（text=logfmt / json 双模，默认 stderr 以便进 journald），接入 mynetd 与 mynet_gateway 的关键路径。实测样本：json 模式 **3/3 行合法 JSON**，text 模式是 logfmt，且 `source` 指向真正的调用方（`main.go:55`）而不是 logger 自身 —— 后者是测试抓出来的（包装函数被内联，slog 的 AddSource 会偏）。**back 侧**：新增 `json` 通道（`JsonFormatter` + `BATCH_MODE_NEWLINES`，一行一条），实测 **9/9 行合法 JSON** 且 `context` 带上结构化字段。8 个 logger 测试，含「未 Init 也能用」与「配错格式必须报错而不是静默用默认」 |
| V4-5 | **presence 真接线**（重做 C-6）：`Registry.SetPresence` 无调用方、`Notify` 从不调 `presence.Publish`、`NewRedisPresence` 仅被测试调用 → 实际恒内存 presence | V1, Redis 部署 | 节点连 A、back 通知 B → **B 能推达**（现在必 404，`server.go` 的 `Notify`） | ✅ 2026-09-24（agent `9255f40`）。**根因不止「漏接线」，是接口设计缺陷**：`Subscribe`/`Publish` 的通道元素是 `struct{}`、`Publish` 还硬编码 `{"event":"queued"}`、`Subscribe` 收到消息后 `_ = msg` 丢弃 —— 跨实例只能传「有事」传不了「什么事」，A 收到信号也拼不出要推的帧（而 back 是发起方、没有「拉取事件」的接口）。已改为带帧原文（`{event,data}`），并补上 `Register`→`SetOnline`、`Unregister`→`SetOffline`、gateway main 读 `MYNET_GATEWAY_REDIS_*` 建 presence。**判据实测**：`gateway/cross_instance_test.go` 用两个 Registry（= 两实例）**共用真 Redis**（6379/DB2），节点连 A + MarkVerified → B（本地无连接）Notify → A 的 `conn.send` 收到完整帧（含 `command_id`），`--- PASS: TestCrossInstanceNotifyDelivers (0.21s)`；把跨实例分支短路的变异测试报 `errNotOnline → node not online` 即还原前的原行为。⚠️ 证据形式说明：这是**单进程双 Registry + 真 Redis pub/sub**，pub/sub 链路真跑通了；**进程级双实例**（两个 gw 进程）待 gw1/gw2 裁定后补 —— 那属于 V4-6 的范畴。另两条守住边界的测试：离线仍 404、本实例连接直接投递不绕 Redis |
| V4-6 | 双 gateway 其余三处：`Verified` 不共享（`MarkVerified` 只写本地）、`Register` 踢旧连接只本地生效（同节点双注册不去重）；原写的第三处「compose 里**无 redis 服务**」**随 V3-0 撤 compose 已不适用**（主机部署下 redis 由主机提供） | V4-5 | 两实例下：鉴权状态一致、同节点重复连接被去重 | 双实例 e2e |
| V4-7 | 真 Redis 的 HSet/Publish/Subscribe **零测试**（现在只覆盖降级路径）→ 补集成测试 | V4-5 | 真 Redis 下测试通过（非 mock） | 测试实测输出 |
| V4-8 | 告警：全仓 **0 处**。至少覆盖节点离线、心跳超时、命令失败率 | V4-2 | 触发条件下真发出告警 | 演练记录 |
| V4-9 | **T4-6 真 50+ 节点**：见 §5，本地不可验证 | H10, V4-2 | — | — |

### V5 · 生态与公测

| ID | 内容 | 前置 | 可否本地验证 |
|---|---|---|---|
| V5-1 | **T5-2 macOS launchd**：plist 已存在但 `mynet install` 不装 unit、不 `launchctl load`（`cmd_install.go:45` 只打印「请按平台执行」）；`uninstall` 是 print-only 空操作 | V4-1（否则装上也是瞎的，darwin metrics 全 0） | ✅ **当前就是 darwin/arm64 开发机** |
| V5-2 | **T5-1 Windows**：dist 不构建 windows；`routing_win.go:46` 硬编码 stub、`:45` netstat 输出被丢弃；无 `firewall_win.go`/`service_win.go`；install/uninstall 无 windows 分支；无任何安装器（.msi/.nsis） | H9 | ⚠️ **交叉编译可出包，运行不可测** |
| V5-3 | **T5-3 移动 relay**：spec 要求的是 **mynetd 侧** `GET /api/v1/relay?path=`（`control-plane.md:278-289`），但 localapi 路由表（`server.go:55-67`）里没有；移动端载体只有 cordova/capacitor 配置壳 | V1-4, G4 | ⚠️ gateway+back 同机可验转发；「移动端」需真机+公网 |
| V5-4 | **T5-4 用户文档**：help 索引 47 引用 / 5 实存；无安装指南 | V5-1（安装流程要真） | ✅ 完全可本地完成 |
| V5-5 | **T5-5 公测清单**：`security_audit.sh` 7 组中 6 组已复核通过，但**都是字符串 grep**（E 项靠注释即通过，不代表行为正确）；F 项未跑；无隐私政策 | G4, H12 | ❌ 需生产 + 人工法务 |

---

## 5. 不可验证项的处置（不许用等价物冒充达成）

三项在当前条件下**物理上无法真验证**。规矩是：**可以做本地等价物，但不得据此勾选计划表**。

| 项 | 硬约束 | 本地等价物（可做） | 真验证（需人工条件） | 勾选规则 |
|---|---|---|---|---|
| **T4-6** 真 50+ 节点心跳 | 真实资产仅 **3 台**（`scripts/inventory/hosts.sh:3`）；现有 `bench_1k.go` 是进程内合成，与心跳无关 | 容器化起 50 个 mynetd 实例连本地 gateway，测**连接数与心跳吞吐**（需 V1 + V4-2 先完成） | 50+ 真设备或等价集群 + 类生产 back（H10） | 等价物跑通 → 记为 **T4-6a（合成）**；真集群跑通 → 才勾 **T4-6** |
| **T5-1** Windows | 无 Windows 主机；且 gnb/wireguard 在 Windows 的驱动可用性**未证** | 交叉编译出包 + 静态检查 + 在 CI 的 windows runner 上跑单测 | Windows 真机装载、起服务、建隧道（H9） | 出包 → 记为 **T5-1a（可构建）**；真机跑通 → 才勾 **T5-1** |
| **T5-5** 公测清单 | 需生产环境 + 外网域名/证书 + 法务 | `security_audit.sh` 从**字符串 grep 升级为行为断言**（现在 E 项靠注释即通过）；补隐私政策草稿 | 生产环境安全复检 + 法务签署（H12） | 审计升级 + 草稿 → 记为 **T5-5a**；生产复检 0 高危 + 法务过 → 才勾 **T5-5** |

> **为什么这条要写死**：`long-range-plan.md:69` 的「1k 会话 + 3k 命令 **224k/s**」就是前车之鉴——
> 数字很漂亮，但 `bench_1k.go:37,49` 的执行器是 `return "ok"` 的桩，无网络、无 WS、无心跳。
> 而 `long-range-plan-t.md:153` 自己写着「**合成压测不能替代真集群**」。
> 判据写对了，勾选时却没遵守。

---

## 6. 验收纪律（防再次纸面达成）

本轮暴露的不是「某几个功能没做」，而是**验收机制没能拦住纸面达成**。
以下五条升级为硬规矩，写进 `AGENTS.md` §7 DoD：

| # | 规矩 | 针对的真实案例 |
|---|---|---|
| **D1** | **返回 error 的桩不算实现**。凡是 `func(...) { return fmt.Errorf("…未注入…") }` 这类占位，其所属项**一律不得勾选** | `upgrade/service.go:47-52` 的两个桩，R4-3 却勾了 ☑ |
| **D2** | **测试必须打真端点，不许「读 fixture，缺失即 skip」**。skip 掉的测试等于没有测试 | `apps_index_test.go:12-16` 读 fixture 且缺失即 `t.Skip` → 从未打过真 ctl 端点，形状分歧因此潜伏 |
| **D3** | **不得把未实现的行为断言成期望值**。若测试锁死了一个错误状态，它比没有测试更糟 | `gateway/server_test.go:72-83` 把恒 502 判为 PASS |
| **D4** | **勾选项必须附实测输出**，不接受「代码已写」作为证据。证据形式见各波次表格的最后一列 | 五处假勾选无一附实测输出 |
| **D5** | **字符串 grep 不等于行为正确**。安全审计若只 grep 注释/关键字，必须显式标注为「静态检查」，不得计入「安全复检通过」 | `security_audit.sh` E 项靠 `server.go:3` 有「不鉴权」注释即通过 |

**执行方式**：勾选任何一项前，先问「D1–D5 有没有被触发」。若无法给出实测输出，
就把它记为 `<ID>a`（部分达成）而不是勾掉原 ID。

---

## 7. 风险

| 风险 | 影响 | 处置 |
|---|---|---|
| **人工闸门交付周期长于工程** | H2/H3/H5/H6/H8 任一项拖延，V1/V2 做完也上不了线，G4 无限期后延 | ✅ H1/H4/H7 三个**裁定类已于 2026-09-24 决断**（不依赖工程）。剩下的是**执行类**：H2（DNS）+ H3（证书 SAN）可立即办；H5（DBA）+ H6（back_priv 生成）在 V1 期间并行推进；H8（真机）G4 前到位即可 |
| **gateway 是全新工程量，不是补完** | V1 六项里 V1-2（真 WS 握手）与 V1-4（relay 转发 + 白名单）是从零写，估时容易乐观 | V1 内部再切小步：先 V1-1+V1-3（能起、状态表真）→ 再 V1-2（握手）→ 再 V1-4（relay）。每步独立可验 |
| **修 apps.json 形状会同时动 ctl 与 agent** | V2-2 两端契约变更，改一边漏一边就静默失效 | 以 **ctl 服务端为准**改 Go 解析器；同一次改动里让 `apps_index_test.go` 打真端点（D2），形状分歧即刻暴露 |
| ~~生产 nginx 与 Caddy 冲突未裁定就先做部署物~~ | ✅ **已解除**：H1 裁定复用现有 nginx，V3-2 已改为 nginx server block 路线，Caddy 方案归档 | — |
| **子域改名波及多处文档** | H4 把 back 管理面从 `admin` 改称 `console`，`deployment-registry.md:13` / `ecosystem.md:125` / `brand-guide.md:152` 三处仍是旧写法，漏改会导致部署与文档不一致 | V3-7 里一次性同步，并用 grep 全库核对 `admin.mynet.club` 的每处出现 |
| **撤勾引起进度观感倒退** | 计划表从「R/T 段几乎全绿」变成「多处红」 | 这是**校正而非倒退**：假绿会让每个后续会话基于错误基线决策，代价远高于观感。撤勾处均已注明「→ 见 `long-range-plan-deep.md` §0」 |
| **框架上游不修** | CPI-34（`route:list` 崩）/ CPI-35（通知面不可用）长期挂着 | 两条都不在关键路径上（GUI 已降级、路由可 grep）。**不在 vendor 打补丁**（§1.8），定期跟进上游 |
| **Windows 驱动可用性未证** | 即使补完代码，gnb/wg 在 Windows 可能根本跑不起来 | H9 之前先做**纸面调研**（gnb 上游有无 windows 构建、wg 在 windows 的官方支持），结论写进 T5-1 前置 |

---

## 8. 与九条主线对照

| 主线 | 真实落点（校正后） |
|---|---|
| ① P2P UDP 真通 | ✅ 已达成（T1 全段，三场景 e2e） |
| ② gnb/wg 双实现 | ✅ 已达成（T1-2..6） |
| ③ 协议/目录不破坏 | ✅ 全程 guard B1 有效 |
| ④ back 社区链接 | ✅ 已达成（T2-2/2-3，实测落库） |
| ⑤ agent 自动化 | ⚠️ **半成**：命令链在本地 e2e 通，但**在线升级是纸面**（V2）、**gateway 不存在**（V1） |
| ⑥ 本机 UI → gateway → back | ⚠️ **半成**：GUI → back 直连已通（T2-6），但**经 gateway 的那条通路不存在**（V1） |
| ⑦ proxy | ⚠️ gnb 原生 proxy 意图链已落（R5-3），但 **relay 域恒 502、白名单未接线**（V1-4） |
| ⑧ 节点服务 | ✅ 域层与 HTTP 层实测通（T2-4，本日 20 项测试） |
| ⑨ 多平台 | ❌ **linux/openwrt 真通，darwin 半成，windows 纸面**（V5-1/V5-2） |

---

## 9. 下一步（本轮之后立即要做的事）

1. ✅ **V0-2 撤勾已完成**：`long-range-plan.md`（R4-1/R4-2 降为「部分」、R4-3/R4-4+G2 撤勾）、
   `long-range-plan-t.md`（T3-3 撤勾）、`dev-plan.md`（C-6 撤勾、F-4 降为「部分」），
   每处均附 `file:line` 依据并指回本文 §0。
2. ✅ **V0-5 三个裁定已完成**（2026-09-24）：H1 复用 nginx · H4 `console`=租户管理 / `admin`=平台管理 ·
   H7 `back_priv` 用 0600 文件。已记入 `mynet-overview.md` §2.2 与 §15。
3. **V1-1 开工**（`cmd/mynet_gateway` main）——拱顶石第一块，不依赖任何人工闸门。
   顺序：V1-1 + V1-3（能起、状态表真）→ V1-2（WS 握手）→ V1-4（relay + 白名单接线）。
4. **并行轨道**（与 V1 互不依赖，可派子代理）：
   - 轨道 B = V2-3/V2-4（版本注入 ldflags + strip + 打 .tgz 带版本号 + `checksums.txt`）
   - 轨道 D = CPI-30（`.env.production` 死文件，**P1，生产前置**）+ CPI-32（业务拒绝返 500，**P1，可观测性前置**）
   - 轨道 A = V4-1（指标采集补全，现在五个字段全仓从未被赋值）
   - 轨道 C = V5-4（用户文档：help 索引 47 引用 / 5 实存）
5. **需要你办的执行类闸门**：H2（DNS：`api`/`gw`/`console`/`admin`）+ H3（证书 SAN 确认）可立即办；
   H5（DBA 建库）+ H6（`back_priv` 生成入凭证库）在 V1 期间并行推进。
6. **仍待你裁定**：H4b（`gw` 单入口 vs 现在就开 `gw1`/`gw2`）、H9（Windows 主机）、
   H10（50+ 节点集群）、H11（GitHub 单 repo secrets + 打 tag）、H12（隐私政策/法务）。
