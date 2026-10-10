# 节点升级部署计划 — .180（proxy server） / .182（proxy client）

> 2026-10-10。**状态：计划已定稿口径，未执行**（S0 取证也尚未跑）。
> 用户本轮澄清（权威）：`.180` = `api.mynet.club`，新加坡 Lightsail，**proxy 的 server**；
> `.182` = `192.168.0.2`，本地（home）**旁路网关**（跑 smartdns），**proxy 的 client**。
> 承接 [platform-lifecycle-build-playbook.md](platform-lifecycle-build-playbook.md) §5 **H6 现网迁移**。
> 既有前置计划（不复述，做输入）：`mynet_openwrt/docs/UPGRADE_PLAN_192_168_0_2.md`（.182，2026-09-29）、
> `.wt/gnb-bare-leftover-20261004/.../p2-route-proxy/UPGRADE-180.md`（.180，2026-09-30 已执行 gnb 升级）。
> 本轮**只出计划**：不连节点、不改配置、不动 smartdns、不轮换密钥。

**本轮裁定（用户 2026-10-10）**：D1 `.182`=本地旁路网关（旧「公司整网网关」为误读，已回改）· D2 **本次不轮换密钥**（接受推荐）· D3 **走 CI 产物** · D4 `.182` **用 ipk** · D5 `.182` 已非活路径（现网主网关已切到 `.1`），维护窗压力低。

---

## 0. 结论先行

- **`.182` 的 ipk 与 `.180` 的 tar 在 CI 里都已就绪**（见 §2），本次**不经 ctl、不靠自动升级**，从 Actions artifact 取件 + `scp` 手投。
- **自动升级链今天仍不可用**：`mynet update` 只按 `runtime.GOOS` 选包（选不到 openwrt 包）、`mynet driver upgrade` 不筛 platform/arch、`upgrade/install.go` 不认 `.ipk`。→ 本次一律手工投递 + `file`/sha256 校验。
- `.182`（OpenWrt x86_64，旧形态）风险最高：**proxy 分流不能断**、**smartdns 链不能动**、**双心跳要摘**、**密钥绝不能覆盖**。
- 两节点都必须**先备份**（含私钥）；**本次不轮换密钥**（泄露事故的轮换 = 全 mesh 事件，另议 —— D2 已裁定本次不做）。

---

## 1. 节点身份与基线（S0 取证清单）

| 节点 | 身份 | 平台 | Store Root | 关键角色 |
|---|---|---|---|---|
| `.180` | `api.mynet.club`；node_id `4997342800601732`；Lightsail `Mynet` ap-southeast-1；SSH 公网 `52.221.22.241` | Debian（Lightsail）amd64 | `/var/lib/mynet` | **proxy server**、生产出口、gnb index `-P -l 9016` |
| `.182` | `192.168.0.2`；node_id `8283289760467957` | **ImmortalWrt 24.10 x86_64** | `/etc/mynet` | **proxy client**、旁路/分流、dnsmasq→smartdns:6053 |

✅ **身份口径已裁定（D1）**：`.182` = **本地（home）旁路网关**，非公司网关。旧记载「公司整网网关 192.168.0.2」系误读，**已回改**：各仓 `.githooks/guard.sh`（含 `knowledge-base/templates/` 模板）、`knowledge-base/AGENTS.md`、`mynet_api` 的 `BundleEnvelopePublisher.php` 注释。`mynet_openwrt/docs` 的「家庭旁路由」与此一致。

**S0 取证（每节点，只读）**：
```text
版本/形态：  /usr/sbin/mynet --version 2>/dev/null || echo "无 agent（旧 Lua 形态）"
            /usr/sbin/mynetd --version 2>/dev/null || true
            opkg list-installed | grep -E 'mynet|luci-app-mynet'        # .182
进程/服务：  ps -o pid,cmd -C gnb,mynetd,gnb_es ; ls -l /etc/init.d/{mynet,mynetd}
            systemctl status mynetd 2>/dev/null | head -5               # .180
身份核对：  cat $HOME/mode.json ; ls $CONF/driver/gnb/conf/
对端参数：  diff 两端 node.conf 的 zip/crypto（parity 基线，见 node-connectivity-checklist §Q&A）
心跳：      crontab -l | grep mynet-heartbeat                          # 找双心跳
proxy：     .182: nft list ruleset | grep -c mynet_proxy ; ip rule ; .180: cat conf/<id>/proxy.conf
DNS：       .182: uci show dhcp | grep -i smartdns ; ps | grep smartdns
```

---

## 2. 产物与来源（CI 产物，D3/D4）

| 节点 | 产物（CI artifact 名） | 产出工作流 | 备注 |
|---|---|---|---|
| `.180` | `mynet_agent_linux_amd64`（内含 `mynet_agent_linux_amd64_<ver>.tgz`：`mynet` + `mynetd`(embed) + `webui.html` + `INSTALL.md`） | `mynet_agent/.github/workflows/build.yml` | gnb 已于 2026-09-30 升级，本次**不重升 gnb**，除非 S0 发现版本落后 |
| `.182` | `openwrt-ipk-amd64`（内含 `mynet-agent_*_amd64.ipk` + `luci-app-mynet_*.ipk`） | `mynet_agent/.github/workflows/build_openwrt.yml`（SDK 24.10，矩阵含 `x86/64`，注释即“192.168.0.2 = ImmortalWrt 24.10 x86_64”） | **必须 `-tags openwrt`**：通用 linux 包已拒绝在 OpenWrt 装配；ipk 路径由 SDK 出，无需 dist 的 openwrt 腿 |

**取件**：GitHub Actions → 对应 run → 下载 artifact → `scp` 到节点 `/tmp/`；落盘后 `sha256sum` 与 artifact 清单比对（不经 ctl 分发面）。

**已知缺口（本次不影响交付，但要记）**：
1. ✅ **已修（H4 构建输入）**：`build.yml` 的 linux 条目**去掉了 `openwrt: true`**，桌面/服务器 mynetd 一律 `-tags embed`（与 `make dist` 一致）；OpenWrt 交付归 `build_openwrt.yml` 的 `-tags openwrt` ipk。不再存在「伪装成 linux 的 OpenWrt 包」。fixture `TestBuildWorkflowDoesNotFakeOpenWrt` 锁住。
2. `Makefile` `DIST_MATRIX` 的 openwrt 腿只有 mipsel（已加）。`.182` 走 ipk 故**不阻塞**；若日后要 openwrt tgz 路线，需补 `openwrt:linux:amd64:amd64:openwrt`。
3. ipk **未进 ctl 分发面**（只出 Actions artifact）→ 本次手投；长期归属 H4/O6。
4. `mynet update` / `driver upgrade` 的选择逻辑缺陷（见 §0）→ H4。

---

## 3. 升级步骤

### 3.A `.180`（Linux / proxy server）—— 参照 UPGRADE-180 的 S 段

```text
P0 备份（强制）
   ssh -i $KEY root@52.221.22.241 '
     BK=/tmp/mynet-cfg-backup-$(date +%Y%m%d_%H%M%S); mkdir -p $BK
     tar -C / -czf $BK/_opt_mynet.tgz opt/mynet
     tar -C / -czf $BK/_etc_mynet.tgz etc/mynet
     tar -C / -czf $BK/_var_lib_mynet.tgz var/lib/mynet     # 含节点私钥
     sha256sum $BK/* > $BK/CHECKSUMS'
   ⚠️ 私钥在 /var/lib/mynet/.../security/（不在 opt/mynet）—— 只打 opt 会漏（踩坑 #11）

P1 投递并校验：/tmp/upgrade/{mynet,mynetd}；file → ELF x86-64；./mynet platform；strings | grep ApplyProxy

P2 dry-run：mynet install --dry-run --home /var/lib/mynet --gateways wss://gw.mynet.club/v1/node/connect
   确认只打印、零写盘（退出 0）

P3 维护窗
   1) 停节点实例（pidfile+cmdline 精确匹配，**禁** pkill/killall；index -P:9016 不动）
   2) 替换 /usr/local/bin/{mynet,mynetd}（gnb 四件套如需）
   3) mynet install --home /var/lib/mynet --gateways wss://…（装配 systemd 服务/env）
   4) 起 mynetd → service start gnb
   5) 验证 §5.A

P4 观察 24h 后删旧二进制（保留 .bak）
```

### 3.B `.182`（OpenWrt / proxy client）—— ipk 路线（D4）

```text
Phase 0 备份（强制，未备份不开跑）
   /etc/mynet/ 全量（含 conf/<id>/security 私钥）
   /etc/config/{network,firewall,dhcp} ；nft list ruleset > backup-nft-<date>.txt
   ip rule ; ip route show table mynet_proxy ; crontab -l ; /etc/init.d/mynet
   opkg list-installed > backup-pkgs.txt
   smartdns 配置 —— **只备份、不改**

Phase 1 投递 ipk 并校验 sha256

Phase 2 dry-run（VM 或节点，只打印）
   mynet install --dry-run --home /etc/mynet --gateways wss://…  → 退出 0、零写盘

Phase 3 维护窗（D5：已非活路径，压力低）
   1) 确认 Phase 0 完整
   2) 停旧服务（procd_kill 精确停；**禁** pkill）；摘 cron */5 mynet-heartbeat（防双心跳）
   3) opkg install /tmp/mynet-agent_*_amd64.ipk /tmp/luci-app-mynet_*.ipk
      → 记录 opkg 输出；检查 /usr/sbin/{mynet,mynetd}、/etc/init.d/{mynet,mynetd}
   4) mynet install --home /etc/mynet --gateways wss://…（UCI 首装 / zone / env）
   5) 起 mynetd；/etc/init.d/mynet start（procd 拉起 gnb）
   6) 验证 §5.B，不达标立即回滚 §7

Phase 4 收尾
   proxy 分流抽查（国内外域名 + 策略表）；心跳只走 mynetd；LuCI 状态与 OS 一致；备份留 ≥7 天
```

---

## 4. 密钥与备份

- **备份约定**：凭证走 `crt/crtvault`（`crt materialize arthur/ssh/id_ed25519` 取 SSH key）；脚本 `itest/backup_work_nodes.sh` 已备 `/etc/mynet`(.182) 与 `/opt/mynet`、`/etc/nginx`(.180)。
- **本轮动作**：两节点各自 P0 再备份一次；产物**不进工作区、不进 git**（列名用 `tar -tzf` 核对）。
- **不覆盖密钥**：升级只换二进制/init/LuCI；`conf/` 的 `credential/config/proxy` 与 `security/` **绝不覆盖**（UPGRADE_PLAN R8）。
- **D2 裁定：本次不轮换**。背景：2026-10-04 `.180` 与 `192.168.0.2` 的 `*.private`/`credential.json` 被打包推上 GitHub；防护已修（A4 归档扫描 + bare 仓 pre-push），但**私钥未轮换**（裁定「暂缓，挂风险」）。轮换 = 全 mesh 事件（两端公钥已扩散进各节点 `ed25519/*.public`/route.conf），需另出跨节点窗口 + 全网对等同步 —— **本次不做，风险继续挂账**。

---

## 5. 验证清单

### 5.A `.180`
| # | 项 | 通过标准 |
|---|---|---|
| A1 | mynetd | running；env 正确（`MYNET_HOME=/var/lib/mynet`） |
| A2 | gnb | 节点实例在、`gnb_tun_*` UP；index `-P:9016` 未受影响 |
| A3 | **proxy server** | `conf/<id>/proxy.conf` role=server / `proxy on` / `ingress_open on`；对端 client 能走它出网 |
| A4 | 防火墙 | Lightsail UDP(生产端口) 入站开；机内 `nft/iptables INPUT` 放行 UDP；`-t nat POSTROUTING` MASQUERADE 在 |
| A5 | 连通 | 对端 VIP ping 通；`mynet route doctor` |

### 5.B `.182`
| # | 项 | 通过标准 |
|---|---|---|
| B1 | mynetd | running；`MYNET_EXECUTOR=lua`；**心跳只走 mynetd**（cron 已摘） |
| B2 | gnb | procd 实例；tun `gnb_tun_16` UP |
| B3 | 路由 | `ip route show table mynet_proxy` default via 对端仍在；（新体系）或 proxy.conf role=client |
| B4 | **proxy client** | 出口授权生效；撤销后旧连接不再复用（fail-closed） |
| B5 | 分流 | 国外域名走隧道、国内直连 |
| B6 | **DNS** | dnsmasq→smartdns 链未破坏 |
| B7 | LuCI | Dashboard 无裸 API 错误；启停反映 OS |

---

## 6. 发行面缺口（归口 H4/O6，本次不阻塞）

| # | 缺口 | 影响 | 归口 |
|---|---|---|---|
| G-a | ~~`build.yml` 的 `openwrt:true` 未带 `-tags openwrt`、产物名 `linux_*`~~ | ✅ **已修（H4 构建输入）**：linux 条目去 `openwrt:true`、mynetd 统一 `-tags embed`；OpenWrt 归 `build_openwrt.yml` 的 ipk | H4 |
| G-b | dist 的 openwrt 腿缺 amd64（现有 mipsel） | 走 ipk 不阻塞；走 tgz 才需要 | H4 |
| G-c | ipk 未进 ctl 分发面 | 本次手投；长期应入 ctl | H4 / O6 |
| G-d | `mynet update` 按 GOOS 选包、`driver upgrade` 不筛 platform/arch | 自动升级不可用 | H4 |
| G-e | install 不认 `.ipk`、opkg 原生升级未接 | 升级只能手投/手装 | H4 / O6 |

---

## 7. 回滚

```text
.180：恢复 /opt/mynet + /var/lib/mynet（含私钥，从 P0 备份）→ 重启节点 → A1–A5
      旧二进制锚点：/var/lib/mynet/driver/gnb/bin/gnb.bak-*（如本次未动 gnb 则无需）
.182：opkg remove mynet-agent luci-app-mynet（或从备份还原 /usr/sbin、/etc/init.d、LuCI）
      → 恢复 /etc/config/* 与 nft ruleset → 恢复 crontab mynet-heartbeat
      → /etc/init.d/mynet start（旧路径）→ B1–B7
```

**红线**：回滚**不得恢复已撤销的旧 Cloud 出口许可**（[lifecycle-contract](lifecycle-contract.md) §2.6）；私钥**只从备份恢复、不重新生成**（公钥已扩散全网）；opkg 无原生 rollback，靠 `opkg list-installed` 快照 + 文件备份手还原。

---

## 8. Go / No-Go

| 条件 | 要求 |
|---|---|
| Go | 两节点 P0 备份齐全（校验和 OK）+ CI 产物架构正确（`file` 校验）+ dry-run 通过 + 回滚演练过 + 维护窗已约 |
| No-Go | 任一：备份不齐 / 架构不对 / `.182` 仍双心跳 / proxy 或 DNS 验证不过 / 误用 `linux_*` 包投 `.182` |

**建议顺序**：S0 取证 → 两节点 dry-run → 备份 → **先升 `.180`**（风险低、可单独回滚）→ 观察 → **再升 `.182`**（维护窗，D5 压力低）。

---

**关联**：`mynet_openwrt/docs/UPGRADE_PLAN_192_168_0_2.md`（.182 前置）· `.wt/.../p2-route-proxy/UPGRADE-180.md`（.180 前置）· [lifecycle-contract.md](lifecycle-contract.md)（安装/卸载/升级合同）· [deployment-registry.md](../deployment-registry.md)（部署清单 SSOT）· `spec/proxy-split.md` §11（.180/.182 实机定案）· `mynet_openwrt/docs/AGENT_INTEGRATION_PLAYBOOK.md` §5（迁移与兼容 9 条）、§7 G6
