# mynet_agent — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Go |
| **角色** | 节点侧控制面：mynetd（通信）+ mynet（执行器）+ mynet_gateway + mynet-updater |
| **决策** | 新建 + 整理式搬运（源 `mynet_tui` / 旧 client 隐性知识） |

## 架构边界（硬约束）

```
mynetd/     纯通信层（常驻）：心跳组装 · WS + 身份密钥 · 取指令/结果上报 · 读 system.json · 命令分派
mynet/      执行器（偶发）：detector · metrics · apply · driver · service · commands
gateway/    边缘网关：WS（节点面）+ HTTPS（用户面）· 纯透传 · 不鉴权
shared/     共享：domain(BundleEnvelope) · config · logger · errors · utils
```

- `mynetd/` **不得**依赖系统能力（`make guard` B2 红线）
- `gateway` **不做鉴权**，鉴权归 back
- `relaypolicy` **只做路径白名单**

## WebUI 三态

| 标签 | 用途 |
|---|---|
| `-tags embed` | **发布**（唯一允许进包的） |
| `-tags dev` | 仅开发，读盘热更新 |
| 无标签 | OpenWrt / 瘦构建，WebUI 不进包 |

## 构建与守护

```bash
make install-hooks   # pre-commit 轻量 / pre-push 重量
make guard           # 安全/结构/产物红线
make check           # guard + lint + test
```

## 知识库

规则：`knowledge-base/AGENTS.md` · 设计：`mynet-overview.md` · 计划：`dev-plan.md`
