# MyNet 统一规范

> 最后更新：2026-10-01（产品标识 / 代码风格对齐 9 仓现状）  
> SSOT: `mynet_ctl/app/Domain/Release/Services/ReleaseDomainService.php`  
> API: `https://ctl.mynet.club/d/conventions.json`

## 文件命名规范

```
{product}_{platform}_{arch}_{version}.{ext}
```

示例: `gnb_openwrt_mipsel-softfp_v1.6.0.a.tgz`

### 命名规则

| 规则 | 说明 |
|------|------|
| 扩展名 | `tgz`（Linux/OpenWrt/macOS/FreeBSD），`zip`（Windows），`ipk`（OpenWrt Lua） |
| soft-float 后缀 | 默认 hard-float 不加后缀，soft-float 加 `-softfp` |
| tui 别名 | 产品 `tui` 对外文件名前缀使用 `mynet`（历史兼容） |
| 版本号前缀 | 始终带 `v` 前缀，允许 `.` 和 `-` 分隔预发布标识 |

### 版本号格式

```
v{major}.{minor}.{patch}[.{pre}]
```

允许: `v1.0.0`, `v1.6.0.a`, `v0.9.8.10`, `v1.0.0-beta.1`

## 产品标识

> 2026-10-01 更新：现行产品只认下表**上半**；下半为历史 ID，仅在解析旧产物名时用。

### 现行

| ID | 名称 | 文件名前缀 | 技术栈 | 备注 |
|----|------|-----------|--------|------|
| `agent` / `mynet_agent` | MyNet Agent | **`mynet_agent`**（不是 `agent_*`） | Go | mynetd + mynet + gateway + updater |
| `gnb` | GNB | `gnb` | C | P2P VPN 核心 |
| `utc` | UTC | `utc` | C | UDP→TCP 隧道 |

### 历史（已退役，解析旧文件名时用）

| ID | 名称 | 文件名前缀 | 承接者 |
|----|------|-----------|--------|
| `tui` | MyNet TUI | `mynet`（别名） | `mynet_agent` |
| `client` | MyNet Client | `client` | `mynet_agent` |
| `lua` | MyNet Lua | `lua` | `mynet_openwrt` + mynetd |
| `proxy` | MyNet Proxy | `proxy` | 能力并入 agent 的 proxy 域（`mynet_proxy` CLI 已下架） |

## 平台与架构

### 平台
| ID | 标签 | 默认扩展名 |
|----|------|-----------|
| `linux` | Linux | `.tgz` |
| `openwrt` | OpenWrt | `.tgz` / `.ipk` |
| `darwin` | macOS | `.tgz` |
| `windows` | Windows | `.zip` |
| `freebsd` | FreeBSD | `.tgz` |

### 架构

| ID | 家族 | 位数 | GOARCH | GOARM | 说明 |
|----|------|------|--------|-------|------|
| `amd64` | x86 | 64 | amd64 | — | x86 64-bit |
| `i386` | x86 | 32 | 386 | — | x86 32-bit |
| `arm64` | arm | 64 | arm64 | — | ARM 64-bit (hard-float) |
| `arm64-softfp` | arm | 64 | arm64 | — | ARM 64-bit (soft-float) |
| `armv7` | arm | 32 | arm | 7 | ARMv7 (hard-float) |
| `armv7-hardfp` | arm | 32 | arm | 7 | ARMv7 (explicit hard-float, GNB) |
| `armv7-softfp` | arm | 32 | arm | 7 | ARMv7 (soft-float) |
| `armv6-softfp` | arm | 32 | arm | 6 | ARMv6 (soft-float, GNB) |
| `mips64` | mips | 64 | mips64 | — | MIPS 64-bit BE |
| `mips64el` | mips | 64 | mips64le | — | MIPS 64-bit LE |
| `mips` | mips | 32 | mips | — | MIPS 32-bit BE (hard-float) |
| `mips-softfp` | mips | 32 | mips | — | MIPS 32-bit BE (soft-float) |
| `mipsel` | mips | 32 | mipsle | — | MIPS 32-bit LE (hard-float) |
| `mipsel-softfp` | mips | 32 | mipsle | — | MIPS 32-bit LE (soft-float) |
| `mipsel-muslsf` | mips | 32 | mipsle | — | MIPS 32-bit LE (musl soft-float, GNB) |
| `riscv64` | riscv | 64 | riscv64 | — | RISC-V 64-bit |

### 平台×架构 有效组合

| 平台 | 支持的架构 |
|------|-----------|
| linux | amd64, arm64, armv7, armv7-hardfp, armv7-softfp, armv6-softfp, i386, mips, mips64, mipsel, mips64el, mips-softfp, mipsel-softfp, arm64-softfp, riscv64 |
| openwrt | 同 linux + mipsel-muslsf |
| darwin | amd64, arm64 |
| windows | amd64, arm64 |
| freebsd | amd64, arm64 |

### uname -m 映射

| uname -m | → arch |
|----------|--------|
| x86_64 | amd64 |
| amd64 | amd64 |
| i386 / i686 | i386 |
| aarch64 / arm64 | arm64 |
| armv7l | armv7 |
| mips / mipsel | mips / mipsel |
| mips64 / mips64el | mips64 / mips64el |
| riscv64 | riscv64 |

> **注意**：soft-float 变体无法通过 uname 区分，需要额外检测 FPU 或读取 OpenWrt 的 `DISTRIB_ARCH`。

### Node.js ↔ 平台映射
```javascript
// platform: process.platform → os
{ linux: 'linux', darwin: 'darwin', win32: 'windows' }
// arch: process.arch → arch  
{ x64: 'amd64', arm64: 'arm64', arm: 'arm', ia32: '386' }
// alias fallback: darwin ↔ macos, amd64 ↔ x86_64, arm64 ↔ aarch64
```

### Go ↔ 平台映射
```go
// GOOS → platform
{ "linux": "linux", "darwin": "darwin", "windows": "windows" }
// GOARCH → arch
{ "amd64": "amd64", "arm64": "arm64", "arm": "armv7", "386": "i386" }
```

## API 规范

### URL 模式
- **mynet_api**: `{host}/api/v2/{resource}`
- **mynet_ctl**: `/d/{path}` (分发) + `/api/v2/{resource}` (业务)
- **mynet_javis**: `{host}/api/v2/{resource}`

### 认证模式
| 场景 | Header | 说明 |
|------|--------|------|
| 用户 API | `Authorization: Bearer {token}` | Sanctum Token |
| 节点心跳 | `X-Node-Id` + `X-Timestamp` + `X-Node-Signature` | HMAC-SHA256 |
| CTL 管理 | `Authorization: Bearer {api_token}` | API Token |
| 文件分发 | 无 | `/d/` 路径无需认证 |

### 标准响应格式
```json
{
  "success": true,
  "data": { ... },
  "message": "optional message"
}
```

## GNB 配置文件格式

### node.conf（空格分隔 key value）
```
nodeid {16位数字}
listen {port}
ifname {tun_interface_name}
mtu 1450
multi-socket on
daemon off
```

### address.conf（| 分隔）
```
i|{nodeId}|{ip}|{port}
```

### route.conf（| 分隔）
```
{targetNodeId}|{subnet}/{mask}|{forwardNodeId}
```

## 客户端安装目录结构（$MYNET_HOME）

> 此结构由 TUI 方案设计，新的 Node.js Client 沿用。  
> `$MYNET_HOME` 默认值：root → `/opt/mynet`，普通用户 → `~/.local/mynet`。

```
$MYNET_HOME/
├── bin/                               # 可执行文件
│   ├── mynet                          # 主程序入口
│   ├── mynet-updater                  # 自更新辅助程序
│   ├── mynetd                         # 守护进程（心跳、监控、远程命令）
│   └── mynet_proxy                    # 代理模块（可选）
├── conf/                              # 应用层配置
│   ├── config.json                    # 应用配置（API URL、下载源、刷新间隔）
│   ├── credential.json                # 守护进程凭证（user_id, token, machine_id）
│   ├── mynet.conf                     # Shell KEY=VALUE 运行时配置（模板生成）
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
│       ├── proxy_ips.txt
│       ├── proxy_peers.conf
│       ├── proxy_role.conf
│       └── proxy_route.conf
├── driver/                            # VPN 驱动（支持多驱动扩展）
│   └── gnb/                           # GNB 驱动
│       ├── bin/                       # gnb, gnb_ctl, gnb_crypto, gnb_es
│       └── conf/
│           └── {nodeId}/              # 每个节点独立目录
│               ├── node.conf
│               ├── address.conf
│               ├── route.conf
│               ├── ed25519/*.public
│               ├── security/{nodeId}.private
│               ├── security/{nodeId}.public
│               ├── gnb.map
│               └── gnb.pid
├── scripts/                           # 跨平台服务管理脚本
│   └── services/
│       ├── service-manager.sh
│       └── common/
│           ├── common.sh
│           ├── vpn.sh
│           └── route.sh
├── var/
│   └── logs/                          # 运行时日志
│       ├── mynet-{date}.log
│       └── mynetd-{date}.log
├── install.sh
└── uninstall.sh
```

### 关键设计：两层配置

| 层级 | 路径 | 说明 |
|------|------|------|
| mynet 配置层 | `$MYNET_HOME/conf/` | 应用层配置：凭证、菜单、远程命令、路由策略 |
| GNB 驱动层 | `$MYNET_HOME/driver/gnb/conf/{nodeId}/` | GNB 原生配置：node.conf、密钥 |

### 开发模式降级

当 `$MYNET_HOME` 未设置时，Node.js Client 降级到 XDG 路径：`~/.config/mynet/`  
结构保持一致（conf/、driver/、var/logs/），仅根路径不同。

## Logo / Favicon

所有站点统一使用 CTL 提供的图标：

| 资源 | URL |
|------|-----|
| favicon.ico | `https://ctl.mynet.club/favicon.ico` |
| icon.svg | `https://ctl.mynet.club/images/icon.svg` |
| icon.png | `https://ctl.mynet.club/images/icon.png` |
| logo.png | `https://ctl.mynet.club/images/logo.png` |

```html
<link rel="icon" href="https://ctl.mynet.club/favicon.ico" sizes="any">
<link rel="icon" href="https://ctl.mynet.club/images/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="https://ctl.mynet.club/images/icon.png">
```

## CTL 服务器分发目录结构

```
/data/app/mynet.club/download/
├── apps.json                          # 产品索引
├── arch_list.txt                      # 架构映射表
├── mynet_agent/                       # 节点代理（历史目录名可能仍写 mynet/）
│   ├── manifest.json
│   └── v…/
│       ├── checksums.txt
│       └── mynet_agent_{platform}_{arch}_{version}.tgz
├── gnb/
│   ├── manifest.json
│   └── v1.6.0.a/
│       └── gnb_{platform}_{arch}_{version}.tgz
└── tools/
    └── jq-linux-amd64
```

## 代码风格

### PHP (Laravel) — mynet_api / mynet_ctl
- PSR-12 + Laravel Pint
- DDD: `Domain/` (纯业务) → `Services/` (协调) → `Controllers/` (薄)
- Controller 不含业务逻辑
- 使用 `make:migration` 管理数据库

### JavaScript/TypeScript — mynet_gui / mynet_web
- ES2020+, `'use strict'`
- Vue 3 Composition API
- 状态管理: Pinia
- 构建: Vite / Quasar（gui）

### Go — mynet_agent（mynetd / mynet / gateway / updater）
- **mynetd 是纯通信层**，系统能力全在执行器（`AGENTS.md` §1.2）
- `make guard` 红线：结构约束（mynetd 不依赖系统能力、gateway 不鉴权等）
- Cobra CLI（`mynet` 子命令）

### C — mynet_gnb / mynet_utc
- C99 + POSIX
- GNU Make (平台专属 Makefile)
- 无外部依赖（纯 C stdlib + 内嵌 libs）
- **mynet_gnb/src/ 只读**；改动走 `mynet_gnb/packages/` 补丁包

### Lua — mynet_openwrt
- Lua 5.1 / LuCI v3 MVC
- OpenWrt 包格式 (.ipk)
- 目标态：只留壳（菜单/视图/静态资源/rpcd ACL），业务迁 mynetd


## 产物命名（E-8 SSOT）

产物前缀统一 **`mynet_agent_*`**（不是 `agent_*`）：

- `mynet_agent_linux_*` / `mynet_agent_darwin_*` / `mynet_agent_windows_*`
- `mynet_agent_openwrt_*.ipk`

与 `ReleaseDomainService` 一致；守护红线 C3 会拦 `agent_(linux|darwin|…)` 漂移。
