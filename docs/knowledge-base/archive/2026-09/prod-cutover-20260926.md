# 正式发布切换计划（2026-09-26）

> **目标**：api.mynet.club 成为核心生产面；退役 api2（数据保留）；stage-api 晋升为 api。
> **硬红线（用户三令）**：**先备份 private**——180/181 两台在运节点的私钥必须先备份，
> 因为其他节点暂不会自动跟；**180/181 暂不换 public**；**index 仍在 api.mynet.club，遵守原配置**。
>
> 关联：`deployment-registry.md` · `production-gates-handoff.md` · `key-bundle-refresh.md`

---

## 0. 现状与目标态

| 面 | 现状 | 目标态 |
|---|---|---|
| **api.mynet.club** | 旧/混杂代码 + 库（生产域名） | **正式核心平台**（V2 mynet_api，自 stage-api 晋升） |
| **stage-api.mynet.club** | staging 实例（`/data/app/mynet.club/stage-api`，库 `mynet_back_stage`） | **晋升为 api**；域名/路径/库收敛到生产 |
| **api2.mynet.club** | 老库 `mynet`（tenant `3678250676883169`），已导入 stage | **退役**；数据只读保留 |
| **index** | api.mynet.club 上的 gnb index | **不动**，继续在 api.mynet.club |
| **gnb 节点 180/181/183** | patch 版 gnb 在运，密钥在各机 conf 目录 | **私钥先备份；公钥/身份暂不换** |

### 在运节点（数据面，不可断）

| node_id | VPN IP | 位置 | 角色 |
|---|---|---|---|
| `4997342800601732` | `10.182.236.180` | 生产主机（api 所在机） | 核心 + proxy/index 节点 |
| `3764021915150630` | `10.182.236.181` | 本机 macOS | 在运节点 |
| `8107260975133902` | `10.182.236.183` | Windows | 在运节点 |

---

## 1. 阶段总览

```text
P0 备份私钥（先做，完成前禁止任何密钥/配置变更）
P1 冻结与盘点（api2 / stage / 现 api 的路径·库·nginx）
P2 数据面保护（180/181 公钥不动；index 配置原样锁定）
P3 stage-api → api 晋升（代码/库/域名/APP_URL/gateway）
P4 退役 api2（停服务，数据只读保留）
P5 验收与回滚锚点
```

**原则**：每阶段有独立验收；P0 未完成不得进入 P3；任何阶段失败可停在原地，不「顺手修」。

---

## 2. P0 —— 先备份 private（最高优先级）

### 2.1 为什么必须最先做

1. 180/181 在运，**私钥仍在被使用**；换 public / 重新生成会立刻断隧道。
2. 其他节点**暂不会自动更新**——对端 bundle 里的旧公钥不会自动跟，换钥等于人为制造全网失联（`key-bundle-refresh.md` 的病根）。
3. 备份是唯一「可回退」锚点；没有备份就没有切换资格。

### 2.2 备份对象与路径（上机核实，不靠猜）

**A. 本机（181 · `3764021915150630`）** —— 已在盘上核实：

```text
/usr/local/opt/mynet/driver/gnb/conf/3764021915150630/security/3764021915150630.private   (128B)
/usr/local/opt/mynet/driver/gnb/conf/3764021915150630/security/3764021915150630.public
/usr/local/opt/mynet/driver/gnb/conf/3764021915150630/ed25519/    # 对端公钥（含 180、183）
/usr/local/opt/mynet/driver/gnb/conf/3764021915150630/{node,address,route}.conf
/opt/mynet/driver/gnb/conf/3764021915150630/security/3764021915150630.private            # 副本路径，一并备份
```

**B. 生产主机（180 · `4997342800601732`）** —— 上机后先定位再备份：

```bash
# 在 10.182.236.180 上（勿按进程名杀 gnb）
ls -la /usr/local/opt/mynet/driver/gnb/conf/4997342800601732/security/ 2>/dev/null
ls -la /opt/mynet/driver/gnb/conf/4997342800601732/security/ 2>/dev/null
# 也查 mynet_agent / 自定义 conf 目录
find /data /usr/local /opt /etc/mynet /root -path '*/conf/4997342800601732/*' -name '*.private' 2>/dev/null
```

**C. Windows（183 · `8107260975133902`）** —— 人工上机：

```text
预期形如：...\driver\gnb\conf\8107260975133902\security\8107260975133902.private
（128 hex 私钥 + 64 hex 公钥 + ed25519\ 对端公钥 + node/address/route.conf）
```

**D. 库内私钥（若 `node_keys.private_key` 非空）**

```sql
-- 仅在隔离备份通道执行；结果不得进聊天/日志
SELECT node_id, algorithm, key_version, status,
       LENGTH(private_key) AS priv_len, public_key
FROM node_keys
WHERE node_id IN (4997342800601732, 3764021915150630, 8107260975133902);
-- 私钥本体用 mysqldump/加密备份整表，不 SELECT 明文到终端
```

### 2.3 备份操作规程

| 步 | 动作 | 判据 |
|---|---|---|
| 1 | 打时间戳目录 `/data/backup/mynet/gnb-keys-YYYYMMDD_HHMMSS/`（本机另建对称目录） | 目录存在 |
| 2 | `cp -a` **整个 conf 目录**（不只 `.private`） | `diff -r` 源与备份一致 |
| 3 | 权限收紧：备份目录 `0700`，`.private` `0600` | `stat` 核实 |
| 4 | 打包加密：`tar … \| openssl enc -aes-256-cbc …`，口令走 `crt` / 0600 文件，**不进 argv、不进聊天** | 无明文 tar |
| 5 | 生成清单：node_id、路径、`sha256sum` **私钥与公钥**（哈希可记，明文不可） | 清单文件存在 |
| 6 | 公钥指纹对照：备份的 `.public` 与对端 `ed25519/<id>.public`、库内 `public_key` **三处一致** | 指纹一致 |
| 7 | 异机副本：至少一份落到本机或凭证库支持的密文介质 | 可读回 |

**本机 181 立即可做的备份命令骨架**（不打印私钥内容）：

```bash
STAMP=$(date +%Y%m%d_%H%M%S)
SRC=/usr/local/opt/mynet/driver/gnb/conf/3764021915150630
DST=$HOME/secure-backup/gnb-keys-$STAMP
mkdir -p "$DST" && chmod 700 "$DST"
cp -a "$SRC" "$DST/conf-3764021915150630"
# 副本路径若存在
[ -f /opt/mynet/driver/gnb/conf/3764021915150630/security/3764021915150630.private ] \
  && cp -a /opt/mynet/driver/gnb/conf/3764021915150630 "$DST/conf-3764021915150630-opt"
chmod -R go-rwx "$DST"
sha256sum "$DST"/conf-3764021915150630/security/* > "$DST/CHECKSUMS.txt"
# 加密包（口令从文件读）
tar -C "$DST" -cf - . | openssl enc -aes-256-cbc -pbkdf2 -pass file:/etc/mynet/backup.pass \
  -out "$HOME/secure-backup/gnb-keys-$STAMP.tar.enc"
```

### 2.4 P0 验收

- [ ] 180 私钥已备份 + 校验和 + 加密包（或书面确认路径与指纹）
- [ ] 181 私钥已备份（`/usr/local/opt/...` 与 `/opt/mynet/...` 两路）
- [ ] 183 私钥已备份（Windows 人工）
- [ ] 三节点公钥指纹与对端 `ed25519/*.public` 一致
- [ ] 备份可解密读回（演练一次，不覆盖原件）
- [ ] **P0 完成前未改任何 private / public / node_keys**

---

## 3. P1 —— 冻结与盘点

### 3.1 机上必查清单（10.182.236.180）

```bash
# 路径
ls -la /data/app/mynet.club/          # api / api2 / stage-api / ctl / my ...
# nginx 站点
ls -la /etc/nginx/sites-available/ /etc/nginx/sites-enabled/
grep -n 'server_name\|root ' /etc/nginx/sites-enabled/*
# 库
mysql -e 'SHOW DATABASES;'
# 库表计数（api2 老库、stage、现 api）
# gnb 进程与 conf（禁止 pkill）
ps aux | grep -E '[g]nb|[m]ynet'
ls -la /usr/local/opt/mynet/driver/gnb/conf/ 2>/dev/null
```

### 3.2 盘点表（执行时填写）

| 项 | api.mynet.club（现） | stage-api | api2 |
|---|---|---|---|
| 代码目录 | | `/data/app/mynet.club/stage-api` | `/data/app/mynet.club/api2` |
| 数据库 | | `mynet_back_stage` | `mynet` |
| nginx root | | `.../stage-api/public` | |
| APP_URL | | `https://stage-api.mynet.club` | |
| back_face 密钥 | `/etc/mynet/back_face_*` | `/etc/mynet/stage/back_face_*` | — |
| gateway | 8710 | 8711 | — |
| index 服务 | **api.mynet.club（原配置）** | — | — |

### 3.3 冻结规则

- P1 期间 **不改 gnb 配置、不换密钥、不动 index**。
- api2：**停止写入**（维护页或只读），允许备份导出。
- stage-api：可继续做验证，但**不再往里导入 api2 新数据**（数据面已导入过，见 registry §「api2 老库 → stage 导入」）。

---

## 4. P2 —— 数据面保护（180/181 公钥不动）

| 规则 | 做法 | 原因 |
|---|---|---|
| **暂不换 public** | 禁止 `PUT /nodes/{id}/keys`、禁止 generate/revoke、禁止重写 `ed25519/*.public` | 对端不会自动跟；换钥即断（`key-bundle-refresh.md`） |
| **私钥继续用原文件** | 晋升前后 conf 目录保持同一 inode/内容；rsync 排除 `*.private` 或 `--link-dest` 保护 | 其他节点仍用旧信任锚 |
| **index 原配置** | api.mynet.club 上的 index 监听/地址/密钥**一字不改**；`address.conf` 里 index 条目不改 | 用户明确要求 |
| **gnb 不重启或最小重启** | 晋升 Laravel/nginx **不触碰 gnb 进程**；若必须重启，用 pidfile+cmdline，禁止按名杀 | 运行中隧道 |

**例外（仅故障且用户点头）**：单节点私钥泄露时，走双钥过渡（旧钥签新钥 → 下发 → 再退役），不在本次范围。

---

## 5. P3 —— stage-api → api 晋升

### 5.1 策略（推荐：路径收敛 + 域名切换，库名对齐生产）

> 不推荐「原地改 nginx root 长期指 stage-api」——目录名与域名会长期错位。
> 推荐把 **已验证的 stage 产物晋升到生产路径与域名**，stage 保留为回滚源。

```text
1) 备份现 api 目录 + 现 api 库（完整可回滚）
2) 将 stage-api 代码与配置晋升：
   - 代码：rsync stage-api/ → /data/app/mynet.club/api/（排除 .env 密钥差异合并）
   - 库：mysqldump mynet_back_stage → 生产库名（mynet_api 或沿用已核定名）
     ※ 禁 migration（deployment-registry.md）；只动数据与 .env
3) .env 对齐生产：
   APP_URL=https://api.mynet.club
   DB_DATABASE=（生产库名）
   CORS_ALLOWED_ORIGINS=（my.mynet.club 等完整 origin）
   MYNET_BACK_FACE_*=/etc/mynet/back_face_*   # 生产密钥，不是 /etc/mynet/stage/
   MYNET_GATEWAY_ENABLED=true
   MYNET_GATEWAY_URL=http://127.0.0.1:8710     # 生产 gateway
   REDIS_PREFIX=mynet_database_                # 固定，防 APP_NAME 漂移
4) nginx：api.mynet.club → root /data/app/mynet.club/api/public
   （IPv4+IPv6 listen 齐全；证书 mynet.club-0001；fastcgi php8.4）
5) reload nginx + php-fpm；**不动 gnb**
6) gateway：确认 8710 在跑且指向新 back（先 5 后 4 的次序见 production-gates §4）
7) stage-api 域名：保留只读或 503，供对照/回滚；不对外宣传
```

### 5.2 密钥与隔离注意

| 项 | 要求 |
|---|---|
| **back_face** | 生产与 stage **必须两对**。晋升后 API 用 `/etc/mynet/back_face_*`；stage 密钥不进生产 `.env` |
| **节点密钥** | **不重生成** 180/181/183；bundle 仅因路径/域名变化重发布时，**公钥字段原样** |
| **Redis** | 显式 `REDIS_PREFIX`，避免键「消失」 |
| **队列** | 部署后 restart queue:monitor；确认无堆积 |

### 5.3 客户端/节点指向

| 组件 | 现指向 | 晋升后 |
|---|---|---|
| 节点 mynetd API | 可能是 stage-api 或 api | **统一 `https://api.mynet.club`** |
| GUI / 我的 | `my.mynet.club` 反代 | 保持 same-origin 反代到 api |
| ctl | `MYNET_BACK_API_URL` | `https://api.mynet.club` |
| **index** | api.mynet.club | **不变** |

节点侧若写死 stage-api：只改 **API 基址**，**不改 gnb 密钥与 index**。

### 5.4 P3 验收

- [ ] `https://api.mynet.club/up` 200
- [ ] `POST /api/v2/auth/login` 可用（或现行登录契约）
- [ ] `GET /api/v1/nodes` 非 404（VPN 路由在）
- [ ] 三节点心跳 200 / 在线（Redis 带前缀查询）
- [ ] 181 ↔ 180、181 ↔ 183 隧道 ping 通（`gnb_ctl -s` in/out）
- [ ] index 查询/注册仍走原 address.conf
- [ ] 公钥指纹与 P0 备份一致（**证明没换 public**）

---

## 6. P4 —— 退役 api2（数据保留）

| 步 | 动作 | 保留物 |
|---|---|---|
| 1 | 最终导出：`mynet` 库 dump（加密）+ `/data/app/mynet.club/api2` 打包 | `/data/backup/mynet/api2-retire-*.sql.enc` 等 |
| 2 | nginx：api2 server 改为 `return 410` 或直接删站点文件 | 配置备份在 backup |
| 3 | 停 api2 的 php-fpm pool / cron / queue（若独立） | 进程清单进记录 |
| 4 | **不删** `/data/app/mynet.club/api2` 与库 `mynet`（只读冻结） | 数据在盘 |
| 5 | 文档：`deployment-registry.md` 记退役日期、备份路径、承接者=api | — |

**判据**：api2 域名不再提供业务 API；`mynet` 库可 SELECT（只读）；备份可恢复演练一次。

---

## 7. P5 —— 总验收与回滚

### 7.1 发布检查单

```text
[ ] P0 私钥三机备份完成且校验
[ ] 180/181 公钥未变（对照 P0 指纹）
[ ] index 仍为 api.mynet.club 原配置
[ ] api.mynet.club = V2 业务 API 全量可用
[ ] stage 已晋升或明确只读对照
[ ] api2 已退役且数据保留可证
[ ] 节点隧道三向连通
[ ] GUI 登录 + 节点列表 + 路由
[ ] 队列/Redis/日志无持续报错
[ ] 备份与回滚路径书面记录
```

### 7.2 回滚锚点

| 故障 | 回滚 |
|---|---|
| API 500 / 路由全挂 | nginx root 切回旧 api 目录；或 git/rsync 回上一备份 |
| 库不对 | restore 加密 dump 回原库名 |
| 节点面 401 | 核 back_face 路径/0600；**不要换节点钥** |
| 隧道断 | **停止一切密钥操作**；用 P0 私钥/公钥恢复 conf；对端 bundle 用旧公钥 |
| index 异常 | 恢复原 address.conf / index 进程配置（P2 锁定的原件） |

---

## 8. 任务拆分（执行顺序）

| # | 任务 | 阶段 | 产出 |
|---|---|---|---|
| T1 | 本机 181 私钥/配置备份（两路径） | P0 | 加密包 + CHECKSUMS |
| T2 | 180 主机 conf 备份 | P0 | 加密包 + 路径记录 |
| T3 | 183 Windows 备份 | P0 | 加密包 + 路径记录 |
| T4 | 公钥指纹三端对照 | P0 | 指纹表 |
| T5 | 机上路径/nginx/库盘点 | P1 | 盘点表 |
| T6 | api2 冻结 + 最终导出 | P1/P4 | api2 备份 |
| T7 | stage 代码/库晋升 api | P3 | 生产 api 可用 |
| T8 | .env / gateway / Redis 前缀 | P3 | 配置核对 |
| T9 | 节点 API 基址统一（不动钥） | P3 | 节点仍在线 |
| T10 | api2 退役 | P4 | 410/下线 |
| T11 | 总验收 + 回滚文档 | P5 | 检查单归档 |

---

## 9. 明确不做（本次范围外）

- 不重新生成 180/181/183 的公钥/私钥
- 不改 gnb index 地址与 index 密钥
- 不强制其他节点升级/重拉 bundle（等自动通道就绪）
- 不跑 `artisan migrate` 建生产结构（禁 migration；结构走 DBA/`database/sql`/`gen-schema.sh` 产物）
- 不删 api2 数据与代码目录（只退役服务）

---

## 10. 证据与引用

| 事实 | 来源 |
|---|---|
| api2 → stage 导入结果与库表 | `deployment-registry.md` §api2 老库 → stage 导入 |
| 换钥导致对端 bundle 旧公钥 | `key-bundle-refresh.md` §1 |
| back_priv / 节点面 401 | `production-gates-handoff.md` §1 |
| 生产替换 V2 顺序 | `production-gates-handoff.md` §4 |
| Redis 前缀陷阱 | `production-gates-handoff.md` §5 |
| 本机 181 私钥路径 128B | 实测 `ls`/`wc -c`（2026-09-26） |
| 节点 ID / VIP 对照 | `mynet_openwrt/docs/MacOS_Openwrt_IPSET_Proxy.md:18-24` |
| 禁按名杀进程 | `AGENTS.md` §3 |

---

## 11. 执行记录（2026-09-26）

### 11.1 P0 备份（完成）

| 对象 | 路径 | 说明 |
|---|---|---|
| **180 全量** | `/data/backup/mynet/prod-full-20260925_180455/`（169M） | mynet/gnb + api/api_old/stage-api/ctl/my + nginx + systemd + crontab，含 CHECKSUMS |
| **180 gnb keys** | `/data/backup/mynet/gnb-keys-20260925_174159/` | 整 conf + 私钥 128B + sha256 |
| **181 全量** | `~/secure-backup/prod181-full-20260926_020836/` | mynet-opt + usr-local-opt tgz |
| **181 keys** | `~/secure-backup/gnb-keys-20260926_014228/` | 两路径 private + 对端公钥 |
| **183 新钥** | `C:\gnb-win\backup-keys-20260926\` + `~/secure-backup/.../8107260975133902.*.NEW` | **新生成**，旧库内私钥作废 |

**180/181 自身 public 未变**：
- 180 = `16447c201d0269337402dd0ca8fa200744992b5014d4d95df14d5ff275b82247`
- 181 = `515aa2b3b056fa1ecfe516ba6b86890291741c561f244fb4fb47c18b76241218`

### 11.2 183 Windows 创建（完成）

| 项 | 值 |
|---|---|
| 机器 | `DESKTOP-SQO1919` / `arthur@192.168.0.5` |
| node_id | `8107260975133902` |
| VPN IP | `10.182.236.183` |
| 新 public | `389a27b994c4bcd79a83404662b59a19cd247d581062efdced85816c8e59f2c8` |
| private | **仅本机**（128 hex），**不入 DB**（已把 `node_keys.private_key` 清空） |
| 网关 | `192.168.0.2` → **`192.168.0.1`**（182 旁路由未动） |
| gnb | `C:\gnb-win\`，listen 9016，if-drv wintun，PID 常驻 |
| 防火墙 | 已放行 gnb.exe / UDP 9016/9180/9002 |

**隧道实测**：
- 183 → 180 `10.182.236.180`：**0% 丢包，~90ms** ✅
- 183 → 181 `10.182.236.181`：**0% 丢包，~10ms** ✅
- 180 → 183 / 181 → 183：**不通**（NAT 不对称；180 侧把 183 端点学成 `52.221.22.241:9016` hairpin）
- 180 ↔ 181：Direct，小流量已通 ✅
- 182：**只读检查**，`in 2.7M / out 872K` 稳定，**未做任何变更**

### 11.3 index（只检查，未配置）

- unit：`mynet-index.service`（`gnb -P -l 9016`），**inactive (dead) + disabled**
- 原 address.conf 的 `i|0|52.221.22.241|9016` 等条目**原样保留**
- 当前互通靠 **静态 n| 条目**（183/181 conf 里写了 180/181/183）

### 11.4 自动刷新（部分跑通）

| 步骤 | 结果 |
|---|---|
| 改 183 公钥入库（public only） | ✅ `389a27b9…`，`private_key=NULL` |
| `BundleInvalidator::invalidateAfterKeyChange(8107260975133902)` | ✅ 产出 `generation=rev-20260925181707-8107260975133902-001` |
| 节点侧 `mynet refresh-config` | ❌ 183 上缺 `var/bundle.json`（mynetd 未接 stage-gateway 拉包） |

**含义**：back 侧「换钥 → 重算 bundle → 生成新 generation」**已经通**；
节点侧「自动拉 bundle → refresh-config」依赖 mynetd 连 gateway，183 上尚未接上。

### 11.5 坑（本轮新增）

1. **Windows SSH 里 PowerShell 管道/`Format-Table`/`ForEach-Object` 会被拆坏**——写成 `.ps1` 再 `-File` 执行。
2. **`echo $pub > file` 在 zsh 里可能带换行**，gnb 要求公钥**恰好 64 hex**；65 字节会认不出对端。用 `printf '%s'`。
3. **183 出口公网 = 52.221.22.241（180 自己）**，UDP 到 52.221.22.241:9180 是 hairpin，180 学到的端点是自己；183→180 能通是靠 gnb 已建的会话/中继，180→183 反向不通。
4. **Mac 从 10.182.236.180 SSH 会超时**，从 `52.221.22.241` 正常（VPN 路径 SSH 不稳，公网 IP 可用）。
5. **181 用 `mynet service start` 不存在**（新 CLI）；直接 `sudo gnb -c conf`，且 `gnb.map` 变 root 后要 `sudo gnb_ctl`。

### 11.6 通信路径核实 + gateway 正式域名（2026-09-26 补）

**问：mynet-index dead，183→180 怎么通的？9016 是谁？**

实测结论：

| 位置 | 9016 归属 | 说明 |
|---|---|---|
| **180** | **无进程监听** | `ss -ulnp` 全表无 9016；`mynet-index.service` = inactive/dead |
| **182** | `gnb`（`221.216.138.69:9016`） | 旁路由自己的 gnb 实例，**不是** mynet-index |
| **183** | `gnb`（本机 `:9016`） | 183 自己的 gnb listen |

**183→180 真实路径**（traceroute + tcpdump 双证）：

```
183 (192.168.0.5)
  → 192.168.0.1        （新网关）
  → 192.168.0.2        （182 旁路由，L3 转发）
  → 10.182.236.180     （180 VPN IP）

封装层：182 gnb(221.216.138.69:9016) ↔ 180 gnb(:60932 multi-socket)
```

- **不是走 index**。address.conf 里的 `i|0|52.221.22.241|9016` 是**陈旧条目**——180 上对应服务已不在。
- 183 能通，是靠 **182 旁路由做 L3 转发 + 182↔180 既有 gnb 隧道**。
- 180 把 183 端点学成 `52.221.22.241:9016`（hairpin：183 出口=180 公网 IP），所以 **180→183 直连不通**。

**gateway 正式域名（用户裁定，2026-09-26）**：

> 升级后 **gateway 统一 `gw.mynet.club` 为正式域名**。

| 现状 | 目标 |
|---|---|
| `stage-gw.mynet.club` → 127.0.0.1:8711（staging） | 退役/合并 |
| `gw.mynet.club` **无独立站点**，当前落到 Laravel session（误答 200） | **独立 nginx server block** → gateway :8710 |
| 生产 `deploy/nginx.conf` 已有 gw 段（8710 + WS 升级） | P3 启用 |

P3 晋升时必须：
1. 启用 `gw.mynet.club` 站点（含 `/v1/node/connect` WS 升级、`proxy_read_timeout 3600s`）
2. gateway 监听 **127.0.0.1:8710**（生产口）
3. 节点/agent 的 gateway 地址一律 `https://gw.mynet.club`
4. 移除 gw.mynet.club 对 Laravel 的 default 落点

### 11.7 端口裁定（2026-09-26）

**本地 API 与 gateway 统一用 8096。**

| 组件 | 端口 | 说明 |
|---|---|---|
| mynetd 本地 API / WebUI | **8096** | AGENTS.md §1.5 |
| mynet_gateway | **8096**（原 8710/8711） | 升级后统一 |
| gnb index | 9016 | `mynet-index.service` 已启动 |
| gnb 数据面 | 180:9180 / 181:9002 / 183:9016 | 不变 |

nginx：`gw.mynet.club` → `proxy_pass http://127.0.0.1:8096`

### 11.8 P3 执行记录（2026-09-26）

**已完成：**

| 步 | 动作 | 结果 |
|---|---|---|
| 1 | 180 整目录备份 | `/data/backup/mynet/prod-full-20260925_180455/` |
| 2 | 旧 api → `api_pre_p3` | 可快速回滚 |
| 3 | stage-api 树 → `api/` | 代码晋升完成 |
| 4 | `.env` 对齐生产 | `APP_URL=https://api.mynet.club` · `DB=mynet_api` · gateway `:8096` · `REDIS_PREFIX=mynet_database_` · CORS 白名单 |
| 5 | 生产 `back_face` | `/etc/mynet/back_face_{priv,pub}`，与 stage **两对分离**，0600 www-data |
| 6 | `BackFaceKeys::resolve()` | ✅ yes |
| 7 | `mynet-gateway.service` | `127.0.0.1:8096` → upstream `https://api.mynet.club`，enabled |
| 8 | nginx `gw.mynet.club` | 独立站点 → `:8096`，含 `/v1/node/connect` WS 升级 |
| 9 | `mynet-index.service` | 9016 active（修了 ExecStart 路径 + logs 目录） |
| 10 | 183 隧道 | 183→180/181 仍 0% 丢包 |

**index 修复：** 原 unit 两个 bug——① `ExecStart=/usr/local/mynet/bin/gnb` 不存在（实际在 `driver/gnb/bin/gnb`）② `StandardOutput=append:logs/…` 在 `ExecStartPre mkdir` 之前解析，logs 不存在则 209/STDOUT 连锁失败。已改为先 `mkdir -p` 且路径指到真实二进制。

**8096 裁定：** 本地 API 与 gateway 统一 8096。

**未完（P5 收尾）：**
- [ ] `gw.mynet.club/v1/health` 具体路径待核（根路径 404 属 gateway 正常）
- [ ] 节点 mynetd 指向 `https://gw.mynet.club` 并拉 bundle（自动刷新闭环）
- [ ] api2 nginx 退役（410）
- [ ] 队列进程确认

### 11.9 自动刷新端到端闭环（2026-09-26 修通）

**两处根因：**
1. **WS 404**：`MYNET_GATEWAYS` 写成 `wss://gw.mynet.club`，缺 `/v1/node/connect`。
   `dial_websocket.go:24` 直接把 `Gateways[0]` 当完整 WS URL 拨号。
   正确值：`wss://gw.mynet.club/v1/node/connect`（relay 由 `backclient.go` 同主机推导为 `/v1/relay`）。
2. **bundle/heartbeat 401**：`mynet.conf` 的 `BACK_PUB_HEX` 还是旧值 `70884c4c…`，
   与生产 `/etc/mynet/back_face_pub`（`f3113893…`）不一致 → DH 共享密钥对不上 → 签名校验失败。

**修复后实测（`/var/log/mynet/mynetd.log`）：**
```
transport: connected wss://gw.mynet.club/v1/node/connect as node 1353179228271167
启动拉取 bundle 完成 status=updated
event: bundle_changed → 刷新驱动配置（node.refresh）成功
event: bundle_changed → 重载驱动（service.reload）成功
event: bundle_changed → 装内核路由（route.update）成功
```

**结论：** `bundle_changed → pullBundle → refresh-config → service reload → apply` 全链路已通。
本机 UI `http://127.0.0.1:8096/ui/` 200。

**launchd：** `/Library/LaunchDaemons/com.mynet.mynetd.plist`
- listen `127.0.0.1:8096`
- `MYNET_GATEWAYS=wss://gw.mynet.club/v1/node/connect`

### 11.10 收尾执行（2026-09-26）

**address.conf 裁定（用户）**：只保留 5 行 index，**禁止 `n|` 静态条目**。三台已改回。

| 项 | 状态 |
|---|---|
| A1 api2 导出 | ✅ `mynet.sql.enc` 261M/159 表，AES-256 |
| A2 api2 nginx | ✅ **410** |
| A3 数据保留 | ✅ `mynet` 库未删；无应用/无 cron 写入 |
| A4 registry | ✅ 已记退役 |
| 180↔181 隧道 | ✅ 双向 0% 丢包 |
| 183→180/181 | ✅ 0% 丢包 |
| 180/181→183 | ❌ NAT 不对称（183 在 182 后，index 尚未给出可达端点） |
| index | ✅ 9016 active |
| gateway 8096 + gw.mynet.club | ✅ |
| 自动刷新闭环 | ✅ |
| queue worker | ✅ `mynet-queue.service` |
| 180/181 public 未变 | ✅ |
