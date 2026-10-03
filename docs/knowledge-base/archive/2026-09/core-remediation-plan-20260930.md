# 核心问题修复执行计划（2026-09-30 · 按四条主线重排）

> **总判**：五节点互通不全只是症状。病根是四条结构性主线——
> **① 构建/发包不规范** → **② 配置双层与路径不清** → **③ 系统层与驱动分工不明** → 才轮到互通与部署。
>
> **开发/编译主场**：本机（181 mac）+ `192.168.0.32`（完全可控测试机）
> **正式发包**：GitHub Actions workflow（干净基线）
> **纪律**：D1–D5 反纸面达成；生产节点（180 / 192.168.0.2）只读或先备份。
>
> 关联：`evidence/20260930-subject/`（主体混乱取证）· `five-node-milestone-20260930.md` · `session-pitfalls-20260930.md`

---

## 0. 病灶对照（为什么先修这四条）

| 主线 | 实测病灶 | 后果 |
|---|---|---|
| **① 构建** | 活机上的 `gnb`/`gnb_ctl` 分居异构树；180 用 `/usr/local/opengnb` 的 ctl 读表得到垃圾（node 2322817668）；补丁 P1/P2 是否打进当前二进制无版本戳 | 脚本找不到主主体；「以为打了补丁」；跨平台产物不可信 |
| **② 配置** | 同机 3–4 套 home（`/var/lib/mynet`、`/opt/mynet`、`/usr/local/opt/mynet`…）；188 cmdline `-c /opt/mynet/var/gnb` 与 find 到的 `driver/gnb/conf/7676…` 是两棵；存储路径 vs 运行路径混用 | 用户配置一团乱码；改 A 树 B 树不生效 |
| **③ 系统/驱动** | `route.conf`（驱动地址宣告）与 OS 路由（`apply --auto`）两层，181 缺 180 的主机路由走 LAN 旁路；防火墙云侧+机内+fw4 三处 | ping 通≠SSH 通；回程不对称；「装了防火墙脚本仍不通」 |
| **④ 更新联动** | bundle 快照不 publish、`MYNET_GATEWAYS` 只认 env、BundleInvalidator 触发面窄 | 配置同步断链、心跳静默不启动 |

---

# 主线 ① 构建与发包基线

## 目标

**每一次「装到节点上的二进制」都能回答三句话**：
1. 从哪个干净基线编的（upstream commit）
2. 打了哪些补丁（P1? P2?）
3. 谁编的（本机 / 0.32 / CI），矩阵里哪一格

## 1.1 包与平台矩阵（V2 真实用到的）

### A. mynet_agent（Go，三/四二进制）

| 依赖 | 用途 | 备注 |
|---|---|---|
| `filippo.io/edwards25519` | gnb 密钥 | 唯一密码学 |
| `github.com/coder/websocket` | WS 节点面 | |
| `github.com/redis/go-redis/v9` | gateway presence | 可选 |
| `fyne.io/systray` | tray（indirect） | **仅 darwin/windows 本机编**，CI 交叉跳过 |

`go.mod` 只有 3 个直接依赖——**很干净**，问题不在依赖面，在**产物来源与配套**。

**产物矩阵**（`Makefile` `DIST_MATRIX`，已对齐 ctl 命名）：

| OS | arch（Go → ctl 名） | 二进制 | 打包 |
|---|---|---|---|
| darwin | arm64, amd64 | mynet, mynetd, mynet_gateway, **mynet_tray**(宿主) | zip |
| linux | amd64, arm64, **mipsle→mipsel** | mynet, mynetd, mynet_gateway | tgz |
| windows | amd64 | mynet, mynetd, mynet_gateway, mynet_tray | zip |
| OpenWrt | amd64 / arm64 / mipsel | 经 `mynet_openwrt` ipk（内嵌 linux 产物） | ipk |

### B. mynet_gnb（C，四程序）

| 程序 | 说明 |
|---|---|
| `gnb` | 隧道主程序 |
| `gnb_ctl` | ctl-block 读写（**必须与 gnb 同构建**） |
| `gnb_es` | 环境服务 |
| `gnb_crypto` | 密钥工具 |

**第三方库**：无外部依赖；`libs/miniupnpc` **已 vendored 进源码树**。可完全离线编。
**Windows**：wintun；**OpenWrt**：`Makefile.openwrt.inc`（musl）。

**补丁栈（干净基线 → 出厂）**：

```text
opengnb 1.6.8 (c411f14ee647)          ← 干净基线（sources/history.ndjson）
  + p1-hot-restart-v2/patches/0001-0005   ← 热重启 + 挂死/日志修复
  + p2-route-proxy/patches/0001-proxy-conf-v3  ← proxy.conf v3
  ──────────────────────────────────
  = 出厂 gnb（含 P1+P2）
```

⚠️ 现行两处危险：
- `p1-hot-restart`（无 -v2）与 `p1-hot-restart-v2` **并存**，选错栈=旧热重启
- `apply.sh` 对嵌套 `src/` 需 `--directory`，`git apply -p2` 会**静默跳过**（脚本已处理，但手编时易踩）

## 1.2 开发/编译分工（按你的建议落成规矩）

| 角色 | 机器 | 允许做什么 | 禁止 |
|---|---|---|---|
| **开发编译** | 本机 181（mac） | `go build`/`go test`、gnb Darwin 本机编、试跑补丁 | 不出「可分发产物」 |
| **开发编译 + 联调** | **0.32**（Linux x86_64，完全可控） | linux/amd64 真编真跑、双节点联调、脚本验收 | 不当生产节点 |
| **正式发包** | **GitHub Actions** | 干净 checkout → `sync_source` 固定基线 → apply P1 v2 → apply P2 → 交叉编 → checksums → 传 ctl | 不在开发机手工打包分发 |
| 生产 180 / 192.168.0.2 | — | 只接受**来自 CI/ctl 的产物** + 先备份 | 禁止 scp 手搓二进制 |

## 1.3 阶段任务

| # | 任务 | 验收（D4 要实测输出） |
|---|---|---|
| B1 | **定基线 SSOT**：`mynet_gnb/target.json` + `sources/history.ndjson` 固定 `1.6.8-c411f14ee647`；废弃无 -v2 的 p1 | 文档+脚本唯一指向 v2 栈 |
| B2 | **干净出包脚本** `make gnb-dist`：临时目录 clean checkout → apply P1 v2 → P2 → 四平台编 → `gnb --version` / strings 含 `proxy.conf` 与热重启标记 | 0.32 上一条命令出包；`strings gnb \| grep -E 'proxy.conf\|Hot Restart'` 命中 |
| B3 | **同构建配套强制**：打包单元 = `{gnb,gnb_ctl,gnb_es,gnb_crypto}` 同目录同 tar；安装后 `subject` 解析（见主线②）只认同目录 | 缺 ctl 则安装失败，不静默 |
| B4 | **agent 产物版本戳**：`-ldflags` 注入 VERSION + `go version -m` 可读基线/补丁号 | `mynet version` 输出三句话（基线/补丁/构建者） |
| B5 | **CI workflow 正式化**：`build_gnb.yml`（矩阵：linux amd64/arm64/mipsel、openwrt 三套、darwin、mingw）+ `build_agent.yml`（现有 `Makefile dist`）→ artifact → ctl | PR 上全绿；release 走 tag |
| B6 | **开发机→0.32 一键联调** `scripts/dev-push-0.32.sh`：只推「同构建套件」+ 校验和 | 0.32 上 `sha256sum -c` 通过才落盘 |

**出口 G-build**：从干净基线一条命令出 P1+P2 套件，0.32 装上跑通热重启 + proxy.conf，产物带版本戳。

---

# 主线 ② 配置：双层职责 + 存储/运行路径

## 目标

**任何人 30 秒内能回答**：改哪个文件？存在哪？运行时谁读？会不会被投影覆盖？

## 2.1 双层职责（已经对的方向，要钉死并去歧义）

```text
┌─ L4 mynet 意图层（真相源，进备份）────────────────────┐
│  $MYNET_HOME/conf/**                                   │
│    mode.json                 激活谁（local|online）     │
│    local/intent.json         本地抽象意图               │
│    online/{tenant}/          profile / bundle / keys    │
│    **/plugins/{name}.json    插件配置（proxy 等）        │
│  规则：业务对象**不含** gnb/wg 术语                      │
└──────────────────┬───────────────────────────────────┘
                   │ Apply（唯一写入者）
                   ▼
┌─ L5 驱动投影层（可再生，禁手改）────────────────────────┐
│  $MYNET_HOME/driver/gnb/conf/<id>/                      │
│    node.conf  route.conf  address.conf  proxy.conf      │
│    security/  ed25519/    gnb.map(运行时)                │
│  $MYNET_HOME/driver/wireguard/<id>/wg0.conf              │
└────────────────────────────────────────────────────────┘
```

| | **mynet 自己的配置** | **驱动配置（gnb / wg）** |
|---|---|---|
| 格式 | JSON（schema v2） | gnb 文本 conf / wg INI |
| 谁写 | 用户 / back 下发 / wizard | **仅 driver.Apply 投影** |
| 谁读 | mynetd、mynet CLI、插件 | gnb / wg 进程 |
| 手改 | 可以（intent.json 等） | **禁止**（会被下次 Apply 覆盖） |
| 真相 | `conf/**` + `mode.json` | 不是真相，是产物 |

**proxy 的两层（易混，单独钉）**：
- `intent.listen.proxy` / `node.conf` 的 `proxy on` = **GNB 数据面准出**（P2）
- `plugins/proxy.json` → 投影 `driver/gnb/conf/<id>/proxy.conf` = **出口选点/权重**（业务意图）
- 二者是「开不开」与「走谁」，**不是**同一件事的两个名字

## 2.2 存储路径 vs 运行路径（现在最乱的一块）

| 概念 | 定义 | 路径 | 进备份? | 谁定 |
|---|---|---|---|---|
| **存储根（Store Root）** | 真相源所在 | `$MYNET_HOME`（或 `MYNET_CONFIG_HOME`） | **是** | install / env |
| **运行根（Runtime Root）** | 进程实际打开的 | 以 **`ps` cmdline `-c`** 为准 | 否 | 运行进程 |
| **投影目录** | 驱动 conf | `driver/gnb/conf/<id>/` | 可选（易再生） | Apply |
| **运行态** | map/pid/log/bundle 缓存 | `$MYNET_HOME/var/**` | **否** | 进程 |
| **快照** | last-good / backups | `$MYNET_HOME/state/`, `backups/` | 是 | Apply/备份 |

### 硬规矩（修「找不到主主体」）

1. **`MYNET_HOME` 必填**：服务单元 / 执行器缺则**拒绝启动**，不落 `~/.mynet`。
2. **运行态解析器 `subject`**（进程锚）：给定 node/pid → `(GnbBin, ConfDir, MapPath, GnbCtl)`；`gnb_ctl` **必须与 gnb 同目录**，缺则 `tools_missing`，**禁止**捡异构建。
3. **一棵活树**：每机只保留一个 Store Root；其余改 `*.legacy-日期` 或移 backup。
4. **手改只准改 `conf/**`**；发现有人改 `driver/**` = 事故，Apply 前 diff 并告警。
5. **`mode.json` 写入 `active` 时附 `source`**，排障时能指出「当初从哪份意图投影的」。

### 路径对照（按节点实测，修完前先认这个）

| 节点 | 活 Store Root | 活运行 conf（ps `-c`） | 问题 |
|---|---|---|---|
| 181 | `/var/lib/mynet` | `/var/lib/mynet/driver/gnb/conf/3764021915150630` | 同目录无 gnb_ctl；另有 3 棵残留 |
| 180 | `/var/lib/mynet` | 同上形式 `4997342800601732` | ctl 在异构建 opengnb；另有 index 进程另一棵树 |
| 182 | `/etc/mynet` | `/etc/mynet/driver/gnb/conf/8283289760467957` | ✅ 最干净 |
| 188 | `/opt/mynet` | **`/opt/mynet/var/gnb`**（非 driver 树！） | 路径双义最严重 |
| 183 | `C:\mynet` | `C:\mynet\driver\gnb\conf\8107260975133902` | 另有 `Program Files` / `mynet-test` / `win-pkg` |

## 2.3 阶段任务

| # | 任务 | 验收 |
|---|---|---|
| C1 | **路径 SSOT 文档**：一页表（存储/运行/投影/运行态）进 `spec/paths.md` | 新人 30 秒能答 |
| C2 | **`mynet subject` 解析器**（进程锚 + R1–R6） | 五机输出唯一且与 `ps` 一致；异构建 ctl 必报错 |
| C3 | **MYNET_HOME 强制** | 缺 env 时 install/service/executor 拒绝 |
| C4 | **单树收敛脚本** `mynet tree doctor` + `tree consolidate`（只标记/搬迁，不删） | 181/183/188 候选树只剩一个 chosen |
| C5 | **配置导入导出只认 `conf/**` + mode.json**（已基本如此）+ 手改 driver 告警 | `config export` 不打包 driver/** |
| C6 | **proxy 双层命名澄清**（intent.proxy vs plugins/proxy） | 文档 + `mynet proxy --help` 不再混 |

**出口 G-config**：`subject --json` 五机一致；`tree doctor` 零歧义；改 `conf/**` → Apply → 驱动文件变化可预期。

---

# 主线 ③ 系统层与隧道（gnb/wg）分工

## 目标

**路由和防火墙只有一套写入规则**：谁宣告、谁装 OS、谁放行，不再互相踩。

## 3.1 职责边界（按 AGENTS.md L5/L6 + 实测教训钉死）

| 层 | 谁 | 写什么 | 不写什么 |
|---|---|---|---|
| **L5 驱动** | gnb / wg 自己 | gnb：`route.conf`（**地址宣告**：谁拥有哪些 VIP/网段）、`address.conf`（邻接）、peer 密钥<br>wg：`AllowedIPs`（对端 TUN **/32**） | **不装 OS 路由**；**不碰防火墙** |
| **L6 系统** | `mynet apply`（唯一写入者） | OS 路由（VIP/32 + 需要的网段）、防火墙（UDP listen、FORWARD、NAT/MASQ）、策略路由/fwmark（若用） | 不改驱动 conf 原生文件 |
| **云/机外** | 人 / 云 API | Lightsail/安全组 UDP listen | — |

```text
包从哪来                谁决定
─────────────────────  ─────────────────────────────
系统 ip route           L6：是否进隧道
gnb route.conf          L5：进了隧道后，目的归属谁（命中则普通转发）
gnb proxy.conf (P2)     L5：未登记目的走哪个 exit
wg AllowedIPs           L5：cryptokey routing 范围（只放对端 /32）
OS 回程路由             L6：对端怎么把回包送回（必须对称）
防火墙 UDP/FORWARD      L6 + 云：放不放行
```

**边界判据（用实测案例）**：

| 案例 | 归谁修 |
|---|---|
| 181 `ping 180` 走 LAN 旁路（缺 utun 主机路由） | **L6**：`apply --auto` 没装 `/32` |
| 188→181 `via 172.26.0.1` | **L6** 回程/出站路由缺失或被 AWS 路由抢 |
| zip 不一致大包丢 | **L5 对等配置**（node.conf parity），不是防火墙 |
| Lightsail 开了仍不通 | **云 + L6 防火墙**两层 |
| `route.conf` 灌 700 条公网段 | **禁止**（叙事已转 proxy.conf）——只做地址宣告 |
| ping 通 SSH 不通 | 先查 L5 对等（zip）再查 L6 MTU |

## 3.2 gnb vs wireguard 对称表

| 能力 | gnb | wireguard | 写入者 |
|---|---|---|---|
| 对端地址 | `address.conf` `n\|id\|ip\|port` | `Endpoint` | driver 投影 |
| 目的归属 | `route.conf` `id\|ip\|mask` | `AllowedIPs`（/32） | driver 投影 |
| 准出/代理 | `proxy on` + `proxy.conf` | 无（用 AllowedIPs/策略路由） | 意图→投影 |
| OS 路由 | `mynet apply --auto` | `mynet apply --auto`（或 wg-quick Table=off） | **L6 only** |
| 防火墙 UDP listen | L6 + 云 | L6 + 云 | L6 |
| NAT/MASQ | L6（出口节点） | L6 | L6 |
| 热重载 | P1 热重启 | `wg syncconf`（语义不同，不可混） | driver |

## 3.3 阶段任务

| # | 任务 | 验收 |
|---|---|---|
| N1 | **边界 SSOT** `spec/route-firewall-split.md`（上表 + 判据） | 评审通过 |
| N2 | **`mynet apply` 语义收口**：`--active`=投影、`--auto`=OS；服务启动默认**两者**；help 写清 | 「改了配置路由仍旧」类坑消除 |
| N3 | **L6 唯一写入者落地**：`apply_plan.go` 为唯一 Route/Firewall 写入口；`check-route-writers.sh` 门禁扩到 OpenWrt 脚本 | 无第二处 `ip route`/`nft add` 散落 |
| N4 | **回程对称检查器** `mynet route doctor`：每 peer 本机 `ip route get` / 对端 get / gnb in-out | 十对矩阵可自动跑 |
| N5 | **防火墙安装链**：`firewall.mynet install` 要 HOME；两层检查（云侧清单 + 机内 nft/iptables）；OpenWrt fw4 持久化 | `fw4 reload` 后规则仍在 |
| N6 | **驱动对等项 parity**：zip/crypto/passcode 必查 | `config parity` 绿 |

**出口 G-net**：五节点十对全通 + 大包 + `route doctor` 零缺失；L5/L6 文件归属可审计。

---

# 主线 ④ 配置同步与更新联动（在 ①②③ 之后）

| # | 任务 | 验收 |
|---|---|---|
| U1 | 改 IP/密钥/role 后强制 `bundle/publish`（补 IP/新建节点触发） | BundleInvalidator 全覆盖 |
| U2 | `refresh-config` vs `config pull` 语义二分（或合并 `config sync`） | help/文档一致 |
| U3 | `MYNET_GATEWAYS`：env → mynetd.env → agent.json；再缺**拒绝起心跳**并指因 | 不再静默 |
| U4 | 真升级一轮（或 dry-run 含校验和+回滚） | G-update |

---

# 主线 ⑤ DNS 服务与代理分流（2026-09-30 追加）

> **产品裁定**（用户确认）：DNS 是代理分流的判路依据。按「网关 / 客户端」两条线落地。
> 每个租户一份智能 DNS —— 这是平台服务，不是用户自建。

## 5.1 架构裁定

| 场景 | DNS 怎么来 | 接管策略 |
|---|---|---|
| **网关 / Linux 软路由** | ① 本机搭干净 DNS（dnsmasq/SmartDNS）或 ② 指到平台智能 DNS | **默认接管**；网关上**一定**接管。机器上已有智能 DNS 则用之，没有 → 接我们的 |
| **客户端**（Win / Mac） | 不自建 DNS；**指到本租户的平台智能 DNS** | 默认接管；用户可关 |
| **用户不接管** | 保持系统 DNS | 显式关闭才生效 |

**平台侧两种服务**（ctl/back 提供）：
1. **干净 DNS**：结果不污染，国内外可用
2. **智能 DNS**：国内接入、按域名智能拆分（国内快答 / 境外干净答）—— **每租户一份**

## 5.2 判路链（钉死）

```text
域名解析
  ├─ 国内域名  → 国内智能 DNS / dns-domestic.txt（快）
  └─ 需干净的  → 平台智能 DNS / dns-clean.txt（不污染）

解析答案 → 分流依据
  境外 IP → 进隧道/代理
  境内 IP → 直连
```

## 5.3 阶段任务

| # | 任务 | 验收 |
|---|---|---|
| D1 | **每租户双智能 DNS**（国内一台 + 国外一台）：国内智能 + 国外智能；客户端声明 region 后下发对应端点；白名单/需干净走 DoW（真实 IP）| `dig @<tenant-dns-domestic>` / `@<tenant-dns-intl>` 分别快答；DoW 返回绝对真实 IP。详见 `spec/proxy-split.md` §13 |
| D2 | **ctl 发布 4 份清单**：`dns-domestic.txt` / `dns-clean.txt` / `proxy-domains.txt` / `direct-domains.txt`（文件 + URL + sha256） | envelope 引用可拉取 |
| D3 | **网关 DNS 接管**：探测本机有无智能 DNS → 有则用，无则配平台 DNS；dnsmasq/SmartDNS 生成分流片段 | 默认开启；`dns hijack=off` 可关 |
| D4 | **客户端 DNS 接管（Mac/Win）**：系统 DNS → 127.0.0.1:53（本地 forwarder）→ 租户智能 DNS；**备份/还原**幂等 | 启动改、停止还原；crash 后二次启动能补还原 |
| D5 | **Mac/Win 小域名白名单**：清单 + 白名单 + 自定义叠加（§3.2 优先级） | 100 条清单 + 加 `abc.com` = 101 条生效 |
| D6 | **IP 大表链路**（Linux 既有）：chinaip/interip → nft set / ipset（保持） | `route doctor` 绿 |
| D7 | **P2 proxy.conf 出口选点**（已修格式）：[default]/[group] CLI + WebUI | `plugin exits/groups` 可写 |

## 5.4 平台分工

| 组件 | 职责 |
|---|---|
| **back/ctl** | 租户智能 DNS 服务、干净 DNS、4 份清单发布、Region×Policy 解算 |
| **agent（L6）** | DNS 接管（改/还原系统 DNS）、dnsmasq/SmartDNS 片段、nft set、fwmark |
| **agent（P2）** | `proxy.conf` 出口选点（role/default/group） |
| **gnb/wg** | 隧道数据面，不管 DNS |

## 5.5 明确不做

- 不在 Mac/Win 跑几万条 IP 大表（没有 ipset 等价物）
- 不让 mynetd 直接改路由/防火墙/DNS（guard B2，归执行器）
- 不自建「每个用户自己的 DNS 服务器」——是**每租户**的平台服务

---

# 顺序总图

```text
① 构建基线 B1–B6  ──┐
                     ├─► ④ 同步/更新 U1–U4 ──► 再部署门禁
② 配置/路径 C1–C6  ─┤
③ 系统/驱动 N1–N6  ─┘
⑤ DNS/代理 D1–D7   ─┘

约束：①未绿不装机；②未绿不动配置；③未绿不扩节点；④未绿不升级生产；⑤未绿不谈分流。
```

**明确不做**：换密钥；合并 legacy 树逻辑（只隔离）；再部署当验收；在开发机手工分发生产二进制。

---

# 立即下一步（建议执行序）

1. **B1+B2**：定基线、写干净出包脚本（0.32 上跑通 P1 v2 + P2）
2. **C1**：`spec/paths.md` 路径 SSOT（半天，立刻降混乱）
3. **B3+C2**：同构建配套 + `subject` 解析器
4. **N1+N2**：边界文档 + apply 语义
5. 再进入互互通矩阵与部署门禁

---

## 附：证据索引

| 文件 | 用途 |
|---|---|
| `evidence/20260930-subject/` | 五机主体混乱取证（§2.2 表来源） |
| `mynet_agent/Makefile` | DIST_MATRIX / ldflags / pack |
| `mynet_gnb/sources/history.ndjson` | 干净基线 commit |
| `mynet_gnb/packages/1.6.8-c411f14ee647/{p1-hot-restart-v2,p2-route-proxy}/` | 补丁栈 |
| `mynet_agent/shared/domain/config_schema.go` | conf/ plugins/ var/ 三分 |
| `mynet_agent/mynet/apply_plan.go` | L6 Route/Firewall 唯一写入 |
| `mynet_agent/mynet/driver/gnb.go` | L5 投影（node/route/address/proxy） |
