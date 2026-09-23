# WireGuard 协议与架构原理

> 本文只讲 WireGuard 自身的协议与架构原理，不涉及任何具体项目的落地方式。

## 0. 来源与可信度

| 来源 | 用途 | 说明 |
|---|---|---|
| `https://www.wireguard.com/protocol/` | 协议规范正文 | 官方规范，本文握手/数据包描述的主要依据 |
| `https://www.wireguard.com/` | 设计哲学、Cryptokey Routing | 官方概念总览 |
| `https://www.wireguard.com/known-limitations/` | 明确声明的局限 | 官方 |
| `wireguard-linux/drivers/net/wireguard/messages.h` | **消息结构体与数值常量** | 上游内核源码，数值的权威来源 |
| `wireguard-linux/.../timers.h` | 定时器接口 | 上游内核源码 |

**重要**：官方的 protocol 页面只给出**时间常量的名字**（`REKEY_AFTER_TIME` 等），
**不给数值**。本文所有数值均取自上游内核源码 `messages.h` 的
`enum limits` / `enum counter_values` / `enum cookie_values`，并标注行号。

作者 Jason A. Donenfeld。内核部分 GPLv2，其他组件 MIT / BSD / Apache 2.0 视上下文而定。

---

## 1. 设计哲学

WireGuard 的自我定位是「极简而现代」，目标是**比 IPsec 更快、更简单、更精简**，
同时避免其「巨大的麻烦」，并显著优于 OpenVPN（官方首页）。

六个界定性主张：

1. **简单易用如 SSH**。连接只需交换公钥，其余透明处理；
   不需要管理连接、不需要关心状态、不需要管守护进程。
2. **密码学保守**。只用一组经过密码学家审查的原语，不做「算法协商」——
   没有 cipher suite 谈判，就没有降级攻击面。
3. **最小攻击面**。目标是「用很少的代码实现、可被单个人完整审计」，
   刻意对比 `*Swan/IPsec` 与 `OpenVPN/OpenSSL` 那种「即使大团队审计也力不从心」的体量。
4. **高性能**。原语本身极快，且**实现在 Linux 内核内**。
5. **明确定义**。有技术白皮书，每个决策都有学术性的论证过程。
6. **边界清晰**：**密钥分发与配置推送被明确排除在 WireGuard 之外**，
   留给其他层处理——「以免我们最终得到 IKE 或 OpenVPN 那样的臃肿」
   （官方首页）。WireGuard 只模拟 SSH / Mosh 的模型：
   双方各持对方公钥，然后就能直接交换数据包。

第 6 点是最重要的设计边界。它意味着 WireGuard **不解决**：
谁该和谁通信、IP 地址如何分配、密钥如何轮换与吊销——这些是上层控制平面的事。

### 1.1 与「VPN 框架」的根本区别

WireGuard 是一个**网络接口**，不是一套管理系统。它创建 `wg0`（或 wg1、wg2…），
这个接口用 `ifconfig` / `ip-address` / `route` 等**普通网络工具**正常配置，
只有「WireGuard 特有的部分」才用 `wg(8)` 工具设置。接口表现为一个隧道接口。

---

## 2. 密码学原语

规范明确列出（`protocol/` "Primitives"）：

| 原语 | 用途 | 规范原文 |
|---|---|---|
| **ChaCha20 + Poly1305** | 对称加密 + 认证 | 使用 RFC7539 的 AEAD 构造 |
| **Curve25519** | ECDH 密钥交换 | |
| **BLAKE2s** | 哈希与带密钥哈希（keyed hashing） | RFC7693 |
| **SipHash24** | 哈希表键 | 用于内部哈希表 |
| **HKDF** | 密钥派生 | RFC5869 |

辅助函数（规范定义的记号）：

```
DH(priv, pub)          Curve25519 点乘，输出 32 字节
DH_GENERATE()          生成随机 Curve25519 私钥，32 字节
DH_PUBKEY(priv)        从私钥算公钥，32 字节
RAND(len)              len 字节随机
AEAD(key, counter, pt, auth)   ChaCha20Poly1305，nonce = 32 位零 || 64 位小端 counter
XAEAD(key, nonce, pt, auth)    XChaCha20Poly1305，24 字节随机 nonce
AEAD_LEN(plain_len)    plain_len + 16
HMAC(key, input)       HMAC-BLAKE2s(key, input, 32)
MAC(key, input)        Keyed-BLAKE2s(key, input, 16)
HASH(input)            BLAKE2s(input, 32)
TAI64N()               12 字节的 TAI64N 时间戳
CONSTRUCTION           37 字节 UTF-8: "Noise_IKpsk2_25519_ChaChaPoly_BLAKE2s"
IDENTIFIER             34 字节 UTF-8: "WireGuard v1 zx2c4 Jason@zx2c4.com"
LABEL_MAC1              8 字节 UTF-8: "mac1----"
LABEL_COOKIE            8 字节 UTF-8: "cookie--"
```

**注意 `AEAD` 的 nonce 构造**：`32 位零 || 64 位小端 counter`。
这是整个协议里唯一的 nonce 来源——数据面的 nonce 就是那个 64 位计数器，
握手消息则统一用 counter = 0。**没有随机 nonce，因此不存在 nonce 重用问题**（§5.3）。

---

## 3. 握手：Noise_IKpsk2

### 3.1 构造与性质

WireGuard 使用 Noise 框架的 **`Noise_IKpsk2_25519_ChaChaPoly_BLAKE2s`**
（规范称 "Noise_IK handshake"，但 CONSTRUCTION 字符串里带 `psk2`）。
所有包都走 **UDP**。

规范声明的安全性质：

- 避免密钥泄露伪装（key-compromise impersonation）
- 避免重放攻击
- 前向保密（perfect forward secrecy）
- 达到 "AKE security"
- **身份隐藏**（identity hiding）

**PSK 槽位**：WireGuard 支持一个可选的预共享密钥，混入公钥密码学之中
（用于后量子抗性等额外一层对称加密）。**未启用 PSK 时，规范假定其为
32 字节全零字符串**。

### 3.2 为什么是「无连接」的

规范对此有专门解释（"Connection-less Protocol"）：任何安全协议都需要状态，
所以有一个初始的简单握手来建立对称密钥。这个握手：

- **每几分钟发生一次**，以提供密钥轮换（前向保密）；
- **基于时间而非基于此前数据包内容**触发——这是**为了优雅地容忍丢包**；
- 有一个「脉冲」机制，自动检测握手过期并在需要时重新协商；
- **每个对端有独立的包队列**，以便在握手期间最小化丢包，
  同时为所有客户端提供稳定性能。

结论就是官方那句话：**你把设备启起来，剩下的一切自动完成**——
不需要请求重连、断开或重新初始化。

### 3.3 第一条消息：Initiator → Responder

规范给出完整伪代码。消息结构：

```
msg = handshake_initiation {
    u8  message_type                  // = 1
    u8  reserved_zero[3]
    u32 sender_index
    u8  unencrypted_ephemeral[32]
    u8  encrypted_static[AEAD_LEN(32)]      // 32 + 16 = 48
    u8  encrypted_timestamp[AEAD_LEN(12)]   // 12 + 16 = 28
    u8  mac1[16]
    u8  mac2[16]
}
```

**总长 = 4 + 4 + 32 + 48 + 28 + 16 + 16 = 148 字节**。

计算过程（逐步）：

```
initiator.chaining_key = HASH(CONSTRUCTION)
initiator.hash         = HASH(HASH(chaining_key || IDENTIFIER) || responder.static_public)

initiator.ephemeral_private = DH_GENERATE()
msg.unencrypted_ephemeral   = DH_PUBKEY(initiator.ephemeral_private)
initiator.hash              = HASH(initiator.hash || msg.unencrypted_ephemeral)

// DH 1: 临时私钥 × 对端静态公钥
temp = HMAC(chaining_key, msg.unencrypted_ephemeral);  chaining_key = HMAC(temp, 0x1)
temp = HMAC(chaining_key, DH(initiator.ephemeral_private, responder.static_public))
chaining_key = HMAC(temp, 0x1)
key = HMAC(temp, chaining_key || 0x2)

msg.encrypted_static = AEAD(key, 0, initiator.static_public, initiator.hash)
initiator.hash       = HASH(initiator.hash || msg.encrypted_static)

// DH 2: 静态私钥 × 对端静态公钥
temp = HMAC(chaining_key, DH(initiator.static_private, responder.static_public))
chaining_key = HMAC(temp, 0x1)
key = HMAC(temp, chaining_key || 0x2)

msg.encrypted_timestamp = AEAD(key, 0, TAI64N(), initiator.hash)
initiator.hash          = HASH(initiator.hash || msg.encrypted_timestamp)

msg.mac1 = MAC(HASH(LABEL_MAC1 || responder.static_public), msg[0:offsetof(mac1)])
msg.mac2 = (无 cookie 时) 全零
           (有 cookie 时) MAC(initiator.last_received_cookie, msg[0:offsetof(mac2)])
```

**关键设计点**：

- **`initiator.hash` 的初值就绑定了 `responder.static_public`**。
  这意味着第一条消息只能被「真正的接收方」处理——**身份隐藏**由此而来：
  除了持有对应私钥的接收者，没人能算出正确的 hash，也就无法解密。
- **静态公钥是被加密传输的**（`encrypted_static`），不是明文。
  这是「身份隐藏」的具体机制。代价见 §8.2：**如果 responder 的私钥泄露
  且攻击者有历史握手的流量日志，攻击者能推断出「谁发过握手」**（但不知道内容）。
- **两个 DH**：先 (临时 × 对端静态)，再 (静态 × 对端静态)。
- **timestamp 用 TAI64N（12 字节）**，用于抗重放（§6.2）。

### 3.4 第二条消息：Responder → Initiator

```
msg = handshake_response {
    u8  message_type                  // = 2
    u8  reserved_zero[3]
    u32 sender_index
    u32 receiver_index
    u8  unencrypted_ephemeral[32]
    u8  encrypted_nothing[AEAD_LEN(0)]     // 0 + 16 = 16
    u8  mac1[16]
    u8  mac2[16]
}
```

**总长 = 4 + 4 + 4 + 32 + 16 + 16 + 16 = 92 字节**。

```
responder.ephemeral_private = DH_GENERATE()
msg.sender_index   = responder.sender_index
msg.receiver_index = initiator.sender_index
msg.unencrypted_ephemeral = DH_PUBKEY(responder.ephemeral_private)
responder.hash = HASH(responder.hash || msg.unencrypted_ephemeral)

temp = HMAC(chaining_key, msg.unencrypted_ephemeral);  chaining_key = HMAC(temp, 0x1)

// DH 3: 临时 × 临时
temp = HMAC(chaining_key, DH(responder.ephemeral_private, initiator.ephemeral_public))
chaining_key = HMAC(temp, 0x1)

// DH 4: 临时 × 对端静态
temp = HMAC(chaining_key, DH(responder.ephemeral_private, initiator.static_public))
chaining_key = HMAC(temp, 0x1)

// PSK 混入（psk2 的位置）
temp = HMAC(chaining_key, preshared_key);   chaining_key = HMAC(temp, 0x1)
temp2 = HMAC(temp, chaining_key || 0x2)
key   = HMAC(temp, temp2 || 0x3)
responder.hash = HASH(responder.hash || temp2)

msg.encrypted_nothing = AEAD(key, 0, [空], responder.hash)
```

**总共 4 次 DH**（2 次在第一条、2 次在第二条），加上 PSK 的 HMAC 混入。
`encrypted_nothing` 是**零长度明文 + 16 字节认证标签**——它的唯一作用是
证明 responder 真的完成了握手并持有正确密钥。

`mac1` 这次用 **initiator 的静态公钥**：
`MAC(HASH(LABEL_MAC1 || initiator.static_public), ...)`。

### 3.5 密钥确认的时序约束（容易忽略但很重要）

规范明确要求：

> 握手完成后，**initiator 可以立即发加密会话包，但 responder 不行**。
> responder 必须等收到 initiator 的第一个加密会话包，才能使用新会话——
> 这是为了提供**密钥确认**。因此在新会话被确认前，responder 要么排队等发，
> 要么继续用之前的会话（如果还有效）。
> 所以 initiator 收到 response 后，**如果没有数据要发，应该发一个空包**来做这个确认。

这就是 WireGuard 的 keepalive 空包在协议层的必要性来源——
它不是「心跳」那么简单，而是**密钥确认机制的一部分**。

---

## 4. 数据密钥派生

握手两条消息完成后，双方各自派生收发密钥：

```
temp1 = HMAC(chaining_key, [空])
temp2 = HMAC(temp1, 0x1)
temp3 = HMAC(temp1, temp2 || 0x2)

initiator.sending_key = temp2      initiator.receiving_key = temp3
responder.receiving_key = temp2    responder.sending_key = temp3
```

即 initiator 的「发」就是 responder 的「收」，反之亦然。
双方的 `sending_key_counter` / `receiving_key_counter` 都从 0 开始。

**派生完成后，所有此前的 chaining key、临时密钥、hash 全部清零**（规范原文：
"all previous chaining keys, ephemeral keys, and hashes are zeroed out"）。
这是前向保密的具体落实——握手状态不残留。

---

## 5. 数据包

### 5.1 格式

```
msg = packet_data {
    u8  message_type                  // = 4
    u8  reserved_zero[3]
    u32 receiver_index
    u64 counter
    u8  encrypted_encapsulated_packet[]
}
```

对应内核结构（`messages.h:105-110`）：

```c
struct message_data {
    struct message_header header;   // __le32 type
    __le32 key_idx;                 // 即 receiver_index
    __le64 counter;
    u8 encrypted_data[];
};
```

**头部固定 16 字节**（4 + 4 + 8）。

填充规则（规范）：

```
encapsulated_packet = encapsulated_packet || zero padding   // 补齐到 16 的倍数
msg.encrypted_encapsulated_packet = AEAD(sending_key, counter, encapsulated_packet, [空])
```

内核常量 `MESSAGE_PADDING_MULTIPLE = 16`（`messages.h:116`）。

**封装开销**：16（头）+ 16（Poly1305 认证标签）= **32 字节**，另加填充到 16 字节倍数。
这与本地那份 `wireguard_protocol.h` 里 `WG_MIN_PACKET_SIZE 32` 一致。

**MTU 推算**（实践常识，非规范数值）：以太网 1500 − 外层 IPv6 头 40 − UDP 头 8 −
WireGuard 32 = **1420**。这也是社区普遍推荐把 `wg0` 的 MTU 设为 1420 的原因
（若只考虑 IPv4，理论值可达 1440）。

### 5.2 AEAD 的 AAD 是空的

注意 `AEAD(..., auth text = [空])`——数据包的认证标签**不覆盖头部**
（`receiver_index` 和 `counter`）。这由规范的设计承担：counter 参与 nonce 构造，
所以篡改 counter 会导致解密失败；`receiver_index` 只用于选密钥，选错就解不开。

### 5.3 Nonce 与重放窗口

规范专门一节（"Nonce Reuse & Replay Attacks"）：

> **Nonce 从不重用**。使用 64 位计数器，且**不能回绕**。
> 但 UDP 有时会乱序投递。因此我们使用**滑动窗口**：
> 记录收到的最大 counter 以及**大约 2000 个先前值**的窗口，
> **在验证认证标签之后**才检查窗口。这样既避免了重放攻击，
> 又保证 nonce 永不重用，同时维持 UDP 的乱序投递性能。

内核的实际参数（`messages.h:34-38`）：

```c
enum counter_values {
    COUNTER_BITS_TOTAL    = 8192,
    COUNTER_REDUNDANT_BITS = BITS_PER_LONG,          // 64 位平台上为 64
    COUNTER_WINDOW_SIZE   = COUNTER_BITS_TOTAL - COUNTER_REDUNDANT_BITS
};
```

即窗口是 **8192 − 64 = 8128 位**（64 位平台上），用位图实现。
规范里说的「大约 2000」是一个保守的通俗说法。

**「先验认证标签，再查窗口」的顺序是关键**：先验标签能防止攻击者
通过伪造 counter 来推进窗口、从而把合法包挤出窗口。

### 5.4 DiffServ / ECN

规范专门一节说明：

- **所有握手包 DSCP = 0x88（AF41）**，使其最不容易被丢弃（控制功能关键），
  ECN 置 00。内核常量 `HANDSHAKE_DSCP = 0x88`（`messages.h:126`）。
- **所有传输数据包 DSCP = 0**，因为**内层包的 DSCP 从不复制到外层**——
  以免泄露加密内层数据的信息。
- 但 **ECN 位会被复制**（按 RFC6040 的逻辑）。

这个细节体现了 WireGuard 对「元数据泄露」的谨慎：QoS 标记会泄露内层流量类型，
所以不复制；ECN 是拥塞控制必需的，所以复制。

---

## 6. 时间常量与密钥轮换

### 6.1 完整数值表

来自上游内核 `messages.h`（`enum limits`，`:40-54`）：

| 常量 | 值 | 含义 |
|---|---|---|
| `REKEY_AFTER_MESSAGES` | `1ULL << 60` | 用同一密钥发送超过此数则重新握手 |
| `REJECT_AFTER_MESSAGES` | `U64_MAX − COUNTER_WINDOW_SIZE − 1` | 超过则丢包（约 2^64 − 8129） |
| `REKEY_TIMEOUT` | **5 秒** | 握手重试间隔；也是握手发起的严格限频 |
| `REKEY_TIMEOUT_JITTER_MAX_JIFFIES` | `HZ / 3` | 最大 333 ms 抖动 |
| `REKEY_AFTER_TIME` | **120 秒** | 会话密钥老化后重新握手 |
| `REJECT_AFTER_TIME` | **180 秒** | 密钥超过此年龄则丢包 |
| `INITIATIONS_PER_SECOND` | 50 | 每设备握手发起速率上限 |
| `MAX_PEERS_PER_DEVICE` | `1U << 20` | 约 100 万对端 |
| `KEEPALIVE_TIMEOUT` | **10 秒** | 静默后发空包 |
| `MAX_TIMER_HANDSHAKES` | `90 / REKEY_TIMEOUT` = **18** | 放弃前的最大握手次数 |
| `MAX_QUEUED_INCOMING_HANDSHAKES` | 4096 | |
| `MAX_STAGED_PACKETS` | 128 | 握手期间暂存待发包数 |
| `MAX_QUEUED_PACKETS` | 1024 | |

Cookie 相关（`enum cookie_values`，`:27-32`）：

| 常量 | 值 | 含义 |
|---|---|---|
| `COOKIE_SECRET_MAX_AGE` | **120 秒** | 服务端 cookie 密钥轮换周期 |
| `COOKIE_SECRET_LATENCY` | 5 秒 | 新旧密钥并存容忍 |
| `COOKIE_NONCE_LEN` | 24 | XChaCha20Poly1305 nonce |
| `COOKIE_LEN` | 16 | cookie / mac 长度 |

### 6.2 规范列出的触发规则（逐条）

规范 "Connection-less Protocol" 一节完整列出，逐条对应上面的数值：

1. 握手发起后若未收到响应，**`REKEY_TIMEOUT` + 抖动后重试**，
   抖动是 0–333 ms 的随机值。
2. 若从某对端收到包，但 **`KEEPALIVE`(10s) 内没回过包，就发一个空包**。
3. 若向某对端发过包，但之后 **`KEEPALIVE + REKEY_TIMEOUT`(15s) 内没收到包，
   则发起新握手**。
4. 若没有交换新密钥，所有临时私钥与对称会话密钥在 **`REJECT_AFTER_TIME × 3`(540s)
   后清零**。
5. 发完一个包后，若用该密钥发送的包数**超过 `REKEY_AFTER_MESSAGES`**，发起新握手。
6. 发完一个包后，若**发送者是原始 initiator** 且当前会话密钥已存在
   `REKEY_AFTER_TIME`(120s)，发起新握手。
   **若发送者是原始 responder，则不像 initiator 那样在 120s 后重新发起。**
7. 收到一个包后，若**接收者是原始 initiator** 且当前会话密钥已存在
   `REKEY_AFTER_TIME − KEEPALIVE_TIMEOUT − REKEY_TIMEOUT`(105s)，发起新握手。
8. **握手每 `REKEY_TIMEOUT`(5s) 最多发起一次**，严格限频。
9. 若会话 counter 超过 `REJECT_AFTER_MESSAGES`，或密钥年龄超过
   `REJECT_AFTER_TIME`，**丢包**。
10. 尝试握手满 **`REKEY_ATTEMPT_TIME`(90s) 后放弃重试，并清空所有已排队待发包**。
    若有包被显式排队，则此计时器重置。

第 6、7 条的**不对称**是有意的：由 initiator 承担重新握手的主导责任，
避免双方同时发起。

未来工作：把 `REKEY_TIMEOUT` 改为指数退避。

### 6.3 为什么「基于时间」而非「基于包数」

规范点明了理由：**为了优雅地容忍丢包**。若基于「发了 N 个包就轮换」，
丢包会导致两端计数不一致，进而状态失步。用时间做触发条件，
双方各自独立判断，不需要同步计数——这是「无连接」哲学的延伸。

代价是**依赖系统时钟的单调性**，这正是官方列为已知局限的一条（§8.6）。

---

## 7. 抗 DoS

### 7.1 三条设计原则

规范 "DoS Mitigation" 一节：

1. **第一条握手消息就要求认证**，因为这样**服务端不需要为潜在的非认证消息
   分配任何状态**。事实上，服务端对未授权客户端**完全不响应——沉默且不可见**。
   这就避免了「允许任何状态在响应未认证包时被创建」导致的 DoS 漏洞。
2. **但第一条包就带认证会引入重放问题**：攻击者可以重放初始握手消息，
   诱使服务端重新生成临时密钥，从而**断开合法客户端连接**（不影响消息安全性）。
   因此引入 **TAI64N 时间戳**：服务端记录每个客户端的最大时间戳，
   **丢弃时间戳小于等于该值的包**。服务端重启丢失此状态也没关系——
   重启后客户端会用更大的时间戳，旧的握手包无法干扰已建立的会话。
3. **DH 计算 CPU 昂贵**。为了抵御 CPU 耗尽攻击，服务端在**负载下**可以选择
   不处理握手消息，而是回复 cookie reply。

### 7.2 mac1 与 mac2

- **`mac1`**：所有消息都必须有，MAC 的密钥是
  `HASH(LABEL_MAC1 || receiver.static_public)`。
  它证明**发送者知道接收者的公钥**（公钥本身是公开的，
  所以 mac1 **可被伪造**——官方局限里明说了）。
  作用是把「不知道公钥的随机垃圾流量」挡在 DH 计算之前。
- **`mac2`**：只有在收到过 cookie 之后才非零，MAC 的密钥是**服务端发来的 cookie**。
  服务端**在负载下只接受带有效 mac2 的包**。

服务端收到消息后计算同样的 MAC 并比较：
**必须拒绝 mac1 无效的消息；负载下必须拒绝 mac2 无效的消息。**

### 7.3 Cookie Reply

当收到 **mac1 有效但 mac2 全零或无效**、且**服务端处于负载下**时，
服务端回复：

```
msg = packet_cookie_reply {
    u8  message_type                  // = 3
    u8  reserved_zero[3]
    u32 receiver_index
    u8  nonce[24]
    u8  encrypted_cookie[AEAD_LEN(16)]    // 16 + 16 = 32
}
```

**总长 = 4 + 4 + 24 + 32 = 64 字节**。

```
msg.nonce = RAND(24)
cookie = MAC(responder.changing_secret_every_two_minutes, initiator.ip_address)
msg.encrypted_cookie = XAEAD(HASH(LABEL_COOKIE || responder.static_public),
                             msg.nonce, cookie, last_received_msg.mac1)
```

**cookie 的本质是「对发送者 IP 地址的 MAC」**，密钥是服务端每两分钟轮换的秘密
（`COOKIE_SECRET_MAX_AGE = 120`）。规范原文：

> cookie 两分钟后过期，是发送者 IP 地址的 MAC，密钥是每两分钟变化的服务端秘密。
> **这提供了 IP 所有权的证明，从而可以正确地做限频。**

这是整个抗 DoS 机制的精髓：**无状态**。服务端不需要为每个半开连接存状态，
只需要持有那个两分钟轮换的秘密——cookie 本身就是「服务端曾经验证过你的 IP」
的可验证凭证。攻击者无法伪造合法 IP 的 cookie，
因此无法把攻击流量伪装成大量不同源 IP。

注意 `encrypted_cookie` 的 AAD 是 `last_received_msg.mac1`，
把 cookie 绑定到具体的握手消息上。

---

## 8. Cryptokey Routing 与 Roaming

### 8.1 核心概念

官方称之为 **Cryptokey Routing**——「**公钥与允许的隧道 IP 列表的简单关联**」。
每个接口有一个私钥和一串 peer；每个 peer 有一个公钥和一组 `AllowedIPs`。

**发送路径**（官方逐步描述）：

1. 这个包要去 192.168.30.8。**这是哪个 peer？** 查表 → peer ABCDEFGH。
   （若不属于任何已配置 peer，**丢包**。）
2. 用 peer ABCDEFGH 的公钥**加密整个 IP 包**。
3. peer ABCDEFGH 的远端 endpoint 是什么？→ `216.58.211.110:53133`。
4. 通过 UDP 把密文发过去。

**接收路径**：

1. 刚从 `98.139.183.24:7361` 收到包，解密！
2. 解密并通过认证 → 属于 peer LMNOPQRS。
   **记住 peer LMNOPQRS 的最新 Internet endpoint 是 `98.139.183.24:7361`。**
3. 解密后的明文源 IP 是 192.168.43.89。**peer LMNOPQRS 被允许以这个源 IP 发给我们吗？**
4. 允许 → 接受进接口；不允许 → **丢包**。

### 8.2 一句话概括

官方原文：

> 换句话说，**发送时，allowed IPs 列表表现得像一张路由表；
> 接收时，allowed IPs 列表表现得像一个访问控制列表。**

这张表就叫 **Cryptokey Routing Table**。

**由此带来一个重要的运维后果**：因为所有包都经过加密与认证，
且「对端身份」与「允许的 IP」之间是紧耦合的，
管理员**不需要 IPsec 那种复杂的防火墙扩展**——
只需要匹配「是不是来自这个 IP？在这个接口上？」，
就能确信包是安全且可信的。这让 `iptables` 规则的行为更可预期。

配置示例（官方首页）：

```ini
[Interface]
PrivateKey = <server private>
ListenPort = 51820

[Peer]
PublicKey = <client1 public>
AllowedIPs = 10.192.122.3/32, 10.192.124.1/24

[Peer]
PublicKey = <client2 public>
AllowedIPs = 10.192.122.4/32, 192.168.0.0/16
```

注意服务端**每个 client 的 `AllowedIPs` 就是他「被允许使用的源地址」**。
客户端通常写 `AllowedIPs = 0.0.0.0/0`（全通配），
意思是「所有去往任意目的地的流量都走这个 peer」。

### 8.3 内层/外层地址族可任意组合

> IPv4 与 IPv6 的任意组合都可以用于任意字段。
> WireGuard **完全有能力在必要时把一个封装在另一个里面**。

### 8.4 Roaming

客户端配置里有服务端的初始 endpoint（这样在收到加密数据前就知道往哪发）。
**服务端配置里没有任何 peer 的初始 endpoint**——因为服务端通过
「**正确地认证过的数据从哪里来**」来发现 peer 的 endpoint。

若服务端自己换了 endpoint 并发数据给客户端，客户端也会同样发现并更新。
**双方都把加密数据发往「最近一次成功认证解密」的 IP endpoint**。
因此**两端都有完整的 IP 漫游**。

这个机制**不需要额外的往返**，也不做额外的认证——代价见 §9.4。

### 8.5 容器友好

WireGuard 使用**创建接口时所在的 network namespace** 收发加密包。
所以可以先在能访问互联网的主 namespace 里创建接口，
再把它移进 Docker 容器的 namespace 作为该容器**唯一的接口**——
这样该容器访问网络的唯一途径就是加密隧道。

### 8.6 与内核路由/防火墙的关系

WireGuard 是内核内的接口，所以：
- 路由由**普通系统路由表**决定（`ip route`），与 `AllowedIPs` 配合使用；
  `AllowedIPs` 决定「选哪个 peer」，系统路由决定「是否走 wg0」；
- 防火墙就是普通 `iptables`/`nftables`，不需要协议特定扩展。

---

## 9. 官方明确声明的局限

WireGuard 官方专门有一页 "Known Limitations"，逐条列出。**这些是理解
WireGuard 适用边界的关键，引用时务必保留原文的取舍语气。**

### 9.1 深度包检测（DPI）

> WireGuard **不专注于混淆**。混淆应该在 WireGuard **之上**的一层完成，
> WireGuard 专注于「用简单实现提供扎实的密码学」。
> 完全可以插入各种形式的混淆。

即：WireGuard 流量**特征明显**，可被识别与封锁。这是有意的分工。

### 9.2 TCP 模式

> WireGuard **明确不支持在 TCP 上做隧道**，因为 TCP-over-TCP 的网络性能
> 众所周知地糟糕。把 WireGuard 的 UDP 包转成 TCP 是上层混淆的职责，
> 可以由 `udptunnel`、`udp2raw` 之类的项目完成。

### 9.3 硬件加密

ChaCha20Poly1305 在几乎所有通用 CPU 上软件实现极快。
当时专用硬件支持不多，但实践中不是问题——
CPU 的向量指令与 AES-NI 处于同一量级，有时甚至更快。

### 9.4 漫游被滥用（Roaming Mischief）

> WireGuard 的漫游**不需要额外往返或其他认证**，这意味着
> **一个主动中间人可以替换源 IP 地址**。
> 中间人本来就能重定向包（因为他是主动的），但可能存在这种情况：
> endpoint 地址被更新，中间人**在失去中间人位置之后仍能中继包**。
> 不过，**由于 WireGuard 惯常的认证加密，这些包对攻击者仍然不可解读**。
> 如果这是个问题，普通的防火墙可以把 WireGuard 的 socket 锁定到特定 IP。

还提到可能通过「TCP 序列号猜测游戏」让 WireGuard 服务端把包导向不受控的 IP。

**这是 §8.4 无认证漫游的直接代价**：安全性（机密性）不受影响，但**可达性/可用性**可能被影响。

### 9.5 身份隐藏与前向保密

> WireGuard 对**数据包**有前向保密（得益于握手），
> 但**握手本身用接收者的静态公钥加密发送者的公钥**。
> 这意味着：**接收者私钥泄露 + 历史握手流量日志 → 攻击者能推断出
> 「谁发送过握手」，但不知道其中的数据内容。**
> 类似地，`mac1` 是对接收者公钥做的，所以可以**试哈希**来猜测
> 某个包是否发给特定接收者（尽管 mac1 可被伪造）。
> 缓解措施包括**基于不可链接性预期轮换或重新生成密钥**。

**这是 §3.3 中「身份隐藏」的边界**：身份隐藏是「对被动观察者」的，
不是「对拿到私钥的攻击者」的。

### 9.6 后量子保密

> WireGuard 默认**不是**后量子安全的。
> 但 **PSK 参数可以用来增加一层后量子保密**。
> 如果公钥是哈希后传输而不是直接发送，它本可以是后量子安全的，
> 但这不是 Noise 框架的一部分（WireGuard 握手基于 Noise），
> 而且这种哈希技术也无法实现前向安全的后量子保密。
> **后量子安全的最佳赌注是在 WireGuard 之上跑一个真正的后量子握手，
> 然后把那个密钥插入 WireGuard 的 PSK 槽位。**

### 9.7 拒绝服务

> WireGuard 通过 mac1 与 mac2 具备一定的抗滥用能力，
> **但在 mac2 生效之前，ECDH 计算可能消耗可观的 CPU**。
> 不过实践中 **mac2 通常足够**。

### 9.8 不可靠的单调计数器

> WireGuard **使用系统时间作为可靠的单调计数器**。
> 如果它**向前跳**，用户可能 DoS 自己的密钥（之后无法有更大的值）；
> 或者控制系统时间的对手可以存下一个握手发起包供之后使用。
> 如果**向后跳**，握手同样会变得不可能。
> 因此**系统时间不应处于敌意对手的控制之下**。

这是 §6.3「基于时间触发轮换」的代价。

### 9.9 路由环路

> 目前在检测路由环路方面存在一些问题（本地与跨网络），
> 有各种技巧如「把外层 src 改成内层 src」。

---

## 10. 协议设计要点小结

| 维度 | WireGuard 的做法 |
|---|---|
| 传输层 | **仅 UDP**（明确拒绝 TCP 隧道） |
| 握手 | Noise_IKpsk2，**4 次 DH**，2 条消息完成 |
| 消息类型 | 1=握手发起(148B)、2=握手响应(92B)、3=cookie 回复(64B)、4=数据传输(16B 头) |
| 数据面加密 | **ChaCha20-Poly1305 AEAD**，nonce = 32 位零 ‖ 64 位小端 counter |
| 完整性 | **AEAD 认证标签（16 字节）**，头部不参与 AAD |
| 前向保密 | **有**（临时密钥 + 密钥清零；数据包层面有，握手层面受限见 §9.5） |
| 密钥轮换 | 时间驱动：120s 重协商、180s 强制丢弃、每 5s 最多一次握手 |
| 抗重放 | 64 位计数器**不可回绕** + **8128 位滑动窗口**（先验标签再查窗口） |
| 抗 DoS | mac1/mac2 + **无状态 cookie**（IP 所有权证明，120s 轮换） |
| 路由模型 | **Cryptokey Routing**：`AllowedIPs` 双向生效（发=路由表、收=ACL） |
| 漫游 | **端点随包自动更新**，无需额外往返或认证 |
| 状态 | 「无连接」：无会话建立/拆除概念，无协商，无保活协议（只有定时器） |
| 密钥分发 | **明确不在范围内**，留给上层 |
| 混淆 | **明确不在范围内**，留给上层 |
| 代码定位 | 内核实现，追求「单个人可完整审计」的体量 |

---

## 附：消息长度速算

| 消息 | 计算 | 总长 |
|---|---|---|
| 握手发起 | 4 + 4 + 32 + (32+16) + (12+16) + 16 + 16 | **148 B** |
| 握手响应 | 4 + 4 + 4 + 32 + (0+16) + 16 + 16 | **92 B** |
| Cookie 回复 | 4 + 4 + 24 + (16+16) | **64 B** |
| 数据传输（头部） | 4 + 4 + 8 | **16 B** |
| 数据传输（总开销） | 16 头 + 16 认证标签 | **32 B** + 填充至 16 倍数 |

对应内核结构体：`messages.h:80-110`（`message_handshake_initiation` /
`_response` / `_cookie` / `message_data`）。