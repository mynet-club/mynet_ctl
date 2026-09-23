# GNB 密钥实现对照 + 节点连通性检查模型

> 2026-09-24 · 对照 PHP 扩展 / Go / C 三端 · 汇总过夜实测
> 关联：`spec/node-keying.md` · `overnight-u1-report.md`

---

## 1. GNB Ed25519 三端对照

**同一算法，三种封装。** 权威在 C（`mynet_gnb/src/libs/ed25519/keypair.c`）：

```c
void ed25519_create_keypair(pub, priv, seed) {
    sha512(seed, 32, private_key);   // 64 字节 = SHA512(seed)
    private_key[0]  &= 248;          // clamp
    private_key[31] &= 63;
    private_key[31] |= 64;
    ge_scalarmult_base(&A, private_key);  // 取 **前 32 字节标量**
    ge_p3_tobytes(public_key, &A);        // 32 字节
}
```

### 1.1 对照表

| | **C 权威** | **PHP 扩展** | **Go（mynet/keys）** |
|---|---|---|---|
| **位置** | `mynet_gnb/src/libs/ed25519/` | `mynet_back/lib/gnb_ed25519/`（.so） | `mynet_agent/mynet/keys/gnb_ed25519.go` |
| **API** | `create_keypair` / `sign` / `verify` / `key_exchange` | `gnb_ed25519_create_keypair`<br>`gnb_ed25519_verify_keypair`<br>`gnb_ed25519_derive_public` | `GenerateGNBKeyPairFromSeed`<br>`VerifyGNBKeyPair`<br>`GNBPublicKeyFromPrivateKey`<br>`SignGNB` |
| **私钥** | 64B `SHA512(seed)` | 同 C，输出 **128 hex** | 同 C，输出 **128 hex** |
| **公钥** | 32B | **64 hex** | **64 hex** |
| **签名** | RFC8032（nonce 取 `SHA512(priv[32:64]‖msg)`） | 扩展未暴露 sign | `SignGNB` 严格按 `sign.c`；签名可被 stdlib `ed25519.Verify` 验 |
| **校验对** | — | `verify_keypair` | `VerifyGNBKeyPair` |

**关键差异（隐性知识）**：

1. **私钥不是 stdlib 的 `seed‖pub`**——是 `SHA512(seed)`。拿 Go `crypto/ed25519.Sign` 会 **R 对 S 错**（nonce 派生用了 `priv[32:64]`）。
2. **clamp 只作用于前 32 字节标量**；后 32 字节参与 nonce 派生，**不能丢**。
3. **编码与布局正交**：128/64 hex 是 GNB 落盘格式；wg 用 base64 44 字符。
4. **`key_exchange` ≈ X25519**（edwards→montgomery + DH）——节点面 DH+HMAC 可复用驱动密钥（C-2）。

### 1.2 PHP 扩展现状（U1 实测）

| 项 | 值 |
|---|---|
| **产物** | `/opt/homebrew/lib/php/pecl/20240924/gnb_ed25519.so` |
| **ini** | `/opt/homebrew/etc/php/8.4/conf.d/gnb_ed25519.ini` |
| **构建** | `./configure --enable-gnb_ed25519`（config.log 可证） |
| **API 面** | 3 个函数（create / verify / derive） |
| **故障** | 当前 **PHP 8.5.3**（API `20250925`）vs 扩展 **8.4**（`20240924`）→ **Unable to initialize** |

**处置**：
- 短期：`gnb_crypto` CLI fallback（已实装 + 单测）
- 中期：用 PHP 8.5 重编扩展（源码树已清理，需从 C 库重做 config.m4 + zend wrapper）
- Go 侧不依赖扩展，**已是权威可移植实现**

---

## 1.3 多实例隔离（设计如此）

每个 gnb 实例有**独立 UDP 端口 + 独立 TUN**；OpenWrt 再加 **netifd interface**：

| 实例 | node.conf | listen | ifname | OpenWrt `/etc/config/network` |
|---|---|---|---|---|
| 旧 e2e 节点 | `conf/8675838…/node.conf` | **9016** | `gnb_tun_8675838…` | `config interface 'mynet'` device=gnb_tun |
| index 节点 | `conf/index/node.conf` | **9016** | `gnb_idx0` | — |
| U1 3002 | `conf/3002/node.conf` | **9202** | `gnb0` | — |

**结论**：同机多实例可并存；排查冲突只看 **ifname / listen 是否撞车**，不要见双进程就杀。

---

## 2. 节点连通性检查模型（四步 + 一）

> 你的模型**成立**，与今夜实测完全吻合。编号整理为 **5 步**（原第 5 点补为 E）。

```
A 软件与配置完整
B 密钥正确交换
C 邻接/网络宣告（route.conf）
D 系统路由可达
E 防火墙放行 + 回程
```

| 步 | 检查 | gnb | wireguard | 失败症状 |
|---|---|---|---|---|
| **A 软件+配置** | 二进制、配置文件齐全可解析 | `node.conf` / `address.conf` /（可选 `index`）/ `route.conf` / `security/*.private\|.public` / `ed25519/` | `wg0.conf`（PrivateKey/Address/ListenPort）+ `ip link add wg0 type wireguard` | 进程起不来 / `miss route.conf` / `if[] opened` 失败 |
| **B 密钥交换** | 彼此持有对方公钥 | **`ed25519/<peer_id>.public`**（64 hex）互拷；`gnb_ctl -s` 的 `shared_secret`/`key512` **两端一致** | **Peer PublicKey** 双方匹配；`wg show` 可见 peer | `key512` 不一致 → 有 Direct 无数据 |
| **C 邻接宣告** | 知道对方是谁、在哪 | `address.conf`：`n\|<id>\|<ip>\|<port>`（`i`=index `f`=forward `s`=silent）<br>**`route.conf`：`<id>\|<ip>\|<netmask>` = 节点地址/网段归属** | `Endpoint = ip:port` + `AllowedIPs`（**只放对端 TUN /32**） | 显示 Local / InDirect；对端 ping 无 in/out |
| **D 系统路由** | OS 找得到路 | `ip route get <dst>` 须指向 **gnb TUN**（如 `dev gnb0`） | `ip route get <dst>` 须指向 **wg0** | **路由被劫持**（见发现 1） |
| **E 防火墙+回程** | 入向放行 + **回程路由/策略对称** | UDP <listen_port> 入向；FORWARD（若做桥）；**回程 dst 也要有 route.conf 宣告** | UDP ListenPort 入向；**AllowedIPs 含回程源网段**；FORWARD/nft | 单向通；握手有、ping 不通 |

### 2.1 与旧 `$MYNET/conf/route.conf` 的关系（你的 d 点）

两层路由，**不可混**：

| 层 | 文件/机制 | 作用 |
|---|---|---|
| **GNB 层** | `driver/gnb/conf/<id>/route.conf` | **地址宣告**：哪个 nodeid 拥有哪些 IP/网段（含非 /24 的友好网段） |
| **L6 系统层** | `$MYNET/conf/route.conf` → `mynet apply` 写 OS 路由 | 两节点**不在同一 /24** 时的系统路由；跨驱动桥也在这层 |

**实测结论**：GNB 的 `route.conf` **不是递归路由表**——只宣告「地址归属」，不保证「任意 dst 经该节点转发」。跨驱动要 **双驱动节点显式转发**（TCP 桥 / SNAT），符合产品规则「智能同类型直连」。

### 2.2 补充发现（过夜实测）

| # | 发现 | 教训 |
|---|---|---|
| **1** | **OS 路由劫持**：`ip route replace 10.31.0.0/24 via 10.23.0.3 dev wg0` 会**抢走整个 gnb 网** | 跨驱动测试**只加 /32**，绝不动对方在用的 /24 |
| **2** | **wg AllowedIPs 塞整网段** → cryptokey routing + 系统路由表互相污染 | AllowedIPs **只放对端 TUN /32**；网段用显式桥 |
| **3** | **多实例是设计特性，不是冲突**：每实例独立 `ifname` + `listen` UDP；OpenWrt 另有 `/etc/config/network` 的 `config interface` | 同机可并存多实例（实测旧 e2e `gnb_tun_8675838:9016` + 新节点 `gnb0:9202`）。A↔B 不通是**路由劫持**（发现 1），不是双实例 |
| **4** | **路由指向物理网关**（`via 192.168.0.1 dev ens18`）→ wg 流量漏出 | `ip route get` 必须核对 **dev 是不是隧道** |
| **5** | **密钥一致 + Direct P2P ≠ 数据通** | 仍要查 D/E；计数 `in/out` 是关键证据 |
| **6** | **计数诊断法** | A↔B 不通时：A `in`↑`out`0 + B `out`↑`in`0 ⇒ 封装单向；`in/out` 皆 0 ⇒ TUN 根本没进 |
| **7** | **gnb_es 双进程/孤儿** | `--crash` 实测：gnb SIGKILL 后 gnb_es 1s 内清空（PPID 机制有效） |
| **8** | **防火墙两套** | OpenWrt = **nft/fw4**（无 iptables）；桥接 FORWARD 用 `nft insert rule inet fw4 forward …` |
| **9** | **回程必须对称** | 请求走了桥，回程若走直连 gnb，源地址可能不匹配被丢 |
| **10** | **跨驱动一跳 ≠ 两跳** | A(gnb)→C→B(wg) 一跳：GNB 不载非 TUN dest；两跳可达（A→C gnb + C→B wg）或 **C 上显式 TCP 桥** |

---

## 3. 快速排查清单（printable）

```text
[ ] A  gnb -c <conf> 起得来；ls node.conf address.conf route.conf security/ ed25519/
[ ] A  wg show / ip link show wg0 UP
[ ] B  ed25519/<peer>.public 存在且 64 hex；两端 gnb_ctl -s 的 key512 一致
[ ] B  wg show peer pubkey 一致
[ ] C  address.conf 有 n|peer|ip|port；route.conf 有 peer|ip|mask
[ ] C  wg AllowedIPs = 对端 TUN /32（不塞网段）
[ ] D  ip route get <dst> → dev gnb0 / wg0（不是 ens18 / 默认网关）
[ ] E  UDP listen port 入向放行；FORWARD/nft 若跨桥
[ ] E  回程：对端 route.conf / AllowedIPs / OS 路由对称
[ ]   gnb_ctl -s 看 in/out；wg show transfer / latest-handshake
```

---

## 4. 与产品规则的对应

| 规则 | 落在哪一步 |
|---|---|
| **智能同类型直连** | C：`sharesDriver()` —— 无共同驱动不建连 |
| **一节点一网卡** | A/D：一张 TUN；跨驱动靠双驱动节点显式转发 |
| **跨驱动 = L6 桥** | D/E：显式转发（`cross_driver_bridge.py`），不是透明路由 |
| **编码与布局正交** | B：hex 落库 ↔ base64 写 wg；GNB 128/64 hex |

---

## 5. 进程生命周期铁律（多实例安全）

**禁止按名字杀进程。** procd / systemd / launchd / 多实例 gnb 都可能同名并存。

| ✅ 正确 | ❌ 错误 |
|---|---|
| `procd_send_signal` / `/etc/init.d/<svc> stop` | `pkill -x mynetd` |
| `systemctl stop <unit>` / `launchctl stop` | `killall gnb` |
| **pidfile + cmdline 校验** 后 `kill <pid>` | `kill $(pidof gnb \| awk '{print $1}')` |
| PPID 匹配收 `gnb_es`（见 `node.lua:find_child_gnb_es`） | `pkill gnb_es` |

**参照实现**：`mynet_openwrt/luasrc/model/mynet/node.lua` 的 `stop_gnb(node_id)`
— pidfile → kill 主进程 → PPID 收 gnb_es → 兜底按二进制路径（仍只杀本安装的）。

**排查实例**用 `pgrep -f 'gnb -c <conf>'`（cmdline 匹配 conf 目录），不用 `pidof gnb`。

### 5.1 何时自动匹配失明（→ 人工判断）

| 场景 | cmdline 匹配 | 说明 |
|---|---|---|
| **改端口 / 改 node.conf 内容** | ✅ 不失明 | cmdline 只有 `-c <conf_dir>`，端口在 conf 里 |
| **conf 目录改名/迁移** | ❌ 失明 | 进程仍带旧路径 |
| **符号链接 / 别名启动** | ⚠️ 可能失明 | `/proc/pid/exe` 是真实路径 |
| **`sudo gnb …`** | ⚠️ 双 PID | sudo 壳 + 真 gnb——杀真进程不是壳 |
| **PID 复用** | ❌ 危险 | pidfile 过期后 PID 被别人占 |
| **daemonize 后改写 cmdline** | ❌ 失明 | 视 `gnb -d` 行为 |

**处置**：`GNB_PID=… sh verify_gnb_es_orphan.sh --crash`  
定位不到时：`sh verify_gnb_es_orphan.sh --inspect` —— **只列候选（pid/ppid/exe/cmd/端口/TUN），不自动杀**，由人判断后带 `GNB_PID` 再操作。
