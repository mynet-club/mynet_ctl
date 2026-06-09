# SPEC: 安装向导（Wizard）

> 更新：2026-05 | 适用：mynet_lua / mynet_client

---

## OPERATION: wizard.init()

首次访问或配置不完整时触发。

**STEPS:**
1. 检查本地是否有凭证（credential.json 中 token 存在且非空）
2. 无凭证 → 跳转 login 页面
3. 有凭证，检查当前模式：
   - `mode == "guest"` → 跳转 guest 页面
   - `mode == "mynet"` → 检查配置完整性（参见 `config.isComplete()`）
     - 完整（有 node_id + 对应配置文件）→ 跳转节点管理页
     - 不完整 → 进入 zone 选择流程

---

## OPERATION: wizard.selectZone()

**PRECONDITION:**
- 用户已登录（有效 token）

**STEPS:**
1. `GET /api/v2/zones` → 获取可用 Zone 列表
2. 若只有 1 个 Zone → 自动选择（无需用户操作）
3. 若多个 → 展示列表，等待用户选择
4. 持久化选中的 `zone_id` 到本地配置
5. 后续所有请求携带 `X-Zone-ID: {zone_id}` header

**POSTCONDITION:**
- 本地配置有 `zone_id`
- 进入节点选择流程

---

## OPERATION: wizard.selectNode(node_id)

**PRECONDITION:**
- 有有效 `zone_id`

**STEPS:**
1. `GET /api/v2/zones/{zone_id}/nodes` → 获取节点列表
2. 用户选择目标节点，确认
3. 调用 `config.generateMynetConf(node_id)` 生成本地运行时配置
4. 调用 `configSync.fetchBundle(node_id)`（参见 wizard.fetchConfigBundle）
5. 持久化 `node_id` 到本地配置
6. 跳转节点管理页

**POSTCONDITION:**
- `mynet.conf` 已生成
- GNB 配置目录 `driver/gnb/conf/{node_id}/` 已完整写入

**ERROR PATHS:**
- bundle 拉取失败 → 回滚（删除已写入的配置文件），Error("CONFIG_BUNDLE_FAILED")

---

## OPERATION: configSync.fetchBundle(node_id)

**设计原则：** 单次请求获取全部配置，禁止多次分散请求。

**STEPS:**
1. `GET /api/v2/nodes/{node_id}/config-bundle`
   - Header: `Authorization: Bearer {token}`, `X-Zone-ID: {zone_id}`
2. 将响应写入本地文件（原子写入，失败则回滚）：

```
response.files["node.conf"]        → driver/gnb/conf/{node_id}/node.conf
response.files["address.conf"]     → driver/gnb/conf/{node_id}/address.conf
response.files["route.conf"]       → driver/gnb/conf/{node_id}/route.conf
response.keys.ed25519[{peer_id}]   → driver/gnb/conf/{node_id}/ed25519/{peer_id}.public
response.keys.security.private     → driver/gnb/conf/{node_id}/security/{node_id}.private
response.keys.security.public      → driver/gnb/conf/{node_id}/security/{node_id}.public
```

3. 验证关键文件存在且非空
4. 记录 `config_version`（响应中的 `version` 字段）到本地配置

**POSTCONDITION:**
- GNB 配置目录结构完整，可以启动服务

**ERROR PATHS:**
- `404` → 节点不存在或不属于当前 Zone，Error("NODE_NOT_FOUND")
- 文件写入失败 → 全部回滚，Error("CONFIG_WRITE_FAILED")

**PLATFORM DIVERGENCE:**
- mynet_lua: 配置目录 `/etc/mynet/driver/gnb/conf/{node_id}/`
- mynet_client: 配置目录 `$MYNET_HOME/driver/gnb/conf/{node_id}/`

---

## OPERATION: config.generateMynetConf(node_id)

生成 `mynet.conf`（Shell KEY=VALUE 格式，供脚本 source 使用）。

**STEPS:**
1. 从节点信息和本地配置组合以下字段：
   - `NODE_ID`、`ZONE_ID`、`VPN_IF`（默认 `gnb_tun`）
   - `GNB_BIN`（指向 driver/gnb/bin/gnb 路径）
   - `CONF_DIR`（指向 driver/gnb/conf/{node_id}/ 路径）
2. 写入 `$MYNET_HOME/conf/mynet.conf`（或 `/etc/mynet/conf/mynet.conf`）

**POSTCONDITION:**
- `mynet.conf` 存在，可被脚本 source

---

## OPERATION: wizard.switchNode(new_node_id)

节点切换（从已运行状态切换到另一个节点）。

**STEPS:**
1. 若服务正在运行 → 停止服务（`service.stop()`）
2. 执行 `wizard.selectNode(new_node_id)` 完整流程
3. 若之前服务在运行 → 启动服务（`service.start(new_node_id)`）

**注意：** 切换节点后，GNB route.conf 内容会变化，原有 proxy route.conf 注入段失效。若 proxy 已启用，切换节点后需重新执行 `proxy.enable()`。
