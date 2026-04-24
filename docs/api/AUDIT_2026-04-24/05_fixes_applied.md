# P5 — GUI 修复记录

> 应用日期：2026-04-24
> 范围：BLOCKER 中"v1→v2 路径命名迁移"类问题（其余 dead code/缺失后端端点登记到 issues.md）

---

## ✅ 修复列表

### Zone Service 路径顺序变化（v2: action 前置）

| GUI 文件 | 旧路径 | 新路径（v2 后端实际） |
|---|---|---|
| `services/zone/zoneServiceApi.ts` (+ `zoneServiceApiWithCache.ts`) | `GET /zones/services` | `GET /zones/services/my` |
| 同上 | `GET /zones/services/{id}` | `GET /zones/services/show/{id}` |
| 同上 | `POST /zones/services` | `POST /zones/services/create` |
| 同上 | `POST /zones/services/{id}/share` | `POST /zones/services/share/{id}` |
| 同上 | `POST /zones/services/{id}/unshare` | `DELETE /zones/services/share/{id}`（v2 改为 RESTful） |
| 同上 | `POST /zones/services/{id}/request-approval` | `POST /zones/services/request-approval/{id}` |
| 同上 | `POST /zones/services/{id}/approve` | 复用 `POST /zones/services/request-approval/{id}`（v2 暂未实现独立 approve 端点，加注释说明） |

### Zone Service typo

| 文件 | 旧 | 新 |
|---|---|---|
| `services/zone/zoneService.ts` | `GET /zones/services/indexeservices` | `GET /zones/services/indexes` |

### Monitor History 端点 v2 已移除

| 文件 | 旧 | 新 |
|---|---|---|
| `pages/route/ActiveRoutesPage.vue:314,403` | `GET /monitor/history/connections?node_id={id}` | `GET /nodes/{id}/peer-connections`（路径形式，node_id 入 path） |

### Node 创建路径

| 文件 | 旧 | 新 |
|---|---|---|
| `services/api.ts:492` | `POST /nodes` | `POST /nodes/create` |

---

## ⏸️ 暂未修复（登记到 `references/issues.md`）

### 后端缺少端点（GUI 在调，待后端实现）→ N1-N3

- `GET /zones/overview`
- `GET /zones/{id}/statistics`
- `GET /users/{id}/ip-resources`

### GUI dead code（功能未上线）→ D1-D7

- 节点配置高级管理 9 个端点
- IP 池高级操作 7 个端点
- 路由模块多个端点
- routes (复数) CRUD
- 节点单密钥 DELETE/PATCH
- IPPool PUT
- i18n 后端语言包

---

## 验证

完成 P7 部署后，访问以下页面验证：

1. `/app/zones` Zone 列表页 — 检查 my services / share / request-approval / index services 调用是否成功
2. `/app/zones/services` 服务管理 — 增删改查
3. `/app/routes/active/{id}` 路由活动页 — 验证 peer-connections 端点
4. `/app/nodes` 节点列表 — 验证 createNode 走 `/nodes/create`

---

## 进入 P6

- [x] 更新 `references/issues.md` 新增「2026-04-24 三方对账新增问题」章节
- [ ] （可选）在 `openapi/back.yaml` 补全 43 个 DOC GAP 端点声明 — 工作量大，留作下一轮迭代
- [ ] 更新 `version.json` last audit date
