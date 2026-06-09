# MyNet 部署清单（Deployment Registry）

> **地位**：本文件是「**部署元数据**」的 SSOT，回答"项目部署到哪里、用什么命令、谁可以登录"。  
> **真实凭证**：见 [`mynet_ctl/.credentials/`](../../.credentials/)（不入仓）。

---

## 1. 项目部署矩阵

| 项目 | 类型 | 生产域名 | 服务器 | 部署路径 | 部署命令 |
|------|------|---------|--------|---------|---------|
| **mynet_ctl** | Laravel 12 | ctl.mynet.club | 10.182.236.180（公网 52.77.179.176） | `/data/app/mynet.club/ctl` | `cd mynet_ctl && bash scripts/deploy/deploy.sh` |
| **mynet_back** | Laravel 12 | api.mynet.club / admin.mynet.club | 同上 | `/data/app/mynet.club/api` | `cd mynet_back && ./manage-prod.sh deploy` |
| **mynet_gui** | Vue 3 / Quasar | my.mynet.club | 同上 | `/data/app/mynet.club/my/public` | `cd mynet_gui && bash deployment.sh` |
| **mynet_web** | 静态/CMS | www.mynet.club | `<待补>` | `<待补>` | `<待补>` |
| **mynet_client** | Node.js 18 | （不部署到服务器，分发到用户机） | — | 用户的 `$MYNET_HOME` | `bash pack.sh` 上传 CTL |
| **mynet_lua** | LuCI / Lua | （不部署到服务器） | — | OpenWrt `/usr/lib/lua/luci/...` | `make package/luci-app-mynet/compile` → `.ipk` 上传 CTL |
| **mynet_javis** | Laravel 12 | myjarvis.ai | `<待补>` | `<待补>` | `<待补>` |

> **共置说明**：mynet_ctl / mynet_back / mynet_gui 三者部署在同一台服务器（10.182.236.180），但分别独立的 Web Server 站点（不同 Nginx server block）。

---

## 2. 域名→项目映射

参见 [`.credentials/_templates/domains.md`](../../.credentials/_templates/domains.md)。

---

## 3. 部署脚本入口

| 脚本 | 路径 | 说明 |
|------|------|------|
| CTL 部署 | [mynet_ctl/scripts/deploy/deploy.sh](../../scripts/deploy/deploy.sh) | rsync + 远程 artisan migrate |
| Back 部署（推荐） | [mynet_back/manage-prod.sh](../../../mynet_back/manage-prod.sh) | 整合部署 + 服务管理 + 队列 |
| Back 部署（旧） | mynet_back/scripts/deployment/deploy-unified.sh | 已废弃，使用 manage-prod.sh |
| GUI 部署 | [mynet_gui/deployment.sh](../../../mynet_gui/deployment.sh) | 本地 build + rsync dist/ |
| 客户端打包 | [mynet_client/pack.sh](../../../mynet_client/pack.sh) | esbuild 产出 → 上传 CTL |
| OpenWrt 打包 | [mynet_lua/Makefile](../../../mynet_lua/Makefile) | OpenWrt SDK 构建 .ipk |
| OpenWrt VM 同步 | [mynet_lua/debug/sync.sh](../../../mynet_lua/debug/sync.sh) | 开发用：rsync 到 qemu VM |

---

## 4. 凭证目录入口

| 类别 | 模板 | 实际位置（不入仓） |
|------|------|------------------|
| 服务器 SSH / 路径 | [_templates/servers.md](../../.credentials/_templates/servers.md) | `.credentials/servers.md` |
| 数据库 | [_templates/databases.md](../../.credentials/_templates/databases.md) | `.credentials/databases.md` |
| API Token / APP_KEY | [_templates/api-tokens.md](../../.credentials/_templates/api-tokens.md) | `.credentials/api-tokens.md` |
| 第三方服务（邮件 / S3 / Reverb） | [_templates/third-party.md](../../.credentials/_templates/third-party.md) | `.credentials/third-party.md` |
| 域名 / SSL / CORS | [_templates/domains.md](../../.credentials/_templates/domains.md) | `.credentials/domains.md` |

---

## 5. 部署前置检查

每次生产部署前必须确认：

- [ ] 本地代码已提交并推送
- [ ] `composer install --no-dev --optimize-autoloader`（Laravel）
- [ ] `npm run build`（前端）
- [ ] 数据库变更评估（mynet_back **禁止 migration**，需 DBA 走 `database/sql/`）
- [ ] 凭证（`.env`）已同步到生产
- [ ] 队列服务（mynet_back）会自动 restart
- [ ] 部署后健康检查（关键 URL 200 OK）

---

## 6. 回滚预案

| 项目 | 回滚方式 |
|------|---------|
| Laravel（ctl/back/javis） | `git reset --hard <prev_commit> && deploy 脚本重跑` |
| GUI | `rsync` 备份的旧 dist 目录回原位置 |
| Client | 用户保持旧版本；CTL 不强制更新 |
| OpenWrt | 用户保持旧 .ipk；CTL 不强制更新 |

---

## 7. 监控与告警

| 项 | 工具 | 配置位置 |
|----|------|---------|
| 服务可用性 | `<待补>` | `<待补>` |
| 错误日志 | Laravel log + Slack webhook | `mynet_back/config/logging.php` |
| 队列堆积 | `queue:monitor:server` 守护进程 | `scripts/service/queue-monitor-daemon.sh` |
| 心跳异常 | `<待补>` | `<待补>` |
