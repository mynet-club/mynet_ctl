# 生产闸门交接包（H5 / H6 / V2-8b）

> 2026-09-25 · 本文只做一件事：把**必须人来做**的三步变成「照着跑就行」。
> 关联：`long-range-plan-deep.md` §3（闸门清单）· `cross-project-issues.md` CPI-40/41/42/43
>
> 红线（AGENTS.md §3 + U1 授权）：agent 不碰生产密钥/crt、不改 DNS、不动 GitHub org 设置。
> 所以本文里的每一步都标明**谁跑**、**在哪跑**、**跑完怎么验**。

---

## 0. 先更正两条闸门状态（实测，不是推断）

| 闸门 | 文档原状 | 实测结论 |
|---|---|---|
| **H2** DNS | 待办 | ✅ **已满足**。`*.mynet.club` 是泛解析 → `mynet.club` → `52.221.22.241`；随机子域也解析。api/gw/console/admin 四个都能解析 |
| **H3** 证书 SAN | 待确认 | ✅ **已满足**。线上证书 `SAN = *.mynet.club, mynet.club`，notBefore 2026-09-12、notAfter **2026-12-11**、自动续期；凭证库 `arthur/misc/ssl/CERT_PATH` = `/etc/letsencrypt/live/mynet.club-0001/` |

⚠️ 两处文档待同步：公网 IP 不是 `52.77.179.176` 而是 `52.221.22.241`
（`deployment-registry.md:23`、`long-range-plan-deep.md:61/130/443`）；
证书目录带 `-0001` 后缀（两个 `nginx.conf` 已核正：`mynet_back c28b482`、`mynet_ctl 8d17181`，
且 `manage-prod.sh` 新增守卫 3 会在部署前上机核实路径存在）。

**所以真正还卡着的执行类闸门只剩：H5（建库）· H6（back_priv）· H8（真机）· H11（CI secrets + tag）。**

---

## 1. H6 —— 生成 `back_priv` / `back_pub` 并入凭证库

**为什么它卡住一整条链**：没有这对密钥，`BackFaceKeys::resolve()` 返 null →
`NodeHeartbeatAuthMiddleware` 记 `Log::error` 并 `reject()` → **所有节点面请求 401**
（心跳、命令拉取、结果回报、bundle 拉取全断）。这是 fail-closed，
症状是「节点在线但联系不上 back」，不是「少配了一项」。

### 1.1 工具已备好并双端验证过

`mynet_back/deploy/gen-back-face-keys.sh`（本轮新增）。它的安全约束：

- 私钥**只写进 0600 文件，绝不打印到 stdout**（打印一次就进了终端回滚缓冲/日志/agent 上下文）；
  要核对身份只给 sha256 指纹
- 不覆盖已存在文件，除非显式 `--force`（覆盖 = 让所有已登记节点失联）
- 生成后**自校验**：长度 64、字符集小写 hex、从私钥重新推导公钥并比对、落盘后实测权限是 600
  （`BackFaceKeys` 的权限闸是 `($mode & ~0600) !== 0` 即**硬停且不回退 env**）

**已做的双端验证**（不是「脚本跑通了」，是两个消费方都认可这对密钥）：

| 端 | 验法 | 结果 |
|---|---|---|
| PHP（back 侧，`hex2bin` + `sodium_crypto_scalarmult_base`） | 由私钥推导公钥，与文件里的公钥比对 | ✅ 一致 |
| Go（agent 侧，`keys.GenerateX25519FromPrivateHex`） | 同上 | ✅ 一致 |

### 1.2 你要跑的（建议直接在生产机上，私钥不经过任何中间环节）

```bash
# 1) 生成（默认写 /etc/mynet/，即 config/mynet.php 的默认路径）
cd /data/app/mynet.club/api
sudo bash deploy/gen-back-face-keys.sh /etc/mynet

# 2) 属主改成 Web 进程用户，否则 php 读不到（脚本会打印这一步）
sudo chown <www-user>:<www-group> /etc/mynet/back_face_priv /etc/mynet/back_face_pub

# 3) 生产 .env 指向它们（默认值已是这两个路径，显式写一遍更稳）
MYNET_BACK_FACE_PRIV_FILE=/etc/mynet/back_face_priv
MYNET_BACK_FACE_PUB_FILE=/etc/mynet/back_face_pub

# 4) 导入凭证库备份 —— 在你自己的终端跑，值从文件读，别贴进任何聊天
crt set <条目>.BACK_FACE_PRIV     # 粘 /etc/mynet/back_face_priv 的内容
crt set <条目>.BACK_FACE_PUB      # 公钥可直接用脚本打印的那行
```

> 凭证库目前**没有任何 mynet 条目**（`vault_tree` 实测：只有 arthur/mantou/openclaw/shared
> 四组，无 mynet）。建议新建一个条目（如 `arthur/mynet/back_face`），
> 顺带把生产 DB、ctl 的 release upload token 也归进去 —— 现在这些散在各处。

### 1.3 验（跑完必须验，别只看「文件生成了」）

```bash
php artisan tinker --execute="var_dump(App\Modules\VPN\Domain\Services\BackFaceKeys::resolve() !== null);"
# 期望 bool(true)。若 false，看 storage/logs 里的 [node-face] critical：
#   权限过宽 → 硬停且不回退 env（这是设计，不是 bug）
#   文件为空 → 同样硬停
```
真机验：任一节点心跳应返 **200**，且日志里不再出现 `[node-face] back_face 密钥…`。

---

## 2. H5 —— DBA 建库（**当前被 CPI-40 硬阻塞**）

生产禁 migration（`deployment-registry.md:63`），所以 H5 的交付物是**一份可审的 SQL**，
不是一句「跑 artisan migrate」。

### 2.1 阻塞：全新库上 `migrate` 必失败

在隔离的临时 MySQL 8.4.8 上实测：跑到第 6 个迁移即
`SQLSTATE[42S01] 1050 Table 'tenant_settings' already exists`。

原因是**我方迁移与框架模块迁移建同一张表**，撞车 5 张：
`tenants` · `tenant_users` · `users` · `system_settings` · `tenant_settings`。
框架侧用**裸 `CREATE TABLE`** 写在 `vendor/dsplat/*/database/migrations/` 里，
所以 grep `Schema::create` 找不到它们 —— 这也是它长期没被发现的原因。

> 为什么一直没暴露：本地用 sqlite 且库是既有的；phpunit 走测试库；生产禁 migration。
> 只有「从零建一个 MySQL 库」会撞上，而 H5 恰恰就是它。
> **所以 B-1 的验收判据「新库可从零建」实测不成立。**

### 2.2 需要你先裁定（agent 不替人决定归属）

vendor 只读（AGENTS.md §1.8），所以只能动我方那 5 个迁移。两条路：

| 选项 | 做法 | 代价 |
|---|---|---|
| **A（推荐）** | 删我方那 5 个 create 迁移，表由框架模块建 | **删之前必须逐列比对**两边定义 —— 我方可能加了业务列，直接删会静默丢字段 |
| **B** | 保留我方定义，改为 `Schema::hasTable` 守卫 + 用 `Schema::table` 补差异列 | 迁移变复杂，但字段归属清晰；框架升级改了定义时会静默分叉 |

比对命令（A 的前置）：
```bash
cd mynet_back
# 框架侧建表名
grep -rhoE "CREATE TABLE [\`']?[a-z_]+" vendor/dsplat/*/database/migrations/*.php | sort -u > /tmp/fw.txt
# 我方建表名
for f in database/migrations/*.php; do grep -oE "Schema::create\('[a-z_]+'" "$f"; done | sort -u > /tmp/our.txt
comm -12 /tmp/fw.txt /tmp/our.txt          # 撞车的表
```

### 2.3 裁定后：一条命令产出 DDL

```bash
cd mynet_back
bash deploy/gen-schema.sh                      # → deploy/schema/mynet_back-schema.sql
```

`gen-schema.sh`（本轮新增）做的事：起**隔离的临时 MySQL**（自己的 datadir/端口/socket，
`--initialize-insecure` 空口令 root，不碰任何既有库）→ 建库 → 真跑 migration →
`mysqldump --no-data --routines --triggers --events` → **逐表断言** → 按 pidfile 停实例。

逐表断言是必要的：只验「mysqldump 退出码 0」不够 —— 少跑一个迁移、或某模块的
ServiceProvider 没注册，dump 照样成功，只是少了表，症状要到上线才出现。
断言覆盖 16 张关键表（含 `nodes`/`node_keys`/`node_route_requests`/`friendships`/
`services`/`node_monitor_snapshots`），外加 `node_keys` 的
`algorithm`/`key_version`/`status` 三列（B-2 要求、C-2 按 `algorithm` 分支）。

撞车时它会**点名撞车表**并打印两侧建表名的取法，而不是只丢一个 QueryException。

交给 DBA 时一并说明：本机是 MySQL 8.4.8 导出、生产是 MySQL 8，需复核方言差异；
不含数据；字符集 utf8mb4 / utf8mb4_unicode_ci。

---

## 3. V2-8b —— agent 产物上传 ctl（G4「可升级」的最后一环）

### 3.1 现状（实测）

- ✅ `make dist` 可用：5 平台 × 3 二进制 + `checksums.txt`，
  产物名 `mynet_agent_{platform}_{arch}_{version}.tgz`（本轮实跑过）
- ✅ ctl 代码侧 `PRODUCTS` 已含 `mynet_agent`（V2-1）
- ❌ **生产 `https://ctl.mynet.club/d/apps.json` 的 products 只有
  `client / gnb / proxy / mynet / utc / lua`，没有 `mynet_agent`** ——
  即生产 ctl 未部署 V2-1 的改动，且从未上传过 agent 产物。
  于是 `mynet update` 在生产**无版本可拉**；而那个叫 `mynet` 的产品是
  **已废弃的 TUI**（latest v1.4.0），V2-8a 的负路径测试专门验过「旧 TUI 不被选中」
- ❌ CI 未建（`mynet_agent` 无 `.github/`；`mynet_openwrt` 有 workflow 但无 remote、无 tag）→ 需 H11

### 3.2 上传契约（从代码读出，不是猜的）

`POST /api/v2/releases`，中间件 `auth.token:release:upload`，multipart 字段
（`ReleaseController.php:60-69`）：

| 字段 | 必填 | 说明 |
|---|---|---|
| `product` | ✅ | `mynet_agent` |
| `version` | ✅ | 如 `v0.1.0` |
| `platform` | ✅ | `darwin` / `linux` |
| `arch` | ✅ | `amd64` / `arm64` / `mipsel`（⚠️ Go 侧叫 `mipsle`，ctl 侧叫 `mipsel`） |
| `variant` | | 可空 |
| `changelog` | | 可空 |
| `is_stable` | | 可空布尔 |
| `file` | ✅ | ≤ 100MB |

服务端上传即算 sha256 并在 manifest 输出 `artifacts[].sha256`，客户端 `mynet update`
会校验、不符即拒且不落 `.bak`（V2-6 已验）。

### 3.3 工具与跑法

`mynet_agent/scripts/release/upload-to-ctl.sh`（本轮新增）：从 dist 文件名解析
product/version/platform/arch，逐个 POST；token 只从环境取，**不接受命令行传参**
（会进 `ps` 与 shell history）。默认 `--dry-run`，打印将要发的每一条请求。

```bash
cd mynet_agent
make dist                                        # 出 5 平台产物 + checksums.txt
sh scripts/release/upload-to-ctl.sh --dry-run    # 先看要发什么（不需要 token）

# 真上传（token 走 crt，别贴进聊天）
crt use <ctl release token 条目> -- \
  CTL_UPLOAD_TOKEN="$CTL_RELEASE_UPLOAD_TOKEN" \
  sh scripts/release/upload-to-ctl.sh
```

⚠️ **版本号**：dist 现在出的是 `v0.0.0-<sha>`（无 tag）。要发正式版得先打 tag（H11），
否则 ctl 上会堆一堆 `v0.0.0-*`，而 `mynet update` 的语义比较会把它们当同一版。

---

## 4. 把生产 back 换成 V2 的顺序（CPI-41）

实测：生产 `api.mynet.club` 只有框架 auth 面，VPN/Monitor/Social 路由全 404。
建议顺序（每步都可独立验证，失败可回滚）：

1. **H6**（§1）—— 不依赖任何其它步，且不做则第 4 步之后节点面全 401
2. **H5**（§2）—— 需先裁定 CPI-40 的表归属
3. 部署代码：`./manage-prod.sh deploy`
   （远端守卫会依次核 APP_KEY、back_priv 0600、**证书目录存在**、**gateway 总闸**）
4. 生产 `.env` 打开 `MYNET_GATEWAY_ENABLED=true` + `MYNET_GATEWAY_URL`（CPI-42）
   —— 但**先确认 gateway 真在跑**，否则通知会静默失败（best-effort，不阻断）
5. 部署 gateway：`mynet_agent/deploy/systemd/mynet_gateway.service`（V3-3a 已本地实测过参数）
6. 验：`curl https://api.mynet.club/api/v1/nodes` 不再是 404；
   真机注册一台走完「注册→密钥→连接→互通」（V3-8 / T3-4）

> 第 4、5 步的顺序有讲究：先开总闸再部署 gateway，会让期间的每次入队都尝试通知一个
> 不存在的上游（best-effort 静默降级，只是日志噪音）；反过来则 gateway 起来了但没人通知它。
> 两者都不致命，但**先 5 后 4** 更干净。

---

## 5. 运维陷阱：Redis 键名带 `APP_NAME` 派生的前缀

`config/database.php` 的 Redis 选项里有

```php
'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
```

所以**键名不是裸的 `node:status:<id>`**，而是 `<slug(APP_NAME)>_database_node:status:<id>`。
staging 上 `APP_NAME=MyNetBackStage` → 前缀 `mynetbackstage_database_`；
生产 api 的 `APP_NAME` 不同，前缀也就不同。

**后果**：任何按裸键名扫描的运维脚本 / 监控 / 排障命令，在换了 `APP_NAME` 的环境上
会**静默扫不到东西** —— `redis-cli --scan --pattern 'node:*'` 返回 0 个键，
看起来像「心跳没写进 Redis」，实际写得好好的。本轮就误判过一次
（V4-2 的验证记录里写的是裸键名，那是本地环境的形状）。

**正确做法**（三选一）：

```bash
# 1) 从应用侧问，前缀由框架自己加/去
php -r '…bootstrap…; print_r(Illuminate\Support\Facades\Redis::keys("node:status:*"));'

# 2) 先取前缀再拼
P=$(php artisan tinker --execute='echo config("database.redis.options.prefix");')
redis-cli -n 1 --scan --pattern "${P}node:status:*"

# 3) 显式固定 REDIS_PREFIX（推荐用于生产：让键名不随 APP_NAME 漂移）
#    .env: REDIS_PREFIX=mynet_database_
```

⚠️ 建议生产**显式设 `REDIS_PREFIX`**：否则改一次 `APP_NAME`（比如 branding 调整）
就会让既有键全部「消失」—— 数据还在，但按旧前缀写、按新前缀读，
表现为「节点全部离线」，而心跳其实一直在写。

**staging 实测的 4 个键**（`REDIS_DB=1`）：

| 键（去前缀） | 类型 | 内容 | TTL |
|---|---|---|---|
| `node:status:<id>` | hash | `status=online` · `last_heartbeat` · `updated_at` | 1032s |
| `node:status:all` | zset | `<id> → last_heartbeat` 时间戳 | 无 |
| `node:<id>:monitor` | string | `is_running` / `connected_peers` / `uptime_seconds` / `rx_bytes` … | 1632s |
| `node:vpn_interfaces:<id>` | string | 接口列表（type / ifname / status） | 1032s |

