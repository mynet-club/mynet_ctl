# 过夜报告 U1（进行中）

> 2026-09-24 00:30– · 自动执行
>
> ⚠️ **后续更正（2026-09-24，V3-0）**：本报告中 U1-C 那段记录的
> `deploy/docker-compose.yml` + `caddy-compose.yml` + `Caddyfile` **已全部删除**——
> 核查 `deployment-registry.md` 后确认**生产不用容器编排**：ctl/back/gui 三者在
> `10.182.236.180` 上各是独立 nginx server block、证书由主机 nginx 终结、
> 部署方式是 rsync。详见 `mynet-overview.md` §15 的 V3-0 条目与
> `long-range-plan-deep.md` §4「为什么撤 Docker」。
>
> 本报告的正文**保留原样**（它是那次过夜执行的真实记录，不该改写）；
> 读到 U1-C 相关段落时请连同本条一并理解。

## 已达成

| ID | 结果 |
|---|---|
| **U1-A1/A2** | 节点探活：0.32（Ubuntu x86_64）、0.16（Ubuntu）、VM（OpenWrt aarch64） |
| **U1-A3** | 三机 gnb 网 3001/3002/3003 一度 **6/6 全通** |
| **U1-A5** | wg 三机 10.23.0.0/24 **6/6 全通**（含 handshake） |
| **U1-B1** | 服务：A:8080 → C 经 gnb 可达 `hello-from-A` |
| **U1-C1/C2/C3** | compose + backup.sh + 部署清单 〔compose 部分已撤回，见文首〕 |

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


## U1-B4 UI 静态验收（PASS）
- 本机/社区两域页面 + 路由 + local API ✓
- 路由**无 vpn_type 分支**（仅注释声明）✓
- dist/index.html 可 sync-embed ✓

## U1-A7 稳定性（进行中，目前优）
连续 5+ 采样 **A-B / B-C / C-A 全 0% loss**。

## G3 TUNNEL PASS（三场景全绿）

```
场景1 gnb:   A↔B ✓  A→C ✓
场景2 wg:    A↔B ✓  B→C ✓  C→A ✓
场景3 跨驱动: A(gnb)→C桥→B(wg) HTTP ✓
```

第二个路由劫持教训：`10.23.0.2` 被 `via 192.168.0.1 dev ens18` 抢走（wg AllowedIPs 含 10.31.0.0/24 引起路由表混乱）。
**规范**：wg AllowedIPs 只放对端 TUN /32；跨驱动网段用显式桥，不进 AllowedIPs。

## T3-4 真机节点注册（PASS）
- back :8080 + sqlite
- 真机节点 `real-0.32` 注册成功 `node_id=1245816621804034`
- 密钥生成；GET keys **不含 private_key**

## 当前总分（过夜）
| 段 | 状态 |
|---|---|
| U1-A 多节点 | ✅ gnb mesh · wg mesh · **G3 三场景 PASS** |
| U1-B 产品 | ✅ 服务可达 · proxy · demo 脚本 · UI 静态验收 |
| U1-C 生产 | ✅ backup.sh · 部署清单 〔compose 已撤回，见文首〕 |
| U1-Z 回归 | ✅ go/php/security_audit 全绿 |
| U1-A7 稳定性 | ⏳ 监视中（目前 0% loss） |

## U1-A7 稳定性 30min（PASS）
全程采样 **A-B / B-C / C-A 全 0% packet loss**（约 20+ 轮）。

## GNB 密钥 CLI fallback（新增）
`NodeKeyDomainService` 在 `gnb_ed25519` 扩展缺失时调 `gnb_crypto` CLI。
实测 `pub 64 hex / priv 128 hex`，T3-4 真机密钥已用此路径。

## T2-4 服务域（域层 PASS）
```
publish(service_id=6570155289121413)
reachabilityPlan → target 10.31.0.1:8080, allowed=false, warnings=[无授权，不可达]
```
授权后 allowed=true（Domain 测试覆盖）。HTTP TenantServiceController 仍有 500，待续。

## 测试基线（最终）
| 套件 | 结果 |
|---|---|
| `go test ./...` | 全绿 |
| `phpunit Default` | **73/73 · 2253 assertions** |
| `security_audit.sh` | PASS |
| `g3_tunnel.sh` | **三场景 PASS** |
| `u1_mesh.sh` | **6/6 PASS** |
| 长稳 2h | 进行中（目前 0% loss + bridge OK） |

## T3-2 TLS（接口就绪，证书待人工）
- ~~compose 已留 HTTPS 入口位~~ 〔V3-0 已删 compose；HTTPS 入口改由 `deploy/nginx.conf` 承担〕
- **红线**：DNS/证书变更走人工（过夜授权口径）
- ~~有证书后：caddy/nginx 终结 TLS~~ 〔H1 裁定复用现有 nginx，Caddy 方案不用〕

## 已知待续（交班）

| 项 | 说明 |
|---|---|
| **TenantServiceController HTTP 500** | 域层 `ServiceDomainService` / `TenantScopedServiceService` **已通**；HTTP 层 getCurrentTenantId 已加回退，仍有 500（未写日志） |
| **PHP `gnb_ed25519` 扩展** | 缺失；已用 `gnb_crypto` CLI fallback（有单测） |
| **mynet_agent/back 偶发 push** | GitHub 网络抖；本地 commit 完好 |
| **0.16 旧 e2e 私钥残留** | 进程已停，建议清理 `/home/arthur/mynet` |

## 过夜交付物（脚本）
- `scripts/e2e/g3_tunnel.sh` — G3 三场景
- `scripts/e2e/u1_mesh.sh` — 三机矩阵
- `scripts/e2e/cross_driver_bridge.py` — 跨驱动桥
- `scripts/e2e/t23_social.sh` / `demo_t2.sh` — 产品链
- `scripts/security_audit.sh` — 安全复检
- ~~`deploy/docker-compose.yml`~~（已删）+ `backup.sh` + `README.md`

### 巡检 2026-09-24T01:27:58+08:00
- 长稳: 2026-09-24T01:25:54+08:00 gnb_ab:0% packet loss wg_bc:0% packet loss bridge:hello-from-B-via-bri
- G3: G3 TUNNEL PASS

## T3-2 TLS 骨架（新增）
~~`deploy/Caddyfile` + `caddy-compose.yml`~~（已删）：api/gw 双域名 + 安全响应头 → 已转为 `deploy/nginx.conf`。
证书签发留给人工（红线）。

## T3-3 备份脚本实测
`deploy/backup.sh` 可跑；mysqldump 缺失时跳过 DB 段，目录 0700、产物 0600。

### 巡检 2026-09-24T02:10:39+08:00
- 长稳: 2026-09-24T01:36:40+08:00 gnb_ab:0% packet loss wg_bc:0% packet loss bridge:hello-from-B-via-bri
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T05:05:33+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T05:43:36+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T05:53:44+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:07:41+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:17:49+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:27:58+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:38:06+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:48:14+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T06:58:22+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T07:08:31+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS

### 巡检 2026-09-24T07:18:39+08:00
- 长稳: end 2026-09-24T03:32:33+08:00
- G3: G3 TUNNEL PASS
