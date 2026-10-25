# PC 白名单加速生命周期

日期：2026-10-18。适用 macOS / Windows 以及 Linux / BSD PC 形态的 IPv4 域名加速；网关的 nft / 策略路由机制不变。
本合同替代 `proxy-split.md` §15.5 原先的 apply 批量预解析与双清单写入路径。

## 设计约束

1. **小集合**：域名清单是候选范围；运行态只加载实际查询命中的有效地址。活跃域名与 IP 各上限 512；不批量预解析白名单。
2. **白名单**：默认直连。ctl 基础代理清单加本机自定义，本机显式白名单排除项优先，包含子域例外。主域规则匹配自身与子域。
3. **单白名单**：PC 仅从 ctl 下载 `proxy-domains.txt`，未命中默认直连。基础直连表不参与 PC 加速决策，旧 `upstream_clean` 也不扩大加速范围；本机显式白名单排除项保留。基础更新能够撤回旧条目。
4. **缓存**：DNS 应答缓存和路由租约分离；缓存可以省上游查询，不能代替对内核路由的确认。
5. **自动加载与释放**：由查询、规则变化、过期、隧道变化、进程退出驱动。只修改本功能拥有的路由与 DNS 设置。
6. **直接失败**：容量满、无可用隧道或路由安装失败时向该查询返回 SERVFAIL，不通过旧 IP 或系统解析器隐式放行。普通域名继续使用国内上游。

## 平台与运行形态

- macOS / Windows 固定 PC；OpenWrt 固定 GW。Linux / FreeBSD / OpenBSD / NetBSD 使用 `mynet config form pc|gw`，或 setup 的 `--runtime-mode pc|gw` 显式选择。旧 Linux/BSD 安装缺省 GW，避免升级自动接管本机 DNS。
- 形态保存在 `conf/runtime-mode.json`，与 local/online 身份模式独立；切换先释放旧形态资源，再应用新形态。WebUI 提供选择和 PC 加速启停，无须托盘。
- Linux/BSD 发布包均嵌入 WebUI，GW 也可使用；OpenWrt 保持精简包。BSD 本批提供 PC 代理路径与包构建，未增加 PF 网关代理后端或原生 rc 服务安装器。
- Linux PC 使用 `ip` 主表路由，BSD PC 使用 BSD route 方言，均由同一白名单会话驱动。GW 不自动启动 PC DNS hook，也不再由旧 DNS-plan sidecar 接管系统 DNS。

## 状态与所有权

- `conf/local/proxy_domain_baseline.json`：ctl 加速白名单基线（代理域名及内容 hash 版本；旧 Direct 字段仅兼容解码），独立于 `proxy_intent.json`。
  配置目录跟随显式 config base；迁移与测试其他 home 时不读取当前实例的全局路径。
- `proxy_intent.json`：本机意图。基线存在时读面投影最新基础清单，写面不保存基础清单副本；自定义域名、直连例外与自定义 IP 保留。
  尚未下载基线的旧安装沿用已有内嵌清单，首次成功下载后以 ctl 基线为准。
- `var/proxy/dns-hook-pins.json`：域名 → IP → 租约截止时间，以及 prefix → interface 的路由所有权日志。
  兼容 `ips` 读面，另含最后一次对账错误。多个域名共享 IP 时只安装一次，最后一个有效引用消失才释放。
- DNS 应答缓存：进程内，最多 4096 项；按 DNS TTL 过期，最大 300 秒。返回客户端的 TTL 同步缩短，缓存应答按时间老化；TTL=0 不缓存。
- `var/dns-backup.json`：每个网络服务各自原 DNS 设置；空列表表示 DHCP。先备份再接管，失败回滚。
  恢复时只处理仍指向本次目标的接口，用户后来修改的 DNS 保留。没有备份不重置用户配置。
- Linux/BSD 的 DNS 备份另保存 `/etc/resolv.conf` 原始内容、权限及符号链接。接管时原子替换链接本身，不写穿 resolver 管理器的目标；恢复仅处理内容仍属于本次接管的文件。管理器或用户已改写时保留其设置，网络管理器实际兼容性仍需真机验证。
- PC 加速统一以 `proxy_intent.json` 的 Local 客户端 `enabled` 为持久开关；托盘、面板与 CLI 使用同一选择。PID、隧道与路由仍是独立运行事实。
- `dns-hook-intent.json` 仅作旧版兼容镜像（`version: 1`）。无 version 的旧文件在首次启动隧道前迁移，保留用户最后一次加速选择；新策略保存后覆盖旧镜像，避免旧开关复活已关闭的 proxy。

## 事件处理

| 事件 | 动作 |
|---|---|
| 未连接时开启加速 | 保存意图，等待隧道；不接管系统 DNS；托盘可取消等待 |
| 隧道连接 | 恢复上次加速选择；先迁移旧开关并应用驱动出口投影，再按意图启动 hook |
| 在线开启加速 | 先持久保存客户端 proxy 开关并更新驱动配置，再启动 DNS hook；失败保留选择并报错 |
| 面板保存 proxy 策略 | 先保存再执行投影与 proxy apply；PC 同步启停 hook，避免执行器读到上一版策略 |
| 首次查询白名单域名 | 查询配置上游，建立域名/IP 租约，安装路由后返回应答 |
| 命中应答缓存 | 不查询上游；重新确认内核路径，缺失时补装；不延长原租约 |
| 相邻地址出现/过期 | 精确合并/拆分 CIDR，撤销旧前缀；不扩大覆盖范围 |
| 删除域名/加直连例外 | 下次对账清理缓存和该域名租约；共享 IP 保留其他引用 |
| 出口 DNS 上游变化 | 丢弃旧应答缓存与旧解析租约，按新上游重新建立 |
| 隧道接口变化/路由丢失 | 重装当前有效租约；旧接口的自有路由精确释放 |
| 地址到期 | 下次对账删除租约与失去引用的路由；不无限保留历史 IP |
| 隧道意外消失 | worker 退出，监督进程恢复 DNS、释放路由；保留用户意图 |
| 用户关闭 | 关闭客户端 proxy 开关，停止查询、等待在途处理结束、撤路由、恢复 DNS；在线时更新驱动关闭投影 |
| worker 异常退出 | 独立 watcher 等待退出后清理；意图仍开且隧道可用时重启并重新接管 DNS |
| 程序重启 | 恢复仍有效的域名租约，核对内核；过期/已移出白名单的地址不加载 |

规则、租约和内核路由每 10 秒对账；缓存命中路径也核对路由。规则刷新与查询共享策略读写锁，旧上游的在途回答不会在新规则生效后重新写回旧缓存。
ctl 清单在 hook 启动后后台下载，此后每小时刷新；下载成功后下一轮对账应用。加速白名单完整下载并解析后原子替换，失败保留此前成功的基线并记录日志。
分发基址共用 `MYNET_APPS_INDEX`，默认 `https://ctl.mynet.club`；PC 使用 `/d/plugins/mp/proxy-domains.txt`，不请求 `direct-domains.txt`。

## 实现边界

- PC 路由唯一运行写入者为 `pcProxySession`（dns-hook 内）；apply 不再批量解析域名或把 hook IP 合并进另一份活动清单。
- 每轮只读一次 IPv4 FIB，检查整个候选前缀；已由外部路由送入隧道的目标不接管其所有权。
- 路由日志写入先于安装；关闭/异常退出可精确清理。内核已被用户改到别的接口的路由不删除。
- 启停用内核文件锁串行，不加重试抢锁；停止优先走请求文件，超时终止前校验 PID 对应的执行器命令。
- watcher 只监督本次 worker，避免 worker 异常退出把系统 DNS 留在失效的 loopback 端口。watcher 自身被强杀/断电后的恢复依赖下一次启动或 `mynet dns restore`。
- 当前仍是 IPv4/A 记录加速；未扩展 IPv6、HTTPS/SVCB hints、TCP DNS 或应用自带 DoH 的拦截。
- 基于 IP 路由的选择会影响共享该 IP 的其他域名；删除路由不等于主动终止已有 TCP 会话。

## 旧状态迁移

旧 `pc-pin.json` 与没有域名归属的 hook IP 清单只用于定位并释放历史路由，不转换为永久有效的新租约。
新地址由实际 DNS 查询重新建立归属。旧的 `pc-dns-cache.json` 不再读取，去掉过期地址无限复用的 fallback。
部署切换后需要重新查询目标域名；首次迁移与本机 DNS 缓存的配合应在真机验收中核对。

## 验证

自动化覆盖：按需加载、共享引用、删除域名、租约过期、CIDR 合并/拆分、路由丢失、接口更换、断开与重启、释放、外部所有权、容量上限、安装失败重试、旧清单迁移、缓存修路由、TTL 老化/上限/零值、直连子域例外、ctl 单白名单更新/撤回/失败保留、本机叠加不遮住基线、逐接口 DNS 恢复与失败回滚、启停互斥。

执行记录见 `project-memory.md` 的 2026-10-18 PC 条目。自动化通过与实机验收分开记录；本批不勾 M4/M6，不替换在运行的程序、不修改宿主 DNS/路由。

## 启停状态反馈（2026-10-18）

执行器的 dns-hook status 保留 alive/wanted 字段，并增加 state：running、disabled、waiting_tunnel、failed。wanted=true 且隧道存在但 worker 不在时为 failed，不再显示为等待连接。托盘按轮询刷新加速状态，并显示操作失败信息；WebUI 采用相同状态字段。隧道成功与加速成功分别判断，侧车失败不回滚已建立的隧道。

proxy apply 与 dns-hook start/stop 加入本机 API 的配置写互斥；status 读面保持可用。GNB 禁用投影显式写 proxy off 与 ingress_open off，覆盖旧启用文件，不以跳过写入代替关闭。

PC 清单分发：公开加速白名单位于 `/d/plugins/mp/proxy-domains.txt`，`d` 是 ctl 分发路由组前缀。生产须部署该文件的 route/controller allowlist、scripts 模板和 storage 发布产物；维护清单先于 GeoIP 下载发布。`direct-domains.txt` 端点为旧客户端兼容保留，新 PC 客户端不下载、不依赖，也不采用旧快照的基础直连表。本机自定义排除项属于可选的白名单例外，不是另一份默认分流清单。


## 外部 DNS 接管监测（2026-10-19）

加速开启后须核对当前活动网络服务及系统默认解析器，不能以 worker 存活或历史 applied 标记代替真实 DNS 所有权。macOS 订阅系统 DNS 变化，并以 3 秒轮询兜底；读取失败或观测过期显示状态待确认。

确认 DNS 被外部修改时，先持久化中断标记，再停止 hook、释放其路由，并只恢复仍归本程序所有的 DNS 配置，保留外部设置。状态为 dns_overridden / active=false；保留用户开启意愿，但 watcher、服务恢复和后台 apply 不得自动重新抢占 DNS。托盘和网页显示加速已中断，用户明确点击重新开启才清除中断并接管。维护停止支持 --preserve-intent，避免升级改变开启意愿。


## DNS hook ad-block（2026-10-20）

PC hook默认开启独立的广告/恶意域名拦截，与加速白名单分离。内置35条常见广告服务后缀+418条URLhaus恶意主机精确快照（来源与时间保留），共453条。日常由ctl分发ad-domains.txt、malware-domains.txt，内置仅作尚无成功缓存时兜底。查询按域名标签哈希查找，拦截优先于上游/缓存/加速路由；本机allow优先于拦截。CNAME目标也检查。

A返回127.0.0.1、AAAA返回::1，其他类型NOERROR空答案，TTL60秒。配置conf/local/dns_adblock.json支持enabled/block/allow，每10秒应用，失败保留运行规则；首次启动配置非法则明确报错。CLI proxy dns-hook ad-block on|off|status，网页提供开关并明确仅hook运行时生效。该配置不启动hook、不改加速意愿，不解除外部DNS接管中断。

维护与范围见mynet_agent/mynet/dnsblock/README.md；不宣称拦截应用DoH或正文共域广告。


2026-10-20 ctl名单管理补充：hook启动后台及每小时下载两表，完整校验后成组原子落盘conf/local/dns_block_baseline.json，10秒规则对账内应用。失败保留旧缓存，合法空表可撤回全部基础拦截规则；下载不覆盖本机enabled/block/allow。手动proxy dns-hook ad-block refresh只更新名单，status报告来源及版本。基址沿用MYNET_APPS_INDEX，路径/d/plugins/mp/{ad-domains,malware-domains}.txt；与加速白名单下载独立。维护规范见scripts/proxy-lists/README-adblock.md。


## 干净DNS主备顺序（2026-10-20）

用户最终裁定PC DNS hook加速域名的干净DNS首选221.216.138.69，peer备用（替代此前不可达的112.124.22.220）。执行器顺序：固定干净IP → 配置TunnelServer → ExitDNS → 启用出口peer VIP，去重。移除隐式平台公共UDP域名回退，避免peer失败时引入非预期解析。无peer/不完整规则也保留固定干净IP。主解析器超时、网络错误或返回SERVFAIL/REFUSED时继续下一个；有效NXDOMAIN仍为权威应答，不强行向其他源探测。普通直连域名的国内DNS及ad-block优先级不变。


## 租户下发与本机自主DNS（2026-10-20，替代固定首选语义）

干净DNS为「下发+本机覆盖」：本机clean_dns列表优先，随后当前在线租户profile.TenantDNS、策略domain_rules.platform_dns，然后peer TunnelServer/ExitDNS/启用出口VIP。没有本机/租户/策略DNS时才使用221.216.138.69默认兜底，再尝试peer。按端点逻辑顺序去重，主机名不会因未bootstrap或旁边有peer IP而被降序；域名bootstrap仍独立于系统解析器。直连域名和ad-block优先级不变。

本机配置独立落conf/local/dns_hook.json（设备级，非云端proxy_intent同步对象），字段clean_dns为1–8个IP/域名；空列表跟随租户。禁止loopback/未指定/多播IP、URL、端口、通配符。配置变更10秒对账时生效，并清旧缓存/旧上游地址租约。损坏配置保留运行规则，首次启动报错；普通云端刷新不会清除本机配置。

CLI：proxy dns-hook dns set <IP或域名...>、dns clear、dns status [--json]。WebUI运行形态PC区域提供填写、保存、恢复跟随租户，并显示来源和上游顺序；轮询不覆盖未保存输入。操作不会启动hook/修改加速意愿。

本机此前用户明确选定的221.216.138.69迁移为显式本机配置，保留原选择；新安装不自动创建该覆盖文件，优先使用租户下发。未增加对其他机器配置的自动迁移。
