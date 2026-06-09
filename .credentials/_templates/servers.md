# 服务器清单（模板）

> 从本模板拷贝到 `../servers.md` 后填入真实值。

## 生产环境

### CTL 服务（mynet_ctl）
- **域名**：ctl.mynet.club
- **服务器 IP（内网）**：10.182.236.180
- **服务器 IP（公网）**：api.mynet.cluv
- **SSH**：`ssh root@10.182.236.180`（密钥 ~/.ssh/...）
- **部署路径**：`/data/app/mynet.club/ctl`
- **备份路径**：`/data/backup/mynet/ctl`
- **Web 用户**：`<填写>`
- **PHP 版本**：8.2

### Back 服务（mynet_back）
- **域名**：api.mynet.club / admin.mynet.club
- **服务器 IP**：同 CTL（共置）
- **SSH**：`ssh root@api.mynet.club`
- **部署路径**：`/data/app/mynet.club/api`
- **备份路径**：`/data/backup/mynet`
- **Web 用户**：www-data

### GUI 服务（mynet_gui）
- **域名**：my.mynet.club
- **服务器**：同 back 共置
- **部署路径**：`/data/app/mynet.club/my/public`
- **Web 用户**：www
- **构建方式**：本地 `npm run build` → rsync

## 开发环境

### 本地 Nginx
- **Hosts**：`*.mynet.local` → `127.0.0.1`
- **Nginx 配置**：`/opt/homebrew/etc/nginx/servers/`
- **证书**：`/opt/homebrew/etc/nginx/certs/`

### OpenWrt 开发 VM（mynet_lua）
- **SSH 别名**：`openwrt-qemu`
- **LAN IP**：192.168.101.2
- **LuCI**：http://192.168.101.2/cgi-bin/luci/
- **密码**：`<填写>`

## 家庭网络（运维参考）

参见用户级运维笔记。本仓库不维护家用拓扑细节。
