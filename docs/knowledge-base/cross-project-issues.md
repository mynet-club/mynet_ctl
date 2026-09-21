# 跨项目一致性问题清单（Cross-Project Issues）

> **维护说明**：本文件汇总 mynet 各子项目之间的契约偏差、规范不一致、待修复项。  
> **更新规则**：每次审计 / 重大重构后更新；问题修复后从「待修复」移到「已修复」并标注日期。

---

## 待修复（按优先级排序）


### 🟠 P1 — 影响安全性

#### 3. mynet_back CORS 允许所有来源
- **位置**：[mynet_back/config/cors.php](../../../mynet_back/config/cors.php)
- **现状**：`'allowed_origins' => ['*']`
- **风险**：生产环境跨站请求无限制
- **修复建议**：改为白名单（见 [.credentials/_templates/domains.md](../../.credentials/_templates/domains.md#cors-配置mynet_back)）
- **优先级**：上线生产前必修

#### 4. Token 刷新机制依赖 Sanctum 设计假设
- **背景**：mynet_back 实现的 `/api/v2/auth/refresh-token` 通过验证当前 Token 后签发新 Token
- **潜在风险**：若 Token 已过期则无法刷新（用户必须重新登录）
- **位置**：[mynet_back/routes/api.php](../../../mynet_back/routes/api.php) + auth controller
- **影响**：长期不活跃的客户端会被强制登出
- **修复建议**：方案 A — 延长 Sanctum 过期时间（已在 .env.example 设为 7 天）；方案 B — 引入 refresh token（需架构变更）

#### 5. mynet_back 生产 APP_KEY 与开发相同
- **现状**：`mynet_back/.env.production` 中的 `APP_KEY` 与开发 `.env` 完全一致（`base64:cbZh...`）
- **风险**：开发环境密钥泄露即可解密生产 session / 加密字段
- **修复建议**：在生产服务器执行 `php artisan key:generate --force`；同时 `.env.production` 中 APP_KEY 改为占位符（如 `BASE64:GENERATE_ON_DEPLOY`），由部署脚本生成
- **位置**：[mynet_back/.env.production](../../../mynet_back/.env.production)

#### 6. mynet_back 生产仍残留 dsplat.com 引用（部署脚本 / 文档）
- **范围**：`.env.production` 的 SESSION_DOMAIN / SANCTUM / VITE_REVERB_HOST 已修复（2026-05）
- **剩余位置**（不在本轮修复范围）：
  - `mynet_back/scripts/shell/{mynet,mynet_linux,mynet_xiaomi,gnb_setup,mynet_bash}.sh`：硬编码 `https://x.dsplat.com/gnb_conf/${NodeID}.tgz`
  - `mynet_back/scripts/deployment/{deploy-mod-local.sh,nginx-config.conf}`：nginx 路径 / 日志名
  - `mynet_back/docs/deployment/PRODUCTION_DEPLOYMENT_CHECKLIST.md`：5× `api.mynet.dsplat.com`
- **保留不动**：`MAIL_FROM_ADDRESS=arthur@dsplat.com`(用户真实邮箱),`docs/deployment/mail-configuration-guide.md` 中同左
- **修复建议**：单独 PR 处理 shell 脚本与部署文档,与 .env 修复解耦

#### 7. Reverb 开发/生产共用同一 App Secret
- **现状**：`oyzmmhlqzmar2uvanq2k` / `yabimhxofy2jud7f3lo5` 在 mynet_back 与 mynet_gui 的 `.env` 与 `.env.production` 中完全相同（且已提交到 git 历史）
- **风险**：开发凭证泄露 = 生产 WebSocket 被冒充
- **修复建议**：生产轮换 Reverb 三件套（app_id / key / secret）；并将 `.env` 移到 `.env.local` 不入仓
- **位置**：[mynet_back/.env](../../../mynet_back/.env)、[mynet_gui/.env](../../../mynet_gui/.env)

---

### 📝 控制面落地发现的缺陷（2026-09-22 记录，发现即记）

> 来源：控制面任务链第 4/5 单元落地过程中撞出来的。**记录在前、处置在后**，
> 避免"当场只修了主问题、周边缺陷随上下文一起丢掉"。
>
> 本批 CPI-1 ~ CPI-8 全部已处置（见「已修复」表）；仅 CPI-7 保留为低优先级记录
> （当前全局 ID 上限恰好等于 float64 精确整数上限，暂无风险）。

---

## 📐 架构设计决策（非 bug，易被误判）

#### mynet_gui 生产 VITE_API_BASE_URL=https://my.mynet.club（同源反代）
- **现状**：`mynet_gui/.env.production` 中 `VITE_API_BASE_URL=https://my.mynet.club`（指向自身域名）
- **设计意图**：生产部署中 nginx 在 `my.mynet.club` 上对 `/api/*` 反向代理到 `api.mynet.club`，实现 same-origin 访问以规避浏览器 CORS preflight
- **决策日期**：2026-05（用户确认）
- **关联**：CORS 配置 issue #3 仍存在，但因 SPA 走同源路径，实际风险被反代屏蔽；严格白名单仍建议补上
- **TODO**：在 [architecture.md](architecture.md) 增加「反代部署模式」章节

### 🟡 P2 — 文档 / 规范

#### 5. mynet_back 文档分散
- **现状**：mynet_back 顶层散落临时 .md（如 `BACKEND_FIX_NEEDED.md`、多个 deploy shell 脚本注释长篇）
- **修复建议**：仿 mynet_gui 归档方式，集中到 `docs/_archive/`
- **优先级**：低（不影响功能）

#### 6. mynet_gui 根目录 70+ 临时报告
- **状态**：✅ 已修复（2026-05 审计中归档）
- **归档位置**：`mynet_gui/docs/_archive/{plans,reports,fixes,guides,misc}/`

#### 7. mynet_client 注释残留 openwrt 引用
- **状态**：✅ 已修复（2026-05 审计中清理）
- **位置**：`src/service/manager.js`、`src/service/platform/darwin.js`、`pack.sh`、`src/service/platform/index.js`

---

### 🟡 P2 — 本机能力面落地时发现的前置（2026-09-22 记录）

> 本轮在把 `spec/local-api.md`（本机能力面）落地到 mynetd 时撞出来的。
> 原则同前：**先记录、再处理**；需要决策的不猜。

#### CPI-9. `proxy` 标志的落点未定，proxy 域因此落不了地
- **ID**：CPI-9
- **位置**：`local-api.md` §3 proxy 行；[gnb_config_render.go](../../../mynet_agent-integration/internal/infrastructure/gnb_config_render.go) 的 `renderNodeConf`
- **现状**：契约要求 `proxy.enable` 写 `proxy on`（gnb_native）到 node.conf，
  但**渲染器目前不输出这一行**——`domain.BundleFlags.Proxy` 字段存在却从未被使用
  （`renderNodeConf` 只写 nodeid/ifname/listen/mtu/daemon off）。所以磁盘上根本没有这个标志可读可写
- **两条路各自的问题**：
  a) 按分层让**驱动**从 bundle 渲染该行（与 `BundleFlags.Proxy` 的设计意图一致）——
     但需要"读回当前 bundle 再改标志位"的路径，现在没有
  b) 本机 API 直接改 node.conf——把 gnb 的文件格式耦合进 API 层，
     与 `driver-abstraction.md` 的分层相悖
- **倾向**：a)；但这是驱动层的设计决定，不在本机 API 这一轮里替它定
- **优先级**：中（proxy 是 GUI 的常用开关）

#### CPI-15. 整批 `NodeMonitorController` 端点曾因缺 request attribute 而全 404（已修）
- **ID**：CPI-15
- **位置**：[NodeMonitorController.php](../../../mynet_back-integration/app/Modules/VPN/Controllers/Api/V1/NodeMonitorController.php)
- **现状（修复前）**：该控制器**每个**端点都写
  `$nodeId = $request->attributes->get('node_id'); // 从中间件获取已验证的node_id`，
  而当时的 `NodeHeartbeatAuthMiddleware` 是 `// TODO` 空壳、**从未设置该属性**
  → `Node::find(null)` → 404 `Node not found`
- **实测（本次做的反向验证）**：移除那行属性 → `GET /monitor/index-services` 返回
  **404 "Node not found"**；加回 → **200**。因果确凿
- **影响面**：node/info、node/config、node/routes、node/full-config、route-config、
  index-services、router-keys **全部 404** —— 也就是**路由刷新与路由公钥同步此前是坏的**
  （`refresh-routes` → `/monitor/route-config` → 404；`refreshPublicKeys` → `/monitor/router-keys` → 404）
- **怎么修好的**：本轮做 CPI-6（按已验签节点限流）时，在验签中间件里加了
  `$request->attributes->set('node_id', $nodeId)` 供限流器用——**顺手把这批端点一起修好了**。
  属"意外的正确修复"，记在此处以免日后被当成"本来就是好的"
- **优先级**：已修（无需动作），记录以便理解历史行为与后续排查

#### CPI-14. `daemon/logger` 在 `Init()` 之前调用会**直接 panic**（地雷）
- **ID**：CPI-14
- **位置**：[logger.go](../../../mynet_agent-integration/internal/daemon/logger/logger.go)（`debugLogger` 等包级变量在 `Init` 前为 nil）
- **实测**：`mynet refresh-address --auto` → SIGSEGV：
  `config.loadCommandMappings` → `logger.Error` → `log.(*Logger).Output` 空指针
- **触发条件**：任何走 `config.LoadConfig()` 且目标环境缺 `conf/command.*.json` 的 CLI 路径
  （`LoadConfig` 会在找不到命令映射时记一行 error，而 CLI 从不调用 `logger.Init`）
- **影响**：不是"少一条日志"，而是**进程崩溃**；`refresh-routes` 等既有命令同样受影响
- **修复方向**：给 logger 一个**可用的默认状态**（包 init 时按 stderr/stdout + INFO 装配，
  与 `Init(level, "")` 的"仅控制台"分支一致），`Init` 再按需重配

#### CPI-12. relay 域缺 node→back 的用户层鉴权链路（契约未规定）
- **ID**：CPI-12
- **位置**：`control-plane.md` §5.3（relay 域契约）
- **契约要求**：GUI 的平台态查询（节点列表/路由/好友/统计）由 mynetd 经 gateway 中继到 back
- **断链在哪**：
  1. back 的用户层接口（`api/v1/nodes|route|social`…）挂在 **`auth:sanctum`**，要**用户** token
  2. agent 确实持有用户凭证（`internal/daemon/credential` 的 `Token`/`RefreshToken`，100 年有效期），
     所以"以用户身份访问 back"在数据上是具备的
  3. **但网关按设计不放行 `Authorization`** —— 中继只透传 `x-*` / 条件请求 / 内容协商头，
     正是为了"避免节点越权设置 Host/Authorization 之类"（`gateway/server.go` relay）
- **结论**：三条路都需要决策，契约里没写选哪条
  a) 网关放行 `Authorization`（削弱一条刚立的安全性质）
  b) back 增一套"节点身份可访问的用户层"接口
  c) 网关侧持有「节点 → 用户」令牌映射，由网关代填
- **影响**：GUI 的平台态功能（列表/好友/路由/统计）暂时无法经本机 API 取到
- **优先级**：高（这是 §5.1「GUI 只跟本机说话」能否成立的关键一环）

#### CPI-13. conf 声明的命令有 3 条实际会失败（agent 侧，implement 缺失）
- **ID**：CPI-13
- **位置**：[conf/command.linux.json](../../../mynet_agent-integration/conf/command.linux.json)（openwrt 同）
- **实测**：
  | 声明的命令 | 实际执行 | 结果 |
  |---|---|---|
  | `mynet.gnb.refresh.address` | `bin/mynet refresh-address --auto` | **CLI 无 `refresh-address` 子命令**（全仓零实现）→ 未知命令 |
  | `mynet.wg.refresh.address` | `bin/mynet refresh-address --auto --vpn-type wireguard` | 同上 |
  | `mynet.wg.refresh.route` | `bin/mynet refresh-routes --auto --node-id X --vpn-type wireguard` | **`refresh-routes` 不认 `--vpn-type`** → unknown flag |
- **与 CPI-2/CPI-4 是同一类**（命令名/参数与实现不一致 → 静默或半静默失效），
  只是方向相反：那次是 back 写错命令名，这次是 agent 的 conf 声明了做不到的事
- **影响**：`config.sync` 契约里的 "**address**" 部分因此无法覆盖（SSOT 要求拉 node/route/address + keys）
- **修复方向**：补 `refresh-address` 实现（back 已有 `/monitor/index-services` 与
  `api.GetIndexServicesRendered`），并让 `refresh-routes` 显式接受/拒绝 `--vpn-type`
  （**不支持就报错，不能静默按 gnb 处理**）
- **补充（同日复查 conf 全部调用后发现更多同类）**：
  | 声明的命令 | 实际执行 | 结果 |
  |---|---|---|
  | `plugin.proxy.start` / `.stop` / `.restart` | `bin/mynet_proxy start\|stop\|restart` | **该 CLI 没有这些子命令**（只有 apply / apply-routes / clean / ipconfig / refresh / setup / status）→ 未知命令（两个平台文件都有） |
  | openwrt 的 `mynet.start` / `.stop` / `.restart` | `mynet start\|stop\|restart` | 本仓 CLI 无这些子命令。**注意**：与 linux 的 `scripts/mynetd start` 不同——openwrt 可能由打包提供 shell 包装器，`mynet_openwrt` 不在工作区，**无法判定真伪，不擅自改** |
- **已修**：`mynet.gnb.refresh.address`（补 `refresh-address` 实现，全仓此前零实现）；
  `mynet.wg.refresh.{address,route}` 由"unknown command/flag"改为**明确报"暂不支持 wireguard"**
  （不再是"看不懂的失败"，但 wg 支持仍属驱动层工作）
- **优先级**：中高（声明即承诺；且挡住 config.sync 的完整实现）

#### CPI-10. `nodes / zones` 域的边界未厘清
- **ID**：CPI-10
- **位置**：`local-api.md` §3 的 `nodes / zones` 行
- **现状**：一行里混了三种东西，归属不同
  - 本机节点**列表** → 本机态，可直接做
  - **切换当前节点** → 要改 `mynet.conf` 的 NodeID 并重载，属本机配置写入
  - **zones** → 平台态（back 上的 zone 列表），按 §0 应走 `relay` 域经网关中继
- **影响**：不厘清就会把平台态数据塞进本机 API，破坏 §0 的信任边界
- **优先级**：中

#### CPI-11. `service.*` 依赖驱动实现生命周期接口（非缺陷，是排期依赖）
- **ID**：CPI-11
- **现状**：本机 API 的 `service.start/stop/reload` 端点已按契约接线，但
  `domain.VPNLifecycleDriver`（Start/Stop/Reload/Capabilities/WriteConfig/Status）
  **尚无任何实现**——`GNBDriver` 有 Capabilities/WriteConfig/Status，缺 Start/Stop/Reload，
  实测不满足该接口（与 `driver-abstraction.md` 的 S1 阶段一致）
- **本机 API 的做法**：类型断言取能力，取不到返回稳定错误码 `unsupported`，
  错误信息里点明属 S3/S5 的工作；**不**代驱动展开 gnb 机制（契约明确禁止）
- **解锁条件**：驱动实现该接口后，本机 API 一行不用改
- **优先级**：记在案（不是本机 API 的债，是驱动层排期）

---

### 🟡 P2 — 控制面相关（2026-09-22 记录）

> CPI-7 为唯一遗留（低优先级：当前 ID 上限恰好等于 float64 精确整数上限，安全）。

#### CPI-7. 命令 ID 经 float64 往返
- **ID**：CPI-7
- **现状**：agent 的 `monitor.Command.ID` 是 `interface{}`，JSON 数字解码成 float64；
  `Execute()` 再 `strconv.FormatInt(int64(v))` 转回字符串
- **当前安全性**：全局 ID 上限 `2^53-1 = 9007199254740991`，**恰好是 float64 精确整数上限**，
  所以当前不会丢精度（实测回调 ID 正确）
- **风险**：一旦 ID 生成范围越过 2^53 就会静默串号。建议 `Command.ID` 改 `json.Number` 或 string
- **优先级**：低（记在案，避免以后扩大 ID 范围时踩）

## ⏸️ 暂缓（用户决策不实现）

### mynet_lua 定时心跳上报
- **决策日期**：2026-05
- **决策来源**：用户明确表示「mynet_lua 心跳先不实现」
- **背景**：spec/plugins.md 定义 client 端定时心跳，mynet_client 已实现，lua 侧暂缺
- **影响**：OpenWrt 节点不主动上报存活；back 仅通过 GNB 隧道流量间接判断
- **重新启用条件**：用户后续主动提出 / 出现因缺心跳导致的业务问题
- **关联 spec**：[spec/plugins.md](spec/plugins.md)

### mynet_lua 远程命令处理（route.refresh / client.update / client.report）
- **决策日期**：2026-05（与心跳一并暂缓）
- **背景**：mynet_client 通过 `cmdLoop` 拉取 `/api/v2/nodes/{id}/commands` 处理；lua 侧未实现
- **影响**：OpenWrt 节点收不到 back 下发的命令；需手动操作或重启服务
- **重新启用条件**：同上

---

## 已修复

| 修复日期 | 问题 | PR / Commit |
|---------|------|------------|
| 2026-05 | mynet_gui 根目录文档归档 | 本次审计 |
| 2026-05 | mynet_client openwrt 注释清理 | 本次审计 |
| 2026-05 | mynet_back 缺 .env.example | 本次审计（新增） |
| 2026-05 | ctl 凭证集中目录建立 | 本次审计（新增 `.credentials/`） |
| 2026-05 | mynet_lua `/api/v1` → `/api/v2`（config.json + deploy.md） | 本次治理 |
| 2026-05 | mynet_lua 下载 URL `download.mynet.club` → `ctl.mynet.club/d` | 本次治理 |
| 2026-05 | mynet_back `.env.production` SESSION_DOMAIN/SANCTUM 域名 dsplat → mynet.club | 本次治理 |
| 2026-05 | mynet_back `.env.production` VITE_REVERB_HOST 一致性修正 | 本次治理 |
| 2026-09-22 | back: monitor 路由被模块级 `auth:sanctum` 覆盖 → 节点无 token 全 401（含心跳） | `836adc52` |
| 2026-09-22 | back: 请求体 gzip 从未被解压 → 真实心跳（必然 >1KB）验证阶段 422 | `836adc52` |
| 2026-09-22 | back: `Services/NodeMonitorDomainService` 空壳遮蔽 `Domain/Services/` 真实现 → 带 node_monitor 的心跳必 500 | `836adc52` |
| 2026-09-22 | back: 节点通道无租户上下文，`TenantScope` fail-closed（`WHERE 1=0`）→ 节点状态永不落库 | `8c40ff72` |
| 2026-09-22 | back: `node_monitor_snapshots` 同秒唯一键 + `create()` → 第二次心跳回滚整事务 | `8c40ff72` |
| 2026-09-22 | back: `monitor_rt_cmds` PK 重命名残留（`where('id')` / `->id` / `'creator'` / `'id'=>uuid`）→ 心跳/回调/指令历史全 500、配置变更命令静默丢失 | `c82a30f7` |
| 2026-09-22 | back: `NodeConfigService` 的 `params` 双重编码 → 节点收到字符串而非对象 | `c82a30f7` |
| 2026-09-22 | agent: 网关中继可被节点用 `X-Node-Id` 头冒充别的节点 | `b7aceeb` |
| 2026-09-22 | agent: gatewayclient 事件合并竞态 → 并发时事件永久丢失 | `d495bd8` |
| 2026-09-22 | agent: gatewayclient 取消 ctx 不生效 → SIGTERM 后卡满 180s 读超时 | `d495bd8` |
| 2026-09-22 | 响应性联动断裂（事件系统删除后节点上线不再触发对端 route.refresh） | `8c40ff72` |
| 2026-09-22 | **CPI-1** 节点启停/重启后台功能整体不可用（`App\Models\Node` 不存在） | `c10aaa45` |
| 2026-09-22 | **CPI-3** `App\Services\NodeRebootService` 三处悬空引用 | `c10aaa45` |
| 2026-09-22 | **CPI-2** back 下发 `mynet.reboot`、agent 只有 `mynet.restart` → 重启静默失效 | `24570da3` |
| 2026-09-22 | **CPI-4** back 命令词汇整体漂移（mynet.boot/shutdown/regenConfig/reloadConfigs/plugin.proxy.reload/upload-log） | `24570da3` |
| 2026-09-22 | **CPI-5** 命令输出没回传 back（`Execute` 把 result 硬写成"命令执行成功"） | `b407e91` |
| 2026-09-22 | **CPI-6** 节点通道 limit 按 IP 60/分（中继后即 300 节点打满）→ 改按已验签节点 180/分 | `fb97a776` |
| 2026-09-22 | **CPI-8** 心跳未收敛到中继 → `relay_heartbeat`（默认开）让心跳也走网关 | `9a3d4e8` |
| 2026-09-22 | 本机能力面骨架落地（信封/token 鉴权/HTTP/传输无关 Service 层） | `ee5467e` |
| 2026-09-22 | 本机能力面 service/config 两域 | `28afbb5` |
| 2026-09-22 | 本机能力面 peers 域 | `790edbd` |

---

## 维护工作流

```
发现跨项目偏差 / 规范冲突
  ↓
0. ★ 先记录，再处理。发现当场就写进本文件「待修复」——不要先动手修、
   更不要把没修的留在脑子里或对话里（上下文一丢就没了）
1. 在「待修复」加条目：ID / 位置 / 现状 / 证据 / 影响 / 修复建议 / 优先级
2. 链接相关 spec / 代码位置；同类问题交叉引用已有文档（如
   mynet-back-namespace-drift.md），不要重复记账
3. 修复完成后移到「已修复」表，commit message 里带上条目 ID
4. 必要时更新 spec/* 或 conventions.md
```

**ID 约定**：`CPI-<n>`（Cross-Project Issue 序号）。新建条目取当前最大值 +1，
只在"待修复 / 暂缓"里分配；修好后 ID 随条目一起进「已修复」表，便于 commit 引用。

**反例（应当避免）**：先在代码里把主问题修掉，顺手发现的周边缺陷只在对话里说一句 ——
下一轮换个上下文就找不回来了。本轮（2026-09-22）那些 CPI 条目就是在补这个课。

**自动校验（已有）**：`scripts/check-command-vocabulary.php` —— 比对 back 声明的远程命令
与 agent `conf/command.*.json` 的真值，back 单方面声明的命令直接以退出码 1 阻断。
这是 CPI-2 / CPI-4 的防复发手段：命令名写错的后果是"节点侧静默失效、back 侧看不出来"，
只靠人眼迟早再漂一次。改了任一侧命令词汇就顺手跑一遍。
