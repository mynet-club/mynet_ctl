# SPEC: 本机能力面（local-api）

> 更新：2026-09-22 | 来源：`mynet_agent/docs/API.md`（接口稿 v0）
> 适用：**mynet_agent**（唯一实现）；`mynet_ui`、`mynet_openwrt`（壳，只调不改）
> 状态：**契约 SSOT**（跨仓引用此处；实现细节仍以 `mynet_agent/docs/API.md` 为最新）
> 关联：[`control-plane.md`](control-plane.md)（GUI 接入模型与 `relay` 域）
>
> **实现进度**（2026-09-22 起，落在 `mynet_agent/internal/localapi`）：
>
> | 域 | 状态 | 说明 |
> |----|------|------|
> | status | ✅ 已实现 | `gnb{running,pid,node_id,vpn_type,tun_iface,hot_restart}` + `agent{version,pid}`；`hot_restart` 如实报 `false`（驱动未声明该能力）。**`proxy` 子对象尚未实现**（见 proxy 行） |
> | service | ⚠️ 端点就位 | `start/stop/reload` 已按契约接线，但**驱动尚未实现 `VPNDriver.Reload()`/生命周期接口**，故如实返回稳定错误码 `unsupported`。按 [`driver-abstraction.md`](driver-abstraction.md) 这属 S3/S5 的工作；驱动实现后本机 API 一行不用改 |
> | config | ✅ sync/refresh | 复用 `NodeRefreshService` + `RouteRefreshService` + `AddressRefreshService`（即心跳命令链里 `mynet.node.update`/`mynet.route.update` 的落地实现）——**node / route / address 三者都已覆盖**，符合 §3 的「拉 node/route/address + keys」。该路径写的是渲染后的 gnb 配置，按分层终应收敛到 `VPNDriver.WriteConfig(bundle)` |
> | proxy | ⬜ 未实现 | 落点未定：契约要求写 `proxy on|off` 到 node.conf，而渲染器目前**不输出这一行**（`BundleFlags.Proxy` 未被使用）。要么让驱动从 bundle 渲染，要么另定标志位——属需决策项 |
> | nodes / zones | ⬜ 未实现 | 「列表」属本机态可做；「切换当前节点」要改 `mynet.conf` 并重载，且 zones 属**平台态**（应经 `relay` 域），需先厘清边界 |
> | peers | ✅ 已实现 | 复用驱动的 `GetAllPeers` / `GetNetworkSummary`（即 `gnb_ctl -s` 的解析结果），不重复解析 |
> | logs | ✅ 已实现 | 读日志环形缓冲（与写往文件/控制台的是同一份格式化行） |
>
> 传输：HTTP 已实现（`127.0.0.1:<port>`，token 0600 鉴权，写操作强制 POST）。
> **ubus 传输未实现**——按本文件 §1 的「同一能力面，两种传输，一份实现」，
> 能力逻辑已在传输无关的 `localapi.Service` 里，ubus 只是再加一层薄适配。

---

## 0. GUI 接入模型（控制面视角）

**GUI 只跟本机 mynetd 说话**，浏览器不持有 back 凭据、不直连 back/gateway：

```
浏览器 ──> 127.0.0.1:<port>/api/v1/... ──> mynetd
                                            │
                              本机态 ────────┤ mynetd 直接答
                              平台态 ────────┴─> WS → gateway → back（中继）
```

| 查询类型 | 数据来源 | 处理方 |
|---------|---------|--------|
| **本机态** | gnb/wg 进程、tun、本机配置、proxy 开关、日志 | mynetd 直接答（本文档 §3 能力面） |
| **平台态** | back 上的节点列表、路由、好友、统计 | mynetd 经 gateway 中继（`relay` 域，见 `control-plane.md` §5.3） |

> GUI 的信任边界止于本机。平台态数据只是「本机代理去取回来的东西」。
> `relay` 域只放行 back 的**用户层业务接口**，禁止 relay 到 `admin/*` 与身份生命周期接口。

---

## 1. 原则

**同一能力面，两种传输，一份实现。**

| 平台 | 传输 | 鉴权 |
|------|------|------|
| 桌面 / Linux / Windows | `HTTP 127.0.0.1:<port>/api/v1` | 本机 local token（文件仅 root/owner 可读） |
| OpenWrt | `ubus mynet.<domain>.<action>` | LuCI 会话 + rpcd ACL（ACL 定义在 `mynet_openwrt`） |

实现只有一处：**`mynetd`**。CLI `mynet` 走同一逻辑或调用同一路径。

> 这条是第一原则：`mynet_ui` 与 LuCI 壳**都不持有业务**，只消费本能力面。
> 壳里出现的任何「业务计算」都是需要迁回 mynetd 的债。

---

## 2. 响应信封

```json
{ "ok": true,  "data": { }, "error": null }
{ "ok": false, "data": null, "error": { "code": "not_running", "message": "..." } }
```

- HTTP 路径前缀：`/api/v1`
- ubus 方法名：`mynet.<domain>.<action>`（例：`mynet.service.reload`）

`error.code` 为稳定标识符（供壳做分支），`message` 仅供人读。

---

## 3. 能力面（双通道映射）

| 域 | HTTP | ubus | 行为 |
|----|------|------|------|
| **status** | `GET /api/v1/status` | `mynet.status` | gnb 进程/PID/tun、proxy 开关、版本、mynetd 自身 |
| **service** | `POST /api/v1/service/start` | `mynet.service.start` | 启动当前节点 GNB |
| | `POST /api/v1/service/stop` | `mynet.service.stop` | 停止 |
| | `POST /api/v1/service/reload` | `mynet.service.reload` | **优先热重启**（§4），回落冷启 |
| **config** | `POST /api/v1/config/sync` | `mynet.config.sync` | 拉 node/route/address + keys |
| | `POST /api/v1/config/refresh` | `mynet.config.refresh` | sync 后若在跑则 reload |
| **proxy** | `GET /api/v1/proxy` | `mynet.proxy` | enabled / mode / gnb_proxy / node.conf flag |
| | `POST /api/v1/proxy/enable` | `mynet.proxy.enable` | 写 `proxy on`（gnb_native） |
| | `POST /api/v1/proxy/disable` | `mynet.proxy.disable` | 写 `proxy off` |
| **nodes / zones** | `GET /api/v1/nodes` 等 | `mynet.nodes` / `mynet.zones` | 列表、切换当前节点 |
| **peers** | `GET /api/v1/peers` | `mynet.peers` | `gnb_ctl -s` 摘要 |
| **logs** | `GET /api/v1/logs?n=100` | `mynet.logs` | 环形缓冲 tail |

### status 响应示例

```json
{
  "gnb": { "running": true, "pid": 1234, "node_id": "1001",
           "vpn_type": "gnb", "tun_iface": "utun4", "hot_restart": true },
  "proxy": { "enabled": true, "mode": "gnb_native", "node_conf": "on" },
  "agent": { "version": "0.1.0", "pid": 99 }
}
```

---

## 4. 热重启语义（`service.reload`）

```
1. 同步 node.conf：ctl-block / pid-file / daemon off / proxy on|off
2. gnb_ctl -b <confDir>/gnb.map -w core.flag=2
3. 失败且非 Windows → kill -HUP <pid>
4. 仍失败 → 冷 stop + start
5. 成功后：重放 OS 路由；若 proxy 需要则重放系统策略层
```

响应：`{ "ok": true, "data": { "reload": "hot"|"cold"|"cold_start", "method": "gnb_ctl", "pid": 1234 } }`

> ⚠️ 步骤 1-3 是 **gnb 特有**。按 `driver-abstraction.md`，本逻辑应落在
> `VPNDriver.Reload()` 内，本机 API 只表达「重载」意图，不暴露 gnb 机制。

---

## 5. 管理端命令（mynet_back → mynetd）

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
- 不在客户端注入 gnb 全量路由 / 管理 f 节点
- 不交付 TUI
- 壳（ui / LuCI）不直接访问 `back` / `ctl`

---

## 7. 待定（实现前确认）

1. ~~HTTP 端口默认值与 local token 落盘路径~~ → **已临时定**：为避免阻塞实现，取了明确
   默认值并全部允许配置覆盖（`mynetd.json` 的 `local_api.listen` = `127.0.0.1:8720`、
   `local_api.token_file` = `<MYNET_HOME>/var/run/local-api.token`）。`mynet_desktop`
   不在当前工作区、无法咨询；打包侧有约定后**改配置即可，不必改代码**
2. ubus 方法名大小写统一（OpenWrt 惯用 `mynet.service.reload`）——**仍待定**：
   ubus 传输尚未实现，等 OpenWrt 侧接入时一并定
3. WebUI 静态资源由 mynetd embed，还是桌面 embed、OpenWrt 走 LuCI 壳——**仍待定**：
   属壳的交付形态，不影响能力面本身（§6 已明确不做反代）
