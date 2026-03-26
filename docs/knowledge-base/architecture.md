# MyNet 系统架构

> 最后更新：2026-03-26

## 整体架构

### 三层服务启动（mynet_client 管理 GNB）

```
Layer 1: GNB 进程启动
  gnb -c driver/gnb/conf/{nodeId}/
  ↓ 创建 TUN 接口，绑定 UDP 端口

Layer 2: 路由注入
  ip route add / route add → TUN 接口
  ↓ 解析 route.conf，注入系统路由表

Layer 3: 防火墙规则
  iptables / pfctl → 允许 TUN 流量
```

### 客户端安装目录结构（$MYNET_HOME）

> 由 TUI 方案设计，Node.js Client 沿用。详见 [conventions.md](conventions.md#客户端安装目录结构mynet_home)。

```
$MYNET_HOME/                           # /opt/mynet 或 ~/.local/mynet
├── bin/                               # 可执行文件（mynet, mynetd, mynet-updater）
├── conf/                              # 应用层配置
│   ├── config.json                    # 应用配置（API URL、下载源、刷新间隔）
│   ├── credential.json                # 守护进程凭证（user_id, token, machine_id）
│   ├── mynet.conf                     # Shell KEY=VALUE 运行时配置（模板生成）
│   ├── command.{platform}.json        # 远程命令映射
│   └── proxy/                         # 代理相关配置
├── driver/                            # VPN 驱动（支持多驱动扩展）
│   └── gnb/                           # GNB 驱动
│       ├── bin/                       # gnb, gnb_ctl, gnb_crypto, gnb_es
│       └── conf/
│           └── {nodeId}/              # 每个节点独立目录
│               ├── node.conf
│               ├── address.conf
│               ├── route.conf
│               ├── ed25519/           # 公钥
│               ├── security/          # 密钥对
│               ├── gnb.map            # 运行时映射
│               └── gnb.pid            # 运行时 PID
├── scripts/                           # 跨平台服务管理脚本
│   └── services/
├── var/
│   └── logs/                          # 运行时日志
├── install.sh
└── uninstall.sh
```

**$MYNET_HOME 路径决策**：
- root 用户：`/opt/mynet`（Linux/OpenWrt）、`/usr/local/opt/mynet`（macOS）
- 普通用户：`~/.local/mynet`（Linux）、`~/Library/Application Support/MyNet`（macOS）
- 开发模式（$MYNET_HOME 未设置）：降级到 `~/.config/mynet/`

**两层配置设计**：
| 层级 | 路径 | 说明 |
|------|------|------|
| mynet 配置层 | `$MYNET_HOME/conf/` | 应用层：凭证、菜单、远程命令、路由策略 |
| GNB 驱动层 | `$MYNET_HOME/driver/gnb/conf/{nodeId}/` | GNB 原生配置：node.conf、密钥 |

### 认证流程

```
1. 用户登录
   POST api.mynet.club/api/v2/auth/login
   → { token, user, zone }

2. Token 自动刷新
   每次 API 调用 → 401 → 自动 POST /auth/token/refresh
   → 新 token 写入 config.json

3. 节点心跳认证（独立于用户 Token）
   POST /api/v2/monitor/heartbeat
   Header: X-Node-Id + X-Timestamp + X-Node-Signature (HMAC-SHA256)
   → 签名材料: METHOD + PATH + TIMESTAMP + BODY
   → 密钥: 节点的 shared_key（在 mynet_back 创建节点时生成）
```

### 配置同步协议

```
1. 客户端请求配置包
   GET /api/v2/nodes/{nodeId}/config-bundle
   → { node_id, files: { node.conf, address.conf, route.conf },
       keys: { ed25519: { id: pubkey }, security: { private, public } },
       version }

2. 客户端写入本地
   driver/gnb/conf/{nodeId}/
   ↓ 写入所有文件
   ↓ 设置 security/*.private 权限 0o600

3. 配置校验
   checkGnbConfig(): node.conf + address.conf 存在且非空,
   ed25519/ 至少一个 .public, security/ 有 .private

4. 失败回滚
   同步前备份 → 校验失败 → 恢复备份
```

### CTL 分发协议

```
1. 客户端获取产品索引
   GET ctl.mynet.club/d/apps.json
   → { products: { gnb: { manifest_url }, tui: { ... } } }

2. 获取版本清单
   GET /d/{product}/manifest.json
   → { latest_stable, versions: {
         v1.6.0: { artifacts: [{ os, arch, file, url }] }
       }}

3. 平台匹配
   Node.js: process.platform → os, process.arch → arch
   匹配优先级: exact → alias (darwin↔macos, amd64↔x86_64) → probe URL

4. 下载安装
   GET /d/{product}/{file}.tgz → 解压提取 binary → driver/{vpnType}/bin/
```

## 数据库架构（mynet_back）

### 核心表
| 表 | 用途 |
|----|------|
| users | 用户（email, password, sanctum tokens） |
| zones | 网络域（多租户隔离） |
| zone_members | Zone 成员关系 |
| nodes | 节点（node_id, zone_id, user_id, vpn_type, status） |
| node_keys | Ed25519 密钥对（public_key, private_key, shared_key） |
| routes | 路由规则（source_node → target_node, subnet） |
| ip_pools | IP 地址池管理 |
| node_heartbeats | 心跳记录（metrics, timestamp） |

### 数据库架构（mynet_ctl）
| 表 | 用途 |
|----|------|
| releases | 版本发布（product, version, platform, arch, file_path） |
| scripts | 安装/升级脚本 |
| plugins | 插件管理 |
| download_logs | 下载统计 |
| api_tokens | API 访问令牌 |
| openwrt_devices | OpenWrt 设备库（1930+ 设备） |

### 数据库架构（mynet_javis）
| 表 | 用途 |
|----|------|
| subdomains | 用户子域名（*.myjarvis.ai） |
| proxy_targets | 节点实时状态（IP, 端口, 质量分） |
| quality_logs | 质量时序数据（30 天保留） |
| plans | 套餐定义（托管版/自托管版） |
| user_plans | 用户订阅记录 |

## 安全要点

- **TUN 接口**：需要 root/sudo 权限（macOS + Linux）
- **Ed25519**：节点间通信签名，私钥权限 0o600
- **HMAC-SHA256**：心跳认证，防重放攻击
- **Sanctum Token**：用户认证，httpOnly cookie + Bearer token 双模式
- **Rate Limiting**：客户端 WebUI 300 req/min，Helmet CSP 保护
- **Javis Quality Gate**：质量分 < 40 自动降级（503），防止低质量节点影响用户
