# 不匹配与 BUG 清单

> Source of Truth: mynet_ctl/docs/api/references/issues.md
> 最后审计：2026-04-24（新增三方对账 P4 结果，详见 `AUDIT_2026-04-24/`）

---

## 🔴 BUG（必须修复）

| # | 项目 | 问题 | 位置 | 影响 |
|---|------|------|------|------|
| B1 | **mynet_lua** | `validator.lua` auto_repair 创建默认配置时写死 `/api/v1`，应为 `/api/v2` | `validator.lua:163,172` | 新用户默认配置指向 v1 API |
| B2 | **mynet_back** | config-bundle 缺少严格 `zone_id` 鉴权（用户可越 Zone 访问） | `NodeConfigController` | 安全漏洞 |
| B3 | **mynet_back** | router-keys 获取失败时静默继续，配置包缺少 peer 公钥 | `ConfigBundleService` | 节点间无法连接 |

---

## 🟠 不匹配（需要对齐）

| # | 项目 | 问题 | 详情 |
|---|------|------|------|
| M1 | **mynet_lua** | router-keys 发送空 body `{}` | back 期望 `{ "router_node_ids": [int] }`。lua 在 bundle fallback 时依赖 back 自动推导 |
| M2 | **client** | keys/upload 发送 `custom_public_key` | v2 合并为 `/keys`，字段名为 `custom_public_key`。需确认 v2 是否仍接受此字段 |
| M3 | **GUI** | 批量设备信息用 `PUT /nodes/deviceinfo` | v2 改为 `POST`。GUI 需更新为 POST |
| M4 | **GUI** | keys/generate 和 keys/upload 分开调用 | v2 合并为 `POST /nodes/{id}/keys`。GUI 仍使用 v1 路径 |
| M5 | **client** | router-keys 响应期望 `{peerId: "hex"}` 扁平格式 | back 返回 `{ keys: [{ router_node_id, public_key }], failed: [...] }`。client 需做格式转换 |

---

## 🟡 建议改进

| # | 项目 | 问题 | 建议 |
|---|------|------|------|
| S1 | mynet_lua | 未使用 config-bundle，每次同步 4 次 API | 升级到 config-bundle（已有 fallback 代码） |
| S2 | mynet_lua | 无心跳上报 | 实现 heartbeat 插件（已有 HMAC 签名代码） |
| S3 | mynet_client | 缺少 zone switch-zone 调用 | 检查是否真的不需要 |
| S4 | 全端 | 缺少 API 版本协商 | 参考 `api-versions.json`，客户端启动时检查 |

---

## 修复优先级

1. **B2** (安全漏洞) — config-bundle zone_id 鉴权
2. **B3** — router-keys 静默失败
3. **B1** — lua v1 硬编码
4. **M3, M4** — GUI v1→v2 迁移
5. **M1, M2, M5** — 字段对齐
6. **S1~S4** — 功能改进

---

## 📋 2026-04-24 三方对账新增问题

> 数据：`docs/api/AUDIT_2026-04-24/04_diff_matrix.md`
> GUI 调用 128 端点，三方一致 40，BLOCKER 45（已修核心 8 个），DOC GAP 43，后端孤儿 69。

### 🔴 已修复（GUI v1→v2 命名迁移）

| # | 项目 | 修复内容 | 文件 |
|---|------|------|------|
| F1 | GUI | `GET /zones/services` → `/zones/services/my` | services/zone/zoneServiceApi*.ts |
| F2 | GUI | `GET /zones/services/{id}` → `/zones/services/show/{id}` | services/zone/zoneServiceApi*.ts |
| F3 | GUI | `POST /zones/services` → `/zones/services/create` | services/zone/zoneServiceApi*.ts |
| F4 | GUI | `POST /zones/services/{id}/share` → `/zones/services/share/{id}` | services/zone/zoneServiceApi*.ts |
| F5 | GUI | `POST /zones/services/{id}/unshare` → `DELETE /zones/services/share/{id}` | services/zone/zoneServiceApi*.ts |
| F6 | GUI | `POST /zones/services/{id}/request-approval` → `/zones/services/request-approval/{id}` | services/zone/zoneServiceApi*.ts |
| F7 | GUI | `POST /zones/services/{id}/approve` → 复用 request-approval（v2 暂未实现独立 approve） | services/zone/zoneServiceApi*.ts |
| F8 | GUI | `GET /zones/services/indexeservices` (typo) → `/zones/services/indexes` | services/zone/zoneService.ts |
| F9 | GUI | `GET /monitor/history/connections` (v2 已移除) → `GET /nodes/{id}/peer-connections` | pages/route/ActiveRoutesPage.vue |
| F10 | GUI | `POST /nodes` → `POST /nodes/create` | services/api.ts:492 |

### 🔴 BLOCKER 待后端实现（GUI 在调，后端无路由）

| # | 项目 | 端点 | 调用页面 |
|---|------|------|------|
| N1 | back | `GET /zones/overview` | composables/useZones.ts:50 |
| N2 | back | `GET /zones/{id}/statistics` | composables/useZones.ts:212（ZoneListPage 切换 Zone 时调用） |
| N3 | back | `GET /users/{id}/ip-resources` | composables/useIPPools.ts:359（用户 IP 资源页） |

### 🟠 GUI dead code（前端有调用但功能未上线，建议清理或标记 TODO）

| # | 项目 | 端点群 | 范围 |
|---|------|------|------|
| D1 | GUI | `/nodes/{id}/config/{apply-template,compare,download,export,history,import,preview,reset,restore}` (9个) | services/node/nodeConfigService.ts — 配置高级管理功能未上线 |
| D2 | GUI | `/ippools/{batch,export,extend,allocate,allocations,check-ip,release}` (7个) | services/ippool/ipPoolService.ts、composables/useIPPools.ts — IP 池高级操作未上线 |
| D3 | GUI | `/route/{active,connect/{id},connections/{id},friend-nodes,{id}/stats}` (5个) | services/route/routesService.ts — 路由模块多个端点未实现 |
| D4 | GUI | `/routes` (复数) CRUD (4个) | services/api.ts dead methods（无调用方） |
| D5 | GUI | `/nodes/{id}/keys/{id}` DELETE/PATCH status | services/node/nodeKeysService.ts — 密钥单独管理未实现 |
| D6 | GUI | `PUT /ippools/{id}` | composables/useIPPools.ts:176 — 后端只支持 store/delete |
| D7 | GUI | `GET /i18n/{id}` | services/api.ts:528 — 语言包后端未提供 |

### ⚠️ DOC GAP（GUI/Back 一致，OpenAPI back.yaml 未声明）共 43 个

详见 `AUDIT_2026-04-24/04_diff_matrix.md` §2。主要分类：
- **/auth** 8 个：cancel-password-reset, change-password, check-activation-status, resend-activation-email, status, token/generate, validate-reset-token
- **/ippools** 7 个：index/store/statistics/show/auto-allocate/available-ips/delete
- **/nodes** 4 个：put `/{id}`, get `/{id}/config`, get `/{id}/index-services`, post `/{id}/operator`
- **/social** 13 个：data, friend-request 全套, friends/*, invitation/*, search-users
- **/users** 2 个：onboarding GET/PUT
- **/zones** 5 个：index/store/show/update/delete, services/{anonymous,friends,my,public}
- **/assets** 1 个：avatar/upload

**建议**：在 `openapi/back.yaml` 增量补全这 43 个端点，使 OpenAPI 完全覆盖 GUI 实际调用面。

### 🟡 后端孤儿（Back 实现但 GUI 未调用）共 69 个

详见 `AUDIT_2026-04-24/04_diff_matrix.md` §4。包括：
- `/notifications/*` 整个通知模块
- `/users/*` 多数管理端点
- `/ids/*` ID 工具端点
- `/monitor/node/*` lua/client 用的监控端点
- `/assets/*` 二进制资源管理

**建议**：保留（其他客户端使用）。如确认废弃可清理。

---

## 修复优先级（更新版）

1. ✅ **F1-F10**（已修） — GUI v1→v2 命名迁移
2. **B2** (安全漏洞) — config-bundle zone_id 鉴权
3. **B3** — router-keys 静默失败
4. **N1-N3** — 后端补齐 GUI 实际调用的缺失端点（zone overview/statistics、user ip-resources）
5. **B1** — lua v1 硬编码
6. **M3, M4** — GUI v1→v2 迁移（旧批次，仍待复核）
7. **DOC GAP 补全** — OpenAPI back.yaml 增量声明 43 个端点
8. **D1-D7** — 清理 GUI dead code
9. **M1, M2, M5** — 字段对齐
10. **S1~S4** — 功能改进
