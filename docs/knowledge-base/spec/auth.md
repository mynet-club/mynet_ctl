# SPEC: 认证（Auth）

> 更新：2026-05 | 适用：mynet_lua / mynet_client

---

## OPERATION: auth.login(email, password)

**PRECONDITION:**
- 用户提供 email 和 password
- 已配置 API Server URL

**STEPS:**
1. `POST /api/v2/auth/login` body: `{ email, password }`
2. 成功 → 持久化 `{ token, refresh_token, user_id, email }` 到本地凭证存储
3. 持久化当前 `zone_id`（若响应中包含默认 zone）

**POSTCONDITION:**
- 本地凭证存储有有效 token
- 后续所有 API 请求携带 `Authorization: Bearer {token}`

**ERROR PATHS:**
- `401` → Error("INVALID_CREDENTIALS")，不保存任何凭证
- 网络超时 → Error("NETWORK_ERROR")，不保存任何凭证

**PLATFORM DIVERGENCE:**
- mynet_lua: 凭证存储在 `/etc/mynet/conf/credential.json`
- mynet_client: 凭证存储在 `$MYNET_HOME/conf/credential.json` 或 `~/.config/mynet/credential.json`（sudo 时 chown 回普通用户）

---

## OPERATION: auth.logout()

**STEPS:**
1. `POST /api/v2/auth/logout`（携带当前 token，可以失败，不阻塞后续）
2. 删除本地凭证存储的全部字段（token、refresh_token、user_id、email）
3. 清除内存中的 zone_id 和 node_id 缓存

**POSTCONDITION:**
- 本地无任何认证凭证
- 下次访问跳转至登录页

---

## OPERATION: auth.refreshToken()

**触发时机：** 任何 API 请求收到 `401` 响应时，**自动触发，无需手动处理**。

**STEPS:**
1. `POST /api/v2/auth/token/refresh` body: `{ refresh_token }`
2. 成功 → 用新 token 替换本地存储中的旧 token
3. 重放原始请求（携带新 token）
4. 若 refresh 也返回 `401` → 清除凭证，跳转至登录页

**POSTCONDITION:**
- 无感知刷新，用户不需要重新登录

**PLATFORM DIVERGENCE:**
- mynet_lua: http 请求库层拦截并重试
- mynet_client: Axios 拦截器统一处理（`src/api/client.js`）

---

## OPERATION: auth.verifyToken()

**STEPS:**
1. `GET /api/v2/auth/verify`（携带当前 token）
2. `200` → token 有效
3. `401` → 触发 `auth.refreshToken()`

**用途：** 启动时检查凭证有效性。
