# 出口授权小闭环 Playbook

> 2026-10-08；状态：**L0–L2 代码结构闭环已闭合**（能授权/发布/应用/撤销/失败回退，有测试守护）；上线前实机验收见 §10。上位基线：`mynet_agent/docs/MYNET_V1_PLAYBOOK.md` §7 P4，以及 `p4-forwarding-proxy-plan.md` §7.4–7.8。

## 1. 目标与边界

让节点属主给朋友授权一个出口，并能证明 API 写入、bundle 许可、agent 应用和撤销清理是同一件事。
首个场景：隔离网络区 T，属主 A 的出口 E 和自家消费节点 O，好友 B 的消费节点 C，未授权用户 D 的节点 U。使用 Linux GNB 两端作为首个真实数据面样本；API 经 gateway 访问，身份从现有会话取得。

本闭环不扩展 console 公共出口、GUI 页面、OpenWrt Lua 迁移、回程路由自动注入、Acceleration、WG 等价验收、安装器或 UTC。通过本闭环不勾 M4/M6；那些独立缺口继续保留。

## 2. 当前资产与风险

- API：`mynet_api@b38c36a`，`ProxyExitController`、`ProxyAuthDomainService`、`ProxyExitApiTest`、`ProxyExitGrantTest`。
- agent：`mynet_agent@0bb3a01`，`shared/domain/proxy_policy.go`、`mynet/proxy_apply_test.go` 和 apply 回滚路径。
- 契约：wire `network.proxy` 只有 `enabled` 与 `acl`；本机分流策略归 Local，不从 wire 下发。
- 特别缺口：`ProxyExitController::destroy()` 文案称“回默认开放”，API 测试也只锁 GET 的 `mode=open`；`resolvePermission()` 在无 active 授权时却返回 null，publisher 不带 proxy。必须验证真实停用与读面说明，不能用 ACL 单测代替。
- `grant()` 多步撤销/登记当前未包事务；并发替换、途中失败及 bundle 读取的一致性必须取证，不预设无风险。
- 既有 454 API 测试、20 GUI 测试等只是历史证据；本轮尚未运行。

## 3. 冻结合同

| 对象/行为 | 本闭环不变量 |
|---|---|
| 出口 E | 节点属主、当前服务端 tenant 作用域；存在性/属主判断复用 OwnerGuard，不自造身份 |
| 授权 | `proxy_exit_grants` 的 active/revoked 生命周期；整组替换；无 TTL，不添加假过期语义 |
| PUT | `/api/v1/nodes/{id}/proxy-exit`，body `{friend_ids: int[]}`；去重后验证好友与可连节点；任一不合格整体拒绝且旧授权不变 |
| 空数组 | 建 active 的开放授权，组织内开放；不等于删除授权 |
| DELETE | 撤销全部 active 行；`resolvePermission=null`，bundle 不带 `network.proxy`。不得据 GET 的 `mode=open` 推断出口启用 |
| 白名单 | 好友有效节点 + 属主自家节点；CIDR 精确 /32；不得扩大到整个网段 |
| wire | 沿用既有 schema/checksum/version；不新增 role、exits、DNS 或 client 策略字段 |
| 错误 | 保留当前响应信封；非属主 403、缺失 404、校验失败沿现有 helper；先取证实际状态码再冻结 fixture，拒绝改成 500 |
| 版本 | 新授权/撤销必须能走现有发布/拉取路径生成可应用的新版本；若版本或同步触发不足，登记并只修该链路 |

P0 先核准“删除=撤销出口许可”的代码与上位裁定一致性；若用户要求删除后继续开放，停在该依赖处形成明确决策，不私自切换含义。

## 4. 工作包与顺序

| 包 | 责任/修改边界 | 输入与交付 | 验收/禁区 |
|---|---|---|---|
| L0 合同锁定 | ctl 文档/OpenAPI；API 既有响应取证 | 对照上述五态：无授权、开放、白名单、撤销、非法请求；生成跨语言脱敏 fixture 与响应表 | 明确许可存在性与 ACL 的区别；核对 DELETE 描述；不新增响应字段、不改业务语义 |
| L1 写入到发布 | API VPN 领域/测试，必要时控制器 | 在隔离 DB 跑 PUT→GET→真实 publisher→DELETE→publisher；回写版本和 checksum 证据 | 覆盖非好友、无可连节点、跨租户、非属主；失败不改旧授权。并发 PUT/DELETE、注入写失败、重复请求；若取证显示中间态，使用事务/出口级序列化且不加唯一索引 |
| L2 应用到清理 | agent shared/domain + mynet apply/Proxy 测试 | 消费 L0 fixture；以伪身份/执行器边界捕获 NAT、ACL、规则变更 | 新版本白名单生效；重复 apply 幂等；撤销清理出口规则、保留无关/Local 规则；旧版或非法 checksum 拒绝；失败回滚上一代。不能改 gateway 鉴权或把执行能力放 mynetd |
| L3 隔离实机验收 | API/gateway/agent 串联，ctl 证据记录 | E/O/C/U 场景的正反结果、版本流转、清理前后差异、回滚结果 | 白名单 C/O 允许、U 拒绝；开放与撤销分别验证；出口离线时消费端是否意外走本地公网须实测，若发生则闭环失败、登记后修复限定路径 |

依赖：L0 → L1/L2 → L3。L1/L2 接口冻结后可并行，但默认单执行者；本文不启动代理或额外聊天。
每包只交付一项不变量，提交按 `area: short-description`，不夹带全项目清理。

## 5. 数据、安全与失败处理

API 检查授权请求 body 的 ID 元数据；publisher 转换成许可/CIDR；agent 检查并应用配置 body。不检查、不保留、不外发用户流量 payload。gateway 继续透传，不做业务授权。
证据只留脱敏测试 ID、提交 SHA、schema/version/checksum、规则差异、请求结果；不留 cookie、token、私钥、真实好友图或原始 bundle。凭证只走 crt/crtvault。
离线/版本异常不能静默开启出口。Cloud 许可撤销与 Local 策略关闭分别验，不混为同一操作。超时走现有配置限值并记录实际值，禁止靠无限等待或 skip 算通过。
固定 fixture 输入并比较规范化输出；不引入随机节点标识或新随机算法。配置请求沿现有缓冲与大小限制，不新增流式合同。

## 6. 迁移、执行与回滚

默认只用隔离 DB、测试节点与现有 migration；不对生产库执行 migrate，不更名现网 `mynet_back`。先查 `proxy_exit_grants` 是否存在，不存在就不能声称发布链路可用。
新增 schema 若成为必要条件，另交 DDL/restore 件与备份校验，核准后再执行；不得把测试建表冒充生产落地。
L1 失败：事务整组回滚。L2 失败：保留 last-known-good、还原由本次写入的规则；L3 失败：恢复测试前授权、Local 配置和规则，核对无残留。不得回退其他用户的并发修改。

## 7. 门禁与完成定义

1. 基线记录各相关仓 HEAD、dirty 状态、工具版本和隔离资源，确认不误用生产身份。
2. L1 跑出口授权 API/领域测试及现有 MySQL 测试组；publisher 必须真调用，不能仅检查专表。
3. L2 跑 Go wire/apply/Proxy 相关测试，验证失败、重放、规则归属和脱敏；运行命令以 agent 绝对 cwd 开头。
4. 相关仓 `make check`；ctl 文档门禁按 Makefile 当前目标；OpenAPI 与 fixture 同步验证。未碰 GUI/安装器，不扩展无关测试。
5. L3 真 Linux 数据面连通与拒绝证据齐全；只有 mock 时标记“部分完成”，不勾闭环。
6. 将运行输出摘要、失败处理、清理结果写入本文件验收表，更新 project-memory 指针；不在旧总计划中勾 M4/M6。

| 包 | 状态 | commit/命令/结果/限制 |
|---|---|---|
| L0 | **已完成**（2026-10-08） | 合同锁定件 `proxy-exit-contract-lock.md`；fixture `spec/fixtures/proxy-exit/`（6 件，`ProxyExitContractDumpTest` 真链路 dump，时间戳脱敏）；P0 裁定 **DELETE=撤销出口许可**（`resolvePermission=null`，bundle 不带 proxy）；文案对齐 Controller/OpenAPI/测试名（不改行为）。命令：`phpunit --filter 'ProxyExit'` 14 绿；全量 `phpunit` **455/455**；`pint --test` 干净 |
| L1 | **代码闭环已完成**（2026-10-08） | 事务/节点行锁/提交后发布；跨租户好友与跨区节点 fail-closed（`resolveFriendNodes` 限 tenant）；双连接并发替换在隔离 MySQL 验证行锁阻断 + 终态合法；失败注入保留旧授权。全量 `phpunit` **460**（1 skip=sqlite 下并发）；`test-mysql` **161 passed / 705 assertions**。真实 gateway 多节点流量矩阵仍属 L3 |
| L2 | **代码闭环已完成**（2026-10-08） | 出口 NAT/ACL 专表/专链、替换/清理/幂等/故障回退 + 隔离 Linux 内核测试；**旧 generation 重放拒绝**（`GenerationRevision` 映射 `rev-{YmdHis}-{nodeId}-{seq}`，`ApplyCloud` 单调门禁：旧代拒、同代改写拒、同代同容幂等）；撤销保留 Local client。`go test ./...` 全绿。出口离线流量、iptables-legacy/OpenWrt 兼容仍待 L3 |
| L3 | 未进入 | 已有独立容器内核规则验证（nft/iptables）；不是 API/gateway/E/O/C/U 实机全链验收 |

每包交付报告须含：工作包、改动文件、接口变化、行为/迁移/安全影响、测试命令与结果、已知限制、后续依赖。执行证据与剩余条件见下节。

## 8. 2026-10-08 执行结果

### 落地与证据

- 整合另一执行者留下的 L0 合同、文案与 fixture；修复并行修改造成的语法/重复 import；未提交任何改动。
- API grant/revoke 使用事务与稳定出口节点行锁。失败注入测试原来丢旧白名单（红），修复后保持旧授权（绿）。提交后复用 BundleDirtySet/Invalidator 自动发布。
- API 真 HTTP PUT→publisher→重复 PUT→非法 PUT→开放 PUT→DELETE→重复 DELETE：版本变化/幂等/checksum/精确 CIDR 都有断言。同秒 bulk 撤销测试原先 pull 判非脏（红），许可内容比较后绿。
- agent 省略许可不再留下旧 Cloud：先红后绿；撤销保留 Local client。三个真实 publisher 信封落到 agent `testdata/proxy-permission-loop/{allowlist,open,revoked}.json`，Go 验 checksum、解码、投影，并验证篡改许可被拒绝。
- API 正常测试导出不再写兄弟仓；L0 fixture dump 默认去临时目录，只有显式配置输出目录才更新知识库。

### 独立门禁失败与下一包

从 agent 根运行 `sh scripts/check-proxy-permission-gate.sh`：两个 `TestLoopProbe` 失败，exit=1。
1. `proxyCleanupCmds` 停用计划只有 DNS host route / rule / route table 清理，没有出口 nft NAT/ACL 删除。
2. `ProxyACLCmds` 的兜底 DROP 为 optional，命令失败可被忽略。

这是调用真实计划生成器的隔离测试，没有修改系统规则，不是 Linux 实机证明。失败诊断源码保存于 `testdata/proxy-permission-loop/blockers.go.txt`，脚本临时装入测试后自动删除。默认 make check 的绿不能替代这条独立验收。
下一包限定为：出口规则改为节点可归属的独立链/表，先保证 DROP 兜底且失败报错；整组替换与清理、重复 apply、失败还原上一代规则都由 fake executor 验证，再在隔离 Linux 实测。不得删除共享 nat/filter 表或清空全局 FORWARD 链。旧版本公共链规则的迁移清理需只删除本节点规则，不能凭名字批量清理；方案冻结后再落码。
双连接 PUT/DELETE 并发验证、跨租户拒绝、旧 generation 重放、真实 gateway 通知与实机访问结果尚未齐备，因此 L1/L2 均只标部分，M4/M6 不勾。

### 首轮验证清单（出口规则修复前）

| 检查 | 本轮结果 |
|---|---|
| API `make check` | guard/pint 通过；458 tests / 4626 assertions，0 skip |
| API `make test-mysql` | 隔离 MySQL 8.4.8；19/33 Feature 文件，159 tests / 692 assertions；不能等同现网 MariaDB 验收 |
| agent `make check` | guard / go vet / 全量 go test / 路由写入门全部通过 |
| agent 独立 L2 gate | 2 条失败，exit=1；临时诊断文件已自动清理 |
| ctl `make check` | guard/pint 通过；54 tests / 426 assertions，其中 1 skipped：首页测试缺 public/build/manifest.json，不计前端验收 |
| 差异与清理 | 三仓 diff --check 无错误；脚本语法通过；MySQL 临时实例由脚本退出清理 |

未访问生产、未改系统防火墙/路由、未提交或推送。当前执行范围的验收结论是“发现并修复控制面三处缺口，数据面门禁阻断”，不是出口功能已可发布。

## 9. 出口 NAT/ACL 专项修复（2026-10-08）

用户授权执行出口规则优化；本次只改 agent 执行层与相关验证/状态文档。

### 最终行为

- 规则名为 `mynet_exit_<接口名SHA256前12hex>`，归属本节点隧道接口；不删除系统共享表、不 flush 全局 FORWARD。
- nft 使用 inet 专表（独立 forward/NAT hook），白名单支持 IPv4/IPv6；整张专表替换及已识别旧规则 handle 删除同属一份 `nft -f -` batch。失败时内核保持上一代；重复应用不会累积规则。
- iptables 使用独立 filter/mangle/nat 链；在 mangle FORWARD 给隧道入包打出口标记，NAT 按标记匹配，避免旧版 POSTROUTING `-i` 无效参数。标记保留低 8 位，使用 `0x4dxxxx00/0xffffff00` 保留段；与已知其它 MARK 冲突时拒绝应用。只支持 IPv4，IPv6 ACL 不静默忽略，会明确拒绝。
- iptables 先装 ACL 再装 NAT；撤销时反向先撤 NAT 再撤 ACL。各表用 `iptables-restore --noflush` 原子替换，失败回退已完成表内本出口规则，回退失败也报错。
- allowlist 的可执行地址为空时默认 DROP；关键读取/写入/清理失败不再 optional。仅有节点 ID 的拒绝名单无法转换成包过滤时明确拒绝，避免冒充已生效。
- `ApplyPlan.ProxyExitDevice` 保留接口名，撤销后即使没有路由/Proxy 计划也能清理；Local client 不被出口撤销清掉。出口专表在其它 apply 操作成功后提交。
- 历史共享链迁移需存在已管理出口的 Cloud 记录，只识别旧生成器在同接口产生的完整无注释规则形状。额外条件、注释、其它接口规则保留。旧无标记规则与人工创建的完全相同规则无法区分，这是历史格式的归属限制；新规则不再有此歧义。
- 不复原全局 `ip_forward`：它可能同时服务 LAN/其它隧道。也不清空 conntrack；本修复保证规则撤销，不声称已经证明所有现存连接终止。

### 验证与产物

- `sh scripts/check-proxy-permission-gate.sh`：7 个 `TestProxyExit*` 通过，已并入默认 Go 测试；原先失败的临时诊断换为正式行为测试。
- `MYNET_KERNEL_PLATFORM=linux/amd64 sh scripts/check-proxy-exit-kernel.sh`：隔离 Docker 网络空间，Linux `7.0.14-orbstack-00380-ga7e0a2dc9535`、nft `1.0.9`、iptables `1.8.10 (nf_tables)`；nft/iptables 两子用例 PASS。本机内核 aarch64，复用缓存 amd64 Alpine 用户态工具，不是交付架构认证。
- 内核验收覆盖旧规则迁移、新规则替换、重复应用、撤销、保留哨兵规则，以及真实 nft 非法 batch 原子性/iptables 中途失败回退；没有宿主/生产防火墙写入。
- 新实现：agent `mynet/proxy_exit_firewall.go`；默认测试：`mynet/proxy_exit_firewall_test.go`。独立内核测试源码存于 testdata（显式 build tag），脚本在临时容器运行后自动删除生成测试文件与编译目录。不会把本地单测的 skip 当成内核证明。

仍未完成：E/O/C/U 实际流量矩阵、网关通知、离线 fail-closed、旧 generation、并发 apply 及 iptables-legacy/OpenWrt 版本兼容实测。出口规则专项通过不等于 L2 全部通过，也不勾 M4/M6。

本次 agent 本地提交：`3e3d464`（`fix(proxy): isolate exit NAT and ACL with scoped cleanup and rollback`）；pre-commit guard 与最终 `make check` 通过。agent 工作树干净，未推送；API 与 ctl 的其它既有改动仍未提交。新独立行为门已并入默认测试，本专项无需重复授权即可按现有范围继续复核。

## 10. L1/L2 收尾（2026-10-08，续）

用户确认「按当前代码结构，主链已闭合」后继续补齐剩余门禁项：

| 项 | 落点 | 证据 |
|---|---|---|
| 跨租户 fail-closed | `ProxyExitController::resolveFriendNodes` 节点查询限 `tenant_id`；测试覆盖「友谊在其它 tenant」与「友谊在本 tenant 但节点在其它 tenant」 | `ProxyExitApiTest::test_跨租户好友与节点拒绝`；`test-mysql` 绿 |
| 双连接并发 PUT/DELETE | 出口节点行 `lockForUpdate` 串行化；连接 2 在持锁期间被挡，锁释放后终态合法（无半套白名单、许可面与 active 行一致） | `ProxyExitApiTest::test_双连接并发替换终态一致`（隔离 MySQL 1.12s PASS；sqlite 自动 skip） |
| 旧 generation 重放拒绝 | `domain.GenerationRevision` 解析 `rev-{YmdHis}-{nodeId}-{seq}`（此前 `ParseInt` 恒 0，重放被当成最新）；`ApplyCloud` 单调门禁：旧代拒 / 同代改写拒 / 同代同容幂等 | `TestGenerationRevision`、`TestApplyCloudRejectsStaleReplay`、`TestApplyCloudZeroRevisionStillAutoIncrements`；`go test ./...` 全绿 |

### 代码结构闭环判据（成立）

用户授权 → API 原子写入（事务+行锁）→ 发布新 bundle（checksum/generation）→ agent 校验并拒绝旧代 → 应用出口 NAT/ACL → 撤销时停用许可并清理规则。重复请求/重复 apply 不叠加；写入失败保留旧授权；出口规则应用失败回退上一代。

### 上线前完整验收（未完成，不据此宣布可发布）

| 项 | 说明 |
|---|---|
| E/O/C/U 流量矩阵 | 真实 Linux GNB 两端：白名单 C/O 允许、U 拒绝；开放与撤销分别实测 |
| 真实 gateway 通知与拉取 | 跨进程/跨机 WS 补拉、ETag、通知丢失窗口 |
| 出口离线 fail-closed | 出口宕机时消费端**不得**回落本地公网；若发生则闭环失败 |
| 现存连接终止 | 撤销后 conntrack 旧连接是否继续走出口（本次只保证规则撤销，不声称已断旧连接） |
| 并发 apply | 多 bundle 同时到达时 apply 互斥 |
| iptables-legacy / OpenWrt 兼容 | 当前只证 nft/iptables-legacy 在 OrbStack Alpine |
| 旧 generation 重放（实机） | 已有单元门禁；实机乱序推送仍待 |

M4/M6 仍不勾。API/ctl 改动未提交；agent 已有本地提交 `3e3d464`，本轮 generation 门禁改动在其后的工作树中。

### L3 隔离实机验收（2026-10-30，OrbStack 栈）

隔离栈：快照控制面（mynet_l3 MySQL + API + gateway `-internal-token`）+ 4 个 Debian arm64 特权容器（E 出口 10.0.0.10 / O 10.0.0.11 / C 10.0.0.16 / U 10.0.0.22，gnb 真隧道）。**6/7 通过，iptables-legacy 待裁决**；下列为实测证据：

| 项 | 结果 | 证据 |
|---|---|---|
| E/O/C/U 流量矩阵 | **通过** | 白名单{B}：O/C 经隧道→E→NAT 出网 0% 丢包（~90ms），U 100% 丢包；开放`[]`：三方全通；DELETE 撤销：三方全拒。撤销后 E 保留 `mynet_exit_*` 拒绝边界 |
| 真 gateway 通知与拉取 | **通过**（揪出集成 bug） | PUT 后 bundle 秒级自动更新、E 全自动落地链（bundle updated→refresh→reload→route）。WS 重连补拉已见。**修复**：API `Authorization: Bearer` vs gateway `X-Internal-Token` 恒 401（agent `092a694` 双认） |
| 出口离线 fail-closed | **通过** | 杀 E gnb 后 O 100% 丢包、`ip route get` 仍指 gnb_tun（fwmark 0xc8→table 200）无 eth0 回落；恢复即通 |
| 现存连接终止 | **通过**（强于承诺） | 对照实测：不撤销时旧连接第二请求得 `HTTP/1.1 301`；撤销后旧连接新请求 0 字节、新连接 fail——旧流往返被切断（合同只承诺规则撤销） |
| 并发 apply | **通过** | 5 发并发 bundle_changed：4 发「已有动作在跑，合并补跑」串行化、`bundle unchanged` 幂等；5 路并发 localapi 写：1×200 + 4×409 `operation_in_progress`；风暴后 nft 规则不堆积（mark 规则仍 1 条） |
| 旧 generation 重放（实机） | **通过** | 旧包（rev-…151800）覆盖重放：`stale cloud revision 2026103015180000001 < current 2026103020522900001，拒绝旧 generation 重放`；数据面 ACL 保持 N+1（4 accept 含 U 的 .22）无回退 |
| iptables-legacy / OpenWrt 兼容 | **待裁决** | legacy 只服务老 xtables 环境（CentOS7 代、fw3 固件、被切后端的主机）；现网目标（.180/.182/现代机）全为 nft 系。建议降级为按需验收（L3 容器 `update-alternatives` 切 legacy 可复验） |

过程修复（L3 揪出的真 bug，均已在 agent 提交）：`findGnbBin` 漏标准位 `<prefix>/bin`（`4b1701e`）；客户端分类器 nft 批 `flush chain` 先于 `add` 致首跑必败（`202c60b`）；gateway 运维口令不认 Bearer（`092a694`）。

过程记录的缺口（待入 CPI/台账）：节点 endpoint 更新不触发 bundle 重发（`BundleInvalidator` 无 endpoint 触发点，peers 拿旧 address.conf）；mynetd 先于 claim 启动则 transport 启动失败后无重试（永久死）；`PATCH /nodes/{id}/status` 收字符串被吞成 500（要整数）。

边界：快照控制面（冻结 API 副本）证据，框架升级（2.7→3.10）完成后控制面相关项需复验；IPv6、真固件 fw4/ufw/firewalld 发行版验收、通知丢失窗口的量化仍未做。M4/M6 仍不勾。

### L3 追加（2026-10-30 晚）：legacy 验收 + 3.10 控制面复验 + 三项修复

**① iptables-legacy 验收：通过**（双路径）。容器切 `iptables-legacy`（1.8.9）+ 摘 nft + ipset：消费侧 ipset+mangle 打标经隧道出网 0% 丢包；出口侧 iptables 独立 filter/mangle/nat 链（`0x4d…` 标记段 + 按标 MASQUERADE + 白名单 accept + 末尾 DROP）矩阵语义一致（{B,D} 时 O/U 通、改 {B} 后 U 100% 拒）。**途中揪出真 bug**：inverted 否定词 `! -m set` 被 legacy 拒（`a89563a` 改 `-m set ! --match-set`）。附带记录：iptables 回退路径无 flush 幂等（重复 apply 堆规则）；消费端 full_tunnel 下 default_iface=gnb_tun 使出口边界 WAN scope 判空（apply 部分失败，非 legacy 专属）。

**② 3.10 控制面复验**（框架升级后重快照）：登录（S2 显式租户头 X-Tenant-ID）、矩阵读写、发布链（config_revisions）、generation 门禁均过。**揪出阻断级集成缺口**：deferred job 缺 `TaskAuthEnvelope` 被框架 3.10 执行边界拒绝（identity_missing）→ 通知链全断（发布正常、afterResponse job 在 terminate 抛异常级联 fatal）；修法（job 挂 HasTaskAuthEnvelope + forService 信封 + provider trustService 登记）已在快照验证端到端恢复，已移交升级会话落正式树。另：auth_module 内嵌迁移（sso_auth_transactions/sso_sessions/operator_sso_accounts 三表 CREATE）升级后永不补跑，全新库 migrate 必炸（生产手工补过）——同移交。

**③ mynetd transport 重试修复（`73b24ec`）**：mynetd 先于 claim 启动则 transport 永久死。抽 tryNodeFace + retryNodeFace（15s 后台重试）；L3 实测：无身份启动告警 → 放入身份 15s 后「transport 已接入」+ WS 真连。另有 gateway 运维口令 Bearer 兼容（`092a694`）与 findGnbBin 标准位（`4b1701e`）、nft 首跑 flush 序（`202c60b`）共四个修复。

M4/M6 仍不勾；3.10 通知链修复落正式树后需再复验一轮自动通知。

**复测收口（2026-10-30 深夜）**：升级会话以 `1c89e71` 落库 TaskAuthEnvelope 适配（job 信封 + trustService + 2 行为测试）与 `database/sql/2026_10_30_sso_tables.sql` 执行件后，以正式树重刷 L3 快照复测：PUT proxy-exit → bundle 自动更新 → E 全自动落地链 ✓。**通知链 3.10 闭环**。取证更正：现网并无 SSO 三表（禁 migration 所以 ALTER 从不跑、未炸；非「手工补过」），运行面无 SSO 消费者不受影响；生产是否补三表待用户裁决。
