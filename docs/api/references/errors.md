# 错误格式规范

> Source of Truth: mynet_ctl/docs/api/references/errors.md

---

## mynet_back 错误格式

### 格式 A: 一般错误

```json
{
  "success": false,
  "message": "用户名或密码错误",
  "error_code": "INVALID_CREDENTIALS"
}
```

`error_code` 字段可选，机器可读。

### 格式 B: 验证失败 (422)

```json
{
  "success": false,
  "message": "验证失败",
  "errors": {
    "field_name": ["错误消息1", "错误消息2"]
  }
}
```

`errors` 对象的 key 为字段名，value 为错误消息数组。

### 格式 C: 业务异常 (DomainException)

```json
{
  "success": false,
  "message": "邀请码已过期或无效",
  "error_code": "DOMAIN_EXCEPTION",
  "data": { "context_key": "context_value" }
}
```

`data` 携带错误上下文（如 403 邮箱未验证时含 `resend_url`）。

---

## 已知 error_code 枚举

| error_code | HTTP | 含义 |
|------------|------|------|
| `INVALID_CREDENTIALS` | 401 | 登录凭证错误 |
| `4031` | 403 | 邮箱未验证 |
| `4032` | 403 | 验证链接已过期 |
| `INVALID_REFRESH_TOKEN` | 403 | Refresh Token 无效 |
| `ZONE_ACCESS_DENIED` | 403 | Zone 访问被拒 |
| `DOMAIN_EXCEPTION` | 422 | 业务逻辑异常 |
| `VALIDATION_ERROR` | 422 | 参数校验失败 |

---

## mynet_ctl 错误格式

```json
{
  "error_type": "auth_invalid",
  "message": "Token 无效或已过期，请重新登录"
}
```

> **注意**：CTL 错误格式与 back 不同（`error_type` vs `error_code`，无 `success` 字段）。

### CTL error_type 枚举

| error_type | 含义 |
|------------|------|
| `auth_invalid` | Token 无效/过期 |
| `permission_denied` | 无权限 |
| `not_found` | 资源不存在 |
| `validation_error` | 参数校验失败 |
| `backend_maintenance` | 后端维护中 |
| `backend_error` | 后端异常 |

---

## HTTP 状态码约定

| 码 | 含义 | 使用场景 |
|----|------|----------|
| 200 | OK | GET/PUT/PATCH/DELETE 成功 |
| 201 | Created | POST 创建成功 |
| 304 | Not Modified | config-bundle ETag 匹配 |
| 400 | Bad Request | 请求格式错误 |
| 401 | Unauthorized | Token 无效/过期 |
| 403 | Forbidden | 邮箱未验证、权限不足、Zone 隔离 |
| 404 | Not Found | 资源不存在 |
| 422 | Unprocessable Entity | 业务逻辑验证失败 |
| 429 | Too Many Requests | 频率限制 |
| 500 | Internal Server Error | 服务端错误 |
| 501 | Not Implemented | 功能未实现 |

---

## 数据类型约定

| 标记 | 含义 |
|------|------|
| `int` | 整数 |
| `number` | 浮点数 |
| `string` | 字符串 |
| `boolean` | `true` / `false` |
| `string\|null` | 可空字符串 |
| `[type]` | 数组 |
| `object` | JSON 对象 |
| `string (ISO 8601)` | 时间戳 `2026-04-20T14:30:00+08:00` |
| `string (UUID)` | `550e8400-e29b-41d4-a716-446655440000` |
| `string (hex)` | 十六进制编码 `e9ca457c...` |
