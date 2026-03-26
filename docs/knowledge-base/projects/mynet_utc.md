# mynet_utc — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | C (C99 + POSIX) / GNU Make |
| **版本** | v1.3.1 |
| **角色** | UDP→TCP 隧道转换器 + 加速器 |

## 核心能力
- UDP↔TCP 双向透明转发
- 绕过 ISP UDP QoS 限速
- 17-20 Gbps 吞吐量（8 核）
- 10,000+ 并发 TCP 连接
- io_uring 异步 I/O（Linux 5.1+）
- 零 malloc 热路径
- 协议插件系统（GNB + WireGuard）

## 部署模式
| 模式 | 端口 | 带宽 |
|------|------|------|
| Normal | 8192 | 5-100 Mbps |
| Torrent | 8193 | >100 Mbps |

## 架构
```
src/
├── core/        会话管理、服务器核心、插件系统
├── protocol/    协议检测与解析
├── network/     事件循环、socket、io_uring
├── forward/     转发引擎
├── security/    DDoS 防护、黑名单
└── utils/       配置解析、日志、内存
plugins/
├── gnb/         GNB 协议插件
└── wireguard/   WireGuard 协议插件
```

## 配置（INI 格式）
```ini
# server.ini
[server]
listen_port = 8192
mode = relay
plugins = gnb
max_connections = 10240
workers = 0  # 0=auto CPU cores

[plugin.gnb]
enabled = yes
forward_addr = 127.0.0.1
forward_port = 9621
```

## 平台
macOS (x86_64), Linux (x86_64), OpenWrt (x86_64)

## 分发
由 mynet_ctl 通过 `/d/utc/` 路径分发
