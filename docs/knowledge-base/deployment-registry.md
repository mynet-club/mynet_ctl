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
| **mynet_back** | Laravel 12 | api.mynet.club（API）<br>**console.mynet.club**（租户管理） | `/data/app/mynet.club/api` | `cd mynet_back && ./manage-prod.sh deploy` |
| **mynet_gui** | Vue 3 / Quasar | my.mynet.club | `/data/app/mynet.club/my/public` | `cd mynet_gui && bash deployment.sh` |
| **mynet_web** | 静态站点 | www.mynet.club | ⚠️ **未建立**（无部署路径记录） | ⚠️ **无部署脚本** |

服务器 **`10.182.236.180`**（公网 `52.77.179.176`）。ctl / back / gui 三者**共置同机、
各是独立 nginx server block**；证书在 `/etc/letsencrypt/live/mynet.club/`，
**主机 nginx 终结 TLS**（H1：不引入 Caddy）。

> **子域命名（H4 裁定，2026-09-24）**：`console.mynet.club` = back 的**租户管理**面；
> `admin.mynet.club` 让给框架的 platform/operator SPA（**不是** back 的管理面）；
> `adm` 弃用。本文档、`ecosystem.md`、`brand-guide.md`、`projects/mynet_back.md`
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
| api.mynet.club | mynet_back（API） | prod |
| console.mynet.club | mynet_back（租户管理） | prod |
| my.mynet.club | mynet_gui | prod |
| www.mynet.club | mynet_web | prod |
| gw.mynet.club | mynet_gateway（节点面入口） | prod |
| admin.mynet.club | 框架 platform/operator SPA（**非** back） | prod |

---

## 3. 部署 / 打包脚本入口

逐条 `ls` 核实过（2026-09-24）。**不存在的已删除**，不再留空指路。

| 用途 | 路径 | 说明 |
|------|------|------|
| CTL 部署 | [mynet_ctl/scripts/deploy/deploy.sh](../../scripts/deploy/deploy.sh) | rsync + 远程 composer + **migrate** + 缓存。ctl 自己允许 migrate（「禁止 migration」只约束 mynet_back） |
| Back 部署 | [mynet_back/manage-prod.sh](../../../mynet_back/manage-prod.sh) | 远端守卫 → 备份 → rsync → composer → 权限 → 重载 FPM → 健康检查。**不跑 migrate** |
| Back 备份 / 恢复 | [mynet_back/deploy/](../../../mynet_back/deploy/) | `backup.sh` / `restore.sh` / `backup-drill.sh` / `install-cron.sh`（V3-5） |
| GUI 部署 | [mynet_gui/deployment.sh](../../../mynet_gui/deployment.sh) | 本地 build + rsync `dist/` |
| 客户端打包 | [mynet_agent/Makefile](../../../mynet_agent/Makefile) 的 `dist` 目标 | 5 平台 × 3 二进制 + checksums |
| OpenWrt 打包 | [mynet_openwrt/Makefile](../../../mynet_openwrt/Makefile) | ipk |
| gnb 导入 | [mynet_ctl/scripts/import_gnb_v1.6.4.sh](../../scripts/import_gnb_v1.6.4.sh) | 从 GitHub 拉 gnb release → `artisan release:import-gnb` |

**已删除的入口**（曾在本文件里，但盘上已无对应物）：

- `mynet_back/scripts/deployment/deploy-unified.sh` —— `mynet_back/scripts/` 整个目录已不存在，`manage-prod.sh` 取代它
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
- [ ] 数据库变更评估（mynet_back **禁止 migration**，需 DBA 走 `database/sql/`）
- [ ] 凭证（`.env`）已同步到生产
- [ ] `back_priv` 文件权限 **0600**（`manage-prod.sh` 的远端守卫会拦；H7 裁定见 `spec/node-keying.md` §4.6）
- [ ] 队列服务（mynet_back）会自动 restart
- [ ] 部署后健康检查（关键 URL 200 OK）

---

## 6. 回滚预案

| 项目 | 回滚方式 |
|------|---------|
| Laravel（ctl / back） | `manage-prod.sh rollback`，或 `git reset --hard <prev_commit>` 后重跑部署脚本 |
| GUI | rsync 备份的旧 `dist/` 目录回原位置 |
| 客户端 / OpenWrt | 用户保持旧版本；CTL 不强制更新 |
| 数据库 | **无自动回滚**。备份见 `mynet_back/deploy/backup.sh`（V3-5），恢复用 `restore.sh`——但那是「恢复到某个时点」，不是回滚一次迁移 |

---

## 7. 监控与告警

> ⚠️ 本节此前三处写 `<待补>`。`<待补>` 的问题是它读起来像「已经有了、只是没填」，
> 而事实是**大部分还没建立**。V3-7 改为如实描述。

| 项 | 现状 |
|----|------|
| 服务可用性 | ⚠️ **未建立**。`manage-prod.sh` 只在部署结束时做一次健康检查，没有常驻监控 |
| 错误日志 | 有：Laravel log（[mynet_back/config/logging.php](../../../mynet_back/config/logging.php) 存在）。**Slack webhook 未接** |
| 队列堆积 | ⚠️ **未建立**。本文件旧版写的 `scripts/service/queue-monitor-daemon.sh` **在盘上不存在** |
| 心跳异常 | ⚠️ **无告警**。back 侧有 `monitor/heartbeat` → Redis（全节点 zset + 离线扫描）这条链，但没有任何提醒出口；节点侧指标采集本身也不完整（`long-range-plan-deep.md` §1「可观测性」） |

**结论**：监控面整体属**未建立**状态，是 V4（规模化与可观测）的内容，不要按本表
以为生产已有监控。
