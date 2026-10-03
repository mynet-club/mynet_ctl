# proxy.conf 设计 —— route.conf 的延续 + 热读取（v3）

> 状态：**实现设计 v3 · 待你确认后动代码**  
> 日期：2026-09-29  
> 定调：**系统 `ip route` 先过滤无需代理的流量；client 用 proxy.conf 决定代理流量走哪个地区出口；server 只要作为出口放行，交给出口网关**
> 风格：完全遵守 gnb（`_gnb_*_t` / `GNB_*` / `fscanf`+`sscanf` / `GNB_LOGn` / `gnb_heap_*`）  
> 关联：`spec/proxy-conf-design.md` v2 评审 6 条 + 新评审 3 条

---

## 0. 角色定调（本稿基线）

| 角色 | 配置 | 行为 |
|---|---|---|
| **client** | `proxy.conf`：group / target / exit | 决定「哪些目的走哪个出口」 |
| **server（出口机）** | `role server` + `proxy on` + `ingress_open on` | **放行**：收下后交出口网关 NAT 出网（与旧 `forward on` 同义） |
| **client+server** | 两者都要 | 先按 client 策略选路；本机作为出口时放行出网 |

`role` 是显式配置，不能通过是否存在 group 推断：

```ini
role client       # 仅按地区策略选择出口
role server       # 仅作为出口节点放行
role both         # 同时具备两种能力
```

其中 `role server` 至少需要 `proxy on`；要接收代理流量并交给本机 NAT，必须再写 `ingress_open on`。

**server 不需要** 一套无意义的 `[default]` 出口表；`role server` 下 `proxy on` 表示本机具备出口能力，`ingress_open on` 才实际接收代理流量。

---

## 1. proxy.conf 格式（client 策略）

```ini
# proxy.conf — route.conf 的延续（conf/<nodeid>/proxy.conf）
role client       # client / server / both，必须显式写
proxy on          # client 的准出兜底开
# ingress_open off   # 默认 off；client 不因 proxy on 自动放宽准入

[default]
exit 4997342800601732 w=20

[group us]
target 1.0.0.0/8
target 8.8.8.0/24
exit 4997342800601732 w=30 order=1
exit 8283289760467957 w=10 order=2

[group asia]
target 10.0.0.0/8
target 192.168.0.0/16
exit 3722810996882027 w=5
```

server 示例：

```ini
role server
proxy on
ingress_open on
```

group 名称表示目的地区域或业务区域，不表示 client/server 角色；实际可按需要配置 8～10 个或更多区域 group。

### 1.1 键

| 键 | 默认 | 语义 |
|---|---|---|
| `role client\|server\|both` | **无默认值** | 节点角色；缺失或非法值整文件拒载 |
| `proxy on\|off` | **off**（文件缺失=off） | 准出兜底；server 也需写 on 才启用出口能力 |
| `ingress_open on\|off` | **off** | 准入：跳过 route.conf 白名单（server 收包出网） |
| `[default]` | 可无 | 无 target 命中时的兜底组 |
| `[group <name>]` | | 策略组 |
| `target <ipv4-cidr>` | | v1 仅 IPv4 |
| `exit <uuid64> w=<n> [order=<n>]` | | 出口节点 + 权重 + 故障转移层 |

### 1.2 失败 / 缺省语义

| 情况 | 行为 |
|---|---|
| 文件不存在 | 发布 **disabled** 快照（proxy=off, ingress=off） |
| 文件被删除 | 同上（**不是**保留旧配置） |
| 语法错误 | **保留上一版** + 日志（避免半截） |
| `proxy` 键缺失 | off |
| `ingress_open` 键缺失 | **off** |
| `target` 含 `/0` 或非法 CIDR | **解析期报错**，整文件拒载，保留上一版 |
| `route.conf` 含 `/0` | 启动/重载时 **告警**（proxy 将永不参与） |

### 1.3 与系统路由的边界

`proxy.conf` 不负责决定所有本机流量是否进入代理。进入 gnb 之前，系统 `ip route` 已完成前置过滤：

- 私有地址段等不应代理的目的不会进入 gnb；
- 明确走本地直连或其他出口的目的不会进入 gnb；
- 进入 gnb 的主要是已经决定交给代理链处理的流量。

因此 `[default]` 是代理链内部的兜底出口，实际运行中通常覆盖大多数进入 gnb 的流量；地区 group 用于少数需要按地区选择不同出口的目的。`target` 仍必须保持精确，不能把系统前置过滤当成 proxy.conf 的校验替代品。

---

## 2. 匹配与选路（唯一算法）

### 2.1 匹配

```text
1. 所有 [group]（不含 default）收集 target 命中
2. 最长前缀胜出；同前缀 → 组书写序先者
3. 命中组选 exit；组内无可用出口 → DROP（不落 default）
4. 无 target 命中 → [default]
5. default 无可用出口 → DROP
```

角色约束：

- `role client`：必须存在至少一个可用策略出口（group 或 default），否则 proxy 流量 DROP；`ingress_open` 不得因 client 角色自动开启。
- `role server`：不执行 client 的 group 选路；`proxy on` 表示允许作为出口，`ingress_open on` 才允许准入后交给本机 NAT。
- `role both`：同时执行以上两套规则。

### 2.2 按**连接**稳定选路（非按包）

| 项 | 规则 |
|---|---|
| 连接表 key | `src_ip4, dst_ip4, proto, src_port, dst_port`（IPv4；ICMP 用 type/code 代端口） |
| TCP | 首包（SYN）建项；FIN/RST 后延时删除 |
| UDP | 首包建项；**idle 180s** 超时 |
| ICMP | echo 流；idle 60s |
| IP 分片 | 仅首片有端口；后续片按 `(src,dst,proto,ip_id)` 命中**分片缓存**，30s 超时 |
| NAT 映射 | 存在则 **优先旧出口**（连接表即 NAT 绑定） |
| 表满 | LRU 淘汰最旧；**淘汰后新包重选**（允许换出口） |
| gnb 重启 | 连接表清空，允许重选 |
| 热更新 | **不迁移**已有连接（查表命中即用旧出口） |

**连接表内存**：单独池，非 8KB 配置槽。这里的条数是本节点全部代理 flow 的总数，不是每个 group、每个出口或每个协议各自一份。建议 tiny 512 条、small 1024 条、large/huge 2048 条，达到上限后按 LRU 淘汰。

### 2.3 order / weight

```text
最小 order 层 → 该层内 weight 加权
「可用」= 在 uuid_node_map + 隧道 PONG(近期) 
         + （可选）健康探测
```

---

## 3. 出口可用性（配置快照 vs 运行时）

**锁内只读配置快照；运行时状态在锁外查。**

```text
读锁内：从快照取出候选 uuid 列表（按 order/weight 逻辑）
读锁外：逐个查 uuid_node_map + PONG/健康
         全失效 → 按 order 降层重试
         仍无 → DROP + 日志
```

| 问题 | 约定 |
|---|---|
| PONG 有效期 | `GNB_NODE_PONG_TTL_SEC` 默认 **60s**（随 detect 周期） |
| 上线/掉线竞态 | 以节点状态字段为准；不保证完美一致，失败即 DROP+日志 |
| 有 node 无路由 | 节点在 map 但无 tun 地址/路由 → 视为**不可用** |
| 全不可用 | 日志：`gen`、组名、dst、候选 uuid、失败原因 |

---

## 4. 并发热更新

### 4.1 线程模型

gnb 用 **pthread**（`gnb_pf_worker.c` / `gnb_primary_worker.c`）。  
v1 用 **`pthread_rwlock_t`**：

```text
写：tmp+rename → 解析到栈/临时 → wrlock 槽 → memcpy + gen++ → unlock
读：rdlock → 选出候选 uuid（仅快照）→ unlock
    → 锁外查节点状态 → 确定出口
```

- **锁内不做 I/O、不查节点、不探测**  
- 开关与策略同一快照（同 gen）  
- 无锁双缓冲+读者计数为后续优化，v1 不做

### 4.2 文件

- 写：`proxy.conf.tmp` + `rename`（原子）  
- 删：watcher/轮询发现 gone → 发布 disabled  
- 错误：不切换

---

## 5. 能力探测兼容矩阵

| `gnb -h` 输出 | 行为 |
|---|---|
| 无 Features 行 | **不写**任何 proxy 配置 |
| `Features: proxy` | **旧 P2（f 模型）**：禁止写 v2 `proxy.conf`；只可写旧 `proxy on` 于 node.conf（过渡） |
| `Features: proxy-conf` | **v2**：读写 `proxy.conf` |
| 同时有 `proxy` 与 `proxy-conf` | **以 `proxy-conf` 为准** |

实现前必须改 `mynet/plugin_proxy.go` 的 `GNBProxySupported()` 与测试，**禁止**只认 `Features: proxy` 就投 v2 文件。

---

## 6. 内存（由 node.conf 的 memory 配置决定）

`tiny` 不是由操作系统或 CPU 架构自动选择的。gnb 从 `node.conf` 读取：

```ini
memory tiny     # tiny / small / large / huge
```

未配置时当前 gnb 默认 `tiny`。该设置统一影响 payload、heap 和 LRU 容量：

| memory | payload block | max heap fragment | gnb LRU 基准 |
|---|---:|---:|---:|
| tiny | 8 KiB | 8 KiB | 2 KiB |
| small | 16 KiB | 16 KiB | 4 KiB |
| large | 32 KiB | 32 KiB | 32 KiB |
| huge | 64 KiB | 64 KiB | 64 KiB |

| 用途 | v1 预算 |
|---|---|
| **配置槽** | `2 × 8 KB` 静态（超限 **拒载**，不截断） |
| **连接表** | 另计：tiny 512 条；small 1024 条；large/huge 2048 条 |
| **分片缓存** | 256 条 × 24B ≈ 6KB |
| IPv6 | v1 **不做** target；结构预留 |

**不宣称**「完整代理 16KB」——配置槽只是其中一块。

### 6.1 跨平台约束

- macOS、Linux、OpenWrt 共用 Unix 路径；OpenWrt 默认使用 `tiny`，容量必须可由构建或配置覆盖。
- Windows 不提供 `pthread_rwlock_t`/`pthread_mutex_t`；锁必须封装成 gnb 平台适配层，Windows 使用 `SRWLOCK`/`CRITICAL_SECTION`，Unix 使用 pthread。
- `time_t`、`uint32_t`、`uint16_t` 和配置结构不得依赖指针大小；flow 表不能保存跨进程或跨重启的指针。
- 原子发布、文件替换、路径分隔符和删除检测必须走平台封装，不能把 Unix 专用调用散落在代理实现中。
- 交叉编译验收至少覆盖 x86_64、arm64、armv7/mipsel 和 Windows x86_64；先验证编译，再验证配置解析与 flow 容量。

---

## 7. gnb 补丁风格（硬约束）

按 `src/` 现有习惯，**禁止另起风格**：

| 约定 | 示例 |
|---|---|
| 结构体 | `typedef struct _gnb_proxy_cfg_t {…} gnb_proxy_cfg_t;` |
| 常量宏 | `#define GNB_PROXY_MAX_GROUPS 16` |
| 配置解析 | `fscanf(file,"%1024[^\n]\n",line_buffer)` + `sscanf` + `strncmp(line_buffer,…)` |
| 文件路径 | `snprintf(path, PATH_MAX+NAME_MAX, "%s/proxy.conf", conf->conf_dir)` |
| 日志 | `GNB_LOG2(gnb_core->log, GNB_LOG_ID_PF, "…%llu\n", …)` |
| 内存 | `gnb_heap_alloc(gnb_core->heap, …)`；静态槽仅用于双缓冲 |
| 头文件 | `#ifndef GNB_PROXY_CONF_H` + 版权头 |
| 缩进/命名 | 与 `gnb_conf_type.h` / `gnb_pf_route.c` 一致（tab + snake） |

补丁走 `mynet_gnb/packages/*/patches/`，`src/` 与上游 diff 对齐，**不直接改 vendor 语义**。

---

## 8. 落点（确认后）

| 位置 | 改动 |
|---|---|
| `src/gnb_proxy_conf.c/h` | 解析 + rwlock 快照 + 按连接选 exit + mtime 热载 |
| `src/gnb_pf_route.c` | 准出改 proxy 选路；日志带 gen/组/dst |
| `src/gnb_conf_file.c` | 删 node.conf `proxy`；启动 load proxy.conf |
| `src/gnb_conf_type.h` | 旧 `proxy` 字段注释/废弃 |
| `mynet/plugin_proxy.go` | v2 renderer、禁用写 off、tmp+rename、能力矩阵 |
| `mynet/driver/gnb.go` | 不写 node.conf `proxy` |
| patches 0001/0002/0003 | 重写对齐 v2 |
| 文档 | 清 f 节点 |

---

## 9. 验收（实现后）

- [ ] 并发：两次热更新 × 慢读交错，无撕裂  
- [ ] 禁用：写 `proxy off` 后旧配置失效  
- [ ] 删文件 → disabled；语法错 → 保留上一版  
- [ ] **单条 TCP 跨热更新出口不变**（连接表）  
- [ ] 分片同流；表满 LRU 不崩溃  
- [ ] 命中组无出口 → DROP + gen/组/dst 日志  
- [ ] `/0` 解析拒载；route.conf `/0` 告警  
- [ ] 纯 server：`proxy on` 放行出网 + 回程  
- [ ] client：`proxy on` 不放宽准入（除非 ingress_open）  
- [ ] 能力矩阵三态 + 旧 P2 不误投 v2  
- [ ] 配置槽超限拒载  
- [ ] 锁内无 I/O/无节点探测  

---

## 10. 明确不做

- 不引入 Clash/应用级代理核  
- 不灌 route.conf 巨表  
- 不用 f 节点 / `select_fwd_node`  
- v1 不做 IPv6 target  
- 不承诺出口故障时已有连接无缝迁移  

---

**请确认 v3。** 确认后按 §8 动代码（严格 gnb 风格）。
