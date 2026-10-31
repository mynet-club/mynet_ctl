#!/usr/bin/env python3
"""生成 scripts/tips-kb/tips-kb.json（蒸馏产物）。

蒸馏源是知识库 md（SSOT）；本脚本是半自动蒸馏的落点（按 spec §4.3 切节、改写、脱敏后的正文）。
手改 tips-kb.json 不作为源——改这里（或改源 md 后重蒸），重跑本脚本整体替换产物。
用法：python3 scripts/tips-kb/build_tips_kb.py
契约：docs/knowledge-base/spec/tips-kb-contract.md + spec/schemas/tips-kb.schema.json
"""

from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

OUT = Path(__file__).resolve().parent / "tips-kb.json"
VERSION = "v20261030.1"

SRC_TC = "mynet_ctl/docs/knowledge-base/troubleshoot-connectivity.md"
SRC_NC = "mynet_ctl/docs/knowledge-base/node-connectivity-checklist.md"
SRC_HELP = "mynet_gui/docs/help/"


def c(id: str, title: str, tags: list[str], body: str, file: str, section: str) -> dict:
    return {
        "id": id,
        "title": title,
        "tags": tags,
        "body": body.strip() + "\n",
        "source": {"file": file, "section": section},
    }


CHUNKS: list[dict] = [
    c(
        "tc-overview",
        "连通排错五步总览（A→E 不跳步）",
        ["connectivity", "troubleshoot", "overview"],
        r"""连通性排错按 A→E 顺序查，**不要跳步**（A 不过查 B 是浪费时间）。每步记证据（命令输出存文件），否则复盘对不上。

**五步总览**

| 步 | 内容 |
|---|---|
| A | 软件与配置完整：二进制、node/address/route/security 齐且可解析 |
| B | 密钥正确交换：彼此持有对方公钥，shared/key512 一致 |
| C | 邻接/网络宣告：address.conf / route.conf 知道对方是谁、在哪 |
| D | 系统路由可达：`ip route get` 指向隧道口（不是物理网关） |
| E | 防火墙放行 + 回程 + 对等：UDP 入向 + 回程对称 + 对等项（zip/crypto） |

**按症状选起点**

| 症状 | 从哪步开始 |
|---|---|
| ping 全不通 / 隧道起不来 | A |
| 有隧道但 ping 不通 / in-out 皆 0 | B 或 C |
| ping 通、SSH/HTTP 不通 | E（对等 zip）或 D（MTU/大包） |
| 单向通（A→B 通、B→A 不通） | D+E（回程路由/防火墙） |
| 走了物理网口/旁路 | D（`ip route get`） |
| 间歇通/丢大包 | E 对等配置 + MTU |
| 界面显示异常/启动失败 | 见「停止后显示启动失败」条目 |""",
        SRC_TC,
        "0. 开始前（0.1 适用症状 + 0.4 五步总览）",
    ),
    c(
        "tc-collect-site",
        "排障前先决条件与一条命令拿现场",
        ["connectivity", "troubleshoot", "collect"],
        r"""**先决条件**：① 先备份再动生产节点；② **不换密钥**（公钥已扩散全网）；③ 不按名字杀进程（用 pidfile + cmdline）；④ 明确在查哪一对节点（本机 node_id ↔ 对端 node_id）；⑤ 有双方 shell 或能让人代跑命令。

**一条命令拿现场**

```bash
export MYNET_HOME=/var/lib/mynet   # 按平台；macOS 默认 /opt/mynet
mynet subject --json               # 主体：bin/conf/map/ctl
mynet route doctor --json          # 机测：路由/回程/计数/对等
```

没装新 CLI 时，用各步条目里的手工命令。""",
        SRC_TC,
        "0.2 先决条件 + 0.3 一条命令拿现场",
    ),
    c(
        "tc-step-a-config",
        "步骤 A：软件与配置完整",
        ["connectivity", "troubleshoot", "config"],
        r"""**检查什么**（gnb / wireguard）

| 项 | gnb | wireguard |
|---|---|---|
| 二进制 | `gnb` `gnb_ctl` `gnb_es`（**同一构建**） | `wg` `wg-quick` |
| 主配置 | `node.conf` | `wg0.conf`（PrivateKey/Address/ListenPort） |
| 拓扑 | `route.conf` **必须存在**（缺了 gnb 直接退出） | `AllowedIPs` |
| 邻接 | `address.conf` | `Endpoint` |
| 密钥 | `security/<id>.private|.public` + `ed25519/` | conf 内 PrivateKey/Peer PublicKey |
| 接口 | `ip link show <ifname> UP` | `ip link show wg0 UP` |
| 进程 | `ps` 有 `gnb -c <conf>` | `wg show` |

**命令（Unix）**

```bash
mynet subject --json        # 主体定位，别猜树
ps aux | grep '[g]nb'       # 取 cmdline 里的 -c <confDir> 作为活 conf
CONF=<从 ps 抓到的 -c 路径>
ls -la "$CONF"/node.conf "$CONF"/route.conf "$CONF"/address.conf
ls -la "$CONF"/security/ "$CONF"/ed25519/
```

**判据**：进程在跑且 `-c` 目录四类文件都在（缺哪类从备份/import 补，**不手搓**）；`gnb_ctl` 与 `gnb` 同目录（缺=装同套件，**禁止**捡别的树的 ctl）；`node.conf` 可解析（缺 `]` 会被静默吞，看 `gnb.log`）；`ifname`/`listen` 是生产约定值（不对走 `config pull` + Apply，别手改 driver）。

**平台常见活 conf 位**：Linux `/var/lib/mynet/driver/gnb/conf/<id>`；OpenWrt `/etc/mynet/driver/gnb/conf/<id>`（ifname 可能 `gnb_tun_16`）；macOS `/opt/mynet/...`（旧树为 legacy，以 ps 为准）；Windows `C:\mynet\driver\gnb\conf\<id>`。

**常见病灶**：`open ctl block error` → map 路径不对/异构建 ctl；`miss route.conf` → 补 route.conf（哪怕只有本节点 /32）；`if[] opened` 失败 → ifname 冲突/权限（Windows 看 wintun）；起来立刻退 → 配置错时 gnb **exit(0)**，看 `gnb.log` 不看退出码；脚本找不到 gnb → 多 home 混，`mynet tree doctor`。""",
        SRC_TC,
        "1. 步骤 A —— 软件与配置完整",
    ),
    c(
        "tc-step-b-keys",
        "步骤 B：密钥正确交换",
        ["connectivity", "troubleshoot", "keys"],
        r"""**原理（三句话）**：① gnb 落盘私钥是 `clamp(SHA512(seed))`（**128 hex**），公钥 **64 hex**——不是 stdlib `seed‖pub`；② 彼此要在自己的 `ed25519/<peer_id>.public` 持有**对方**公钥；③ `gnb_ctl -s` 的 `shared_secret`/`key512` **两端一致**才算交换完成。

**命令**

```bash
ls "$CONF"/security/ && cat "$CONF"/security/*.public       # 本机公钥 64 hex
ls "$CONF"/ed25519/ && cat "$CONF"/ed25519/<peer_id>.public # 对端公钥
gnb_ctl -b <live map> -s | grep -E 'key512|shared_secret'   # 两端各跑，比对
```

Windows / 无 gnb_ctl 时：对比 `ed25519/<peer>.public` 与对端自己 `security/*.public` 是否一字不差。

**判据**：对端 pub = 本机 `ed25519/<peer>.public`（不等 → 重新 `keys sync`，**只传 public 不换钥**）；两端 key512 一致；`security/*.private` 权限 0600。

**五方比对**（密钥不对时）：① 本机生产原件 `$CONF/security/*.private`；② mynetd 树 `$MYNET_HOME/conf/**/nodes/<id>/keys/`；③ 对端 `ed25519/<本节点id>.public`；④ 后端 `node_keys.public_key`；⑤ bundle `peer_public_keys`。任两处 pub 不一致 → **以生产原件为准，只导入不生成**。

**正确身份流程**：登录 → 取身份/节点信息 → 本地 keygen → **只上传 public** → bundle/publish（不是「后端生成私钥下发」）。

**常见病灶**：「私钥与 public_key 不是一对」→ 混树，从生产 security/ 拷到目标树 `cmp` 后再起；有 Direct 无数据 → key512 不一致，做五方比对；改钥后对端全断 → 对端仍持旧公钥，**恢复旧钥**再 `config keys sync`；编码疑问 → gnb 128/64 hex、wg base64 44，别混。""",
        SRC_TC,
        "2. 步骤 B —— 密钥正确交换",
    ),
    c(
        "tc-step-c-adjacency",
        "步骤 C：邻接 / 网络宣告",
        ["connectivity", "troubleshoot", "adjacency"],
        r"""**两层宣告（不可混）**

| 文件 | 作用 | 格式 |
|---|---|---|
| `address.conf` | 对端**公网地址**（NAT 穿透） | `n\|<id>\|<host>\|<port>` 或 `i\|0\|<index>\|<port>` |
| `route.conf` | 谁拥有哪些 **VIP/网段**（地址归属） | `<id>\|<ip>\|<dotted mask>` |

`route.conf` **不是**递归路由表，**不装 OS 路由**（那是 D 步）。

**命令**

```bash
cat "$CONF"/address.conf "$CONF"/route.conf
gnb_ctl -b <live map> -s
# 看：ipv4 Local / Direct Point to Point / InDirect / unknown
#     tun_ipv4、in/out 字节、wan_ipv4
```

**判据**：address.conf 两端都有对方行（只写一端 → 对端 InDirect）；形式是 `n|uuid|ip|port`（`x|` 或缺 host → 静态地址/PONG 全挂）；route.conf 有对方 VIP/32 且本节点 VIP 也在；至少一个可达 index 行 `i|0|host|port`。

**常见病灶**：一直 InDirect → 缺 address 或 index 不可达，两端都写并核对 index host:port；address.conf role 不对 → bundle 是旧快照，`bundle/publish` 重建 envelope；route.conf 灌了几百条公网段 → 旧 L6 注入，剥 marker 段改走 proxy.conf；`strings gnb.map | grep nodeid` 恒 0 → nodeid 是 uint64，只信 `gnb_ctl -s`。""",
        SRC_TC,
        "3. 步骤 C —— 邻接 / 网络宣告",
    ),
    c(
        "tc-step-d-routing",
        "步骤 D：系统路由可达（含回程）",
        ["connectivity", "troubleshoot", "routing"],
        r"""**一句话**：包必须**进隧道**。`ip route get` 的 dev 必须是隧道口。

**命令**

```bash
ip route get <对端VIP>              # Linux/OpenWrt
route -n get <对端VIP>              # macOS
# Windows: Find-NetRoute -RemoteIPAddress <vip>
ip route | grep <overlay 段>        # 全部 overlay 路由
# 对端反向（ssh 上去跑）：ip route get <本机VIP>
```

**判据**：`dev gnb_tun*` / `dev wg0` / `dev utun*` = ✅ 进隧道；`via <物理网关> dev ens/en0` = ❌ 旁路/没装路由；`via <云内网关>` = ❌ 出了云内网；路由表里**根本没有**该 VIP = ❌ `apply --auto` 没装 /32。

**修复**

```bash
mynet apply --active           # 只写 driver 投影
mynet apply --auto             # 只装 OS 路由/防火墙
mynet apply --active --auto    # 两者（service start 默认）
ip route replace <对端VIP>/32 dev <tun>   # 手工兜底（临时）
```

**回程（单向通主因）**：本机通、对端回不来 = 回程没装。在**对端**重复本步的检查与修复。

**常见病灶**：apply 后路由仍走物理口 → 只跑了 `--active`，`--auto` 才装 OS 路由；同机双实例 NAT 互抢 → netns 或真双机；路由指向物理网关 → 学来的路由覆盖，钉 `${gw}/32` 回 WAN 口；macOS 路由在 utun 但 ping 丢 → 转 E 步。""",
        SRC_TC,
        "4. 步骤 D —— 系统路由可达",
    ),
    c(
        "tc-step-e-firewall",
        "步骤 E（上）：三层防火墙放行",
        ["connectivity", "troubleshoot", "firewall"],
        r"""**三层放行（缺一不可）**

```text
云安全组 / Lightsail   UDP <listen port> 入站
机内 nft/iptables      INPUT udp dport <listen>
FORWARD（若做桥/NAT）  forward + MASQUERADE
```

**只开云侧不够**：Debian 常见 `nft input policy drop` 只放 22/80/443。**只开机内也不够**：云主机默认丢 UDP。

**命令**

```bash
sudo nft list ruleset | head -80
sudo iptables -L INPUT -n
# OpenWrt
fw4 print
# 云侧（Lightsail 示例）
aws lightsail get-instance --instance-name <name>
aws lightsail open-instance-public-ports \
  --port-info fromPort=<port>,toPort=<port>,protocol=udp
```""",
        SRC_TC,
        "5.1 三层放行 + 5.2 命令",
    ),
    c(
        "tc-peering-zip",
        "步骤 E（下）：对等配置 zip/crypto 与大包二分",
        ["connectivity", "troubleshoot", "zip", "peering"],
        r"""**症状特征**：小 ping（≤~288B）通、TCP 握手通，但 SSH banner / HTTP 全超时。

```bash
grep -E 'zip|crypto|passcode' "$CONF"/node.conf
mynet config parity --json
```

**对等项要求**

| 键 | 级别 | 要求 |
|---|---|---|
| `zip` / `zip-level` | **critical** | 两端同为 `zip auto` + `zip-level -1` |
| `crypto` | **critical** | 两端一致 |
| `passcode` | **critical** | 启用则两端相同 |
| `safe-index` / `multi-socket` | 建议 | 行为相关 |

⚠️ **云端 bundle 不下发 zip/crypto**——不能假设 pull 下来就是全的，要 parity 校验。

**大包二分（zip/MTU 快速判据）**

```bash
for s in 50 200 280 290 400 1400; do ping -c 1 -s $s -t 2 <ip>; done
```

- 288 附近突然丢 → **zip 对等**问题
- 全尺寸丢但小包通 → MTU/路径
- 全丢 → 回 D 步或防火墙

**回程对称清单**：① 对端 `ip route get <本机VIP>` → 隧道口；② 对端 route.conf 有本机网段；③ FORWARD/conntrack 允许回包；④ NAT 方向：旁路由 VPN→LAN 需 SNAT，VPN↔VPN 不 NAT。

**常见病灶**：tcpdump 0 包 → 云侧丢 UDP，开安全组；云侧开了仍不通 → 机内 nft drop，加 `udp dport` accept；ping 通 SSH 不通 → 两端 `zip auto`；单向通 → 对端补 /32 + FORWARD；Windows 全网 ping 不进 → 放行 ICMP + 隧道 UDP + 回程路由。""",
        SRC_TC,
        "5.3 对等配置 + 5.4 大包二分 + 5.5 回程对称 + 5.6 病灶",
    ),
    c(
        "tc-count-diagnosis",
        "计数诊断法：in/out 看数据面",
        ["connectivity", "troubleshoot", "diagnostics"],
        r"""`gnb_ctl -s` 的 in/out 是关键证据：

| 本机 in/out | 对端 in/out | 含义 |
|---|---|---|
| in↑ out↑ | in↑ out↑ | 通 |
| in↑ out0 | out↑ in0 | **封装单向**（发出对端收不到进 tun） |
| 0 / 0 | 0 / 0 | **TUN 根本没进**（路由/防火墙） |
| 只有几十字节 | 同 | 心跳/keepalive，**数据面没跑起来** |
| Direct 但 0/0 | — | 「Direct ≠ 数据通」 |

**密钥一致 + Direct P2P ≠ 数据通**：仍要查 D/E。""",
        SRC_TC,
        "6. 计数诊断法（进阶）",
    ),
    c(
        "tc-platform-quickref",
        "平台速查 + 一句话速查",
        ["connectivity", "troubleshoot", "platform"],
        r"""**平台速查**

| 环境 | 隧道口 | 查路由 | 杀进程 | 备注 |
|---|---|---|---|---|
| Linux | `gnb_tun*` / `wg0` | `ip route get` | pidfile + cmdline | 机内 nft 或 iptables |
| OpenWrt | `gnb_tun_16` 等 | `ip route get` | procd / pidfile | **fw4/nft**，无 iptables legacy |
| macOS | `utunN` | `route -n get` | pidfile | `stat -f`；`sed -i` 不同 |
| Windows | wintun | `Find-NetRoute` | 服务管理器 | `.exe`；`powershell -NoProfile -File` |

**一句话速查**

| 问题 | 先查 |
|---|---|
| 隧道不通 | A→E，不跳步 |
| 密钥不对 | 五方比对（见密钥交换条目） |
| 配置被覆盖 | 渲染器写死？bundle 旧修订？只改 `conf/**` |
| 路由走错口 | `ip route get` + `apply --auto` |
| 端口不通 | 云**和**机内两层 |
| ping 通 SSH 不通 | 对等 zip parity |
| 脚本找不到主体 | `mynet subject` / `mynet tree doctor` |""",
        SRC_TC,
        "7. 平台速查 + 10. 一句话速查",
    ),
    c(
        "tc-decision-tree",
        "连通排障决策树（一张图）",
        ["connectivity", "troubleshoot", "decision-tree"],
        r"""```text
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

配合「计数诊断法」条目使用。""",
        SRC_TC,
        "8. 决策树（一张图）",
    ),
    c(
        "nc-check-model",
        "节点连通检查模型与快速排查清单",
        ["connectivity", "checklist", "model"],
        r"""**五步检查模型**（与 A–E 排错法同构）

| 步 | 检查 | 失败症状 |
|---|---|---|
| A 软件+配置 | 二进制、配置文件齐全可解析 | 进程起不来 / `miss route.conf` / `if[] opened` 失败 |
| B 密钥交换 | 彼此持有对方公钥，key512 一致 | 有 Direct 无数据 |
| C 邻接宣告 | address.conf 知道对方在哪；route.conf 宣告地址归属 | 显示 InDirect；对端 ping 无 in/out |
| D 系统路由 | `ip route get` 指向隧道口 | 路由被劫持/旁路 |
| E 防火墙+回程 | UDP 入向 + 回程路由/策略对称 | 单向通；握手有 ping 不通 |

**快速排查清单（printable）**

```text
[ ] A  gnb -c <conf> 起得来；node.conf address.conf route.conf security/ ed25519/ 齐
[ ] A  wg show / ip link show wg0 UP
[ ] B  ed25519/<peer>.public 存在且 64 hex；两端 key512 一致
[ ] C  address.conf 有 n|peer|ip|port；route.conf 有 peer|ip|mask
[ ] C  wg AllowedIPs = 对端 TUN /32（不塞网段）
[ ] D  ip route get <dst> → dev gnb0 / wg0（不是物理网卡/默认网关）
[ ] E  UDP listen port 入向放行；FORWARD/nft 若跨桥
[ ] E  回程：对端 route.conf / AllowedIPs / OS 路由对称
[ ]   gnb_ctl -s 看 in/out；wg show transfer / latest-handshake
```

**关键教训**：跨驱动/跨机路由**只加 /32**，绝不动对方在用的网段（否则路由劫持整网）；wg AllowedIPs 只放对端 TUN /32；同机多实例是**设计特性**（独立 ifname + listen），排查只看是否撞车，别见双进程就杀。""",
        SRC_NC,
        "2. 节点连通性检查模型 + 3. 快速排查清单",
    ),
    c(
        "nc-q1-large-packet",
        "Q1 ping 通但 SSH/HTTP 不通（zip 不一致）",
        ["connectivity", "qa", "zip"],
        r"""**症状**：`ping <对端overlay>` 正常（小包）、`nc -z <ip> 22` 或 TCP 握手成功，但 `ssh` 卡在 banner、`curl http://` 超时。

**判据（三步）**

```bash
# ① 二分包长：>~288B payload 即失败 → 高度疑似对等配置
for s in 50 200 280 290 400 1400; do ping -c 1 -s $s -t 2 <ip>; done
# ② 对照本机 node.conf 是否有 zip（与对端一致）
grep -E 'zip|crypto' $MYNET_HOME/driver/gnb/conf/<nodeId>/node.conf
# ③ 对等检查
mynet config parity --json
```

**根因**：**对等配置（尤其 `zip`）不一致**。对端 `zip auto` 会压帧，本机未开 zip 则大包解不开 → **静默丢弃**（不是报错）。

**处置**：① `node.conf` 两端对齐 `zip auto` + `zip-level -1`；② 重启 gnb（`mynet service restart gnb`）；③ 用 `ping -s 1400` 与 SSH 验证。

**易误判**：以为是 gnb 二进制回归 / 系统坏了 / MTU——先 diff 历史可用 `node.conf`；以为云端 bundle 会带 zip——**bundle.gnb.options 不含 zip/crypto**。""",
        SRC_NC,
        "6. Q&A —— Q1",
    ),
    c(
        "nc-q2-stop-failed",
        "Q2 停止后显示「异常 / 启动失败」",
        ["connectivity", "qa", "lifecycle"],
        r"""**症状**：手动停止后状态不是「已停止」，而是「异常」「gnb 已退出」。

**判据**：`gnb.pid` 是否仍在；进程是否已退。

**根因**：Stop 未删 pid 文件 → 状态机判「pid 在、进程没了」= FAILED。

**处置**：新版 `Stop()` 成功后会删除 `gnb.pid`，状态应为 `STOPPED/已停止`；若仍见 pid 残留，手动删 pid 或查 gnb 是否异常退出（`/var/log/mynet/gnb.err`）。""",
        SRC_NC,
        "6. Q&A —— Q2",
    ),
    c(
        "nc-q3-old-pubkey",
        "Q3 对端还用旧公钥，换新密钥后连不上",
        ["connectivity", "qa", "keys"],
        r"""**场景**：其它节点已交换过本节点公钥，且**不会自动更新** public；换新密钥后连不上。

**处置**：
- **不要**「生成并同步新密钥」
- 用 **「仅同步现有公钥」**（`mynet config keys sync`）：不换身份，只登记公钥
- 若已换错：从备份恢复旧密钥对，再 `keys sync`

**自检**：`mynet config keys --json` 的 `public_hex` 应与对端持有的公钥一致（可用 `config parity` / 云端 bundle 的 `peer_public_keys` 对照）。""",
        SRC_NC,
        "6. Q&A —— Q3",
    ),
    c(
        "nc-q4-restore-backup",
        "Q4 恢复备份 / 手动导入后通不了",
        ["connectivity", "qa", "backup"],
        r"""**处置顺序**：
1. `mynet config parity` —— 看 zip/crypto/passcode 是否与对端一致
2. critical 不一致 → 改 `node.conf` 对齐后再 `service restart`
3. 仍不通 → 按「ping 通 SSH 不通」条目做包长二分

```bash
mynet config import <file.tar.gz>   # 或省略文件名用 backups/ 最新（会先自动快照现状）
mynet config parity
```""",
        SRC_NC,
        "6. Q&A —— Q4",
    ),
    c(
        "nc-q5-peering-items",
        "Q5 对等配置包含哪些项",
        ["connectivity", "qa", "peering"],
        r"""| 键 | 级别 | 说明 |
|---|---|---|
| `zip` / `zip-level` | **critical** | 压缩帧格式，必须一致 |
| `crypto` | **critical** | 数据面加密算法（默认 xor） |
| `passcode` | **critical** | 启用则两端相同；都无则一致 |
| `safe-index` | 建议 | gnb 默认 off |
| `multi-socket` / `unified-forwarding` | 建议 | 行为相关 |

**工具**：`mynet config parity --json`。找一份实测可用的对端基线配置作参照（工作对端的最佳）。""",
        SRC_NC,
        "6. Q&A —— Q5",
    ),
    c(
        "nc-q6-commands",
        "排障命令速查",
        ["connectivity", "qa", "commands"],
        r"""```bash
# 状态
curl -s localhost:8096/api/v1/node-status | python3 -m json.tool
# 对等
mynet config parity --json
# 运行日志（或 WebUI 诊断 → 日志）
curl -s 'localhost:8096/api/v1/logs?lines=40'
# 包长二分
for s in 50 288 290 1400; do ping -c 1 -s $s -t 2 <ip>; done
# 停止是否干净
mynet service stop gnb && ls $MYNET_HOME/driver/gnb/conf/<id>/gnb.pid
```""",
        SRC_NC,
        "6. Q&A —— Q6 排障命令速查",
    ),
    c(
        "nc-product-rules",
        "产品规则与两层路由的对应",
        ["connectivity", "checklist", "rules"],
        r"""| 产品规则 | 落在哪一步 |
|---|---|
| 智能同类型直连 | C：无共同驱动不建连 |
| 一节点一网卡 | A/D：一张 TUN；跨驱动靠双驱动节点显式转发 |
| 跨驱动 = L6 桥 | D/E：显式转发（TCP 桥 / SNAT），不是透明路由 |
| 编码与布局正交 | B：hex 落库 ↔ base64 写 wg；GNB 128/64 hex |

**两层路由不可混**：GNB 的 `route.conf` 是**地址宣告**（哪个 nodeid 拥有哪些 IP/网段），不是递归路由表、不装 OS 路由；系统路由归 `mynet apply --auto`（OS 层）。跨驱动要双驱动节点**显式转发**。""",
        SRC_NC,
        "4. 与产品规则的对应 + 2.1 两层路由关系",
    ),
    c(
        "help-what-is-mynet",
        "什么是 MyNet",
        ["product", "overview", "getting-started"],
        r"""MyNet 是一个去中心化的网络管理系统，帮助用户创建、管理和维护自己的私有网络连接——与朋友、同事或合作伙伴建立安全连接，共享资源和服务。

**核心特性**
- 安全加密：端到端加密通信，密钥管理与认证
- 去中心化：点对点直接连接，不依赖中央服务器
- 社交化网络：好友邀请、连接请求管理、服务分享与发现
- 多域隔离：多个独立网络域，域间完全隔离，灵活权限管理

**适用场景**：个人（远程办公、文件共享、游戏联机、隐私保护）；企业（分支机构互联、远程员工接入、合作伙伴协作、云资源访问）；开发者（隔离测试环境、微服务通信、私有 API 发布）。

**快速开始**：① 用邀请码注册账号 → ② 加入/创建网络区 → ③ 添加设备 → ④ 邀请朋友 → ⑤ 发布和使用网络服务。""",
        SRC_HELP + "overview/what-is-mynet.md",
        "什么是 MyNet",
    ),
    c(
        "help-basic-concepts",
        "基本概念：域 / 节点 / IP 池 / 路由 / 服务",
        ["product", "concepts", "overview"],
        r"""**域（Zone）**：完全隔离的独立网络空间。不同域之间无法直接通信；每个域有独立的用户、节点、路由和服务；用户通过邀请加入特定域。

**节点（设备）**：参与网络通信的设备（服务器、个人电脑、路由器等）。每个节点有唯一标识与密钥对，负责数据转发、服务托管。状态：在线 / 离线 / 错误 / 未知。

**IP 池**：为节点分配内网 IP 的地址资源池，CIDR 格式（如 `10.0.0.0/24`），自动分配与回收；分私有/共享、静态/动态。

**路由（连接）**：节点之间的网络连接与通信路径（点对点 / 网关 / 中继），端到端加密。状态：活跃 / 连接中 / 断开 / 重连中。

**密钥**：公钥用于身份验证，**私钥绝不泄露**；推荐本地生成、只上传公钥。新用户须邀请码注册；支持多因素认证。

**连接类型**：直连（延迟低、带宽大，需 NAT 穿透成功）；中继（可连内网节点，延迟与带宽有代价）。

**服务**：私有服务（域内可见、免审批）与公共服务（跨域、需审批、受监控）；服务注册 / 发现 / 健康检查自动完成。

**典型流程**：注册加入 → 创建资源（IP 池/设备）→ 建立连接 → 发布/使用服务 → 监控维护。规划先行、安全第一、监控为本。""",
        SRC_HELP + "overview/basic-concepts.md",
        "基本概念",
    ),
    c(
        "help-registration",
        "用户注册（邀请码 + 邮箱验证）",
        ["product", "getting-started", "registration"],
        r"""MyNet 采用**邀请制注册**。

**流程**：① 获取邀请码（朋友邀请 / 组织分发 / 官方活动；**一码一次**）→ ② 注册页填用户名（3–20 字符，字母/数字/下划线，全局唯一）、邮箱（一邮一账号）、密码（≥8 位含大小写、数字、符号）、邀请码（区分大小写）→ ③ 同意条款并提交。

**邮箱验证**：注册后收激活邮件（约 2–5 分钟），点链接或输 6 位激活码（24h 有效）。失败处理：过期→重新发送；错误→核对输入；未收到→查垃圾箱/重发/联系支持。

**常见问题**
- 邀请码无效：已用 / 过期 / 输错 → 找邀请人换新码
- 用户名已存在：换个名字（可加数字/下划线）
- 注册后无法登录：先确认账号已激活；核对账号密码；清缓存重试

**安全提醒**：妥善保管密码与邮箱验证码，不向他人透露账号信息。""",
        SRC_HELP + "getting-started/registration.md",
        "用户注册",
    ),
    c(
        "help-login",
        "登录系统与登录问题排除",
        ["product", "getting-started", "login"],
        r"""**登录方式**：用户名或邮箱 + 密码（标识不区分大小写，密码区分）；可选「记住我」（仅私人设备，30 天）；支持邮箱快速登录、手机验证码登录。

**忘记密码**：登录页「忘记密码？」→ 输注册邮箱 → 收重置邮件（约 5 分钟，链接 24h 有效）→ 点链接设新密码。未请求重置可忽略该邮件。

**多因素认证（MFA）**：启用后登录需额外 6 位验证码（邮箱 / 短信 / TOTP 认证器）。

**设备识别**：常用设备可标为信任设备（简化验证）；新设备登录会发邮件通知，可选信任。

**多域切换**：顶部导航域名下拉切换，不同域权限不同；收到域邀请邮件/链接后，登录确认即可加入新域。

**登录问题**
- 密码错误：查 Caps Lock；用密码重置
- 账号锁定：连续错 5 次锁 30 分钟，可联系支持解锁
- 验证码错误：核对时间同步（TOTP），重新获取
- 连接超时 / DNS 失败：刷新、清缓存、换 DNS/网络再试

**安全建议**：强密码且不跨站复用；公共设备用完退出；开启 MFA；不在公共 WiFi 下登录；只走 HTTPS；不点可疑邮件里的登录链接。""",
        SRC_HELP + "getting-started/login.md",
        "登录系统",
    ),
    c(
        "help-create-ip-pool",
        "创建 IP 池",
        ["product", "ippool", "how-to"],
        r"""IP 池为节点提供 IP 地址分配。

**创建流程**
1. 「IP 池管理」→ 右上角「创建 IP 池」
2. 基本信息：名称（3–50 字符）、描述（≤200 字符）
3. 网络参数：CIDR 范围（如 `10.0.0.0/24` 可分配 254 个地址）；网关（自动=首个可用 IP，或自定义）；DNS（系统默认或自定义主/备）
4. 高级：分配策略（动态推荐 / 静态 / 混合）、保留地址（网络/广播/网关地址，可预留服务器）、租期与自动清理
5. 权限：域内共享 / 私有 / 指定用户；只读 / 使用 / 管理三档

**私网范围参考**：A 类 `10.0.0.0/8`（大型）；B 类 `172.16.0.0/12`（中型）；C 类 `192.168.1.0/24`（小型起步）。

**创建后校验**：CIDR 合法性、网关有效、与现有池不冲突、权限配额。

**常见错误**：创建失败 → 查 CIDR 格式 / 范围冲突 / 权限；地址冲突 → 换不冲突范围或子网划分；权限不足 → 找域管理员申请。

**最佳实践**：按规模选段（<50 设备用 /24，50–500 用 /16，>500 用 /8 分段）；生产/测试分开；不同用途用不同池；设置使用率告警。""",
        SRC_HELP + "user-guide/ip-pools/create-ip-pool.md",
        "创建 IP 池",
    ),
    c(
        "help-general-faq",
        "常见问题精选（账号 / 网络 / 连接 / 服务）",
        ["product", "faq"],
        r"""**账号**：邀请码从朋友/组织/官方活动获取，一码一次；无效先核对大小写与有效期；用户名注册后不可改；忘记密码走登录页重置流程；激活邮件未到先查垃圾箱、10–15 分钟后重发。

**网络**：域是隔离的独立网络空间，可同属多域；建域可被邀请/申请/企业版自建；IP 池按规模选私网段（小 /24、中 /16、大 /8）；节点显示离线 → 查网络连通、防火墙端口、重启客户端、升级版本。

**连接**：与朋友建连 = 加好友 → 建路由 → 选双方节点 → 发请求 → 对方确认；被拒多半是对方暂不需要/配置冲突/策略限制，沟通后重发；连接慢 → 查带宽 / 是否中继 / MTU / 加密配置 / 设备性能。

**服务**：私有服务域内可见免审批；公共服务跨域需审批且受监控；服务不可访问 → 查服务状态、连通性、防火墙、端口冲突、权限。

**技术**：支持 Windows 10/11、macOS 10.15+、主流 Linux；需稳定互联网、NAT 穿透、上行 ≥1Mbps；配置可自动备份/手动导出；频繁断线 → 查网络稳定性、客户端版本、系统资源、日志。

**安全**：端到端加密 + 密钥管理 + 访问控制 + 审计日志；密钥可自动/手动/紧急更新；怀疑盗用 → 改密码、查登录记录、撤销设备、开 MFA、联系支持。""",
        SRC_HELP + "faq/general-faq.md",
        "常见问题解答",
    ),
]

def build() -> None:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    chunks = []
    for ch in CHUNKS:
        body = ch["body"]
        chunks.append(
            {
                "id": ch["id"],
                "title": ch["title"],
                "tags": ch["tags"],
                "body": body,
                "source": ch["source"],
                "sha256": hashlib.sha256(body.encode("utf-8")).hexdigest(),
            }
        )
    doc = {
        "version": VERSION,
        "generated_at": now,
        "lang": "zh-CN",
        "chunks": chunks,
    }
    OUT.write_text(json.dumps(doc, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {OUT} ({len(chunks)} chunks, version {VERSION})")


if __name__ == "__main__":
    build()
