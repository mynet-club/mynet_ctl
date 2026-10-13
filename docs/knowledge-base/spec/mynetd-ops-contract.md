# mynetd Ops 执行器合同（G3.a 冻结稿）

> 状态：**接口冻结**（2026-10-13）。改字段/语义须双侧同步并 bump `ops_schema`。
> 范围：`mynetd/localapi` / `cmd/mynetd` 经 `Server.Ops` 分派到执行器（`mynet` 子命令）。
> 原则：mynetd 纯通信；系统能力（探测解析、OS 路由、进程、驱动配置）只在执行器。

## 1. 调用形态

```go
// mynetd/localapi/server.go
Ops func(argv []string) (any, error)
```

| 实现 | 平台 | 行为 |
|---|---|---|
| `defaultExecMynet` | 桌面/Windows | `exec mynet <argv...>`，**60s** 超时 |
| lua 执行器 | OpenWrt | `lua -e ...`（`cmd/mynetd/executor_lua.go`） |
| 测试注入 | 全部 | 假 Ops 验超时/非零退出/坏 JSON/部分状态 |

**超时**：60s（`defaultExecMynet`）；调用方不得假设无限等待。
**退出语义**：`err != nil` ⇒ 失败；`out` 仍带回 stdout/stderr/argv（见 §3）。
**秘密**：argv 中 `--private` / password / token 必须经 `redactArgvAny` 再回显；stdout/stderr 走 `sanitizeWizardOut`。

## 2. 命令面（当前已用 + 冻结新增）

### 2.1 已在 Ops 上（保持词不变）

| argv | 用途 | 消费者 |
|---|---|---|
| `apply --active` / `apply --auto` | 驱动配置应用 | config_api, connect_api |
| `service start\|stop <driver>` | 隧道生命周期 | config_api, connect_api |
| `plugin apply proxy` / `proxy apply` | proxy 投影 | proxy_api |
| `config keys set/show/...` | 节点密钥（stdin 私钥） | wizard, config_api |
| `auth login ...` | 会话（stdin 口令） | wizard |

### 2.2 G3 冻结新增（执行器必须提供，mynetd 只调用）

| argv | 输入 | 输出 JSON（`data` 或 stdout 对象） | 失败语义 |
|---|---|---|---|
| `status --json` | 无 | `{state, one_line, process_up, process_pid, tun_iface, tun_up, routes_ok, peers: [...], uptime_sec, mtu, rx_bytes, tx_bytes, ...}` | 超时/非零退出/坏 JSON → 明确 error，**不得**编状态 |
| `route exists --dest CIDR` 或 `--host IP` | 目标 | `{exists: bool}` | 无法判定 → error，**不得**当 exists=false |
| `peer-links --json` | 无 | `{peers: [{id, link, latency_ms, ...}]}` 或空 | gnb_ctl 不可用 → 空数组 + `unknown`，不编链路 |
| `iface-stats --if NAME` | 接口名 | `{mtu, rx_bytes, tx_bytes}` | 读不到字段 → 该字段缺省，不猜 0 |

**字段缺失**：JSON 允许省略可选字段；**禁止**用 0/空串冒充「未知」。
**版本**：输出建议带 `"ops_schema": 1`；v1 允许省略，调用方按缺省 1 解析。

## 3. 返回信封（defaultExecMynet）

成功/失败均返回 `map[string]any`：

```json
{
  "argv": ["status", "--json"],
  "stdout": "...",
  "stderr": "...",
  "bin": "/path/to/mynet",
  "error": "exit status 1"          // 仅失败
}
```

HTTP 层（`/api/v1/command`）包装为 `{success, data|message+stdout+stderr+argv}`，argv 一律脱敏。

## 4. mynetd 禁止事项（B2）

`cmd/mynetd/**`、`mynetd/**` 不得 import / 传递依赖：

- `mynet` 根包、`mynet/tools`、`mynet/detector`、`mynet/apply`、`mynet/metrics`、`mynet/service`
- `shared/config`
- `gopsutil` / `shirou/gopsutil`

**允许**（有意白名单，与本仓 B2 分叉一致）：

- `mynet/keys`（密钥原语，无系统副作用）
- `mynetd/commands`（纯词表）
- 经 `Ops` 注入的执行器调用

**迁移前已知违例**（G3.b 清单）：

| 位置 | 违例 | 归属 |
|---|---|---|
| `mynetd/localapi/node_status.go` | `mynet/driver`、`exec`（ps/ifconfig） | → `status --json` / `peer-links` / `iface-stats` |
| `mynetd/localapi/route_query_*.go` | `exec`（ip/route/route get） | → `route exists` |
| `cmd/mynetd/main.go` | `mynet`、`shared/config` | 组合根可暂时保留，最终走注入 |
| `cmd/mynetd/nodeface.go` | `mynet/keys`（允许）、`shared/config` | config 读写改注入 |
| `mynetd/localapi/wizard.go` | `mynet` | 核对是否仅注释/类型 |

## 5. 变更纪律

1. 先改本文件与 `mynet` 执行器实现 + fixtures，再改 mynetd 调用端。
2. 任一字段改名/删减 = schema bump + 双侧测试同步。
3. `go list -deps ./cmd/mynetd` 在 G3.c 完成时不得再进入 §4 禁止包（多 `GOOS`/`build tag` 均查）。
