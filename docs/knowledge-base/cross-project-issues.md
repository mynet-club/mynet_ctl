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

### 🟡 P2 — SSOT 自身一致性（2026-09-22 记录）

#### CPI-18 ~ CPI-21. 配置链路四个缺陷（proxy 域落地时全部撞出来）（均已修）
- **ID**：CPI-18 / 19 / 20 / 21
- **来源**：把「本机 API 的 proxy 域」真跑通，就必须走完配置的读→改→写整条链路，
  于是这四个各自独立、都会让配置"存了不生效"的缺陷全部暴露

| ID | 缺陷 | 症状 | 修法 |
|----|------|------|------|
| **CPI-18** | 仅凭 Bearer token **解析不出租户**：`IdentifyTenant` 第 6 步靠 `property_exists($user,'current_tenant_id')`，而框架与 back 的 User 都没该属性（Eloquent 字段在 `$attributes`，`property_exists` 恒 false） | 租户为空 → `TenantScope` fail-closed（`WHERE 1=0`）→ 用户层查询**静默返回空**、`Node::find` 得"节点不存在"，而 HTTP 是 200 | 新增 `App\Http\Middleware\ResolveTenantFromUser`：框架没能显式识别时用用户在 `tenant_users` 的活跃归属兜底；显式来源（域名 / `X-Tenant-ID`）仍优先 |
| **CPI-19** | `extractConfigByType` 对 `expert`/`sys` **恒返回空数组**（原注释："暂时返回空数组"） | 保存的 expert 字段（如 `proxy`）读取时取不到，只见默认值 → "存了 on 读回还是 off" | 按 `config/vpn.php` 定义归类；未定义字段仍归 base（宁可放错也不丢） |
| **CPI-20** | 节点通道渲染 node.conf 时**丢掉用户配置**：控制器 `array_merge($config['base'], $config['expert'])`，而 `getNodeFullConfig()` 返回的是 `node_info/network_config/additional_config/custom_config`，没有 base/expert 段 → 合并恒空 | **任何一次配置同步都会把节点 node.conf "还原"成默认**，用户改的配置被无声改回 | 改用 `custom_config`（normalize 后的扁平表，生成器正好吃扁平数组） |
| **CPI-21** | `NodeConfig::updateOrCreateRawConfig()` **被调用而从未定义**（与 CPI-17 同类的第二个） | `saveFullConfig` 这条保存路径同样抛 `BadMethodCallException`——两条路径都要有 | 补实现（含 forceSave 刷 `updated_at`） |

> 另修一处悬空命名空间：`VpnConfigGeneratorFactoryDomainService` 被写成
> `App\Domain\Services\VPN\...`（真位置 `App\Modules\VPN\Domain\Services\...`），
> 两处引用（`NodeMonitorController` 的路由渲染、`RouteController`）都会 fatal
> → **节点侧路由刷新此前是坏的**。

- **优先级**：已修（`84dd7170`）

---

#### CPI-17. 保存节点配置整条路径走不通（`updateOrCreateConfig` 从未定义）（已修）
- **ID**：CPI-17
- **位置**：[NodeConfigDomainService.php:123](../../../mynet_back-integration/app/Modules/VPN/Domain/Services/NodeConfigDomainService.php) 调用
  `NodeConfig::updateOrCreateConfig()`，而 [NodeConfig.php](../../../mynet_back-integration/app/Modules/VPN/Models/NodeConfig.php) 只有 `findByNodeId`
- **实测**：`BadMethodCallException: Call to undefined method App\Modules\VPN\Models\NodeConfig::updateOrCreateConfig()`
  → `POST/PUT /api/v1/nodes/{id}/config` 这条保存路径根本走不通
- **与 CPI-2/4/13 同一类**：声明了却做不到（这次是"调用了却没人实现"）
- **已修**：实现为**整体替换 `config`**（调用方是 `saveFullConfig`，传的是重建的全量数据；
  合并会让"清掉某项"永远做不到）。模型带 SoftDeletes 且 node_id 唯一，故从软删记录里找并恢复
- **优先级**：已修（`02319ece`）

---

#### CPI-16. 6 份 spec 的「适用」头仍指向已冻结的旧线（已标注）
- **ID**：CPI-16
- **位置**：`spec/{auth,wizard,service,proxy,plugins,debug-env}.md`
- **现状**：`spec/README.md` 声明唯一实现是 `mynet_agent`，但这 6 份文件的「适用」头写的是
  `mynet_lua` / `mynet_client` —— 已在 `integration-plan-v2.md` §2.2 冻结归档的旧线
- **影响**：读者（含 agent）按索引进到这些文件，会读到旧线做法并照着实现，
  做出与目标线架构相悖的形态。这不是"文档旧"，而是**索引与内容互相矛盾**
- **已做**：6 份文件顶部加横幅 + 逐份写明目标线对应契约；`spec/README.md` 索引增
  「适用线」列。**只标注、不改写内容**——"哪些旧步骤在新线下仍成立"属决策，
  而"这份文件描述的是谁"是事实
- **仍待**：是否把其中某些内容重新基于目标线重写（例如 service.md 的三层模型是否
  仍然成立、还是整体由驱动层承担）——需逐份判断
- **优先级**：中（会误导实现，但不影响运行）

---

### 🟡 P2 — 本机能力面落地时发现的前置（2026-09-22 记录）

> 本轮在把 `spec/local-api.md`（本机能力面）落地到 mynetd 时撞出来的。
> 原则同前：**先记录、再处理**；需要决策的不猜。

#### CPI-11. `service.*` 与「切换当前节点」依赖驱动生命周期接口 —— **按 spec 现在就不该实现**
- **ID**：CPI-11
- **现状**：本机 API 的 `service.start/stop/reload` 端点已按契约接线，但
  `domain.VPNLifecycleDriver`（Start/Stop/Reload/Capabilities/WriteConfig/Status）
  **尚无任何实现**——`GNBDriver` 有 Capabilities/WriteConfig/Status，缺 Start/Stop/Reload，
  实测不满足该接口。本机 API 的做法是类型断言取能力，取不到返回稳定码 `unsupported`
  并点明原因，**不代驱动展开 gnb 机制**（契约明确禁止）。
- **为什么现在不该做（查过 spec 后更正）**：`driver-abstraction.md §5` 的落地顺序是
  **R0–R9**，而生命周期权威收敛是 **R5**，排在 R2（`BundleEnvelope` + `NetworkIntent`
  schema v2）与 R4（原子 Apply：stage→validate→write→reload→health-check→commit/rollback）
  之后。该 spec 明确写着：「**在 R2–R6 完成前，不应继续实现 `WireGuardDriver`；
  否则会把错误抽象固化进代码**」——同理，现在补 `Start/Reload` 会绕过 R4 的原子 Apply
  与 R5 的写入权收敛，等于把两套生命周期权威固化下来。
- **因此**：这不是"缺一个补丁"，而是**被已排期的工作流挡住**。本机能力面除这两项外已齐。
- **顺带更正**：`internal/domain/vpn_lifecycle_driver.go` 的注释原引用 S1/S3/S4/S5
  并称"见 spec §5"，而该章节早已改写成 R0–R9——过期引用，已更正（`988a543`）。
- **优先级**：记在案（非本机 API 的债；等 R2–R5）

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
| 2026-09-22 | **CPI-12** relay 域缺 node→back 用户层鉴权链路 → 已定设计并实现（节点持用户 token，网关按路径透传 Authorization） | 见 relay 系列提交 |
| 2026-09-22 | 本机能力面 relay 域 + relaypolicy 单一策略来源（网关/本机两道闸） | 见 relay 系列提交 |
| 2026-09-22 | **CPI-9** proxy 域落地（标志归 back，node.conf 为投影；读→改→整份写回→同步） | `c335296` |
| 2026-09-22 | relay 补上漏掉的限流（固定窗口 20/秒，超限 429 `rate_limited`） | `c335296` |
| 2026-09-22 | **CPI-18~21** 配置链路四个缺陷 + 一处悬空命名空间 | `84dd7170` |
| 2026-09-22 | **CPI-10** nodes/zones 域落地（zone ≡ tenant；relay 之上的具名薄封装 + 本机态标记） | `4c3025f` |
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

**自动校验（已有）**：
- `scripts/check-command-vocabulary.php` —— 比对 back 声明的远程命令与 agent
  `conf/command.*.json` 的真值，back 单方面声明的命令以退出码 1 阻断
- `scripts/check-command-invocations.php` —— 反方向：真跑 agent CLI 的 `--help`，
  核对 `conf/command.*.json` 里**每条调用**（子命令是否存在、参数是否被接受）。
  已知未决项逐条列在脚本里（带台账 ID）并每次运行都打印，新出现的不一致才 exit 1
  —— 这样既不会漏，也不会因为"永远失败"而被忽略
这是 CPI-2 / CPI-4 的防复发手段：命令名写错的后果是"节点侧静默失效、back 侧看不出来"，
只靠人眼迟早再漂一次。改了任一侧命令词汇就顺手跑一遍。
