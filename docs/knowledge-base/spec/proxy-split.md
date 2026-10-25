# SPEC: 代理分流（新线 · Proxy Split）

> 更新：2026-09-28 | 适用：`mynet_api` · `mynet_agent` · 本机 WebUI  
> 状态：**现行**  
> 旧线契约 [`proxy.md`](proxy.md) **已冻结**（mynet_lua / mynet_client + `mynet_proxy` CLI）；  
> 能力归宿见 [`control-plane.md`](control-plane.md) §7。  
> **本文是新线代理分流的产品与技术 SSOT。**

---

## 0. 一句话

**云端决定「谁、按什么规则、从哪出去」；入口在 DNS 时刻做域名决策，把结果翻译成 IP 路由动作；出口只负责 NAT 出网。用户数据不经过云。**

---

## 1. 三角色

| 角色 | 是谁 | 做什么 | 不做什么 |
|------|------|--------|----------|
| **消费者** | App / 手机 / 电脑 / LAN 主机 | 发 DNS + 业务流量 | 不做分流 |
| **代理入口（client）** | 跑代理 client 的节点 | **DNS 劫持** · 域名决策 · 打标 · **把数据直接送给出口对端** | 不落地互联网 |
| **代理出口（server）** | 被指定为出口的**对端节点** | **收包（自己就是数据路径的目标）** · NAT 出网 | 不做消费者侧 DNS |

### 1.0 数据路径（关键：出口=下一跳，不是 f 节点转发）

> ⚠️ **本产品的 proxy 与 gnb 的 f / forward / 多跳转发无关。**
>
> 实现走 **gnb `p2-route-proxy` 补丁**（`node.conf` 的 `proxy on` + 指定对端）：
> 入口把未登记目的地的流量**直接塞给出口对端**，**对端就是目标**；
> 对端收下后 NAT 出网。语义等同 `route.conf` 里 `出口node|目的|mask`，由指定出口作为下一跳。

```
消费者 → 入口（DNS 决策 + fwmark）→ gnb 隧道 ──► 出口对端（目标）→ NAT → 互联网
                                              ↑
                                    数据路径的终点，不是中转 f
```

| 概念 | 本产品含义 | 不要理解成 |
|------|------------|------------|
| 出口对端 | 流量的**目标节点**（proxy egress） | mesh 里的 f / forward 节点 |
| `proxy on` | 未登记目的 → 送给指定对端出网 | 出口=下一跳（非中继跳） |
| `exits[]` | 出口对端列表（选点/权重） | forward 候选 |

### 1.0.1 同机自环约束（ipset/nft 的本质）

> **「proxy 不能为自己代理」** —— 不是推翻「消费者=入口」，而是同机时必须防自环。

| 流量 | 走哪条链 | 处理 |
|------|----------|------|
| LAN 转发 | PREROUTING | 打标进 tun |
| **本机 App** | **OUTPUT** | **也必须打标**（只写 PREROUTING 时同机等于没分流） |
| gnb UDP / 出口物理 endpoint | OUTPUT | **豁免**（return / 钉物理口 /32） |
| lo / 本机解析器 | OUTPUT | 豁免 |

**顺序**：自环豁免 → 打标 → 策略路由。  
缺豁免 = 历史上的路由自环（隧道包再进隧道）。

Clash 靠转发进程绑物理口；我们是内核隧道，靠 **endpoint 钉物理口 + OUTPUT return**。

### 1.1 两种拓扑

| client 跑在哪 | 消费者 | 劫持范围 | 类比 |
|---|---|---|---|
| **PC / 手机** | 与 client **同一节点** | 只劫本机（lo :53） | ≈ OpenClash |
| **网关 / 软路由** | 独立设备 | 劫 LAN :53（+ 可选 DoH/DoT） | 透明网关 |

`client` 与 `server` 可同时开（`role=both`）；消费者与入口也可同节点。**角色是逻辑角色，不是设备类型。**

### 1.2 数据面（云不参与）

```
消费者 → [DNS: 劫持→域名决策] → [业务流: fwmark→隧道] → 入口 ──gnb──► 出口对端 → 互联网
```

云端只下发策略与授权，**不转发、不解析、不当出口**。

---

## 2. 控制面分工

| 层 | 干什么 | 落点 |
|----|--------|------|
| **云 mynet_api** | ① 开放策略（开不开 / 开放方向 / DNS 方式）② 对谁开放（授权）③ 解算 Region×Policy ④ 发 envelope | `node_configs.config.proxy` → `BundleEnvelope.network.proxy` |
| **mynetd** | 双控合并（后覆盖先）· Op→argv 翻译 · 回写云端 | **不含系统能力**（B2） |
| **执行器** `mynet` / lua | 装 DNS 劫持 · 生成 dnsmasq · L5 投影 · L6 策略路由 | 桌面 `mynet plugin proxy …`｜OpenWrt lua |
| **gnb / wg** | 隧道数据面；gnb `proxy on`：未登记目的**直接送出口对端** | 不管策略与 DNS |

### 2.1 命令链

```
WebUI / 云端 Op
  → mynetd Plan() 翻译
    → Executor: mynet plugin proxy …   （OpenWrt → lua）
```

禁止 `mynetd` 直接改路由/防火墙/DNS（guard B2）。

### 2.2 双控（本地 ↔ 云端）

| 规则 | 说明 |
|------|------|
| **后覆盖先** | 比 `revision`（单调递增），大者胜 |
| **同时刻** | **云优先**（防离线老页面冲掉紧急策略） |
| **本地写** | 先生效 + 标 `dirty`，异步回写 |
| **回写失败** | **不回滚**，保持 dirty，可重试 |
| **唯一出口** | 执行器只认 `EffectiveProxyPolicy` |

回写只含**业务意图**（region/policy/exits/dns/rules），不含 L6 投影（fwmark/nft）。

回写通道：`WebUI → localapi → node-face 签名 → gateway relay → PUT /api/v1/monitor/node/proxy-config`。

---

## 3. 分流：域名优先，IP 兜底

### 3.1 为什么域名分流不是「换一套规则」

IP 在包头；**域名只在 DNS 答案、TLS SNI、HTTP Host**。  
因此域名规则不能被 nft 直接 match，必须：

> **在 DNS 时刻用域名决策 → 翻译成 IP 路由动作（或假 IP 映射）。**

### 3.2 路线 A：DNS 驱动（现行）

```
App DNS ──劫持──► 本地解析器（dnsmasq/smartdns）
                        │
              查域名规则（谁走代理）
                        │
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
  国内域名直连     隧道内国际DNS     出口节点DNS
  (223.5.5.5)     (8.8.8.8 隧道内)   (可选开关)
        │               │               │
        │               └───────┬───────┘
        │                       ▼
        │              答案 A 记录 → ipset mynet_proxy
        │                       │
        └───────────────────────┴──► fwmark 0xc8 → table 200 → 隧道
```

| 步骤 | 说明 |
|------|------|
| 1. 强劫持 | `:53` 收到本地解析器（PC=lo；网关=LAN） |
| 2. 域名规则 | 精确/后缀/geo → 选上游 |
| 3. 上游三选一 | **隧道国际 DNS（默认）** / 出口节点 DNS / 国内 DNS |
| 4. 回答钩子 | proxied 域名的 A 记录 **动态 `ipset add`** |
| 5. IP 分流 | 已有 fwmark/table 执行（降为兜底层） |

**不做**：Fake-IP / Clash 核（应用级代理，另一产品线）；SNI 嗅探不当主路径。

### 3.3 IP 分流（兜底层，保留）

Region × Policy 矩阵（SSOT，`ResolveProxySplit` / `ProxySplitResolver`）：

| NODE_REGION | POLICY | IP 集合 | MATCH_MODE | 语义 |
|-------------|--------|---------|------------|------|
| domestic | whitelist | interip | normal | 命中海外 → 走代理 |
| domestic | blacklist | china | **inverted** | **未**命中国内 → 走代理 |
| international | whitelist | china | normal | 命中国内 → 走代理 |
| international | blacklist | interip | **inverted** | **未**命中海外 → 走代理 |

⚠️ **禁止只按 region 选数据源**（旧线教训：domestic+blacklist 加载 interip 再反转 → 境外不走代理）。

用途：无域名规则的流量、直连 IP、写死 IP 的 App。

### 3.4 DNS 上游与形态（网关 / PC 两条路）

**DNS 只管「返回什么 IP」（快、尽量干净）；「走不走隧道」仍归入口分流。两者解耦。**

#### 网关 / 软路由

```
LAN App → 劫持 LAN :53 → 本地 dnsmasq / SmartDNS（缓存+域名规则）
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        国内域名直连     隧道内国际DNS     出口节点DNS
        (223.5.5.5)     (clean 列表)      (可选)
```

- **强劫持** `LAN :53`（+ 本机）
- 本地解析器做缓存与域名规则；上游按 §3.5 策略选择
- 配置由执行器生成（dnsmasq 片段 / SmartDNS），`reload` 不 `restart`

#### PC / 手机（消费者 = 入口）

**不装 dnsmasq。** 解析指到**平台智能 DNS**（或过渡期国内公共 DNS）：

```
PC App →（可选轻劫持 lo:53）→ dns.mynet.club / DoH
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
              国内域名直连     常规境外就近答     高风险域名平台侧解
              (最快)          (CDN/分线路)       (干净，不占用户隧道)
```

| 选择 | 说明 |
|------|------|
| **MyNet DNS（目标）** | `dns.mynet.club`：国内接入、平台做干净解析与智能拆分；PC **一行 nameserver** |
| 过渡 | AliDNS `223.5.5.5` / DNSPod `119.29.29.29` 等国内公共 DNS（快，但不保证被墙域名） |
| 隧道 DNS | 仅 `clean` 列表兜底，**不是 PC 默认** |

PC 要域名规则时：入口**轻劫持**到 MyNet DNS（或 mynetd 内嵌 tiny forwarder），**不上完整 dnsmasq**。

#### 形态对照

| | 网关 | PC |
|---|---|---|
| 解析器 | 本地 dnsmasq / SmartDNS | 平台 DNS / 公共 DNS |
| 劫持 | **强** LAN+lo | 可选轻劫持 |
| 缓存 | 网关共享 | 上游 + 系统缓存 |
| 域名规则 | 本地 dnsmasq / 云端下发 | 平台 DNS / 本地 tiny 规则 |
| 运维 | 生成配置 | **零运维** |

### 3.5 上游策略（fast / clean / balanced）

| 策略 | proxied 域名 DNS | 适用 |
|------|------------------|------|
| **fast（默认）** | 国内 DNS 或 MyNet DNS，**不进隧道** | 一般场景（解析不被拖慢） |
| **clean** | 隧道国际 DNS / 出口 DNS | 仅 `upstream_clean` 列表 |
| **balanced** | 竞速或「国内解析 + 污染 IP 过滤」 | 接 SmartDNS/mosdns 时 |

```json
"domain_rules": {
  "proxied": ["google.com", "github.com"],
  "upstream_proxied": "fast",
  "upstream_clean": ["google.com"],
  "upstream_direct": "223.5.5.5,119.29.29.29",
  "platform_dns": "dns.mynet.club"
}
```

**流量与 DNS 解耦**：解析可走国内；连接仍按域名/IP 规则进隧道。污染 IP 在隧道里多半仍可出网；黑洞 IP 时才对该域名切 `clean`。

---

## 4. 劫持范围（入口职责）

| 场景 | 必须劫 | 可选 | 不做 |
|------|--------|------|------|
| **PC（消费者=入口）** | 本机 `lo :53` | 堵 DoH/853 | 不碰 LAN |
| **网关** | `LAN :53` + `lo :53` | DoH/853 黑洞或进隧道 | — |

「强劫持」= nft/iptables 拦截发往非本机解析器的 53 端口，**不是建议性 resolv.conf**。

写死 DNS 的 App：靠 **国外 DNS `/32` 钉进隧道** 兜底（见 §5）。

---

## 5. 兜底与防绕过

| 手段 | 作用 | 地位 |
|------|------|------|
| 国外公共 DNS `/32` 进隧道 | 写死 `8.8.8.8` 的查询仍走隧道 | **兜底**（非主路径） |
| DoH/DoT（443/853）堵洞或进隧道 | 防应用层加密 DNS 旁路 | 可选开关 |
| 「全部进隧道」 | 无域名规则时的全局模式 | 开关 |
| `dns.mynet.club` | 公共 DNS 全挂时的平台兜底 | 最后一道 |

---

## 6. 权威配置形状

### 6.1 业务意图（云端权威 + 本地可改，双控）

```json
{
  "enabled": true,
  "role": "client|server|both",
  "region": "domestic|international",
  "policy": "whitelist|blacklist",
  "split": {
    "list_source": "china|international",
    "match_mode": "normal|inverted",
    "ip_list": { "source": "china|international", "url": "…", "sha256": "…" }
  },
  "domain_rules": {
    "proxied": ["google.com", "github.com"],
    "direct": ["baidu.com", "*.cn"],
    "upstream_proxied": "fast|clean|balanced",
    "upstream_clean": ["google.com"],
    "upstream_direct": "223.5.5.5,119.29.29.29",
    "platform_dns": "dns.mynet.club"
  },
  "dns": {
    "mode": "none|redirect|resolv|split",
    "server": "8.8.8.8",
    "domestic_servers": ["223.5.5.5", "119.29.29.29"],
    "redirect_target": "10.x.x.x"
  },
  "exits": [{ "node_id": "9001", "via": "10.x.x.x", "weight": 20, "order": 1, "enabled": true }],
  "foreign_dns_route": true,
  "fwmark": 200,
  "table": 200
}
```

- `split.list_source/match_mode` **必须**由 `ResolveProxySplit(region, policy)` 解算，两端交叉校验。  
- 上面的 JSON 是**本机意图**（`conf/<source>/proxy_intent.json`）的形状。  
- ⚠️ **2026-10-05 D1 收窄**：wire 上 `BundleEnvelope.network.proxy` **只带出口许可**
  `{enabled, acl}`（`$defs/ProxyPermission`），不再带 role/split/exits/dns/域名规则等客户端策略
  ——那些由节点 Local 轨承载，L6 与云端许可合成。详见 `p4-forwarding-proxy-plan.md` §7.5。  
- 本地落盘：`conf/<source>/proxy_intent.json`（`local` / `cloud` / `status.dirty`）。

### 6.2 执行计划（L6，不回写）

ApplyPlan.Proxy：ipset 文件、fwmark/table、mangle、DNS 文件、路由。  
IP 列表 URL+sha256 下发，节点拉取校验后灌 set，**envelope 不内嵌全量 CIDR**。

---

## 7. 实现归属

| 组件 | 落点 | 现状 |
|------|------|------|
| `ProxyPolicy` / `ResolveProxySplit` | `mynet_agent/shared/domain/proxy_policy.go` · `mynet_api/.../ProxySplitResolver.php` | ✅ |
| 双控合并 | `shared/domain/proxy_intent.go` | ✅ |
| Op 面 `plugin.proxy.*` | `mynetd/commands` | ✅ |
| 回写 | `PUT /api/v1/monitor/node/proxy-config` | ✅ |
| L6 策略路由 / DNS 文件 | `mynet/proxy_apply.go` · `proxy_dns.go` · `proxy_iplist.go` | ✅ 命令生成 + **真机 apply（2026-10-11）** |
| WebUI 代理分流 | `mynetd/localapi/webui/index.html` | ✅ 面板 |
| **DNS 强劫持** | — | **T12** |
| **域名规则 + dnsmasq split** | — | **T13** |
| **DNS 回答钩子 → ipset** | — | **T14** |
| **规则来源双控** | — | **T15** |
| **DoH 兜底** | 部分（/32） | **T16** |
| **同机 OUTPUT 打标 + 自环豁免** | `proxySelfExemptCmds` / `proxyMarkCmds` | **T17 ✅ 命令生成** |
| 授权「对谁开放」 | — | T11 |
| WG 出口 AllowedIPs | `mynet/proxy_wg_projection.go`（`ProjectProxyToWG`） | ✅ 2026-10-11 |
| PC（mac/win）域名 → /32 钉隧道 | `mynet/proxy_pc_pin.go`（`ApplyPCPin`） | ✅ 2026-10-11 |

---

## 8. 出口判据（DoD）

1. PC：本机 `dig` 被劫持；域名规则生效；proxied 答案 IP 进 ipset；流量进隧道。  
2. 网关：LAN 客户端 DNS 被劫持，同上。  
3. 矩阵四格 + 域名规则单测绿；`go test ./...` 与 `phpunit` 绿。  
4. 双控：本地后写 / 云端后写 / 同时刻云优先 / 回写失败保持 dirty。  
5. 不触红线：mynetd 无系统能力；禁按名字杀进程；私钥不进响应。

---

## 9. 与其它 spec

| 文档 | 关系 |
|------|------|
| [`proxy.md`](proxy.md) | 旧线已冻结，仅供历史对照 |
| [`control-plane.md`](control-plane.md) §7 | `mynet_proxy` 下架与能力归宿 |
| [`driver-abstraction.md`](driver-abstraction.md) | gnb `proxy on` / wg 不同构 |
| [`unified-platform-architecture.md`](unified-platform-architecture.md) | L2 TransportPolicy / L6 NetworkBackend |
| [`local-api.md`](local-api.md) | WebUI → localapi |

---

## 10. 完整架构（一图流）

```
┌─────────────────────────────────────────────────────────────────┐
│  云 mynet_api —— 控制面/业务权威（不碰流量）                       │
│  ① 开放策略 ② 对谁开放(ACL) ③ Region×Policy 解算 ④ 发 envelope    │
└───────────────────────────┬─────────────────────────────────────┘
                            │ envelope 下发     ▲ 回写(node-face)
┌───────────────────────────▼─────────────────────────────────────┐
│  mynetd —— 纯通信 + 双控合并（后覆盖先）+ Op→argv                   │
│  EffectiveProxyPolicy / EffectiveDomainRules · proxy_intent      │
└───────────────────────────┬─────────────────────────────────────┘
                            │ Executor: mynet plugin proxy … / lua
┌───────────────────────────▼─────────────────────────────────────┐
│  执行器 → L5 投影 + L6 落地                                        │
│  L5: gnb proxy on · 出口对端（下一跳/数据目标）· address.conf 不写角色              │
│  L6: DNS 劫持 · dnsmasq/平台DNS · ipset/fwmark · ACL · DoH 兜底    │
└───────────────────────────┬─────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
   消费者              代理入口 client       代理出口 server
   App/LAN/PC          打标→隧道            对端=目标 → NAT → 互联网
   （可与入口同机）     （client 模式）       （server 模式）
                            └──── gnb 隧道 ────┘
                         （数据直接送给对端，不经 f）
```

### 10.1 控制链 / 解析链 / 数据链

| 链 | 路径 |
|----|------|
| **控制链** | WebUI/云端 → 合并 Effective → 执行器；本地改 Dirty → 回写云端 |
| **解析链** | App :53 劫持 → 解析器 → 域名规则选上游(fast/clean/direct) → A 记录进 ipset |
| **数据链** | 消费者 → 入口 fwmark→table200 → **gnb 直送出口对端** → NAT → 互联网 |

### 10.2 形态

| | PC | 网关 |
|---|---|---|
| 消费者 | 与入口同机 | LAN 独立设备 |
| DNS | 平台 DNS（dns.mynet.club，零运维） | 本地 dnsmasq/SmartDNS |
| 劫持 | 可选 lo | 强劫持 LAN |

### 10.3 实现归属（防重复实施）

| 块 | 文件 | 状态 |
|----|------|------|
| 契约 ProxyPolicy/Split/DomainRules/ACL | `shared/domain/proxy_*.go` | ✅ |
| 双控 | `shared/domain/proxy_intent.go` | ✅ |
| Op/回写 | `mynetd/commands` · `localapi/proxy_api.go` · `backclient.go` | ✅ |
| L6 | `mynet/proxy_apply.go` `proxy_dns*.go` `proxy_iplist.go` | ✅ 命令生成 |
| 云端 | `ProxySplitResolver` `ProxyACL` `ProxyAuthDomainService` `saveProxyConfig` | ✅ |
| WebUI | `mynetd/localapi/webui/index.html` 代理分流 | ✅ |
| 真机 apply | `.182`（OpenWrt client） | ✅ 2026-10-11 端到端：`.5` 加路由经 `.182` 转发 → `curl https://1.1.1.1/cdn-cgi/trace` = `ip=52.221.22.241`（= `.180` 出口） |
| dns.mynet.club | — | ⚠️ 未建 |
| WG 出口 | — | 后置 |

**禁止**把本 proxy 重新实现成「f 节点转发链」或另起应用级代理核（Clash 路线）。
> 注意：f 节点是旧 mesh 概念，**本插件不用**。出口对端=指定下一跳，等同动态 route.conf。

---

## 11. 实机对照与定案（2026-09-28）

对照：`api.mynet.club`（Debian + proxy **server**）· `192.168.0.2`（ImmortalWrt + proxy **client**）。

### 11.1 两台现状

| | 192.168.0.2 client（网关） | api.mynet.club server（出口） |
|---|---|---|
| 角色 | 消费者=LAN，client=本机 | **proxy 出口**（NAT 出网） |
| 防火墙 | **fw4 / nft** + gnb_tun 区 masq 双向 | **iptables** MYNET_FWD / MYNET_PROXY_NAT |
| 分流 | `table inet mynet_proxy` set（interval CIDR）→ mark **0xc8** | 无 ipset/分流（只转发） |
| 路由 | `ip rule 31800 fwmark 0xc8 lookup mynet_proxy` | 无 policy rule |
| 出口 | table mynet_proxy **default via 10.182.236.180 dev gnb_tun_16** | 自身（ens5 MASQ） |
| 隧道 | gnb_tun_16 | gnb_tun_180 :9016 |
| DNS | dnsmasq **dns_redirect** → smartdns **:6053** | smartdns **bind 10.182.236.180:53** |
| 其它 | openclash/homeproxy 并存 | sniproxy :443、smartxray（另一产品） |

### 11.2 生产 DNS 链路（已定型）

```
LAN App ──dns_redirect──► dnsmasq ──► smartdns :6053
                                        │
                     domain-set 规则选组  │
              ┌─────────────────────────┼──────────────────┐
              ▼                         ▼                  ▼
        domestic: 223.5.5.5        foreign: 10.182.236.180:53
        223.6.6.6（本机直连，快）     （出口 smartdns 绑 VPN IP）
                                        │
                                        └─► 出口侧 8.8.8.8/1.1.1.1 + china 集
```

**要点**：「干净解析」= **查出口 VPN IP:53**，不是把 `8.8.8.8` 整条塞进隧道；出口 smartdns 再向公网查。域名规则只影响 **DNS 上游**，不直接写 ipset。

### 11.3 生产流量链路（已定型）

```
LAN 包 ──PREROUTING──► nft set mynet_proxy(大 interval CIDR)
                         │ 命中 → mark 0xc8（并写 ct mark）
                         │ exclude_ips 先 return（自环/豁免）
                         │ established 回填 ct mark
                         ▼
              ip rule 31800 → table mynet_proxy
                         │ default via 10.182.236.180 dev gnb_tun
                         ▼
                    出口 MASQUERADE 出网
```

脚本实名：`/etc/mynet/scripts/proxy/route_policy.sh`  
参数：`TABLE_NAME=mynet_proxy TABLE_ID=200 FWMARK=0xc8 RULE_PRIORITY=31800`。

### 11.4 定案（以此为准）

| 设计点 | 定案 | 依据 |
|--------|------|------|
| **流量主路径** | **静态 IP 段 nft set + fwmark 0xc8 + table 200 + default via 出口 VIP** | 两台实机在跑，计数数百万包 |
| **nft 命名** | `table inet mynet_proxy` / `set mynet_proxy`（`flags interval; auto-merge`） | 与 route_policy.sh 一致 |
| **域名 × 流量** | **解耦**：域名只进 smartdns 上游选择；流量进 IP set | 生产无 domain→ipset |
| **域名→ipset** | **可选增强**，不是主路径 | 生产未用；缺了不影响基本代理 |
| **DNS 快 + 净** | domestic=本机 223；**foreign=出口 VPN IP:53**（smartdns） | 避免整段 DNS 进公网隧道 |
| **出口语义** | 对端 = **数据目标**，VIP 进 table default | `via 10.182.236.180` |
| **自环** | `exclude_ips` set + established ct mark | 与 T17 同类；生产已有 |
| **server 侧** | 只做 FORWARD + MASQ（tun↔ens5）；可选 smartdns 绑 VPN IP | 实机极简 |
| **fw4 习惯** | `iifname gnb_tun_*` 分区 + LAN↔VPN masq | ImmortalWrt 已如此 |

### 11.5 与新实现对齐清单

| 我们代码 | 对齐动作 |
|----------|----------|
| `mynet_proxy` / `dst4` | 改为/兼容 `inet mynet_proxy` + interval auto-merge |
| 动态 domain→ipset | 保留为可选，**不挡主路径** |
| DNS 上游 | `foreign` 默认改 **出口 VIP:53**（smartdns），而非裸 8.8.8.8 |
| exclude/ct mark | 已有 T17；补 `exclude_ips` set 形状 |
| server | 保持 FORWARD+MASQ；smartdns bind VPN IP 作产品项 |

**禁止**：把 openclash/smartxray 路线当作 MyNet proxy 的实现依赖——它们是并存组件，不是本产品 proxy。

---

## 12. 评估过但**不采纳**的方案：服务端 fake（sniproxy + nginx + smartdns）

> 2026-09-30 评估。**记入文档备查，不实现**——流量全走单点，带宽/延迟不可接受。

### 12.1 思路

在出口节点（如 10.182.236.180）搭 **SNI 反向代理**，配合 SmartDNS 假解析：

```text
SmartDNS:  google.com → 10.182.236.180（假解析，指向出口机）
Client  →  TLS 连 180，SNI=google.com（客户端不知道被换了）
180 sniproxy  读 SNI → 不解密 TLS → TCP 转发到真 google.com
180 nginx     :80 读 Host → 反代真目标
```

**本质**：fake 模式的**服务端实现**——假解析在 DNS 层，拦截在反代层，客户端零改造。

### 12.2 与客户端 fake-IP（Clash 式）对比

| | 客户端 fake-IP | **服务端 fake（sniproxy）** |
|---|---|---|
| 假解析位置 | 客户端本地回假 IP（198.18.x） | SmartDNS 把域名指到出口机 |
| 流量拦截 | 本地 TUN 劫持 | 出口 sniproxy 按 SNI 转发 |
| 客户端改造 | 要 TUN + 映射表 | **只改 DNS，零改造** |
| TLS | 隧道内终止 | **SNI 转发，不解密** |
| 策略入口 | 分散 | **单点统一** |

### 12.3 不采纳的理由（定案）

| # | 理由 |
|---|---|
| **1** | **流量全走单点**：所有代理域名的**完整流量**（不只是 DNS）经 180 转发，带宽与延迟成本不可接受 |
| **2** | 出口机成瓶颈与单点故障 |
| **3** | SNI 明文（不能 ECH），被动可见 |
| **4** | 与已定案的「静态 IP 段 nft set + fwmark」主路径重叠，两套并存徒增复杂度 |

### 12.4 与现行方案的关系

| 场景 | 用什么 |
|---|---|
| 流量主路径 | **静态 IP 段 nft set + fwmark + table 200**（§11.4 定案） |
| DNS 快 + 净 | smartdns：domestic 本机 / foreign 出口 VIP:53 |
| 域名导航（Mac/Win） | 小域名白名单 + 自定义（D5）+ DoW（DNS over WS） |
| 服务端 fake（sniproxy） | **不实现**；仅作备选记录 |

**一句话**：sniproxy fake 是「省客户端、贵出口」；我们选「客户端做分流、出口只做 NAT」——出口轻、可水平扩。

---

## 13. DNS 解析方案（定案，2026-10-01 修订）

> **用户三选一 + peer 优先/智能兜底**。
> 不指向公网智能 DNS 了事——UDP:53 在链路上可被劫持/污染；
> **唯一安全路径是 DNS 查询走 gnb 加密隧道到 peer 节点**。

### 13.1 用户三选一（可叠加 ①+②）

| # | 模式 | 机制 | 安全性 | 来源 |
|---|---|---|---|---|
| ① | **隧道节点 DNS** | 系统 DNS → peer 隧道 VIP；查询进 gnb 数据面（加密） | ✅ 链路看不见 | 租户下发 / 用户自建 / 朋友分享 |
| ② | **智能 DNS** | 系统 DNS → 智能 DNS（公网或自建） | ⚠️ UDP:53 可被链路劫持 | 租户下发 / 自建 / 平台 |
| ③ | **不加速（默认）** | 不碰 DNS | —— | —— |

**优先级链**（①+② 同时配置时）：
```text
peer DNS（走隧道，安全）→ 有响应就用
        ↓ 无响应 / 隧道断
智能 DNS（兜底，不断网）
```
实现：系统 nameserver 按 `[peer VIP, smart IP]` 顺序填，OS 自带 fallback，无需额外进程。

### 13.2 为什么不是「下发公网智能 DNS」就完

| 路径 | 谁能劫持 | 结论 |
|---|---|---|
| 系统 DNS → `dns.mynet.club`（UDP:53 公网） | ISP / 中间盒 / 链路伪造响应 | ❌ 不安全 |
| 系统 DNS → peer 隧道 VIP（如 `10.182.236.180`） | 查询被 gnb 加密封装 | ✅ 唯一安全 |
| 本地 forwarder :53 → WS/隧道 | 同上但要常驻进程 | ✅ 但徒增体积，**已裁** |

**设计取舍**：不需要本地 forwarder——只要把系统 DNS **指到 peer 的隧道 VIP**，查询就自动被 gnb 加密封装。零常驻、零体积、零端口占用。

### 13.3 形态分工（client / gateway）

| | **client（Win/Mac）** | **gateway（Linux）** |
|---|---|---|
| WebUI 归属 | ✅ **WebUI 只做 client 模式** | 不进 WebUI |
| 落地机制 | 改系统 DNS（`networksetup` / PowerShell） | dnsmasq/SmartDNS 上游（LAN 客户端已指网关，**无需劫持**） |
| 三选一逻辑 | 同一套 | 同一套 |
| 叠加 ①+② | nameserver 顺序 fallback | dnsmasq 多上游 fallback |
| 崩溃保护 | 备份/还原（`dns-backup.json`） | 配置生成，`reload` 不 `restart` |

**网关不需要劫持**：LAN 客户端本来就指网关当 DNS，网关只是「按三选一配上游」。

### 13.4 DNS 来源（与 proxy exits 同模型）

来源三类，和出口选点 `exits/groups` 复用同一形状：

| 来源 | 语义 | 形态 |
|---|---|---|
| **租户下发** | 平台/租户配好，envelope 下发 | peer VIP 或 smart DNS 地址 |
| **用户自建** | 用户自己的 DNS 服务器 | peer 节点跑 dnsmasq，或自建 smart DNS |
| **朋友分享** | 好友节点提供 DNS | peer VIP（隧道内可达） |

### 13.5 判路解耦（不变）

```text
DNS 只管「返回什么 IP」；「走不走隧道」仍归入口分流（IP 大表 / 域名规则）。
两者正交 —— 见 §14。
```

### 13.6 落地清单

| # | 件 | 归属 | 状态 |
|---|---|---|---|
| 1 | ~~DoW forwarder + WS dns 帧~~ | agent / gateway | ❌ **已裁**（徒增体积；改走 peer VIP） |
| 2 | DNS 三选一配置模型（peer/smart/off + 来源） | agent 配置 | ⬜ 待做 |
| 3 | client 落地：nameserver = `[peer VIP, smart]` | agent (Win/Mac) | ⬜ 待做 |
| 4 | gateway 落地：dnsmasq 上游同序 | agent (Linux/OW) | ⬜ 待做 |
| 5 | WebUI client 模式 DNS 面板（三选一 + 来源列表） | WebUI | ⬜ 待做 |
| 6 | peer 节点跑 DNS 服务（dnsmasq/SmartDNS） | 运维/用户 | ⬜ 待做 |
| 7 | envelope 下发 DNS 来源（peer VIP / smart 地址） | 后端 | ⬜ 待做 |

### 13.7 一句话定案

> **用户三选一：① 隧道节点 DNS（走加密隧道，安全）② 智能 DNS（兜底，不断网）③ 不加速（默认）。**
> **①+② 可叠加：peer 优先，智能兜底。**
> **指 peer 隧道 VIP 而非公网 DNS —— 后者可被链路劫持。**
> **WebUI 只做 client 模式；网关不劫持，只配上游。**

---

## 14. 代理设置模型：数据链路 × DNS（正交，用户可选）

> 2026-09-30 定案。代理配置分**两块独立设置**，用户各有选择、互不耦合。

### 14.1 两块设置

```text
代理设置 = ① 数据链路（纯 IP / 出口选点）  ×  ② DNS（用户三选一）
```

#### ① 数据链路（P2 proxy.conf —— 纯基于 IP / 出口）

| 设置项 | 语义 | 落点 |
|---|---|---|
| 选出口节点 | `[default] exit <node> w= order=` | `proxy.conf` |
| 分组 | `[group name]` + `target <cidr>` → 组内出口 | `proxy.conf` |
| 加权 / 降层 | `w=` 同层加权；`order=` 故障转移层 | P2 语义 |
| （扩展）域名分组 | 域名 → 组 → 出口 | 待 D5/WebUI |

**链路只关心 IP 怎么走**：进隧道后选哪个出口、怎么分流，**不碰 DNS**。

#### ② DNS（用户三选一，可叠加 ①+②）

| 模式 | 语义 | 用户场景 | 安全性 |
|---|---|---|---|
| **① 隧道节点 DNS** | 系统 DNS → peer 隧道 VIP（走加密隧道） | 要安全、信任某 peer | ✅ 链路看不见 |
| **② 智能 DNS** | 系统 DNS → 智能 DNS（公网/自建） | 要快、不断网 | ⚠️ 可被链路劫持 |
| **③ 不加速（默认）** | 不碰 DNS | 自己有 DNS / 不想让我们管 | —— |

**①+② 叠加**：peer 优先，智能兜底（隧道断了不至于断网）。
来源：租户下发 / 用户自建 / 朋友分享（与 exits 同模型，见 §13.4）。

**模式 ①/② 内部**（§13）：
```text
nameserver 顺序：[peer VIP, smart IP]
  ├─ peer 有响应（走 gnb 加密隧道）→ 用
  └─ peer 无响应 / 隧道断 → smart 兜底
```

### 14.2 组合矩阵（都合法）

| 链路 \ DNS | ① 隧道 DNS | ② 智能 DNS | ③ 不加速 | ①+② 叠加 |
|---|---|---|---|---|
| **选出口/分组** | ✅ IP 大表判路 | ✅ IP 大表判路 | ✅ IP 大表判路 | ✅ IP 大表判路 |
| **用户自定义** | ✅ | ✅ | ✅ | ✅ |

**解耦原则**：
- DNS 只决定「解析出什么 IP、给判路什么依据」
- 链路决定「IP 怎么走」（进隧道、选出口）
- 用户可以 DNS 不劫持但链路走出口，反之亦然

### 14.3 设置面（WebUI / CLI）

```text
代理设置
├── 数据链路
│   ├── 出口节点：[default] exit …
│   ├── 分组：[group] target → exit
│   └── 加权/降层：w= / order=
└── DNS（WebUI 只做 client 模式）
    ├── ○ ① 隧道节点 DNS
    │       来源：租户下发 / 用户自建 / 朋友分享
    │       选择：[peer 节点 ▾]
    ├── ○ ② 智能 DNS
    │       来源：租户下发 / 自建 / 平台
    │       地址：[____________]
    ├── ☑ ①+② 叠加（peer 优先，智能兜底）
    └── ● ③ 不加速（默认）
```

### 14.4 一句话定案

> **数据链路（出口/分组）与 DNS 是两块正交设置。**
> **DNS 用户三选一：① 隧道节点 DNS（走加密隧道，安全）② 智能 DNS（兜底）③ 不加速（默认）。**
> **①+② 可叠加：peer 优先，智能兜底。指 peer 隧道 VIP 而非公网 DNS。**
> **WebUI 只做 client 模式（Win/Mac）；网关不劫持，只配上游。**

---

## 15. 落地分层与归属（2026-10-11 定案）

> 起因：client 分流曾有两个 writer（Go 的 apply 计划 与 Lua 的 `proxy.lua`+`route_policy.sh`），
> 共用 `fwmark 0xc8` / `table 200`；apply 的「proxy 已禁用」清理会删这套对象，正好把 Lua 那份拆掉
> → 页面开关"看起来生效、实际时通时断"。
> 定案：**proxy 是一个整体单元，归 Go**（跨平台：Linux/mac/Win 当 client 同样需要这份分流，
> 与 UCI 那次同一条线）；**Lua 退成 UI/触发器**。

### 15.1 三层（缺一不通）

| 层 | 是什么 | 归属 | 现状 |
|---|---|---|---|
| **参数（意图）** | `ProxyPolicy`（`DNS`/`DomainRules`/`Split`/`Exits`…），双控 Cloud+Local | proxy 单元（`proxy_intent.json`） | ✅ |
| **驱动侧放行**（目的→出口，**按驱动方言**） | gnb：`proxy.conf`（`proxy on` + `[default] exit` + `[group] target <cidr> exit`）；wg：出口 peer 的 `AllowedIPs`（并集目的集 + `Table = off`） | 驱动投影（agent 按方言写） | gnb ✅ / wg ✅（2026-10-11） |
| **OS 侧打标**（把流量推进驱动） | nft 集合 + `mark 0xc8` → `table 200` → 隧道 | L6 `mynet apply`（**驱动无关**） | ✅ |

- **只放行不打标** → 流量进不了隧道；**只打标不放行** → 进了也丢（wg 的 cryptokey routing 尤甚）。
- DNS 的**参数**属本单元（`ProxyPolicy.DNS`，`shared/domain/proxy_policy.go`）；DNS 的**落地机制**
  （dnsmasq 片段 / resolv：`mynet proxy dns-split`/`resolv`）属 **agent**，**两处都不碰 gnb|wg**。

### 15.2 入口与单一写入者（防重复实施）

- **唯一写面**：`localapi POST /api/v1/plugin/proxy` —— 写意图（`proxy_intent.json`）+ 投影驱动
  （`plugin apply proxy`）+ 派发 **`mynet proxy apply`**（只刷 proxy 段：nft/ip rule/table/DNS，
  **不碰 OS 路由/防火墙/接口/默认网关**）+ 回写云端。
- **驱动投影唯一入口**：`mynet plugin apply proxy`（gnb → `driver/gnb/conf/<id>/proxy.conf`；wg → 待补）。
- **Lua 侧已退役**：`scripts/proxy/{proxy.sh,route_policy.sh}` 与 plugin 钩子删除；控制器 proxy
  端点只走桥、**无 Lua 回退**（回退会成第二个 writer）。

### 15.3 待办

- ~~**wg 侧放行投影**~~（2026-10-11 已落，**产品口径**）：目的集**不由 split 列表枚举**。
  `AllowedIPs` 是 wg 的**双向 cryptokey 路由**（选 peer + 反欺骗），**不是**分流选择器 ——
  分流选择在 OS 层（nft 集合 + `mark 0xc8` + `table 200`，§15.1 第三层，驱动无关）。
  故投影只表达「哪条隧道承载哪些分组出口」：
  - **默认出口** → `0.0.0.0/0`（安全超集；`Table = off` 下不劫持主表，见 §15.4 已下线的
    /32 钉扎病）。
  - **分组出口** → 该分组 `targets`（多出口按分组的 destination 映射到各自 exit 的 peer）。
  - 无出口的分组 = DROP（不投影）。
  `ProjectProxyToWG` 把目的集**并集**进**匹配出口** peer 的 `AllowedIPs`（幂等去重），并置
  `Routing.Mode=off` → `RenderWGConfig` 出 `Table = off`。
  fail-loud：投影后「目的集 ⊄ 所有 peer AllowedIPs 并集」即报错、不写盘（出口选点对不上
  wg peer 的静默丢包）。落点 `mynet/proxy_wg_projection.go`，两个入口：
  `WGExitDestinations(loadPluginProxyConfig())`（出口→目的集映射）+ `ProjectProxyToWG`，
  挂在 `writeWgProjection`。
- **Lua `dns_split.sh`** 与 agent `proxy dns-split` 重叠（同一"两个 writer"病），按同标准退役。
- **`proxy.lua` 死码清理**（`enable/disable/start/stop/reload`、firewall-include 注册等已不可达）。

### 15.4 铁律：本机自身不可代理（Linux / OpenWrt 同理）

**本地发出（OUTPUT）的包，FIB 路由查找发生在 netfilter OUTPUT hook 之前** ⇒ 在 OUTPUT
里 `meta mark set` 改不了这一包的路由 ⇒ `ip rule fwmark` 对**本机自身**流量**不生效**。

- **打标只挂 `PREROUTING`**（转发流量 = LAN 客户端）；**不挂 `OUTPUT`**。
- 2026-10-11 真机实证：强制 `curl --interface gnb_tun_16` 出网**成功**（`ip=52.221.22.241`），
  而依赖 OUTPUT 打标的本机自发路径**必然失败**。旧版「本机 App（消费者=入口）打 OUTPUT」
  已删 —— 它既违这条铁律、又把真实失败掩盖成「配置看起来齐全」。
- 历史上用**主表 `/32` 钉扎**把本机流量送进隧道（外部 DNS 那批）也是同一目的；该层已于同日
  按裁决下线（见 §15.1），因为它连**节点自身**的解析流量一起卷进了隧道。
- 推论：**验证代理必须走「转发」路径**（LAN 客户端 / 造一个转发源），
  **不能**由口机自己 curl 出去当验收 —— 那测的不是客户端路径。

### 15.5 PC 形态（mac/win/Linux/BSD）自出口：域名驱动，例外于 §15.4

**§15.4 的铁律只管 GW 形态的 Linux/OpenWrt 节点**（路由器，自身是转发平面的旁路）。
PC（mac/win/Linux/BSD）上**用户自己就是端点**，没有 LAN 转发可打标、也没有 fwmark 策略表 ——
唯一能把本机流量送进隧道的机制就是**主表目的路由**。这不是「节点代理自己」的违规，
而是 PC 形态的正当路径。

- 目的集**不固定**（用户想代理啥就配啥域名），所以**不装全量 CIDR**；只把
  **指定域名**解析出的 IP 按 `/32` 钉进隧道（用户裁决 2026-10-11：「可以维护一个小集」）。
- **2026-10-18 生命周期收口**：PC 动态路由由 dns-hook 内 `pcProxySession` 唯一管理，
  实际查询命中后加载、有界租约到期回收；apply 不再预解析整份清单或另写一份动态路由。
  ctl 基线与本机叠加分开存储，缓存命中仍核对内核路由；关闭与异常退出精确释放。
  状态文件、迁移、事件矩阵及验收边界见 [PC 白名单加速生命周期](pc-proxy-lifecycle.md)。
- **跨平台分流**：Linux GW/OpenWrt 走 nft 打标 + 策略表（§15.1 三层），PC 走域名 `/32` 主表
  钉扎 —— 同一份 `ProxyPolicy`，两种落地方言，`isPCProxyForm` 分派。
