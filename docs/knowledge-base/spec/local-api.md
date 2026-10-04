# SPEC: 本机能力面（local-api）

> 更新：**2026-10-05**（裁定 B2「本机面改文档承认现状」的落点）| 原来源：~~`mynet_agent/docs/API.md`~~
> ⚠️ 那份接口稿 v0 **文件已不在仓里**（`ls mynet_agent/docs/` 无 `API.md`），而本文件头部原先还写着
> 「实现细节仍以它为最新」⇒ 那是一个指向空气的 SSOT 双层引用。**现在实现细节的唯一真相是代码**：
> `mynet_agent/mynetd/localapi/{server,handlers,config_api,proxy_api,wizard,node_status,logs,relay,gui}.go`，
> 路由全集在 §3。
> 适用：**mynet_agent**（唯一实现）；`mynet_gui`（浏览器壳）、`mynet_openwrt`（LuCI 壳，只调不改）
> ⚠️ 本行原写的 `mynet_ui` **不是本 workspace 的仓**（根目录只有 `mynet_gui`，另有 `mynet_web`/`mynet_desktop`）
> 状态：**契约 SSOT**（跨仓引用此处）
> 关联：[`control-plane.md`](control-plane.md)（GUI 接入模型与 `relay` 域）
>
> **实现进度**（2026-09-22 起，落在 `mynet_agent/mynetd/localapi`）：
>
> ⚠️ 2026-10-05 复核更正：本行原写 `mynet_agent/internal/localapi`，但 V2 树里**没有 `internal/` 目录**，
> 实际路径是 `mynetd/localapi`。下表各行的「✅ 已实现」按此路径复核，relay 一行已更正为 ❌。
> **同日的第二次复核又把 relay 那行改回 ✅**：路由 `/api/v1/relay` 现在确实存在
> （`mynetd/localapi/server.go:84` + `relay.go` 整套实现），而表里其余各行描述的
> `/api/v1/service/*`、`/config/sync|refresh`、`/proxy/enable|disable`、`/zones/*`、`/peers`
> **在路由表里一条都没有** —— 真实路由清单见 §3（已按代码重写）。
>
> ⚠️ **下表整体降级为「2026-09 的域视角 / 意图登记」**：它记的是「这个能力该由谁做、归属在哪」，
> 这些结论仍然成立（proxy 标志归 back 持有、zone ≡ tenant、config 复用三条 refresh 服务等）。
> 但**路径名与「✅/⚠️ 端点就位」一列一律以 §3 为准**：status 的 `gnb{…hot_restart}` 字段、
> service 行的「稳定错误码 `unsupported`」、peers 与 zones 的端点，2026-10-05 复核**都不存在**。
> 不在这里逐行改写是刻意的 —— 免得同一个事实有两处真相。
>
> | 域 | 状态 | 说明 |
> |----|------|------|
> | status | ✅ 已实现 | `gnb{running,pid,node_id,vpn_type,tun_iface,hot_restart}` + `agent{version,pid}`；`hot_restart` 如实报 `false`（驱动未声明该能力）。**`proxy` 子对象尚未实现**（见 proxy 行） |
> | service | ⚠️ 端点就位 | `start/stop/reload` 已按契约接线，但**驱动尚未实现 `VPNDriver.Reload()`/生命周期接口**，故如实返回稳定错误码 `unsupported`。按 [`driver-abstraction.md`](driver-abstraction.md) 这属 S3/S5 的工作；驱动实现后本机 API 一行不用改 |
> | config | ✅ sync/refresh | 复用 `NodeRefreshService` + `RouteRefreshService` + `AddressRefreshService`（即心跳命令链里 `mynet.node.update`/`mynet.route.update` 的落地实现）——**node / route / address 三者都已覆盖**，符合 §3 的「拉 node/route/address + keys」。该路径写的是渲染后的 gnb 配置，按分层终应收敛到 `VPNDriver.WriteConfig(bundle)` |
> | proxy | ✅ 已实现 | 标志**归 back 持有**（`node_configs.config` 的 `proxy`），node.conf 只是它的投影——这样配置同步不会把本地改动冲掉。`GET /proxy` 读回 back 值 + node.conf 投影；`enable/disable` 经中继写 back 再 `config.sync` 让 node.conf 跟上。已在 `config/vpn.php` 的 gnb expert 参数里加 `proxy`（渲染成 `proxy on|off`）。实测：开→back 落库→node.conf 出现 `proxy on`；关→回退 |
> | nodes / zones | ✅ 已实现 | **zone ≡ tenant**（用户确认；back 亦把 tenant 切换写作「切换当前区域」）。两域是 relay 之上的具名薄封装：数据源在 back，并**合并本机态**——当前区域＝本节点所属租户、当前节点＝`mynet.conf` 的 NodeID（裸 relay 给不了）。`GET /zones`、`GET /nodes`、`POST /zones/switch` 均已实现并真环境实测。「切换当前**节点**」（改 `mynet.conf` + 重载）未做，与 CPI-11 同块依赖 |
> | peers | ✅ 已实现 | 复用驱动的 `GetAllPeers` / `GetNetworkSummary`（即 `gnb_ctl -s` 的解析结果），不重复解析 |
> | logs | ✅ 已实现 | 读日志环形缓冲（与写往文件/控制台的是同一份格式化行） |
> | relay | ❌ **未实现（2026-09-24 复核更正）** | 本行原写「✅ 已实现…已对真 back 实测（`/api/v1/nodes` 200 + admin 403）」——**不成立**。V2 树里 `mynetd/localapi/server.go:56-65` 的路由表**没有** `/api/v1/relay`，全仓也搜不到任何 relay 实现（只有 `gateway/server.go` 的注释与 `cmd_setup.go:129` 的一句打印）。原记录描述的是 V1 的工作，从未搬进 V2。见 `long-range-plan-deep.md` §4 的 **V1-8**。<br>⚠️ 注意区分：**gateway 侧**的 `/v1/relay`（C-7 用户面入口）已于 2026-09-24 真实现并对真 back 实测通过（login 200 / nodes 200 / 未鉴权 401 / admin 403）；缺的是 **mynetd localapi 侧**这一跳 |
>
> 传输：HTTP 已实现（`127.0.0.1:<port>`）。⚠️ 本行原写「token 0600 鉴权，写操作强制 POST」——
> 2026-10-05 复核两条都要收窄：**token 只种不验**（屏障是 Host/Origin，§2），**POST 强制只覆盖部分写端点**
> （`/api/v1/relay` 是 ANY 透传、`/api/v1/plugin/proxy` 允许 GET，§2 表末行）。
> **ubus 传输未实现**——按本文件 §1 的「同一能力面，两种传输，一份实现」，
> 传输无关的接缝在 `Server.Ops`（执行器函数，默认 `defaultExecMynet` 走 exec，
> OpenWrt 装配时换成 lua，`handlers.go:108-112,145`）；⚠️ 本行原写的 `localapi.Service`
> **类型不存在**（`grep -rn "type Service struct" mynetd/localapi/*.go` ⇒ 0 命中，只有 `Server`）。
> ubus 只是再加一层薄适配 —— 但适配层也没写，所以 §3 表里的 ubus 列**全部是目标形态**。

---

## 0. GUI 接入模型（控制面视角）

**GUI 只跟本机 mynetd 说话**，浏览器不持有 back 凭据、不直连 back/gateway：

```
浏览器 ──> 127.0.0.1:<port>/api/v1/... ──> mynetd
                                            │
                              本机态 ────────┤ mynetd 直接答
                              平台态 ────────┴─> HTTPS /v1/relay → gateway → back
```

> ⚠️ 2026-10-05 更正：第二跳原画成「WS → gateway → back（中继）」——**不成立**。
> relay 走的是 gateway 的 **HTTPS 反代**（`mynetd/localapi/relay.go:49` 打到 `scheme://host/v1/relay`），
> 与节点的 WS 拨号通道（`/v1/node/connect`）是两回事，代码注释专门写了「不能把 WS 路径原样拼上去」。
> gateway 侧那一跳自称「**无身份、不鉴权**，鉴权一律归 back」（`gateway/relay.go:16-27`）。

| 查询类型 | 数据来源 | 处理方 |
|---------|---------|--------|
| **本机态** | gnb/wg 进程、tun、本机配置、proxy 开关、日志 | mynetd 直接答（本文档 §3 能力面） |
| **平台态** | back 上的节点列表、路由、好友、统计 | mynetd 经 gateway 中继（`relay` 域，见 `control-plane.md` §5.3） |

> GUI 的信任边界止于本机。平台态数据只是「本机代理去取回来的东西」。
> `relay` 域只放行 back 的**用户层业务接口**，禁止 relay 到 `admin/*` 与身份生命周期接口。

---

## 1. 原则

**同一能力面，两种传输，一份实现。**

| 平台 / 传输 | 鉴权 |
|------|------|
| 桌面 / Linux / Windows：`HTTP 127.0.0.1:<port>/api/v1` | ⚠️ **实际屏障是 Host + Origin 校验，不是 token**（`server.go:214-267`）。旧文本写的「本机 local token（文件仅 root/owner 可读）」在 HTTP 侧**没有校验分支** —— token 只被种成会话 cookie（SameSite=Strict）作为第二道，见 §2 |
| OpenWrt：`ubus mynet.<domain>.<action>` | LuCI 会话 + rpcd ACL（ACL 定义在 `mynet_openwrt`）⚠️ **ubus 传输至今未实现** |

实现只有一处：**`mynetd`**。CLI `mynet` 走同一逻辑或调用同一路径。

> 这条是第一原则：`mynet_gui` 与 LuCI 壳**都不持有业务**，只消费本能力面。
> 壳里出现的任何「业务计算」都是需要迁回 mynetd 的债。

---

## 2. 响应信封（2026-10-05 按代码改写）

> ⚠️ **本节原先写的 `{ok,data,error{code}}` 从未存在过** —— 裁定 B2「本机面改文档承认现状」的落点。
> 代码里从第一条响应起就是 `success` 布尔 + 平铺 `message`，**全仓没有任何 `error` 对象、没有任何
> 稳定 `error.code`**（取证见下）。旧文本散布的稳定码（`not_running` / `unsupported` / `not_found` /
> `forbidden` / `unauthorized` / `rate_limited`）在实现里一个都搜不到 ——
> `grep -rn '"not_running"\|"unsupported"\|"not_found"\|"forbidden"\|"unauthorized"\|"rate_limited"' mynetd gateway mynet`
> ⇒ **0 命中**（作为字段值；`mynet/plugin_proxy.go:235` 注释里的「unsupported」是驱动能力词，不是信封码）。

```json
{ "success": true,  "data": { } }
{ "success": false, "message": "…", "data": null }
```

- HTTP 路径前缀：`/api/v1`
- ubus 方法名：`mynet.<domain>.<action>`（例：`mynet.service.reload`）⚠️ **ubus 传输仍未实现**

字段事实源（`writeJSON` 只有一处，`mynetd/localapi/server.go:305-309`；所有处理器手写 map，
没有共享的信封结构体 ⇒ **字段集合是「逐处涌现」的**，下面是全量清点）：

| 字段 | 出现于 | 语义 |
|---|---|---|
| `success` | **每一条**响应（含 `withSecurity` 拒绝时的裸字符串 JSON，`server.go:218`/`:222`） | 唯一的成败标志 |
| `data` | 成功路径；失败路径**也可能有**（见下） | 载荷 |
| `message` | 失败路径；也有「成功但带说明」的变体 | 给人读的话术，直接进 UI |
| `stage` | `config/apply`、向导切节点（`config_api.go:290,314,318,329,334,337`） | 多步流程**停在哪一步**：`setup`/`switch`/`apply`/`start`/`routes`/`running` |
| `need_login` | 仅 `GET /api/v1/config/cloud`（`config_api.go:45`） | 是否该跳登录 |
| `warning` | 成功但部分未达成（`config_api.go:334`：节点起来了、系统路由没全应用） | **`success:true` 配 `warning` 是有意形状**，壳别只判 `success` |
| `steps` / `started` / `driver` | `config/apply` 与各步出口（`config_api.go:400,415,428,329,337`） | 过程明细与结果态 |
| `stdout` / `stderr` / `argv` / `error` | `/api/v1/command` 分派失败时（`handlers.go:119-131`） | 子进程细节。注释写明**必须一起回**：`exit status 1` 本身说明不了任何事 |

三条读法上的硬结论：

1. **失败也带 `data`**。`config_api.go:318,329` 在 500 里回 `{success:false,data:m,…}` —— 前半段
   已经真改写了本机配置，壳需要 `data` 才能告诉用户「切到哪一步坏了、现在生效的是什么」。
   旧文档「失败时 `data: null`」不成立。
2. **没有 `error.code` ⇒ 壳的分支只能靠文本与 HTTP 码**。全仓唯一的分类动作是**文本嗅探**：
   `config_api.go:42` 用 `strings.Contains(err.Error(), "need_login")` 把 502 改判成 401 +
   `need_login:true`；被嗅的那串字来自 CLI 的 JSON 输出（`mynet/config_cloud.go:35`、
   `mynet/cmd_config_face.go:906,926`、`mynet/config_keys_sync.go:17` 都写
   `"error":"need_login"`）；WebUI 侧还有**第二道同样的嗅探**
   （`mynetd/localapi/webui/index.html:2228`：`r.data.need_login || /need_login/.test(r.message)`）。
   ⇒ 这条是**已知债**：判据是子进程话术的一个字符串，改文案即静默失效，两处嗅探还会各自漂移。
   裁定 B2 只把它**写明**，不改代码（改法属于「本机面要不要引入结构化错误码」的独立裁决）。
3. **HTTP 码与 `success` 是两套闸门**。`/api/v1/relay` 成功时**根本不用信封** —— back 的状态码与
   响应体原样回写（`relay.go:87-96`），所以同一条 URL 的失败既可能是 back 的 4xx/5xx JSON
   （back 自己的 `{success,data,message}`，见 [`v1-error-codes.md`](v1-error-codes.md)），
   也可能是本机的 `{success:false,message}` + 400/403/502。壳必须**先看 `data` 形状再分支**。

**防线是 Host / Origin，不是 token**（这一条旧文档也写反了）：

| 事实 | 取证 |
|---|---|
| 每个请求先过 `checkHost`（Host 必须是回环或 `AllowedHosts` 白名单，防 DNS rebinding），再 `checkOrigin`（**有** `Origin` 时必须同源回环；**没有** `Origin` 头的 curl/本机工具直接放行），任一不过 → **403** `{success:false,message:"invalid host"/"invalid origin"}` | `server.go:214-267` |
| local token 只**种**不**验**：`ensureToken` 在首次请求时下发 `mynet_local` 的 HttpOnly + SameSite=Strict 会话 cookie；全仓除该行外**没有任何地方读取并校验它** | `server.go:269-290`；`grep -rn "AuthCookieName" mynetd/localapi/*.go` ⇒ 只有常量定义、写入与比对 cookie 的三处，**无拦截分支** |
| 所以旧文档 §1 表里「本机 local token（文件仅 root/owner 可读）」描述的**鉴权并未生效**；跨站请求的实际屏障是上面两道头校验 + cookie 的 SameSite=Strict | 同上 |
| 写操作强制 POST：**部分成立** —— `config/keys`（`config_api.go:155`）、`config/mode`（`:214`）、`config/active`（`:265`）、`config/apply`（`:373`）、`wizard/login`（`wizard.go:121`）、`wizard/logout`（`:220`）、`command` 与 `command-result`（`handlers.go:35,69`）都是 405 `POST only`/`GET\|POST only`；但 `/api/v1/plugin/proxy` 允许 GET（`proxy_api.go:85-88`），而 `/api/v1/relay` 是 `ANY` 透传（`relay.go:28`）⇒ 本机这一跳**不**按方法设闸 | 逐处 |

**relay 域的真实语义**（落地在 `mynetd/localapi/relay.go`）：

```json
// 命中 back：back 的状态码 + back 的响应体，原样透传，本机不套信封
HTTP/1.1 401 …   {"success":false,"message":"…","code":"…"}   // ← 这是 back 的信封，不是本机的

// 本机这一跳失败：才用本机信封
{ "success": false, "message": "path 不在中继白名单内；鉴权归 back" }      // 403
{ "success": false, "message": "缺少 path 参数：/api/v1/relay?path=…" }   // 400
{ "success": false, "message": "未配置 MYNET_GATEWAYS —— 平台面需要 gateway 中继" }  // 502
```

- relay **只做白名单 + 透传**：`relaypolicy.IsAllowed(rawPath)` 先 deny 后 allow、含 `%` 一律拒
  （`relay.go:34`），method/body/头**原样**转发给 gateway 的 `/v1/relay`（基址由 `MYNET_GATEWAYS`
  或 `agent.json` 的 gateway 推导成 `scheme://host`，WS 路径不参与拼接，`relay.go:101-142`）
- ⚠️ 旧文档承诺的三样**都不存在**：**限流**（代码里无 limiter/429 分支）、
  **带 `Authorization`（用户凭证）**（本机不注入，只把请求已有头透传，跳过 `Host`/`Content-Length`/`Connection`，
  `relay.go:66-74`）、**`X-Tenant-ID`**（`grep -rn "X-Tenant-ID" mynetd/localapi gateway/relaypolicy` ⇒ **0 命中**）
- ⚠️ 旧文档的 `error.detail` / `data.body` / `data.status` / `data.content_type` **也不存在**：
  这一跳是反向代理形状，不是「把 back 响应装进本机信封」
- 路径白名单**默认拒绝**，清单唯一定义在 `relaypolicy`（网关与本机两道闸共用一份，防漂移）。
  ⚠️ 「具体清单」是 [`control-plane.md`](control-plane.md) §10 的待决项，当前是依据
  §5.3 原则与 §5.2 举例给出的**起始清单**（nodes / route / social / user(s) /
  notifications / service(s) / mynet + 节点通道），可随契约演进调整

**鉴权链路**（2026-10-05 按代码改写；旧图把「节点 WS」和「relay 反代」画成了同一条）：

```
浏览器 ──(只有 Host/Origin 这一道屏障，§2)──> mynetd
   mynetd ──(HTTPS 反代 /v1/relay，头原样透传)──> gateway ──(反代)──> back 用户面
   节点身份（Ed25519 / HMAC）在**另一条通道**上：WS 心跳 `/v1/node/connect`，不在 relay 里
```

- GUI 不持有 back 凭据（§5.1）；**向导登录拿到的会话写进本机 `credential.json`(0600) 给 CLI 复用，
  不给浏览器**（`wizard.go:96-109` 的注释就是这条取舍的理由：token 进浏览器 = XSS 泄漏面）
- ⚠️ 旧文本「网关只证明节点身份…用户层的 `Authorization` 由节点带上、网关**按路径**决定是否透传」
  在 relay 这条路上**不成立**：gateway 用的是 `httputil.ReverseProxy`，Director 只改
  `URL`/`Host`、剥 `RequestURI`（`gateway/relay.go:101-115`），**没有任何按路径剥头/加头的分支**，
  而它自己声明「无身份、不鉴权」（`:16-27`）；`relaypolicy` 的口径也是「纯路径，不含鉴权语义」（B3）。
  本机这一跳同样只转发现有头（跳过 `Host`/`Content-Length`/`Connection`，`relay.go:66-74`）
- ⚠️ 由此留一个**待核问题**（不属 B2 范围，别当已解决）：`/gui/api/*` 改写成 relay 后
  （`gui.go:27-34`），既然本机与网关都不注入凭据，那 GUI 的平台态调用**靠什么带上 back 会话**？
  内嵌 WebUI 不用 relay（`grep -c "api/v1/relay" webui/index.html` = 0，它的平台态走
  `config/cloud` 这类「mynetd 叫 CLI 去取」的端点），而 mynet_gui 侧尚未取证
- 该路径白名单由 `relaypolicy` 统一定义，网关侧与节点侧共用，避免两边漂移
- ⚠️ **代码注释自己也说错了一处**（本批只登记、不改码，改它属 mynet_agent 侧）：
  `wizard.go:92-95` 写「口令**只经 stdin** 交给 `mynet auth login --password-stdin`」，
  `handlers.go:91-94` 还以同一条理由放行 `auth` 白名单 —— 但 `handleWizardLogin` 实际是把
  `{email,password,…}` POST 给 `relayToBack`（`wizard.go:140-150`），
  而 `execWithStdin`（`:283-295`）**全仓零调用方**（`grep -rn "execWithStdin" mynet_agent` ⇒ 只有定义）。
  两条都是**代码级取证**：不改变「口令不进 argv、不进日志」的结论（它走 JSON body），
  但「走 stdin/CLI」这句是错的 ⇒ 要么代码回到注释，要么注释按 §3 那行改

---

## 3. 能力面（2026-10-05 按代码路由表重写；ubus 一列是目标形态）

> ⚠️ 裁定 B2 的第二处「文档先不说谎」：本节原表里的 `/api/v1/service/{start,stop,reload}`、
> `/api/v1/config/{sync,refresh}`、`/api/v1/proxy{,/enable,/disable}`、`/api/v1/zones{,/switch}`、
> `/api/v1/peers` **一条都不在路由表里**（`grep -rn "mux.HandleFunc" mynetd/localapi/*.go` ⇒ 全集就是下表）。
> 启停/重载/同步这些**意图**现在的真实入口有两个：`POST /api/v1/command` 的子命令分派，
> 以及 §5 的 back 心跳命令（`service.reload` 是 **Op 不是 HTTP 端点**，`mynetd/commands/commands.go:39,116`）。
> 旧文档按「域」画的这张表**作为目标形态保留**，但读者必须知道它未落。

| 路径 | 方法 | 真实行为 / 响应形状 | 源 |
|---|---|---|---|
| `/` | GET | `Accept` 含 `text/html` 且装了 WebUI → **302 `/ui/`**；否则 `{success,data:{name,local,time,has_web}}` | `server.go:311-330` |
| `/api/v1/status` | GET | **只报 mynetd 自己**：`{daemon:"mynetd",listen,role:"transport-only"}`。旧文档在此处写的 `gnb{…hot_restart}` / `proxy{…}` / `agent{…}` 三块，这个端点一个都不给（`hot_restart` 作为字段名在全仓 0 命中，驱动侧现在叫 `HotReload`，`shared/domain/vpn_lifecycle_driver.go:10`） | `server.go:334-343` |
| `/api/v1/node-status` | GET | 节点态**六态机**：`state` / `one_line` / `detail` / `primary_action` / `actions{动作id:可点}` / `labels{动作id:文案}` + 明细 `home,gnb_bin,installed,configured,process_up,process_pid,tun_iface,overlay_ip,tun_up,routes_ok,node_id,active_node_id,config_mismatch,peers_total,peers_routed,missing_routes,uptime_sec,mtu`。⚠️ `actions` 的注释原话是「前端按这张表禁用/启用，**不要自己再判一遍状态**」⇒ 按钮权威在这里，不在壳里 | `node_status.go:36-68,739-757` |
| `/api/v1/system.json` | GET | 直读 detector 快照（不 fork）；缺文件 → **404** `{success:false,message:"system.json 不存在，请先 mynet detector"}` | `handlers.go:19-26` |
| `/api/v1/logs` | GET only（其他 405） | query 是 **`?lines=`**（默认 80，>400 忽略）—— 旧文档写的 `?n=100` 不存在；内存事件环 tail，逐行过 `redactSecrets` | `logs.go:21-58` |
| `/api/v1/metrics` | — | 分派 `mynet metrics --json` | `handlers.go:29-31` |
| `/api/v1/command` | POST only | 通用子命令分派：body `{"argv":["detector","--json"]}`；`argv[0]` 必须在 `allowedSubcommand` 白名单（**22 项**，含向导用的 `auth`/`tenants`/`node`）否则 400 `subcommand not allowed`；`argv` 是 `service start\|stop` 时顺带记「开机期望隧道」开关（`handlers.go:52-63`）。⚠️ 成功时 `data` 是**子进程原始件** `{argv,stdout,stderr,bin}` —— 不是结构化业务对象，壳拿到的是文本（过 `sanitizeWizardOut` 脱敏） | `handlers.go:33-65,95-106,108-166` |
| `/api/v1/command-result` | POST only | E-4：Lua / `mynet` 把执行结果回灌 mynetd，body `{id,ok,message,source}`；缺 `id` → 400。注意这里的 `ok` 是**回灌体的字段名**，与旧信封那个 `ok` 无关 | `handlers.go:67-87` |
| `/api/v1/config/{mode,tenants,active,keys,identity,runtime,cloud,apply}`、`/api/v1/plugins` | 读走 GET；`mode`/`keys`/`active`/`apply` 的写侧 POST only（405 `GET\|POST only`） | 一律经 `opsJSON` 调 `mynet <子命令> --json`；`config/apply` 是唯一带 `stage` 推进的（§2 表）；`config/cloud` 是唯一做 `need_login` 判级的（502→401） | `config_api.go:25-33,38-68,139-…` |
| `/api/v1/nodes` | GET | 本机**会话表**视图：`data:{focus,parallel,nodes}`（焦点 + 并行会话数 + 列表）。⚠️ 旧文档写的「平台态节点列表（经 relay）+ 本机态标记」**不是这个端点** —— 平台态列表在 relay/`config/cloud` 那一侧 | `server.go:102-114` |
| `/api/v1/nodes/switch` | POST only | D-10/R5-1：只切 UI **焦点**（body `{node_id}`，缺 → 400 `node_id required`）；**并行会话不互踢** | `server.go:117-133` |
| `/api/v1/plugin/proxy` | GET \| POST（其他 405） | proxy 意图态读写；标志归 back 持有的口径不变（本节顶部那张表的 proxy 行仍成立，**只有路径名不同**：`/api/v1/plugin/proxy`，不是 `/api/v1/proxy*`） | `proxy_api.go:38,70-92` |
| `/api/v1/wizard/{login,logout,state}`、`/api/v1/node-status` | login/logout **POST only**（405） | 登录真实链路：浏览器 → mynetd →（`relayToBack`，`wizard.go:22-23`「本机 WebUI 的唯一出网方式」）gateway `/v1/relay` → back `/api/v1/auth/login`，body 是 `{email,password,device_name,device_fingerprint}`；解 back 信封 `{success,data:{user,tenant_id,auth_token,refresh_token,…}}`，**token 落本机 `credential.json`(0600)、不回浏览器**（`:96-101` 的理由：token 进浏览器 = XSS 泄漏面）。失败出口有两种形状：转发/解析失败 → 502，back 业务失败 → **沿用 back 的状态码**（`writeJSON(w, code, …)`，`:176-182`）| `wizard.go:22-23,96-109,120-190,219-236` |
| `/api/v1/relay?path=` | ANY | 见 §2 的 relay 语义：back 状态码 + back 响应体**原样回写、不套本机信封** | `relay.go:28-97` |
| `/gui/`（含 `/gui/api/v1/*`） | GET | 托管 mynet_gui 产物（找不到 `dist/spa` → 404 提示设 `MYNET_GUI_DIR`）；其中 **`/gui/api/*` 在 mynetd 内部改写成 relay**（`q.Set("path", p)` 后调 `handleRelay`）⇒ GUI 的「平台态」是这条，本机侧**不注入凭据** | `gui.go:21-45` |
| `/ui/` | GET | 内嵌 WebUI 静态资源；**未 embed 时这条根本不注册** | `server.go:93` |

### `GET /api/v1/status` 的真实响应（旧文档的示例是假的）

```json
{ "success": true,
  "data": { "daemon": "mynetd", "listen": "127.0.0.1:8096", "role": "transport-only" } }
```

节点能不能用要看 `/api/v1/node-status`；gnb 进程/隧道/路由/peers 的明细都在那里。

---

## 4. 热重启语义（现状：走命令面，不是 HTTP 端点）

```
1. 同步 node.conf：ctl-block / pid-file / daemon off / proxy on|off
2. gnb_ctl -b <confDir>/gnb.map -w core.flag=2
3. 失败且非 Windows → kill -HUP <pid>
4. 仍失败 → 冷 stop + start
5. 成功后：重放 OS 路由；若 proxy 需要则重放系统策略层
```

⚠️ **2026-10-05 更正**：旧文档在这里写的响应
`{ "ok": true, "data": { "reload": "hot"|"cold"|"cold_start", "method": "gnb_ctl", "pid": 1234 } }`
**从未存在过**（`grep` 无 `cold_start`、无 `reload` 字段）。现状是两条入口，都不返回这个形状：

- back 心跳命令 `service.reload`（`mynetd/commands/commands.go:39` 的 `OpServiceReload`，
  `:116` 规划成 `serviceArgv("reload", name)`）⇒ 走 §5 那条链，结果经 `POST /api/v1/command-result` 回灌
- 本机主动：`POST /api/v1/command {"argv":["service","reload",…]}` ⇒ `data` 是 `{argv,stdout,stderr,bin}` **文本**

> ⚠️ 步骤 1-3 是 **gnb 特有**。按 `driver-abstraction.md`，本逻辑应落在
> `VPNDriver.Reload()` 内，本机 API 只表达「重载」意图，不暴露 gnb 机制。
> 驱动侧的能力位现在叫 `HotReload`（`shared/domain/vpn_lifecycle_driver.go:10`）。

---

## 5. 管理端命令（mynet_api → mynetd）

心跳响应中的 `commands[]`：

| action | mynetd 处理 |
|--------|-------------|
| `config.refresh` | sync +（在跑则）reload；callback |
| `service.restart` / `gnb.reload` | §4 reload；callback |
| `gnb.start` / `gnb.stop` | start / stop；callback |
| `route.refresh` | 重写 route/keys + reload；callback |
| `client.update` | 可选，交给 `mynet_desktop` / updater |

callback：`PATCH .../commands/{id}/result`（以 back 为准）。

---

## 6. 非目标

- 不做 uhttpd / nginx 反代（OpenWrt 走 LuCI 同源 ubus）
- 不在客户端注入 gnb 全量路由；出口=指定下一跳（等同 route.conf）
- 不交付 TUI
- 壳（ui / LuCI）不直接访问 `back` / `ctl`

---

## 7. 待定（实现前确认）

1. ~~HTTP 端口默认值与 local token 落盘路径~~ → **已临时定**：为避免阻塞实现，取了明确
   默认值并全部允许配置覆盖（`mynetd.json` 的 `local_api.listen` = `127.0.0.1:8096`、
   `local_api.token_file` = `<MYNET_HOME>/var/run/local-api.token`）。`mynet_desktop`
   不在当前工作区、无法咨询；打包侧有约定后**改配置即可，不必改代码**
2. ubus 方法名大小写统一（OpenWrt 惯用 `mynet.service.reload`）——**仍待定**：
   ubus 传输尚未实现，等 OpenWrt 侧接入时一并定
3. WebUI 静态资源由 mynetd embed，还是桌面 embed、OpenWrt 走 LuCI 壳——**仍待定**：
   属壳的交付形态，不影响能力面本身（§6 已明确不做反代）
