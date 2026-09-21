# SPEC: 节点密钥与鉴权（node-keying）

> 更新：2026-09-21
> 适用：`mynet_gnb`（数据面，**只读参考，不改动**）、`mynet_agent`（`mynetd` 实现）、`mynet_back`（校验端）、`mynet_gateway`
> 依据：`mynet_gnb/src/libs/ed25519/*`、`mynet_gnb/src/src/gnb_node.c`、`mynet_agent-integration/internal/utils/gnb_ed25519.go`、`mynet_agent-integration/internal/daemon/monitor/monitor.go`、`mynet_tui/internal/{utils,tui}/gnb_ed25519.go`、`spec/plugins.md`
> 状态：**现状契约**（依据源码实测；gateway 鉴权方案见 §5，属设计稿）

---

## 0. 先纠正一个说法

**「GNB 是 Ed25519 鉴权」不准确。** 源码里是：

| 层 | 实际操作 | 出处 |
|----|---------|------|
| **密钥生成** | seed → SHA512 → clamp → `ge_scalarmult_base` 派生公钥（**不是** stdlib 一把梭） | `libs/ed25519/keypair.c` |
| **GNB 协议控制帧** | `ed25519_sign` / `ed25519_verify`（对 ping/pong/detect/index 等帧签名） | `gnb_node_worker.c` / `gnb_index_worker.c` 等 |
| **GNB 数据面** | **`ed25519_key_exchange`**：ed25519 私钥 × 对端公钥 → **edwards→montgomery 转换 → X25519 式 DH → `shared_secret`** | `gnb_node.c:74` |
| **节点 ↔ back 心跳** | **HMAC-SHA256**，密钥 = **GNB 派生出的 32 字节公钥**（**不是** ed25519 签名） | `monitor.go`、`spec/plugins.md:195` |

> 用户原话：**「gnb 不是 Ed25519 鉴权，是 Ed25519 后又做了一个操作」** —— 那个操作就是 `ed25519_key_exchange`（同一对 ed25519 密钥，签名之外再做一次 DH 密钥协商）。

---

## 1. GNB ed25519 密钥格式与派生

### 1.1 生成流程（`keypair.c`）

```c
void ed25519_create_keypair(public_key, private_key, seed) {
    sha512(seed, 32, private_key);       // ① seed(32B) --SHA512--> private_key(64B)
    private_key[0]  &= 248;              // ② clamp：清低 3 位
    private_key[31] &= 63;               // ③ clamp：清高 2 位
    private_key[31] |= 64;               // ④ clamp：置 bit6
    ge_scalarmult_base(&A, private_key); // ⑤ 标量乘基点
    ge_p3_tobytes(public_key, &A);       // ⑥ → 32B 公钥
}
```

Go 侧等价实现：`mynet_agent/internal/utils/gnb_ed25519.go` 的 `GenerateGNBKeyPairFromSeed`（逐位对齐上述 C 逻辑，无 CGO）。

### 1.2 存储格式

| 项 | 长度 | hex | 说明 |
|----|------|-----|------|
| seed | 32 B | — | **不落盘**（GNB 私钥文件里没有它） |
| private key | 64 B | 128 字符 | = `SHA512(seed)`；前 32B 是 clamp 后的标量，后 32B 用于签名时的 nonce 派生 |
| public key | 32 B | 64 字符 | = `ge_scalarmult_base(clamped_scalar)` |

⚠️ **GNB 的 64 字节私钥 = `SHA512(seed)`，不是** Go 标准 `ed25519.PrivateKey` 的 `seed || SHA512(seed)` 布局。因此：
- 拿到 GNB 私钥**无法反推 seed**（`CreateGNBFromExistingScalar` 里 `Seed: nil` 就是这个原因）
- 与 stdlib ed25519 互操作时必须显式转换（`ConvertStandardToGNB`）

### 1.3 公钥派生（心跳/HMAC 用的就是这一步）

```go
// mynet_agent/internal/utils/gnb_ed25519.go
GNBPublicKeyFromPrivateKey(processedPrivateKey)  // 32B processed scalar → 32B public key
// 等价 C：ge_scalarmult_base + ge_p3_tobytes
```

`monitor.go:232` 在创建监控服务时调用它，把结果存进 `s.publicKey`。

---

## 2. GNB 协议层：签名（控制帧）

`ed25519_sign(signature, message, len, public_key, private_key)` — `libs/ed25519/sign.c`，是标准 RFC 8032 结构：

```
r       = SHA512(private_key[32..63] || message) ; sc_reduce(r)
R       = r · B                          ; signature[0..31]
hram    = SHA512(R || public_key || message) ; sc_reduce(hram)
S       = hram · private_key[0..31] + r      ; signature[32..63]
```

**调用点**（控制帧，`lite_mode` 关闭时才启用）：

| 帧 | 文件 |
|----|------|
| `detect_addr_frame` | `gnb_detect_worker.c` / `gnb_index_worker.c` / `gnb_secure_index_worker.c` |
| `uf_node_notify_frame` | `gnb_node_worker.c` |
| `node_ping_frame` / `node_pong_frame` / `node_pong2_frame` | `gnb_node_worker.c` |
| `post_addr_frame` / `request_addr_frame` / `push_addr_frame` / `echo_addr_frame` | `gnb_secure_index_worker.c` |
| LAN 发现 `node_ping_frame` | `es/gnb_discover_in_lan.c` |

验签用**对端公钥**：`ed25519_verify(sig, &data, len, src_node->public_key)`。验签失败即丢弃（`lite_mode` 下跳过验签，密钥置零）。

### 2.1 与 Go stdlib 的互操作性（**gateway 零依赖的依据**）

`libs/ed25519/verify.c` 实现的就是标准 RFC 8032 验签方程：

```
h  = SHA512(R || A || M) mod L
R' = h·(−A) + S·B           // ge_double_scalarmult_vartime
判定 R' == R
```

（另有 `signature[63] & 224` 的高位检查与 `ge_frombytes_negate_vartime` 的公钥解压校验。）

**结论**：

| 侧 | 能否直接用 Go stdlib | 原因 |
|----|---------------------|------|
| **验签** | ✅ **可以**，`crypto/ed25519.Verify(pub, msg, sig)` 直接互通 | 公钥是标准的压缩 Edwards 点编码，签名是标准 `R‖S`，方程同 RFC 8032 |
| **签名** | ❌ 不可以直接用 `ed25519.Sign` | 私钥**存储格式**非标准：GNB 存的是 `SHA512(seed)`（§1.2），而 stdlib 要 `seed‖SHA512(seed)`；且 nonce 用 `private_key[32..63]` |

**对本项目的影响**：
- **`mynet_gateway` 只做验签** → 可**纯 stdlib（`crypto/ed25519`）、零外部依赖**，正好契合「极致轻量」
- **`mynetd`（签名侧）** 需要按 `sign.c` 自行实现签名，或把 GNB 私钥转成 stdlib 布局；现有 `internal/utils/gnb_ed25519.go` 已覆盖**密钥派生**，缺的是 **sign**
- 这也是为什么 gateway 不需要引入任何 crypto 库，而 agent 侧要补一个签名函数

---

## 3. GNB 数据面：`ed25519_key_exchange` —— 「Ed25519 后又做的那个操作」

### 3.1 调用点（`src/gnb_node.c:74`）

```c
if (gnb_core->conf->local_uuid != uuid64) {
    gnb_load_public_key(gnb_core, uuid64, node->public_key);
    ed25519_key_exchange(node->shared_secret, node->public_key,
                         gnb_core->ed25519_private_key);
} else {
    memcpy(node->public_key, gnb_core->ed25519_public_key, 32);
    memset(node->shared_secret, 0, 32);
    memset(node->crypto_key, 0, 64);   // 自身节点：无共享密钥
}
```

每个对端节点加载时做一次：**用本节点 ed25519 私钥 × 对端 ed25519 公钥 → `shared_secret`(32B)**，后续派生 `crypto_key`(64B) 用于数据面。

### 3.2 算法（`libs/ed25519/key_exchange.c`）

```
e = private_key[0..31]；再 clamp（&=248, &=63, |=64）
x1 = edwardsY → montgomeryX
     // CodesInChaos: montgomeryX = (edwardsY + 1) · inverse(1 − edwardsY) mod p
montgomery ladder：pos 254→0 位扫描标量 e（fe_cswap 交换，mul121666 等）
shared_secret = x2 · inverse(z2)          // 32B
```

即：**ed25519 曲线上的密钥 → 双有理映射到 Curve25519 Montgomery 形式 → 做 X25519 式 DH**。

> 这就是「不是单纯的 Ed25519 鉴权」的核心：同一对 ed25519 密钥被**两用**——
> ① 控制帧 `ed25519_sign/verify`（身份认证）；② `ed25519_key_exchange`（密钥协商 → 数据面加密）。

### 3.3 lite_mode

`lite_mode` 下：ed25519 私钥/公钥全置零；`public_key` 用 node_id 网络序填充；`shared_secret`/`crypto_key` 置零。**无签名、无密钥协商**。

---

## 4. 节点 ↔ back 心跳：HMAC-SHA256（不是 ed25519 签名）

### 4.1 实现（`mynet_agent/internal/daemon/monitor/monitor.go`）

```go
// NewMonitorService：从私钥派生公钥，作为 HMAC 密钥
publicKey, _ := utils.GNBPublicKeyFromPrivateKey(processedScalar)  // 32B
s := &monitorService{ publicKey: publicKey }   // 注释原文：
                                               // "从私钥派生的公钥(GNB格式32字节)，用于HMAC签名"

// SendHeartbeat：
signData := json.Marshal(HeartbeatData 但置 NodeMonitor=nil)   // 签名体不含 node_monitor
message  := fmt.Sprintf("%s|%s|%d|%s", "POST", "/api/v1/monitor/heartbeat", timestamp, signData)
h        := hmac.New(sha256.New, s.publicKey)                 // ← HMAC-SHA256，密钥=公钥
signature := base64.StdEncoding(h.Sum(nil))
```

请求头：`X-Node-Id` / `X-Timestamp` / `X-Node-Signature`；`Content-Encoding: gzip`（载荷 >1KB 时）。

### 4.2 与 `spec/plugins.md` 一致

```
util.hmac_sha256(pub_hex, method .. "|" .. path .. "|" .. ts .. "|" .. body_json)
HMAC-SHA256("PATCH|/api/v2/monitor/commands/{id}/result|{ts}|{body_json}", hex_key) → base64
```

命令回报同签名方式。

### 4.3 签名体范围

| 字段 | 是否进签名 |
|------|-----------|
| `node_id` / `timestamp` / `cpu_usage` / `memory_usage` / `disk_usage` / `connection_count` / `vpn_interfaces` | ✅ |
| `node_monitor`（node_self / peers_status） | ❌ **不进签名**（体量大；先置 nil 再序列化） |

### 4.4 ⚠️ 安全性质（必须知道）

HMAC 密钥 = **节点公钥**。而公钥：
- 存在 back 的 `node_keys.public_key`
- 写在节点上 `PublicKeyPath`，权限 **0644（全局可读）**

因此这**不是非对称鉴权，而是「把公钥当共享秘密用」的对称方案**：
- 双方都能算出签名 → 能互通
- 但**任何能读到 node_keys 或节点公钥文件的一方都能伪造该节点的心跳**
- 私钥在此链路中不参与运算（只用于派生公钥）

**结论**：现有心跳鉴权的强度 = 「公钥的保密程度」，而公钥按设计是可公开的。这是**已知弱点**，不是实现 bug。

### 4.5 back 侧现状

`NodeHeartbeatAuthMiddleware` 仍是空壳：

```php
// TODO: Implement Ed25519 signature verification
return $next($request);
```

即**当前未做任何校验**，agent 在签、back 不验。这是 `control-plane.md` §6.2 缺口 #1。

---

## 5. gateway 鉴权方案（设计稿）

### 5.1 不复用心跳的 HMAC 方案

§4.4 已说明心跳 HMAC 的弱点。gateway 作为**节点面唯一鉴权边界**，应采用**真非对称**方案：

```
gateway → 节点：challenge = random(32B) + ts
节点 → gateway：sig = ed25519_sign(challenge, 本节点私钥)   // GNB 格式 ed25519
gateway 校验：ed25519_verify(sig, challenge, node_keys.public_key)
```

- **私钥永不出节点**（只用于签名运算）
- 公钥可公开存储，泄露不影响认证强度
- 复用节点已有的 GNB ed25519 密钥对，无需新增密钥体系

### 5.2 三层密钥/鉴权职责

| 通道 | 方案 | 密钥用途 |
|------|------|---------|
| **GNB 数据面**（节点↔节点） | `ed25519_key_exchange` → `shared_secret` → `crypto_key` | 密钥协商 + 加密 |
| **GNB 控制帧**（节点↔节点） | `ed25519_sign` / `verify` | 帧完整性/身份 |
| **节点 ↔ back/gateway**（控制面） | **目标**：Ed25519 challenge-response<br>**现状**：HMAC-SHA256（公钥作密钥，back 侧尚未校验） | 控制面鉴权 |

### 5.3 过渡

1. back 心跳验签：先按**现状方案**实现（HMAC-SHA256 + 公钥），把「不校验」这个洞先堵上
2. gateway 上线时启用 Ed25519 challenge-response
3. back 的心跳通道随 gateway 中继一并收敛，HMAC 方案自然退役

> 不建议为 HMAC 方案单独投入强化——它本就要被 gateway 替代。先堵洞、再换代。

---

## 6. 驱动差异

| | GNB | WireGuard |
|---|-----|-----------|
| 密钥体系 | GNB 定制 ed25519（`SHA512(seed)` 64B 私钥 + clamp + scalarmult） | X25519（`sodium_crypto_box_keypair` 等价） |
| 节点↔节点协商 | `ed25519_key_exchange`（edwards→montgomery DH） | X25519 原生 DH（AllowedIPs + preshared key 可选） |
| 数据面加密 | `crypto_key`(64B) 由 `shared_secret` 派生 | Noise 协议握手 |
| 控制面心跳签名 | 现状 HMAC-SHA256(公钥)；目标 Ed25519 签名 | 应同样走控制面统一方案，不复用 WG 密钥做控制面鉴权 |

> 控制面鉴权**不应绑定驱动**。节点无论底层是 gnb 还是 wg，对 back/gateway 的身份都应是同一套（建议统一使用节点身份密钥做 Ed25519 challenge-response），驱动密钥只用于数据面。

`back` 生成密钥时按 `vpn_type` 分派（见 `NodeKeyDomainService`：`generateGnbKeyPair` / `generateWireGuardKeyPair`）；控制面鉴权密钥的归属需要在 L3 BundleEnvelope 中显式声明，避免混用。

---

## 7. 与其它 spec 的关系

| 文档 | 关系 |
|------|------|
| [`control-plane.md`](control-plane.md) | 传输与通道；其 §2.2 握手、§6 心跳缺口引用本文档的鉴权方案 |
| [`plugins.md`](plugins.md) | 心跳/命令回报的 HMAC 签名细节（`X-Node-Signature`、消息拼接格式） |
| [`driver-abstraction.md`](driver-abstraction.md) | L5 驱动边界；本文档 §6 说明控制面鉴权不绑定驱动 |
| [`data-models.md`](data-models.md) | `node_keys` 等数据结构 |

---

## 8. 待决

- [ ] 控制面身份密钥的归属：是否独立于驱动密钥（建议独立，避免 wg/gnb 混用）
- [ ] gateway challenge 的参数（nonce 长度、ts 窗口、重放缓存 TTL）
- [ ] back 心跳 HMAC 验签的具体格式确认（base64/hex、消息拼接与 `plugins.md` 对齐）
- [ ] WG 节点接入时控制面鉴权密钥如何下发（BundleEnvelope 字段）
