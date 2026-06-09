# 各项目 .env 模板索引

各项目自身已有 `.env.example`，本目录不重复维护内容，仅作索引。  
真实生产 `.env` 可在本目录同名（不带 `.example`）保存备份，受 [`.gitignore`](../.gitignore) 保护不入仓。

## 项目 → .env.example 位置

| 项目 | 项目内位置 | 备份文件名（本地） |
|------|----------|------------------|
| mynet_ctl | [mynet_ctl/.env.example](../../../.env.example) | `ctl.env` |
| mynet_back | [mynet_back/.env.example](../../../../mynet_back/.env.example) | `back.env` |
| mynet_gui | （Quasar 不强制 .env，通过 quasar.config.ts 注入） | `gui.env` |
| mynet_client | [mynet_client/conf/config.json](../../../../mynet_client/conf/config.json) | `client.config.json` |
| mynet_lua | OpenWrt 设备上的 `/etc/mynet/conf/config.json` | `lua.config.json` |

## 凭证轮换记录

| 日期 | 项目 | 操作 | 操作人 |
|------|------|------|--------|
| `<填写>` | `<填写>` | `<填写>` | `<填写>` |
