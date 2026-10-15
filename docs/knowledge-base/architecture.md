# MyNet 系统架构

> 最后更新：2026-10-01（按现状对齐：9 仓 / agent 线 / 三层凭证）  
> **定位**：系统总览（启动三层 / `$MYNET_HOME` / 认证 / 同步 / 分发 / 数据库）。  
> **L1–L7 分层权威**是 [`spec/unified-platform-architecture.md`](spec/unified-platform-architecture.md)——两者冲突时以后者为准。  
> 路径细节权威是 [`spec/paths.md`](spec/paths.md)。  
> CTL 站点产品规划不在此，在 [`../ctl-product-architecture.md`](../ctl-product-architecture.md)。

## 整体架构

### 三层服务启动（mynet 执行器管理 GNB）

```
Layer 1: GNB 进程启动
  gnb -c driver/gnb/conf/{nodeId}/
  ↓ 创建 TUN 接口，绑定 UDP 端口

Layer 2: 路由注入
  ip route add / route add → TUN 接口
  ↓ 解析 route.conf，注入系统路由表
  （写入归一：L6 单入口，见 spec/route-apply-unification.md）

Layer 3: 防火墙规则
  iptables / pfctl / fw4 → 允许 TUN 流量
  （驱动 vs 系统分工，见 spec/route-firewall-split.md）
```

**谁做这件事**：`mynet_agent` 的 **`mynet` 执行器**（偶发、用完即抛）。
常驻的 `mynetd` **只做通信**（心跳、WS、取指令、结果上报、命令分派），
**不含**进程生命周期 / 路由写入 / 驱动配置生成——硬约束见 `AGENTS.md` §1.2。

```
mynetd（常驻，纯通信） ──分派──▶ mynet（执行器）──▶ driver/gnb · OS 路由/防火墙
        │
        ▼
   mynet_api（业务权威）· gateway（纯透传，不鉴权）
```

### 安装目录结构（$MYNET_HOME）

> 路径 SSOT 见 [`spec/paths.md`](spec/paths.md)（存储 / 运行 / 投影 / 运行态四层）。
> 下图是常用形状速查，冲突时以 paths 为准。

```
$MYNET_HOME/                           # /opt/mynet 或 ~/.local/mynet
├── bin/                               # mynet, mynetd, mynet-updater
├── conf/                              # 应用层配置
│   ├── config.json                    # API URL、下载源、刷新间隔
│   ├── credential.json                # 守护进程凭证（user_id, token, machine_id）
│   ├── mynet.conf                     # Shell KEY=VALUE 运行时配置
│   ├── command.{platform}.json        # 远程命令映射
│   └── proxy/                         # 代理相关配置
├── driver/                            # VPN 驱动（多驱动扩展）
│   └── gnb/
│       ├── bin/                       # gnb, gnb_ctl, gnb_crypto, gnb_es
│       └── conf/{nodeId}/             # 每节点独立
│           ├── node.conf · address.conf · route.conf
│           ├── ed25519/               # 公钥
│           ├── security/              # 密钥对（.private 权限 0o600）
│           ├── gnb.map                # 运行时映射（gnb 自产，只读）
│           └── gnb.pid
├── scripts/services/                  # 跨平台服务管理脚本
└── var/logs/
```

**$MYNET_HOME 路径决策**：
- root：`/opt/mynet`（Linux/macOS/BSD）；OpenWrt `/etc/mynet`
- 普通用户：`~/.local/mynet`（Linux）、`~/Library/Application Support/MyNet`（macOS）
- 开发模式（未设置）：降级到 `~/.config/mynet/`

**两层配置**：应用层 `$MYNET_HOME/conf/` vs 驱动层 `driver/gnb/conf/{nodeId}/`。
**编码与布局正交**：`node_keys` 存 hex；写 wg 配置时转 base64 44 字符（见 `AGENTS.md` §1.4）。

### 认证（三层三套凭证）

```
┌────────────┬────────────────┬──────────────────────┬─────────────────┐
│ 层         │ 谁 ↔ 谁         │ 认证                  │ 凭证            │
├────────────┼────────────────┼──────────────────────┼─────────────────┤
│ 数据面     │ 节点 ↔ 节点     │ gnb / wg 自己的加密   │ 驱动密钥         │
│ 节点面     │ 节点 ↔ back     │ X25519 DH + HMAC     │ 复用驱动密钥     │
│ 用户面     │ 用户/桌面 ↔ back │ cookie / 登录会话     │ 无密钥           │
└────────────┴────────────────┴──────────────────────┴─────────────────┘
```

- 人用会话（cookie），机器用密钥。
- **gateway 不鉴权**——纯透传 + 连接状态表；鉴权一律归后端。
- X25519 签不了名 → 节点面走 **DH 不走签名**，一套驱动密钥通吃 gnb/wg。
- 心跳：`POST /api/v2/monitor/heartbeat`，`X-Node-Id` + `X-Timestamp` + `X-Node-Signature`（HMAC-SHA256）。
- 用户 Token 刷新：API 401 → `POST /auth/token/refresh`。

详见 [`spec/node-keying.md`](spec/node-keying.md) · [`spec/auth.md`](spec/auth.md)。

### 配置同步协议

```
1. GET /api/v2/nodes/{nodeId}/config-bundle
   → { node_id, files: { node.conf, address.conf, route.conf },
       keys: { ed25519, security }, version }

2. 写入 driver/gnb/conf/{nodeId}/；security/*.private 权限 0o600

3. 校验：node.conf + address.conf 非空，ed25519/ 至少一个 .public，security/ 有 .private

4. 失败回滚：同步前备份 → 校验失败恢复
```

⚠️ 云端 bundle **不保证下发 zip/crypto 等对等项**——对等项要靠本地约定或
`mynet config parity` 基线（见 `troubleshoot-connectivity.md` Q&A）。

### CTL 分发协议

```
1. GET ctl.mynet.club/d/apps.json
   → { products: { gnb, mynet_agent, utc, … } }

2. GET /d/{product}/manifest.json
   → { latest_stable, versions: { v…: { artifacts: [{ os, arch, file, url }] } } }

3. 平台匹配：exact → alias (darwin↔macos, amd64↔x86_64) → probe URL

4. GET /d/{product}/{file}.tgz → 解压到 driver/{vpnType}/bin/
```

产物前缀统一 **`mynet_agent_*`**（不是 `agent_*`）。命名矩阵见 [`conventions.md`](conventions.md)。

## 数据库架构

### mynet_api（业务权威）

| 表 | 用途 |
|----|------|
| users | 用户（email, password, sanctum tokens） |
| zones · zone_members | 网络域（多租户）与成员 |
| nodes | 节点（node_id, zone_id, user_id, vpn_type, status） |
| node_keys | 密钥对（public_key, private_key, shared_key）— 私钥出口要收口 |
| routes | 路由规则（source_node → target_node, subnet） |
| ip_pools | IP 地址池 |
| node_heartbeats | 心跳（metrics, timestamp） |

### mynet_ctl（分发中心）

| 表 | 用途 |
|----|------|
| releases | 版本发布（product, version, platform, arch, file_path） |
| scripts · plugins | 安装/升级脚本、插件 |
| download_logs · api_tokens | 下载统计、API 令牌 |
| openwrt_devices | OpenWrt 设备库（1930+） |

### 独立产品（不在本 workspace）

`mynet_javis`（myjarvis.ai）曾并列描述，现已划出 workspace——
若仍需其表结构，请查 `projects/_retired/mynet_javis.md` 或独立仓文档。

## 安全要点

- **TUN 接口**需要 root/sudo（macOS + Linux）
- **驱动密钥**：节点间加密；私钥权限 0o600；`back_priv` 保护等级 **>** 节点私钥
- **HMAC-SHA256** 心跳认证，防重放
- **Sanctum** 用户认证；本地 token 用 HttpOnly + SameSite=Strict
- **embed WebUI 必须校验 `Origin`/`Host`**（防 DNS rebinding）
- **禁止按名字杀进程**（pidfile + cmdline 精确停）
- 完整红线见 [`AGENTS.md`](AGENTS.md) §3
