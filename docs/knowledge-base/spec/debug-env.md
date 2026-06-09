# SPEC: macOS 本地调试环境（vm / vml）

> 更新：2026-05 | 适用：mynet_lua（vm）/ mynet_client（vml）

---

## 两套环境对比

| 项目 | `vm`（mynet_lua） | `vml`（mynet_client） |
|------|-------------------|----------------------|
| 脚本位置 | `mynet_lua/debug/vm` | `mynet_client/debug/vml` |
| 虚拟化系统 | OpenWrt 23.05 armsr-armv8 | Debian 12 arm64 |
| 管理口 IP | 192.168.101.2 | 192.168.102.2 |
| LAN 接口模式 | vmnet-host (eth1/br-lan) | vmnet-host (eth0) |
| WAN 接口模式 | vmnet-shared DHCP (eth0) | vmnet-bridged DHCP (eth1) |
| SSH 别名 | `openwrt-qemu` | `mynet-gw` |
| WebUI | http://192.168.101.2/cgi-bin/luci/admin/services/mynet | http://192.168.102.2:7070 |
| 用途 | 调试 Lua/LuCI 代码 | 调试 Node.js client + 充当 macOS 网关 |

**共同依赖：** QEMU + Apple HVF（仅支持 Apple Silicon / arm64）

---

## 网络拓扑

### vm（OpenWrt）

```
macOS
 └─ vmnet-host 192.168.101.1
      └── OpenWrt eth1 (br-lan): 192.168.101.2  ← SSH / WebUI
 └─ vmnet-shared (NAT)
      └── OpenWrt eth0: DHCP  ← WAN 上网
```

### vml（Debian + mynet_client 网关模式）

```
macOS
 ├─ vmnet-host bridge102 (192.168.102.1)
 │    └── Debian eth0: 192.168.102.2   ← 管理口 / 网关入口
 └─ vmnet-bridged en0
      └── Debian eth1: DHCP (192.168.0.x)  ← WAN（接家庭路由器）

macOS 路由表（vml gateway on 后）:
 0.0.0.0/8 → 192.168.102.2
 1.0.0.0/8 → 192.168.102.2
 ... 共 223 条 /8 路由（绕过 127.x/私有 /8 段）
 192.168.0.0/16 → 192.168.102.2  ← GNB peer LAN
 {local_subnet}  → en0（精确匹配，保留直连）
```

---

## 生命周期命令

### vm（OpenWrt）

```bash
bash debug/setup.sh          # 一次性初始化（下载镜像、配置 cloud-init）
bash debug/start.sh          # 后台启动 QEMU
bash debug/start.sh -fg      # 前台启动（调试用，Ctrl-A X 退出）
bash debug/stop.sh           # 停止 VM
bash debug/sync.sh           # 同步全部代码（controller/model/view/static/config）
bash debug/sync.sh controller  # 只同步 controller/mynet.lua
bash debug/sync.sh model       # 只同步 model/*.lua
bash debug/sync.sh view        # 只同步 view/*.htm
```

> sync 后自动执行 `rm -rf /tmp/luci-*`，刷新浏览器即见效果。

### vml（Debian / mynet_client）

```bash
vml setup       # 一次性初始化（下载镜像、生成 cloud-init ISO）
vml install     # VM 内安装 Node.js 20 LTS + 目录结构
vml up          # 启动 VM
vml down        # 停止 VM
vml restart     # 重启 VM
vml ssh         # SSH 进入 VM
vml sync        # 同步 mynet_client 代码到 VM（rsync）
vml on          # 开启网关模式（macOS 流量走 VM → GNB 隧道）
vml off         # 关闭网关模式（恢复直连）
vml status      # 状态一览（VM 运行/网关/DNS）
vml check       # 连通性测试（DNS / HTTP / IP 验证）
vml logs [-f]   # 查看 mynet_client 日志
vml dns setup   # VM 上启用 dnsmasq split DNS（→ dns-split.sh）
vml dns status  # 查看 DNS 分流状态
```

---

## 初始化流程（首次使用）

### vm 初始化

```bash
# 1. 初始化镜像（约 400MB 下载）
bash debug/setup.sh

# 2. 启动
bash debug/start.sh           # 等待约 20s 就绪

# 3. 部署代码
bash debug/sync.sh install    # 安装 luci + luci-lib-jsonc + curl，然后同步

# 4. 访问
open http://192.168.101.2/cgi-bin/luci/admin/services/mynet
```

### vml 初始化

```bash
# 1. 初始化镜像（约 400MB 下载，cloud-init ISO 生成）
vml setup

# 2. 启动（首次需 60-90s 让 cloud-init 完成）
vml up

# 3. 安装环境
vml install     # Node.js 20 LTS + /opt/mynet 目录结构

# 4. 同步代码
vml sync

# 5. 启动 mynet_client
vml ssh
sudo node /opt/mynet/bin/mynet.js web

# 6. 开启网关模式（可选，测试透明代理）
vml on
```

---

## 网关模式（vml on）详细行为

**目的：** macOS 所有公网流量通过 Debian VM，经 GNB 隧道出站，验证透明代理效果。

**添加路由（macOS 侧）：**
1. 检测 Wi-Fi 接口（`en0`）当前子网 → 保留精确路由走 en0
2. 添加 224 条 `/8` 路由（0.x - 126.x, 128.x - 223.x）→ VM 管理口 `192.168.102.2`
3. 添加 `192.168.0.0/16` → VM（GNB peer 私有网络）
4. （可选）切换 macOS DNS → VM 上的 dnsmasq split DNS

**清理路由（vml off）：**
1. 删除全部 /8 路由
2. 删除 192.168.0.0/16 路由
3. 恢复 macOS DNS 为 DHCP 自动

**验证（vml check）：**
```
DNS 解析: google.com → 应得到干净 IP（非 ISP 污染）
HTTP: curl https://ipinfo.io → 应显示 GNB peer 节点的出口 IP
Ping: 8.8.8.8 → 应可达
```

---

## DNS 分流（vml dns）

在 VM 内部运行 dnsmasq，macOS DNS 指向 VM：

```bash
vml dns setup   # 配置 split DNS（国内用 223.5.5.5，国外域名走 8.8.8.8）
vml dns update  # 更新国外 DNS 服务器（不重建域名列表）
vml dns status  # 查看 dnsmasq 状态 + gfwlist 条目数
```

底层调用 VM 内部的 `dns-split.sh`（参见 proxy.md `dns_split` 规范）。

---

## 调试技巧

### vm（OpenWrt）

```bash
# 实时 LuCI 日志
ssh openwrt-qemu "logread -f | grep -i mynet"

# 查看 GNB 进程
ssh openwrt-qemu "ps | grep gnb"

# 查看路由表
ssh openwrt-qemu "ip route show table all | grep gnb"

# 查看 nftables 规则
ssh openwrt-qemu "nft list ruleset"
```

### vml（Debian）

```bash
# mynet_client 日志
vml logs -f

# SSH 进入查看
vml ssh

# VM 内查看 GNB 状态
sudo node /opt/mynet/bin/mynet.js status

# 查看策略路由
ssh mynet-gw "ip rule list; ip route show table 200"

# 查看 iptables/nftables 规则
ssh mynet-gw "nft list ruleset 2>/dev/null || iptables -t mangle -L -n"
```

---

## 注意事项

- 两套环境的子网**不重叠**（101.x vs 102.x），可以同时运行
- `vml up/down` 需要 sudo（vmnet 网络权限）
- `vml on`（网关模式）修改 macOS 路由表，需要 sudo；`vml off` 必须执行否则路由残留
- VM 内 `/opt/mynet` 路径与 macOS 主机完全隔离，不冲突
- OpenWrt VM 的磁盘是 512MB qcow2；Debian VM 是 8GB qcow2（稀疏格式，实际占用小）
