# 主体快照证据索引 — 2026-09-30 P0

> 原始文件：本目录 `*.txt`。此处只收**对照结论**，供 P1 Subject Locator 当验收靶。

---

## 1. 对照表（活主体 vs 找得到的工具）

| 节点 | 活 gnb（ps 为准） | 活 conf (`-c`) | 同目录 `gnb_ctl`? | 其它树/残留 | 判定 |
|---|---|---|---|---|---|
| **181 mac** | `/var/lib/mynet/driver/gnb/bin/gnb` | `/var/lib/mynet/driver/gnb/conf/3764021915150630` | **无**（只有 gnb+bak） | 4 树：`/opt/mynet`、`/usr/local/opt/mynet`、`/usr/local/mynet`、`/usr/local/bin` 都有 ctl | ⚠️ 活主体缺工具 |
| **180 linux** | `/var/lib/mynet/driver/gnb/bin/gnb` | `/var/lib/mynet/driver/gnb/conf/4997342800601732` | **无** | `gnb_ctl` 只在 `/usr/local/opengnb/bin/`（**异构建**）；另有 index 进程 `/usr/local/mynet/.../gnb -P -l 9016`（**另一棵树**） | ❌ 异构建 ctl 读表已证伪（node 2322817668/0.0.0.0） |
| **182 owrt** | `/etc/mynet/driver/gnb/bin/gnb` | `/etc/mynet/driver/gnb/conf/8283289760467957` | **有** gnb_ctl+gnb_es | `bin.bak-1.6.0/`、`bin.old/` 备份树 | ✅ 唯一干净样例 |
| **188 linux** | `/opt/mynet/bin/gnb --systemd` | **`/opt/mynet/var/gnb`**（admin 无权限读） | **无**（bin 只有 gnb/mynet/mynetd） | `driver/gnb/conf/7676…/` 是另一棵；但其 `gnb.map` mtime 在更新 → 可能是 `--systemd` 把 ctl-block 写这 | ❌ 路径双义最严重 |
| **183 win** | `C:\mynet\driver\gnb\bin\gnb.exe` | `C:\mynet\driver\gnb\conf\8107260975133902` | **有** gnb_ctl.exe | `C:\Program Files\mynet`、`C:\mynet-test`、`C:\mynet\mynet-win-pkg\`（重复二进制） | ⚠️ 进程锚干净，但 3 套 home 并存 |

---

## 2. 五个「找不到主主体」的具象模式

1. **活目录没有 ctl**（181/180/188）→ 脚本去别的树捡 ctl → 异构建读表错。
2. **`-c` 路径 ≠ find 出来的 conf 树**（188：`/opt/mynet/var/gnb` vs `driver/gnb/conf/7676…`）→ 不知信谁。
3. **同机多 home**（181 四树、183 三套、180 双树：node + index）→ 默认路径猜必错。
4. **`--systemd` 改写 map 约定**（188）→ conf 目录 `gnb.map` 可能是残留也可能被写，mtime 不可靠单独作判据。
5. **备份树/安装介质残留**（182 `bin.bak`、183 `mynet-win-pkg`、180 `opengnb`）→ `find` 一把抓会捡到错版本。

---

## 3. 对 P1 的硬需求（从证据反推）

`Resolve(nodeID|pid|confArg)` 必须：

| # | 规则 | 证据来源 |
|---|---|---|
| R1 | **进程锚优先**：`cmdline -c` 与 `/proc/exe` 是唯一真相 | 188 双树 |
| R2 | **ctl 与 gnb 同目录**，否则报错，不回退 | 180 异构建读表错 |
| R3 | 多 home 时输出 `candidates[]` + `chosen` + `why` | 181/183 |
| R4 | `--systemd` 时 map 位置需探测（conf 与 `/tmp/gnb.<port>.map`） | 188 |
| R5 | 无 gnb_ctl 时明确 `tools_missing`，禁止静默 | 181/180/188 |
| R6 | Windows 路径/大小写/`.exe` 同规则 | 183 已相对干净，但 win-pkg 残留会干扰 |

---

## 4. 原始文件

| 文件 | 内容 |
|---|---|
| `local-181-mac.txt` | 本机四树 + 活进程 |
| `180-linux.txt` | 双进程（node/index）+ opengnb 异构建 + last-good |
| `182-openwrt.txt` | 干净单树 + bak 备份 |
| `188-linux.txt` | `--systemd` + `/opt/mynet/var/gnb` + driver 树 map 在动 |
| `183-windows.txt` | 三套 home + win-pkg 重复二进制 |
