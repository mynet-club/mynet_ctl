# 归档 — 2026-09 过程记录

> 这些是**单次执行的真实记录**，不再更新。要看结论证据请进原文；
> 要知道「现在该做什么」去 `../../long-range-plan-deep.md` / `../../dev-plan.md`。

| 文件 | 记录 |
|------|------|
| [audit-2026-05.md](audit-2026-05.md) | 2026-05 全站审计（客户端/lua 已退役，文中名称保留历史） |
| [prod-cutover-20260926.md](prod-cutover-20260926.md) | 正式发布切换计划（api.mynet.club 晋升） |
| [production-gates-handoff.md](production-gates-handoff.md) | 生产闸门交接包（H5 / H6 / V2-8b） |
| [overnight-u1-plan.md](overnight-u1-plan.md) · [overnight-u1-report.md](overnight-u1-report.md) | 过夜计划与报告 U1 |
| [core-remediation-plan-20260930.md](core-remediation-plan-20260930.md) | 核心问题修复执行计划（四条主线） |
| [five-node-milestone-20260930.md](five-node-milestone-20260930.md) | 五节点全平台联调里程碑 |
| [proxy-conf-cutover-20260930.md](proxy-conf-cutover-20260930.md) | proxy.conf 新管理方案切换 + 0.2↔188 排障实录 |
| [reinstall-181-report-20260930.md](reinstall-181-report-20260930.md) | 181 重装实验报告 |
| [evidence/20260930-subject/](evidence/20260930-subject/) | 五机主体混乱取证 |

**仍被现行文档引用的结论**（引用请写 `archive/2026-09/...` 路径）：

- `troubleshoot-connectivity.md` ← 五节点实测
- `spec/paths.md` · `spec/route-firewall-split.md` ← 主线②/③ 病灶
- `node-connectivity-checklist.md` ← overnight-u1 联动
