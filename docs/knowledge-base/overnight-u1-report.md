# 过夜报告 U1（进行中）

> 2026-09-24 00:30– · 自动执行

## 已达成

| ID | 结果 |
|---|---|
| **U1-A1/A2** | 节点探活：0.32（Ubuntu x86_64）、0.16（Ubuntu）、VM（OpenWrt aarch64） |
| **U1-A3** | 三机 gnb 网 3001/3002/3003 一度 **6/6 全通** |
| **U1-A5** | wg 三机 10.23.0.0/24 **6/6 全通**（含 handshake） |
| **U1-B1** | 服务：A:8080 → C 经 gnb 可达 `hello-from-A` |
| **U1-C1/C2/C3** | compose + backup.sh + 部署清单 |

## 关键发现

### 1. A↔B gnb 单向/不通（进行中）
- A↔C、B↔C 稳定通
- A↔B 显示 Direct P2P、shared_secret 一致、UDP 9202 直连通
- 但 TUN 层 ICMP 不通；计数显示 **B→A in/out 有包，A→B out=0**
- 0.16 曾有 **旧 e2e gnb 双实例**（gnb_tun_8675838），已停

### 2. 跨驱动桥要点（U1-A6）
- 不是「wg AllowedIPs 随便写」——**GNB route.conf 必须声明网段归属节点**
  - 如 `3003|10.23.0.0|255.255.255.0` 表示 wg 网经 3003
- wg 侧 **AllowedIPs 要含回程网段**，否则 cryptokey routing 丢包
- OpenWrt 无 iptables，用 **nft/fw4**

### 3. 安全发现
- 0.16 旧进程命令行含 **明文私钥**（mynet_e2e_node），属测试残留
- 已停进程；建议清理 `/home/arthur/mynet` 残试配置

### 4. 构建链
- Ubuntu glibc：Docker ubuntu:22.04 + build-essential
- OpenWrt：官方 SDK 24.10（见 build_openwrt.yml）
- OpenWrt 上无 pkill/nohup/tcpdump/iptables

## 待续
- [ ] A↔B gnb 根因（计数不对称）
- [ ] U1-A6 跨驱动桥（route.conf 归属 + nft + AllowedIPs）
- [ ] U1-A7 30min 稳定性（监视已挂）
- [ ] U1-B2 proxy · U1-B3 demo_t2

## U1-A6 结论（跨驱动桥）

**GNB `route.conf` 是「节点地址宣告」不是递归路由。**

| 尝试 | 结果 |
|---|---|
| OS `ip route 10.23.0.0/24 via C` | ❌ 且**会劫持 gnb 网**（A↔B 断）——**教训：跨驱动只加 /32，绝不动 /24 网段** |
| `route.conf` 写 `3003\|10.23.0.0\|255.255.255.0` | ❌ gnb 不把非 TUN IP 封装给该节点 |
| `route.conf` 写 `3003\|10.23.0.2\|255.255.255.255` | ❌ 同上 |
| **A(gnb)↔C ↔ B(wg) 两跳可达** | ✅ A→C gnb 通；C→B wg 通 |
| **A→B wg IP 一跳** | ❌ 需 gnb forward/NAT，纯 route.conf 不够 |

**结论**：F-3「一条 ip route」在 GNB 上需要额外机制（forward 节点 / SNAT）。
产品规则「智能同类型直连」与此一致——跨驱动互通应经**双驱动节点显式转发**，不是透明路由。

## A↔B 故障根因（已修复）

`ip route replace 10.31.0.0/24 via 10.23.0.3 dev wg0` **劫持了整个 gnb 网段**。
`ping -I gnb0` 一直通，是路由表被污染。
**教训**：跨驱动测试只用 /32 主机路由。

## 安全发现

0.16 上 `mynet_e2e_node` 进程命令行含**明文私钥**（9-22 测试残留）。已停进程，建议清理。

## U1-A6 最终方案（PASS）

**双驱动节点显式 TCP 桥**（Python 转发 10.31.0.3:9080 → 10.23.0.2:8080）：

```
A(gnb 10.31.0.1) --gnb--> C(10.31.0.3 + 10.23.0.3) --wg--> B(10.23.0.2:8080)
                          └── bridge_fwd.py :9080
curl http://10.31.0.3:9080/  →  hello-from-B-via-bridge
```

与产品规则一致：**跨驱动 = 双驱动节点显式转发**，不是透明路由。

OpenWrt 坑：Python `create_connection` 报 `unknown encoding: idna`，须用 `(ip, port)` 元组裸连。

## U1-B2 PASS
A 经 C 桥访问 B wg 侧多文件（`/p.txt` → `proxy-target-ok`）。

## U1-Z1 回归（全绿）

| 套件 | 结果 |
|---|---|
| `go test ./...` | 全绿 |
| `phpunit Default` | **72/72 · 2250 assertions** |
| `security_audit.sh` | **SECURITY AUDIT PASS** |

## U1 稳定性（进行中）
30min 监视 `/tmp/u1-stab.log`：A-B 0% loss（修复路由后）；B-C / C-A 大部分 0%。
