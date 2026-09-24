# MyNet 全景：从 P2P 组网到平台生态

> 本文按「用户定义的九条主线」组织，逐条说明 MyNet 是什么、现状到哪一步。
> **配套**：[`repo-migration.md`](repo-migration.md)（仓库怎么建、代码怎么搬）·
> [`dev-plan.md`](dev-plan.md)（建完做什么，含可跟踪的计划顺序表）。
> 三份文档以 **`dev-plan.md` 的任务 ID 为唯一标识**。
> 阅读时请特别注意 **【现状】/【目标态】** 的区分——这是理解 MyNet 时最容易出错的地方。

---

## 0. 材料来源与可信度分层

| 层级 | 来源 | 采信度 |
|---|---|---|
| A | 各仓**实际源码** | 权威。本文所有「现状」判断以此为准 |
| B | `mynet_ctl/docs/knowledge-base/spec/*` | 权威**设计契约**，但部分标着「设计稿」，不等于已实现 |
| C | 各仓 README / `docs/` | 参考。**已发现多处与代码不符**（见 §11） |

**中央知识库的位置**（重要，容易被忽略）：
`mynet_ctl/docs/knowledge-base/` —— 它不只是 ctl 的文档，而是**整个 MyNet 项目的文档中枢**，
包含 `mynet.md`（总参考）、`ecosystem.md`（生态全景）、
`spec/unified-platform-architecture.md`（**L1–L7 分层权威定义**）、
`spec/control-plane.md`（控制面）、`spec/driver-abstraction.md`、`spec/node-keying.md`、
`spec/proxy.md`、`spec/local-api.md`、`spec/data-models.md`、
`projects/mynet_*.md`（一仓一篇），以及 `conventions.md`（命名/SSOT 约定）。

**知识库内部也有新旧线**：`spec/proxy.md` 等已标「**已冻结（旧线）**」，
当前实现依据是 `spec/README.md` 声明的 **`mynet_agent`（`mynetd`）**。

---

## 1. 九条主线速览

| # | 用户定义 | 承担者 | 状态 |
|---|---|---|---|
| 1 | 底层逻辑是 P2P UDP 组网 | `mynet_gnb`（opengnb） | **成熟**（1.6.8 + 本地补丁） |
| 2 | gnb / wireguard 两种 P2P 实现 | `mynet_agent/driver/{gnb,wireguard}` | gnb **落地**；**wireguard 未实现** |
| 3 | 各自协议与目录结构不破坏 | 补丁包机制 + 驱动写入边界 | **扎实** |
| 4 | back 中心化社区化链接、交换公钥 | `mynet_back` + `mynet_gui` | **成熟**（含安全隐患，见 §11） |
| 5 | 自动发现 / 自动更新 | `mynet_agent`（mynetd/mynet）+ `mynet_ctl` | **基本落地** |
| 6 | 界面交给本地：mynetd→gateway→back | `mynet_agent`（gateway 已实现） | agent 段**已实现**；**UI→mynetd 未接** |
| 7 | 通过部分节点访问到不了的地方（proxy） | gnb `proxy on` / `mynet_proxy` | **两代并存，新线已定** |
| 8 | 节点提供服务（如 web） | `mynet_back` 的 `services` 建模 | **已建模**，节点侧靠外部服务 + 路由 |
| 9 | 支持更多平台/终端 | `mynet_openwrt` / `mynet_desktop` | OpenWrt **成熟**；desktop **占位** |

---

## 2. 项目地图

### 2.1 有效项目 vs 冻结归档

**有效项目**（2026-09 有提交）：

| 项目 | 技术栈 | 角色 |
|---|---|---|
| `mynet_gnb` | C | opengnb fork：构建、发布、**本地补丁包** |
| `mynet_agent` | Go | 控制面：`mynetd` + `mynet` CLI + `mynet_gateway` + `mynet_proxy` + `mynet-updater` |
| `mynet_back` | Laravel 13 / PHP | 业务权威：用户/租户/节点/路由/密钥/服务/社交 |
| `mynet_ctl` | Laravel | 分发中心 + **知识库中枢** |
| `mynet_ui` | Vue 3 + Vite + Tailwind | 本机 WebUI（静态） |
| `mynet_gui` | Vue 3 + Quasar | 云端社区管理前端（服务 back） |
| `mynet_desktop` | Shell | 非 OpenWrt 平台适配（**占位**） |
| `mynet_openwrt` | Lua / LuCI | OpenWrt 适配（**最成熟**） |
| `mynet_utc` | C | UDP→TCP 隧道转换器（可选组件） |

**冻结归档**（不再演进）：`mynet_client`、`mynet_client-hotproxy`、`mynet_tui`、`mynet_lua`、
`mynet_main`、`mynet_saas`、`mynet_web`、`mynet_front`、`mynet_back_old`、
`mynet_back_20250904`、`mynet_backup_20250610_155701`。

> `ecosystem.md` 的目标线修订（2026-09）明确了「**产品本体与平台适配分离**」：
> 产品本体 = `mynet_agent` + `mynet_ui`（只写一份）；平台适配 = `mynet_desktop` + `mynet_openwrt`（一平台一份）。

### 2.2 域名与部署

| 域名 | 项目 |
|---|---|
| `api.mynet.club` | `mynet_back`（业务 API） |
| `ctl.mynet.club` | `mynet_ctl`（分发 + 知识库） |
| `my.mynet.club` | `mynet_gui`（**用户交互前端**） |
| `www.mynet.club` | `mynet_web`（官网 / 项目主页） |
| `idx.mynet.club` | GNB index 节点（`address.conf` 里的索引服务） |
| `console.mynet.club` | **租户管理**（= back 的管理面） |
| `admin.mynet.club` | **平台管理**（框架 platform/operator SPA） |
| `gw.mynet.club` | `mynet_gateway` 公网入口（单入口；多实例时再开 `gw1`/`gw2`） |

⚠️ **2026-09-24 裁定（H4）**：`console` = 租户管理，`admin` = 平台管理。
此前 `deployment-registry.md:13` / `ecosystem.md:125` / `brand-guide.md:152` 把 **back 管理面**
写作 `admin`，与 `unified-platform-architecture.md:100,130`（`adm` = 框架 platform/operator SPA，
「不在 back」）冲突。裁定后：**back 管理面改称 `console`**，`admin` 让给平台管理，`adm` 弃用。
上述三处文档待同步。

⚠️ **2026-09-24 裁定（H1）**：生产入口**复用现有 nginx**（`172.26.3.227:443`，前置公网 LB，
证书 `/etc/letsencrypt/live/mynet.club/`），**不引入 Caddy**。
`mynet_back/deploy/{Caddyfile,caddy-compose.yml}` 归档或删除；api/gw/console/admin 各加 nginx server block。

---

## 3. ① 底层是 P2P UDP 组网

数据面**完全由 gnb 承担**，MyNet 自己的代码不碰 UDP 组网逻辑。这一点在各层都有明确表述：

- `mynet_back` 不碰数据面。`routes` 表只是「源节点 → 目标节点 + 可访问前缀」的**授权记录**，
  back 把它投影成配置包下发给 agent，转发由节点自行 P2P 完成。
- `mynet_agent` 只生成 `node.conf` / `route.conf` / `address.conf` 驱动 gnb，
  数据面二进制在 `driver/gnb/bin/gnb`。
- `mynet_gnb` 就是 opengnb 本体（fork），协议细节见 [`refs/opengnb.md`](refs/opengnb.md)。

**由此推出的关键结论**：MyNet 是**控制面/数据面彻底分离**的系统。
它自己解决的是「**谁和谁应该连、怎么让它们连上、连上后允许访问什么**」，
而「怎么在 UDP 上打洞、怎么加密、怎么转发」全部委托给驱动。

---

## 4. ② gnb / wireguard 两种实现 —— 现状：只有 gnb 落地

这是**最需要如实说明的一条**。

**抽象层已经为两者设计好**（`mynet_agent/internal/domain/`）：

- `VPNDriver`（只读能力面）：`GetDriverType / IsAvailable / IsProcessAlive /
  GetAllPeers / GetOnlinePeers / GetPeer / GetLocalPeer / GetNetworkSummary / Refresh`
- `VPNLifecycleDriver`（写配置 + 生命周期）：`Capabilities / WriteConfig / Start /
  Stop / Reload / Status`——**注释明说「只定义契约，尚未有实现」**
- `DriverCaps` 如实声明能力差异：`HotReload / Discovery / Proxy / CustomKeyUpload`，
  并注明「gnb 有 P2P 索引发现与内建 proxy 标志，wireguard 没有等价物。
  调用方必须按本结构分支，而不是假设『换了驱动功能一样』」

**配置意图层也已双驱动化**：`BundleEnvelope` 含
`NetworkIntent` + `GnbIntent` + `WireGuardIntent`，且 applier 强制校验互斥
（`driver=gnb 不得携带 wireguard intent`，反之亦然）。

**但实现只有 gnb**：

| 项 | 状态 |
|---|---|
| `GNBDriver` | 实现 `VPNDriver` + `Capabilities` + `Status` + `WriteConfig`，但**未满足完整 `VPNLifecycleDriver`** |
| WireGuard 驱动 | `driver/wireguard/` **是空目录**；工厂直接返回「WireGuard 驱动将在 v0.10.0 版本支持」 |
| WireGuard 密钥 | schema 要求 `key_algorithm=curve25519`，**无 Go 生成/落盘实现** |

**为什么这条重要**：MyNet 的核心设计原则是
「**上层业务逻辑（好友、连接、密钥、授权）在 GNB 和 WireGuard 之间完全统一，
驱动差异从驱动编排层开始向下分化，不泄漏到业务对象**」。
抽象已经按这个原则建好，但第二条腿还没装上。
所以现在谈「两种实现」时，准确说法是：**架构上为两者预留，实现上只有 gnb**。

---

## 5. ③ 不破坏各自的协议与目录结构 —— 已制度化

这条**做得最扎实**，有两个互相独立的机制。

### 5.1 补丁包机制（针对 gnb 源码）

`mynet_gnb` 对 opengnb 的改动**不以「改源码」的形式存在**，而是以
「相对上游某个 commit 的 `git format-patch`」形式归档：

```
packages/1.6.8-c411f14ee647/
├── p1-hot-restart/      热重启（SIGHUP / gnb_ctl -w core.flag=2，PID 不变、继承 UDP socket）
├── p1-hot-restart-v2/   热重启 v2（5 补丁，含修 secure index 挂死、日志 fd 泄漏）
├── p2-route-proxy/      node.conf 新增 `proxy on`（4 补丁）← 直接对应第 7 条
├── p3-log-hot-config/   运行时日志热配置（6 补丁，依赖 P1 v2）
└── dist/                打包好的 tgz
```

- `src/` 保持**上游树原样**，补丁在 `src/` 下按相对路径应用；
- `apply.sh` / `revert.sh` 支持应用与回滚，`scripts/apply_packages.sh` 按
  **P1v2 → P2 → P3** 顺序幂等应用，且**不静默降级**；
- 升级上游用 `packages/rebase.sh`（`git am -3`）重基，改动不散进工作区；
- 源码同步用 `scripts/sync_source.sh`：`git fetch --depth 1 <ref>` → `rsync -a --delete` 到 `src/`
  → 提交 → 打 tag → 追加 `sources/history.ndjson`（每行记 repo/ref/commit/diffstat）。
  版本双轨记录：`src/version`（当前 `1.6.8`）+ `sources/history.ndjson`。

> 这个机制的价值：**上游升级只需重基，本地改动与上游代码物理分离**。
> 这正是「不破坏 gnb 自己的目录结构」在工程上的落地方式。

### 5.2 驱动写入边界（针对运行时配置）

`mynet_agent` 的所有配置写入**限定在 gnb 原生目录内**：

```
driver/gnb/
├── bin/{gnb,gnb_ctl,gnb_crypto,gnb_es}
└── conf/{nodeId}/          ← 每个节点一个目录，nodeId 为 16 位数字
    ├── node.conf           节点运行配置
    ├── address.conf        索引节点地址列表
    ├── route.conf          路由配置表
    ├── ed25519/{peer}.public   对等节点公钥
    ├── security/{nodeId}.private|.public   自身密钥（0600 / 目录 0700）
    ├── gnb.map             运行时映射表（gnb 自己产出，agent 只读）
    └── gnb.pid
```

三条硬约束：

1. **写入路径硬编码** `{gnbBaseDir}/conf/{nodeID}/`，只产出上述文件；
2. **`gnb.map` 由 gnb 自己生成**，agent 只定位和读取（`gnb_ctl -s` 解析），不手搓；
3. **能力探测只读二进制找补丁特征串，绝不执行 `gnb_ctl` 去写东西**
   （例如探测 `"GNB Hot Restart Data Restore"` 判断是否带热重启补丁）。

### 5.3 写入权收敛（制度化）

`unified-platform-architecture.md` §4.4 定义了「每个文件只有一个写入者」的目标：

| 文件 | 当前写入者（多） | 目标写入者（唯一） |
|---|---|---|
| `node.conf` | node.refresh / GNBRunner / GNBDriver.WriteConfig | 仅 `GNBDriver.Apply` |
| `route.conf` | route.refresh / GNBDriver.WriteConfig | 仅 `GNBDriver.Apply` |
| `address.conf` | 安装包 | 仅 `GNBDriver.Apply` |
| `ed25519/` | route.refresh / GNBDriver.WriteConfig | 仅 `GNBDriver.Apply` |
| `security/` | credential init / GNBDriver.WriteConfig | 仅 `GNBDriver.Apply` |
| WG interface | mynet.wg.refresh.address | 仅 `WireGuardDriver.Apply` |
| OS routes | CLI route tools | 仅 `NetworkBackend.Apply` |

**现状**：旧链已收敛到 `coordinator.ApplyFiles`（带快照 + 失败回滚），
但 `VPNLifecycleDriver` 尚无实现，所以「唯一写入者」还是目标态。

---

## 6. ④ back 的中心化社区化链接

这是 **MyNet 的核心价值**：数据面是 P2P，但「**找到谁、和谁连、拿到谁的公钥**」
全部靠 back 这个中心撮合点。

### 6.1 「链接」分两层

**用户层（社交图）**——表达人际联系：

| 表 | 语义 |
|---|---|
| `friendships` | 双向好友关系（`user_id` / `friend_user_id` / `tenant_id`） |
| `friend_requests` | 好友请求 |
| `invitations` | 邀请 |
| `social_connections` | 统一表达（`type=friend_request\|invitation`） |

**节点层（拓扑授权）**——表达可访问关系：

| 表 | 语义 |
|---|---|
| `nodes` | 节点（`node_type` 位掩码，含 `NODE_TYPE_PROXY=64`） |
| `node_route_requests` | 节点间路由请求（`source_node_id` → `target_node_id`） |
| `routes` | **路由授权**：`node_id` → `target_node_id` / `target_network` / `target_mask` / `status`；**`relay_nodes` 与 `relay_mode` 是它的列，不是独立表** |

### 6.2 完整链路

```
搜索用户（按 tenant_id 收敛）
  → 发好友请求
    → 接受 → 生成【双向】friendships
      → 好友节点可被发现
        → 发路由请求（node_route_requests）
          → 接受 → 生成【双向】routes
            → 投影成 BundleEnvelope → 下发节点 → 写入 gnb 配置
```

所以 back 持有的是**社交图 + 路由授权**，数据面仍 P2P。

### 6.3 公钥交换

两条编码路径，**互不相同**：

| 驱动 | 算法 | 编码 | 公钥长度 | 私钥长度 |
|---|---|---|---|---|
| gnb | Ed25519 | **hex** | 64 hex | 128 hex |
| wireguard | X25519 (Curve25519) | **base64** | 44 字符 | — |

流程：节点注册/上线时上传公钥（`PUT /nodes/{id}/keys`）→ 存 `node_keys` 表 →
节点通过 `POST /monitor/router-keys`（节点通道）或 bundle 拿到**对端公钥**
（`getRouterKeys` 只返回 `public_key`）→ 写入 `ed25519/{peerNodeId}.public`，
增量更新（内容相同不覆盖）。

⚠️ **私钥确实存在 back 里**，且有明确证据：`node_keys.private_key` 列存在（`private_key text`），
`NodeKeyDomainService::saveNodeKeys` 把明文落库并**明文返回**，
`generateNodeKeys` 还会把 `private_key` 组装进邮件数据**发出去**。
这与「私钥永不出节点」的安全目标相冲突，是明确的风险点（见 §11）。

---

## 7. ⑤ agent 的自动化

`mynet_agent` 是中间代理层，产物四个：

| 产物 | 说明 |
|---|---|
| `mynetd` | 常驻守护：心跳遥测 + 执行 back 命令 + 网关长连接 + 本机 HTTP API |
| `mynet` | **配置工具 + 系统能力**（执行器）：`setup`/`install`/`uninstall`/`update`/`config`、`detector`/`metrics`/`apply`/`service`/`driver`、以及 `status`/`monitor`/`refresh-*` 等运维子命令 |
| `mynet_gateway` | 边缘网关（节点面唯一公网入口） |
| `mynet_proxy` | 代理辅助二进制（**在新线上下架**，见 §10） |
| `mynet-updater` | 自更新辅助 |

### 7.0 执行器架构（mynetd 的体积边界）

**mynetd 只做通信；系统能力全在执行器。** 两个平台用**同一套 mynetd 通信层**，
只是执行器不同：

```
mynetd（纯通信层，常驻）            执行器（偶发，用完即抛）
   ├── 心跳组装与上报                    │
   ├── WS 长连接 + 身份密钥（X25519 DH）  │
   ├── 取指令 · 结果上报                  │
   ├── 读 $MYNET_HOME/var/system.json     │
   └── 命令分派 ───────────────────────→ 桌面/Linux/Windows：exec mynet <子命令>
                                          OpenWrt：lua -e "..."
```

**mynet 不含**（即 mynetd 也不含）：探测解析、OS 路由/防火墙写入、驱动配置生成、
进程生命周期、`gnb_ctl -s` / `wg show` 的解析、gopsutil——这些全在 `mynet`。
**mynetd 因此不需要 `thin` 标签去裁业务——它本身就没有重活**；
桌面构建与 OpenWrt 构建的唯一差别是**是否 embed WebUI**。

**`system.json` 由 `mynet detector` 生成**（带 `generated_at` + 接口指纹），
有两个消费者：`mynet apply`（决定 exec 哪些工具）与 **mynetd 的本机态**
（WebUI 显示系统信息时直读文件，不必 fork）。网卡变化时 mynetd 触发重跑。

**跨平台对称性**——这就是「一份实现」的真正落点：

| | 桌面 / Linux / Windows | OpenWrt |
|---|---|---|
| mynetd | 纯通信 + WebUI embed | 纯通信（无 embed，LuCI 托管 UI） |
| 执行器 | `exec mynet <子命令>` | `lua -e "..."` |
| 系统探测 | `mynet detector` | Lua 已有 |
| 配置/写入 | `mynet apply` | Lua 已有 |
| 进程生命周期 | procd / systemd / launchd（`mynet service` 委托） | procd |

> 这个设计源自一个观察：`internal/detector`（3322 行）与 `internal/tools/{firewall,routing}`
> （1990 行）是**两套功能重复的探测实现**，且都来自归档的 `mynet_tui`——
> 两边函数全是 `Detect`/`Get`/`parse`，**无一 `Apply`/`Write`**。
> 探测是偶发、解析是重活，两者都不该在常驻进程里。

### 7.1 「自动」的三个来源

1. **心跳驱动**：`mynetd` 按周期（默认 300s，最小 30s）上报遥测，
   响应里带回待执行命令 → 执行命令链。
2. **网关事件驱动**：收到 `command_queued` / `bundle_changed` / `route_changed`
   事件后，按事件分派到专用 handler（**不是跑一次完整心跳**）。
3. **配置拉取**：`configsync.Sync` 优先走 Envelope Apply，`GetBundle(etag)` 支持 304 短路。

### 7.2 配置下发的两种方式（关键设计）

**服务端渲染**（旧/简单路径）：`GET /nodes/{id}/config?render_conf=1` 返回
**服务端渲染好的 `node.conf` 纯文本**，客户端不在本地拼装 GNB 配置，直接写入。
「node.conf 内容由服务端渲染」是个明确的设计决策——把配置正确性收到服务端。

**BundleEnvelope**（新/目标路径）：结构化意图包，含 `schema_version` / `generation` /
`checksum` / `min_agent_version` + `connections[]` + `network` + 驱动 intent。

### 7.3 原子 Apply（可靠性设计）

```
Fetch → generation 相同则 skip → Validate → Stage 到临时目录
  → driver.Apply → network.Apply → driver.Reload → HealthCheck
    → Commit（写 last-known-good / current_generation）
  任一步失败 → Rollback（从 last-known-good 重放）
```

- 写前 **snapshot**，任一文件失败则 **restore**（新建文件删除）；
- 并发 Apply 有文件锁保护（`apply.lock`）；
- `apply_journal.jsonl` 记录每次 Apply 生命周期，**journal 不含明文私钥**；
- 离线策略：优先 `last-known-good.json`，Fetch 失败不阻塞启动。

### 7.4 自动更新（与 ctl 的配合）

```
mynet_ctl（分发中心，SSOT）
  ↓ /d/apps.json              产品索引
  ↓ /d/{product}/manifest.json 版本清单（schema 1.1，含 checksums）
  ↓ /d/{product}/{file}        二进制
mynet_agent
  ↓ 下载 + 校验 sha256 + 解压
  ↓ exec mynet-updater --full-update <current> <new>
     等父进程退出 → 备份关键文件 → 整目录覆盖 → 失败回滚 → 重启
```

已被四端消费：agent(Go)、OpenWrt Lua、mynet_client、mynet_gui（版本兼容检查）。
`mynet_ctl` 已替换 `download.mynet.club` 成为升级索引源。

---

## 8. ⑥ 本地界面 → mynetd → gateway → back

这是**用户昨天刚定的方向**，也是**现状与目标态差距最大**的一条。

### 8.1 目标态（`control-plane.md` 定义的权威模型）

```
浏览器 ──> 127.0.0.1:8008 (mynetd httpd) ──> gateway ──> back ──> mysql
mynetd ═══════ WebSocket ═══════> gateway ── 内网 HTTP ──> back
```

**三跳分工**：

| 跳 | 组件 | 职责 | 不做什么 |
|---|---|---|---|
| ① 本机 | `mynetd` httpd | 对 GUI 提供本机能力面；对需要 back 数据的查询做**中继** | 不直连 back；不持有 back 凭据 |
| ② 边缘 | `mynet-gateway` | 节点鉴权、连接注册、presence、通知推送、**字节中继** | 不解析业务载荷；不存业务数据 |
| ③ 内网 | `mynet_back` | 业务权威 | **对节点不可达** |

**为什么这样切**（规范列出的收益）：

- **back 对节点的攻击面归零**——节点只需知道 gateway 地址，`/api/v1/monitor/*`、
  admin、用户 API 全部退内网；
- **鉴权边界唯一**——节点身份验证只在 gateway 做一处；
- **暴露面是小靶子**——对外只剩一个 Go 二进制，而不是整个 Laravel（含 vendor 数百条路由）；
- **GUI 零信任边界**——浏览器永不接触 back，凭据与会话都在本机 mynetd 侧；
- **gateway 可水平扩展**——无业务状态，可多点部署就近接入。

**关键约束**：`mynet_gnb` 不被触碰——gateway 对的是 `mynetd`，不是 gnb 进程。

### 8.2 现状：agent 段已实现，UI 段未接

**已实现**：

- `mynet_gateway` 代码存在（`cmd/mynet_gateway` + `internal/gateway`），
  监听 `/v1/node/connect`（节点 WS 长连接）、`/v1/presence/{node_id}`、`/v1/notify`；
- **mynetd 主动**以 WebSocket 连 gateway（back 不直连节点）；
- 握手是 **Ed25519 challenge-response**：签名覆盖
  `mynet-gateway-v1\n<node_id>\n<ts>\n<nonce>`，`ts` 偏差 > 300s 拒绝（防重放）；
- gateway 只做**字节中继**：`fetch` 携带 method/path/headers/body，
  gateway 转成对 back 的一次内网 HTTP 调用，**不解析载荷**，且只放行**白名单路径**；
- **心跳也默认走网关**（`relay_heartbeat=true`），所以「back 对节点不可达」成立；
- 本机 API（`localapi`）监听 `127.0.0.1:8096`，Bearer 本机 token（落盘 0600），
  提供 `/status`、`/logs`、`/service/{start,stop,reload}`、`/config/{sync,refresh}`、
  `/peers`、`/proxy[/enable|disable]`、`/zones[/switch]`、`/nodes`、`/relay`；
- `/relay` 就是 GUI 取平台态的通道，经 `localRelay → ControlPlane → gatewayclient` 中继到 back。

**未落地**：

- **`mynet_ui` 还没接上 mynetd**。它现在调的是 `mynet_client` 的 Express 服务
  （`127.0.0.1:7070`，`baseURL: '/api'`），端点面与 mynetd 的 `/api/v1/*` 不匹配；
- mynet_ui 里 **ubus 通道没有任何实现**（grep 只命中 README）；
- mynetd **不含静态文件托管**，所以「mynet_ui 的 dist 由 mynetd embed」尚未发生；
- **OpenWrt 侧反而禁用了 mynetd**：postinst 主动 `pkill -x mynetd`，
  改用 cron 每 5 分钟心跳替代，`mynet.*` ubus 对象**未注册**。

**结论**：「UI → mynetd → gateway → back」这条链中，
**mynetd → gateway → back 已通，UI → mynetd 与 OpenWrt 段还没接上**。

### 8.3 路径白名单（安全咽喉点）

`relaypolicy` 是 gateway 与本机 API **共用同一份**白名单（防漂移）：

- **允许**：`/api/v1/monitor/`（节点通道）、
  `/api/v1/{nodes,route,social,user,users,notifications,service,services,mynet}`；
- **始终拒绝**：`/admin`、`/auth`、`/mfa`、`/login`、`/logout`、`/register`、
  `/password`、`/reset`、`/token`、`/activate`、`/billing`、`/commerce`、`/orders`；
- 含 `%` 一律拒绝（防 `%2f` 绕过）。

这个白名单是**防止节点越权访问 back 的 admin/身份接口**的关键，
所以它必须与 gateway 同源，不能各写一份。

---

## 9. ⑦ proxy：通过部分节点访问到不了的地方

这条有**两代实现，且新线已经明确**。

### 9.1 旧线（已冻结）：`mynet_proxy` 的 CIDR 注入

机制是「往 `route.conf` 灌海量路由 + 策略路由分流」：

```
LAN 客户端请求
  → nftables/iptables PREROUTING mangle
    → 命中 IP 集合（国内/海外）→ 打标记 fwmark 0xc8
      → ip rule: fwmark 0xc8 → 查 table 200
        → table 200: default via {peer_vip} dev {tun_iface}
          → GNB 隧道 → 对端节点出口
```

- 角色 `client` / `server` / `both`；
- 为每个代理节点生成约 **712 条**固定放行路由（1–223 的 `/8`，拆 172/192，
  排除私有段），写进 GNB `route.conf`；
- 策略路由另生成 `proxy_route.conf`（nft set 格式）；
- IP 源来自 `interip.txt` / `chinaip.txt`；
- 还有一套 DNS 策略（`none`/`redirect`/`resolv`/`split`）+ 强制国外 DNS 走隧道。

**这套东西的问题**：route.conf 被灌成几千条、节点本地状态文件一堆、
`fwmark` 与系统路由深度耦合、LAN 透明接入还要 `proxy_arp` 补丁。

### 9.2 新线（目标）：gnb 原生 `proxy on`

**`mynet_proxy` 坚决下架**，其最重的职责已被 gnb 原生 `p2-route-proxy` 取代——
即 `node.conf` 的 **`proxy on`**：**未登记目的地兜底给 f 节点**，
`route.conf` 不再需要灌海量路由。

这正是 `mynet_gnb/packages/p2-route-proxy` 那个补丁做的事：
- 准出：未匹配 `route.conf` 时兜底交给 f 节点转发；
- 准入：`proxy on` 时跳过白名单（把 `GNB_PF_NOROUTE` 从致命改为放行）。

**能力归宿**（规范 §7.2，注意这不是「换个地方调 gnb」）：

| 旧能力 | 归宿 |
|---|---|
| 未登记目的地走代理 | **L2 TransportPolicy → L3 BundleEnvelope → L5 Driver**（gnb：`proxy on` + `address.conf` f 节点；wg：`AllowedIPs`） |
| OS 策略路由 | **L6 NetworkBackend**（驱动无关：`ip route`/pf 对 gnb0 与 wg0 一视同仁） |
| IP 段列表 / 地域分流 | **L2 TransportPolicy 的输入**（谁走谁是业务决策，back 算好随 envelope 下发） |
| `proxy_role.conf` 等节点本地状态 | **消亡**（角色与拓扑是 back 的 L1/L2 数据） |

**用户原话的机制澄清**（规范里明确引用）：

> **不是「mynet_proxy 去和 driver 抽象层通信」，而是「proxy 是业务意图，
> 由 driver 各自解释落地」**。底层可能是 gnb 也可能是 wg ——
> 所以意图在上、落地在 driver，中间经 envelope 传递。

⚠️ 规范同时指出：gnb 的 `proxy on` 与 wg 的 `AllowedIPs` **语义不同构，不可压成扁平结构**。

### 9.3 agent 侧现状：两套并存

| | 本机 API 的 proxy 域 | `mynet_proxy` 二进制 |
|---|---|---|
| 模式 | `gnb_native`（恒为此值） | CIDR 注入 |
| 做什么 | 只维护 `node.conf` 的 `proxy on\|off` | 灌 712 条 `/8` 路由 + nft 策略路由 |
| 权威 | **back 持有**（`node_configs.config` 的 `proxy`），node.conf 只是投影 | 节点本地 |
| 读/写 | 经中继问 back / 改 back 后刷新 node.conf | 直接改本地文件 |

`DriverCaps.Proxy` 由 gnb 二进制是否带 P2 补丁决定。

---

## 10. ⑧ 节点提供服务（如 web）

**这条在 back 侧已建模，在节点侧靠外部服务 + 路由。**

### 10.1 back 侧建模

`services` + `node_services` + `service_shares` + `service_approvals`：

| 字段 | 取值 |
|---|---|
| `service_type` | `gnb_index` / `music` / `video` / `netbridge` |
| `service_address` / `service_port` / `service_protocol` | 服务地址与端口 |
| `service_is_public` | 是否公开 |
| `bind_mode` | `direct` / `dnat` / `lan` / `public` / **`proxy`** |
| `share_type` | `public` / `friend` / `anonymous` / `private` |

公开服务需**审批**（`service_approvals`）；可**分享给好友**（`service_shares`）。
节点通过 `GET /monitor/index-services` 拉取「公开 + 好友分享」的服务列表生成 `address.conf`。

API 面：`/services/{my,public,friends,anonymous,show,create,update,delete,share,request-approval}`，
前端页面在 `mynet_gui/src/pages/services/`。

### 10.2 节点侧现状

- **agent 侧没有「端口暴露 / 服务发现 / 把请求转发到某节点 web 服务」的代码实现**；
- 实际落点是「**外部部署服务 + 路由注入**」：例如把流量经 `route.conf`
  导向某节点上的 sniproxy（`192.168.88.0/24 via 10.237.149.244 dev gnb_tun`），
  agent 只负责把 CIDR 路由到该节点；
- mynetd **不内嵌 WebUI 供其他节点访问**（localapi 只有 JSON API，无静态托管）；
- desktop / openwrt **无端口映射/dnat 发布脚本**。

**所以准确说法是**：back 提供服务的**注册、授权、分享、审批**模型，
真正的服务运行与流量引导靠节点上的外部服务 + gnb 路由能力。

---

## 11. ⑨ 支持更多平台/终端 —— 明确目标，大量未完成

### 11.1 已覆盖

| 平台 | 状态 |
|---|---|
| **OpenWrt** | **最成熟**：完整 `luci-app-mynet` 2.1.8 ipk，`controller/mynet.lua`（~2500 行）+ 12 个 model + view + 静态资源，含手搓 BSD-ar 格式 ipk 的 CI |
| Linux / macOS / Windows | `mynet_desktop/pack.sh` 声明支持 `tgz/zip/deb/rpm/pkg` |
| 架构矩阵 | `mynet_ctl` 有 13 架构矩阵 + OpenWrt 设备库（1930 条）+ `/d/detect/openwrt.sh` 硬件检测 |

### 11.2 缺口

1. **`mynet_desktop` 实为占位**——全仓仅 7 个文件，脚本全是 `mynet_client` 血统：
   `APP_NAME=mynet-client`、systemd 单元跑的是 **Node 而非 mynetd**
   （`ExecStart=$node_bin .../bin/mynet.js service start`）、
   打包输入是 mynet_client 的 `web/` 与 `src/cli/index.js`；
   **无 launchd plist、无 Windows 服务、无 gnb/mynetd 二进制布局、无 conf 模板**。
2. **mynetd 的 ubus 形态未实现**——ubus 实现不在 agent 仓，OpenWrt 侧也没注册 `mynet.*` 对象，
   只有 LuCI `menu.d` 路由与 rpcd ACL（只含 uci read/write）。
3. **`mynet_ui` 的 dist 两侧都没被引用**——desktop 用 mynet_client 的 `web/`，
   OpenWrt 用自己的 `.htm` + `mynet.js`；没有「一份代码两环境」的切换机制。
4. **平台适配重复两份**——desktop（Node 脚本）与 openwrt（Lua）功能集重叠
   （安装/服务/gnb 启停/proxy/日志）但实现各写一套，**没有共享控制面**。
5. FreeBSD 等仅见于 ctl 规范表，无适配层实现。

---

## 12. 分层架构：L1–L7 权威模型

`spec/unified-platform-architecture.md` 是整个项目的架构 SSOT。核心原则：

> **上层业务逻辑（好友、连接、密钥、授权）在 GNB 和 WireGuard 之间完全统一。
> 驱动差异从「驱动编排层」开始向下分化，不泄漏到业务对象。**

| 层 | 归属 | 职责 | 核心对象 |
|---|---|---|---|
| **L1 业务域** | back | 好友、连接生命周期、密钥、授权/撤销——**不含任何驱动术语** | `NodeIdentity` / `Friendship` / `ConnectionRequest` / `Connection` / `ConnectionGrant` / `KeyExchange` |
| **L2 拓扑与策略** | back | 把 Connection 翻译成「怎样通信」，仍不生成驱动配置 | `TransportPolicy`（`mode`: direct/proxy/relay/auto/disabled）、`NodeCapability`、`TransportPlanner`（**纯函数**）、`TransportPlan`（`active_path` / `fallback_path`） |
| **L3 配置发布** | back → agent | 序列化为 `BundleEnvelope`，管理版本与发布 | `BundleEnvelope` + `GnbIntent` / `WireGuardIntent` / `NetworkIntent`；`secret://` 引用 |
| **L4 Agent 协调** | agent | 拉取 → 校验 → 原子 Apply → 健康检查 → Commit/Rollback | `BundleFetcher` / `BundleValidator` / `BundleApplier` |
| **L5 驱动适配** | agent | 把 driver intent 翻译为原生配置 + 管进程生命周期 | 统一驱动接口；`GnbDriver` / `WireGuardDriver` |
| **L6 系统网络** | agent | OS 路由/防火墙/NAT（**驱动无关**） | `NetworkBackend` |
| **L7 观测与故障切换** | agent | 期望态 vs 观测态、故障切换状态机、上报 | `DesiredState` / `ObservedState` / `FailoverStateMachine` |

**§0.1 传输与控制面**（横切，不改变业务分层）：
`mynetd ══WebSocket══> mynet-gateway ──内网 HTTP──> mynet_back`。

### 12.1 L1 核心对象（业务的完整定义）

```
NodeIdentity      node_id / tenant_id / user_id / vpn_type / node_status
Friendship        source_user_id / target_user_id / status(pending|accepted|rejected|revoked)
ConnectionRequest connection_id / source_node_id / target_node_id / status
Connection        connection_id / source_node_id / target_node_id / tenant_id / status(active|suspended|revoked)
ConnectionGrant   grant_id / connection_id / resource_type(network|node|service) / resource_ref / permission
KeyExchange       key_id / node_id / algorithm(ed25519|curve25519) / public_key / key_version / status
```

**注意 `ConnectionGrant.resource_type` 含 `service`**——这是第 8 条「节点提供服务」
在业务层的挂点，与 `permission`（read/write/full）一起表达「允许访问什么、能做什么」。

### 12.2 与现有实现的映射（过渡期）

| 新对象 | 现有 back 模型 | 现有 agent 行为 |
|---|---|---|
| `NodeIdentity` | `nodes` 表 | — |
| `Friendship` | `friendships` / `friend_requests` | — |
| `ConnectionRequest` | `routes` 表（`status=pending`） | — |
| `Connection` | `routes` 表（`status=enabled`） | `route.refresh` 触发 |
| `ConnectionGrant` | 无独立模型（隐含在 `node_ips`） | `route.conf` 中的网段 |
| `KeyExchange` | `node_keys` 表 | `ed25519/` 或 WG peer |

**当前缺失**：`ConnectionGrant` 无独立表；`node_keys` 缺 `algorithm`/`key_version`/`status` 列；
`Connection.status` 的 `suspended`/`revoked` 需扩展 `routes.status`；
`ConnectionRequest` 与 `Connection` 共用 `routes` 单表（过渡期靠 status 区分）。

### 12.3 `auto` 模式的语义差异（很好的例子）

```
业务层声明: mode = "auto", allow_direct = true, fallback_to_proxy = true

策略引擎输出: active_path = DirectPlan, fallback_path = ProxyPlan

驱动解释:
  GNB: 生成 route.conf 同时包含 direct + forward/proxy 配置
       → 【GNB 协议层自行选择 P2P 或 forward】
  WG:  不含自动 fallback
       → 【L7 观测层负责检测并切换 peer 配置】
```

**这个例子说明为什么抽象必须保留驱动差异**：同一个业务意图，
gnb 靠协议层自己兜底，wg 必须靠上层观测切换。硬压成一套会丢失语义。

---

## 13. 现状盘点：已完成 / 进行中 / 未落地

### 已完成（有代码与测试）

- gnb 补丁包机制（P1/P2/P3）与源码同步流程
- back 的社交图 + 路由授权 + 公钥目录 + 心跳落库
- agent 的 `coordinator.ApplyFiles` 原子写入（快照 + 回滚 + journal + 并发锁）
- `GnbAdapter`：`BundleEnvelope` → gnb 原生文件集
- **gateway 代码**（WS + Ed25519 握手 + 字节中继 + 白名单）
- `mynetd → gateway` 通路，含心跳中继
- `mynet_ctl` 分发中心（已被四端消费）
- `mynet_openwrt` 完整 LuCI 包
- `mynet_utc`（UDP→TCP，独立可选组件）
- 双端 fixture 一致性（7 个场景，PHP 与 Go 解码结果一致，checksum 算法冻结）

### 进行中（契约已定，实现不全）

- `VPNLifecycleDriver`：**只有契约，无实现**（注释明确要求「不要在 R2–R4 之前实现」）
- 写入权收敛：旧链已收敛到 `ApplyFiles`，但「唯一写入者」未达成
- 生命周期权威收敛（R5）、原子 Apply（R4）

### 未落地

| 项 | 说明 |
|---|---|
| **WireGuard 驱动** | 空目录；工厂返回「v0.10.0 支持」 |
| **热重启** | README 描述的降级链（`gnb_ctl -w core.flag=2` → SIGHUP → 冷启）**仓库无实现**；`Capabilities().HotReload` 如实返回 false，生产实际用冷启 |
| **UI → mynetd** | mynet_ui 仍调 mynet_client 的 Express |
| **mynetd 的 ubus 形态** | 未实现；OpenWrt 反而禁用了 mynetd |
| **mynet_desktop** | 占位，脚本仍是 mynet_client 血统 |
| **离线启动用 last-known-good** | `LoadLastKnownGood()` 已有，**启动路径未接** |
| **第 8 条节点侧服务暴露** | 无端口映射/服务注册/静态托管 |

---

## 14. 发现的问题与风险

按严重程度排列。

### 14.1 安全

**① 私钥明文存在 back，且存在外发路径**
`node_keys.private_key` 明文落库（`NodeKey.php:24` 在 `$fillable` 里），
`NodeKeyDomainService::saveNodeKeys` 明文返回，
`generateNodeKeys` 把它组装进邮件数据发出去。

**需要公允说明的两点**：

- 模型 docblock 自己承认了这个状态：「GNB 约定：public_key 为 64 位 hex，
  private_key 为 128 位 hex（**可能为空——密钥由节点自行生成时服务端不持有**）」
  （`NodeKey.php:14`）。即**设计上允许两条路径**：服务端生成（则持有私钥）
  或节点自行生成（则只有公钥）。
- 查对端公钥的接口是干净的：`getRouterKeys` **只返回 `public_key`**，
  `GET /nodes/{id}/keys`（`getNodeKeyInfo`）也**不含私钥**。

**风险点在于**：产品上既然存在「服务端生成密钥」这条路径，
私钥就会在 back 落库、经 API 明文返回、并可能进入邮件正文。
这与 `control-plane.md` 声称的「**私钥永不出节点**」在方向上冲突——
该规范描述的是目标态，而现状保留了服务端持钥的分支。

**② 心跳用「公钥」当 HMAC 密钥（已知弱点，非实现 bug）**
`signature = base64(HMAC-SHA256(key = base64_decode(node_keys.public_key), message))`，
`message = METHOD|path|timestamp|body`，容忍窗口 300s。

**公钥按设计是公开的**（back 的 `node_keys` 表、节点上 0644 的公钥文件），
所以「强度 = 公钥的保密程度」——**任何能读到公钥的一方都能伪造该节点的心跳**。

这一条要公允地说明：**代码里自己就写清楚了这是已知弱点、不是实现 bug**，
并把它定位为「**过渡方案，本就等着被 gateway 的 Ed25519 challenge-response 取代**」
（`NodeHeartbeatAuthMiddleware` docblock；`control-plane.md` §6.3、
`node-keying.md` §5 有对应设计）。
所以风险的性质是「**迁移尚未完成期间的窗口期暴露**」，
而不是设计疏漏。但 gateway 目前只覆盖节点↔gateway 这一跳，
**节点↔back 的心跳仍是 HMAC**，且 back 侧没有 gateway 时心跳会直接暴露在旧路径上。

**③ 公钥编码有三种假设（这条是真隐患）**
同一列 `node_keys.public_key` 上有三套互不兼容的假设：

| 位置 | 假设 |
|---|---|
| `NodeHeartbeatAuthMiddleware` | **32 字节 base64**（44 字符），显式校验 `strlen === 32`，否则拒签 |
| `NodeKey` 模型 docblock | 「GNB 约定：public_key 为 **64 位 hex**」 |
| Bundle 生成 | 做 base64 → hex 转换后写入 envelope |

**模型注释与中间件期望直接矛盾**。若某条路径写入的是 hex，
心跳会因「形态异常（应为 32 字节 base64）」被拒——
即**密钥编码不一致会让节点无法上报心跳**，且表现为鉴权失败而非编码错误，不易定位。

**④ BundleEnvelope 内含明文私钥**
`secret://node_keys/1001/private` 只是 back 内部生成流程的引用，
**下发时明文写入 envelope 的 driver intent 字段**，`secret://` 引用不下发。
规范把它列为「迁移路径」，后续阶段可改为 agent 自行从安全存储读取。

### 14.2 文档与代码漂移

| 位置 | 文档说 | 实际 |
|---|---|---|
| `mynet_ctl/docs/knowledge-base/mynet.md` | gnb 私钥是「seed + 公钥」64 字节 | **是 `SHA512(seed)`**（`keypair.c:9-12`），拿不到 seed |
| `HEARTBEAT_API_SPEC.md` | `POST /api/v1/heartbeat` | 实际 `POST /api/v1/monitor/heartbeat` |
| `HEARTBEAT_IMPLEMENTATION_SUMMARY` | 「Ed25519 签名验证」 | 实现是 HMAC |
| `control-plane.md` | 状态「gateway 未实现」 | **代码已存在** |
| `mynet_utc/VERSION` | — | `1.5.0`；README 写 1.3.0、`docs/ARCHITECTURE.md` 写 1.3.1、`docs/PACKET_FORMAT.md` 写 1.4.0 |
| `mynet_utc/README.md` | 双模式端口 8192/8193、`config/mode_default`/`mode_torrent` | **这些目录不存在**，实际默认 8192，档位靠 `config/examples/*.ini` |
| `mynet_utc/README.md` | 「零 malloc 热路径」 | 热路径每包 `malloc(len)` |
| `mynet_agent/README.md` | 热重启降级链 | 仓库无实现 |
| `mynet_ui/README.md` | 桌面走 mynetd、OpenWrt 走 ubus | 两条都没实现，实际走 mynet_client Express |
| `mynet_desktop/README.md` | 平台适配层 | 实为 mynet_client 脚本拷贝 |

### 14.3 结构性问题

- **平台适配重复两份**（Node 脚本 vs Lua），无共享控制面；
- **死代码**：`internal/legacy`、`internal/checker`、`internal/detector`、`internal/interfaces`
  在 agent 仓全无 import；`internal/tui/viewmodel` 仍是活代码（TUI 界面已谢幕，但 viewmodel 服务 CLI/WebUI 后端）；
- **gnb conf 目录里有 Go 格式化 bug 产物（已实测确认）**：
  `driver/gnb/conf/4147069894021819/ed25519/` 下同时存在两组文件——
  正确的 `{nodeid}.public`（65 字节）与 **`%!d(string={nodeid}).pub`**（同样 65 字节）。
  5 个节点各有这样一份重复。`%!d(string=...)` 是 Go 把 `%d` 用在字符串上的产物，
  说明**写对端公钥时有格式串不匹配的缺陷**，且旧产物没有被清理。
  危害有限（正确文件也在），但会让目录变脏、并掩盖真实的写入错误；
- **`mynet_agent` 根目录有名为「生成配置 功能」的游离文件**（含换行/特殊字符）。

---

## 15. 关键决策记录

| 决策 | 内容 |
|---|---|
| **控制面/数据面分离** | MyNet 只管「谁和谁连、允许什么」，转发与加密全交驱动 |
| **产品本体与平台适配分离**（2026-09） | 本体 = agent + ui（一份）；适配 = desktop + openwrt（一平台一份） |
| **驱动差异不下泄** | 业务对象不含 `node.conf`/`AllowedIPs`/`endpoint`/`gnb`/`wireguard` 字段 |
| **gnb 改动走补丁包** | 不直接改 `src/`，用 format-patch + rebase |
| **服务端渲染 node.conf** | 客户端不本地拼装 GNB 配置，直接写服务端返回的纯文本 |
| **写入权收敛** | 每个配置文件的写入者收敛为唯一的 `driver.Apply` / `NetworkBackend.Apply` |
| **back 对节点不可达** | gateway 是节点面唯一入口 |
| **GUI 只谈本机** | 浏览器 → `127.0.0.1:mynetd` → gateway → back，GUI 零持有 back 凭据 |
| **`mynet_proxy` 下架** | 职责被 gnb 原生 `proxy on` 取代；能力归宿 = L2→L3→L5 + L6 |
| **proxy 是业务意图** | 不是「CLI 去调驱动」，而是「意图在上、落地在 driver，中间经 envelope」 |
| **mynetd 纯通信，系统能力在执行器**（2026-09-23） | mynetd 不含探测/写入/驱动/进程管理；`mynet` 承载系统能力，`mynet detector` 产出 `system.json` 供双端消费 |
| **平台执行器对称**（2026-09-23） | 桌面 `exec mynet <子命令>`、OpenWrt `lua -e "..."`；**同一套 mynetd 通信层** |
| **身份生命周期交给框架** |
| **back 全部在 user 层** | 不用 RBAC；admin 是平台层（框架 platform 模块），不属于 back |
| **节点直连 = 智能同类型**（2026-09-23） | 节点↔节点**只同类型直连**（一节点一网卡）；双驱动节点可分别与 gnb/wg 建连；**gnb-only ↔ wg-only 不直连**。跨驱动互通 = 经双驱动节点的 **L6 路由桥**（apply 一条 route），不是节点间建连 |
| **多节点并行分两步**（2026-09-23） | 短期互斥切换 `POST /api/v1/nodes/switch`；并行作为后续增量，不阻塞 M0–M1 |
| **连接「已鉴权」置位时机**（2026-09-23） | gateway 维护连接状态表；back 验证通过**任意一次**节点通道请求即置位。回传机制：back 响应头 `X-Node-Verified: <node_id>`，gateway 解析后置位；伪造 node_id 不被置位 |
| **mynet_back 按需 module 砍 logging**（2026-09-23） | 只留 `multi-tenant-saas` + `infrastructure` + `auth`；MyNet 不用审计日志，`logging` 一并砍 |
| **D-4 作废：`mynet_gui` 才是用户交互前端**（2026-09-24） | `mynet_web` 只是**官网/项目主页**（`www.mynet.club`），`mynet_gui`（`my.mynet.club`，Vue3+Quasar，102 个页面）才是真正提供给用户交互的地方。原「合并 `mynet_ui`+`mynet_gui` → `mynet_web` 一份 WebUI」的 D-4 **作废**；S 段只搬了 `mynet_ui` 的 `local/` 6 页进 `mynet_web`，`mynet_gui` 一页未搬。**T2-6 的载体是 `mynet_gui`**，已挂载进 workspace（origin 仍指 `luoyueliang/mynet_gui`，不新建 org 仓） |
| **multi_tenant_saas 框架只使用不修改**（2026-09-24） | `vendor/dsplat/**` 只读。框架缺陷或新需求**回上游 `github.com/dsplat/*` 改**，这边 `composer update` 拉进来。定位问题先判根因在 `app/`（可改）还是 `vendor/dsplat/`（只出报告），别把框架 bug 当自己的 bug 绕过去。详见 `AGENTS.md` §1.8 |
| **H1 生产入口复用现有 nginx，不引入 Caddy**（2026-09-24） | 生产已有 nginx（`listen 172.26.3.227:443`，前置公网 LB）+ Let's Encrypt 证书（`/etc/letsencrypt/live/mynet.club/`）。`deploy/caddy-compose.yml` 直接占宿主 80/443 与该拓扑冲突，且该文件本身是坏的（跨文件 `depends_on` 报 `invalid compose project`）。→ 只加 server block，Caddy 方案归档 |
| **H4 `console` = 租户管理，`admin` = 平台管理**（2026-09-24） | 用户原话：「back管理是租户管理，租户管理是console，admin 是平台管理」。→ back 管理面称 `console.mynet.club`；`admin.mynet.club` 让给框架 platform/operator SPA；`adm` 弃用。`deployment-registry.md:13` / `ecosystem.md:125` / `brand-guide.md:152` 三处待同步 |
| **H7 `back_priv` 用 0600 文件，不用 env**（2026-09-24） | 按原规范（`product-form/index.html:1121,1134`：不进代码库、不进 BundleEnvelope、有条件上 KMS）。现实现是 env 注入（`config/mynet.php:25-26`、`docker-compose.yml:16-17`）**与规范不符，须改**。理由：`back_priv` 保护等级**高于**节点私钥（泄露可冒充**所有**节点），而 env 会出现在 `docker inspect`、compose 文件与进程环境里。`spec/node-keying.md` 现无 back_priv 条目，须补 |
| **V3-0 撤掉 Docker 路线，生产走主机 + nginx + rsync**（2026-09-24） | 用户问「我们为什么使用 docker」后核查发现：**Docker 不是本项目的部署模型**。`deployment-registry.md` 写的是主机 `10.182.236.180`、三个项目各是独立 nginx server block、证书在 `/etc/letsencrypt/live/mynet.club/`、部署命令是 rsync。原 `deploy/docker-compose.yml` + `caddy-compose.yml` + `Caddyfile` 与此拓扑不符（caddy 直接占宿主 80/443，与现有 `listen 172.26.3.227:443` + 前置 LB 冲突，H1 已裁定复用现有 nginx）。→ 三个文件删除；Caddyfile 的意图（TLS + api/gw 反代 + 安全头）转为 `deploy/nginx.conf`；补上 registry 声称「推荐」却**不存在**的 `manage-prod.sh`。**教训：先确认技术选型是否成立再补实现——计划表里的条目也可能定错，不是免检的** |

---

## 16. 一句话总结

**MyNet 是一个「控制面中心化、数据面 P2P」的组网平台**：
`mynet_back` 做中心撮合（社交图 + 路由授权 + 公钥目录），
`mynet_agent` 做节点侧自动化——`mynetd` 是**纯通信层**（心跳、指令、WS、经网关回连），\n系统能力（探测/写入/配置/驱动）由**执行器**承载（桌面 `mynet`、OpenWrt Lua），
`mynet_gnb`（opengnb）做真正的 UDP P2P 穿透与转发，
`mynet_ctl` 做分发与文档中枢，`mynet_ui`/`mynet_gui` 分别做本机控制台与云端社区前端。

**架构设计是自洽且相当克制的**——L1–L7 分层、驱动差异不下泄、写入权收敛、
补丁包隔离上游，这些都体现了清晰的边界意识。

**但实现进度与设计有明显落差**，当前的三条主要断裂带是：
**WireGuard 驱动未实现**（第二条腿没装上）、
**UI→mynetd 未接**（第 6 条只走了一半）、
**mynet_desktop 是占位**（第 9 条的平台适配缺口）。

另有三处需要处理的安全问题，按性质排列：

1. **公钥编码三种假设冲突**（真隐患，且会以「鉴权失败」而非「编码错误」的形式暴露）；
2. **私钥明文存在 back 且有外发路径**（现状保留了服务端持钥分支，与目标态冲突）；
3. **心跳用公钥当 HMAC 密钥**（代码已自认是已知弱点，性质是迁移未完成的窗口期暴露，
   而非设计疏漏）。