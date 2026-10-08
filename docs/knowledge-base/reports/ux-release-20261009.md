# 2026-10-09 体验优化发布记录

用户授权：“都发布吧”。本次范围为当前会话的设备创建、连接流程、提示与样式优化。

## 已上线内容

- GUI：https://my.mynet.club/；应用构建 `3a1b53d`，生产代理配置 `5896fe4`。云端资源使用 `/`，本机嵌入使用 `/gui/`。
- API：从 `76e1214` 提取本轮 quickCreate / IP 消耗计算改动，增量应用到生产既有文件。**不是整个 main 的后端部署**。来源、产物校验记录在服务器 `.deployment-ux.json`。
- Agent：`v0.1.1` / `48b84f6`；macOS arm64/amd64、Linux amd64/arm64/mipsel、Windows amd64 共 6 包。native WebUI 与同源 GUI 均进入 embed 二进制。
- CTL：同步已有 `ReleaseDomainService` 产品定义/文件名解析，发布 Agent 包与校验和；清单 https://ctl.mynet.club/d/mynet_agent/manifest.json 。未改表、未运行 migrations。
- 文档：localapi 合同已推送；各仓源码与两个 Agent 版本标签已推送。

## 验证

GUI 38 测试通过；类型棘轮保持 194 个存量错误 / 38 文件（不宣称类型零错误）。API 471 测试、4690 assertions，1 个既有 SQLite 并发 skip；隔离 MySQL 172 测试 / 765 assertions。实际增量 API 文件另跑 11 测试 / 60 assertions。

生产 API 在事务内验证首次池创建、GNB/WireGuard 默认配置与不同 IP，全部测试写入回滚。API `/up` = 200，GUI 代理未登录创建设备 = 401。云端入口资源与本地产物字节一致，登录与创建设备 SPA 路径 = 200。

Agent `make check` 通过；6 平台 embed 构建与校验和通过。实际发布包本机 HTTP 检查 `/ui/experience.css`、`/gui/` 与 GUI JS/CSS = 200。自动更新预演选择 darwin/arm64 包；发布 v0.1.0 后发现旧 updater 未筛平台，补两项回归并发布 v0.1.1；最终稳定版为 v0.1.1。

## 发布时修复与边界

生产 GUI API 代理曾使用 `Host my.mynet.club`，形成同站回环并返回 400。本次改为 `Host api.mynet.club` 与对应 SNI，修正 WebSocket CSP/域名；`nginx -t` 通过后重载，代理恢复 401。

另轮 P4 出口授权专表 `proxy_exit_grants` 在生产尚不存在，所以没有同步那轮 API 功能与数据库变更。本次不改变既有出口授权数据。Gateway relay 白名单本轮仅新增回归测试，未变更策略或重启生产 gateway。

已有设备未被强制升级或重启；更新客户端后新的 localapi/WebUI 生效。此批为压缩分发包，未生成图形化 PKG/DMG/EXE 安装器；依赖驱动按各包 INSTALL.md 安装。本次不宣称真实隧道、出口流量矩阵或 M4/M6 已验收。

## 回滚与产物

服务器备份：`/data/backup/mynet/ux-20261009/`，包括 API 三个旧文件、GUI 静态产物、GUI nginx 配置及 CTL 产品定义。GUI 发布保留旧 hash 资源，避免正在使用的页面因旧 chunk 删除而失败。

API 增量文件 SHA-256：

- NodeService.php：`bc19ef32afab35a705f2f90abcfb74c88b5134fdb56ecfb2daa819d417db7bcb`
- IpPoolDomainService.php：`bfbf6f781885312048d0a29f9d337fe040530813d5b52f4e65f58c1081d96f30`
- NodeController.php：`699982e48dfbea004b9920e22624085a4cc52fee5bcfbc40bc6ca5419fa2cad0`

回滚 API 时恢复这三个文件、重载 PHP-FPM/queue；GUI 恢复备份与 nginx 配置，先 `nginx -t` 再 reload；客户端下载指定旧包，CTL 不强制更新。不得把本次回滚解释为数据库迁移或出口授权变更。
