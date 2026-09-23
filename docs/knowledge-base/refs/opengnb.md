# OpenGNB 协议与架构原理

> 本文只讲 OpenGNB（GNB）自身的协议与架构原理，不涉及任何具体项目的落地方式。

## 0. 基线、来源与可信度

**代码基线**：`github.com/luoyueliang/opengnb` fork，`src/version` = **1.6.8**，
`src/src/gnb_version.h:22` 声明 `GNB version Dev 1.6.8  protocol version 1.6.0`。
上游原始仓库为 `github.com/gnbdev/opengnb`（`src/README_CN.md:6`），GPLv3。

本机源码根：`/Users/arthur/Devel/WorkSpacePHP/lyt.com/mynet_gnb/`。
下文所有 `file:line` 均相对该根目录，`src/src/` 为 C 源码，`src/libs/` 为内联第三方库。

**可信度分层**（阅读时请留意这个区别）：

| 层级 | 内容 | 采信度 |
|---|---|---|
| A | 本机 1.6.8 源码 | 权威，本文所有机制描述以此为准 |
| B | 上游 README / 用户手册 | 权威，但**手册标注 version 1.3.0.0 / protocol 1.2.0，已落后于代码** |
| C | fork 自带的 `docs/*.md` | 二次整理，**有已知错误**（见 §11.3） |

**一处必须先纠正的过时说法**：`src/docs/gnb_user_manual_cn.md:11` 写「nodeid 是一个 32bit
无符号整型数字」。在 1.6.8 中它是 **64 位**：`gnb_type.h:24`
`typedef unsigned long long int gnb_uuid_t;`。手册那一版尚未跟上协议升级
（`src/README_CN.md:2` 已声明 1.6.0 协议不向后兼容）。

---

## 1. 设计定位

GNB 是一个**去中心化的软件定义虚拟网络（SDVN）**，目标是「极致的 NAT 穿透能力」
（`src/README_CN.md:6,11-15`）。几个界定性特征：

- **节点对等**：网络中每个节点都是对等的，没有 Server / Client 概念
  （`gnb_user_manual_cn.md:9`）。
- **控制面与数据面分离**：`index` 节点的角色类似 DNS 与 BitTorrent 中的 tracker，
  **绝大多数情况下只提供地址索引，不中转数据**（`src/README_CN.md:260`）。
- **三层解耦**：身份靠 ed25519，地址发现靠 index，实际转发有四种可选模型（§8）。
- **虚拟网卡工作在 TUN 模式**，即三层交换，不支持 TAP 二层
  （`src/README_CN.md:285`）。

**官方明确声明的限制**（`src/README_CN.md:280-285`）：

1. 不支持 CIDR，**仅支持 A / B / C 类网络**（详见 §8.4）；
2. 不转发默认路由的 IP 帧——`host to net` 与 `net to net` 可以为特定子网转发，
   但不支持全流量转发；
3. Windows 平台下 IPv6 目前无法正常工作；
4. 不支持 TAP，即不支持二层交换。

第 1 条是理解 GNB 路由模型的关键，它不是实现疏漏而是刻意的取舍（§8.4）。

---

## 2. 身份与密钥体系

### 2.1 节点身份

节点 ID 称 `nodeid` / `uuid64`，**64 位无符号整数**（`gnb_type.h:24`），
文本解析走 `strtoull`（`gnb_nodeid.c:26-71`）。节点 ID 与 ed25519 公钥是两个独立概念：
节点 ID 是路由与配置里的索引，公钥才是密码学身份。

### 2.2 ed25519 密钥对的生成与存储布局

生成流程（`src/libs/ed25519/keypair.c:6-16`）：

```
seed(32B, /dev/urandom 或 gnb_random_data)
  → private_key = SHA512(seed)                      // 64B
  → clamp: private_key[0] &= 248; [31] &= 63; [31] |= 64
  → public_key  = ge_scalarmult_base(private_key)   // 32B
```

**私钥文件的存储布局值得单独记住**：落盘的是 **`SHA512(seed)` 的 64 字节**（128 位十六进制），
**不是** ed25519 标准库常见的 `seed || SHA512(seed)` 布局
（`cli/gnb_crypto.c:55-76` 写、`gnb_keys.c:68-74` 读）。
由此推出一个实际后果：**拿到私钥文件无法反推 seed**，因为 SHA512 是单向的，
而 seed 本身没有被保存。公钥文件则是 32 字节（64 位十六进制）。

签名与验证就是标准 RFC8032：`ed25519_sign()` / `ed25519_verify()`
（`src/libs/ed25519/ed25519.h:28-29`）。这一点有实用价值——**任何标准库都能验签 GNB 的控制帧**。

### 2.3 key512：索引层的身份摘要

```
key512 = SHA512(public_key[32] || passcode[4])     // 64B
```

见 `gnb_node.c:103-118`。它是节点在 index 协议中的标识——index 帧里携带的是
`src_key512[64]` / `dst_key512[64]`（`gnb_index_frame_type.h:52,81-82,102-103`），
而不是裸公钥。`passcode` 是 4 字节（32 位）的十六进制串
（`gnb_keys.c:173-194`），相当于在公钥之外再叠一层「同一虚拟网络的共享口令」。

### 2.4 lite 模式：完全没有非对称加密

lite 模式（`-n <id> -I <index> -p <passcode>` 直接启动）下
**不启用非对称加密**，`shared_secret` 由 passcode 与两个节点 ID 直接拼出
（`gnb_node.c:80-99`）：

```
shared_secret[0..3]  = passcode(4B)
shared_secret[4..11] = max(node_id, local_id)   // 网络字节序
shared_secret[12..19]= min(node_id, local_id)
```

即**共享密钥完全由可公开推测的信息派生**，唯一秘密是 passcode。
`src/README_CN.md:181` 明确承认这一点：「安全性会比使用非对称加密的工作模式低很多」。
同 passcode + 同 index 的两个 lite 网络必然互相干扰（`:62`）。lite 模式只适合临时组网。

---

## 3. 数据面密钥协商

GNB 的密钥协商是一个**把 ed25519 密钥当 X25519 用**的 DH。核心函数
`ed25519_key_exchange()`（`src/libs/ed25519/key_exchange.c:4-79`），
调用点 `gnb_node.c:74`：

```c
ed25519_key_exchange(node->shared_secret, node->public_key, gnb_core->ed25519_private_key);
```

算法三步：

1. **clamp 私钥**（`key_exchange.c:25-27`）：取私钥前 32 字节重新 clamp
   （对已 clamp 过的值是无操作）。
2. **Edwards → Montgomery 坐标转换**（`:31-36`），依据
   `montgomeryX = (edwardsY + 1) · inverse(1 − edwardsY) mod p`：
   ```c
   fe_frombytes(x1, public_key);
   fe_add(tmp0, x1, tmp1);   // edwardsY + 1
   fe_sub(tmp1, tmp1, x1);   // 1 - edwardsY
   fe_invert(tmp1, tmp1);
   fe_mul(x1, tmp0, tmp1);
   ```
3. **Montgomery ladder**（`:44-71`）：`for (pos = 254; pos >= 0; --pos)`，
   用 `fe_cswap` 做常数时间交换，`fe_mul121666` 是 X25519 的特征常数
   （2^255 − 19 上的 (A−2)/4 = 121665… 此处实现为 121666）。
   最后 `fe_tobytes(shared_secret, x2)` 输出 **32 字节**。

也就是说：**GNB 的 `shared_secret` 等价于 X25519 DH 的结果**，
只是公钥以 ed25519 的 Edwards 形式分发。这个设计的好处是控制帧签名与数据面 DH
复用同一对密钥。

### 3.1 crypto_key 的派生

`gnb_build_crypto_key()`（`gnb_keys.c:117-129`）：

```c
unsigned char buffer[64+4];
if (GNB_CRYPTO_KEY_UPDATE_INTERVAL_NONE != conf->crypto_key_update_interval)
    memcpy(buffer, gnb_core->time_seed, 32);      // 轮换开启时用时钟种子
else
    memcpy(buffer, node->shared_secret, 32);      // 默认：DH 结果
memcpy(buffer + 32, node->shared_secret, 32);
memcpy(buffer + 64, conf->crypto_passcode, 4);
memcpy(node->pre_crypto_key, node->crypto_key, 64);
sha512(buffer, 64 + 4, node->crypto_key);         // 64B
```

即

```
crypto_key = SHA512( [time_seed | shared_secret] || shared_secret || passcode )   // 64B
```

两端各自算出的 `crypto_key` 相同（DH 结果对称，passcode 共享）。
`pre_crypto_key` 保留上一把密钥，用于轮换瞬间的平滑过渡。

### 3.2 基于时钟的密钥轮换

`--crypto-key-update-interval` 取 `hour` / `minute` / `none`，**默认 `none`**
（`gnb_user_manual_cn.md:837`）。开启后 `time_seed` 由当前 UTC 时间派生
（`gnb_keys.c:135-152`）：

```c
time_seed = tm_year + tm_mon + tm_yday;
if (HOUR)   time_seed += tm_hour;
if (MINUTE) { time_seed += tm_hour; time_seed += tm_min; }
sha512(&time_seed, 4, gnb_core->time_seed);
```

这是一个**依赖两端时钟同步**的机制——手册明确提示「由于考虑到实际环境中一些节点
时钟可能无法及时同步时间，因此这个选项默认是不启用」（`gnb_user_manual_cn.md:837`）。
它带来的是「随时间变化的密钥」，而不是密码学意义上的前向保密（§4.4）。

---

## 4. 数据面加密：实际强度（重要）

**这一节是整份资料里最需要精确的地方，因为官方文档的措辞容易造成误解。**

### 4.1 只有 XOR 和 RC4

`--crypto` 的取值是 `xor` / `rc4` / `none`，**默认 `xor`**
（`gnb_user_manual_cn.md:833`；`gnb_argv.c:157` 设默认位
`conf->pf_bits |= GNB_PF_BITS_CRYPTO_XOR`；位定义 `gnb_conf_type.h:33-34`）。
手册原文：「未来会支持 aes」。

**全仓不存在 AES / GCM / CBC / ChaCha20 的任何实现**。
`src/libs/protocol/` 目录下只有一个 `network_protocol.h`，内容是 IP / IPv6 头部结构体
（`network_protocol.h:52-107`），**不含密码库**。对称加密实现在 `src/crypto/{xor,arc4}/`
与 `src/src/packet_filter/gnb_pf_crypto_{xor,arc4}.c`。

### 4.2 默认的 XOR 是「64 字节重复密钥流」

`xor_crypto()`（`src/crypto/xor/xor.c:18-29`）与它在数据面的调用
（`packet_filter/gnb_pf_crypto_xor.c:52-59`）：

```c
unsigned char *p = (unsigned char *)pf_ctx->ip_frame;
for (i = 0; i < pf_ctx->ip_frame_size; i++) {
    *p = *p ^ pf_ctx->dst_node->crypto_key[j];
    p++; j++;
    if (j >= 64) j = 0;          // 64 字节后回绕
}
```

**关键性质**：密钥流就是 `crypto_key` 这 64 字节本身，周期固定 64 字节，**没有 nonce / IV**。
这意味着：

- 同一明文位置在任意数据包中产生**相同的密文**；
- 周期 64 字节使「crib-dragging」类分析成立——若已知明文的 64 字节周期结构
  （例如 IP 头与 TCP 头高度规整），可直接恢复密钥流的相应字节；
- 长流量下 64 字节的密钥空间重复暴露。

RC4 模式（`packet_filter/gnb_pf_crypto_arc4.c:49,68`）用
`arc4_init(sbox, node->crypto_key, 64)` 做标准 RC4 流密码，比 XOR 强，
但仍然是**未经认证的流密码**，且 RC4 本身已被广泛认为不安全。

### 4.3 没有任何完整性保护

数据面**没有 MAC / HMAC / AEAD**。全仓 `src/src/` 下 grep `hmac|MAC` 在数据面路径上无命中。

唯一的「校验」是 IPFRAME 内层帧头里的 4 字节 `verifycode`
（`packet_filter/gnb_pf_route.c:36`），它的构造是**明文首 2 字节 + 末 2 字节**
（`gnb_primary_worker.c:304-323,345-349`），解密后比对；不匹配则用
`pre_crypto_key` 再试一次（服务于密钥轮换过渡）。

**这个 `verifycode` 不是完整性保护**：它不依赖密钥、可由攻击者重算，
只用于「判断用新旧哪把密钥解密成功」。因此 GNB 的数据面**不具备防篡改能力**——
在转发路径上改写 IP 分组无法被端点检测到。

### 4.4 与「前向保密」的距离

GNB 的密钥体系提供的是：**身份认证（ed25519 签名）+ 密钥协商（X25519 式 DH）**。
但因为它没有「握手 → 临时密钥 → 会话密钥」这样的分层，`crypto_key` 是
**长期静态密钥对直接派生的**（`shared_secret` 来自静态 ed25519 密钥对之间的 DH），
所以：

- **不具备前向保密（PFS）**：一旦某节点的私钥泄露，攻击者可以重算
  `shared_secret` 与 `crypto_key`，解密历史抓包（前提是他已经破解了 XOR/RC4 密钥流）；
- 开启 `--crypto-key-update-interval` 后密钥随时间变化，
  能**削弱**（而非消除）上述问题，代价是引入时钟同步依赖。

对比 WireGuard 的「每次握手生成临时密钥、2 分钟轮换、180 秒后强制丢弃」，
这是两套系统在安全模型上最本质的差距（详见对比篇）。

### 4.5 官方「非对称加密」说法的准确含义

`src/README_CN.md:262` 写「GNB 主机之间的非对称数据加密使得 forward 节点无法窥探中转的数据」。
准确的理解是：**forward / relay 节点无法解密，是因为它们不在 DH 的双方之内**，
而不是因为数据面用了非对称加密。数据面用的始终是对称的 XOR/RC4，
密钥由非对称协商得来。这个区分在评估安全性时很重要。

---

## 5. 帧格式

### 5.1 payload16：统一外层帧头

`src/src/gnb_payload16.h:27-36`：

```c
#pragma pack(push, 1)
typedef struct _gnb_payload16_t {
    uint16_t size;        // 网络字节序（htons/ntohs）
    unsigned char type;
    unsigned char sub_type;
    unsigned char data[0];
} __attribute__((packed)) gnb_payload16_t;
#pragma pack(pop)

#define GNB_PAYLOAD16_HEAD_SIZE 4
#define GNB_MAX_PAYLOAD_SIZE    65535
```

**4 字节头，没有 magic 字段**（magic 只存在于 IPFRAME 的内层帧头）。
`data_len = size - 4`（`gnb_payload16.c:47-50`）。粘包重组由
`gnb_payload16_handle()`（`gnb_payload16.c:62-177`）负责——它先用 2 字节 buffer
攒够长度字段再读整帧。

### 5.2 帧类型总表

权威定义在 `src/src/gnb_core_frame_type_defs.h`：

| type | 值 | sub_type | 值 | 阶段 |
|---|---|---|---|---|
| `UR0` | 0x3 | — | — | 通用中继 |
| `IPFRAME` | 0x4 | `INIT` / `STD` | 0x0 / 0x1 | **数据面** |
| | | `RELAY` | 0x2 | 中继（逐跳再加密） |
| | | `UNIFIED` | 0x4 | 统一转发 |
| | | `UNIFIED_MULTI_PATH` | 0x8 | 多路径重传 |
| | | `ZIP` | 0x10 | 压缩 |
| `INDEX` | 0x8 | `POST_ADDR` / `ECHO_ADDR` / `REQUEST_ADDR` / `PUSH_ADDR` / `DETECT_ADDR` | 0x1–0x5 | **地址索引** |
| `NODE` | 0x9 | `PING` / `PONG` / `PONG2` / `LAN_PING` / `UNIFIED_NOTIFY` | 0x1–0x5 | **保活 / 打洞** |
| `LAN_DISCOVER` | 0x43 | — | — | 内网广播发现 |
| `UDPLOG` / `ES_UDPLOG` | 0x44 / 0x45 | — | — | 日志 |
| `UR1` | 0x46 | `SRC_ADDRESS4` / `DST_ADDRESS4` / `HEADER` / `STD` / `UNIFIED` | 位掩码 0x1–0x10 | 通用中继 |

`ED25519_SIGN_SIZE = 64`（`:69`）。

### 5.3 IPFRAME 内层帧头与封装开销

`gnb_route_frame_head_t`（`packet_filter/gnb_pf_route.c:30-37`）：

```
magic[2]='S','U' | pf_type_bits(1) | ttl(1) | src_uuid64(8) | dst_uuid64(8) | verifycode[4]
```

合计 **24 字节**。所以**单跳封装开销 = 4（payload16）+ 24（route_frame_head）= 28 字节**。
relay 路径再追加 8 字节上一跳 uuid 尾（`gnb_pf_crypto_xor.c:110,137`），
UR1 另有自己的头（`gnb_ur1_frame_type.h:27-37`）。

MTU 默认 **1280**（`gnb_argv.c:155`，`--mtu`）。IPv6 强制 ≥1280
（`gnb_argv.c:831-832`）；IPv4 在恶劣网络下官方建议可降到 **532**
（`gnb_argv.c:149-154` 注释：「ipv4 MTU 设为 532 是因为减掉 payload 首部占用字节」）。

---

## 6. Index 协议：地址发现

### 6.1 四文件分工

| 文件 | 角色 | 是否加密/签名 |
|---|---|---|
| `gnb_index_worker.c` | 客户端侧 | 否 |
| `gnb_index_service_worker.c` | 服务端侧 | 否 |
| `gnb_secure_index_worker.c` | 客户端侧 | **是** |
| `gnb_secure_index_service_worker.c` | 服务端侧 | **是** |

普通版与 secure 版由 `safe_index` 开关二选一（`gnb_core.c:508-522`）。
secure 版的差别是：**所有 index 帧先 XOR 加密再 ed25519 签名，收端验签**
（如 `gnb_secure_index_worker.c:84-85` 发送侧，
`gnb_secure_index_service_worker.c:100,107` 接收侧先验签再解密）。

**一个不对称的细节值得注意**（`gnb_core.c:508-522`）：

```c
// 客户端侧：要求 safe_index 且非 lite 模式
if (1 == conf->safe_index && 0 == conf->lite_mode)
    index_worker = gnb_worker_init("gnb_secure_index_worker", ...);
else
    index_worker = gnb_worker_init("gnb_index_worker", ...);

// 服务端侧：只要求 safe_index
if (1 == conf->safe_index)
    index_service_worker = gnb_worker_init("gnb_secure_index_service_worker", ...);
```

也就是说 **lite 模式下客户端永远不会走 secure 路径**（因为 lite 模式根本没有
ed25519 密钥对，无法签名），但 index 服务端仍可启用 secure——
不过 lite 客户端发来的帧本来就没有签名，所以这种情况下 secure 服务端对它们无效。
实际含义是：**secure index 只在双方都持有 ed25519 密钥对的 Safe Mode 下才有意义**。

**普通版完全没有 ed25519**（`gnb_index_service_worker.c` 无任何签名调用），
这与手册的说明一致：「GNB index 协议允许在提交和查询节点 ip 地址端口过程中
不验证报文的数字签名」（`gnb_user_manual_cn.md:364`）。
换句话说，**index 层的抗伪造能力取决于是否启用 secure index**；
而 forward 协议则**必须**验证签名（`gnb_user_manual_cn.md:371`）。

### 6.2 五类帧的结构

全部定义在 `src/src/gnb_index_frame_type.h`，均 `#pragma pack(1)`，
尾部统一带 `src_sign[64]` 与 `node_uuid64[8]`：

**post_addr_frame_t**（node → index，`:44-71`）：`arg0='p'`，
携带 `src_key512[64]`、`src_uuid64`、`src_ts_usec`、自测的 WAN 地址
（`wan6_addr[16]+wan6_port`、`wan4_addr[4]+wan4_port`）、
`node_random_sequence[32]` 及其签名 `node_random_sequence_sign[64]`、
`text[32]`、`attachment[132]`。

> `node_random_sequence` 的机制值得注意：节点提交地址时生成一组随机数并对它签名，
> index 保存「最后收到的随机数 + 签名」，在 PUSH 时一并发给请求方；
> 请求方用公钥验签。**这样包括 attachment 在内的整个地址条目都无法被 index 伪造**
> （`gnb_index_frame_type.h:60-63` 的注释）。这是一个用「节点自己的签名」
> 把不可信 index 变成「可验证但不必信任」的巧妙设计。

**request_addr_frame_t**（node → index，`:94-111`）：`arg0='g'`，
携带 `src_key512[64]`、`dst_key512[64]`、双方 uuid、时间戳。

**push_addr_frame_t**（index → node，`:114-145`）：携带 `node_key[64]`、
被查询节点的 **3 组 IPv6 地址 + 3 组 IPv4 地址**（`GNB_KEY_ADDRESS_NUM = 3`，`:41`）、
随机数及其签名、`idx_ts_usec`。`arg0` 取值
`PUSH_ADDR_ACTION_NOTIFY='N'` / `PUSH_ADDR_ACTION_CONNECT='C'`（`:116-117`）。

**echo_addr_frame_t**（index → node，`:75-91`）：回显节点自测的 WAN 地址。

**detect_addr_frame_t**（`:149-164`）：`arg0='d'`（发起探测）/ `'e'`（回显），
携带双方 uuid 与 attachment。

### 6.3 索引服务端的状态与限频

服务端用 **LRU**（`gnb_lru32`）维护 `gnb_key_address_t`
（`gnb_index_service_worker.c:54-72`）：uuid、`last_post_addr4/6_sec`、
WAN 地址、`address4/6_list_block`（各 3 项，FIFO 保留最近 3 个）、`attachmenta/b`。

时间常量（`gnb_index_frame_type.h:25-34`）：

| 常量 | 值 | 含义 |
|---|---|---|
| `GNB_POST_ADDR_INTERVAL_TIME_SEC` | 60 s | 节点向 index 提交地址的间隔 |
| `GNB_POST_ADDR_LIMIT_SEC` | 20 s | index 侧对提交的限频 |
| `GNB_REQUEST_ADDR_INTERVAL_SEC` | 39 s | 节点查询对端地址的间隔 |
| `GNB_REQUEST_ADDR_LIMIT_USEC` | 20 ms | 查询的频率下限 |

查询时会校验对端的 post 未超时（`< POST_ADDR_INTERVAL_TIME_SEC × 2`，即 120 秒），
否则把该条移出 LRU（`gnb_index_service_worker.c:265,293`）。

### 6.4 P2P 打洞的促成方式

`handle_request_addr_frame()` 在收到查询后，**向双方各发一个
`PUSH_ADDR_ACTION_CONNECT`**（`gnb_index_service_worker.c:327-328`），
把对方最近 3 个 IPv4 / IPv6 地址连同 WAN 地址一起推过去。
两端各自收到后向对端地址发 DETECT 探测并互 ping，从而打洞。
这个「同时通知双方」是 NAT 穿透能成功的关键——**两边同时向对方发包**，
才能同时在各自的 NAT 上开出映射。

---

## 7. NAT 穿透机制

### 7.1 没有 NAT 类型分类，靠穷举

**一个常见误解需要澄清**：GNB **不判定 NAT 类型**（没有 symmetric / full-cone /
restricted-cone 之类的枚举），`gnb_address.c` 里只有掩码分类函数
`get_netmask_class()`（`:80-91`）和地址列表工具。

官方的态度写在手册里（`gnb_user_manual_cn.md:568-572`），相当直白：

> **Don't talk about the white paper, who known? who care?**
> **Don't talk about the type of NAT, I don't f\*cking care**
> **What GNB has to do, just try it's best to traverse the NAT**

所以策略是「尽一切办法多试」，具体手段：

| 手段 | 机制 | 来源 |
|---|---|---|
| 多 index | 同时向多个 index 交换信息，因为一个节点经 NAT 后可能用**多个 WAN 地址**访问互联网 | `gnb_user_manual_cn.md:380,581` |
| 多 socket | `multi-socket on` 在 `listen` 端口外**额外开 4 个随机 UDP 端口**，提高端口探测命中率 | `gnb_user_manual_cn.md:216-220` |
| 端口探测 | 对已获 WAN 地址但未打通的节点做小范围端口探测；另有专门线程做大范围探测 | `gnb_user_manual_cn.md:585-587` |
| UPnP | `--es-argv "--upnp"` 在出口网关建立端口映射 | `gnb_user_manual_cn.md:589-593` |
| 地址扩散 | `gnb_es --broadcast-address` 把已建立 P2P 的节点地址扩散给其他节点 | `gnb_user_manual_cn.md:610` |

### 7.2 探测 worker

`gnb_detect_worker.c`：对每个节点，在其 detect 地址列表与
`port_detect_start..end` 端口范围内逐个发 `DETECT_ADDR` 帧
（`detect_node_address()` `:55-98`，`detect_loop()` `:132-187`）。
已 PONG 或 SLIENCE 且非 FWD 的节点会被跳过。

探测帧 `node_ping_frame_t` / `node_pong_frame_t`（`gnb_pingpong_frame_type.h:26-59`）
携带 src/dst uuid、时间戳，可选回填 dst 地址与 attachment（可携带 tun sockaddr，`:63-73`）。

连接状态用位掩码表达（`gnb_map_visualization_prompt.md:88-95`）：

```
GNB_NODE_STATUS_UNREACHABL = 0x0
GNB_NODE_STATUS_IPV4_PING  = 0x1     已发 IPv4 PING
GNB_NODE_STATUS_IPV6_PING  = 0x2     已发 IPv6 PING
GNB_NODE_STATUS_IPV4_PONG  = 0x4     收到 IPv4 PONG（直连建立）
GNB_NODE_STATUS_IPV6_PONG  = 0x8     收到 IPv6 PONG（直连建立）
```

保活节奏：PING 间隔 37 秒，超时 75 秒（= 37×2+1），连续探测失败 32 次判定失联
（`gnb_node_type.h:93` `GNB_NODE_MAX_DETECT_TIMES = 32`；
`gnb_map_visualization_prompt.md:140-145`）。

### 7.3 Discover In Lan

`src/es/gnb_discover_in_lan.c` 实现同网段自动发现，由 `gnb_es` 进程承担：

- **发送**：`gnb_discover_in_lan_ipv4()`（`:262-306`）用 `getifaddrs` 枚举所有
  UP+RUNNING+BROADCAST 的 IPv4 网卡，构造 `LAN_DISCOVER`(0x43) 帧广播出去
  （`send_broadcast4()` `:57-122`）。
- **接收**：`handle_discover_lan_in_frame()`（`:124-`）识别同 LAN 的已知节点，
  回发 `NODE` 类型的 `LAN_PING`，使同网段节点直接用 LAN 地址互 ping 建立 P2P
  （`gnb_node_worker.c:287-298` 用 attachment 里的端口更新地址）。

意义：同 LAN 的节点**不必绕 WAN 做 NAT 穿透**；更进一步，
已成功穿透的节点可以帮同 LAN 未穿透的节点转发（`gnb_user_manual_cn.md:418`）。

`gnb_es` 与 `gnb` 之间通过共享内存映射文件 `gnb.map` 通信
（`gnb_user_manual_cn.md:413-416`），详见 §9.3。

---

## 8. 转发模型

### 8.1 四种模型

`gnb_user_manual_cn.md:180-184` 定义了四种：

| 模型 | 触发条件 | 中继节点能否解密 |
|---|---|---|
| **Direct** | 已建立 P2P，直发对端 | — （端到端） |
| **Standard** | 完全无法 P2P，经 `address.conf` 中带 `f` 属性的公网 forward 节点中转 | 否 |
| **Relay** | 按 `route.conf` 明确指定的中继路径逐跳转发 | 否（逐跳再加密） |
| **Unified** | 自动借道「已分别与 A、B 都建立 P2P」的节点 | 否 |

**决策优先级**（`gnb_pf.c:342-381`）：
`relay_forwarding > unified_forwarding > direct_forwarding > std_forwarding`。

### 8.2 Relay：逐跳再加密

relay 的核心特点是**每一跳都用下一跳节点的密钥重新加密**
（`gnb_pf_crypto_xor.c:95-122` `pf_chain_relay_cb`，仅处理带
`IPFRAME_RELAY` 标记的 payload）：

```c
if (!(pf_ctx->fwd_payload->sub_type & GNB_PAYLOAD_SUB_TYPE_IPFRAME_RELAY)) return pf_ctx->pf_status;
p = pf_ctx->fwd_payload->data;
for (i = 0; i < gnb_payload16_data_len(pf_ctx->fwd_payload) - sizeof(gnb_uuid_t); i++) {
    *p = *p ^ pf_ctx->fwd_node->crypto_key[j];     // 用下一跳的 crypto_key
    p++; j++; if (j >= 64) j = 0;
}
```

上一跳的 uuid 被追加在 payload 尾部（`:110`），下一跳解密时从尾部读出来
（`pf_inet_frame_cb` `:136-139`）。这就是手册所说
「IP 分组发往下一个中继点前都会作加密处理」（`gnb_user_manual_cn.md:183`）。

`route.conf` 的中继配置（`gnb_user_manual_cn.md:316-329`）：

```
$nodeid|$relay_nodeid3,$relay_nodeid2,$relay_nodeid1
$nodeid|$relay_mode
```

路径语义是 `local → relay1 → relay2 → relay3 → dst`，**反向路径由对端自己的
`route.conf` 决定**（`:322`）。`relay_mode` 取
`auto`（P2P 和 forward 都不行时才用）/ `force`（强制走中继）/
`static`（多条中继取第一条）/ `balance`（负载均衡）。
每个目的节点最多 8 条中继路由，每条最多 5 个中继节点（`:289`）。

### 8.3 Unified Forwarding：借道已穿透的节点

机制（`src/src/gnb_unified_forwarding.c`）：源节点把 IP 帧交给一个
「UF 节点」代转，payload 尾部追加
`gnb_unified_forwarding_frame_foot_t`（src/dst/uf nodeid + seq，
`gnb_unified_forwarding.c:29-34`）。

- 出方向 `gnb_unified_forwarding_tun()`（`:60-110`）选 `unified_forwarding_nodeid`；
- 入方向 `gnb_unified_forwarding_inet()`（`:177-222`）按 dst 是否本地决定 to_tun / to_inet；
- 多路径 `..._with_multi_path_tun()`（`:112-175`）对 TCP 或 HYPER 模式
  **同时向最多 5 个 UF 节点发**，用 `recv_seq` 数组去重（`:224-307`）。

工作模式（`gnb_user_manual_cn.md:396-399`，`gnb_conf_type.h:126-131`）：

| 模式 | 行为 |
|---|---|
| `auto` | 仅当 A、B 无法建立 P2P 时，自动借道 |
| `super` | 无论是否 P2P，对 **TCP 类型** IP 分组做多节点重传 |
| `hyper` | 无论是否 P2P，对**所有类型** IP 分组做多节点重传 |

**「多路径重传」的副作用**：先到达目的地的分组被写入虚拟网卡，重复的被丢弃，
每个目标节点维护一个 64 位时间戳队列用于去重。手册指出这个特性
「在一些场合下可以用作 TCP 重传加速」（`gnb_user_manual_cn.md:393`）。
代价是**放大流量**——`hyper` 模式对所有分组重传，实际带宽消耗会显著上升。

`GNB_UNIFIED_FORWARDING_NODE_EXPIRED_SEC = 15`、
`..._ARRAY_EXPIRED_SEC = 90`（`gnb_node_type.h:139,142`）。

### 8.4 路由查找：A / B / C 三类分层匹配

这是 GNB 路由模型中最独特、也最容易踩坑的部分。

**数据结构**：`subnetc_node_ring_map` / `subnetb_node_ring_map` / `subneta_node_ring_map`
（外加精确匹配用的 `ipv4_node_map`），每个子网键映射到一个**节点环**
（`gnb_node_ring_t`，最多 `GNB_MAX_NODE_RING = 128` 个节点）。

**填充**（`gnb_conf_file.c` `load_route_config()` `:897-975`）：
读取 `uuid|tun_ip|netmask` 行，判断逻辑是

```c
if (0 != p[3] && 0 == node->tun_addr4.s_addr)      // IP 末位非 0 → 主机地址
    GNB_HASH32_UINT32_SET(ipv4_node_map, ...);
else {
    netmask_class = get_netmask_class(tun_netmask_addr4);
    if ('c' == netmask_class) gnb_add_routenode_ring(..., subnetc_node_ring_map, ...);
    else if ('b' == ...)      gnb_add_routenode_ring(..., subnetb_node_ring_map, ...);
    else if ('a' == ...)      gnb_add_routenode_ring(..., subneta_node_ring_map, ...);
    // 'n'（非标准掩码）→ 静默忽略
}
```

**查找**（`gnb_node.c` `gnb_select_route4_node()` `:169-237`）优先级：
精确 IP → C 类（/24）→ B 类（/16）→ A 类（/8）→ 无匹配返回 NULL。

**环内选择**按 `multi_forward_type`：

- `SIMPLE_FAULT_TOLERANT`（默认）：返回环中**第一个在线（PONG）节点**（`:212-218`）；
- `SIMPLE_LOAD_BALANCE`：round-robin 轮转（`:219-233`）。

**三个必须知道的约束**：

1. **非标准掩码被静默忽略**。`get_netmask_class()`（`gnb_address.c:80-91`）只认
   `255.0.0.0` / `255.255.0.0` / `255.255.255.0` 三种，其余返回 `'n'`，
   配置行**不报错也不生效**。所以 `/27`、`/28`、`/22` 等**都无法配置**。
2. **主机地址的判定看 IP 末位**，不看掩码。`192.168.1.0|255.255.255.255` 会被忽略
   （末位为 0，进 else 分支，而 `255.255.255.255` 分类为 `'n'`）；
   `8.8.8.8|255.255.255.255` 才有效。
3. **默认路由用 `0.0.0.0|0.0.0.0`**，它落在 `subneta_node_map`，
   查询时任何 IP & 0 = 0 都能命中——但注意这与 README 的「不转发默认路由」
   说法**不矛盾**：README 说的是不自动接管系统默认路由，
   而这里说的是你可以显式配置一条 `0.0.0.0/0` 的放行规则。

**这是「放行策略」而非「路由策略」**：`route.conf` 只决定「这个目的 IP 允不允许
走 GNB、走哪个节点」，实际的路由决策由**系统路由表**完成
（`docs/route_forwarding_whitelist_cn.md:5,298-322`）。
两级检查：

- **TUN → INET（发送）**：`pf_tun_frame_cb()`（`packet_filter/gnb_pf_route.c:59-118`）
  从 IP 头取 dst，查白名单，无匹配则 `GNB_PF_DROP`；
- **INET → TUN（接收）**：`pf_inet_fwd_cb()`（`:464-517`）写 TUN 前再查一次，
  无匹配则 `GNB_PF_NOROUTE`。

**接收侧有两个绕过白名单的开关**（源码 `:467-501`）：

1. **`proxy=1` 时完全跳过白名单检查**。注释原文：
   「proxy=1 时本节点不受 route.conf 转发白名单限制: 写入 tun 之前不再检查目的 ip
   是否登记在 route.conf 中」（`:467-476`）。即开启 proxy 的节点会接受任意目的 IP
   的入向 payload——这正是「作为出口代理」所需的语义，但也意味着**它不再是白名单**。
2. **`pf_route_bits & 0x1` 时启用同网段放行**（`:501-517`）：
   若 payload 的源地址与本节点 TUN 地址同网段，则直接放行；
   **不同网段反而被判 `GNB_PF_NOROUTE`**。注意这里的判定用的是**源地址**
   （`gnb_determine_subnet4(ip_src, ...)`），是整段逻辑里唯一涉及源地址的检查。

**因此接收检查校验的是「目的 IP 是否被放行」，而非「源 IP 的身份绑定」**——
后者是 WireGuard `AllowedIPs` 的语义（详见对比篇 §5.1）。
GNB 对源地址的信任来自「payload 已用该节点的 `crypto_key` 成功解密」，
而不是来自 `route.conf`。

**精细路由的正确做法**是「GNB 只配大网段，精细控制在系统路由表」
（`route_forwarding_whitelist_cn.md:378-390`）：

```bash
# route.conf 里只写节点和宽网段
1002|10.1.0.2|255.255.255.0
1002|0.0.0.0|0.0.0.0
# 精细粒度交给系统
ip route add 192.168.1.0/27 via 10.1.0.2 dev gnb0
```

### 8.5 多节点路由的演进（2026-01）

fork 自带的 `docs/UPGRADE_2026_01_MULTI_NODE_ROUTING.md` 记录了这次重构：

- **旧版**：`subnetc_node_map` 用哈希 `SET`，**重复配置后者覆盖前者**，
  无容错、无负载均衡；
- **新版**：`subnetc_node_ring_map`，**追加**到节点环，全部保留；
  新增 `gnb_add_routenode_ring()` 与 `gnb_select_route4_node()`；
- 配置**向后兼容**（单节点配置照常工作），默认仍是容错模式；
- 负载均衡模式需配合 `pf-worker 0`（单线程），因为轮转索引不是线程安全的。

### 8.6 转发的本质是逐跳的

GNB 的转发是 **hop-by-hop**：每个节点维护一张
`uuid64 → gnb_node_t`（含该节点的 UDP 地址、`crypto_key`、PONG 状态、延迟）的表，
发包时查表直发。`route.conf` **不构成转发表**，它只用于「白名单 + 选哪个节点」。
所以 GNB 没有「路由收敛」概念，也不需要分布式路由协议。

---

## 9. 进程与线程模型

### 9.1 单进程多线程

**单进程 + 多线程**（`pthread_create`），只有 daemon 化时 `fork()`
（`gnb_daemon.c:40,46`）。每个 worker 是独立线程
（`gnb_worker.c:28` 注释明示）。

worker 清单在 `gnb_worker.c:40-50` 的 `gnb_worker_array[]`
（注意 `gnb_worker_type.h` 里**没有枚举**，只有 `gnb_worker_t` 结构定义 `:33-46`）：

```
primary / index / secure_index / index_service / secure_index_service / detect / node / pf
```

secure 与普通 index worker 由 `safe_index` 开关二选一（`gnb_core.c:508-522`）。

### 9.2 线程间通信：定长 ring buffer + 信号

- **ring buffer**：`gnb_ring_buffer_fixed.c:64-89`，掩码环形，`push` / `pop` / `_submit`。
  队列元素 `gnb_worker_queue_data.h:27-41`（`NODE_IN` / `NODE_OUT`，块 512 字节）。
- **唤醒**：投递后 `notify()` 用 `pthread_kill(SIGALRM)` 唤醒目标线程
  （`gnb_primary_worker.c:889-900`）。
- **primary_worker**：`gnb_primary_worker.c:835-857`，Unix 下只起 1 个线程
  `tun_udp_loop_thread_func`；主循环是 `select()` 多路复用
  （`:682-779`，超时 1.00001 s），负责读 TUN、收 UDP，然后**按帧类型分发**
  （`handle_udp()` `:403-534`）：

  | 帧类型 | 去向 |
  |---|---|
  | `IPFRAME` | pf worker |
  | `INDEX` / `POST_ADDR` / `REQUEST_ADDR` | index_service worker |
  | `ECHO_ADDR` / `PUSH_ADDR` / `DETECT_ADDR` | index worker |
  | `NODE` | node worker |
  | `UR0` / `UR1` | primary 内联处理 |

这是一个「一个 select 循环 + N 个工作线程」的经典结构：
**网络 I/O 单线程，密码学与转发多线程**，用 ring buffer 解耦。

### 9.3 ctl_block：共享内存控制块（`gnb.map`）

GNB 把运行状态放在一块 mmap 共享内存里，供 `gnb_ctl` 读取、`gnb_es` 双向通信
（`gnb_user_manual_cn.md:413-416`）。根结构（`gnb_map_visualization_prompt.md:12-20`）：

```
gnb_ctl_block_t {
  ├── conf_zone      配置区（conf_dir）
  ├── core_zone      核心区（local_uuid, wan4/6_addr, wan4/6_port, ifname,
  │                          ed25519_private/public_key）
  ├── status_zone    状态区（keep_alive_ts_sec）
  └── node_zone      节点区（每个节点的完整状态）
}
```

`GNB_CTL_KEEP_ALIVE_TS = 15` 秒（`gnb_ctl_block.h:107`）——进程存活判据是
`now - keep_alive_ts_sec < 15`。

`node_zone` 里每个节点含：uuid64、`tun_addr4`、`tun_ipv6_addr`、`type`（位掩码）、
`in_bytes` / `out_bytes`、`udp_addr_status`、延迟（`addr4/6_ping_latency_usec`）、
各类地址列表（static 6 / dynamic 16 / resolv 6 / push 6 /
available 各 3）、`detect_count`、以及**密钥材料**
（`public_key[32]` / `shared_secret[32]` / `crypto_key[64]` / `pre_crypto_key[64]` / `key512[64]`）。

> ⚠️ **`core_zone` 与 `node_zone` 里含明文私钥与 `shared_secret`**。
> 这一点已在源码中直接核实：
>
> - `gnb_ctl_block.h:63-64`：`unsigned char ed25519_private_key[64];`
>   `unsigned char ed25519_public_key[32];`——**私钥就在控制块里**；
> - `gnb_node_type.h:122-132`：每个节点带 `public_key[32]`、`shared_secret[32]`、
>   `crypto_key[64]`、`pre_crypto_key[64]`、`key512[64]`。
>
> `gnb.map` 的文件权限因此**等价于私钥的权限**——任何能读该文件的进程
> 都能拿到节点的 ed25519 私钥。运维上必须把配置目录与 `gnb.map`
> 当作密钥材料保护（`-b/--ctl-block` 可指定其位置，例如指向 `/tmp`）。

`gnb_ctl -b <gnb.map> -s` 直接 dump 这块内存（`cli/gnb_ctl.c:54-99`）。

### 9.4 热重启

`cli/gnb.c` 支持 SIGHUP 触发的**热重启**（`:53-58` 置 RESTART 标志）：
主循环停掉旧 core、rename mmap、用 `gnb_core_new` 恢复节点与 socket
（`gnb_core.c:823-887`）。目的是**不中断已建立的 P2P 连接**就完成配置重载。

---

## 10. 节点角色

`gnb_node_type.h:34-41`，`type` 是**位掩码**（`unsigned char`）：

```
GNB_NODE_TYPE_STD          = 0x0      标准节点
GNB_NODE_TYPE_IDX          = 0x1      索引节点
GNB_NODE_TYPE_FWD          = 0x2      转发节点（公网中转）
GNB_NODE_TYPE_RELAY        = 0x4      中继节点
GNB_NODE_TYPE_SLIENCE      = 0x8      静默节点
GNB_NODE_TYPE_STATIC_ADDR  = 0x10     静态地址
GNB_NODE_TYPE_DYNAMIC_ADDR = 0x20     动态地址（未使用）
```

`address.conf` 里用属性字符表达（`gnb_conf_file.c:135-150`）：

| 字符 | 含义 |
|---|---|
| `i` | index 节点，加入 index ring |
| `f` | forward 节点，加入 forward ring |
| `r` | relay 节点 |
| `s` | 静默（silence）节点 |
| `n` | 普通节点 |
| `u` | 加入 fwdu0 地址环 |

`address.conf` 行格式为 `attrib|nodeid|ip/hostname|port`
（`gnb_user_manual_cn.md:345`）。两个约束值得记：

- **`i` 属性的 nodeid 可以设为 `0`**（Public Index node），此时不需要在
  `route.conf` 里配路由（`gnb_user_manual_cn.md:366-369`）；
- **`f` 属性的 nodeid 不能为 `0`**，且**必须**在 `route.conf` 中出现；
  forward 相关报文**必须**验证数字签名，即 forward 节点与使用它的节点必须已交换公钥
  （`gnb_user_manual_cn.md:371`）。

静默节点（`s`）不与 index 通信，也不响应 ping-pong 与地址探测，
以避免暴露自身 IP（`gnb_user_manual_cn.md:348`）。

---

## 11. 工程性观察

### 11.1 单跳封装开销与 MTU 的关系

| 项 | 字节 |
|---|---|
| payload16 头 | 4 |
| route_frame_head | 24 |
| **合计（单跳）** | **28** |
| relay 追加 uuid 尾 | +8 |
| UR1 自有头 | 视 sub_type |

默认 MTU 1280（IPv6 下限），IPv4 恶劣网络可降至 532。

### 11.2 未启用 / 死代码

- `crypto_seed[64]`（`gnb_pingpong_frame_type.h:36,54`）**只有声明，全仓无任何读写点**。
- `GNB_NODE_TYPE_DYNAMIC_ADDR = 0x20` 未被使用。

### 11.3 fork 自带文档中需要修正的说法

| 文档 | 说法 | 实际 |
|---|---|---|
| `docs/route_forwarding_whitelist_cn.md:47,359` | 查询函数是 `gnb_query_route4()`，位于 `gnb_pf.c` | 1.6.8 中为 `gnb_select_route4_node()`，位于 `gnb_node.c:169` |
| `docs/route_forwarding_whitelist_cn.md:61-71` | 用 `subnetc_node_map` 等单节点映射 | 1.6.8 为 `subnet*_node_ring_map` 节点环 |
| `docs/route_forwarding_whitelist_cn.md:113` | 配置解析用 `GNB_HASH32_UINT32_SET` | 1.6.8 为 `gnb_add_routenode_ring()` |
| `gnb_map_visualization_prompt.md:86` | `udp_addr_status` 是 `uint` | 实际类型见 `gnb_node_type.h`，语义以位掩码为准 |
| `gnb_user_manual_cn.md:11` | nodeid 是 32bit | 1.6.8 为 64 位 |

该文档自身标注「适用版本：openGNB main 分支」（`:442`），
所以它是**升级前**的快照，其 FAQ 结论（非标准掩码被静默忽略等）仍然成立。

### 11.4 安全评估要点小结

| 维度 | GNB 1.6.8 的实际情况 |
|---|---|
| 身份认证 | ed25519 签名，**标准 RFC8032**，任何标准库可验证 |
| 密钥协商 | Ed25519 → Montgomery 的 X25519 式 DH（静态密钥对之间） |
| 数据面加密 | **XOR（默认，64 字节重复密钥流）或 RC4**，无 AES |
| 数据面完整性 | **无**。`verifycode` 不依赖密钥，不是 MAC |
| 前向保密 | **无**（密钥由静态密钥对派生；时钟轮换只削弱问题） |
| index 层抗伪造 | 取决于 `safe_index`；**普通版不验签** |
| 抗重放 | 未见数据面重放窗口机制（对比 WireGuard 的 8128 位滑动窗口） |
| 私钥保护 | `gnb.map` 内含明文私钥与 `shared_secret`，需按密钥文件保护 |

**这不是对 GNB 的贬低**——它用极低的资源占用（官方称可在 32 MB 内存的
OpenWRT 路由器上运行，`src/README_CN.md:25`）换取了很强的穿透与组网能力，
在「穿透优先、性能优先」的场景下这些取舍是自洽的。
但**如果目标是承载敏感数据，必须叠加一层自己的加密与认证**
（例如在 GNB 隧道内再跑 WireGuard，或跑在应用层加密之上）。

---

## 附：关键常量速查

| 常量 | 值 | 位置 |
|---|---|---|
| `GNB_PAYLOAD16_HEAD_SIZE` | 4 | `gnb_payload16.h:35` |
| `GNB_MAX_PAYLOAD_SIZE` | 65535 | `gnb_payload16.h:36` |
| `ED25519_SIGN_SIZE` | 64 | `gnb_core_frame_type_defs.h:69` |
| `GNB_KEY_ADDRESS_NUM` | 3 | `gnb_index_frame_type.h:41` |
| `NODE_RANDOM_SEQUENCE_SIZE` | 32 | `gnb_index_frame_type.h:40` |
| `INDEX_ATTACHMENT_SIZE` | 132 (4+128) | `gnb_index_frame_type.h:36` |
| `GNB_POST_ADDR_INTERVAL_TIME_SEC` | 60 | `gnb_index_frame_type.h:25` |
| `GNB_POST_ADDR_LIMIT_SEC` | 20 | `gnb_index_frame_type.h:28` |
| `GNB_REQUEST_ADDR_INTERVAL_SEC` | 39 | `gnb_index_frame_type.h:31` |
| `GNB_REQUEST_ADDR_LIMIT_USEC` | 20000 | `gnb_index_frame_type.h:34` |
| `GNB_NODE_MAX_DETECT_TIMES` | 32 | `gnb_node_type.h:93` |
| `GNB_UNIFIED_FORWARDING_NODE_EXPIRED_SEC` | 15 | `gnb_node_type.h:139` |
| `GNB_UNIFIED_FORWARDING_NODE_ARRAY_EXPIRED_SEC` | 90 | `gnb_node_type.h:142` |
| `GNB_CTL_KEEP_ALIVE_TS` | 15 | `gnb_ctl_block.h:107` |
| `GNB_MAX_NODE_RING` | 128 | `gnb_node_type.h:161` |
| 默认 MTU | 1280 | `gnb_argv.c:155` |
| 默认加密算法 | `xor` | `gnb_argv.c:157` |

PING 间隔 37 s / 更新超时 75 s 见 `gnb_map_visualization_prompt.md:142-143`。