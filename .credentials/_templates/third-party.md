# 第三方服务凭证（模板）

> 从本模板拷贝到 `../third-party.md` 后填入真实值。

## 邮件服务

### 生产：Postmark / Resend / SES（择一）

| 项 | env key | 值 |
|----|---------|----|
| Driver | `MAIL_MAILER` | postmark / resend / ses |
| API Token | `POSTMARK_TOKEN` / `RESEND_KEY` | `<1Password ref>` |
| From | `MAIL_FROM_ADDRESS` | noreply@mynet.club |

### 开发：log driver（不实际发送）

```
MAIL_MAILER=log
```

## 对象存储

| 项 | env key | 值 |
|----|---------|----|
| Driver | `FILESYSTEM_DISK` | s3 / local |
| Access Key | `AWS_ACCESS_KEY_ID` | `<1Password ref>` |
| Secret | `AWS_SECRET_ACCESS_KEY` | `<1Password ref>` |
| Region | `AWS_DEFAULT_REGION` | `<填写>` |
| Bucket | `AWS_BUCKET` | `<填写>` |
| Endpoint | `AWS_ENDPOINT` | `<填写>`（兼容 S3 时） |

## Redis / 队列

参见 [databases.md](./databases.md#redis共享)

## WebSocket（Laravel Reverb）

| 项 | env key | 值 |
|----|---------|----|
| App ID | `REVERB_APP_ID` | `<填写>` |
| App Key | `REVERB_APP_KEY` | `<填写>` |
| App Secret | `REVERB_APP_SECRET` | `<1Password ref>` |
| Host | `REVERB_HOST` | api.mynet.club |
| Port | `REVERB_PORT` | 8080 |
| Scheme | `REVERB_SCHEME` | https |

## DNS / 域名注册

| 域名 | 注册商 | 管理后台 | 凭证 |
|------|--------|---------|------|
| mynet.club | `<填写>` | `<填写>` | `<1Password ref>` |
| myjarvis.ai | `<填写>` | `<填写>` | `<1Password ref>` |
