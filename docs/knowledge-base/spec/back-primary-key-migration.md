# SPEC: 主键改造 —— 全局 ID 直接作 PK

> 更新：2026-09-21 | 适用：`mynet_api`
> 状态：**VPN 批次已完成**（18 张表 + zone 清理，提交 `27b32753`）；其余模块 30 个自增列待做
> 决策来源：用户拍板「取消自增 PK + 业务 ID 的双主键，所有 PK 直接就是全局 ID」

---

## 0. 执行进度

| 批次 | 表数 | 状态 |
|---|---|---|
| 备份 | — | ✅ `storage/backups/mynet_back_new-20260921-094002.sql`（0.6 MB / 156 表） |
| zone → tenant 彻底完成 | — | ✅ 0 悬空外键；全库 zone 表/列/外键归零；4 个 Zone 死类已删 |
| VPN 核心 + 服务 | 18 | ✅ PK 全部改为语义名全局 ID |
| Social / Monitor / Billing / Plugins 等 | 30 个自增列 | ⬜ 待做 |

**执行顺序与预期不同**：实测发现整个 VPN/Social 数据集都是**孤儿行**
（`tenants` 仅 1 行、`users` 仅 2 行，而 routes 32/32、node_ips 38/38、nodes 25/25 全部悬空），
必须先清孤儿才能重建外键。清理后 17/18 张目标表已空，PK 改造退化为**纯结构操作**。

已完成的两条迁移：
- `2026_09_21_000002_repair_legacy_zone_foreign_keys`（孤儿清理 + FK 修复）
- `2026_09_21_000001_add_global_ids_to_legacy_vpn_tables`（PK 改造）

---

## 0. 已确认的两项决策（2026-09-21）

| 决策 | 结论 |
|---|---|
| **PK 列名** | **统一用语义名** —— `route_id` / `node_id` 保持不变；只有 `id` 的表新增语义列（`alert_rule_id` / `invoice_id` …）。理由：框架自带的 107 张表已用语义名（`agents.agent_id`、`conversations.conversation_id`），语义名才是全库一致的做法 |
| **zone → tenant** | **彻底完成** —— 修 6 个悬空外键、删除遗留 `user_zones`、清理 4 个 Zone 类。见 §8 |

> 命名决策的直接好处：9 张「双 ID」表**不需要改名**，因此指向 `route_id`/`node_id` 的
> **外键与 PHP 代码零改动**。

---

## 1. 背景与决策

旧结构为兼容自增，在自增 `id` 之外再挂一个业务 ID 列（`route_id` / `node_id`…）。
项目已全局禁用自增（`docs/architecture/设计决策.md:9`、
`docs/security/安全审计报告.md:108`、`CHANGELOG.md:121`），
业务 ID 列因此成为冗余 → **直接提升为唯一主键，删掉自增 `id`**。

目标形态：

```sql
-- 之前
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
route_id BIGINT UNSIGNED UNIQUE

-- 之后
route_id BIGINT UNSIGNED PRIMARY KEY
```

---

## 2. 实测范围（库 `mynet_back_new`，156 张表）

| 类别 | 数量 | 处理 |
|---|---|---|
| 非自增 PK（已符合约定） | 107 | **不动** |
| 自增 PK | 48 | 需改造 |
| ├ 已有业务 ID 列 | 9 | 删 `id`，提升业务 ID 为 PK |
| └ 只有自增 `id` | 39 | 新增全局 ID 列 → 回填 → 提升为 PK → 删 `id` |

**不动**：`migrations`（Laravel 系统表）、`cache` / `cache_locks`（已是 varchar key）。
**待定**：`personal_access_tokens`（Sanctum 自带迁移用 `$table->id()`，改动可能影响该包）。

---

## 3. 外键影响（已实测，风险远低于预期）

106 个外键中，**绝大多数已指向业务 ID 列**而非 `id`：

| 被引用表 | 引用数 | 指向 |
|---|---|---|
| `nodes` | 8 | 全部 `→ node_id` ✅ |
| `services` | 3 | 全部 `→ service_id` ✅ |
| `plugins` | 1 | `plugin_dependencies.plugin_id → plugins.id` ❌ 唯一指向裸 id |
| `tenants` | 27 | 全部 `→ tenant_id` ✅ |

**只有一处**需改：`plugin_dependencies.plugin_id` 的引用目标需从 `plugins.id` 改为 `plugins.plugin_id`。

**另有历史遗留**：6 处外键指向**不存在的 `zones` 表**（`routes.tenant_id`、
`addresses.tenant_id`、`ip_pools.tenant_id`、`node_services.tenant_id` 等）。
`zones` 已更名为 `tenants`，这些 FK 未同步 → 需一并重建为指向 `tenants.tenant_id`。

---

## 4. 分类映射

### 4.1 A 类：删 `id`，提升既有列（9 张）

| 表 | 新 PK |
|---|---|
| `addresses` | `address_id` |
| `ip_pools` | `ippool_id` |
| `nodes` | `node_id` |
| `routes` | `route_id` |
| `services` | `service_id` |
| `user_api_tokens` | `user_id` |
| `user_preferences` | `user_id` |
| `node_configs` | `node_id`（1:1，`unique(node_id)` 语义不变） |
| `node_keys` | ⚠️ 见 4.3 |

### 4.2 B 类：新增全局 ID 列（38 张）

| 表 | 新 PK 列 | 表 | 新 PK 列 |
|---|---|---|---|
| `node_ips` | `ip_id` | `invoice_items` | `invoice_item_id` |
| `node_route_requests` | `request_id` | `invoices` | `invoice_id` |
| `node_services` | `node_service_id` | `lottery_pools` | `lottery_pool_id` |
| `node_access_rules` | `rule_id` | `node_monitor_snapshots` | `snapshot_id` |
| `service_approvals` | `approval_id` | `node_peer_connections` | `node_peer_connection_id` |
| `service_connection_logs` | `log_id` | `payment_logs` | `payment_log_id` |
| `service_shares` | `share_id` | `payment_orders` | `payment_order_id` |
| `sms_verifications` | `verification_id` | `plugin_dependencies` | `plugin_dependency_id` |
| `system_events` | `event_id` | `plugins` | `plugin_id` |
| `friend_requests` | `friend_request_id` | `rate_limit_rules` | `rate_limit_rule_id` |
| `friendships` | `friendship_id` | `role_permissions` | `role_permission_id` |
| `invitations` | `invitation_id` | `structured_logs` | `structured_log_id` |
| `social_connections` | `social_connection_id` | `tax_rules` | `tax_rule_id` |
| `id_pools` | `id_pool_id` | `tenant_modules` | `tenant_module_id` |
| `agent_workflows` | `agent_workflow_id` | `user_api_token_histories` | `history_id` |
| `alert_rules` | `alert_rule_id` | `user_payment_passwords` | `password_id` |
| `alerts` | `alert_id` | `user_zones` | `user_zone_id` |
| `api_versions` | `api_version_id` | `email_verification_tokens` | `token_id` |
| `export_tasks` | `export_task_id` | | |

### 4.3 `node_keys`：新增 `key_id` 作 PK，`unique(node_id)` 降为普通索引

理由：密钥**轮换**需要保留历史（旧 key 标记 rotated 而非删除）。
`unique(node_id)` 会让同一节点只能存在一行（软删行仍占用唯一索引），无法记录轮换历史。
故 `node_keys` 用自有 `key_id` 作 PK，`node_id` 建普通索引。

---

## 5. 迁移执行顺序（每张表）

```sql
-- 1) 先摘掉引用本表 id 的外键（仅 plugin_dependencies 一处）
-- 2) 去掉自增属性（AUTO_INCREMENT 列必须先离开主键才能删）
ALTER TABLE t MODIFY id BIGINT UNSIGNED NOT NULL;
-- 3) B 类：新增列并回填 16 位全局 ID（A 类跳过）
ALTER TABLE t ADD COLUMN new_id BIGINT UNSIGNED NULL FIRST;
UPDATE t SET new_id = <random 16 位>;     -- 逐行保证唯一
ALTER TABLE t MODIFY new_id BIGINT UNSIGNED NOT NULL;
-- 4) 换主键
ALTER TABLE t DROP PRIMARY KEY, ADD PRIMARY KEY (new_id);
-- 5) 删旧列
ALTER TABLE t DROP COLUMN id;
-- 6) 重建外键（指向新 PK）
```

**批量边界（建议按模块分批执行与验证，不要一次 48 张）**

| 批次 | 表 |
|---|---|
| 1 | VPN 核心：`nodes` `routes` `ip_pools` `node_keys` `node_configs` `node_ips` `node_route_requests` `addresses` |
| 2 | VPN 服务：`services` `service_shares` `service_approvals` `service_connection_logs` `sms_verifications` `node_services` `node_access_rules` |
| 3 | Social：`friend_requests` `friendships` `invitations` `social_connections` |
| 4 | Monitor：`alert_rules` `alerts` `node_monitor_snapshots` `node_peer_connections` `structured_logs` `system_events` |
| 5 | 其余：`agent_workflows` `api_versions` `export_tasks` `id_pools` `invoices` `invoice_items` `lottery_pools` `payment_logs` `payment_orders` `plugin_dependencies` `plugins` `rate_limit_rules` `role_permissions` `tax_rules` `tenant_modules` `user_api_token_histories` `user_api_tokens` `user_payment_passwords` `user_preferences` `user_zones` `email_verification_tokens` |

---

## 6. 前置与回滚

**执行前必须**：
1. 确认目标库身份（本地开发库 or 其他）——`mynet_back_new`，127.0.0.1:3306
2. **完整备份**（mysqldump 全库）
3. 确认该库上还有 126 个已执行迁移、以及大量 pending 迁移；本改造**不依赖**它们

**回滚**：每批次的迁移 `down()` 重建 `id` 自增列并恢复原 PK。
但**数据无法完全还原自增序号**（原值已删）——回滚只保证结构可用，
因此备份是唯一可靠的回滚手段。

**风险**：
- 删列不可逆（无备份时）
- 换 PK 会重建聚簇索引，大表有锁与耗时（本库表均小，NV 级）
- 外键重建若有遗漏，会留下悬空引用（迁移内置断言）

---

## 7. 验收判据

- [ ] `information_schema` 中不再有 `extra LIKE '%auto_increment%'` 的列（除白名单）
- [ ] 所有被引用表的 FK 指向的列都是该表 PK
- [ ] 无指向 `zones` 的残留 FK
- [ ] `php artisan migrate:status` 该批次显示 Ran
- [ ] Eloquent 读路径抽样通过（`Route` / `NodeKey` / `NodeIP` / `NodeRouteRequestModel`）
- [ ] `php artisan route:list` 仍为 1090 条

---

## 8. zone → tenant 彻底完成（本次一并做）

### 8.1 现状：迁移半途而废（实测）

| 项 | 现状 |
|---|---|
| `tenants` 表 | ✅ 已建，PK = `tenant_id` |
| `user_zones` 表 | ❌ **仍然存在**（`zone_id` / `zone_name` / `zone_description`）——`2026_07_10_000003_rename_user_zones_to_tenant_users` 未生效 |
| 指向 `zones` 的外键 | ❌ **6 个全部悬空**（`zones` 表已不存在） |

悬空外键清单：

```
addresses.tenant_id      -> zones.zone_id   (fk_addresses_zone_id)
ip_pools.tenant_id       -> zones.zone_id   (fk_ip_pools_zone_id)
node_services.tenant_id  -> zones.zone_id   (node_services_zone_id_foreign)
nodes.tenant_id          -> zones.zone_id   (fk_nodes_zone_id)
routes.tenant_id         -> zones.zone_id   (fk_routes_zone_id)
user_zones.zone_id       -> zones.zone_id   (user_zones_zone_id_foreign)
```

### 8.2 处理

| 步骤 | 动作 |
|---|---|
| 1 | 删除上述 6 个悬空外键（约束名已实测，逐个 dropForeign） |
| 2 | 为 5 张业务表（`addresses`/`ip_pools`/`node_services`/`nodes`/`routes`）重建外键：`tenant_id → tenants.tenant_id` |
| 3 | `user_zones`：确认 `tenant_users` 是其后继。若 `tenant_users` 已含同等数据 → 删除 `user_zones`；否则先迁移数据。**执行前先比对两表行数与结构** |
| 4 | PHP 侧清理 4 个 Zone 类及其引用：`ZoneId`、`ZoneDomainService`、`ZoneAccessDeniedException`、`ZoneConfigCacheService`；改指 `TenantId` / `TenantDomainService` 等价物 |
| 5 | 复查：`information_schema` 中 `LIKE '%zone%'` 的表/列/外键均为 0；`grep -r Zone app/` 为空 |

### 8.3 ⚠️ `user_zones` 删除前必须比对数据

`tenant_users` 与 `user_zones` 可能同时含数据（或其一为空）。删除前须：
- 比对行数
- 比对业务列映射（`zone_id`→`tenant_id`、`zone_name`→?、`zone_description`→?）
- 若 `tenant_users` 缺列/缺数据，先补再删

**在比对结果出来前不得直接 DROP TABLE。**
