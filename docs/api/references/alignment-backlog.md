# API 对齐待办清单（Alignment Backlog）

> 更新：2026-09-21 | 来源规划：`integration-plan-v2.md` §3 W3
> **基准（SSOT）**：`../openapi/back.yaml`、`../openapi/ctl.yaml`、`client-matrix.md`
> **偏差来源**：`issues.md`、`../AUDIT_2026-04-24/`
> **本文件是 W3 的唯一执行清单** —— 修复后回填状态，不在别处另开清单。

---

## 0. 复核结果（2026-09-21 对代码实测）

> ⚠️ `issues.md` 是 **2026-04-24** 的，代码已前进：`zone → tenant` 更名、GUI v1→v2 迁移、config-bundle 加固。
> 下表为**逐条对代码复核**的结论，**以本节为准**；§2 原始清单保留作来源追溯。

| ID | 声称问题 | 复核 | 证据 |
|----|----------|------|------|
| B1 | lua 默认配置写死 `/api/v1` | ✅ **已修** | `mynet_openwrt/luasrc`、`mynet_lua/luasrc` 中仅 `api.lua:147` 注释提及 v1；`validator.lua` 无硬编码 |
| B2 | config-bundle 缺 zone 鉴权 | ✅ **已修** | `configBundle()` 以 `$user->tenant_id` + `$user->user_id` 收口（`NodeConfigController.php:912-917`） |
| B3 | router-keys 静默失败 | ✅ **已修** | `collectKeys()` 逐个跟踪失败并产出 `warnings[missing_peer_key]`（`ConfigBundleService.php:146-186`） |
| M3 | 批量设备信息用 `PUT /nodes/deviceinfo` | ✅ **已修** | 路由已是 `POST /nodes/deviceinfo`（`app/Modules/VPN/routes/api.php:84`） |
| M4 | keys generate/upload 分开、走 v1 | 🟡 **部分** | 有 `POST/GET/DELETE /nodes/{id}/keys`（:87-89），**无 PUT 上传**语义 |
| N1 | `GET /zones/overview` 缺失 | 🔴 **仍缺** | 无 `/tenants/overview`（zone 已更名 tenant） |
| N2 | `GET /zones/{id}/statistics` 缺失 | 🔴 **仍缺** | 无 `/tenants/{id}/statistics`；相邻 `GET /users/{id}/statistics` 存在（:189） |
| N3 | `GET /users/{id}/ip-resources` 缺失 | ✅ **已修（异路径）** | 实现为 `GET /ippools/user-resources`（:125）→ **改 GUI 路径**，非 back 补端点 |
| F1-F10 | GUI v1→v2 命名 | ✅ 已修 | `/services/*` 已为扁平动作路径（:133-146） |
| DOC GAP 43 | OpenAPI 未声明 | ⏳ **待复核** | 需逐条比对 `openapi/back.yaml` |
| 孤儿 69 | 零调用端点 | ⏳ **保留** | 其中 `/monitor/node/*`、`/ippools/*` 实为 agent / lua 在用 |

**结论**：原定「P0 修 back 安全漏洞（B2/B3）」**无需动代码，已修**。真正待办收敛为：

1. **doc 侧**：A1-A5（agent/ui 进矩阵与版本表）+ DOC GAP 43 复核
2. **back 侧**：N1/N2（视 GUI 是否仍需要）、M4 的 keys 上传语义
3. **agent 侧**：M1/M5（客户端字段，属 `mynet_agent`）
4. **下一批实现**：W2 driver 接口壳（`mynet_agent`）

---

## 1. 归属重映射（旧线 → 新线）

v1 的 issue 清单按 `lua / client / GUI` 记名，需按目标线重新归属：

| 旧 owner | 新 owner | 说明 |
|----------|----------|------|
| `mynet_lua` 的**业务**问题 | **mynet_agent** | 业务迁入 mynetd；Lua 只留壳 |
| `mynet_lua` 的**壳/渲染**问题 | **mynet_openwrt** | LuCI 菜单/模板/ACL |
| `mynet_client` (Node) | **mynet_agent**（`mynet` CLI） | 产品线退役，CLI 能力在 agent |
| `GUI` | **mynet_gui** | 不变 |
| `back` / `ctl` | **mynet_back** / **mynet_ctl** | 不变 |

---

## 2. 待办清单

### 2.1 BUG（必须修）

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| B2 | **mynet_back** | config-bundle 缺严格 `zone_id` 鉴权（可越 Zone） | 加 Zone 归属校验 | 🔴 P0 | open |
| B3 | **mynet_back** | router-keys 失败静默继续 → 缺 peer 公钥 | 失败上报 + 进 `warnings` | 🔴 P0 | open |
| B1 | **mynet_openwrt** | 默认配置写死 `/api/v1`，应 `/api/v2` | 改默认值（若由 mynetd 下发则归 **agent**） | 🟠 P1 | open |

### 2.2 字段/契约不匹配

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| M1 | **mynet_agent** | router-keys 发空 body `{}` | 按契约发 `{router_node_ids:[int]}` | 🟠 P1 | open |
| M5 | **mynet_agent** | router-keys 期望 `{peerId:"hex"}` 扁平格式 | 适配 back 的 `{keys:[{router_node_id,public_key}]}` | 🟠 P1 | open |
| M2 | **mynet_agent** | keys/upload 发 `custom_public_key` | 确认 v2 是否仍接受该字段名 | 🟡 P2 | open |
| M3 | **mynet_gui** | 批量设备信息用 `PUT /nodes/deviceinfo` | 改 `POST` | 🟠 P1 | open |
| M4 | **mynet_gui** | keys generate/upload 分开调 v1 路径 | 改用 v2 合并语义 | 🟡 P2 | open |

### 2.3 BLOCKER（GUI 在调、back 无路由）

| ID | Owner | 端点 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| N1 | **mynet_back** | `GET /zones/overview` | 实现或从 GUI 移除 | 🟠 P1 | open |
| N2 | **mynet_back** | `GET /zones/{id}/statistics` | 同上 | 🟠 P1 | open |
| N3 | **mynet_back** | `GET /users/{id}/ip-resources` | 同上 | 🟡 P2 | open |

### 2.4 新线新增（文档覆盖缺口）

这些是目标线引入后**矩阵/规范里还没有的**：

| ID | Owner | 问题 | 动作 | 优先级 | 状态 |
|----|-------|------|------|--------|------|
| A1 | mynet_ctl/docs | `client-matrix.md` 无 **agent** 行 | 补 agent 的用户面调用（back `/api/v2/*`） | 🔴 P0 | open |
| A2 | mynet_ctl/docs | `client-matrix.md` 无 **ui** 行 | ui 只调**本机** agent API，不直连 back —— 需在矩阵中明确标注 | 🔴 P0 | open |
| A3 | mynet_ctl/docs | agent 本机 API（`/api/v1`）无规范文件 | 以 `mynet_agent/docs/API.md` 为源，落 `spec/local-api.md` | 🟠 P1 | open |
| A4 | mynet_ctl/docs | `version.json` 无 agent/ui/desktop/openwrt | 补四行（见 W1-2） | 🔴 P0 | open |
| A5 | mynet_ctl/docs | `deployment-registry.md` 无 desktop/openwrt 条目 | 补打包/分发条目 | 🟡 P2 | open |

### 2.5 文档缺口（DOC GAP，back 一致但 OpenAPI 未声明）

43 个端点（`/auth` 8、`/ippools` 7、`/nodes` 4、`/social` 13、`/users` 2、`/zones` 5、`/assets` 1）。
详见 `../AUDIT_2026-04-24/04_diff_matrix.md` §2。

**动作**：增量补 `openapi/back.yaml`，使 OpenAPI 覆盖 GUI 实际调用面。**Owner: mynet_ctl/docs**。

### 2.6 后端孤儿（back 有实现、无客户端调用）

69 个端点（`/notifications/*`、多数 `/users/*`、`/ids/*`、`/monitor/node/*`、`/assets/*`）。

**动作**：**保留**（lua/旧的 monitor 端点仍可能被 agent 使用）；确认废弃后再清理。**Owner: mynet_back**。

### 2.7 GUI dead code（前端有调用、功能未上线）

D1-D7（config 高级管理 9 个、ippool 高级 7 个、route 5 个、keys 管理、i18n 等）。

**动作**：标记 TODO 或清理。**Owner: mynet_gui**，P3 处理。

### 2.8 已修复（留档）

F1-F10：GUI v1→v2 命名迁移，已完成（2026-04-24）。不重复处理。

### 2.9 建议改进

| ID | Owner | 建议 | 优先级 |
|----|-------|------|--------|
| S1 | mynet_agent / openwrt | 弃 4 次独立 API，统一用 config-bundle | 🟡 P2 |
| S2 | mynet_agent | 实现心跳上报（HMAC 签名已有） | 🟠 P1 |
| S3 | mynet_agent | 确认是否需 switch-zone 调用 | 🟡 P2 |
| S4 | 全端 | 启动时按 `version.json` 做 API 版本协商 | 🟠 P1 |

---

## 3. 统计与执行顺序

| Owner | 待办数（不含已修） |
|-------|-------------------|
| mynet_back | B2,B3,N1,N2,N3 + DOC GAP 43 + 孤儿确认 69 |
| mynet_ctl/docs | A1,A2,A3,A4,A5 + DOC GAP 补文档 |
| mynet_agent | B1(可能),M1,M2,M5,S1,S2,S3 |
| mynet_gui | M3,M4,D1-D7 |
| mynet_openwrt | B1(壳侧),A5 |

**执行顺序（P1 起）**：

1. 🔴 **安全优先**：B2（越 Zone 漏洞）
2. 🔴 **本规划 P0**：A1-A4（矩阵/版本表补 agent/ui，属 W1 交付）
3. 🔴 **功能正确**：B3（peer key 缺失）
4. 🟠 **契约统一**：M1,M3,M5 + S4（版本协商）
5. 🟠 **端点补齐**：N1-N3
6. 🟡 **文档完整**：DOC GAP 43 补 OpenAPI、A3 本机 API 规范
7. 🟡 **清理**：D1-D7、孤儿确认
