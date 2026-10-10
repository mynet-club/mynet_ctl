# 项目记忆（2026-10-10 刷新，上一次 10-08）

## 持续有效的约定

- workspace：`/Users/arthur/Devel/WorkSpaceAI/mynet-club`。业务后端为 `mynet_api/`，旧目录 `mynet_back/` 已改名。
- `mynet_utc` 暂时搁置，不纳入当前实现、验证或交付范围（用户 2026-10-08 明示）。
- 现网库名仍为 `mynet_back`，引擎 MariaDB 10.11.18；源码更名不构成数据库更名授权。历史归档、备份和数据库引用保留原名。
- 知识库在 `mynet_ctl/docs/knowledge-base/`；v1 执行基线在 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md`。冲突时按最新明确裁定与代码取证判定，不能按旧勾选推定完成。
- `mynet_gnb/` 是裸仓；工作树为 `.wt/gnb-actions`。上游源码只读，改动走补丁包。
- 实际用户前端为 `mynet_gui`；`mynet_web` 定位在旧文档中有冲突，不据其 README 发起合并。
- mynetd 纯通信、系统能力归执行器；gateway 不鉴权；凭证走 crt/crtvault，不读凭证库。

## 当前基线（本地提交取证，非本轮测试或线上验收）

**2026-10-10 刷新（与 HEAD 对齐）**：

| 项目 | HEAD | 未推送 | 最新事实 |
|---|---|---|---|
| mynet_agent | db398d2 | 0 | 出口 NAT/ACL 隔离+作用域清理与回滚、旧 generation 重放门禁、一键接入设备、升级按 OS/架构选包、绑定 v0.1.2 内置 GUI |
| mynet_api | 76e1214 | 0 | 出口授权写面原子化 + 跨租户/并发/合同门禁；首次创建设备自动分配地址池与默认配置 |
| mynet_ctl | 8fc5e6e | 0 | P4 出口授权小闭环合同锁定与验收记录、localapi 简化接入合同、v0.1.1 体验发布记录 |
| mynet_gui | 26d8562 | 0 | 简化设备与连接流程、生产 API 代理回环与 WS 域名修正、首建/首载引导区分 |
| mynet_desktop | 4ed21f2 | 0 | 三平台安装器已落码，真实安装与发布证据另验 |
| mynet_openwrt | f7c956e | **10** | 指纹绑定、路由唯一写入者、procd 启停；Proxy 仍有旧 Lua 路径 |
| mynet_gnb | 8734260 | **2** | 裸仓 + 工作树 `.wt/gnb-actions`；两条未推送全是 guard 模板同步 |
| mynet_web | dc3ad08 | — | 近期主要守护更新 |

上表各仓无脏改动、无 stash（`mynet_gnb` 是**裸仓**，其工作树为 `.wt/gnb-actions`，同样干净）。**除 openwrt（10 条）与 gnb（2 条）外全部已推送**；这两条线的分叉点是 `de765cf`（09-30）与 `3991970`（10-04），自 10-04 起未再动。

**本机测试（2026-10-10）**：api `make test`（=`--testsuite Default`）**471 tests / 4690
assertions / 1 skip**（skip = sqlite 并发，需 MySQL 双连接）、`make test-mysql` **172 passed /
765 assertions**；agent `go build ./...` + `go test ./...` 19 包全绿；ctl 54 tests / 426 assertions /
1 skip；gui vitest 20 passed。
⚠️ 裸跑 `vendor/bin/phpunit`（不带 `--testsuite`）只出 **454** —— `phpunit.xml` 套件定义有重叠
目录、默认套件才是 `make test` 用的那个，**计数一律以 `make test` 为准**。

P3 已裁定收口：自家节点自动组网、不限制；不建 planner、不加容量闸门；重复护栏保持代码判断，不加唯一索引。
P4/M4 未收口：OpenWrt 未接统一 Proxy 合同、回程缺口、离线与错误出口证据不足、console 公共出口线未开。
GUI 的 20 测试通过与类型基线 202 错误/39 文件来自既有实施记录，不是“类型零错误”。

## 下一次开工

先读 [小闭环 playbook](spec/proxy-permission-loop-playbook.md)。2026-10-08 **L0–L2 代码结构闭环已闭合**（用户确认主链成立）；下一步是 §10 上线前实机验收（E/O/C/U 流量、gateway 通知、出口离线 fail-closed、现存连接终止、并发 apply），不是再改控制面。
特别核对：DELETE 后 `resolvePermission()` 为 null，`resolveForExit()` 却返回 open；不能把 ACL 默认开放当成出口已启用。
状态刷新时记录 HEAD、未提交变更和实测命令；已完成项必须有行为证据。

## 2026-10-08 执行验证刷新

API 全量 458 tests / 4626 assertions；隔离 MySQL 8.4.8 的组 159 tests / 692 assertions（19/33 Feature 文件）；agent make check 通过。ctl 54 tests 中 1 个首页测试因未构建 manifest 跳过。全部是本地验证，未动生产；改动未提交。
L2 独立 gate 两项失败：出口 NAT/ACL 无清理、兜底 DROP 被当 optional。先修这条规则归属/替换/回滚链，再做隔离 Linux L3；不勾 M4/M6。

## 出口规则专项更新（2026-10-08，后续优化）

出口 NAT/ACL 两个阻断已落码修复：接口独立 nft 专表/iptables 链、替换/幂等/撤销、故障回退、精确历史规则迁移。独立门禁7例通过，Docker 隔离 Linux 中 nft1.0.9 / iptables1.8.10(nf_tables) 内核测试通过；不再沿用“两项仍失败”的旧状态。当前规则集撤销已验，不代表 conntrack 旧连接、出口离线或 API/gateway 真实流量矩阵已验。完整 L2/L3 与 M4/M6 仍未达成。具体以 playbook §9 为准。

agent 当前 HEAD 已更新为 `3e3d464`（出口 NAT/ACL 专项与前轮 Cloud 撤销修复一起保存为本地提交），工作树干净，未推送。最终 agent make check 与 pre-commit guard 通过；API/ctl 其它变更仍未提交。
（2026-10-10：该状态已被取代 —— `3e3d464` 及其后续提交均已推送，四仓 0 未推送；见上方「当前基线」表与文末同日段。）

## 2026-10-08 L1/L2 收尾（代码结构闭环）

补齐跨租户 fail-closed（节点查询限 tenant）、双连接并发行锁验证（隔离 MySQL）、旧 generation 重放拒绝（`GenerationRevision` + `ApplyCloud` 单调门禁）。API 全量 460 tests（1 skip=sqlite 并发）；`test-mysql` 161 passed；agent `go test ./...` 全绿。**代码结构小闭环成立**（授权→发布→应用→撤销→失败回退有测试）；上线前实机项见 playbook §10，M4/M6 仍不勾。

## 2026-10-10 与 HEAD 对齐（本次刷新）

- **三份文档都曾落后于代码**：上位基线 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md`（10-06）、本文件（10-08）、
  `dev-plan.md` §3（10-05）。本次按 10-09 HEAD 对齐，各文件的落地记录见其新增段。
- **10-07→10-09 落地面**：出口授权小闭环 L0–L2 代码结构闭合（agent `3e3d464`/`9f20e45`、
  api `5eb08d0`、ctl `1208561`）；出口 NAT/ACL 由「无清理」改为接口独立 nft 专表/iptables 链 +
  替换/幂等/撤销/故障回退；旧 generation 重放门禁（`GenerationRevision` 解析 + `ApplyCloud` 单调）；
  首次创建设备自动分配地址池与默认配置（api `76e1214`）；GUI 简化设备与连接流程 + 生产 API 代理
  回环与 WS 域名修正 + 首建/首载引导区分（gui `3a1b53d`/`5896fe4`/`26d8562`）；agent 一键接入设备
  与 v0.1.1/v0.1.2 发布绑定（`2212e47`/`48b84f6`/`db398d2`）。
- **`dev-plan.md` §3 两处状态列依据已过期，不按旧记号读**（不据此翻勾，待实测）：
  - **#30 C-6**「撤勾」的依据是「死代码 + gateway 二进制不存在」⇒ 实测已不成立：`cmd/mynet_gateway/main.go`
    存在、`NewRedisPresence` 被 `:90` 真接线、有跨实例测试 `gateway/cross_instance_test.go`。
  - **#16 F-4**「Windows 是纸面」⇒ 已失效：windows 平台文件齐全，且 `mynet_agent/Makefile:76` 的
    `DIST_MATRIX` 含 `windows:amd64`。仍保留「部分」——实现存在 ≠ 真机跑过。
  - 另：**#75 状态列 ◐ → ☑**（该格正文早已写「P0 两件全部收口 ✅」，标记漏跟）。
- **仍未收口（不变）**：M4/M6 不勾；OpenWrt 未接统一 Proxy 合同、回程缺口、离线与错误出口证据不足、
  console 公共出口线未开；**上线前七项实机验收**（E/O/C/U 流量矩阵、真 gateway 通知与拉取、出口离线
  fail-closed、撤销后 conntrack 旧连接、并发 apply、iptables-legacy/OpenWrt 兼容、实机乱序重放）
  见 `spec/proxy-permission-loop-playbook.md` §10。
- **口令**：读状态前先核 HEAD —— 本文件与 §3 的勾选都可能滞后于代码，冲突时按最新裁定与代码取证判定。
