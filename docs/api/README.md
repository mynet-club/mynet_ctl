# MyNet API 文档中心

> API Version: **2.2.0** — back + ctl 共享统一版本号

---

## 文档结构

```
docs/api/
├── version.json                    # API 版本 SSOT
├── README.md                       # 本文件
├── openapi/
│   ├── back.yaml                   # mynet_back OpenAPI 3.1.0
│   └── ctl.yaml                    # mynet_ctl  OpenAPI 3.1.0
└── references/
    ├── middleware.md                # 中间件矩阵
    ├── client-matrix.md            # 客户端匹配 & 调用矩阵
    ├── errors.md                   # 错误格式规范
    └── issues.md                   # 已知问题 & BUG 清单
```

---

## 机器可读规范 (OpenAPI 3.1.0)

| 文件 | 服务 | 域名 | 说明 |
|------|------|------|------|
| [openapi/back.yaml](openapi/back.yaml) | mynet_back | api.mynet.club | 认证、Zone、节点、路由、心跳 |
| [openapi/ctl.yaml](openapi/ctl.yaml) | mynet_ctl | ctl.mynet.club | 分发、检测、Setup 代理 |

可导入 Swagger UI / Redoc / Stoplight 等工具预览。

---

## 人类可读参考

| 文件 | 内容 |
|------|------|
| [references/middleware.md](references/middleware.md) | back + ctl 中间件详解、组合模式、HMAC 签名 |
| [references/client-matrix.md](references/client-matrix.md) | GUI/client/lua 字段匹配、65+ 端点调用矩阵 |
| [references/errors.md](references/errors.md) | 错误格式 A/B/C、error_code 枚举、HTTP 状态码 |
| [references/issues.md](references/issues.md) | 3 BUG + 5 不匹配 + 4 建议改进 |

---

## 版本追踪

[version.json](version.json) 为版本 SSOT：

- `api.version` — 统一 API 版本号（back + ctl 共享）
- `services.mynet_back` / `services.mynet_ctl` — 服务 base_url + OpenAPI 路径
- `clients.*` — 各客户端独立 `app_version` + `min_api_version`

---

## 相关文档

- [知识库完整规范](../knowledge-base/api-specification.md) — 1800+ 行详细规范（本目录各文件的源数据）
- [URL 架构](../url-architecture.md) — CTL 分发 URL 设计
- [架构文档](../architecture.md) — 系统架构总览
- [命名规范](../conventions.md) — 产品/平台/架构命名约定
