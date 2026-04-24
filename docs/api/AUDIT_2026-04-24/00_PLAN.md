# API 三方对账审计计划 (2026-04-24)

> **目标**：彻底比对 mynet_gui（前端调用）↔ mynet_back（实际路由）↔ mynet_ctl（OpenAPI SSOT），消除脱节，更新 SSOT。
> **触发原因**：v1→v2 升级后多次出现前端调用 404/405（如 `status-info`、`PUT /deviceinfo`、`/keys/generate`），上一轮修复后仍有遗漏，证明缺乏系统化对账流程。

---

## 任务断点续做约定

每个阶段产物落盘到 `docs/api/AUDIT_2026-04-24/`，文件按序号命名。中断后从最后一个**已生成且完整**的文件继续即可。每完成一个阶段须更新本文件 `## 进度` 章节。

---

## 阶段总览

| # | 阶段 | 输入 | 产物 | 状态 |
|---|------|------|------|------|
| P1 | 提取前端调用 | `mynet_gui/src/services/**`、`composables/**` | `01_gui_calls.md` | ⬜ |
| P2 | 提取后端路由 | `mynet_back/routes/api*.php` + Controller | `02_back_routes.md` | ⬜ |
| P3 | 提取 OpenAPI 端点 | `mynet_ctl/docs/api/openapi/back.yaml` | `03_ctl_endpoints.md` | ⬜ |
| P4 | 三方对账 | 上述三个清单 | `04_diff_matrix.md` | ⬜ |
| P5 | 修复 GUI | `04_diff_matrix.md` | 代码修改 + `05_gui_fixes.md` | ⬜ |
| P6 | 更新 SSOT | `04_diff_matrix.md` | `back.yaml` 更新 + `references/issues.md` 更新 + `06_ctl_updates.md` | ⬜ |
| P7 | 部署验证 | 修改后的 GUI | `07_deploy_log.md` | ⬜ |

---

## 范围界定

**纳入对账**：
- v2 API：`/api/v2/**`（mynet_back）
- HTTP 方法、路径、关键参数（path/query/body 字段名）
- 业务必经端点：节点 CRUD、设备信息、密钥、状态、配置、网络配置、服务、心跳、统计

**暂不纳入**（标注为「OUT-OF-SCOPE-2026-04」）：
- ctl.yaml（CTL 自有端点，本次只验 back）
- 响应体细节（仅核对存在性 + 关键字段）
- v1 兼容路径（保留中，本次只看 v2）
- WebSocket / Reverb 通道
- mynet_lua / mynet_client 调用

---

## 输出格式约定

### `01_gui_calls.md` 表格列
| File | Function | Method | Path (实际拼接后) | Body 关键字段 | 备注 |

### `02_back_routes.md` 表格列
| Method | Path | Controller@Action | Middleware | v1/v2 差异点 |

### `03_ctl_endpoints.md` 表格列
| Method | Path | operationId | Tag | 备注 |

### `04_diff_matrix.md` 三态分类
- ✅ 三方一致
- ⚠️ GUI 调了但 OpenAPI 缺/不一致
- ❌ GUI 调用 ↔ Back 路由不匹配（这是阻塞问题）
- 🟡 Back 有但 GUI/CTL 都未声明（潜在死代码）
- 🔵 OpenAPI 声明但 Back 未实现（文档超前）

---

## 进度追踪

> 每完成一个 P*，把对应行的 ⬜ 改成 ✅ 并附时间戳。

- [x] P1 ✅ 2026-04-24 — 128 端点 → `01_gui_calls.md`
- [x] P2 ✅ 2026-04-24 — 152 v2 路由方法组合 → `02_back_routes.md`
- [x] P3 ✅ 2026-04-24 — 52 OpenAPI 声明 → `03_ctl_endpoints.md`
- [x] P4 ✅ 2026-04-24 — 三方对账：✅40 / ⚠️43 / ❌45 / 🟡69 / 🔵0 → `04_diff_matrix.md`
- [x] P5 ✅ 2026-04-24 — GUI 修复 10 处 v1→v2 命名迁移 → `05_fixes_applied.md`
- [x] P6 ✅ 2026-04-24 — 已更新 `references/issues.md`、`version.json`；OpenAPI schema 增量补全留下一轮
- [ ] P7 ___ 待执行 `npm run build && ./deployment.sh`

---

## 工具脚本路径

为减少人工错误，部分提取通过脚本辅助：
- 后端路由提取：`php artisan route:list --json`（在 mynet_back 中执行）
- 前端调用扫描：`grep -RnE "api(Service)?\.(get|post|put|patch|delete)" src/`
- OpenAPI 解析：`yq '.paths | keys' back.yaml`

---

## 已知历史问题（来自上轮）

| 端点 | 问题 | 已修复? |
|------|------|---------|
| `PUT /nodes/deviceinfo` → `POST` | v2 改方法 | ✅ |
| `PUT /nodes/{id}/deviceinfo` → `PATCH` | v2 改方法 | ✅ |
| `GET /nodes/{id}/status-info` → `/status` | v2 合并 | ✅ |
| `POST /nodes/{id}/keys/generate` → `/keys` | v2 合并 | ✅ |
| `POST /nodes/{id}/keys/upload` → `/keys` | v2 合并 | ✅ |
| `GET /nodes/{id}/network-config` → `/configuration?section=network` | v2 合并 | ✅ |

本次审计需要验证这些已修复项**实际部署后**仍然正确，并发现尚未识别的问题。
