# MyNet 部署清单（Deployment Registry）

> **地位**：本文件是「**部署元数据**」的 SSOT，回答"项目部署到哪里、用什么命令、谁可以登录"。  
> **真实凭证**：见 [`mynet_ctl/.credentials/`](../../.credentials/)（不入仓）。
>
> ⚠️ **本文件 2026-09-24 由 V3-7 逐条核对过**：每条声明的脚本/路径都 `ls` 过，
> 不存在的删掉、待补的写明「未建立」而不留 `<待补>`（`<待补>` 会让人以为
> 「已经有了只是没填」，那和事实相反）。

---

## 1. 项目矩阵

### 1.1 服务器部署

| 项目 | 类型 | 生产域名 | 部署路径 | 部署命令 |
|------|------|---------|---------|---------|
| **mynet_ctl** | Laravel 12 | ctl.mynet.club | `/data/app/mynet.club/ctl` | `cd mynet_ctl && bash scripts/deploy/deploy.sh` |
| **mynet_api** | Laravel 12 | api.mynet.club（API）<br>**console.mynet.club**（租户管理） | `/data/app/mynet.club/api` | `cd mynet_api && ./manage-prod.sh deploy` |
| **mynet_gui** | Vue 3 / Quasar | my.mynet.club | `/data/app/mynet.club/my/public` | `cd mynet_gui && bash deployment.sh` |
| **mynet_web** | 静态站点 | www.mynet.club | ⚠️ **未建立**（无部署路径记录） | ⚠️ **无部署脚本** |

服务器 **`10.182.236.180`**（公网 `52.77.179.176`）。ctl / back / gui 三者**共置同机、
各是独立 nginx server block**；证书在 `/etc/letsencrypt/live/mynet.club/`，
**主机 nginx 终结 TLS**（H1：不引入 Caddy）。

> **子域命名（H4 裁定，2026-09-24）**：`console.mynet.club` = back 的**租户管理**面；
> `admin.mynet.club` 让给框架的 platform/operator SPA（**不是** back 的管理面）；
> `adm` 弃用。本文档、`ecosystem.md`、`brand-guide.md`、`projects/mynet_api.md`
> 已于 V3-7 同步。

### 1.2 分发到节点（不经服务器部署）

这些仓的产物由 **ctl 分发中心**发放，用户机 / OpenWrt 设备自行更新：

| 项目 | 类型 | 产物 | 打包入口 |
|------|------|------|---------|
| **mynet_agent** | Go | 5 平台 × 3 二进制（`mynet` / `mynetd` / `mynet_gateway`）+ `checksums.txt` | `make dist` |
| **mynet_openwrt** | LuCI 壳 + ubus 桥 + ipk | `luci-app-mynet.ipk`、`mynet-agent` ipk | `make package/.../compile`（缺 `files/mynetd` 时**拒绝产出空壳 ipk**） |
| **mynet_desktop** | 平台适配 | systemd unit / launchd plist / 安装脚本 | ⚠️ **无打包入口**（`make lint` / `make test` 目前是占位） |
| **mynet_gnb** | opengnb fork | gnb 二进制（**我们自己 patch 过的树**，非上游原件） | 见 `packages/`（补丁 + `rebase.sh`）；导入 ctl 用 §3 的 `import_gnb_*.sh` |
| **mynet_utc** | UDP→TCP 隧道 | 二进制 | `./build.sh`（符号链接 → `scripts/dev/build.sh`） |

### 1.3 已退役 / 不在本 workspace

| 名字 | 状态 |
|------|------|
| `mynet_client` | **已退役**：Node.js 客户端，职责拆到 agent / gui / desktop 三条线（依据 `repo-migration.md:81`） |
| `mynet_lua` | **已退役**：OpenWrt LuCI 旧线，由 `mynet_openwrt` 取代（依据 `repo-migration.md:82`） |
| `mynet_javis` | myjarvis.ai，**不在本 workspace**。本文件历史版本列过它，此处保留仅为说明其去向 |

> 上面两条退役记录不是「删掉了」——照旧名去 `../mynet_client/` 找东西只会扑空，
> 所以必须写明承接者。

---

## 2. 域名→项目映射

参见 [`.credentials/_templates/domains.md`](../../.credentials/_templates/domains.md)。

| 子域 | 项目 | 环境 |
|------|------|------|
| ctl.mynet.club | mynet_ctl | prod |
| api.mynet.club | mynet_api（API） | prod |
| console.mynet.club | mynet_api（租户管理） | prod |
| my.mynet.club | mynet_gui | prod |
| www.mynet.club | mynet_web | prod |
| gw.mynet.club | mynet_gateway（节点面入口） | prod |
| admin.mynet.club | 框架 platform/operator SPA（**非** back） | prod |

---

## 3. 部署 / 打包脚本入口

逐条 `ls` 核实过（2026-09-24）。**不存在的已删除**，不再留空指路。

| 用途 | 路径 | 说明 |
|------|------|------|
| CTL 部署 | [mynet_ctl/scripts/deploy/deploy.sh](../../scripts/deploy/deploy.sh) | rsync + 远程 composer + **migrate** + 缓存。ctl 自己允许 migrate（「禁止 migration」只约束 mynet_api） |
| Back 部署 | [mynet_api/manage-prod.sh](../../../mynet_api/manage-prod.sh) | 远端守卫 → 备份 → rsync → composer → 权限 → 重载 FPM → 健康检查。**不跑 migrate** |
| Back 备份 / 恢复 | [mynet_api/deploy/](../../../mynet_api/deploy/) | `backup.sh` / `restore.sh` / `backup-drill.sh` / `install-cron.sh`（V3-5） |
| GUI 部署 | [mynet_gui/deployment.sh](../../../mynet_gui/deployment.sh) | 本地 build + rsync `dist/` |
| 客户端打包 | [mynet_agent/Makefile](../../../mynet_agent/Makefile) 的 `dist` 目标 | 5 平台 × 3 二进制 + checksums |
| OpenWrt 打包 | [mynet_openwrt/Makefile](../../../mynet_openwrt/Makefile) | ipk |
| gnb 导入 | [mynet_ctl/scripts/import_gnb_v1.6.4.sh](../../scripts/import_gnb_v1.6.4.sh) | 从 GitHub 拉 gnb release → `artisan release:import-gnb` |

**已删除的入口**（曾在本文件里，但盘上已无对应物）：

- `mynet_api/scripts/deployment/deploy-unified.sh` —— `mynet_api/scripts/` 整个目录已不存在，`manage-prod.sh` 取代它
- `mynet_client/pack.sh`、`mynet_lua/Makefile`、`mynet_lua/debug/sync.sh` —— 两个仓已退役（见 §1.3）

---

## 4. 凭证目录入口

| 类别 | 模板 | 实际位置（不入仓） |
|------|------|------------------|
| 服务器 SSH / 路径 | [_templates/servers.md](../../.credentials/_templates/servers.md) | `.credentials/servers.md` |
| 数据库 | [_templates/databases.md](../../.credentials/_templates/databases.md) | `.credentials/databases.md` |
| API Token / APP_KEY | [_templates/api-tokens.md](../../.credentials/_templates/api-tokens.md) | `.credentials/api-tokens.md` |
| 第三方服务（邮件 / S3 / Reverb） | [_templates/third-party.md](../../.credentials/_templates/third-party.md) | `.credentials/third-party.md` |
| 域名 / SSL / CORS | [_templates/domains.md](../../.credentials/_templates/domains.md) | `.credentials/domains.md` |
| `back_priv` / `back_pub` | — | **H6 待办**：生成后入 `crt` 凭证库；形态与权限见 `spec/node-keying.md` §4.6 |

---

## 5. 部署前置检查

每次生产部署前必须确认：

- [ ] 本地代码已提交并推送
- [ ] `composer install --no-dev --optimize-autoloader`（Laravel）
- [ ] `npm run build`（前端）
- [ ] 数据库变更评估（mynet_api **禁止 migration**，需 DBA 走 `database/sql/`）——**现网执行顺序与前置取证见 [`dba-operations-ticket-2026-10.md`](dba-operations-ticket-2026-10.md)**（工单外壳，不重复 SQL 正文）
- [ ] 凭证（`.env`）已同步到生产
- [ ] `back_priv` 文件权限 **0600**（`manage-prod.sh` 的远端守卫会拦；H7 裁定见 `spec/node-keying.md` §4.6）
- [ ] 队列服务（mynet_api）会自动 restart
- [ ] 部署后健康检查（关键 URL 200 OK）

---

## 6. 回滚预案

| 项目 | 回滚方式 |
|------|---------|
| Laravel（ctl / back） | `manage-prod.sh rollback`，或 `git reset --hard <prev_commit>` 后重跑部署脚本 |
| GUI | rsync 备份的旧 `dist/` 目录回原位置 |
| 客户端 / OpenWrt | 用户保持旧版本；CTL 不强制更新 |
| 数据库 | **无自动回滚**。备份见 `mynet_api/deploy/backup.sh`（V3-5），恢复用 `restore.sh`——但那是「恢复到某个时点」，不是回滚一次迁移 |

---

## 7. 监控与告警

> ⚠️ 本节此前三处写 `<待补>`。`<待补>` 的问题是它读起来像「已经有了、只是没填」，
> 而事实是**大部分还没建立**。V3-7 改为如实描述。

| 项 | 现状 |
|----|------|
| 服务可用性 | ⚠️ **未建立**。`manage-prod.sh` 只在部署结束时做一次健康检查，没有常驻监控 |
| 错误日志 | 有：Laravel log（[mynet_api/config/logging.php](../../../mynet_api/config/logging.php) 存在）。**Slack webhook 未接** |
| 队列堆积 | ⚠️ **未建立**。本文件旧版写的 `scripts/service/queue-monitor-daemon.sh` **在盘上不存在** |
| 心跳异常 | ⚠️ **无告警**。back 侧有 `monitor/heartbeat` → Redis（全节点 zset + 离线扫描）这条链，但没有任何提醒出口；节点侧指标采集本身也不完整（`long-range-plan-deep.md` §1「可观测性」） |

**结论**：监控面整体属**未建立**状态，是 V4（规模化与可观测）的内容，不要按本表
以为生产已有监控。

---

## 8. `console.mynet.club` 上线清单（G-6 + G4，2026-10-05 **出件、未执行**）

> **状态先说清**：本节是**部署件**，不是部署记录。本仓从未在生产机上执行过下面任何一步，
> 也从没向 `console.mynet.club` 发过一个请求 —— 凡本机取证不到的事一律写在
> §8.7「未取证」里，没有一条按「大概已经好了」处理。
> 10-04 的交互式裁定是「**出部署件 + 待你上机**」，所以上机动作归用户/运维。

### 8.1 nginx server block

配置**只有一处真相**：[`mynet_api/deploy/nginx.conf`](../../mynet_api/deploy/nginx.conf)
末尾的 `console.mynet.club` 段（2026-10-05 由「注释保留」转为可上线，因为 G-2/G-4/G-5 已真落）。
本节**不复述配置内容**，只给动作与判据：

```
# 上机动作（在服务器上，不是本机）
cp deploy/nginx.conf /etc/nginx/sites-enabled/mynet-back.conf   # 或该机的等价 include 位置
nginx -t && systemctl reload nginx
```

⚠️ 顺带修掉一处会让 `nginx -t` 直接红的旧缺陷：那一段此前是**整块注释掉的草稿**，
而草稿里有一行 `listen [::]:443 ssl http2;` **漏了 `#` 前缀** —— 也就是说仓库里这份 conf
从来没被 `nginx -t` 验过（`server` 块外多一条裸 `listen`）。现在整段已落地成形，
本机用 `nginx -t` 对**剥掉 ssl_* 与证书路径**的等价副本做过语法校验（syntax ok）。

关键设计（细节与理由都在 conf 的注释里，这里只列结论）：

| 点 | 结论 |
|---|---|
| `root` | 与 `api.mynet.club` **同一个应用**的 `public/` —— console 取数是相对路径 `/api/v1/console/*`（`src/Modules/VPN/resources/console/api.ts:18`），同站同源 ⇒ **不需要**为它配 CORS |
| 登录态 | **Cookie 会话**，不是 Bearer（`bootstrap/app.php:55-62` 的 `EnsureFrontendRequestsAreStateful`）⇒ 靠 `SANCTUM_STATEFUL_DOMAINS`，见 §8.4 |
| SPA 深链接 | 由 `routes/web.php:23-25` 的 `console` 组回落，nginx 侧只需常规 `try_files` |
| 外壳缓存 | `/console/` 必须 `no-store`（`index.html` 引 hash chunk，vite `emptyOutDir` 每次构建删旧 ⇒ 缓存外壳 = 改版白屏） |
| 证书 | 沿用同一份泛域名（SAN = `*.mynet.club, mynet.club`）⇒ **不需要重新签发**，只需核 `-0001` 目录名 |

### 8.2 上机前两条只读核对

```
ls -d /etc/letsencrypt/live/mynet.club*        # 期望出现 mynet.club-0001/，不是 mynet.club/
ls /run/php/                                    # fastcgi_pass 的 socket 版本要与之一致（本项目要 PHP ^8.4）
dig +short console.mynet.club                   # 见 §8.7：本机无法取证
```

`manage-prod.sh` 的**远端守卫 3** 会自动做第一条（它从 `deploy/nginx.conf` 里解析
`ssl_certificate` 的路径再核存在，所以 conf 改了守卫跟着改，两边不会各说一套）。

### 8.3 ⚠️ SPA 产物必须先构建 —— 否则这次部署会把线上 console 清空

这是本节最容易踩的一条，形状是「**不报错的破坏性动作**」：

- `public/console/` 在 `.gitignore:37` ⇒ 它**不随 git 走**，是构建产物；
- `deploy/.rsyncignore` **没有**排它（排了就没法上线），而 `manage-prod.sh:234` 的 rsync 带 `--delete`
  ⇒ **本地没有 `public/console/` 时执行部署，远端那份会被删掉**；
- `manage-prod.sh` **不会**替你构建它（全文 grep `console` 零命中）。

所以顺序固定是：

```
cd mynet_api/resources/js/console && npm ci && npm run build   # 产出 ../../public/console/
cd mynet_api && ./manage-prod.sh deploy
```

判据：`public/console/index.html` 存在且是新生成的。缺它时的症状是
`SpaController::console()` 回 **503** +「Run: cd resources/js/console && npm run build」
（`app/Http/Controllers/SpaController.php:28-30`）—— 那是症状，不是原因。

> 这条地雷**没有**在 `manage-prod.sh` 里加前置检查拦住 —— 改生产部署脚本的动作面超出「出件」授权，
> 列为待裁（§8.7 第 5 条）。在此之前，把它当 checklist 上第一条硬台阶。

### 8.4 生产 `.env`：console 相关的键（含 G4 时区裁定）

`.env` 在服务器上、不入库、rsync 排除，所以下表是**人工核对清单**，不是本仓文件。
取值口径来自 `.env.example`（同名列已带默认），逐条给「为什么必须这样」。

| 键 | 期望值 | 为什么 / 踩了会怎样 |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | `true` 会把堆栈与配置值直接吐进响应 |
| **`APP_TIMEZONE`** | **`Asia/Shanghai`** | **G4 裁定**。本仓**没有** `config/app.php`（实测 `ls config/` 无此文件），默认走框架的 `UTC`；⇒ 只加这一条 env 即生效，**不要为此新建一份 `config/app.php`** |
| ⚠️ 同一行的硬口径 | —— | **框架自带端点返回的时间字符串不带偏移量**（back 自有端点带），它们要按**同一墙钟**解释。前端 `format.ts` 的 `toDate()` 就是这个收口点。**同页混用两种信封而不按本口径读 ⇒ 显示差 8 小时** —— G4 明确要求把这句写进部署清单，就是这一句 |
| `SANCTUM_STATEFUL_DOMAINS` | 含 `console.mynet.club` | `.env.example:72` 已带；漏了就是「登录成功但 `/auth/user` 恒 401 ⇒ 无限回登录页」（`bootstrap/app.php:55-58` 记的同一次故障） |
| `SESSION_DRIVER` | `database`（现状） | ⇒ 库里**必须有 `sessions` 表**；E1 的 74 张里已含（实测 `grep -c "CREATE TABLE \`sessions\`"` = 1）。改成 `redis` 则 §8.7 第 1 条变成硬阻塞 |
| `SESSION_SAME_SITE` | 保持 `lax` | console 的 POST 走 api 组，而 **api 组没有 `VerifyCsrfToken`** ⇒ 挡 cross-site 的只有这一条（`config/session.php:202`）。改成 `none` 等于只剩浏览器默认值在守 |
| `SESSION_SECURE_COOKIE` | `true` | 全 HTTPS 站点 |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` | 见 §8.7 第 1 条：**Redis 是现役硬依赖，本仓取证不到它在生产是否常驻** |
| `CORS_ALLOWED_ORIGINS` | 含 `https://my.mynet.club`（用户面 GUI） | console **不需要**加进来（同源）；这一条只管跨站的 GUI |
| `APP_URL` | `https://api.mynet.club` | 只影响生成的绝对 URL，不参与 stateful 判定（判定看 `SANCTUM_STATEFUL_DOMAINS`） |

### 8.5 数据库动作：两件，都要 DBA 手工执行

⇒ **现网执行顺序、A 栏只读取证前置、回贴格式**统一在 [`dba-operations-ticket-2026-10.md`](dba-operations-ticket-2026-10.md)
（工单只排顺序，物件正文仍以各自 `.sql` 文件头为准）。对号：**本栏第 2 件 = 工单 B-2**（A5 收紧）；
**本栏第 1 件（建库 DDL）不在工单里** —— 它只服务新建库，现网已有库不跑。工单另有一条 **B-4**
（`2026_10_05_sync_invite_code_infrastructure.sql`：给**存量库**补齐 F-5 那三条框架迁移的等价 DDL），
本栏原先没提它，是因为它当时还只是个缺口（详见 dev-plan F-5 行末的回写）。

⚠️ **生产禁 migration**（§5 那条硬规矩）。上传了迁移文件 ≠ 建了表。

| # | 件 | 何时跑 |
|---|---|---|
| 1 | **建库 DDL** [`mynet_api/deploy/schema/mynet_back-schema.sql`](../../mynet_api/deploy/schema/mynet_back-schema.sql)（E1，74 张） | 只在**新建库**时；现网已有库**不跑**它 |
| 2 | **A5 归属列收紧** [`mynet_api/database/sql/2026_10_04_harden_owner_columns_not_null.sql`](../../mynet_api/database/sql/2026_10_04_harden_owner_columns_not_null.sql) | 先跑同目录的只读清单 `2026_10_04_owner_columns_null_inventory.sql`，按裁定回填完，再跑本件 |

**第 2 件是 2026-10-05 新补的物件**，补的是一处「文档指向空气」：迁移件类注释与 dev-plan A5 行
都写着「现网由 DBA 手工执行**同名的 `database/sql/` 脚本**」，而那个脚本此前**并不存在** ——
DBA 手上只有一份生产跑不了的迁移 + 一份只读清单。它含 `DELIMITER`（守卫用的存储过程），
⇒ **只能用 `mysql` 客户端整份 source**，`artisan`/PDO 吃不下（同 E2 那条约束）。
已在隔离临时 MySQL 8.4.8 上往返验过（塞无主行→被 `45000` 拒且一条 ALTER 没落地、
NULL 没被填 0 →回填后跑通→ `information_schema` 复核 7 列全 `NO`/全 `bigint unsigned`
→ 插 NULL 被 `1048` 挡下），过程中真打出并修掉两处缺陷：`MESSAGE_TEXT` 超 128 字符会换成
1648、守卫中止后过程残留会撞 1304（已加 `DROP ... IF EXISTS`），证据写在该文件头。

⚠️ 两条执行纪律（该文件里有，这里再压一遍）：**严禁 `mysql --force`**（中止语义完全靠客户端
「遇错即停」，带 `--force` 会把 7 条 `ALTER` 一条条执行掉，正是本件要防的半套状态）；
改变列的可空性在 MySQL 8 走 **COPY**（重建表、期间挡写）⇒ 按现网各表行数选窗口。

**硬口径（E1 立的一条，别在上线时忘掉）**：**DDL ≠ 现网** —— 这份 DDL 只保证
「back 启用的 74 张」，现网那 **47 张**框架下游表不参与 mynet，本件既不建也不删它们。

### 8.6 上线后冒烟（三条机器做的 + 一条要人做的）

```
curl -sS https://console.mynet.club/up                                   # 200，进程活着
curl -sSI https://console.mynet.club/console/ | grep -i cache-control    # no-store（§8.1 那条）
curl -sS  https://console.mynet.club/api/v1/console/auth/user            # 未登录 ⇒ **401 JSON**，
                                                                         # 不是 500（`redirectGuestsTo(null)` 的判据）
```

第四条要人做：浏览器登录 console → 侧栏 VPN 组 8 页各点开一次（5 只读页 + 档案/运营成员/邀请码）。
出现「缺少网络区上下文」类 403 说明租户上下文没起来，属**门禁**问题不是前端问题。

⚠️ 第四条**本机做不了**（要生产 Operator 账号），所以「界面已实测」这句话在出件阶段
**不成立**，别在提交说明或工单里替它背书。

### 8.7 未取证 / 待裁（5 条，别读成「已确认」）

1. **Redis 在生产是否常驻** —— `CACHE_STORE=redis` + `QUEUE_CONNECTION=redis` + 心跳 zset
   三处都靠它（`.env.example:53-54`），但本仓**没有**任何生产侧取证。⇒ 这就是 **C-6** 那条待裁；
   没确认之前，「上线 console」与「队列/心跳本来就活着吗」是**同一个未决问题**，别当成两件。
   **2026-10-05 裁定：并入本节未取证项，不另立动作**（本机够不到生产，也不改代码取向）。
   上机时顺手一条即收口：`redis-cli -h 127.0.0.1 ping` + 读现网 `.env` 的
   `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` 三行 —— 回话即把本格改成「已确认」。
2. **`console.mynet.club` 的 DNS 记录** —— 本机取证不了（要公网 `dig`）。
3. **现网真实表清单** —— dev-plan E1 行末就挂着这条：要 DBA 出
   `SELECT table_name FROM information_schema.tables WHERE table_schema='<现网库>'`。
   ⇒ **已排进 DBA 工单**（[`dba-operations-ticket-2026-10.md`](dba-operations-ticket-2026-10.md) 第 1 项），
   本节不再单独跟。
4. **有没有可用的 console Operator 账号** —— 冒烟第四条的前提；且 Operator 面还有
   **F-18**（`show/update/toggle-status/resend-invite` 四条端点无租户条件）与
   **F-16**（三层过滤 ⇒ 被邀请人不在列表）两条**未裁**，上线 console 等于把这些面打开给运营。
5. **`manage-prod.sh` 要不要加 SPA 前置检查**（§8.3 那条 `--delete` 地雷）——
   **2026-10-05 已裁：不加**。改生产部署路径的动作面超出本轮授权，§8.3 的顺序硬台阶
   （先 `npm ci && npm run build` 再 `deploy`）就是唯一防线；这条从「待裁」改记为
   「**已知风险 + 人工台阶**」，别再当作脚本会兜住的事。

## api2 退役（2026-09-26）

| 项 | 状态 |
|---|---|
| 域名 | `api2.mynet.club` → **410 Gone** |
| 应用目录 | `/data/app/mynet.club/api2` 不存在（早已移除）；`api_pre_p3` 为晋升前 api 快照 |
| 老库 `mynet` | **数据保留**，159 表；未 DROP |
| 备份 | `/data/backup/mynet/api2-retire-20260926_010200/mynet.sql.enc`（AES-256-CBC，口令 `/etc/mynet/backup.pass`） |
| 承接者 | `api.mynet.club`（`/data/app/mynet.club/api`，库 `mynet_api`） |
| nginx | 旧站点已移除，仅保留 `api2.mynet.club.retired` 410 块 |

⚠️ MariaDB 不支持 `ALTER DATABASE … READ ONLY`；冻结方式 = nginx 410 + 无应用/无 cron 写入 + 全量备份。
