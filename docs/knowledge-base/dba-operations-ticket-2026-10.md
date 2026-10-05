# DBA / 运维工单（2026-10-05 出件）

**这份文件只是工单外壳**：把散在 `dev-plan.md` / `deployment-registry.md` / `mynet_api/database/sql/*.sql`
里的**现网动作**按依赖排成一个可递交的顺序表。

> **同一主题不许有两处真相**：本文件**不复述**任何 SQL 正文、表/列定义、env 取值或验证数字 ——
> 那些都在各自的物件里，这里只给「跑哪个文件、什么顺序、跑完贴回什么、卡在哪一条裁定」。
> 每件 SQL 的文件头就是它的执行手册，请先读文件头再动手。

**状态**：全部**未执行**。back 侧（本仓）够不到现网 MySQL，也 never 上过生产机。
**为什么现在需要这份工单**：2026-10-04/05 那批收口时，多处文档写着「由 DBA 手工执行」，
但**既没有一份按顺序可执行的清单，也出现过「文档指向一个不存在的脚本」**（A5 那条，已补件）。
所以本工单的第一条纪律是：**每一项都必须指出一个仓库里真实存在的文件**。

---

## A. 只读取证（先跑完这一栏再排 B 的任何一项）

A 栏全是 `SELECT` / `SHOW`，不改数据，可以一次跑完贴回。

| # | 取证什么 | 用什么物件 | 结果决定 |
|---|---|---|---|
| **A-1** | 现网库到底有哪些表 | `SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='<现网库>' ORDER BY table_name` | B-4/B-5 要不要跑；E1 那条硬口径「**DDL ≠ 现网**」的落地判据（back 启用的 74 张 vs 现网实际） |
| **A-2** | 7 处归属列还有多少无主行 + 各自可推断到哪一档 | **`mynet_api/database/sql/2026_10_04_owner_columns_null_inventory.sql`**（10 段，全只读） | B-1 的回填范围；`node_route_requests.target_user_id` 与 `ip_pools` 那两档要不要单独处理 |
| **A-3** | `invitations` 表在不在、有几行、谁还在用 | **`mynet_api/database/sql/2026_10_04_drop_invitations_table.sql`** 文件头的「执行前取证（必做）」三条 SELECT | B-3 能不能执行（G-8b 的现网行数取证**至今没做**） |
| **A-4** | `personal_access_tokens.device_fingerprint` 列在不在 | `SHOW COLUMNS FROM personal_access_tokens LIKE 'device_fingerprint'` | B-5 要不要跑 |
| **A-5** | 两张 `invite_code*` 表 / `users.email` 可空性 / `invite_code.manage` 权限位 / 三条全局角色 | **`mynet_api/database/sql/2026_10_05_sync_invite_code_infrastructure.sql`** 的 §0（4 段只读） | B-4 要不要跑、跑哪几段 |
| **A-6** | `sessions` 表在不在（`SESSION_DRIVER=database` 的前提） | `SHOW TABLES LIKE 'sessions'` | C 栏上线前是否需要先建表 |

⚠️ A-4 的那份 SQL 自称「幂等：重复执行无害」是**错的** —— `ADD COLUMN IF NOT EXISTS` 是 MariaDB
方言，MySQL 8.4 直接 1064（本机实测，dev-plan F-5 行有记录）。⇒ 判「在不在」必须靠 A-4 这条
`SHOW COLUMNS`，不能靠重跑文件。

⚠️ A-2 数 NULL 的口径**含软删行**，且迁移件守卫用的是同一口径 —— 别在清单里自行加
`deleted_at IS NULL` 过滤，否则两边数出来不一样，B-2 会莫名其妙被拒。

---

## B. 写动作（顺序即依赖，别并行）

| # | 动作 | 物件 | 前置 | 窗口/风险 |
|---|---|---|---|---|
| **B-1** | 按裁定回填无主行的归属 | inventory 文件 §10 的三档 UPDATE 形状（档 A 区内唯一活跃成员 / 档 B 经父资源推断 / 档 C `tenants.admin_id`） | A-2 | **改前逐表留底**（`CREATE TABLE …_bak_A5 AS SELECT`）；每条 UPDATE 必须带 `AND <col> IS NULL` 收口 |
| **B-2** | 7 处归属列收 `NOT NULL` | **`mynet_api/database/sql/2026_10_04_harden_owner_columns_not_null.sql`** | B-1 完成后 §1 复数 = 0 | 改变列可空性在 MySQL 8 走 **COPY**（重建表、期间挡写）⇒ 按 `table_rows` 选窗口 |
| **B-3** | 下架 `invitations` 表 | **`mynet_api/database/sql/2026_10_04_drop_invitations_table.sql`** | A-3 显示行数 0，**或**存量码的处置已被裁定 | `DROP`，不可逆；「把存量码重铸成框架码」这条**至今没裁**（见 E 栏） |
| **B-4** | 补齐邀请码基础设施（两张表 + `users.email` 放宽 + 权限位） | **`mynet_api/database/sql/2026_10_05_sync_invite_code_infrastructure.sql`** | A-5；§3 另需先在生产机生成 16 位 id | 与 B-1/B-2 无依赖，可独立排期；执行**必须带 `--default-character-set=utf8mb4`**（该文件头记了实测后果） |
| **B-5** | 加 `device_fingerprint` 列 | **`mynet_api/database/sql/2026_09_27_add_device_fingerprint_to_personal_access_tokens.sql`** | A-4 显示缺列 | 漏了它 = **任何** Bearer 用户面请求 500（`token.binding` 中间件无条件读这列）；测试全绿掩盖不了现网 |

**两条执行纪律（适用于本栏全部）**

1. **严禁 `mysql --force`**。B-2 的守卫靠「客户端遇错即停」中止，带 `--force` 会把 7 条 `ALTER`
   逐条执行掉 —— 那正是它要防的「半套状态」（前几张表已改、后几张没改）。
2. 含 `DELIMITER` 的物件（B-2）**只能用 `mysql` 客户端整份 source**，`artisan`/PDO 吃不下。

**跑完复核**：B-2 用该文件 §4 的 `information_schema` 查询（期望恰好 7 行、`IS_NULLABLE` 全 `NO`），
再执行 §4b 那条注释着的 `INSERT … NULL` 探针，期望 `ERROR 1048` —— 这一步证明约束**真在挡**，
而不只是字典变了。

---

## C. 运维上线：`console.mynet.club`

见 [`deployment-registry.md`](deployment-registry.md) **§8**（唯一真相：nginx 动作、上机前只读核对、
SPA 构建顺序、生产 `.env` 逐键、DBA 两件、冒烟三条 + 人走查、未取证 5 条）。本工单只压两条与
A/B 栏有交集的：

- §8.5 的两件 = 上表的 **B-2** 与 **B-4**，同一批人、同一窗口排。
- §8.4 的 `APP_TIMEZONE=Asia/Shanghai`（G4 裁定）与「框架端点时间字符串按应用时区墙钟解释」这条
  硬口径，在部署时一并核对。

---

## D. 回贴格式（每项贴这四行，别只贴「已执行」）

```
A-x / B-x：
  执行时刻：            （UTC+8 墙钟）
  命令：                mysql --default-character-set=utf8mb4 -u<user> -p <dbname> < <文件名>
  结果：                退出码 + 全部 SELECT 输出原样贴回；报错贴首条错误号
  复核：                该文件「复核」段的期望值 vs 实测值
```

⚠️ **口令不要出现在贴回内容里**（也别写进 argv，用 `--defaults-extra-file`）。贴回时命令一行的
`-p` 保持不带值。

---

## E. 不在本工单内（要先裁，才谈执行）

| 事项 | 为什么卡住 |
|---|---|
| `node_route_requests.target_user_id` 的 pending 行归属 | 语义是「被请求那一方的人」，A5 第二轮已裁「照常收 7 列 + 走推断」；若 A-2 显示存在**推断不出来**的 pending 行，那是裁定没覆盖的形状 ⇒ 回主线裁决，DBA 不要自己填 0 |
| 「0 号归属行谁能改」 | 若 A-2 出现三档都覆盖不到的剩余行：`OwnerGuard::owns()` 对 NULL 与 0 给的是**同一个答案（拒）**，填 0 只是换个写法 ⇒ 必须先定「console/运营面按租户管理员放行、用户面继续 fail-closed」这条规则才允许选 0 |
| 旧 `invitations` 存量码重铸成框架码 | G-8b 留的可选项，至今没裁；裁之前 B-3 只能「行数 0」才敢跑 |
| `node_ips.user_id` / `service_connection_logs.user_id` | A5 已裁「**保留可空 + 登记语义**」⇒ 本工单**不收**这两列，也别顺手收 |
| Operator 自我隔离 **F-18** / 被邀请人不在列表 **F-16** | 未裁，且都在框架侧（back 只上报）。开 console 给运营等于把这些面打开 ⇒ 上线前至少知道自己在承担哪条 |
| gnb 节点私钥轮换 | 事故件（2026-10-04 泄露），10-04 裁定「暂缓，只挂风险」；归属用户/运维，不在 DBA 工单里 |
| 生产 Redis 是否常驻、`console.mynet.club` DNS、有没有可用 Operator 账号 | 三条本机取证不到，登记在 `deployment-registry.md` §8.7；上机时一条命令即可收口 |
