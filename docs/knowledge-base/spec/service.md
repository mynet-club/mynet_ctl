# SPEC: 服务启停（Service）

> 更新：2026-05 | 适用：mynet_lua / mynet_client

---

## 三层模型

```
Layer 1: GNB 进程层
  gnb -c driver/gnb/conf/{node_id}/
  → 创建 TUN 接口，建立 P2P 隧道

Layer 2: 路由注入层
  ip route add ... dev {tun_iface}
  → 解析 route.conf，注入系统路由表

Layer 3: 防火墙层
  iptables / nftables / pfctl
  → 允许 TUN 接口流量通过
```

**启动顺序：** Layer 1 → Layer 2 → Layer 3  
**停止顺序：** Layer 3 → Layer 2 → Layer 1（反向）

---

## OPERATION: service.start(node_id)

**PRECONDITION:**
- 配置完整（`mynet.conf` 存在，`driver/gnb/conf/{node_id}/` 目录完整）
- GNB binary 存在
- 当前进程具有 root/sudo 权限
- 服务未在运行

**STEPS:**
1. 检查是否有僵尸 GNB 进程（扫描系统进程），有则先 kill 并等待 800ms
2. 验证权限（isRoot），不是 root → Error("ROOT_REQUIRED")
3. 前置检查（`service.preflight()`）：验证 binary、配置文件、密钥文件均存在
4. **preStart hooks**（插件钩子）：各插件按注册顺序执行 `preStart(node_id)`
   - proxy 插件：若 `PROXY_ENABLED=1`，将 pipe 路由段注入 route.conf
5. 修补 node.conf：将 `daemon on` 替换为 `daemon off`（Node.js/Lua 自管进程生命周期）
6. 启动 GNB 进程：`gnb -c {conf_dir}`（detached，不阻塞父进程）
7. 等待 600ms，检测进程是否立即崩溃；若已退出 → Error("GNB_EXITED_IMMEDIATELY")
8. 等待 TUN 接口出现（检测 stdout `if[X] opened` 或轮询接口列表），超时 5s → Error("TUN_TIMEOUT")，kill 进程
9. 记录 PID 文件（含 pid / node_id / tun_iface / 启动时间戳）
10. **Layer 2**：根据 route.conf 注入系统路由（`ip route add ... dev {tun_iface}`）
11. **Layer 3**：应用防火墙规则（允许 TUN 接口流量）
12. 广播服务状态变更事件 `service_changed { running: true }`
13. **postStart hooks**（插件钩子）：各插件按注册顺序执行 `postStart(node_id, tun_iface)`
    - proxy 插件：若 `PROXY_ENABLED=1`，应用 policy routing（参见 proxy.md）

**POSTCONDITION:**
- GNB 进程运行中，TUN 接口已建立
- 系统路由表包含 route.conf 中的条目
- PID 文件存在

**ERROR PATHS:**
- binary 不存在 → Error("GNB_BINARY_NOT_FOUND")
- root 权限不足 → Error("ROOT_REQUIRED")
- TUN 超时 → kill 进程，Error("TUN_TIMEOUT")
- Layer 2 路由注入失败 → kill 进程，Error("ROUTE_INJECT_FAILED")

**PLATFORM DIVERGENCE:**
- OpenWrt/Lua: Layer 3 使用 nftables zone（`nft add rule ... iifname {tun}`）+ `firewall reload`
- Linux/Node: Layer 3 使用 iptables（`-A FORWARD -i {tun} -j ACCEPT`）
- macOS/Node: Layer 3 使用 pfctl（`pass on {tun} all`）
- OpenWrt/Lua: 服务管理通过 `/etc/init.d/mynet start`，由 rc 脚本驱动三层流程
- mynet_client: Node.js 进程直接 spawn gnb，管理子进程生命周期

---

## OPERATION: service.stop()

**STEPS:**
1. 确定当前 GNB 进程来源：
   - 内存中有 PID → 按记录 PID 操作
   - PID 文件存在但内存无记录 → 按 PID 文件操作
   - 两者均无，但系统中扫描到 gnb 进程 → 按扫描结果操作
   - 均未找到 → 返回 `already_stopped`
2. **Layer 3**：清除防火墙规则
3. **Layer 2**：清除系统路由注入（`ip route del ...`）
4. **preStop hooks**（插件钩子）：各插件执行 `preStop()`
   - proxy 插件：若在运行，清除 policy routing（参见 proxy.md `proxy.stop()`）
5. 发送 SIGTERM 给 GNB 进程组（含伴随进程 gnb_es/gnb_ctl）
6. 等待最多 5s；若未退出 → SIGKILL
7. 删除 PID 文件
8. 广播状态变更事件 `service_changed { running: false }`

**POSTCONDITION:**
- GNB 进程已退出
- TUN 接口已消失
- 系统路由已清理

---

## OPERATION: service.preflight(node_id)

启动前配置完整性检查（不修改任何状态）。

**CHECKS（全部通过才允许启动）：**
1. GNB binary 存在且可执行
2. `driver/gnb/conf/{node_id}/node.conf` 存在
3. `driver/gnb/conf/{node_id}/route.conf` 存在
4. `driver/gnb/conf/{node_id}/address.conf` 存在
5. `security/{node_id}.private` 存在（128 hex chars）
6. `security/{node_id}.public` 存在
7. `ed25519/` 目录下至少有一个对端公钥文件

**ERROR PATHS:**
- 任一检查失败 → 返回具体失败项，Error("PREFLIGHT_FAILED: {item}")

---

## OPERATION: service.getStatus()

**RETURNS:**
```json
{
  "running": true,
  "pid": 12345,
  "node_id": "3722810996882027",
  "tun_iface": "gnb_tun_178",
  "uptime_s": 3600,
  "layers": {
    "gnb_process": true,
    "routes": true,
    "firewall": true
  }
}
```

**检测方式：**
- `gnb_process`：PID 文件存在且进程存活（`kill -0 {pid}`）
- `routes`：检查系统路由表是否包含 TUN 接口的条目
- `firewall`：检查对应规则是否存在
