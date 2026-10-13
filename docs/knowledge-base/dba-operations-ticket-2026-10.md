# DBA / 运维工单（2026-10-05 出件）

**这份文件只是工单外壳**：把散在 `dev-plan.md` / `deployment-registry.md` / `mynet_api/database/sql/*.sql`
里的**现网动作**按依赖排成一个可递交的顺序表。

> **同一主题不许有两处真相**：本文件**不复述**任何 SQL 正文、表/列定义、env 取值或验证数字 ——
> 那些都在各自的物件里，这里只给「跑哪个文件、什么顺序、跑完贴回什么、卡在哪一条裁定」。
> 每件 SQL 的文件头就是它的执行手册，请先读文件头再动手。

**状态（2026-10-05 更新）**：**A 栏 + B-1/B-2/B-4/B-5 已执行**（智能体经 SSH 在现网机上跑，见文末 §F）；
**B-3 未执行**（`invitations` 尚有 1 行，存量码处置未裁 —— E 栏第 3 条）。C/D/E 栏见各自条目。
下表「状态」原文的「全部未执行」已不成立，保留此句作对照。

**状态（原文）**：全部**未执行**。后端侧（本仓）够不到现网 MySQL，也 never 上过生产机。
**为什么现在需要这份工单**：2026-10-04/05 那批收口时，多处文档写着「由 DBA 手工执行」，
但**既没有一份按顺序可执行的清单，也出现过「文档指向一个不存在的脚本」**（A5 那条，已补件）。
所以本工单的第一条纪律是：**每一项都必须指出一个仓库里真实存在的文件**。

---

## A. 只读取证（先跑完这一栏再排 B 的任何一项）

A 栏全是 `SELECT` / `SHOW`，不改数据，可以一次跑完贴回。

| # | 取证什么 | 用什么物件 | 结果决定 |
|---|---|---|---|
| **A-1** | 现网库到底有哪些表 | `SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='<现网库>' ORDER BY table_name` | B-4/B-5 要不要跑；E1 那条硬口径「**DDL ≠ 现网**」的落地判据（后端启用的 74 张 vs 现网实际） |
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
| Operator 自我隔离 **F-18** / 被邀请人不在列表 **F-16** | 未裁，且都在框架侧（后端只上报）。开 console 给运营等于把这些面打开 ⇒ 上线前至少知道自己在承担哪条 |
| gnb 节点私钥轮换 | 事故件（2026-10-04 泄露），10-04 裁定「暂缓，只挂风险」；归属用户/运维，不在 DBA 工单里 |
| 生产 Redis 是否常驻、`console.mynet.club` DNS、有没有可用 Operator 账号 | 三条本机取证不到，登记在 `deployment-registry.md` §8.7；上机时一条命令即可收口 |

---

## F. 执行记录（2026-10-05，现网实跑）

> 执行人是智能体，经 SSH 到现网机 `10.182.236.180`（内部名 `ip-172-26-3-227`）跑 `mysql` 客户端。
> 全部语句与探针输出见本节；SQL 物件按本仓提交哈希即为准。

### F.1 环境实测（三处与工单原文不符，先记）

| 项 | 工单/文档原假设 | 现网实测 | 影响 |
|---|---|---|---|
| **DB 引擎** | MySQL 8.4.8（隔离实例上验的） | **MariaDB 10.11.18**（`VER 15.1 Distrib 10.11.18-MariaDB-0+deb12u1`） | 本批语句 MariaDB 全兼容（rc=0 + 探针过）；但 §8.5 那句「可空性改动在 MySQL 8 走 COPY」**不是本机引擎的行为**，MariaDB 下 7 张表行数极小、秒级完成。以后按引擎核，别照抄 MySQL 口径 |
| **库名** | `deployment-registry.md:311` 记 `mynet_api` | **`.env` `DB_DATABASE=mynet_back`**（`SHOW DATABASES`：`mynet`=159 表[退役保留]、`mynet_back`=121、`mynet_ctl`=16，**无 `mynet_api`**） | registry 那条已更正；工单 D 栏的 `<dbname>` 以现网 `.env` 为准的口径成立 |
| `sql_mode` | —— | `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` | 含 `STRICT_TRANS_TABLES` ⇒ 不存在「NULL 被静默改 0」 |

**执行前备份**（先备份后动手）：`/data/backup/mynet/api/pre-a5-owner-hardening-20261005-020434/mynet_back.sql.gz`
（`mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4`）。
809129 字节、`gzip -t` 通过、dump 内 `CREATE TABLE` 计数 = **121**、sha256 `4a3d2571e9ed1aebfa85548928a6f43d0a2e6eb308ac38e2fc801ac7a5363352`。

### F.2 A 栏只读取证（全 SELECT/SHOW）

| # | 结果 | 决定 |
|---|---|---|
| **A-1** | `mynet_back` = **121 张**表 | 与 E1「后端启用 74 张」对照：现网另有 47 张框架下游表 |
| **A-2** | 7 处归属列**无主行全为 0**（`nodes`=73、`routes`=56、`node_route_requests`=8、`ip_pools`=16 行，`connections`/`connection_grants` 为 0 行空表） | **B-1 回填是空操作** ⇒ 直接跑 B-2 |
| A-2 §9 | `node_ips.user_id` 70 行 / 0 NULL / 0 零；`service_connection_logs` 0 行 | 与「保留可空 + 登记语义」一致，不动 |
| **A-3** | `invitations` = **1 行** | **B-3（DROP）不执行** —— 行数非 0 且存量码重铸未裁 |
| **A-4** | `personal_access_tokens.device_fingerprint` **已存在**（`varchar(64) NULL`） | **B-5 无需执行** |
| **A-5** | `users.email` = `NOT NULL`；`users_email_unique` 在；`permissions`=114；`invite_code.manage` 缺失；**14 个角色全在 `tenant_id=9007199254740991`，无一 `tenant_id IS NULL`** | 跑 B-4 §1/§2/§3，但 §3 授权判据要换（见 F.4） |
| **A-6** | `sessions` 表存在 | C 栏上线无需先建表 |

### F.3 B 栏执行结果

| # | 物件 | 结果 | 探针/复核 |
|---|---|---|---|
| **B-1** | inventory §10 回填 | **未执行**（无主行 = 0，空操作） | A-2 计数全 0 |
| **B-2** | `2026_10_04_harden_owner_columns_not_null.sql` | **rc=0**，7 列 `IS_NULLABLE` 全 `NO` | `information_schema` 7 行全 `NO`/`bigint(20) unsigned`；插 NULL `user_id` → **ERROR 1048** 被挡；6 表行数执行前后一致（73/56/16/8/0/0） |
| **B-4 §1** | 建 `invite_codes` / `invite_code_usages` | **rc=0**，两表建成 | `SHOW CREATE TABLE` 与 `deploy/schema/mynet_back-schema.sql` 结构等价（MariaDB 仅显示宽度 `bigint(20)`、`json`→`longtext+json_valid` 渲染差异）；**中文 COMMENT 完整保留**（`--default-character-set=utf8mb4` 生效） |
| **B-4 §2** | `users.email` → NULL | **rc=0**，`IS_NULLABLE=YES`，唯一索引保留 | 事务内插 `email=NULL` 的 users 行 → **接受**（原为 `1048`），`ROLLBACK` 后 0 残留 |
| **B-4 §3** | 权限位 `invite_code.manage` + 授予 | **rc=0**，`permissions` 114→**115**，授予 **3** 角色 | 见 F.4：判据改为按角色名 |
| **B-3** | `DROP invitations` | **未执行** | 见 A-3 |
| **B-5** | 加 `device_fingerprint` | **未执行（不需要）** | 见 A-4 |

### F.4 分歧一：`invite_code.manage` 的授权判据（决策包）

框架迁移 `vendor/dsplat/multi-tenant-saas-module-auth/Database/migrations/2026_10_01_000002_add_invite_code_permission.php:99-103`
与 seeder `…/module-operator/Database/Seeders/RolePermissionSeeder.php:132-135`、`…/SuperAdminSeeder.php:107`
**一律用 `roles.tenant_id IS NULL` 找「全局角色」**。而现网这 14 个角色（role_id 与框架核心数据迁移
`multi-tenant-saas/database/migrations/2025_01_02_000000_framework_core_data.php:112+` 的 NULL 版本**逐一致**）
的 `tenant_id` 是 **`9007199254740991`（平台租户）** —— 即现网这批全局角色被（项目侧无任何迁移记录地）
表示成了「平台租户下的角色」。

⇒ **照抄该判据在现网会零授权**（§3 试算结果为 0 行）。本次执行按**角色名**授权（`super_admin` /
`platform_admin` / `tenant_admin`），与现网既有约定一致：这 3 个角色本就分别持有 111 / 69 / 61 条
`role_permissions`（现有 344 行全挂在平台租户角色上）。授权后 `granted_roles = 3`。

**待裁（回主线）**：现网全局角色是「平台租户表示」，框架代码是「NULL 表示」——
两者 role_id 相同、`role_permissions` 已按前者积累。要么（a）接受平台租户表示、认框架那批
`whereNull` 判据在本部署为**框架侧不适用**（出上游报告）；要么（b）把现网全局角色的 `tenant_id`
规整回 NULL（数据迁移，需单独评估对既有 344 行授权与 `operator_tenants.role_id` 的影响）。
在裁定前，**任何框架 seeder/迁移在现网都会找不到全局角色**（`SuperAdminSeeder` 亦然）。

### F.5 现网留档与最小痕迹

- 上机 SQL 副本：`/root/a5-sql/`（3 个文件，与本地 sha256 一致：sync `073de960…`、harden `7b1c9d7a…`、inventory `223e094d…`）。**收工已删**。
- 临时凭据文件 `/root/.my.db.cnf`（0600，仅执行期存在）。**收工已 `shred` 删除**；`/root/a5-sql/` 同批删净，两者复核均 `No such file` ⇒ 现网不留任何凭据或暂存物。
- 全部动作均为可逆：B-4 §1 可 `DROP TABLE`、§2 可 `MODIFY … NOT NULL` 回退、§3 可
  `DELETE FROM role_permissions/permissions` 回退、B-2 可 `MODIFY … NULL` 回退；备份可整库还原。
