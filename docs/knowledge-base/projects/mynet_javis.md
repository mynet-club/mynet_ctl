# mynet_javis — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Laravel 12 / PHP 8.2 / OpenResty (Nginx+Lua) / Redis |
| **域名** | myjarvis.ai / *.myjarvis.ai |
| **角色** | 基于 GNB P2P 穿透的个人 AI 反向代理（独立产品） |

## 与 MyNet 的关系
- **独立产品**，与 mynet 组网服务弱耦合
- 复用 mynet_back 账号体系（Sanctum Token）
- 复用 mynet_client 管理 GNB 节点
- 使用 GNB 底层穿透能力，不依赖 mynet 组网服务

## 核心职责
- 子域名管理（*.myjarvis.ai 动态路由）
- GNB 隧道穿透（用户 PC ← P2P → Javis 入口节点）
- 质量评分（延迟40% + 可用性40% + 带宽20%）
- 套餐管理（托管版 ¥39/年 / 自托管版 免费）
- 实时路由（Lua shared dict → Redis → Laravel API）

## 访问链路
```
浏览器 → arthur.myjarvis.ai
  → OpenResty (subdomain_router.lua)
  → GNB P2P 隧道
  → 用户 PC 上的 OpenClaw (本地 AI :3000)
```

## OpenResty Lua 脚本
```
subdomain_router.lua   子域名 → IP:Port 路由
quality_gate.lua       质量门限（<40 分 → 503）
rate_limiter.lua       限流（10/sec per subdomain, 2/sec per IP）
```

## 数据库
- `subdomains` — 用户子域名
- `proxy_targets` — 节点实时状态
- `quality_logs` — 质量时序（30 天）
- `plans` / `user_plans` — 套餐与订阅
- 共享 `mynet` DB（只读）: users, nodes, node_keys
