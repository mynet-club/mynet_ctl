# MyNet 统一平台/架构/命名规范

> **SSOT (Single Source of Truth)**:  
> `mynet_ctl/app/Domain/Release/Services/ReleaseDomainService.php`  
> API: `https://ctl.mynet.club/d/conventions.json`

## 命名格式

```
{product}_{platform}_{arch}_{version}.{ext}
```

### 示例

```
mynet_linux_amd64_v0.9.8.10.tgz
gnb_openwrt_mipsel-softfp_v1.6.0.a.tgz
gnb_windows_amd64_v1.6.0.zip
```

### 命名规则

| 规则 | 说明 |
|------|------|
| 扩展名 | `tgz`（Linux/OpenWrt/macOS/FreeBSD），`zip`（Windows），`ipk`（OpenWrt Lua） |
| soft-float 后缀 | 默认 hard-float 不加后缀，soft-float 加 `-softfp` |
| tui 别名 | 产品 `tui` 对外文件名前缀使用 `mynet`（历史兼容） |
| 版本号前缀 | 始终带 `v` 前缀，允许 `.` 和 `-` 分隔预发布标识 |

---

## 产品（Products）

| ID | 名称 | 文件名前缀 | 语言 | 说明 |
|----|------|------------|------|------|
| `agent` | MyNet Agent | `agent` | Go | **产品本体**：`mynetd` + `mynet` CLI（目标线） |
| `gnb` | GNB | `gnb` | C | GNB P2P 组网核心（**冻结**，仅保留驱动接缝） |
| `wireguard` | WireGuard | `wireguard` | C | 预留驱动位（见 `knowledge-base/spec/driver-abstraction.md`） |
| `utc` | UTC | `utc` | C | UDP→TCP 隧道转换器 |
| `proxy` | MyNet Proxy | `proxy` | Node.js | Mihomo 代理管理器 |
| `lua` | MyNet Lua | `lua` | Lua | OpenWrt LuCI 壳（`.ipk`） |
| `tui` | MyNet TUI | `mynet` (别名) | Go | Go 终端客户端（**冻结**） |
| `client` | MyNet Client | `client` | Node.js | 跨平台客户端（**冻结**，已被 `agent` 线取代） |

> 产品表按 [`knowledge-base/integration-plan-v2.md`](knowledge-base/integration-plan-v2.md) §2 冻结策略维护。

## 平台（Platforms）

| ID | 标签 | 默认扩展名 |
|----|------|-----------|
| `linux` | Linux | tgz |
| `openwrt` | OpenWrt | tgz |
| `darwin` | macOS | tgz |
| `windows` | Windows | zip |
| `freebsd` | FreeBSD | tgz |

## 架构（Architectures）

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

## 平台×架构 有效组合

| 平台 | 支持的架构 |
|------|-----------|
| linux | amd64, arm64, armv7, armv7-hardfp, armv7-softfp, armv6-softfp, i386, mips, mips64, mipsel, mips64el, mips-softfp, mipsel-softfp, arm64-softfp, riscv64 |
| openwrt | 同 linux + mipsel-muslsf |
| darwin | amd64, arm64 |
| windows | amd64, arm64 |
| freebsd | amd64, arm64 |

## uname -m 映射

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

## 版本号格式

```
v{major}.{minor}.{patch}[.{pre}]
```

- 允许: `v1.0.0`, `v1.6.0.a`, `v0.9.8.10`, `v1.0.0-beta.1`
- 始终带 `v` 前缀

## API 端点

```
GET https://ctl.mynet.club/d/conventions.json
```

返回完整的产品/平台/架构/命名规则数据，供编译脚本和各项目消费。

## Logo / Favicon

所有站点统一使用 CTL 提供的图标：

| 资源 | URL |
|------|-----|
| favicon.ico | `https://ctl.mynet.club/favicon.ico` |
| icon.svg | `https://ctl.mynet.club/images/icon.svg` |
| icon.png | `https://ctl.mynet.club/images/icon.png` |
| logo.png | `https://ctl.mynet.club/images/logo.png` |

### 引用方式

```html
<link rel="icon" href="https://ctl.mynet.club/favicon.ico" sizes="any">
<link rel="icon" href="https://ctl.mynet.club/images/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="https://ctl.mynet.club/images/icon.png">
```

## 服务器目录结构

```
/data/app/mynet.club/download/
├── apps.json                          # 产品索引
├── arch_list.txt                      # 架构映射表
├── mynet/
│   ├── manifest.json                  # mynet (tui) 版本清单
│   ├── v0.9.8.10/
│   │   ├── checksums.txt
│   │   ├── mynet_linux_amd64_v0.9.8.10.tgz
│   │   ├── mynet_openwrt_mipsel-softfp_v0.9.8.10.tgz
│   │   └── ...
│   └── v0.9.7.3/
│       └── ...
├── gnb/
│   ├── manifest.json
│   └── v1.6.0.a/
│       ├── checksums.txt
│       ├── gnb_linux_amd64_v1.6.0.a.tgz
│       ├── gnb_openwrt_mipsel-muslsf_v1.6.0.a.tgz
│       └── ...
├── tools/
│   ├── jq-linux-amd64
│   └── ...
└── wireguard/
    └── wireguard.json
```
