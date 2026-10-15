# 连通性排错手册 —— A–E 五步法

> **地位**：连通问题的**唯一排错手册**。客服、排障、Agent、skill 一律引本文件。
> **用法**：症状出现 → 按 A→E 顺序查，**不要跳步**；每步有「命令 / 判据 / 不过怎么办」。
> **配套**：`mynet route doctor`（机测子集）· skill `mynet-connectivity`（交互引导）·
> `node-connectivity-checklist.md`（速查表）· `spec/route-firewall-split.md`（L5/L6 分工）
>
> 实测来源：2026-09 五节点（180/181/182/183/188）· `archive/2026-09/evidence/20260930-subject/` ·
> `session-pitfalls-20260930.md` · `archive/2026-09/proxy-conf-cutover-20260930.md`

---

## 0. 开始前

### 0.1 适用症状

| 症状 | 从哪步开始 |
|---|---|
| ping 全不通 / 隧道起不来 | **A** |
| 有隧道但 ping 不通 / in-out 皆 0 | **B** 或 **C** |
| ping 通、SSH/HTTP 不通 | **E**（对等 zip）或 **D**（MTU/大包） |
| 单向通（A→B 通、B→A 不通） | **D+E**（回程路由/防火墙） |
| 走了物理网口/旁路 | **D**（`ip route get`） |
| 间歇通/丢大包 | **E** 对等配置 + MTU |
| 界面显示异常/启动失败 | 另见 Q2（pid 残留） |

### 0.2 先决条件

1. **先备份再动**生产节点（192.168.0.2、10.182.236.180 等）。
2. **不换密钥**（公钥已扩散全网）。
3. **不按名字杀进程**（pidfile + cmdline）。
4. 知道你在查**哪一对**节点：`本机 node_id` ↔ `对端 node_id`。
5. 有双方的 shell（或能让人代跑命令）。

### 0.3 一条命令拿现场

```bash
# 本机（有 MYNET_HOME 时）
export MYNET_HOME=/var/lib/mynet   # 按平台，见 spec/paths.md
mynet subject --json                # 主体：bin/conf/map/ctl
mynet route doctor --json           # 机测：路由/回程/计数/对等（N4）
```

没装新 CLI 时用 §2–§6 的手工命令。

### 0.4 五步总览

```text
A 软件与配置完整     二进制、node/address/route/security 齐且可解析
B 密钥正确交换       彼此持有对方公钥，shared/key512 一致
C 邻接/网络宣告      address.conf / route.conf 知道对方是谁、在哪
D 系统路由可达       ip route get 指向隧道口（不是 ens/物理网关）
E 防火墙放行+回程    UDP 入向 + 回程对称 + 对等项（zip/crypto）
```

**顺序不能乱**：A 不过查 B 是浪费时间；B 不过查 D 也是。
**每步记证据**（命令输出存文件），否则复盘对不上。

---

## 1. 步骤 A —— 软件与配置完整

### 1.1 检查什么

| 项 | gnb | wireguard |
|---|---|---|
| 二进制 | `gnb` `gnb_ctl` `gnb_es`（**同一构建**） | `wg` `wg-quick` |
| 主配置 | `node.conf` | `wg0.conf`（PrivateKey/Address/ListenPort） |
| 拓扑 | `route.conf` **必须存在**（缺了 gnb 直接退出） | `AllowedIPs` |
| 邻接 | `address.conf` | `Endpoint` |
| 密钥 | `security/<id>.private|.public` + `ed25519/` | conf 内 PrivateKey/Peer PublicKey |
| 接口 | `ip link show <ifname> UP` | `ip link show wg0 UP` |
| 进程 | `ps` 有 `gnb -c <conf>`（或 `--systemd`） | `wg show` |

### 1.2 命令（Unix）

```bash
# 主体定位（先做这个，别猜树）
mynet subject --json
# 或手工：
ps aux | grep '[g]nb'
# 取 cmdline 里的 -c <confDir> 作为活 conf —— 不认 find 扫到的其它树

CONF=<从 ps 抓到的 -c 路径>
ls -la "$CONF"/node.conf "$CONF"/route.conf "$CONF"/address.conf
ls -la "$CONF"/security/ "$CONF"/ed25519/
# 同构建配套（R2）
ls -la "$(dirname "$(ps ... | awk '{print $NF}')")"   # gnb_ctl 是否同目录
```

### 1.3 判据

| 判据 | 不过 → |
|---|---|
| 进程在跑，`-c` 目录里四类文件都在 | 文件缺哪类就补哪类（从备份/import，**不手搓**） |
| `gnb_ctl` 与 `gnb` 同目录 | `tools_missing`：装同套件，**禁止**捡别的树的 ctl |
| `node.conf` 可解析（无残缺段头） | `sscanf` 类问题：看 `gnb.log`；缺 `]` 会被静默吞 |
| `ifname` / `listen` 是生产约定值 | 渲染器写死问题 → `config pull` + Apply，别手改 driver |

### 1.4 平台差异

| 平台 | 活 conf 常见位 | 备注 |
|---|---|---|
| Linux 生产 | `/var/lib/mynet/driver/gnb/conf/<id>` | 私钥在 `security/` |
| OpenWrt | `/etc/mynet/driver/gnb/conf/<id>` | ifname 可能是 `gnb_tun_16` |
| macOS | `/opt/mynet/...`（默认）；旧树 `/var/lib/mynet`、`/usr/local/opt/mynet` 为 legacy | 以 ps 为准 |
| Windows | `C:\mynet\driver\gnb\conf\<id>` | `gnb.exe -c C:\mynet\...` |

### 1.5 常见 A 步病灶

| 症状 | 根因 | 修法 |
|---|---|---|
| `open ctl block error` | map 路径不对 / 异构建 ctl | `subject` 给 MapPath；ctl 必须同目录 |
| `miss route.conf` | 只写了 node.conf | 补 route.conf（哪怕只有本节点 /32） |
| `if[] opened` 失败 | ifname 冲突/权限 | 查 `ip link`；Windows 看 wintun |
| 起来立刻退 | 配置错时 gnb **exit(0)** | 看 `gnb.log` / journal，别看退出码 |
| 脚本找不到 gnb | 多 home 混 | `mynet tree doctor` |

---

## 2. 步骤 B —— 密钥正确交换

### 2.1 原理（三句话）

1. gnb 落盘私钥是 `clamp(SHA512(seed))`（**128 hex**），公钥 **64 hex**——不是 stdlib `seed‖pub`。
2. 彼此要在自己的 `ed25519/<peer_id>.public` 持有**对方**公钥。
3. `gnb_ctl -s` 的 `shared_secret` / `key512` **两端一致**才算交换完成。

### 2.2 命令

```bash
# 本机自身份
ls "$CONF"/security/
cat "$CONF"/security/*.public          # 64 hex
# 对端公钥是否在
ls "$CONF"/ed25519/
cat "$CONF"/ed25519/<peer_id>.public

# 共享密钥指纹（两端各跑，比对）
gnb_ctl -b <live map> -s | grep -E 'key512|shared_secret'
```

Windows / 无 gnb_ctl 时：对比 `ed25519/<peer>.public` 与对端自己 `security/*.public` 是否一字不差。

### 2.3 判据

| 判据 | 不过 → |
|---|---|
| 对端 pub = 本机 `ed25519/<peer>.public` | 重新 `keys sync`（**只传 public**，不换钥） |
| 两端 key512 一致 | 不一致 = 有一边私钥不是这一对 → 查五方比对 |
| `security/*.private` 0600 | 权限收紧 |

### 2.4 五方比对（密钥不对时）

```text
1) 本机生产原件   $CONF/security/*.private
2) mynetd 树      $MYNET_HOME/conf/**/nodes/<id>/keys/
3) 对端 ed25519/  <本节点id>.public
4) back           node_keys.public_key
5) bundle         peer_public_keys
```

**任两处 pub 不一致 → 以生产原件为准，只导入不生成。**

### 2.5 正确身份流程

```text
登录 → 取身份/节点信息 → 本地 keygen → 只上传 public → bundle/publish
```
不是「后端生成私钥下发」。实现：`mynet auth login` + `mynet node claim <id>`。

### 2.6 常见 B 步病灶

| 症状 | 根因 | 修法 |
|---|---|---|
| `NODE_PRIV_HEX 与 bundle public_key 不是一对` | 混树 | 从生产 security/ 拷到目标树，`cmp` 后再起 |
| 有 Direct 无数据 | key512 不一致 | 五方比对 |
| 改钥后对端全断 | 对端仍持旧公钥 | **恢复旧钥** + `config keys sync` |
| 编码看起来不对 | hex vs base64 | gnb 128/64 hex；wg base64 44；别混 |

---

## 3. 步骤 C —— 邻接 / 网络宣告

### 3.1 两层宣告（不可混）

| 文件 | 作用 | 格式 |
|---|---|---|
| `address.conf` | 对端**公网地址**（NAT 穿透） | `n\|<id>\|<host>\|<port>` 或 `i\|0\|<index>\|<port>` |
| `route.conf` | 谁拥有哪些 **VIP/网段**（地址归属） | `<id>\|<ip>\|<dotted mask>` |

`route.conf` **不是**递归路由表，**不装 OS 路由**（那是 D 步）。

### 3.2 命令

```bash
cat "$CONF"/address.conf
cat "$CONF"/route.conf

# gnb 视角的链路
gnb_ctl -b <live map> -s
# 看：
#   ipv4 Local / Direct Point to Point / InDirect / unknown
#   tun_ipv4
#   in / out 字节
#   wan_ipv4
```

### 3.3 判据

| 判据 | 不过 → |
|---|---|
| `address.conf` 两端都有对方行 | 只写一端 → 对端 InDirect |
| 形式是 `n\|uuid\|ip\|port` | `x\|` 或缺 host → 静态地址/PONG 全挂 |
| `route.conf` 有对方 VIP/32 | 缺 → 对方地址无归属 |
| 本节点 VIP 也在 route.conf | 缺 → 本地宣告不完整 |
| index 行 `i\|0\|host\|port` | 至少一个可达 index |

### 3.4 常见 C 步病灶

| 症状 | 根因 | 修法 |
|---|---|---|
| 一直 InDirect | 缺 address / index 不可达 | 两端都写；核对 index host:port |
| `address.conf` role 不对 | bundle 是旧快照 | `bundle/publish` 重建 envelope |
| route.conf 灌了几百条公网段 | 旧 L6 注入 | 剥 marker 段；改走 proxy.conf |
| `strings gnb.map \| grep nodeid` 恒 0 | nodeid 是 uint64 | 只信 `gnb_ctl -s` |

---

## 4. 步骤 D —— 系统路由可达

**一句话**：包必须**进隧道**。`ip route get` 的 dev 必须是隧道口。

### 4.1 命令

```bash
ip route get <对端 VIP>          # Linux/OpenWrt
route -n get <对端 VIP>          # macOS
# Windows: Find-NetRoute -RemoteIPAddress <vip>

# 全部 overlay 路由
ip route | grep 10.182.236       # 或你的 overlay 段
# macOS: netstat -rn -f inet | grep 10.182.236

# 对端反向（ssh 上去跑）
ip route get <本机 VIP>
```

### 4.2 判据

| 判据 | 含义 |
|---|---|
| `dev gnb_tun*` / `dev wg0` / `dev utun*` | ✅ 进隧道 |
| `via <物理网关> dev ens/en0` | ❌ 旁路/没装路由 |
| `via 172.26.0.1`（AWS）等 | ❌ 出了云内网，不是隧道 |
| 路由表里**根本没有**该 VIP | ❌ `apply --auto` 没装 `/32` |

### 4.3 修复

```bash
# 语义（spec/route-firewall-split.md §3.1）
mynet apply --active     # 只写 driver 投影
mynet apply --auto       # 只装 OS 路由/防火墙
mynet apply --active --auto   # 两者（service start 默认）

# 手工兜底（临时）
ip route replace <对端VIP>/32 dev <tun>
```

### 4.4 回程（单向通的主因）

本机通、对端回不来 = 回程没装。在**对端**执行 §4.1–4.3。

实测案例：
- 181 缺 `10.182.236.180/32`，ping 走 `en0→0.1→0.2→180`（旁路，能通但不算隧道）
- 188 `ip route get 10.182.236.181` → `via 172.26.0.1 dev ens5`（181 全网 ping 不进）

### 4.5 常见 D 步病灶

| 症状 | 根因 | 修法 |
|---|---|---|
| `apply` 后路由仍走物理口 | 只跑了 `--active` | `--auto` 才装 OS 路由 |
| 同机双实例 NAT 互抢 | 共享路由表 | netns 或真双机 |
| 路由指向物理网关 | 学来的路由覆盖 | 钉 `${gw}/32` 回 WAN 口 |
| macOS 路由在 utun 但 ping 丢 | 对端回程/防火墙 | 转 E 步 |

---

## 5. 步骤 E —— 防火墙放行 + 回程 + 对等

### 5.1 三层放行（缺一不可）

```text
云安全组 / Lightsail   UDP <listen port> 入站
机内 nft/iptables      INPUT udp dport <listen>
FORWARD（若做桥/NAT）  forward + MASQUERADE
```

**只开云侧不够**：Debian 常见 `nft input policy drop` 只放 22/80/443。
**只开机内也不够**：Lightsail 默认丢 UDP。

### 5.2 命令

```bash
# 机内
sudo nft list ruleset | head -80
sudo iptables -L INPUT -n
# OpenWrt
fw4 print
nft list ruleset

# 云侧（Lightsail 示例）
aws --profile <p> lightsail get-instance --instance-name <name>
# 开 UDP：open-instance-public-ports --port-info fromPort=9016,toPort=9016,protocol=udp
```

### 5.3 对等配置（zip / crypto / passcode）

**症状特征**：小 ping（≤~288B）通、TCP 握手通，但 SSH banner / HTTP 全超时。

```bash
grep -E 'zip|crypto|passcode' "$CONF"/node.conf
mynet config parity --json
```

| 键 | 级别 | 要求 |
|---|---|---|
| `zip` / `zip-level` | **critical** | 两端同为 `zip auto` + `zip-level -1` |
| `crypto` | **critical** | 两端一致 |
| `passcode` | **critical** | 启用则两端相同 |
| `safe-index` / `multi-socket` | 建议 | 行为相关 |

⚠️ **云端 bundle 不下发 zip/crypto**——不能假设 pull 下来就是全的，要 parity 校验。

### 5.4 大包二分（zip/MTU 快速判据）

```bash
for s in 50 200 280 290 400 1400; do ping -c 1 -s $s -t 2 <ip>; done
```

- 288 附近突然丢 → **zip 对等**问题
- 全尺寸丢但小包通 → MTU/路径
- 全丢 → 回 D 步或防火墙

### 5.5 回程对称清单

- [ ] 对端 `ip route get <本机VIP>` → 隧道口
- [ ] 对端 route.conf 有本机网段
- [ ] FORWARD/conntrack 允许回包
- [ ] NAT 方向：旁路由 VPN→LAN 需 SNAT；VPN↔VPN 不 NAT

### 5.6 常见 E 步病灶

| 症状 | 根因 | 修法 |
|---|---|---|
| tcpdump 0 包 | 云侧丢 UDP | 开安全组 |
| Lightsail 开了仍不通 | 机内 nft drop | `nft insert rule ... udp dport X accept` |
| ping 通 SSH 不通 | zip 不一致 | 两端 `zip auto` |
| 单向通 | 回程路由/防火墙 | 对端补 `/32` + FORWARD |
| 代理出口被打环 | 出口 IP 在 CNIP 大段 | `exclude_ips` / `PROXY_PUBLIC_IPS` |
| Windows 全网 ping 不进 | 入站/回程 | 放行 ICMP + 隧道 UDP + 回程路由 |

---

## 6. 计数诊断法（进阶）

`gnb_ctl -s` 的 in/out 是关键证据：

| 本机 in/out | 对端 in/out | 含义 |
|---|---|---|
| in↑ out↑ | in↑ out↑ | 通 |
| in↑ out0 | out↑ in0 | **封装单向**（发出对端收不到进 tun） |
| 0 / 0 | 0 / 0 | **TUN 根本没进**（路由/防火墙） |
| 只有几十字节 | 同 | 心跳/keepalive，**数据面没跑起来** |
| Direct 但 0/0 | — | 「Direct ≠ 数据通」 |

---

## 7. 平台速查

| 环境 | 隧道口 | 查路由 | 杀进程 | 备注 |
|---|---|---|---|---|
| Linux | `gnb_tun*` / `wg0` | `ip route get` | pidfile + cmdline | 机内 nft 或 iptables |
| OpenWrt | `gnb_tun_16` 等 | `ip route get` | procd / pidfile | **fw4/nft**，无 iptables legacy |
| macOS | `utunN` | `route -n get` | pidfile | `stat -f`；`sed -i` 不同 |
| Windows | wintun | `Find-NetRoute` | 服务管理器 | `.exe`；`powershell -NoProfile -File` |

SSH 排障注意：
- 180 走公网 `52.221.22.241`（VPN IP 会超时）
- 188 `ssh -i Aws.pem admin@18.141.199.14`
- 184 等跨机可 `ProxyJump=root@192.168.0.2`
- 长脚本用 `scp` 落远程再跑，别嵌 heredoc

---

## 8. 决策树（一张图）

```text
起不来？ ──A── 配置/二进制/同构建
   │
在跑但 ping 不通？
   │
   ├─ in/out 全 0？ ──D── ip route get 是不是隧道口？
   │                        └─ 是 → E 防火墙/对端回程
   ├─ in↑out↑ 对端 0？ ──B── key512 / ed25519 一致？
   ├─ InDirect？ ──C── address.conf / index
   └─ 小包通大包丢？ ──E── zip parity / MTU

单向通？ ──D+E── 对端 ip route get + FORWARD + 回程 route.conf
```

---

## 9. 机测与人工分工

| 工具 | 干什么 | 不干什么 |
|---|---|---|
| `mynet route doctor` | 跑 §4–§5 的可机测项，出矩阵/JSON | 不解读根因、不改配置 |
| skill `mynet-connectivity` | 按症状引导选步、调命令、解释结果 | 不替代本手册条文 |
| 本手册 | 条文、判据、平台差异、病灶表 | — |

**推荐流程**：`route doctor --json` → 看哪对哪步红 → 回本手册对应节 → 修 → 再 doctor。

---

## 10. 一句话速查

| 问题 | 先查 |
|---|---|
| 隧道不通 | 本手册 A→E，不跳步 |
| 密钥不对 | §2.4 五方比对 |
| 公钥没同步 | `MYNET_GATEWAYS` / WS 起没起 |
| 配置被覆盖 | 渲染器写死？bundle 旧修订？只改 `conf/**` |
| 路由走错口 | §4 `ip route get` + `apply --auto` |
| 端口不通 | 云 **和** 机内两层 |
| ping 通 SSH 不通 | §5.3 zip parity |
| status 不对 | 心跳链，不是直连 DB |
| 脚本找不到主体 | `mynet subject` / `mynet tree doctor` |

---

**关联**：`spec/route-firewall-split.md` · `spec/paths.md` ·
`session-pitfalls-20260930.md` · `node-connectivity-checklist.md`（速查）·
`archive/2026-09/core-remediation-plan-20260930.md` 主线③
