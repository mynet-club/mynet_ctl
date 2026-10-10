> ⚠️ **状态：已被取代（历史）**（2026-10-01 标注）。
> 本方案的「tui → client」路线**已整体谢幕**：`mynet_client` / `mynet_tui` / `mynet_lua` 三仓均已退役，
> 能力由 **`mynet_agent`（mynetd + mynet）** 承接，壳层见 `mynet_desktop` / `mynet_openwrt`。
> 现行规划见 [`integration-plan-v2.md`](integration-plan-v2.md) 与 [`dev-plan.md`](dev-plan.md)。
> 保留本文只为旧线决策考古，**不要再照它执行**。

# MyNet 客户端迁移与优化方案

> tui (Go) → client (Node.js) + lua (LuCI) 全面升级规划
> 生成日期: 2026-03-23

---

## 一、总体决策

| 决策 | 内容 |
|------|------|
| **废弃 mynet_tui** | Go 二进制客户端停止维护，不再发布新版本 |
| **非 OpenWrt 设备** | `mynet_client`（Node.js）— CLI + WebUI |
| **OpenWrt 设备** | `mynet_lua`（Lua + LuCI App）— 纯 WebUI，不提供 CLI |
| **插件化架构** | proxy / heartbeat / updater 作为独立插件 |
| **同步升级** | ctl / back / client / lua 四个项目同步开发、同步发版 |

### 产品对照表

| 旧 (tui) | 新 (client/lua) | 说明 |
|-----------|-----------------|------|
| `mynet` 单体 Go 二进制 | `mynet` Node.js CLI + WebUI | 登录/Zone/节点/GNB 启停 |
| `mynetd` Go 守护进程二进制 | `@mynet/heartbeat` 插件 | 心跳上报 + 远程命令 |
| `mynet-updater` Go 更新器二进制 | `@mynet/updater` 插件 | 自更新 + 回滚 |
| `mynet_proxy` Go 代理二进制 | `@mynet/proxy` 插件 | 代理路由 + DNS 分流 |
| 三个独立 Go 编译二进制 | Node.js 模块，无需交叉编译 | 架构大幅简化 |

---

## 二、核心 vs 插件 — 职责划分

### 2.1 核心（Core）— 必装

核心做**完整的网络服务管理**：登录、配置同步、GNB + 路由 + 防火墙三位一体的服务控制。

> **关键认知**：mynet 不只是点对点 VPN，还支持 net-to-net（站点互联），涉及路由表操作和防火墙规则。
> service 管理 = GNB 进程 + 路由（ip route）+ 防火墙（iptables/nftables/pf/UCI fw），三者联动。

| 功能 | 说明 |
|------|------|
| 用户认证 | login / logout / token refresh |
| Zone 管理 | 列出、选择、切换 Zone |
| 节点管理 | 列出、选择、查看详情 |
| 配置同步 | 从 back 拉取 config-bundle（一次性获取所有配置文件 + 密钥） |
| **服务生命周期** | **GNB 进程 + 路由表 + 防火墙规则**的安装/启动/停止/重启/状态 |
| 配置校验 | **所有配置文件写入后先 test/check 再 apply** |
| WebUI 服务 | Express + Vue 3 本地管理界面 |
| CLI 命令 | `mynet login / zone / nodes / status / service / web` |

#### 服务管理三层架构

```
mynet service start
  ├── ① GNB 进程启动        gnb -c driver/gnb/conf/{nodeId}
  ├── ② 路由表应用          读取 route.conf → ip route add / route add
  └── ③ 防火墙规则应用      iptables/nftables/pf/UCI 规则

mynet service stop
  ├── ① 防火墙规则清除
  ├── ② 路由表清除
  └── ③ GNB 进程停止
```

**平台差异处理**（Node.js 内部封装，用户无感）：

| | Linux | macOS | OpenWrt |
|--|-------|-------|----------|
| 防火墙 | nftables 优先，降级 iptables | pf anchors | UCI fw3(iptables) / fw4(nftables) |
| 路由 | iproute2 优先，降级 net-tools | route add -net + netmask 转换 | ip route（busybox） |
| IP 转发 | sysctl net.ipv4.ip_forward=1 | sysctl net.inet.ip.forwarding=1 | sysctl（通常已开启） |
| NAT | MASQUERADE on VPN interface | pf nat anchor | UCI masq=1 on mynet zone |
| 服务注册 | systemd | launchd | init.d / procd |
| DNAT | iptables/nft DNAT 规则 | pf rdr 规则 | UCI redirect |

> **注意**：Windows 和 macOS 通常不作为路由网关。net-to-net（站点互联）场景仅在 Linux 和 OpenWrt 上需要完整的路由+防火墙管理。

### 2.2 插件（Plugins）— 按需安装

```
$MYNET_HOME/plugins/
├── heartbeat/           # @mynet/heartbeat
│   ├── package.json
│   ├── index.js         # 插件入口（exports: { name, version, init, start, stop, status }）
│   ├── daemon.js        # 心跳循环主逻辑
│   └── collector.js     # 系统指标采集（CPU/内存/磁盘/网络）
├── updater/             # @mynet/updater
│   ├── package.json
│   ├── index.js
│   ├── checker.js       # 版本检查（比对 CTL manifest）
│   └── installer.js     # 下载 + 解压 + 替换 + 重启
└── proxy/               # @mynet/proxy 
    ├── package.json
    ├── index.js
    ├── manager.js        # 代理进程/路由管理
    └── rules/            # 路由规则（chinaip.txt 等）
```

#### @mynet/heartbeat（替代 mynetd）

| 旧 (Go mynetd) | 新 (Node.js heartbeat 插件) |
|-----|------|
| 独立 Go 二进制，需交叉编译 | Node.js 模块，跨平台零编译 |
| 单独的 systemd service | 可集成到 WebUI 进程，或独立 service |
| gopsutil 采集系统指标 | `os` 模块 + `/proc` 读取（Linux）/ `systeminformation` 库 |
| Ed25519 签名（Go crypto） | `crypto.sign()` / `tweetnacl` 库 |
| command.{platform}.json 执行任意命令 | **优化：预定义动作类型**，不再执行任意 shell |

**运行方式**：

```bash
# 方式 A：集成到 WebUI 进程（推荐，简单）
mynet web --with-heartbeat     # WebUI 启动时同时启动心跳

# 方式 B：独立 daemon（systemd/launchd 管理）
mynet heartbeat start          # 注册并启动 heartbeat service
mynet heartbeat status         # 查看心跳状态
```

**远程命令安全优化**（重要）：

```
旧设计（不安全）：
  服务端 → 返回 {"command": "scripts/mynetd", "args": ["start"]}
  客户端 → 直接 exec（任意 shell 命令）

新设计（安全）：
  服务端 → 返回 {"action": "gnb.restart", "params": {"node_id": "12345"}}
  客户端 → 查预定义 action 映射表，只执行已知操作
```

预定义 action 清单：

| action | 客户端执行 | 说明 |
|--------|-----------|------|
| `gnb.start` | 启动 GNB 进程 | |
| `gnb.stop` | 停止 GNB 进程 | |
| `gnb.restart` | 重启 GNB 进程 | |
| `service.restart` | 重启完整服务（GNB + 路由 + 防火墙） | |
| `config.refresh` | 重新拉取 config-bundle + check + apply | 含 node.conf + route.conf + keys |
| `route.refresh` | 重新应用路由表 | net2net 场景路由变更 |
| `firewall.refresh` | 重新应用防火墙规则 | |
| `client.update` | 触发自更新检查 | |
| `client.report` | 立即上报一次状态 | |

不再支持 `"command": "rm -rf /"` 这类任意 shell 执行。

#### @mynet/updater（替代 mynet-updater）

| 旧 (Go updater) | 新 (Node.js updater 插件) |
|-----|------|
| 独立二进制，替换自身需要先退出 | Node.js 脚本可直接替换，无需独立进程 |
| 单文件 / 全目录两种模式 | 统一模式：下载 tgz → 解压到临时目录 → 原子替换 → 重启 service |
| 失败回滚靠 .backup 文件 | 保留上一版本目录 `$MYNET_HOME/.backup/` |

**更新流程**：

```
mynet update check             # 检查 CTL manifest，比对本地版本
mynet update install           # 下载 + 安装最新版
mynet update rollback          # 回滚到上一版本

流程：
1. GET ctl.mynet.club/api/v2/client/manifest → 获取最新版本号 + 下载 URL
2. 比对 package.json 中的 version
3. 下载 tgz → 写入 $MYNET_HOME/.update/client_v{new}.tgz
4. 备份当前版本 → $MYNET_HOME/.backup/
5. 解压到当前目录（conf/ 不覆盖）
6. 重启 service（systemctl restart mynet 或 launchctl）
7. 若启动失败 → 自动恢复 .backup/
```

**Node.js 优势**：不需要 `mynet-updater` 这个独立二进制。Go 需要是因为不能替换正在运行的 exe；Node.js 的 `.js` 文件可以被替换，下次 `require` 自动加载新版本。但 `node_modules/` 变更仍需重启进程。

#### @mynet/proxy（替代 mynet_proxy）

| 旧 (Go proxy) | 新 (Node.js proxy 插件) |
|-----|------|
| 独立 Go 二进制 | Node.js 模块 |
| SOCKS5/HTTP 代理进程 | 可用 Node.js `net` / `http` 模块实现 |
| chinaip.txt 路由规则 | 同样的规则文件，Node.js 解析 |

**功能**：
- 管理代理路由规则（proxy_route.conf, chinaip.txt, interip.txt）
- 配置代理节点（proxy_peers.conf）
- 启动本地代理服务端口
- DNS 分流（国内 / 国外 DNS）

```bash
mynet proxy start              # 启动代理服务
mynet proxy stop               # 停止代理服务
mynet proxy status             # 查看代理状态
mynet proxy check              # 校验代理配置文件
mynet proxy update-rules       # 更新 IP 路由规则
```

**⚠️ proxy 会修改 GNB 配置文件（重要联动）**：

proxy 启用时需要 hack GNB 的配置文件来注入代理路由，这意味着：

```
mynet proxy start
  ├── ① 校验 proxy 配置文件（proxy_route.conf, proxy_peers.conf）
  ├── ② 修改 GNB route.conf（注入代理路由条目）
  ├── ③ 修改 GNB address.conf（添加代理节点地址）
  ├── ④ 重启 GNB 进程（使配置生效）
  ├── ⑤ 追加系统路由规则（代理 IP 段走 GNB 隧道）
  └── ⑥ 追加防火墙规则（DNS 劫持等）

mynet proxy stop
  ├── ① 还原 GNB route.conf（移除注入的代理路由）
  ├── ② 还原 GNB address.conf
  ├── ③ 重启 GNB 进程
  ├── ④ 清除代理路由规则
  └── ⑤ 清除代理防火墙规则
```

**设计原则**：
- proxy 修改 GNB 配置前先备份原文件（`route.conf.bak`）
- proxy stop 时从备份恢复
- proxy 通过核心的 `ServiceManager` 调用 GNB 重启，不直接操作 GNB 进程
- 修改前后都要执行 `check`（见 §2.5）

### 2.5 配置校验 — 所有服务必须有 test/check

**原则**：任何配置写入后、apply 前，必须先校验。所有服务和插件统一支持 `check` 子命令。

```bash
# 核心配置校验
mynet config check                # 校验 conf/config.json 格式 + API 可达性
mynet service check               # 校验 GNB 配置完整性（node.conf + keys + route.conf）
mynet service check --dry-run     # 仅校验，不实际启动

# 插件配置校验
mynet heartbeat check             # 校验 credential + back API 可达性
mynet proxy check                 # 校验 proxy 路由规则 + peer 配置
mynet update check                # 检查 CTL 可达性 + manifest 版本号
```

**校验内容**：

| 服务/插件 | check 做什么 |
|-----------|-------------|
| `config` | config.json 格式合法、API URL 格式正确、可选：ping API 健康端点 |
| `service` | node.conf 存在且语法正确、address.conf 至少一个索引节点、ed25519/ 有自身公钥、security/ 有私钥、route.conf 格式合法（每行三字段）、GNB binary 存在且可执行 |
| `heartbeat` | credential.json 存在、token 非空、back API 可达 |
| `proxy` | proxy_route.conf 格式合法、proxy_peers.conf 有至少一个 peer、chinaip.txt 非空 |

**配置同步时自动校验**：

```javascript
async function syncNodeConfig(nodeId) {
  const bundle = await fetchConfigBundle(nodeId);
  writeConfigFiles(bundle);           // 写入文件
  const result = checkGnbConfig();    // 自动校验
  if (!result.ok) {
    throw new Error(`Config check failed: ${result.errors.join(', ')}`);
  }
}
```

**Lua (OpenWrt) 等价物**：

LuCI 页面写入配置后，在 "启动服务" 前自动执行检查，不合格不允许启动，页面显示红色错误提示。

---

### 2.3 插件加载机制

```javascript
// src/plugins/loader.js
class PluginLoader {
  constructor(pluginsDir) {
    this.pluginsDir = pluginsDir;   // $MYNET_HOME/plugins/
    this.plugins = new Map();
  }

  // 扫描 plugins/ 下所有子目录，加载 package.json + index.js
  discover() {
    for (const dir of fs.readdirSync(this.pluginsDir)) {
      const pkgPath = path.join(this.pluginsDir, dir, 'package.json');
      if (!fs.existsSync(pkgPath)) continue;
      const pkg = JSON.parse(fs.readFileSync(pkgPath));
      const plugin = require(path.join(this.pluginsDir, dir));
      this.plugins.set(pkg.name, { ...plugin, version: pkg.version });
    }
  }

  // 插件标准接口
  // plugin.init(core)     — 注入核心 API（config, api, gnb）
  // plugin.start()        — 启动插件
  // plugin.stop()         — 停止插件
  // plugin.status()       — 返回插件状态
  // plugin.registerCLI(program)   — 注册 CLI 子命令
  // plugin.registerRoutes(app)    — 注册 WebUI API 路由
}
```

### 2.4 OpenWrt (mynet_lua) 的插件等价物

OpenWrt 资源受限，不使用 Node.js 插件机制：

| 功能 | 实现方式 |
|------|---------|
| 心跳 | Shell 脚本 + cron（`/etc/cron.d/mynet_heartbeat`），curl 上报 |
| 更新 | `opkg upgrade luci-app-mynet`，或手动下载 .ipk |
| 代理 | 不支持（OpenWrt 路由器通常不跑代理） || **路由** | OpenWrt 原生：`ip route` + UCI 路由表配置 |
| **防火墙** | OpenWrt 原生：UCI firewall zone/forwarding/masq + fw3/fw4 |
| **配置校验** | LuCI 页面启动前自动检查，不合格显示红色提示 |
---

## 三、配置同步优化 — config-bundle API

### 3.1 当前问题

旧 tui 的配置同步需要**多次 API 调用**：

```
① GET /nodes/{id}/config?render_conf=1         → node.conf（纯文本）
② GET /nodes/{id}/route-config?render_conf=1   → route.conf（纯文本）
③ GET /services/index                          → address.conf（纯文本）
④ POST /monitor/router-keys {node_ids: [...]}  → 对等节点公钥（JSON）
⑤ 密钥本地已有（security/ 目录私钥在初始注册时生成）
```

问题：
- 5 次网络请求，任何一个失败都会导致配置不完整
- 公钥同步绑定在心跳签名认证上（router-keys 接口），概念耦合
- 客户端需要自己拼装目录结构

### 3.2 新方案：back 提供 config-bundle API

**mynet_api 新增端点**：

```
GET /api/v2/nodes/{nodeId}/config-bundle
Authorization: Bearer {token}
X-Zone-ID: {zoneId}
```

**响应**：

```json
{
  "node_id": "3722810996882027",
  "zone_id": 123,
  "version": "2026-03-23T10:00:00Z",
  "files": {
    "node.conf": "nodeid 3722810996882027\nifname gnb_tun_178\nlisten 9016\n...",
    "address.conf": "i|0|52.221.22.241|9016\ni|0|idx.mynet.club|9016\n...",
    "route.conf": "3722810996882027|10.182.236.178|255.255.255.0\n..."
  },
  "keys": {
    "ed25519": {
      "3722810996882027": "e9ca457c243383bc68a6ab2635867986ec386e2f0faff5bc3394c78fc7f64bc3",
      "8283289760467957": "a49e4acf3d8dceaa0cb8fc49b494f9e1f3b6d39caf3c920a5194a819d83ce793"
    },
    "security": {
      "private": "48973280e7ae2c6bfbb6103387f0566361f91a3528c57a3ad240334a3a6c9e60...",
      "public": "e9ca457c243383bc68a6ab2635867986ec386e2f0faff5bc3394c78fc7f64bc3"
    }
  }
}
```

**客户端收到后**一次性写入：

```
driver/gnb/conf/{nodeId}/
├── node.conf              ← files.node_conf
├── address.conf           ← files.address_conf
├── route.conf             ← files.route_conf
├── ed25519/
│   ├── 3722810996882027.public   ← keys.ed25519.{id}（所有对等节点 + 自身）
│   └── 8283289760467957.public
└── security/
    ├── 3722810996882027.private  ← keys.security.private
    └── 3722810996882027.public   ← keys.security.public
```

**优势**：
- 1 次请求替代 4-5 次
- back 负责完整性，客户端只管写文件
- 配置版本号可用于增量更新（If-Modified-Since）

### 3.3 CTL 的角色

CTL 不直接提供 config-bundle（配置属于 mynet_api 职责），但 CTL 的 `/setup/config-pack` 页面可以代理调用 back 的 config-bundle API，面向浏览器用户提供 ZIP 下载。

```
客户端 → 直接调用 mynet_api /api/v2/nodes/{id}/config-bundle
         （正常使用流程）

浏览器 → CTL /setup/config-pack/{nodeId}
         → CTL 代理调用 mynet_api config-bundle
         → 打包成 ZIP 返回给浏览器
         （手动下载 / 调试场景）
```

---

## 四、目录结构优化

### 4.1 mynet_client 安装后目录

```
$MYNET_HOME/                            # /opt/mynet 或 ~/.local/mynet
├── bin/
│   └── mynet -> ../src/cli/index.js    # symlink（或 wrapper script）
├── package.json                        # 版本号、依赖声明
├── node_modules/                       # npm 依赖
├── conf/
│   └── config.json                     # 应用配置（API URL、WebUI 端口）
├── src/                                # 核心源码
│   ├── cli/
│   ├── api/
│   ├── config/
│   ├── service/
│   │   ├── manager.js              # ServiceManager（GNB + 路由 + 防火墙联动）
│   │   ├── checker.js              # 配置校验器
│   │   └── platform/               # 平台特定实现
│   │       ├── linux.js            # nftables/iptables + iproute2
│   │       ├── darwin.js           # pf + route
│   │       └── windows.js          # 仅 GNB 进程管理（无路由/防火墙）
│   ├── server/
│   └── plugins/
│       └── loader.js
├── web/dist/                           # Vue 3 前端构建产物
├── plugins/                            # 插件目录
│   ├── heartbeat/
│   ├── updater/
│   └── proxy/                          # 按需安装
├── driver/
│   └── gnb/                            # GNB 驱动
│       ├── bin/gnb
│       └── conf/{nodeId}/
├── data/                               # 运行时数据（替代旧 ~/.mynet/ + var/）
│   ├── config.json                     # 用户状态（auth token、当前 zone/node）
│   ├── credential.json                 # daemon 凭证
│   └── logs/
│       ├── mynet.log
│       └── heartbeat.log
├── install.sh
└── uninstall.sh
```

**关键变化**：
- 取消 `~/.mynet/config.json`，用户状态统一到 `$MYNET_HOME/data/config.json`
- 取消 `conf/mynet.conf`（Shell KEY=VALUE），Node.js 直接读 JSON
- 取消 `conf/command.{platform}.json`，改为预定义 action
- `scripts/services/` → 平台特定的路由/防火墙操作封装到 `src/service/platform/`
- 新增 `plugins/` 目录

### 4.2 配置文件合并

旧设计有两个配置位置（混乱）：

```
旧：
  conf/config.json         — 应用默认配置（API URL 等）
  ~/.mynet/config.json     — 用户运行时状态（token、zone、node）
  conf/credential.json     — daemon 凭证（又一个位置）
  conf/mynet.conf          — Shell 变量（第四个位置）
```

新设计只有两类文件、路径统一在 `$MYNET_HOME` 下：

```
新：
  conf/config.json         — 应用配置（只读模板，随安装包分发）
  data/config.json         — 用户状态（运行时写入：token、zone、node、UI 偏好）
  data/credential.json     — daemon 凭证（心跳插件读取）
```

### 4.3 mynet_lua 目录（OpenWrt）

保持现有结构不变，已经合理：

```
/etc/mynet/
├── conf/
│   ├── config.json          # API URL
│   ├── credential.json      # auth token
│   └── zone.json            # 当前 zone
└── driver/
    └── gnb/
        ├── bin/gnb
        └── conf/{nodeId}/
```

---

## 五、各项目具体升级工作

### 5.1 mynet_api — 新增 config-bundle API

| 优先级 | 工作项 | 说明 |
|--------|--------|------|
| **P0** | `GET /api/v2/nodes/{id}/config-bundle` | 一次返回 node.conf + address.conf + route.conf + 全部密钥 |
| P1 | config-bundle 支持 `If-Modified-Since` | 增量更新，304 无需重传 |
| P1 | 心跳响应改用 action 类型 | `{"action": "gnb.restart"}` 替代 `{"command": "..."}` |
| P2 | 废弃 `/monitor/router-keys` 端点 | 公钥已包含在 config-bundle 中 |

### 5.2 mynet_ctl — 插件分发支持

| 优先级 | 工作项 | 说明 |
|--------|--------|------|
| P1 | 插件清单 API | `GET /api/v2/{product}/plugins.json` — 列出可用插件 |
| P1 | 插件 tgz 上传/下载 | 复用现有 plugins 表，新增 `type` 字段（`binary` / `nodejs` / `lua`）|
| P1 | `ConfigPackService` 调用 back config-bundle | 代理转发，打包成 ZIP |
| P2 | install.sh 更新 | 支持安装核心 + 可选插件（`--with-heartbeat --with-proxy`）|
| P2 | apps.json 增加 plugins 字段 | 让客户端知道有哪些可用插件 |

**apps.json 新格式**：

```json
{
  "schema_version": "2.1",
  "products": {
    "client": {
      "name": "MyNet Client",
      "description": "MyNet 桌面/服务器客户端",
      "latest": "2.0.0",
      "manifest_url": "/api/v2/client/manifest",
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
    "gnb": { "..." : "..." },
    "utc": { "..." : "..." }
  }
}
```

### 5.3 mynet_client — 核心重构

| 优先级 | 工作项 | 说明 |
|--------|--------|------|
| **P0** | 配置同步改用 config-bundle | 一次请求替代多次，直接写入 driver/gnb/conf/{nodeId}/ |
| **P0** | 插件加载器 `src/plugins/loader.js` | 扫描 plugins/ 目录，加载插件 |
| P1 | heartbeat 插件 | 移植 mynetd 心跳逻辑到 Node.js |
| P1 | updater 插件 | 版本检查 + 下载 + 替换 + 重启 |
| P1 | CLI 注册插件子命令 | `mynet heartbeat status` / `mynet update check` |
| P1 | WebUI 插件页面 | 插件管理界面（安装/启用/禁用/状态） |
| P2 | proxy 插件 | 代理路由功能 |
| P2 | `data/` 目录迁移 | 从 `~/.config/mynet/` 迁移到 `$MYNET_HOME/data/` |

**config-bundle 客户端实现**：

```javascript
// src/api/nodes.js
async function fetchConfigBundle(nodeId) {
  const client = getClient();
  const { data } = await client.get(`/nodes/${nodeId}/config-bundle`);
  return data;
}

// src/service/config-sync.js
async function syncNodeConfig(nodeId) {
  const bundle = await fetchConfigBundle(nodeId);
  const confDir = path.join(GNB_ROOT, 'conf', String(bundle.node_id));

  // 写入配置文件
  fs.mkdirSync(confDir, { recursive: true });
  for (const [filename, content] of Object.entries(bundle.files)) {
    fs.writeFileSync(path.join(confDir, filename), content);
  }

  // 写入 ed25519 公钥
  const ed25519Dir = path.join(confDir, 'ed25519');
  fs.mkdirSync(ed25519Dir, { recursive: true });
  for (const [id, pubkey] of Object.entries(bundle.keys.ed25519)) {
    fs.writeFileSync(path.join(ed25519Dir, `${id}.public`), pubkey);
  }

  // 写入 security 密钥
  const secDir = path.join(confDir, 'security');
  fs.mkdirSync(secDir, { recursive: true });
  fs.writeFileSync(path.join(secDir, `${bundle.node_id}.private`), bundle.keys.security.private);
  fs.writeFileSync(path.join(secDir, `${bundle.node_id}.public`), bundle.keys.security.public);
}
```

### 5.4 mynet_lua — 同步优化

| 优先级 | 工作项 | 说明 |
|--------|--------|------|
| **P0** | 配置同步改用 config-bundle | `node.lua` 一次 curl 获取全部配置 |
| P1 | 心跳脚本 | Shell + cron 实现，curl 上报 |
| P1 | 更新支持 | LuCI 页面显示新版本提示，`opkg upgrade` 或手动 .ipk |
| P2 | 废弃 `system.lua` 中多余的单独 config 拉取方法 | 统一用 config-bundle |

**Lua config-bundle 实现**：

```lua
-- luasrc/model/mynet/node.lua
function M.sync_config_bundle(node_id)
  local api = require("luci.model.mynet.api")
  local util = require("luci.model.mynet.util")

  local bundle, err = api.get_json_authed("/nodes/" .. node_id .. "/config-bundle")
  if not bundle then return nil, err end

  local conf_dir = util.GNB_CONF_ROOT .. "/" .. bundle.node_id
  util.ensure_dir(conf_dir)
  util.ensure_dir(conf_dir .. "/ed25519")
  util.ensure_dir(conf_dir .. "/security")

  -- 写入配置文件
  for filename, content in pairs(bundle.files or {}) do
    util.write_file(conf_dir .. "/" .. filename, content)
  end

  -- 写入公钥
  for id, pubkey in pairs((bundle.keys or {}).ed25519 or {}) do
    util.write_file(conf_dir .. "/ed25519/" .. id .. ".public", pubkey)
  end

  -- 写入私钥
  local sec = (bundle.keys or {}).security or {}
  if sec.private then
    util.write_file(conf_dir .. "/security/" .. bundle.node_id .. ".private", sec.private)
  end
  if sec.public then
    util.write_file(conf_dir .. "/security/" .. bundle.node_id .. ".public", sec.public)
  end

  return bundle
end
```

---

## 六、问题清单与优化

### 6.1 安全问题

| # | 问题 | 旧设计 | 优化方案 |
|---|------|--------|---------|
| S1 | 远程命令任意执行 | command.{platform}.json 映射 shell 命令，服务端可下发任意指令 | **改为预定义 action 类型**（见 §2.2），客户端只执行已知操作 |
| S2 | credential.json 100 年 token | `expires_at: "2125-..."` 永不过期 | token 有效期改为 30 天，依赖 refresh_token 续期 |
| S3 | Ed25519 私钥明文存储 | security/{id}.private 文件权限不明确 | 确保 chmod 600，Node.js 写入时设置 `{ mode: 0o600 }` |

### 6.2 架构问题

| # | 问题 | 旧设计 | 优化方案 |
|---|------|--------|---------|
| A1 | 配置同步需 5 次请求 | 分别拉 node.conf / route.conf / address.conf / keys | **config-bundle API 一次返回**（见 §3） |
| A2 | mynet.conf Shell 变量多余 | Go 客户端生成 Shell KEY=VALUE，供 bash 脚本 source | **取消**，Node.js 直接读 JSON + GNB 配置文件 |
| A3 | 两个配置文件位置 | conf/config.json + ~/.mynet/config.json | **统一到 $MYNET_HOME**（conf/ + data/） |
| A4 | command.{platform}.json 机制 | 不同平台不同命令映射，运维复杂 | **预定义 action**，平台差异在客户端内部处理 |
| A5 | GNB 启动方式不统一 | mynet status --start 或 scripts/service-manager.sh 或 mynetd | **统一入口**：`mynet service start`（CLI）/ WebUI 按钮 |
| A6 | refresh-routes 和配置同步分离 | 公钥需要单独通过 heartbeat 签名请求获取 | **公钥包含在 config-bundle 中**，不再需要单独 API |
| A7 | service = 只管 GNB 进程 | 路由和防火墙靠 shell 脚本单独管理 | **service = GNB + 路由 + 防火墙**三层联动，统一管理 |
| A8 | 配置写入无校验 | 写完直接 apply，出错才发现 | **所有配置写入后先 check 后 apply**（见 §2.5） |
| A9 | proxy 修改 GNB 配置无协调 | proxy 直接改 route.conf，可能和其他进程冲突 | **proxy 通过核心 ServiceManager 协调**，改前备份改后校验 |

### 6.3 产品问题

| # | 问题 | 旧设计 | 优化方案 |
|---|------|--------|---------|
| P1 | tui 产品名混乱 | 内部 `tui`，外部 `mynet`，别名映射 | client 产品统一用 `client`，CTL 仅保留 tui→mynet 别名兼容旧版 |
| P2 | 三个 Go 二进制维护成本高 | mynet + mynetd + mynet-updater 各自交叉编译 | Node.js 无需编译，core + 插件都是 JS 模块 |
| P3 | OpenWrt 无 CLI | mynet_lua 只有 LuCI web 界面 | **OK，用户确认不需要 OpenWrt CLI**。路由器通过 LuCI 管理即可 |
| P4 | install.sh 重复 | mynet_tui 和 mynet_client 各自一份 install.sh | 统一维护一份，CTL scripts 表分发 |

---

## 七、CLI 命令对照表

### mynet_client (Node.js)

```
核心命令：
  mynet login                    登录
  mynet logout                   登出
  mynet zone                     Zone 选择
  mynet nodes list               节点列表
  mynet nodes use                选择当前节点
  mynet nodes show <id>          节点详情
  mynet config show              查看配置
  mynet config check             校验配置 + API 可达性
  mynet config set-server <url>  修改 API 地址
  mynet service install          安装 GNB 二进制
  mynet service start            启动完整服务（GNB + 路由 + 防火墙）
  mynet service stop             停止完整服务（防火墙 + 路由 + GNB）
  mynet service restart          重启完整服务
  mynet service status           查看服务状态（进程 + 路由 + 防火墙）
  mynet service check            校验 GNB 配置完整性
  mynet refresh-config           同步配置（config-bundle + check）
  mynet web                      启动 WebUI
  mynet status                   综合状态

插件命令（heartbeat 插件安装后可用）：
  mynet heartbeat start          启动心跳
  mynet heartbeat stop           停止心跳
  mynet heartbeat status         查看心跳状态

插件命令（updater 插件安装后可用）：
  mynet update check             检查更新
  mynet update install           安装更新
  mynet update rollback          回滚

插件命令（proxy 插件安装后可用）：
  mynet proxy start              启动代理
  mynet proxy stop               停止代理
  mynet proxy status             代理状态
  mynet proxy update-rules       更新路由规则

管理命令：
  mynet plugin list              列出已安装插件
  mynet plugin install <name>    从 CTL 安装插件
  mynet plugin remove <name>     卸载插件
```

### mynet_lua (LuCI) — 无 CLI

所有操作通过 LuCI Web 界面完成：

```
http://router-ip/cgi-bin/luci/admin/services/mynet/
├── /            仪表盘（状态总览）
├── /login       登录
├── /node        节点选择 + 配置同步
├── /status      系统状态 + 依赖检查
├── /service     GNB 服务控制（启动/停止/重启）
├── /wizard      初始配置向导
├── /settings    设置（API 地址、GNB 路径）
└── /monitor     GNB 进程监控
```

---

## 八、升级策略 — 四项目同步升级

> **关键决策**：ctl / back / client / lua 不分阶段，同步开发、同步发版。
>
> CTL 作为总控中心，既是分发平台也是协调枢纽，同步推进可以避免接口不一致和集成延迟。

```
┌──────────────────────────────────────────────────────────────────┐
│                    同步升级（一个迭代周期内）                       │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │  mynet_api  │  │  mynet_ctl   │  │  mynet_gnb   │            │
│  │ config-bundle│  │ 插件分发      │  │  mynet_utc   │            │
│  │ action 心跳  │  │ apps.json 2.1│  │ CI 对接 CTL  │            │
│  └──────┬───────┘  └──────┬───────┘  └──────────────┘            │
│         │                 │                                      │
│         └────────┬────────┘                                      │
│                  │ 统一 API 契约                                  │
│         ┌────────┴────────┐                                      │
│  ┌──────┴───────┐  ┌──────┴───────┐                              │
│  │ mynet_client │  │  mynet_lua   │                              │
│  │ 服务管理+插件 │  │ config-bundle│                              │
│  └──────────────┘  └──────────────┘                              │
└──────────────────────────────────────────────────────────────────┘
                              ↓ 发版后
┌──────────────┐
│  mynet_tui   │ 发布最终版：启动提示迁移到 client
│  (deprecated)│
└──────────────┘
```

### 同步升级工作分配

| 项目 | 核心工作 | 依赖关系 |
|------|---------|----------|
| **mynet_api** | config-bundle API；心跳 action 类型响应 | 无外部依赖，先行定义 API 契约 |
| **mynet_ctl** | apps.json v2.1（含 plugins）；插件 tgz 分发；config 校验端点；install.sh 更新 | 需 back 的 config-bundle 接口契约 |
| **mynet_client** | 服务三层管理（GNB+路由+防火墙）；插件架构；config-bundle 集成；config check | 需 back API + CTL 分发 |
| **mynet_lua** | config-bundle 集成；路由+防火墙 UCI 管理；心跳 cron；配置校验 | 需 back API + CTL 分发 |
| mynet_gnb/utc | CI 上传对接 CTL（并行推进） | 独立，不阻塞其他项目 |
| mynet_tui | 发布 v1.5.0 最终版：启动时显示迁移提示 | 最后执行 |

### 开发节奏

```
Week 1: API 契约定义（back + ctl 接口文档 + mock）
         → 四个项目可以并行开发

Week 2-3: 核心实现
         back: config-bundle 实现 + 单测
         ctl:  插件分发 + apps.json 2.1
         client: 服务管理重构（GNB+路由+防火墙）+ 插件架构
         lua: config-bundle 集成 + 路由/防火墙管理

Week 4: 联调 + 集成测试
         四项目联调，统一发版

Week 5: tui 最终版发布 + 观察期
```

---

## 九、完整 API 交互矩阵（新版）

### mynet_client ↔ mynet_api

| 请求 | 端点 | 说明 |
|------|------|------|
| 登录 | `POST /api/v2/auth/login` | email + password → token |
| 登出 | `POST /api/v2/auth/logout` | 撤销 token |
| 刷新 token | `POST /api/v2/auth/refresh-token` | refresh_token → 新 token |
| 验证 token | `GET /api/v2/auth/verify-token` | 检查 token 有效性 |
| Zone 列表 | `GET /api/v2/user/zones` | 当前用户可用 Zone |
| 节点列表 | `GET /api/v2/nodes` | X-Zone-ID 过滤 |
| 节点详情 | `GET /api/v2/nodes/{id}` | |
| **配置包** | **`GET /api/v2/nodes/{id}/config-bundle`** | **一次返回所有配置 + 密钥** |
| 心跳上报 | `POST /api/v2/monitor/heartbeat` | Ed25519 签名，返回 action 列表 |
| 状态更新 | `PATCH /api/v2/nodes/{id}/status` | 上报 online/offline |

### mynet_client ↔ mynet_ctl

| 请求 | 端点 | 说明 |
|------|------|------|
| 产品索引 | `GET /api/v2/apps` | 产品列表 + 插件列表 |
| 版本清单 | `GET /api/v2/client/manifest` | 最新版本 + 下载链接 |
| GNB 下载 | `GET /api/v2/releases/{id}/download/{file}` | GNB 二进制 |
| 插件清单 | `GET /api/v2/client/plugins` | 可用插件列表 |
| 插件下载 | `GET /api/v2/plugins/{id}/download/{file}` | 插件 tgz |
| 更新检查 | `GET /api/v2/client/manifest` | 比对版本号 |

### mynet_lua ↔ mynet_api

与 client 相同（config-bundle + 状态上报），仅 HTTP 方式为 curl。

### mynet_lua ↔ mynet_ctl

| 请求 | 端点 | 说明 |
|------|------|------|
| 产品索引 | `GET /api/v2/apps` | 获取 GNB 下载信息 |
| GNB 下载 | `GET /api/v2/releases/{id}/download/{file}` | OpenWrt 架构的 GNB 二进制 |
| LuCI 更新 | 不通过 API | `opkg upgrade` 或手动下载 .ipk |

---

## 十、总结

### 架构简化对比

```
旧 (mynet_tui):
  3 个 Go 二进制 × 13 架构 = 39 个编译目标
  5 次 API 请求同步配置
  4 个配置文件位置
  任意 shell 命令执行
  Shell KEY=VALUE 中间格式
  service = 只管 GNB，路由/防火墙靠 shell 脚本各管各的
  配置写完直接 apply，出错才知道
  proxy 直接改 GNB 配置，无协调机制

新 (mynet_client + mynet_lua):
  1 个 Node.js 包 + 可选插件（零编译）
  1 个 Lua LuCI 包（架构无关 PKGARCH=all）
  1 次 API 请求同步配置（config-bundle）
  2 个配置位置（conf/ 只读 + data/ 读写）
  预定义 action 安全模型
  原生 JSON 配置
  service = GNB + 路由 + 防火墙三层联动
  所有配置先 check 后 apply
  proxy 通过 ServiceManager 协调 GNB 配置变更
  四项目同步升级，CTL 总控协调
```

### 下一步行动（同步推进）

1. **API 契约**: back + ctl 先定义 config-bundle / apps.json v2.1 / plugins 接口契约
2. **四项目并行开发**: back（config-bundle）+ ctl（插件分发）+ client（服务管理+插件）+ lua（config-bundle+路由防火墙）
3. **联调发版**: 四项目集成测试 → 同步发版
4. **mynet_tui**: 发布最终版本并标记 deprecated
