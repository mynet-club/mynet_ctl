# 升级 API 契约文档

> 四项目同步升级的接口契约定义
> 创建日期：2026-03-24

---

## 一、mynet_back — config-bundle API

### `GET /api/v2/nodes/{nodeId}/config-bundle`

**认证**：`Authorization: Bearer {token}` + `X-Zone-ID: {zoneId}`（sanctum + api.zone）

**用途**：一次性返回节点所需的全部 GNB 配置文件和密钥，替代旧版 5 次分散请求。

**请求参数**（Query）：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `format` | string | 否 | `rendered`（默认，纯文本配置）/ `raw`（JSON 结构化） |

**响应 200**：

```json
{
  "node_id": "3722810996882027",
  "zone_id": "1396569674824134",
  "version": "2026-03-24T10:00:00+08:00",
  "files": {
    "node.conf": "nodeid 3722810996882027\nifname gnb_tun_178\nlisten 9016\n...",
    "address.conf": "i|0|52.221.22.241|9016\ni|0|idx.mynet.club|9016\n...",
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

**响应 404**：节点不存在或不属于当前 Zone

```json
{ "message": "Node not found" }
```

**客户端写入映射**：

```
files["node.conf"]           → driver/gnb/conf/{nodeId}/node.conf
files["address.conf"]        → driver/gnb/conf/{nodeId}/address.conf
files["route.conf"]          → driver/gnb/conf/{nodeId}/route.conf
keys.ed25519[{id}]           → driver/gnb/conf/{nodeId}/ed25519/{id}.public
keys.security.private        → driver/gnb/conf/{nodeId}/security/{nodeId}.private
keys.security.public         → driver/gnb/conf/{nodeId}/security/{nodeId}.public
```

**实现要点**（mynet_back 内部）：

1. 复用 `GnbConfigGeneratorDomainService::generateNodeConfig()` → `node.conf`
2. 复用 `GnbConfigGeneratorDomainService::generateIndexServicesConfig()` → `address.conf`
3. 复用 `VpnConfigGeneratorFactoryDomainService::create('gnb')::generateRouteConfigText()` → `route.conf`
4. `NodeKey::findByNodeId()` → 自身密钥
5. `NodeKeyDomainService::getRouterKeys()` → 对等节点公钥

---

## 二、mynet_back — 心跳 action 类型响应

### 现有行为

```json
// POST /api/v2/monitor/heartbeat 响应
{
  "success": true,
  "node_id": "3722810996882027",
  "node_status": 2,
  "server_time": "2026-03-24T10:00:00+08:00",
  "commands": [
    {
      "id": 42,
      "command": "mynet.node.update",
      "params": { "changed_fields": ["node_port"] },
      "created_at": "2026-03-24T09:59:00+08:00"
    }
  ]
}
```

### 新增 action 映射（向后兼容）

保留 `commands` 字段名，但 `command` 值改用结构化的 action 类型：

| command 值（旧） | action 值（新） | 含义 |
|-----|------|------|
| `mynet.node.update` | `config.refresh` | 重新拉取 config-bundle |
| `mynet.service.restart` | `service.restart` | 重启完整服务 |
| — | `gnb.restart` | 仅重启 GNB 进程 |
| — | `route.refresh` | 重新应用路由表 |
| — | `firewall.refresh` | 重新应用防火墙规则 |
| — | `client.update` | 触发自更新 |
| — | `client.report` | 立刻上报一次状态 |

**兼容策略**：

1. 数据库 `monitor_rt_cmds.command` 字段改存 action 类型名
2. 心跳响应 `commands[].command` 字段同时返回，但新增 `commands[].action` 字段
3. 新客户端优先读 `action`，旧客户端继续读 `command`

```json
{
  "commands": [
    {
      "id": 42,
      "command": "config.refresh",
      "action": "config.refresh",
      "params": { "reason": "node_port_changed" },
      "created_at": "2026-03-24T09:59:00+08:00"
    }
  ]
}
```

---

## 三、mynet_ctl — apps.json v2.1

### `GET /d/apps.json`

**当前格式 (v1)**：

```json
{
  "apps": {
    "mynet": "https://ctl.mynet.club/d/mynet/manifest.json",
    "tui": "https://ctl.mynet.club/d/tui/manifest.json",
    "gnb": "https://ctl.mynet.club/d/gnb/manifest.json"
  }
}
```

**新格式 (v2.1)**：

```json
{
  "schema_version": "2.1",
  "generated_at": "2026-03-24T10:00:00+08:00",
  "products": {
    "client": {
      "name": "MyNet Client",
      "description": "MyNet 桌面/服务器客户端",
      "latest": "2.0.0",
      "manifest_url": "/d/client/manifest.json",
      "plugins": {
        "heartbeat": {
          "name": "@mynet/heartbeat",
          "description": "心跳守护插件",
          "latest": "1.0.0",
          "required": false,
          "type": "nodejs"
        },
        "updater": {
          "name": "@mynet/updater",
          "description": "自动更新插件",
          "latest": "1.0.0",
          "required": false,
          "type": "nodejs"
        },
        "proxy": {
          "name": "@mynet/proxy",
          "description": "代理路由插件",
          "latest": "1.0.0",
          "required": false,
          "type": "nodejs"
        }
      }
    },
    "mynet": {
      "name": "MyNet TUI",
      "description": "MyNet Go 终端客户端（旧版，即将废弃）",
      "latest": "1.4.0",
      "manifest_url": "/d/mynet/manifest.json",
      "deprecated": true,
      "successor": "client"
    },
    "gnb": {
      "name": "GNB",
      "description": "GNB P2P VPN 核心",
      "latest": "1.6.0.a",
      "manifest_url": "/d/gnb/manifest.json"
    },
    "utc": {
      "name": "UTC",
      "description": "UDP→TCP 隧道转换器",
      "latest": "1.0.0",
      "manifest_url": "/d/utc/manifest.json"
    },
    "lua": {
      "name": "MyNet OpenWrt",
      "description": "OpenWrt LuCI 管理界面",
      "latest": "1.0.0",
      "manifest_url": "/d/lua/manifest.json"
    },
    "proxy": {
      "name": "MyNet Proxy",
      "description": "透明代理插件",
      "latest": "1.0.0",
      "manifest_url": "/d/proxy/manifest.json"
    }
  },
  "aliases": {
    "tui": "mynet"
  }
}
```

**兼容策略**：

- v2.1 同时保留 `apps` 字段（扁平格式），让旧客户端继续工作
- 新客户端优先读 `products`，忽略 `apps`
- `aliases` 记录旧名→新名映射

---

## 四、mynet_ctl — 插件分发 API

### `GET /d/{product}/plugins.json`

返回某产品可用的插件清单：

```json
{
  "schema_version": "1.0",
  "product": "client",
  "generated_at": "2026-03-24T10:00:00+08:00",
  "plugins": [
    {
      "name": "heartbeat",
      "full_name": "@mynet/heartbeat",
      "description": "心跳守护插件",
      "version": "1.0.0",
      "type": "nodejs",
      "required": false,
      "download_url": "/api/v2/plugins/{id}/download/mynet-heartbeat-1.0.0.tgz",
      "sha256": "abc123...",
      "size": 12345
    }
  ]
}
```

### 插件上传/下载

复用现有 `plugins` 表和 PluginController。需新增 `type` 字段区分：

| type | 说明 |
|------|------|
| `binary` | 平台相关二进制（旧模式，按 platform/arch 分发） |
| `nodejs` | Node.js 模块 tgz（平台无关） |
| `lua` | Lua 模块/脚本（平台无关） |

---

## 五、URL 端点汇总

### mynet_back 新增

| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/api/v2/nodes/{id}/config-bundle` | sanctum + zone | 一次性配置包 |

### mynet_ctl 新增/变更

| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | `/d/apps.json` | 无 | **变更**: v2.1 格式（含 products + plugins） |
| GET | `/d/{product}/plugins.json` | 无 | **新增**: 插件清单 |
| GET | `/d/{product}/manifest.json` | 无 | 不变 |

### 无变更端点

| 端点 | 说明 |
|------|------|
| `POST /api/v2/monitor/heartbeat` | 响应格式微调（加 action 字段），请求不变 |
| `GET /api/v2/releases/{id}/download/{file}` | 不变 |
| `GET /api/v2/plugins/{id}/download/{file}` | 不变 |
