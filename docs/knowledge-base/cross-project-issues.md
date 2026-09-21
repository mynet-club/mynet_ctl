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

### 🟠 P1 — 控制面落地时发现的阻断项（2026-09-22 记录，发现即记）

> 来源：控制面任务链第 4/5 单元落地过程中撞出来的。**记录在前、处置在后**，
> 避免"当场只修了主问题、周边缺陷随上下文一起丢掉"。

#### CPI-1. 节点启停/重启功能整体不可用（命名空间漂移残留）
- **ID**：CPI-1
- **位置**：[mynet_back/app/Modules/Monitor/Domain/Services/NodeOperatorDomainService.php:5](../../../mynet_back-integration/app/Modules/Monitor/Domain/Services/NodeOperatorDomainService.php)
- **现状**：`use App\Models\{MonitorRtCmd, Node, User};` —— 该命名空间下只有
  `AuditLog / DatabaseNotification / Tenant / TenantUser / User`，**没有 `Node`、没有 `MonitorRtCmd`**
- **证据**：实测调用 `NodeOperatorDomainService::executeAction()` 直接抛
  `Error: Class "App\Models\Node" not found`（同文件 :61）→ 后台"启动/停止/重启节点"三个动作全部不可用
- **修复动作**：改用真实位置 `App\Modules\VPN\Models\Node`、`App\Modules\Monitor\Models\MonitorRtCmd`
- **归属**：属 [mynet-back-namespace-drift.md](mynet-back-namespace-drift.md) 那一类的**残留项**（T42 未清干净）
- **优先级**：高（用户可见功能直接 500）

#### CPI-3. `App\Services\NodeRebootService` 三处引用不存在的类
- **ID**：CPI-3
- **位置**：[RouteRequestDomainService.php:513](../../../mynet_back-integration/app/Modules/VPN/Domain/Services/RouteRequestDomainService.php)、
  [NodeDomainService.php:219](../../../mynet_back-integration/app/Modules/VPN/Domain/Services/NodeDomainService.php)、
  [SocialDomainService.php:226](../../../mynet_back-integration/app/Modules/Social/Domain/Services/SocialDomainService.php)
- **现状**：`app(\App\Services\NodeRebootService::class)`，真类是
  `App\Modules\VPN\Services\NodeRebootService`
- **影响**：路由请求审批、节点域、社交域里任何走到"需要重启节点"的分支都会 fatal
- **优先级**：高（同一类漂移，一起清）

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

### 🟡 P2 — 控制面相关（2026-09-22 记录）

#### CPI-5. 命令输出没有回传给 back
- **ID**：CPI-5
- **位置**：[command/executor.go](../../../mynet_agent-integration/internal/daemon/command/executor.go)
- **现状**：`Execute()` 成功时把 `result.Result` 硬写成 `"命令执行成功"`；`executeFromMapping()`
  抓到的 stdout 只进本地日志，`CommandResult` 拿不到
- **影响**：back 侧命令记录里永远只有一句"命令执行成功"，看不到真实输出；
  `upload-log` 这类以输出为结果的功能会失去意义
- **证据**：真环境端到端实测，back 落库 `result = "命令执行成功"`，而节点日志里
  `[CommandMapping] 执行成功: e2e-ok 6329822596533071`（真实 stdout 只在本地）
- **优先级**：中

#### CPI-6. 节点面路由的 `throttle:api` 按 IP 限流（待确认）
- **ID**：CPI-6
- **位置**：[VPN/Routes/api.php](../../../mynet_back-integration/app/Modules/VPN/Routes/api.php) monitor 组
- **现状**：摘 `auth:sanctum` 时保留了 `throttle:api`；Sanctum 下限流键是用户，现在没有用户，
  退化为按 IP
- **风险（待验证）**：NAT 后多个节点共用出口 IP 时可能互相挤占配额；默认 60/min 对
  5 分钟一次的心跳尚有余量，但节点数多、或命令轮询密集时需要重新评估
- **优先级**：中（需实测确认实际键与阈值后再定）

#### CPI-7. 命令 ID 经 float64 往返
- **ID**：CPI-7
- **现状**：agent 的 `monitor.Command.ID` 是 `interface{}`，JSON 数字解码成 float64；
  `Execute()` 再 `strconv.FormatInt(int64(v))` 转回字符串
- **当前安全性**：全局 ID 上限 `2^53-1 = 9007199254740991`，**恰好是 float64 精确整数上限**，
  所以当前不会丢精度（实测回调 ID 正确）
- **风险**：一旦 ID 生成范围越过 2^53 就会静默串号。建议 `Command.ID` 改 `json.Number` 或 string
- **优先级**：低（记在案，避免以后扩大 ID 范围时踩）

#### CPI-8. 心跳仍未收敛到网关中继
- **ID**：CPI-8
- **现状**：控制面（事件/取命令/回报）已全部经网关中继，但**心跳本身仍直连 back**
- **影响**：back 节点接口还不能真正收内网（spec/control-plane.md §6.3 第 3/4 步的剩余部分）
- **优先级**：中（gateway 已具备能力，缺的是切换与验证）

---

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
