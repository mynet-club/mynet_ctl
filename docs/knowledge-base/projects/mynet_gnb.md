# mynet_gnb — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | C (纯 C99) / GNU Make |
| **上游** | gnbdev/opengnb (GitHub) |
| **版本** | v1.6.0.a |
| **角色** | GNB P2P VPN 核心（底层基础设施） |

## 核心能力
- P2P 内网穿透（无需公网 IP）
- NAT 穿越（UDP hole punching）
- Ed25519 数字签名（节点认证）
- TUN 接口（虚拟网卡）
- UPnP / NAT-PMP 端口映射
- 数据压缩 (zlib) + 加密 (XOR/ARC4)

## 产出二进制
```
gnb          主进程（VPN daemon）
gnb_ctl      控制工具
gnb_crypto   密钥生成
gnb_es       ES 服务（UPnP, NAT-PMP, LAN 发现）
```

## CI/CD（GitHub Actions）
- build_linux.yml — Linux + OpenWrt (9 架构)
- build_darwin.yml — macOS (Universal Binary)
- build_windows.yml — Windows (MinGW 交叉编译)
- build_bsd.yml — FreeBSD

## 源码同步
```bash
scripts/sync_source.sh   # 从 opengnb 同步
scripts/release.sh       # 构建发布
sources/history.ndjson   # 同步追溯记录
```

## 分发
由 mynet_ctl 通过 `/d/gnb/` 路径分发
