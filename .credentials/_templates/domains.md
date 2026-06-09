# 域名与 URL 映射清单（模板）

> 从本模板拷贝到 `../domains.md` 后填入真实值。

## 生产域名

| 域名 | 项目 | 用途 | 公网 IP | 内网 IP |
|------|------|------|---------|---------|
| ctl.mynet.club | mynet_ctl | 分发中心 | 52.77.179.176 | 10.182.236.180 |
| api.mynet.club | mynet_back | API 后端 | 同上 | 同上 |
| admin.mynet.club | mynet_back | 管理后端 | 同上 | 同上 |
| my.mynet.club | mynet_gui | Web 前端 | 同上 | 同上 |
| www.mynet.club | mynet_web | 官网（静态） | `<填写>` | `<填写>` |
| download.mynet.club | 遗留 | 旧静态 | `<填写>` | `<填写>` |
| myjarvis.ai | mynet_javis | Jarvis 服务端 | `<填写>` | `<填写>` |
| `*`.myjarvis.ai | OpenResty 动态路由 | 用户子域名 | 同上 | 同上 |

## SSL 证书

| 域名 | 颁发机构 | 到期日 | 续期方式 |
|------|---------|--------|---------|
| `*.mynet.club` | `<填写>` | `<填写>` | `<填写>` |
| `*.myjarvis.ai` | `<填写>` | `<填写>` | `<填写>` |

## CORS 配置（mynet_back）

**当前**（[mynet_back/config/cors.php](../../../mynet_back/config/cors.php)）：

```php
'allowed_origins' => ['*'],  // ⚠️ 生产应改为白名单
```

**建议生产白名单**：

```php
'allowed_origins' => [
    'https://my.mynet.club',
    'https://admin.mynet.club',
    'https://ctl.mynet.club',  // 如需 ctl 调用 back API
],
```

## 开发域名（本地 hosts）

| 域名 | 映射 |
|------|------|
| ctl.mynet.local | 127.0.0.1 |
| api.mynet.local | 127.0.0.1 |
| admin.mynet.local | 127.0.0.1 |
| my.mynet.local | 127.0.0.1 |

`/etc/hosts` 配置示例：
```
127.0.0.1 ctl.mynet.local api.mynet.local admin.mynet.local my.mynet.local
```
