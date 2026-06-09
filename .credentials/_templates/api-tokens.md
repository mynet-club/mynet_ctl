# API Token / 应用密钥清单（模板）

> 从本模板拷贝到 `../api-tokens.md` 后填入真实值。

## Laravel APP_KEY

| 项目 | env key | 生成方式 | 当前值位置 |
|------|---------|----------|------------|
| mynet_ctl | `APP_KEY` | `php artisan key:generate` | `mynet_ctl/.env` |
| mynet_back | `APP_KEY` | 同上 | `mynet_back/.env` |

⚠️ 每个环境必须**独立密钥**，生产 / 开发 / 测试不得共用。

## Sanctum Token（mynet_back）

- **存储表**：`personal_access_tokens`
- **过期**：120 分钟（`config/sanctum.php`）
- **刷新机制**：`/api/v2/auth/refresh-token`
- **格式**：Bearer Token

## CTL 分发 API Token

- **存储表**：`api_tokens`（SHA256 哈希）
- **abilities**：`release:upload` / `release:manage` / `plugin:upload` / `plugin:manage` / `script:publish` / `script:manage` / `*`
- **管理命令**：`<填写：php artisan token:... >`

## Node 心跳签名密钥（GNB Ed25519）

- **类型**：每节点独立 Ed25519 私钥
- **存储**：节点本地 `driver/gnb/conf/{node_id}/security/{node_id}.private`
- **公钥上报**：`PUT /api/v2/nodes/{id}/keys`
- **HMAC-SHA256**：心跳/命令回调用 Ed25519 公钥 hex 作为 HMAC key

## 第三方 webhook / OAuth

| 服务 | env key | 用途 | 1Password ref |
|------|---------|------|---------------|
| Slack Bot | `SLACK_BOT_USER_OAUTH_TOKEN` | 通知 | `<填写>` |
| Postmark | `POSTMARK_TOKEN` | 邮件发送 | `<填写>` |
| Resend | `RESEND_KEY` | 邮件发送 | `<填写>` |
| Pusher / Reverb | `REVERB_APP_KEY` | WebSocket | `<填写>` |
