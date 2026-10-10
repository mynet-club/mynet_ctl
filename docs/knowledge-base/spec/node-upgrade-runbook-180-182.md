# 今晚升级 Runbook —— .180（proxy server）/ .182（proxy client）

> 2026-10-10 夜。**状态：可执行清单，未执行**。
> 背景/裁定见 [node-upgrade-plan-180-182.md](node-upgrade-plan-180-182.md)；合同见 [lifecycle-contract.md](lifecycle-contract.md)。
> **红线**：不覆盖 `conf/` 与密钥；不恢复已撤销的 Cloud 出口许可；**禁按名字杀进程**；**不动机器上的 smartdns**；不动 `src/`。

---

## 0. 开工前必须定（30 分钟）

| # | 事项 | 结论 / 动作 |
|---|---|---|
| **P-1** | **出包 commit** | 四仓当前**有未提交改动**（§1 清单）。CI 出包拿的是 HEAD → **不提交就缺**：G-11 UCI 修复、`-tags openwrt` 接线、安装器委托 `mynet install`、wintun 随包、ctl `kind` 迁移。**先提交再出包。** |
| **P-2** | push 授权 | CI 从 GitHub 取码，**必须 push**（需你明确批准；我不擅自 push）。 |
| **P-3** | `GUI_REPO` | 仅 mac/win 图形安装器才需要。默认 `mynet-club/mynet_gui-V2` **未确认**（workflow 从未出现过 GUI 仓名）。 |
| **P-4** | **H5 豁免声明** | `.182` 的 **LuCI 入口层本次不验**（只验 CLI/IPK/procd/数据面）；H5 的 reference 验收另补。**写进窗口记录。** |
| **P-5** | 备份 | 两节点 P0 + 密钥再备份（§4）。 |

---

## 1. Commit 锚点（提交后填 SHA）

```bash
cd /Users/arthur/Devel/WorkSpaceAI/mynet-club
for r in mynet_agent mynet_desktop mynet_ctl; do printf '%s %s\n' "$r" "$(git -C $r rev-parse HEAD)"; done | tee /tmp/night-anchors.txt
```

**锚点表（执行时填）**

| 仓 | HEAD（提交后） | 说明 |
|---|---|---|
| mynet_agent | `__________` | 出 `.180` tar 与 `.182` ipk 的源 |
| mynet_desktop | `__________` | 安装器 / 发行编排 |
| mynet_ctl | `__________` | 分发 / artifact kind / 迁移 |

### 1.1 待提交清单（截至 2026-10-10 晚）

- **mynet_agent**（HEAD `50c1b58`）
  - 改：`.githooks/guard.sh`、`.github/workflows/{build,build_openwrt}.yml`、`Makefile`、`cmd/mynetd/service_windows.go`、`mynet/cmd_install*.go`、`mynet/cmd_setup.go`、`mynet/prefix_windows.go`、`mynet/units.go`、`mynet/units/openwrt_mynet.init`、`packaging/{darwin,openwrt}/INSTALL.md`、`shared/utils/paths.go`
  - 删：`packaging/darwin/{build_pkg.sh,scripts/}`（重复实现退役）
  - 新：`mynet/{cmd_install_systemd,cmd_install_unsupported,openwrt_detect,platform_descriptor,units_openwrt,units_systemd}.go`、对应 `_test.go`、`mynet/lifecycle_contract_test.go`、`mynet/platform_filetags_test.go`
  - ⚠️ `docs/MYNET_V1_PLAYBOOK.md` 是**历史遗留改动**（非本轮），单独判断是否一起提交。
- **mynet_desktop**（HEAD `4ed21f2`）
  - 改：`.githooks/guard.sh`、`.github/workflows/release.yml`、`README.md`、`docs/INSTALL.md`、`macos/{build-pkg.sh,scripts/*}`、`nsis/{build-nsis.sh,mynet-agent.nsi}`、`scripts/{check.sh,install-linux.sh}`
  - 删：`launchd/club.mynet.mynetd.plist`、`nsis/service.ps1`、`systemd/mynetd.service`
  - 新：`.guard-c4-allow`、`nsis/vendor/wintun/wintun.dll`、`nsis/wintun-provenance.md`
  - ⚠️ **幽灵条目**：`scripts/fetch-wunint.sh` 在 `git status` 里仍是 `??`，但 OS 里**已不存在**（untracked 缓存陈旧）。提交时**不要 add 它**；若 `git add -A` 报错，用显式路径 add。
- **mynet_ctl**（HEAD `084496c`）
  - 改：`.githooks/guard.sh`、`app/Domain/Release/Services/ReleaseDomainService.php`、`app/Http/Controllers/Admin/ReleaseAdminController.php`、`app/Models/Release.php`、`app/Services/{Manifest/ManifestService,Release/ReleaseService}.php`、`resources/views/admin/releases/create.blade.php`、`tests/Unit/ReleaseFilenameTest.php`、知识库文档若干、`templates/.githooks/guard.sh`
  - 新：`database/migrations/2026_10_10_000001_add_kind_to_releases_table.php`、`docs/knowledge-base/spec/{lifecycle-contract,node-upgrade-plan-180-182,platform-lifecycle-build-playbook,workspace-review-20261010}.md`
- **mynet_api**：2 文件（本地记录用；本次升级不涉）。

---

## 2. 出包（CI，push 之后）

| 节点 | 工作流 | 取哪个 artifact |
|---|---|---|
| `.180` | `mynet_agent` → `build-agent` | `mynet_agent_linux_amd64`（内含 `mynet_agent_linux_amd64_<ver>.tgz`） |
| `.182` | `mynet_agent` → `build-openwrt-sdk` | `openwrt-ipk-amd64`（`mynet-agent_*_amd64.ipk` + `luci-app-mynet_*.ipk`） |

**落盘后必校**：
```bash
file  mynet mynetd            # 期望 ELF x86-64
sha256sum -c checksums.txt    # 或与服务端清单比对
strings mynet | grep -q 'mynet.ApplyProxy' && echo "proxy 能力在"
strings mynet | grep -q '1.6.8-c411f14ee647' && echo "版本戳在"
# .182 额外：ipk 内不含 embed WebUI（瘦）
```

---

## 3. S0 取证（两节点，只读，先做）

```bash
KEY=~/.cache/crtvault/scratch/arthur_ssh_id_ed25519/id_ed25519   # 缺则先 crt materialize arthur/ssh/id_ed25519
# .180（公网）
ssh -i $KEY root@52.221.22.241 '
  /usr/sbin/mynet --version 2>/dev/null || echo "NO-AGENT(旧形态)"
  /usr/sbin/mynetd --version 2>/dev/null || true
  ps -o pid,cmd -C gnb,mynetd,gnb_es ; ls -l /etc/init.d/{mynet,mynetd} 2>/dev/null
  cat /var/lib/mynet/mode.json 2>/dev/null ; ls /var/lib/mynet/driver/gnb/conf/
  cat /var/lib/mynet/driver/gnb/conf/*/proxy.conf 2>/dev/null | head
  nft list ruleset 2>/dev/null | head -5 ; iptables -t nat -L POSTROUTING -n 2>/dev/null | head'
# .182（局域网）
ssh -i $KEY root@192.168.0.2 '
  opkg list-installed | grep -E "mynet|luci-app-mynet" || echo "无 agent 包（旧 Lua 形态）"
  ps -o pid,cmd -C gnb,mynetd,gnb_es ; ls -l /etc/init.d/{mynet,mynetd} 2>/dev/null
  cat /etc/mynet/mode.json 2>/dev/null ; ls /etc/mynet/driver/gnb/conf/
  nft list ruleset | grep -c mynet_proxy ; ip rule ; ip route show table mynet_proxy
  crontab -l | grep mynet-heartbeat ; ps | grep -i smartdns | head -3'
```

**判读**：有 `mynet`/`mynetd` → 走**刷新**；只有 Lua → 走**迁移**（`.182` 属后者时风险↑，见计划 §3.B）。

---

## 4. 备份（P0 / Phase 0，未备份不开跑）

```bash
# .180 —— ⚠️ 私钥在 /var/lib/mynet/.../security/，不是 opt/mynet（踩坑 #11）
ssh -i $KEY root@52.221.22.241 '
  BK=/tmp/mynet-cfg-$(date +%Y%m%d_%H%M%S); mkdir -p $BK
  tar -C / -czf $BK/_opt_mynet.tgz opt/mynet
  tar -C / -czf $BK/_etc_mynet.tgz etc/mynet
  tar -C / -czf $BK/_var_lib_mynet.tgz var/lib/mynet
  sha256sum $BK/* > $BK/CHECKSUMS; ls -l $BK'
# .182
ssh -i $KEY root@192.168.0.2 '
  BK=/tmp/mynet-cfg-$(date +%Y%m%d_%H%M%S); mkdir -p $BK
  tar -czf $BK/_etc_mynet.tgz /etc/mynet
  tar -czf $BK/_etc_config.tgz /etc/config
  nft list ruleset > $BK/nft-ruleset.txt
  ip rule > $BK/ip-rule.txt ; ip route show table mynet_proxy > $BK/route-mynet_proxy.txt
  crontab -l > $BK/crontab.txt ; opkg list-installed > $BK/opkg-list.txt
  sha256sum $BK/* > $BK/CHECKSUMS; ls -l $BK'
```
**备份件不得进工作区/git**（只 `tar -tzf` 列名核对）。密钥再备份按你要求单独做一份。

---

## 5. `.180` 升级（低风险，建议先做）

```bash
# P1 投递
scp -i $KEY mynet mynetd root@52.221.22.241:/tmp/upgrade/    # 先不覆盖
# P2 dry-run（零写盘）
ssh -i $KEY root@52.221.22.241 '/tmp/upgrade/mynet install --dry-run --home /var/lib/mynet --gateways wss://gw.mynet.club/v1/node/connect'
# P3 维护窗
ssh -i $KEY root@52.221.22.241 '
  # 停节点实例：pidfile+cmdline 精确匹配；**index -P:9016 不动**
  install -m0755 /tmp/upgrade/mynet /tmp/upgrade/mynetd /usr/local/bin/
  mynet install --home /var/lib/mynet --gateways wss://gw.mynet.club/v1/node/connect
  systemctl restart mynetd'
# P4 观察 24h 后删旧二进制（保留 .bak）
```

**验证 A**：A1 mynetd running/env 正确 · A2 gnb 实例在、tun UP、index 未受影响 · A3 `proxy.conf` role=server/`proxy on`/`ingress_open on`、对端能出网 · A4 Lightsail UDP 入站 + 机内 `nft/iptables INPUT` 放行 + `POSTROUTING` MASQUERADE · A5 对端 VIP ping 通、`mynet route doctor`。

---

## 6. `.182` 升级（维护窗；**LuCI 不验**）

```bash
# Phase 1 投递 ipk
scp -i $KEY 'mynet-agent_*_amd64.ipk' 'luci-app-mynet_*.ipk' root@192.168.0.2:/tmp/
# Phase 2 dry-run（节点或 VM）
ssh -i $KEY root@192.168.0.2 '/usr/sbin/mynet install --dry-run --home /etc/mynet --gateways wss://gw.mynet.club/v1/node/connect'
# Phase 3
ssh -i $KEY root@192.168.0.2 '
  set -e
  /etc/init.d/mynet stop 2>/dev/null || true        # procd 精确停；禁 pkill
  crontab -l | grep -v mynet-heartbeat | crontab -  # 摘旧 cron 心跳（防双心跳）
  opkg install /tmp/mynet-agent_*_amd64.ipk /tmp/luci-app-mynet_*.ipk
  mynet install --home /etc/mynet --gateways wss://gw.mynet.club/v1/node/connect
  /etc/init.d/mynetd start ; /etc/init.d/mynet start'
```
> ⚠️ `.182` 若 S0 显示是**旧 Lua 形态**（无 mynet/mynetd），这是**迁移**不是刷新 → 先确认 `mynet install` 的 OpenWrt 装配（G-11 已修）在 VM 过过，否则**今晚不升它**。

**验证 B**：B1 mynetd running、`MYNET_EXECUTOR=lua`、心跳只走 mynetd · B2 procd 实例、tun `gnb_tun_16` UP · B3 `table mynet_proxy`/proxy.conf role=client 在 · B4 出口授权生效、撤销后旧连接不复用（fail-closed）· B5 分流抽查 · **B6 dnsmasq→smartdns 链未破坏** · B7 记录"LuCI 本次未验"。

---

## 7. 回滚

```bash
# .180
ssh -i $KEY root@52.221.22.241 '
  systemctl stop mynetd
  tar -C / -xzf /tmp/mynet-cfg-*/_opt_mynet.tgz
  tar -C / -xzf /tmp/mynet-cfg-*/_var_lib_mynet.tgz     # 含私钥
  systemctl start mynetd'
# .182
ssh -i $KEY root@192.168.0.2 '
  /etc/init.d/mynet stop ; /etc/init.d/mynetd stop
  opkg remove luci-app-mynet mynet-agent                # 或从备份还原 /usr/sbin、/etc/init.d、LuCI
  tar -xzf /tmp/mynet-cfg-*/_etc_config.tgz -C /        # uci 配置
  nft -f /tmp/mynet-cfg-*/nft-ruleset.txt               # 防火墙
  crontab /tmp/mynet-cfg-*/crontab.txt                  # 恢复 cron 心跳
  /etc/init.d/mynet start'
```
**红线**：回滚**不恢复已撤销的 Cloud 出口许可**；私钥**只从备份恢复、不重新生成**。

---

## 8. Go / No-Go

| Go | No-Go |
|---|---|
| P0 备份齐全且校验和 OK · 产物架构正确（`file`）· dry-run 通过 · 回滚演练过 · 窗口已约 | 备份不齐 / 架构不对 / `.182` 仍双心跳 / proxy 或 DNS 验证不过 / **误把通用 linux 包装到 `.182`** / `.182` 是 Lua 形态而 VM 未过 |

---

## 9. 窗口记录（执行时填）

| 项 | 值 |
|---|---|
| 出包锚点 | agent `______` / desktop `______` / ctl `______` |
| 出包 run id / artifact | `______` |
| 开始 / 结束（本地时间） | `______` |
| `.180` 结果 | A1–A5：`______` |
| `.182` 结果 | B1–B7：`______`（**LuCI 未验**） |
| 回滚？ | 否 / 是（原因：`______`） |
| 遗留 | `______` |
