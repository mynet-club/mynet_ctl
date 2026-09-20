# SPEC: 本机能力面（local-api）

> 更新：2026-09-21 | 来源：`mynet_agent/docs/API.md`（接口稿 v0）
> 适用：**mynet_agent**（唯一实现）；`mynet_ui`、`mynet_openwrt`（壳，只调不改）
> 状态：**契约 SSOT**（跨仓引用此处；实现细节仍以 `mynet_agent/docs/API.md` 为最新）

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

1. HTTP 端口默认值与 local token 落盘路径（由 `mynet_desktop` 打包约定）
2. ubus 方法名大小写统一（OpenWrt 惯用 `mynet.service.reload`）
3. WebUI 静态资源由 mynetd embed，还是桌面 embed、OpenWrt 走 LuCI 壳
