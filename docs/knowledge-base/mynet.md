# MyNet 客户端与 GNB 驱动 — 技术参考文档

> 本文档服务于 mynet 所有项目，描述 GNB 驱动的目录结构/文件格式，以及 mynet 客户端（tui）的完整安装流程和运行机制。

---

## 1. GNB 驱动目录结构与文件格式

GNB 是 mynet 依赖的 P2P VPN 底层驱动。一个 GNB 节点的标准目录结构如下：

### 1.1 目录树

```
gnb_root/
├── bin/
│   ├── gnb               # GNB P2P VPN 主进程
│   ├── gnb_ctl            # GNB 控制工具
│   ├── gnb_crypto         # GNB 密钥生成工具
│   └── gnb_es             # GNB ES（Extra Service）进程
└── conf/
    └── {nodeId}/              # 每个节点一个目录，nodeId 为 16 位数字
        ├── node.conf          # 节点运行配置
        ├── address.conf       # 索引节点地址列表
        ├── route.conf         # 路由配置表
        ├── ed25519/           # Ed25519 公钥目录
        │   ├── {nodeId}.public      # 自身公钥
        │   ├── {peerNodeId}.public  # 对等节点公钥（多个）
        │   └── ...
        ├── security/          # 密钥目录
        │   ├── {nodeId}.private     # 自身私钥（128 个 hex 字符 = 64 字节，clamp(SHA512(seed))）
        │   └── {nodeId}.public      # 自身公钥（64 个 hex 字符 = 32 字节）
        ├── gnb.map            # 运行时生成的节点映射表
        └── gnb.pid            # 运行时 PID 文件
```

**启动命令**：`gnb -c gnb_root/conf/{nodeId}` （加 `-d` 可后台运行）

### 1.2 文件格式详解

#### node.conf — 节点运行配置（空格分隔 key value）

```ini
nodeid 3722810996882027
ifname gnb_tun_178
listen 9016
mtu 1450
multi-socket on
direct-forwarding on
unified-forwarding auto
standard-forwarding on
address-secure on
ipv4-only off
ipv6-only off
keepalive 25
forward on
daemon on
zip auto
zip-level -1
memory tiny
es-argv --upnp
```

注意：字段名用 kebab-case（如 `multi-socket`、`direct-forwarding`）。

#### address.conf — 索引节点地址列表（管道分隔）

```
# Format: i|0|ip|port
i|0|52.221.22.241|9016
i|0|idx.mynet.club|9016
i|0|2406:da18:9df:900:1855:beb3:ea22:4e81|9016
i|0|112.124.22.220|9016
i|0|2408:4005:36d:7e00:dbeb:cd16:ed95:3179|9016
```

- `i` = index service type
- `0` = reserved（总为 0）
- 支持 IPv4、IPv6、域名

#### route.conf — 路由配置表（管道分隔）

```
# Format: {nodeId}|{ipAddress}|{netmask}

# Node Self Routes
3722810996882027|10.182.236.178|255.255.255.0
3722810996882027|192.168.8.1|255.255.255.0

# User Node Routes
8283289760467957|10.182.236.182|255.255.255.0
8283289760467957|192.168.0.2|255.255.255.0
6802325343720556|10.182.236.179|255.255.255.0
```

一个节点可以有多条路由条目（VPN IP + 局域网 IP）。

#### ed25519/*.public — 公钥文件

每个文件包含一个 Ed25519 公钥的 hex 字符串（64 个 hex 字符 = 32 字节）：

```
e9ca457c243383bc68a6ab2635867986ec386e2f0faff5bc3394c78fc7f64bc3
```

#### security/{nodeId}.private — 私钥文件

GNB 非标准 Ed25519 私钥的 hex 字符串：`clamp(SHA512(seed))`，128 个 hex 字符 = 64 字节
（**不是** `seed‖pub` 布局，拿 stdlib 的 `seed‖pub` 去算会签错，见 AGENTS §4 坑表第 2 条）：

```
48973280e7ae2c6bfbb6103387f0566361f91a3528c57a3ad240334a3a6c9e60a49e4acf3d8dceaa0cb8fc49b494f9e1f3b6d39caf3c920a5194a819d83ce793
```

#### security/{nodeId}.public — 公钥文件

与 ed25519/ 下的公钥内容一致，64 个 hex 字符。

---

## 2. MyNet 客户端（tui）运行时目录结构

mynet 客户端将 GNB 作为 "driver" 嵌套管理，完整的安装后目录结构如下：

```
$MYNET_HOME/                           # /opt/mynet 或 ~/.local/mynet
├── bin/
│   ├── mynet                          # 主程序（Go，CLI + TUI）
│   ├── mynet-updater                  # 自更新辅助程序
│   ├── mynetd                         # 守护进程（心跳、监控、远程命令执行）
│   └── mynet_proxy                    # 代理模块（可选）
├── conf/
│   ├── config.json                    # 应用配置（API URL、下载源、刷新间隔）
│   ├── credential.json                # 守护进程凭证（user_id, token, machine_id）
│   ├── mynet.conf                     # Shell KEY=VALUE 运行时配置（由模板生成）
│   ├── mynet.conf.template            # mynet.conf 模板
│   ├── route.conf                     # 系统路由表（由 GNB route.conf 翻译）
│   ├── route.conf.template            # route.conf 模板
│   ├── dnat.conf.template             # DNAT 端口转发模板
│   ├── menu.json                      # TUI 菜单树定义
│   ├── welcome.json                   # TUI 欢迎屏文字
│   ├── command.linux.json             # 远程命令映射（Linux）
│   ├── command.darwin.json            # 远程命令映射（macOS）
│   ├── command.openwrt.json           # 远程命令映射（OpenWrt）
│   └── proxy/                         # 代理相关配置
│       ├── proxy_sources/
│       │   ├── chinaip.txt
│       │   ├── interip.txt
│       │   └── ...
│       ├── proxy_ips.txt
│       ├── proxy_peers.conf
│       ├── proxy_role.conf
│       └── proxy_route.conf
├── driver/
│   └── gnb/                           # GNB 驱动（完整的 GNB 目录）
│       ├── bin/
│       │   ├── gnb
│       │   ├── gnb_ctl
│       │   ├── gnb_crypto
│       │   └── gnb_es
│       └── conf/
│           └── {nodeId}/              # GNB 节点配置（结构同上节 1.1）
│               ├── node.conf
│               ├── address.conf
│               ├── route.conf
│               ├── ed25519/*.public
│               ├── security/{nodeId}.private
│               ├── security/{nodeId}.public
│               ├── gnb.map
│               └── gnb.pid
├── scripts/
│   └── services/
│       ├── service-manager.sh         # 统一跨平台服务管理入口
│       └── common/
│           ├── common.sh              # 平台检测、路径解析、日志
│           ├── vpn.sh                 # VPN 进程管理（启停/状态）
│           └── route.sh               # 系统路由管理
├── var/
│   └── logs/
│       ├── mynet-{date}.log           # 主程序日志
│       └── mynetd-{date}.log          # 守护进程日志
├── install.sh                         # 安装脚本
└── uninstall.sh                       # 卸载脚本
```

### 关键设计：两层配置

| 层级 | 路径 | 说明 |
|------|------|------|
| mynet 配置层 | `conf/` | 应用层配置：登录凭证、菜单、远程命令映射、路由策略 |
| GNB 驱动层 | `driver/gnb/conf/{nodeId}/` | GNB 原生配置：node.conf、address.conf、route.conf、密钥 |

mynet 客户端读取 `driver/gnb/conf/{nodeId}/` 下的 GNB 配置来生成 `conf/mynet.conf`（Shell KEY=VALUE 格式），以供脚本和守护进程使用。

---

## 3. 安装流程

### 3.1 发布包格式

构建系统（Makefile + pack.sh）产出：

| 格式 | 平台 |
|------|------|
| `mynet_{platform}_{arch}_v{version}.tgz` | 所有平台 |
| `.deb` | Debian/Ubuntu |
| `.rpm` | RHEL/CentOS |
| `.pkg` | macOS |
| `.ipk` | OpenWrt |

三个二进制均为 Go 编译（CGO_ENABLED=0 纯 Go），支持交叉编译。

### 3.2 安装步骤

```bash
# 1. 下载（从 CTL 或 GitHub Release）
wget https://ctl.mynet.club/api/v1/releases/{id}/download/mynet_linux_amd64_v1.4.0.tgz

# 2. 解压
tar xzf mynet_linux_amd64_v1.4.0.tgz && cd mynet/

# 3. 安装
./install.sh                     # 自动检测平台
./install.sh --prefix /opt/mynet # 指定目录
./install.sh --no-service        # 不安装系统服务
```

### 3.3 install.sh 做了什么

1. **平台检测**：darwin / linux / openwrt / freebsd
2. **安装目录决策**：
   - root 用户：`/opt/mynet`（Linux/macOS/BSD）；OpenWrt `/etc/mynet`
   - 普通用户：`~/.local/mynet`（Linux）、`~/Library/Application Support/MyNet`（macOS）
3. **复制文件**：
   - `bin/` → `$INSTALL_DIR/bin/`（mynet、mynet-updater、mynetd）
   - `conf/` → `$INSTALL_DIR/conf/`（不覆盖已有配置）
   - `scripts/` → `$INSTALL_DIR/scripts/`
4. **创建符号链接**：
   - root: `/usr/local/bin/mynet` → 安装目录
   - 非 root: `~/.local/bin/mynet` → 安装目录
5. **安装系统服务**：
   - Linux root: systemd `mynetd.service`
   - macOS: launchd `com.mynet.mynetd.plist`
   - OpenWrt: init.d `/etc/init.d/mynet`
6. **环境配置**：非 root 用户写入 `~/.mynet/env`，追加到 shell profile

---

## 4. 客户端运行机制

### 4.1 技术栈

- **语言**：Go 1.21
- **CLI 框架**：spf13/cobra
- **TUI 框架**：rivo/tview + gdamore/tcell
- **系统监控**：shirou/gopsutil（CPU、内存、磁盘）
- **依赖注入**：AppContainer（composition 模式）

### 4.2 双模式入口

```
mynet               → 启动 TUI 管理界面（交互式，需要 root）
mynet <command>     → 执行 CLI 命令（非交互）
```

`main.go` 根据参数决定走 TUI 还是 CLI 路径。无参数或仅有 `--debug`/`-c` 等配置参数时进入 TUI。

### 4.3 CLI 命令列表

| 命令 | 功能 |
|------|------|
| `mynet` | 启动 TUI 管理界面 |
| `mynet login` | 用户登录（TUI） |
| `mynet logout` | 用户登出 |
| `mynet zone` | Zone 管理（TUI） |
| `mynet nodes list [--refresh] [--format json]` | 列出当前 Zone 节点 |
| `mynet nodes show <node-id>` | 查看节点详情 |
| `mynet nodes summary` | 节点统计摘要 |
| `mynet nodes refresh` | 从 API 刷新节点数据 |
| `mynet status [node-name]` | 节点状态查询 |
| `mynet status --start/--stop/--restart <node>` | 节点生命周期管理 |
| `mynet monitor [node] [-i 30]` | 持续监控 |
| `mynet refresh-config --node-id N` | 从 driver 配置重生成 mynet.conf |
| `mynet refresh-routes --node-id N` | 刷新 route.conf + 同步对等节点公钥 |
| `mynet check` | 系统健康检查 |
| `mynet config show` | 查看当前配置 |
| `mynet config check` | 验证配置和 Token 有效性 |
| `mynet token` | Token 管理 |
| `mynet platform` | 平台检测信息 |

**通用 flags**: `--debug`, `--conf-dir / -c`, `--log-dir`, `--zones-ttl`, `--nodes-ttl`, `--status-ttl`

### 4.4 核心流程：登录 → Zone → 节点配置同步 → GNB 启动

```
┌─────────────────────────────────────────────────────────────────────┐
│ 1. 登录                                                             │
│    用户 → mynet login (TUI)                                         │
│    → POST api.mynet.club/api/v1/auth/login                          │
│      {email, password, device_name}                                  │
│    ← {auth_token, refresh_token, user{id, name, email}}             │
│    → 保存到 ~/.mynet/config.json + conf/credential.json             │
├─────────────────────────────────────────────────────────────────────┤
│ 2. 选择 Zone                                                        │
│    → GET api.mynet.club/api/v1/zones (Bearer token, X-Zone-ID)      │
│    ← Zone 列表                                                      │
│    → 用户选择 → 保存 current_zone 到 config.json                     │
├─────────────────────────────────────────────────────────────────────┤
│ 3. 节点配置同步（EnsureConfig）                                      │
│    → GET api.mynet.club/api/v1/nodes/{nodeId}/config?render_conf=1  │
│    ← 服务端渲染的 node.conf 纯文本（完整 GNB 配置）                   │
│    → 写入 driver/gnb/conf/{nodeId}/node.conf                        │
│    → 同理获取 address.conf、route.conf                               │
│    → 密钥通过心跳接口同步到 ed25519/*.public                         │
├─────────────────────────────────────────────────────────────────────┤
│ 4. GNB 启动（Smart Start）                                          │
│    ① 预检查：node.conf 存在？网卡冲突？端口占用？                     │
│    ② 服务端状态：GET /nodes/{nodeId}/status-info → 必须 enabled/online│
│    ③ 驱动二进制：检查 driver/gnb/bin/gnb 存在                        │
│    ④ 启动：gnb -c driver/gnb/conf/{nodeId}                          │
│    ⑤ 上报在线：PUT /nodes/{nodeId}/status → status=2 (online)       │
└─────────────────────────────────────────────────────────────────────┘
```

**关键设计**：node.conf 内容由**服务端渲染**，客户端不在本地拼装 GNB 配置，而是直接写入 API 返回的纯文本。

### 4.5 配置文件详解

#### conf/config.json — 应用配置

```json
{
  "server_config": {
    "api_base_url": "https://api.mynet.club/api/v1",
    "timeout": 30,
    "retry_count": 3
  },
  "downloads": {
    "apps_index_url": "https://download.mynet.club/apps.json"
  },
  "refresh": {
    "zones_ttl_seconds": 300,
    "nodes_ttl_seconds": 60,
    "status_ttl_seconds": 15,
    "status_auto_refresh_seconds": 15
  }
}
```

#### ~/.mynet/config.json — 用户状态（登录后生成）

```json
{
  "server": "https://api.mynet.club/api/v1",
  "email": "user@example.com",
  "token": "703|...",
  "refresh_token": "...",
  "user_id": 3844197700300659,
  "current_zone": {"zone_id": 123, "zone_name": "my-zone"},
  "current_node_info": {"node_id": 3722810996882027, ...},
  "ui_settings": {"status_auto_refresh": 15}
}
```

#### conf/credential.json — 守护进程凭证

```json
{
  "user_id": 3844197700300659,
  "user_email": "user@example.com",
  "token": "703|...",
  "refresh_token": "...",
  "machine_id": "27d90670-2e88-5bbd-9b21-25ddf2fb6106",
  "created_at": "2025-11-19T10:55:03Z",
  "expires_at": "2125-11-19T10:55:03Z"
}
```

`machine_id` 由 UUID v5 算法从 hostname + username + OS + arch 生成。token 有效期 100 年（实质永久）。

#### conf/mynet.conf — 运行时配置（Shell KEY=VALUE 格式，由模板生成）

```ini
VPN_INTERFACE=gnb_tun
VPN_IP=10.182.236.180
VPN_TYPE=gnb
PLATFORM=darwin
MYNET_HOME=/opt/mynet
VPN_BINARY=/opt/mynet/driver/gnb/bin/gnb
VPN_CONFIG=/opt/mynet/driver/gnb/conf/{nodeId}
VPN_WORKDIR=/opt/mynet/driver/gnb
CONFIG_DIR=/opt/mynet/conf
LOG_DIR=/opt/mynet/var/logs
PID_FILE=/opt/mynet/var/run/mynet.pid
ROUTER_MODE=auto
```

由 `mynet refresh-config --node-id N` 从 driver/gnb 配置读取后按模板生成。

#### conf/command.{platform}.json — 远程命令映射

守护进程收到服务端命令时查此表执行：

```json
{
  "mynet.start":               {"command": "scripts/mynetd", "args": ["start"]},
  "mynet.stop":                {"command": "scripts/mynetd", "args": ["stop"]},
  "mynet.gnb.refresh.route":   {"command": "bin/mynet", "args": ["refresh-routes", "--auto", "--node-id", "{{.NodeID}}"],
                                 "chain_commands": ["mynet.restart"]},
  "mynet.gnb.refresh.address": {"command": "bin/mynet", "args": ["refresh-config", "--auto", "--node-id", "{{.NodeID}}"]},
  "mynet.route.refresh":       {"command": "scripts/services/service-manager.sh", "args": ["route", "refresh"]}
}
```

支持模板变量（`{{.NodeID}}`）、命令链（chain_commands）、超时控制、结果回调。

---

## 5. 守护进程（mynetd）

mynetd 是独立二进制，以 root 运行，负责持续监控和心跳上报。

### 5.1 运行模式

| 模式 | 命令 | 说明 |
|------|------|------|
| 单次 | `mynetd` | 执行一次心跳后退出（适合 cron） |
| 守护 | `mynetd -d` | 持续运行，默认每 300 秒心跳 |

### 5.2 心跳周期

```
1. 收集系统指标（CPU、内存、磁盘 — via gopsutil）
2. 收集 VPN 接口状态（ifname、IP、up/down）
3. 收集对等节点连通性数据
4. 用 Ed25519 私钥签名心跳数据（gzip 压缩）
5. POST /api/v1/monitor/heartbeat → 服务端
6. 处理响应：
   - 更新节点状态
   - 执行服务端下发的远程命令（查 command.{platform}.json 映射）
   - 将命令执行结果回调到服务端（截断 10KB）
```

### 5.3 公钥同步

由 `mynet refresh-routes --node-id N` 完成，也由守护进程通过远程命令自动触发：

```
1. 读取 driver/gnb/conf/{nodeId}/route.conf
2. 提取所有对等节点 ID（排除自身）
3. POST /api/v1/monitor/router-keys {node_ids: [...]}（心跳签名认证）
4. 将返回的公钥（64 字符 hex）写入 ed25519/{peerNodeId}.public
5. 增量更新：内容相同不覆盖，新增/变更才写入
```

---

## 6. 自更新机制

`mynet-updater` 是独立二进制，在主程序退出后执行替换：

### 单文件更新（legacy）

```bash
mynet-updater <旧路径> <新路径> [重启参数...]
# 1. 等待父进程退出
# 2. 备份旧文件 → {path}.backup
# 3. 复制新文件覆盖旧路径
# 4. 设置可执行权限
# 5. exec 启动新版本
```

### 全目录更新（当前主流）

```bash
mynet-updater --full-update <当前目录> <新目录> [重启参数...]
# 1. 等待主程序退出
# 2. 创建时间戳备份目录 {dir}.backup.{timestamp}
# 3. 备份关键文件：bin/mynet, bin/mynet-updater, bin/mynetd
# 4. 遍历新目录，复制所有文件到当前目录
# 5. 设置可执行权限
# 6. 清理临时目录
# 7. exec 启动新版本
```

**失败回滚**：任何步骤出错自动恢复备份。日志写入 `/tmp/mynet-updater.log`。

---

## 7. 与 CTL 的交互

mynet 客户端通过以下 CTL 端点获取软件和配置：

| 请求 | CTL 端点 | 用途 |
|------|----------|------|
| 应用索引 | `GET ctl.mynet.club/api/v1/apps.json` | 获取所有产品列表 |
| 版本清单 | `GET ctl.mynet.club/api/v1/{product}/manifest.json` | 获取产品最新版本信息 |
| 二进制下载 | `GET ctl.mynet.club/api/v1/releases/{id}/download/{file}` | 下载 tgz 安装包 |
| 架构检测脚本 | `GET ctl.mynet.club/api/v1/detect/openwrt.sh` | OpenWrt 硬件检测 |

config.json 中 `downloads.apps_index_url` 配置了起始 URL（历史遗留指向 `download.mynet.club`，新版已改为 `ctl.mynet.club`）。

---

## 8. 与 mynet_api 的交互

客户端通过以下 mynet_api API 获取配置（Bearer token 认证，X-Zone-ID 头）：

| 请求 | mynet_api 端点 | 用途 |
|------|-----------------|------|
| 登录 | `POST /api/v1/auth/login` | 获取 auth_token + refresh_token |
| 登出 | `POST /api/v1/auth/logout` | 注销 |
| 刷新 Token | `POST /api/v1/auth/refresh` | 自动续期 |
| 验证 Token | `GET /api/v1/auth/verify` | 检查有效性 |
| Zone 列表 | `GET /api/v1/zones` | 获取用户的 Zone |
| 节点列表 | `GET /api/v1/nodes` | 获取 Zone 下的节点 |
| 节点配置 | `GET /api/v1/nodes/{id}/config?render_conf=1` | 获取服务端渲染的 node.conf |
| 节点状态 | `GET /api/v1/nodes/{id}/status-info` | 查询节点状态 |
| 上报状态 | `PUT /api/v1/nodes/{id}/status` | 上报 online/offline |
| 心跳 | `POST /api/v1/monitor/heartbeat` | 守护进程心跳（Ed25519 签名） |
| 路由公钥 | `POST /api/v1/monitor/router-keys` | 批量获取对等节点公钥 |
| 命令回调 | `POST /api/v1/monitor/command-callback` | 上报远程命令执行结果 |