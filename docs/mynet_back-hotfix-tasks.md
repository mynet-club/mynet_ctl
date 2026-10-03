# mynet_api 项目修复任务指导

> **来源**：mynet_ctl API 全链路审计（2026-04-19）  
> **目标项目**：`/Users/arthur/Devel/WorkSpacePHP/lyt.com/mynet_back/`  
> **完成后**：回到 mynet_ctl 更新 `docs/api-versions.json` back_api 版本为 v2.2.0

---

## 🔥 P0 优先级（必须立即修复）

### 1. config-bundle 安全漏洞：zone_id 校验不严格

**位置**：`app/Http/Controllers/Api/V1/NodeConfigController.php` ~L905

**问题**：用户可通过篡改 `zone_id` 查询参数越级访问其他用户的节点配置

```php
// 当前（错误）代码
$node = Node::where('node_id', $id)
    ->where('zone_id', $request->input('zone_id'))  // ⚠️ 客户端可篡改!
    ->where('user_id', Auth::id())
    ->first();
```

**修复方案**：
```php
// 修复后代码
$user = Auth::user();
$currentZone = $user->currentZone();  // 从认证上下文获取
if (!$currentZone) {
    return response()->json(['message' => '请先切换到有效 Zone'], 422);
}

$node = Node::where('node_id', $id)
    ->where('zone_id', $currentZone->id)  // ✅ 使用认证上下文的 zone_id
    ->where('user_id', $user->id)
    ->first();
```

**验证方法**：
1. 用户 A 创建节点，获得 `node_id=123`，`zone_id=1`
2. 用户 B 尝试访问：`GET /api/v2/nodes/123/config-bundle?zone_id=1`
3. **修复前**：返回用户 A 的配置（安全漏洞）
4. **修复后**：返回 404 或 403

---

### 2. 对等节点公钥获取失败时静默继续

**位置**：`app/Http/Controllers/Api/V1/NodeConfigController.php` ~L920-930

**问题**：生成 config-bundle 时，如果某个对等节点公钥获取失败，不影响整体响应，导致配置包缺失关键密钥

**修复方案**：
```php
// 在 config-bundle 响应中增加 warnings 字段
$warnings = [];
$peerKeys = [];

foreach ($peerNodeIds as $peerId) {
    try {
        $peerKeys[$peerId] = $this->getNodePublicKey($peerId);
    } catch (Exception $e) {
        $warnings[] = [
            'type' => 'missing_peer_key',
            'node_id' => $peerId,
            'message' => "无法获取对等节点 {$peerId} 的公钥"
        ];
    }
}

$response = [
    'node_id' => $nodeId,
    'files' => [...],
    'keys' => ['ed25519' => $peerKeys, ...],
];

if (!empty($warnings)) {
    $response['warnings'] = $warnings;
}
```

**验证方法**：
1. 删除 Zone 中某个对等节点的密钥
2. 调用 config-bundle API
3. **修复前**：返回 200，但 keys.ed25519 缺失该节点
4. **修复后**：返回 200 + warnings 字段说明缺失原因

---

## 🟠 P1 优先级（2 周内修复）

### 3. 心跳上报：vpn_interface 字段名不一致

**位置**：`app/Http/Controllers/Api/V1/NodeHeartbeatController.php` ~L80-120

**问题**：客户端发送 `vpn_interfaces`（复数），但某些逻辑使用 `vpn_interface`（单数），导致兼容性问题

**修复方案**：
```php
// 统一为复数形式
$heartbeatData = $request->validated();

// 兼容旧客户端的单数形式
if (isset($heartbeatData['vpn_interface']) && !isset($heartbeatData['vpn_interfaces'])) {
    $heartbeatData['vpn_interfaces'] = [$heartbeatData['vpn_interface']];
    unset($heartbeatData['vpn_interface']);
}

// 后续逻辑全部使用 vpn_interfaces
$interfaces = $heartbeatData['vpn_interfaces'] ?? [];
```

---

### 4. 心跳命令合并逻辑覆盖问题

**位置**：`app/Http/Controllers/Api/V1/NodeHeartbeatController.php` ~L130-139

**问题**：同类型命令（如多个 `config.refresh`）会互相覆盖，只保留最后一个

**修复方案**：
```php
// 命令去重但不覆盖不同参数的同类型命令
$commandMap = [];
foreach ($commands as $cmd) {
    $key = $cmd->action . '_' . md5(json_encode($cmd->params ?? []));
    $commandMap[$key] = $cmd;
}
$uniqueCommands = array_values($commandMap);
```

---

## 📋 修复完成后的验证清单

### API 测试
- [ ] **config-bundle 安全性**：跨用户访问返回 403/404
- [ ] **config-bundle 完整性**：warnings 字段正确显示缺失密钥
- [ ] **心跳兼容性**：同时支持 `vpn_interface` 和 `vpn_interfaces`
- [ ] **命令去重**：多个同类型命令正确处理

### 更新版本
- [ ] **back 项目**：`config/app.php` 中更新版本为 `2.2.0`
- [ ] **ctl 项目**：更新 `docs/api-versions.json` 中 `back_api.version` → `2.2.0`
- [ ] **ctl 项目**：移除 `known_issues` 中已修复的问题

### 客户端通知
- [ ] **mynet_lua**：通知升级到 config-bundle API（性能提升 4×）
- [ ] **mynet_client**：确认 API 兼容性无破坏性变更

---

## 🔄 关联任务（其他项目）

完成 back 修复后，建议处理：

### mynet_lua 升级（P0）
**位置**：`/Users/arthur/Devel/WorkSpaceLua/mynet_lua/`
- **文件**：`luasrc/model/mynet/node.lua`
- **任务**：替换 4 次分散 API 调用为 1 次 config-bundle 调用
- **期望收益**：性能提升 4×，服务端负载降低 75%

### mynet_client URL 规范化（P2）
**位置**：`/Users/arthur/Devel/WorkSpaceNode/mynet_client/`
- **任务**：API base URL 从 `my.mynet.club` 改为 `api.mynet.club`
- **影响**：移除对 CNAME 的依赖，更规范

---

## 📞 联系方式

如有问题，参考 CTL 项目中的完整审计报告：
- **API 规范**：`docs/knowledge-base/api-specification.md`
- **版本追踪**：`docs/api-versions.json`
- **审计会话记录**：`/memories/session/mynet_back_api_audit.md`
