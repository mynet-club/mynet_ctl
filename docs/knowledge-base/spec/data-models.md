# SPEC: 数据模型（Data Models）

> 更新：2026-09-21 | 适用：mynet_agent（实现）/ mynet_ui / mynet_openwrt  
> **本文件是所有 JSON/conf 格式的唯一定义处。两端修改格式前必须先更新此处。**
>
> **注意（目标线）**：§4 与 §9 描述的 gnb 原生格式属**驱动适配层**。back 下发的应是 driver-agnostic
> **抽象 bundle**，由 agent 侧 driver 翻译为原生格式。见 [`driver-abstraction.md`](driver-abstraction.md)
> 与 [`../integration-plan-v2.md`](../integration-plan-v2.md) §3 W2。本文件后续将拆为「抽象层」+「gnb adapter」两节。

---

## 1. credential.json — 认证凭证

**路径：**
- mynet_lua: `/etc/mynet/conf/credential.json`
- mynet_client: `$MYNET_HOME/conf/credential.json`

```json
{
  "token": "eyJ...",
  "refresh_token": "eyJ...",
  "user_id": "123456",
  "email": "user@example.com"
}
```

---

## 2. config.json — 应用配置

**路径：**
- mynet_lua: `/etc/mynet/conf/config.json`
- mynet_client: `$MYNET_HOME/conf/config.json`

```json
{
  "mode": "mynet",
  "zone_id": "1396569674824134",
  "node_id": "3722810996882027",
  "server_config": {
    "api_url": "https://back.mynet.club",
    "download_index_url": "https://ctl.mynet.club/d/apps.json"
  },
  "heartbeat": {
    "interval": 300
  },
  "updater": {
    "interval": 3600
  },
  "ui_settings": {
    "auto_update_enabled": true
  }
}
```

**`mode` 取值：** `"mynet"` | `"guest"`

---

## 3. mynet.conf — 运行时 Shell 配置

**格式：** bash `KEY="VALUE"` 可 source  
**路径：**
- mynet_lua: `/etc/mynet/conf/mynet.conf`
- mynet_client: `$MYNET_HOME/conf/mynet.conf`

```sh
NODE_ID="3722810996882027"
ZONE_ID="1396569674824134"
VPN_IF="gnb_tun"
GNB_BIN="/opt/mynet/driver/gnb/bin/gnb"
CONF_DIR="/opt/mynet/driver/gnb/conf/3722810996882027"
```

---

## 4. GNB 配置文件（driver/gnb/conf/{node_id}/）

### node.conf

GNB 原生配置，关键字段：

```
nodeid {node_id}
ifname {tun_iface}
listen {port}
daemon off          ← 必须是 off（由服务管理层管控进程生命周期）
```

**写入规则：**
- `daemon on` 在写入时/启动前必须替换为 `daemon off`
- 通过 `config-bundle` API 获取的 `node.conf` 可能含 `daemon on`，客户端必须 patch

### route.conf

格式：`{node_id}|{network}|{netmask}`

```
# GNB 自身路由
3722810996882027|10.182.236.0|255.255.255.0

#----proxy begin----
# T_inject: 2026-05-10T12:00:00Z
3722810996882027|1.0.0.0|255.0.0.0
3722810996882027|2.0.0.0|255.0.0.0
... (约 714 条/peer)
#----proxy end----
```

**注入规则：**
- proxy pipe 段由 `proxy.enable()`/`preStart()` 写入，位于文件末尾
- 标记行 `#----proxy begin----` 和 `#----proxy end----` 是唯一定位依据
- 标记行之间包含 `# T_inject: {ISO8601时间戳}` 用于判断路由是否已被 GNB 加载
- GNB 无热加载：route.conf 修改后必须重启 GNB 才生效

### address.conf

GNB index server 地址列表：

```
i|0|52.221.22.241|9016
i|0|idx.mynet.club|9016
```

### security/{node_id}.private

- 长度：128 hex chars（64 字节，SHA512 of 32-byte seed）

### security/{node_id}.public

- 长度：64 hex chars（32 字节）

### ed25519/{peer_node_id}.public

- 长度：64 hex chars
- 每个对端节点一个文件

---

## 5. proxy_role.conf — 代理持久化配置

**路径：**
- mynet_lua: `/etc/mynet/conf/proxy_role.conf`
- mynet_client: `$MYNET_HOME/conf/proxy_role.conf`

**格式：** bash `KEY="VALUE"` 可 source

| Key | 取值 | 默认 |
|-----|------|------|
| `PROXY_ENABLED` | `0`/`1` | `0` |
| `PROXY_MODE` | `client`/`server`/`both` | `client` |
| `NODE_REGION` | `domestic`/`international`/`non_domestic` | `domestic` |
| `DNS_MODE` | `none`/`redirect`/`resolv`/`split` | `none` |
| `DNS_SERVER` | IPv4 | `""` |
| `DNS_DOMESTIC_SERVER` | 逗号分隔 IPv4 | `223.5.5.5,119.29.29.29` |
| `PROXY_PEERS` | 逗号分隔 node_id（字符串） | `""` |

---

## 6. proxy_policy_params.env — 运行时传参（Lua/Node → shell）

**路径：** `$MYNET_HOME/var/proxy_policy_params.env`  
**生命周期：** 每次 `proxy.start()` 生成，供 `route_policy.sh` source

```sh
NODE_REGION="domestic"
MATCH_MODE="normal"
DNS_MODE="split"
DNS_SERVER="8.8.8.8"
DNS_DOMESTIC_SERVER="223.5.5.5,119.29.29.29"
FOREIGN_DNS_SERVERS="8.8.8.8 8.8.4.4 1.1.1.1 1.0.0.1 9.9.9.9 208.67.222.222 208.67.220.220"
TUN_IFACE="gnb_tun_178"
PEER_VIP="10.182.236.1"
```

**MATCH_MODE 推导规则：**
- `NODE_REGION == "non_domestic"` → `MATCH_MODE="inverted"`
- 其他 → `MATCH_MODE="normal"`

---

## 7. proxy_state.json — 代理运行时状态

**路径：** `$MYNET_HOME/var/proxy_state.json`  
**生命周期：** `proxy.start()` 写入，`proxy.stop()` 删除

```json
{
  "start_ts": 1746234567,
  "mode": "client",
  "region": "domestic",
  "dns_mode": "split",
  "layers": {
    "route_inject": true,
    "policy_routing": true,
    "dns_intercept": true,
    "foreign_dns_routed": true
  }
}
```

---

## 8. gnb.pid — 进程状态文件

**路径：** `$MYNET_HOME/driver/gnb/conf/{node_id}/gnb.pid`

```json
{
  "pid": 12345,
  "node_id": "3722810996882027",
  "tun_iface": "gnb_tun_178",
  "start_time": "2026-05-10T12:00:00Z"
}
```

---

## 9. config-bundle API 响应

**接口：** `GET /api/v2/nodes/{node_id}/config-bundle`  
**Header：** `Authorization: Bearer {token}`, `X-Zone-ID: {zone_id}`

```json
{
  "node_id": "3722810996882027",
  "zone_id": "1396569674824134",
  "version": "2026-05-10T12:00:00+08:00",
  "files": {
    "node.conf": "nodeid 3722810996882027\nifname gnb_tun_178\nlisten 9016\ndaemon on\n...",
    "address.conf": "i|0|52.221.22.241|9016\ni|0|idx.mynet.club|9016\n",
    "route.conf": "3722810996882027|10.182.236.178|255.255.255.0\n..."
  },
  "keys": {
    "ed25519": {
      "3722810996882027": "e9ca457c...",
      "8283289760467957": "a49e4acf..."
    },
    "security": {
      "private": "48973280e7ae2c6b...",
      "public": "e9ca457c24338..."
    }
  }
}
```

**客户端写入映射：**

```
files["node.conf"]         → driver/gnb/conf/{node_id}/node.conf  [patch: daemon on→off]
files["address.conf"]      → driver/gnb/conf/{node_id}/address.conf
files["route.conf"]        → driver/gnb/conf/{node_id}/route.conf
keys.ed25519[{id}]         → driver/gnb/conf/{node_id}/ed25519/{id}.public
keys.security.private      → driver/gnb/conf/{node_id}/security/{node_id}.private
keys.security.public       → driver/gnb/conf/{node_id}/security/{node_id}.public
```

---

## 10. guest.json — 离线模式配置（mynet_lua 专属）

**路径：** `/etc/mynet/conf/guest.json`

```json
{
  "network_name": "MyNetwork",
  "subnet": "10.1.0",
  "listen_port": 9001,
  "local_node_id": 1001,
  "nodes": [
    { "node_id": 1001, "name": "Router（本机）", "virtual_ip": "10.1.0.1", "is_local": true },
    { "node_id": 1002, "name": "设备 2", "virtual_ip": "10.1.0.2", "is_local": false }
  ],
  "created_at": "2026-05-10T00:00:00Z"
}
```

**注意：** guest 模式不支持 proxy 分流功能。
