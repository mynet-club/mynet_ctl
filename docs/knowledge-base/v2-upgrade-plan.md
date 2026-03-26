# MyNet 生态 v2 升级总规划

> 本文档描述从 download.mynet.club → ctl.mynet.club 迁移，以及各子项目统一到 v2 API 的升级方案。
> 生成日期: 2026-03-20

---

## 一、升级背景

### 1.1 当前问题

| 问题 | 描述 |
|------|------|
| **download.mynet.club 已废弃** | 遗留静态文件分发站，已禁止目录访问，但多个客户端仍硬编码 |
| **API 版本不统一** | CTL 当前用 `/api/v1`，mynet_back 用 `/api/v1`，但客户端指向各不同 |
| **API URL 混乱** | mynet_client 用 `my.mynet.club/api/v1`，tui/lua 用 `api.mynet.club/api/v1` |
| **无 `client` 产品** | 旧产品名 `tui`/`mynet` 别名混乱，新 `client` 包尚未在分发链路中就绪 |
| **CI 未对接 CTL** | mynet_gnb 的 GitHub Actions 仍上传到 download.mynet.club |

### 1.2 升级目标

1. **CTL 接管所有软件分发** — `ctl.mynet.club/api/v2` 成为唯一 binary 来源
2. **统一 API URL** — `api.mynet.club/api/v2`（mynet_back）、`ctl.mynet.club/api/v2`（CTL）
3. **新增 `client` 产品** — 替代旧 `tui`，CTL 保留别名兼容
4. **CI 自动上传** — GNB/UTC 构建完成后自动 POST 到 CTL release API
5. **版本可追溯** — 所有 binary 通过 CTL API 管理，带 SHA256/changelog

---

## 二、API v2 变更总览

### 2.1 CTL（ctl.mynet.club）v1 → v2

| v1 端点 | v2 端点 | 变更说明 |
|---------|---------|---------|
| `GET /api/v1/apps` | `GET /api/v2/apps` | 响应增加 `schema_version: "2.0"` |
| `GET /api/v1/{product}/manifest` | `GET /api/v2/{product}/manifest` | 同上 |
| `GET /api/v1/releases` | `GET /api/v2/releases` | 新增 `client` 产品支持 |
| `GET /api/v1/releases/latest` | `GET /api/v2/releases/latest` | 无变化 |
| `POST /api/v1/releases` | `POST /api/v2/releases` | 新增 `client` 产品验证通过 |
| `GET /api/v1/detect/openwrt` | `GET /api/v2/detect/openwrt` | soft-float 回退覆盖更全 |
| `GET /api/v1/detect/openwrt.sh` | `GET /api/v2/detect/openwrt.sh` | 脚本内 URL 自动改为 v2 |
| `GET /api/v1/detect/arch-map` | `GET /api/v2/detect/arch-map` | 无变化 |
| `GET /api/v1/scripts/*` | `GET /api/v2/scripts/*` | 无变化 |
| `GET /api/v1/plugins/*` | `GET /api/v2/plugins/*` | 无变化 |
| `POST /api/v1/setup/*` | `POST /api/v2/setup/*` | 代理目标升级为 back v2 |

**v1 保留运行**（至少 6 个月），v2 为推荐版本。

### 2.2 mynet_back（api.mynet.club）v1 → v2

| v1 端点 | v2 端点 | 变更说明 |
|---------|---------|---------|
| `POST /api/v1/auth/login` | `POST /api/v2/auth/login` | 无变化 |
| `GET /api/v1/zones` | `GET /api/v2/zones` | 无变化 |
| `GET /api/v1/nodes` | `GET /api/v2/nodes` | 无变化 |
| `POST /api/v1/monitor/heartbeat` | `POST /api/v2/monitor/heartbeat` | 无变化 |
| 所有已有端点 | 同结构，前缀改 v2 | 业务逻辑不变 |

**策略**：v2 路由文件复制自 v1，仅改前缀，作为版本锚点。后续 breaking change 在 v2 上迭代。

---

## 三、Download URL 迁移映射

| 旧 URL | 新 URL | 说明 |
|--------|--------|------|
| `https://download.mynet.club/apps.json` | `https://ctl.mynet.club/api/v2/apps` | 产品索引 |
| `https://download.mynet.club/mynet/manifest.json` | `https://ctl.mynet.club/api/v2/mynet/manifest` | tui(mynet) 清单 |
| `https://download.mynet.club/gnb/manifest.json` | `https://ctl.mynet.club/api/v2/gnb/manifest` | GNB 清单 |
| `https://download.mynet.club/utc/manifest.json` | `https://ctl.mynet.club/api/v2/utc/manifest` | UTC 清单 |
| `https://download.mynet.club/gnb/v1.6.0.a/*` | `https://ctl.mynet.club/api/v2/releases/{id}/download/{file}` | 具体文件 |
| `https://download.mynet.club/mynet/plugins/mp/*` | `https://ctl.mynet.club/api/v2/plugins/{id}/download/{file}` | 插件 |

---

## 四、子项目升级优先级与顺序

### 4.1 依赖关系图

```
┌──────────────┐
│  mynet_back  │ ① 最先：提供 v2 路由（auth/zones/nodes）
└──────┬───────┘
       │ 认证 + 配置
       ▼
┌──────────────┐     ┌──────────────┐
│  mynet_ctl   │ ②   │  mynet_gnb   │ ③ CI：上传到 CTL
│  (本项目)    │◄────│  mynet_utc   │ ③ CI：上传到 CTL
└──────┬───────┘     └──────────────┘
       │ manifest + binary
       ▼
┌──────────────┐     ┌──────────────┐
│  mynet_tui   │ ④   │  mynet_lua   │ ⑤
│→ mynet_client│     │  (OpenWrt)   │
└──────┬───────┘     └──────────────┘
       │ 用户界面
       ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  mynet_gui   │ ⑥   │  mynet_web   │ ⑥   │  mynet_javis │ ⑦
└──────────────┘     └──────────────┘     └──────────────┘
```

### 4.2 升级阶段

| 阶段 | 项目 | 优先级 | 工作量 | 理由 |
|------|------|--------|--------|------|
| **① 基础层** | mynet_back | P0 | 小 | v2 路由是所有客户端升级的前提 |
| **② 分发层** | mynet_ctl | P0 | 已完成 | v2 路由注册 + apps/manifest 端点 |
| **③ 构建层** | mynet_gnb, mynet_utc | P1 | 中 | CI 改为上传到 CTL（非阻塞，可并行） |
| **④ 客户端** | mynet_tui → mynet_client | P1 | 大 | 改 config 默认 URL + updater 逻辑 |
| **⑤ OpenWrt** | mynet_lua | P1 | 中 | 改 APPS_INDEX + MANIFEST_URL |
| **⑥ 前端** | mynet_gui, mynet_web | P2 | 小 | 仅改链接 URL，无 API 调用 |
| **⑦ 独立应用** | mynet_javis | P2 | 小 | 改 MYNET_API_URL 指向 v2 |

### 4.3 回滚策略

- CTL v1 路由保留不删除，`.json` 后缀别名保留
- mynet_back v1 路由保留不删除
- 客户端发布新版本前，先确认 v2 端点可用
- download.mynet.club 可配一个 301 redirect 到 CTL（作为终极兜底）

---

## 五、CTL v2 路由实施（本项目）

### 5.1 新增 v2 路由组

在 `routes/api.php` 中新增 `v2` 前缀组，控制器复用 v1（逻辑完全相同）：

```php
// API v2 — 推荐版本
Route::prefix('v2')->group(function () {
    // 与 v1 完全相同的路由注册，复用同一批控制器
    // ...
});
```

### 5.2 `client` 产品正式启用

- `ReleaseDomainService::PRODUCTS` 已添加 `client` ✅
- `ReleaseAdminController` 已支持 `client` ✅
- ManifestService 需感知 `client` 产品

---

## 六、各项目升级文档

每个子项目会在自己的 `docs/` 目录下放一个 `UPGRADE-v2.md`，内容包含：

1. 当前状态概述
2. 需要修改的文件清单
3. 具体代码变更（精确到行）
4. 测试验证方法
5. 回滚方案

---

## 七、时间线建议

| 里程碑 | 内容 |
|--------|------|
| M1 | mynet_back v2 路由上线 + mynet_ctl v2 路由注册 |
| M2 | mynet_gnb/utc CI 对接 CTL API |
| M3 | mynet_client v2 发布（默认 CTL URL） |
| M4 | mynet_lua v2 发布 |
| M5 | mynet_gui/web 更新链接 |
| M6 | download.mynet.club 配置 301 redirect → ctl.mynet.club |
| M7 | 观察 v1 流量，若为零则标记 deprecated |
