# 181 重装实验报告（2026-09-30）

> **目的**：验证用户旅程「安装 → 登录 → 密钥导入/恢复 → 配置 → 起隧道」能否用工具跑通。
> **约束**：只走 WebUI / mynet 工具；不直改 DB、不手改配置文件。
> **账号**：luoyueliang@gmail.com（密码不入本文档）

---

## 1. 结果摘要

| 阶段 | 结果 | 说明 |
|---|---|---|
| 备份私钥/公钥 | ✅ | `~/secure-backup/mynet-181-keys-20260930-144644/` |
| 完全卸载 | ✅ | launchd / 旧 Node / Go 全套树清空 |
| 干净出包 | ✅ | `make dist` 6 产物 + 分平台 INSTALL.md |
| 落位 + install | ✅ | `/opt/mynet` + `com.mynet.mynetd` |
| auth login | ✅ | user_id=3844197700300659 |
| setup --online | ✅ | 只建 profile/激活（**已改为不生成密钥**） |
| 密钥导入 | ✅ | `config keys set` → 指纹 `515aa2b3…`（与备份一致） |
| keys sync | ✅ | `synced: true` |
| config pull | ✅ | bundle 7966B + 投影 + parity zip 全绿 |
| detector + apply | ✅ | 23 条 OS 路由 |
| service start gnb | ✅ | `running=true node_id=3764021915150630` |
| subject | ✅ | 进程锚唯一，同目录 gnb_ctl/gnb_es |
| route doctor | ⚠️ | 主路径通，部分 peer 走 en0（见 §5） |
| ping 180/182/183 | ✅ | 100ms / 24ms / 隧道内 |
| ping 188 | ❌ | 未进隧道（en0） |
| WebUI | ⚠️ | mynetd 在 8711，8096 未响应 |

**一句话**：用户旅程主链路已用工具跑通；密钥恢复路径成立；剩余是路由覆盖与 WebUI 端口两个尾巴。

---

## 2. 备份（唯一资产 = 私钥）

| 项 | 路径 |
|---|---|
| 备份根 | `~/secure-backup/mynet-181-keys-20260930-144644/`（0700） |
| tgz ×4 | gnb security 三树 + v2 conf 树 |
| 解包（恢复用） | `extract/` |
| 校验 | `CHECKSUMS.txt` |

**生产钥**：`3764021915150630` → 公钥 `515aa2b3b056fa1ecfe516ba6b86890291741c561f244fb4fb47c18b76241218`

> 实测确认：**GNB / WireGuard 均可由私钥推导公钥**（`cmd/keyprove`）。
> 备份只需私钥；本报告仍附了公钥便于核对。

---

## 3. 卸载清单（已执行）

- 停 `com.mynet.mynetd` / `club.mynet.tray` / gnb 进程（pidfile+cmdline）
- `mynet uninstall`（Go 版）
- 删 LaunchDaemons/LaunchAgents、旧 Node `/usr/local/bin/mynet*`
- 删 `/opt/mynet` `/var/lib/mynet` `/usr/local/opt/mynet` `/usr/local/mynet` `/usr/local/bin/gnb*`
- 验收：无进程、无 launchd、`which mynet` 空

---

## 4. 重装流程（工具链）

```text
make dist                          # 干净出包（含分平台 INSTALL.md）
cp 二进制 → /opt/mynet/bin         # 安装包职责
cp gnb 四件套 → 标准位            # 同构建 P1v2+P2
sudo -E mynet install --gateways … # 只装服务
sudo -E mynet auth login --email … --password-stdin
sudo -E mynet setup --online --tenant … --node-id …   # 不生成密钥
sudo -E mynet config keys set --private … --public …  # 导入备份钥
sudo -E mynet config keys sync
sudo -E mynet config pull          # bundle + 投影 + parity
sudo -E mynet detector
sudo -E mynet apply --auto
sudo -E mynet service start gnb
```

**密钥指纹核对**：导入后 `config keys show` = `515aa2b3…` = 备份公钥 ✅

---

## 5. 本次实测暴露的工具缺口（已修 / 待修）

### 已修（本轮代码变更）

| # | 问题 | 修复 |
|---|---|---|
| 1 | **`setup` 自动生成密钥**，干扰「导入/恢复」路径 | `setup` 只写 profile/intent + 激活；密钥必须 `keys set` / `keys generate` |
| 2 | `keys generate/set` 不认 `--node-id`，mode 未写 node 就报错 | `configFaceKeysDirWrite(nodeIDHint)` 优先 CLI 显式 id |
| 3 | 产物包无安装文档 | `packaging/{darwin,linux,windows,openwrt}/INSTALL.md` 打进各包；pack.sh 强制校验 |

### 待修

| # | 现象 | 影响 |
|---|---|---|
| 4 | `apply --auto` 要求先 `detector`（system.json） | 顺序隐含；建议 install 后自动 detector 或报错更明确 |
| 5 | 部分 peer 路由走 en0（188/184–186） | 与 `route.conf` 条目/接口名有关；需查 apply 的 route 源 |
| 6 | WebUI `127.0.0.1:8096` 无响应；mynetd 在 **8711** | 端口约定与 install 实际不一致（8096 vs 8711） |
| 7 | `mynetd.env` 未写到 `/opt/mynet/etc/` | plist 里 env 还在；U3 回退链可补 |
| 8 | `config use online` 要求 profile.json 已存在 | 与 setup 顺序耦合；现用 setup 建 profile 绕过 |

---

## 6. 用户旅程对照

| 旅程步骤 | 工具 | 实测 |
|---|---|---|
| 安装 | 装包 + `mynet install` | ✅ |
| 登录 | `mynet auth login` | ✅ |
| 有私钥 → 导入 | `mynet config keys set` + `keys sync` | ✅ |
| 无私钥 → 生成 | `mynet config keys generate` | 路径就绪（本次用导入） |
| 配置开关 | `config sync` / `plugin` / `apply` | ✅ |
| 代理 | `plugin enable proxy` | 代码路径在；本次未切出口 |
| 日常诊断 | `subject` / `route doctor` / `status` | ✅ |

---

## 7. 与 5 节点 MVP 的关系

- 181 是四平台样本之一（macOS）；本实验验证的是**流程**，不是现网配置。
- 180/188 双外网出口仍是代理 MVP 最小模型。
- 5 节点**唯一需资产备份**：各节点私钥（已证可推导公钥）。

---

## 8. 建议下一步

1. 查 `apply --auto` 对 188 等未装路由的原因（route 源 / ifname）
2. 对齐 WebUI 端口（8096 文档 vs 8711 实装）
3. install 后自动 `detector`，减少顺序坑
4. 把「setup 不生成密钥」写进分平台 INSTALL.md（已写）
5. 代理全链路（plugin → proxy.conf）单独验收

---

**证据**：`~/secure-backup/mynet-181-keys-20260930-144644/` · `mynet subject --json` · `route doctor` 输出 · `make dist` 产物清单
