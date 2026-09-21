# SPEC: 插件生命周期（Plugins）

> ⚠️ **本文件描述的是已冻结的旧线（`mynet_lua` / `mynet_client`），不是当前的实现依据。**
> 冻结策略见 [`../integration-plan-v2.md`](../integration-plan-v2.md) §2.2；
> 本目录（`spec/README.md`）声明的唯一实现是 **`mynet_agent`（`mynetd`）**。
> **目标线对应**：目标线已把相关能力拆开：心跳见 [`control-plane.md`](control-plane.md) §6；**proxy 下架**见 §7；updater 归属未定


> 更新：2026-05 | 适用：mynet_client / mynet_lua（心跳命令处理）
>
> **注**：mynet_lua 无插件系统，但 `system.lua` 直接实现了 heartbeat 上报与 `handleCommands` 逻辑，契约与 mynet_client 保持一致。

---

## 插件接口契约

每个插件必须导出以下函数：

```js
init(core)       // 注入 core API（config、manager、log）
start()          // 插件启动（可为 async）
stop()           // 插件停止
status()         // 返回当前状态对象
// 可选钩子：
preStart(nodeId) // GNB 启动前（如：注入 route.conf）
postStart(nodeId, tunIface) // GNB 启动后（如：应用 policy routing）
preStop()        // GNB 停止前（如：清理 policy routing）
```

**core 注入对象包含：**
```js
{
  config,   // ConfigManager（getAppConfig / getUserConfig）
  manager,  // ServiceManager（getStatus / getPidInfo）
  log,      // { info, debug, warn, error }
}
```

**懒加载规则：** 插件不得在顶层 require 重型模块；必须在函数体内按需加载（避免 esbuild 打包陷阱）。

---

## OPERATION: pluginLoader.loadAll()

启动时加载所有已安装插件。

**STEPS:**
1. 读取 `conf/config.json` 中 `plugins` 字段（列出已启用插件名）
2. 对每个插件名：
   - 尝试 `require('plugins/{name}/index.js')`
   - 验证必要导出（`init`、`start`、`stop`、`status`）存在
   - 调用 `plugin.init(core)`
3. 记录加载成功/失败的插件列表

**ERROR PATHS:**
- 插件加载异常 → 记录错误日志，**跳过该插件，不影响其他插件**

---

## OPERATION: pluginLoader.startAll()

服务启动后调用（在所有 postStart hooks 完成后）。

**STEPS:**
1. 对每个已加载插件，调用 `plugin.start()`
2. 异常 → 记录日志，跳过，不影响主服务

---

## OPERATION: pluginLoader.stopAll()

服务停止前或进程退出时调用。

**STEPS:**
1. 对每个已加载插件，调用 `plugin.stop()`
2. 全部并行，等待最多 3s

---

## 内置插件：heartbeat

**功能：** 定期向 mynet_back 上报节点健康状态，接收并执行远程指令。

### heartbeat.start()

**STEPS:**
1. 读取心跳间隔（`config.heartbeat.interval`，默认 300s）
2. 延迟 5s 后发送第一次心跳（等待 GNB 稳定）
3. 此后按间隔循环

### heartbeat._tick()（单次心跳）

**STEPS:**
1. 收集系统指标（CPU/内存/磁盘/VPN 接口状态）
2. 读取共享密钥，生成 HMAC-SHA256 签名
3. `POST /api/v2/monitor/heartbeat` body: `{ node_id, metrics, signature, ... }`
4. 处理响应中的 `commands` 列表（参见下方指令处理）

### heartbeat.handleCommands(commands)

**指令类型映射（SSOT — 两端行为必须一致）：**

| action | 行为 |
|--------|------|
| `config.refresh` | 调用 `configSync.fetchBundle(node_id)` |
| `service.restart` | `service.stop()` + `service.start(node_id)` |
| `gnb.start` | `service.start(node_id)` |
| `gnb.stop` | `service.stop()` |
| `gnb.restart` | `service.stop()` + `service.start(node_id)` |
| `route.refresh` | 重新应用路由注入（Layer 2） |
| `firewall.refresh` | 重新应用防火墙规则（Layer 3） |
| `client.update` | 触发 updater 插件检查并安装更新 |
| `client.report` | 立刻发送一次额外心跳 |

**STEPS（每条指令）：**
1. 执行对应操作
2. `PATCH /api/v2/monitor/commands/{id}/result` body: `{ success: bool, message: string }`  
   签名方式与心跳相同：`HMAC-SHA256("PATCH|/api/v2/monitor/commands/{id}/result|{ts}|{body_json}", hex_key)` → base64  
   请求头：`X-Node-Id` / `X-Timestamp` / `X-Node-Signature`

**ERROR PATHS:**
- 执行失败 → 上报 `{ success: false, message: "错误描述" }`，继续处理下一条指令
- 上报本身失败 → best-effort，忽略错误，不影响下一条指令

---

## 内置插件：updater

**功能：** 定期检查 CTL manifest 是否有新版本，支持自动更新。

### updater.start()

**STEPS:**
1. 检查 `ui_settings.auto_update_enabled`（默认 true）
2. 延迟 60s 后首次检查
3. 此后按间隔循环（默认 3600s）

### updater._checkOnce()

**STEPS:**
1. 读取 `server_config.download_index_url`（默认 `https://ctl.mynet.club/d/apps.json`）
2. 拉取 `apps.json`，解析 `products.client.latest`
3. 与当前版本（`package.json`）比较
4. 有新版本 → 记录 `_latestInfo`，广播 `update_available` 事件（WebUI 可展示通知）

**注意：** updater 只检查+通知，不自动安装（安装由 CLI `mynet update install` 触发）。

---

## 内置插件：proxy（mynet_client 专属）

proxy 插件通过钩子嵌入服务生命周期，**不暴露独立 start/stop 接口**给用户。

### proxy.preStart(node_id)

- 若 `PROXY_ENABLED=1`：执行 `proxy.enable()` 的路由注入步骤（route.conf 写入 pipe 段）

### proxy.postStart(node_id, tun_iface)

- 若 `PROXY_ENABLED=1`：执行 `proxy.start()` 完整流程（参见 proxy.md）

### proxy.preStop()

- 若 proxy policy 运行中：执行 `proxy.stop()`（参见 proxy.md）

**与 mynet_lua 的对应关系：**
- `preStart` → `proxy.route_inject()`（由 rc.mynet 在 GNB 启动前调用）
- `postStart` → `proxy.start()`（由 rc.mynet 在 GNB 启动后调用）
- `preStop` → `proxy.stop()`（由 `/etc/init.d/mynet stop` 调用）

---

## mynet_lua 实现说明（无插件系统）

mynet_lua 不使用插件架构，心跳与命令处理直接在 `luasrc/model/mynet/system.lua` 中实现：

| 功能 | 实现 |
|------|------|
| 心跳上报 | `M.submit_heartbeat()` / `M.run_daemon_heartbeat()` |
| 命令处理 | `M._handle_heartbeat_commands(commands, node_id, nid_str, pub_hex)` |
| 命令结果回调 | `M._report_command_result(cmd_id, nid_str, pub_hex, ok, message)` |

**支持的 action（与 mynet_client 对称）：**

| action | Lua 实现 |
|--------|----------|
| `config.refresh` | 下载并应用 config-bundle |
| `service.restart` | `/etc/init.d/mynet restart` |
| `gnb.start` | `/etc/init.d/mynet start` |
| `gnb.stop` | `/etc/init.d/mynet stop` |
| `gnb.restart` | `/etc/init.d/mynet restart` |
| `route.refresh` | 重新注入系统路由（Layer 2） |
| `firewall.refresh` | `sh FIREWALL_SCRIPT start --interface {iface}`（Layer 3 nftables） |
| `client.report` | 立刻触发一次额外心跳 |

**PATCH 签名实现（Lua）：**
```lua
-- util.hmac_sha256(key_hex, message_str) → hex
-- util.hex_to_base64(hex) → base64
local sig = util.hex_to_base64(
  util.hmac_sha256(pub_hex, method .. "|" .. path .. "|" .. ts .. "|" .. body_json)
)
```
