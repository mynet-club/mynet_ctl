# 项目记忆（2026-10-08 刷新）

## 持续有效的约定

- workspace：`/Users/arthur/Devel/WorkSpaceAI/mynet-club`。业务后端为 `mynet_api/`，旧目录 `mynet_back/` 已改名。
- `mynet_utc` 暂时搁置，不纳入当前实现、验证或交付范围（用户 2026-10-08 明示）。
- 现网库名仍为 `mynet_back`，引擎 MariaDB 10.11.18；源码更名不构成数据库更名授权。历史归档、备份和数据库引用保留原名。
- 知识库在 `mynet_ctl/docs/knowledge-base/`；v1 执行基线在 `mynet_agent/docs/MYNET_V1_PLAYBOOK.md`。冲突时按最新明确裁定与代码取证判定，不能按旧勾选推定完成。
- `mynet_gnb/` 是裸仓；工作树为 `.wt/gnb-actions`。上游源码只读，改动走补丁包。
- 实际用户前端为 `mynet_gui`；`mynet_web` 定位在旧文档中有冲突，不据其 README 发起合并。
- mynetd 纯通信、系统能力归执行器；gateway 不鉴权；凭证走 crt/crtvault，不读凭证库。

## 当前基线（本地提交取证，非本轮测试或线上验收）

| 项目 | HEAD | 最新事实 |
|---|---|---|
| mynet_agent | 0bb3a01 | apply 失败回滚已落码；wire ProxyPermission；P4 进度回写 |
| mynet_api | b38c36a | proxy_exit_grants 专表与用户出口授权 GET/PUT/DELETE；邀请码与脱敏补件 |
| mynet_ctl | 42f1f95 | P4 合同/OpenAPI 与 DBA 工单记录 |
| mynet_gui | 922f18e | Tenant 内部命名、网络区文案、孤儿页清理、类型棘轮与首批测试 |
| mynet_openwrt | f7c956e | 指纹绑定、路由唯一写入者、procd 启停；Proxy 仍有旧 Lua 路径 |
| mynet_desktop | 4ed21f2 | 三平台安装器已落码，真实安装与发布证据另验 |
| mynet_gnb | 8734260 | main 唯一发布通道，裸仓/工作树布局 |
| mynet_web | dc3ad08 | 近期主要守护更新 |

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

## 2026-10-08 L1/L2 收尾（代码结构闭环）

补齐跨租户 fail-closed（节点查询限 tenant）、双连接并发行锁验证（隔离 MySQL）、旧 generation 重放拒绝（`GenerationRevision` + `ApplyCloud` 单调门禁）。API 全量 460 tests（1 skip=sqlite 并发）；`test-mysql` 161 passed；agent `go test ./...` 全绿。**代码结构小闭环成立**（授权→发布→应用→撤销→失败回退有测试）；上线前实机项见 playbook §10，M4/M6 仍不勾。
