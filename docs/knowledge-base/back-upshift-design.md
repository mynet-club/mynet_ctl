# back 业务上移设计（business upshift）

> 更新：2026-09-21 | 来源规划：`integration-plan-v2.md` §3 W4
> 状态：**设计稿**（P0 交付；P3 落地）
> 关联：`spec/driver-abstraction.md`（W2）、`api/references/alignment-backlog.md`（W3）

---

## 1. 一句话

「业务逻辑向上长」= **mynet_api 承载业务权威与策略计算，客户端变薄为执行器与上报者。**
同一业务逻辑不在多仓重复实现（不变量 I4）。

---

## 2. 为什么

当前生态的业务逻辑分散在多个客户端（旧 line：tui/client/lua 各写一份），后果正是 W3 清单里的那些偏差：

| 症状 | 根因 |
|------|------|
| 心跳格式两端不一致（M1/M5） | 客户端各自实现心跳 |
| 4 次独立 API 取配置（S1） | 客户端各自拼配置 |
| 默认配置写死 v1（B1） | 客户端各自硬编码 |
| proxy 规则多处实现 | 策略未统一 |

**收敛方向**：这些「多端必须一致」的逻辑，唯一实现在 back（或 agent 单份），其余只消费。

---

## 3. 状态归属矩阵

| 状态 | 权威位置 | 客户端角色 | 备注 |
|------|----------|------------|------|
| Zone / 节点 / 路由 / 密钥**定义** | **mynet_api** | 只读缓存 | config-bundle / VPNBundle 来源 |
| 节点**在线状态** | **mynet_api** | 上报（心跳） | back 由心跳合成，客户端不自判定 |
| **VPNBundle**（抽象配置） | **mynet_api** | 落盘执行 | 见 W2；back 不产 gnb 原生文本 |
| 代理**策略**（区域/分流规则） | **mynet_api**（计算） | 执行 | 现部分在客户端，应上移 |
| 版本/升级**策略**（灰度/最低版本） | **ctl**（manifest）+ back（策略） | 执行 | ctl 管分发，back 管「谁该升级」 |
| 用户偏好（当前 Zone/节点） | **mynet_api** | 本地缓存 | 断网时用缓存 |
| 进程运行态（pid / tun / 端口） | **设备（agent 本地）** | 持有 | back 不关心 pid |
| 系统集成（路由表/防火墙/TUN） | **设备（agent 本地）** | 持有 | 必须本地 |
| 日志 | **设备本地** + 摘要上报 | 持有 | 全文不上云 |

**分界线**：**「想连谁、走哪些网段、什么策略」在 back；「怎么连、进程活得怎样」在 agent。**

---

## 4. 上移 / 下沉判据

### 应上移到 back

- 多客户端必须一致的**策略计算**（代理规则、路由选择、IP 分配）
- 需要**全局视图**才能决定的（冲突检测、配额、灰度）
- 变更频繁但希望**统一**的（默认值、功能开关、心跳间隔）
- 涉及**跨 Zone / 跨用户**的规则

### 应下沉留设备

- **必须离线可用**的逻辑
- 涉及**本机系统调用**（`modprobe tun`、`ip route`、nftables）
- **延迟敏感**（进程重启、心跳响应、状态采样）
- **隐私敏感**且无需上云的

---

## 5. 与 W2（driver 抽象）的接口

back 上移后产出的配置不能是 gnb 原生文本，也不能是假装完全通用的扁平结构。

```
[错误] back ──"nodeid ...\nifname gnb_tun_178\n..."──▶ agent
[错误] back ──FlatBundle{routes→AllowedIPs, discovery→Endpoint}──▶ agent
[正确] back ──Envelope{network, gnb-intent | wireguard-intent}──▶ agent
              └─ agent 分别执行 driver adapter 与 OS NetworkReconciler
```

GNB 的 route/index/NAT 穿透/forward/relay/proxy 与 WireGuard 的
cryptokey routing/Endpoint roaming/keepalive 不同，必须用 driver-specific intent 表达；
公共层只保存业务节点、接口意图、网络状态和 generation。完整契约见
`spec/driver-abstraction.md` §3。

---

## 6. 反模式（明确禁止）

| 反模式 | 后果 | 对应修复 |
|--------|------|----------|
| 每个客户端各写一份策略 | 漂移（M1/M5/B1 就是这么来的） | 本设计 §3/§4 |
| back 下发 gnb 原生格式 | 驱动耦合 | W2 |
| 客户端降为「哑转发」 | 断网即废 | §7 离线边界 |
| 把延迟敏感逻辑也上移 | 隧道抖动、重启变慢 | §4 下沉判据 |

---

## 7. 离线降级边界（关键）

「上移」不等于「断网不可用」。断网时：

1. agent 用**最后一次** `VPNBundle` 维持隧道（不重新拉取）
2. 策略计算**暂用缓存**，不回退到本地硬编码
3. 心跳失败**重试 + 指数退避**，不因 back 不可达而停隧道
4. 恢复后 back 的 bundle **覆盖**本地缓存（back 为准）

> 原则：**控制面可降级，数据面不中断。**

---

## 8. 落地清单（P3 起）

| # | 任务 | Owner | 依赖 |
|---|------|-------|------|
| U1 | 盘点当前在客户端实现的策略点（proxy 规则、route 生成、默认配置） | agent / openwrt | — |
| U2 | 逐个标注「上移 / 下沉」，产出决策表 | 本仓 docs | U1 |
| U3 | back 增策略端点（代理规则、默认值下发、版本策略） | mynet_api | U2 |
| U4 | 客户端删重复实现，改为消费 back 下发 | mynet_agent | U3 |
| U5 | 明确离线降级路径并测试（拔网线场景） | mynet_agent | U4 |

---

## 9. 非目标

- 不把系统调用（进程/路由/防火墙）上移
- 不做「客户端零逻辑」——执行、上报、离线自愈必须在端侧
- 不在本阶段改动现有心跳协议（B2/B3 走 W3 清单）
