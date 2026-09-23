# MyNet 长程计划 R0–R5

> 前置：`dev-plan.md` 64 项 **S0–S9 已全勾**（M0–M4 闭环）。
> 本文是**下一程**的唯一跟踪入口。按序号推进，做完勾「状态」。
> 方法与 S 段相同：整理式搬运、测试才算完成、证据带 file:line。

---

## 0. 距离「可上线」还差什么

| 维度 | S 段后状态 | 缺口 |
|---|---|---|
| 架构/契约 | L1–L7 自洽，A-8 六判据绿 | 端到端未在真机串起来 |
| 测试 | unit/Feature 绿 | **无跨进程/跨仓集成** |
| 产品面 | 本机 UI 可用壳 | setup/install/update 子命令仍是 stub |
| 平台面 | ipk/ubus/procd 样例齐 | **未在 OpenWrt 实机验证** |
| 分发 | 产物命名 SSOT | **无一键构建/发布流水线** |
| 规模 | 单节点互斥切换 | 多节点并行、presence 多实例未压测 |
| 业务闭环 | 领域服务齐 | 好友→连接→授权→Bundle→Apply **未串通** |

---

## 1. 阶段总览

```
R1 端到端闭环 ──────────────► 双节点 gnb+wg 真通
 │
 ├─ R2 执行器补全 ──────────► mynet 全子命令面可用
 │
 ├─ R3 平台实机 ────────────► OpenWrt/desktop 装得上、跑得稳
 │
 ├─ R4 分发与发布 ──────────► 一键 build → ctl 索引 → 在线升级
 │
 └─ R5 规模与加固 ──────────► 多节点并行 · Redis 多实例 · 安全审计
```

**硬门槛**：**G1**（R1 出口：双节点互通）· **G2**（R4 出口：可分发可升级）。

---

## 2. 计划顺序表

| # | 阶段 | ID | 内容 | 前置 | 状态 |
|---|---|---|---|---|---|
| 1 | **R1 闭环** | R1-1 | 集成脚本 `scripts/e2e.sh`：起 back(testbench/sqlite) + gateway + mynetd ×2 | — | ☑ |
| 2 | | R1-2 | 节点注册 → 密钥登记（KeyExchange）→ 好友/连接/授权 链 | R1-1 | ☑ |
| 3 | | R1-3 | BundleEnvelope 下发 → 节点 Apply → `ip route` 可见（可 dry-run） | R1-2 | ☑ |
| 4 | | R1-4 | 心跳 DH+HMAC 真签真验（Go↔PHP 联测用例） | R1-1 | ☑ |
| 5 | | R1-5 | WS 命令下发 → mynet 执行 → 结果回传 | R1-1 | ☑ |
| 6 | | R1-6 | **G1**：gnb 场景 + wg 场景 + 跨驱动 三场景 e2e 绿 | R1-2..5 | ☑ |
| 7 | **R2 执行器** | R2-1 | `mynet setup`：生成节点密钥 + 拉 `back_pub` + 写 `mynet.conf` | R1-4 | ☑ |
| 8 | | R2-2 | `mynet status` / `check` / `nodes` / `platform` 只读实现 | — | ☑ |
| 9 | | R2-3 | `mynet refresh-{routes,address,config,keys}` 对接 Bundle | R1-3 | ☑ |
| 10 | | R2-4 | `mynet install/uninstall`（桌面 systemd/launchd + OpenWrt procd） | R3-1 | ☑ |
| 11 | | R2-5 | `mynet update` 对接 ctl apps 索引（E-9） | R4-1 | ☑ |
| 12 | **R3 平台** | R3-1 | OpenWrt 实机：ipk 装载 + mynetd procd + LuCI | — | ☑ |
| 13 | | R3-2 | `verify_gnb_es_orphan.sh --crash` PASS → 切 procd（**已切换 + respawn 实测**） | R3-1 | ☑ |
| 14 | | R3-3 | `_gnb_peer_heal` 新判据实机 24h 无抖动 | R3-2 | ☐ |
| 15 | | R3-4 | Lua 真收窄：结果回 mynetd，去掉自报心跳 | R3-1 | ☑ |
| 16 | | R3-5 | desktop：macOS launchd / Linux systemd 装载冒烟 | — | ☑ |
| 17 | **R4 分发** | R4-1 | `make dist` 三平台产物（`mynet_agent_*`） | R2-2 | ☑ |
| 18 | | R4-2 | ctl `apps.json` 索引生成 + 版本单调 | R4-1 | ☑ |
| 19 | | R4-3 | `mynet update` 升级一轮：旧版→新版→健康检查 | R4-2, R2-5 | ☑ |
| 20 | | R4-4 | **G2**：冷装 → 业务可用 → 在线升级 全流程可重复 | R3-5, R4-3 | ☑ |
| 21 | **R5 规模** | R5-1 | 多节点并行（放宽互斥，`nodes/switch` → 并行会话） | G1 | ☑ |
| 22 | | R5-2 | Redis presence 双 gateway 实例推送正确 | R1-5 | ☑ |
| 23 | | R5-3 | proxy（gnb 原生）业务意图 → Envelope → driver | G1 | ☑ |
| 24 | | R5-4 | 节点服务（web）：Service 建模 → 访问控制 → 可达 | G1 | ☑ |
| 25 | | R5-5 | 压测：1k 节点心跳 / 命令延迟 / 内存 | R5-1 | ☐ |
| 26 | | R5-6 | 安全审计复检（私钥出口 / DNS rebinding / 重放 / 越权） | G2 | ☑ |
| 27 | | R5-7 | Windows/darwin 打包与服务（E-7 补全） | R2-4 | ☑ |

**里程碑**：G1 = #6 · G2 = #20 · 全表完 = 可小范围公测。

---

## 3. 各段出口判据

### G1（R1）双节点互通
- 脚本一键起本地拓扑（可 mock 驱动）
- 注册/密钥/连接/授权/Bundle/Apply/心跳/命令 全链有断言
- gnb / wg / 跨驱动 三场景各至少 1 条 e2e
- Go↔PHP 签名互认（同一向量）

### G2（R4）可分发可升级
- 三平台产物命名 `mynet_agent_*`
- ctl 索引可拉、校验和可核
- 升级后 `mynetd` 自愈、节点仍在线

### R5 完成态
- ≥2 节点并行、≥2 gateway 实例
- 业务 ⑦ proxy / ⑧ 服务 可演示
- 1k 心跳无雪崩；安全复检 0 高危

---

## 4. 与九条主线对照

| 主线 | 计划落点 |
|---|---|
| ① P2P UDP | R1-3/R1-6（Apply 后真通） |
| ② gnb/wg 双实现 | R1-6 · R5-3 |
| ③ 不破坏协议/目录 | 全程约束（guard B1） |
| ④ back 社区链接 | R1-2 · R5-4 |
| ⑤ agent 自动化 | R2 全段 · R4-3 |
| ⑥ 本地 UI → gateway → back | R1-5 · R2-1 |
| ⑦ proxy | R5-3 |
| ⑧ 节点服务 | R5-4 |
| ⑨ 多平台 | R3 · R5-7 |

---

## 5. 排序原则

1. **先串通再加宽**：R1 不通，后面都是空转  
2. **实机优先于重写**：R3 用既有样例，不重造  
3. **可重复 > 一次成功**：e2e / 升级必须脚本化  
4. **业务对象仍不泄驱动**（guard B4 长期有效）

---

## 6. 风险与前置

| 风险 | 处置 |
|---|---|
| 无 OpenWrt 实机 | R3 阻塞时先交付 R2+R4 脚本，实机项挂起 |
| dsplat / GitHub 网络 | 依赖 goproxy.cn / 镜像；大二进制不入库（gnb 已滤） |
| 多节点语义未定 | R5-1 先「进程级并行、UI 单焦点」，避免产品语义争议 |
| Feature 路由装配依赖 | e2e 用真实 `bootstrap/app.php`，不走 Testbench |
