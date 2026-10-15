# 路径 SSOT —— prefix / home / 原生配置 / 运行投影

> **地位**：路径问题的唯一事实源。安装、脚本、排障、文档一律引本文件。
> **动机**：2026-09-30 五节点取证发现同机 3–4 套 home、脚本找不到主主体
> （`archive/2026-09/evidence/20260930-subject/INDEX.md`）。路径不清 = 用户配置一团乱码。
>
> 代码对应：`mynet_agent/shared/utils/paths.go`（实现必须与本文件一致）。
>
> **2026-10-14 定稿**：unix 系 prefix/home 统一 `/opt/mynet`；系统级唯一、无用户级模式；
> `/var` 只放运行时；「投影」一词专指运行时副本。

---

## 1. 四类路径（先分清这四个词）

| 词 | 定义 | 谁定 | 进备份? | 可手改? |
|---|---|---|---|---|
| **prefix** | 程序安装根（`bin/`） | 平台默认或 `--prefix` / `MYNET_PREFIX` | 否 | 否 |
| **home（Store Root）** | 配置/数据/脚本/辅助的真相源根 | `MYNET_HOME`（install 写入服务单元） | **是** | 只改 `conf/**` |
| **原生配置 Native conf** | apply 把 intent/bundle 渲染成驱动认的格式（node.conf / wg.conf…） | `mynet apply` | **是**（与密钥同居 conf） | **禁**（会被再生成覆盖） |
| **运行投影 Projection** | **专为运行时**服务的副本（进程 `-c` 打开的目录） | `mynet apply` 落盘 | **否**（可丢可重投） | **禁** |

**铁律**：
1. 排障时**运行根只认 `ps`**，不认任何「默认树」。
2. 每机**只有一棵** home；其余是 legacy/残留，禁止混写。
3. home 缺失 → 用**平台默认**（§3），禁止猜 exe/cwd、禁止猜用户目录。
4. 手改只准进 `conf/**`；直接改运行投影视为无效（会被重投）。
5. **`/var` 不是 home**——`/var/run/mynet/<nodeId>` 只放运行投影，重启可空。
6. **起服务前必须先 apply 投影**（`service start` 已内置）。

---

## 2. 目录布局

> **定稿（2026-10-14 用户裁定）**：
> · **二进制**：unix 系统一 `<prefix>/bin`，prefix 默认 **`/opt/mynet`**（linux/mac/bsd）。
> · **home**：系统级唯一（节点守护要建 TUN/写路由/持密钥，**不做用户级模式**）。
>   unix = **`/opt/mynet`**（与 prefix 同树）、OpenWrt = `/etc/mynet`、Windows = `C:\ProgramData\MyNet`。
> · **`conf/`**：意图 + 身份 + **原生配置**（apply 渲染结果），进备份。
> · **运行投影**：目标位 **`/var/run/mynet/<nodeId>/`**（gnb `-c` 指这里；map/pid 也落这）。
>   `/var/run` 常为 tmpfs、重启即空 → **起服务前必须先 apply 投影**。
> · **「投影」只指运行副本**；conf 里的驱动文件叫**原生配置**，不叫投影。

> **节点唯一存放位（2026-10-13 D4-E，现行代码）**：`<home>/conf/<form>/<nodeId>/`
> （密钥 + 原生配置同居）。`form = local | online` 是获取 key 的形式，不是平行密钥面；
> nodeID 全局唯一。gnb 协议内布局不动。
> **运行投影（2026-10-14 已实施）**：apply 将原生配置投到 `/var/run/mynet/<nodeId>/`，
> gnb `-c` 指这里；`service start` **先投影再起进程**。`MYNET_RUN_ROOT` 可覆盖（测试）。

```text
/opt/mynet/                            # unix：prefix 与 home 同树（系统级）
├── bin/                               # mynet · mynetd · mynet_tray · gnb 四件套
├── conf/                              # ★唯一可手改面（意图 + 身份 + 原生配置），进备份
│   ├── mode.json                      #   激活谁（local|online + tenant/node/driver）
│   ├── gateways.json                  #   网关列表
│   ├── local/ | online/
│   │   └── <nodeId>/                  #   ★节点唯一存放位
│   │       ├── meta.json
│   │       ├── security/ ed25519/     #   密钥
│   │       ├── node.conf route.conf address.conf [proxy.conf | wg.conf]  # 原生配置
│   │       └── …
│   └── plugins/
├── plugins/                           # 插件包体
├── var/                               # 可丢缓存（bundle/system.json/token/log…），不进备份
├── state/last-good/<ts>/              # apply 成功快照
└── backups/

/var/run/mynet/<nodeId>/               # ★运行投影（可丢；重启后先 apply 再起服务）
├── （原生配置副本，gnb -c / wg 指这）
├── gnb.map · gnb.pid                  # 运行脏文件不进 conf
└── …
```

**Windows**：prefix=`C:\Program Files\MyNet`，home=`C:\ProgramData\MyNet`，无 `/var/run`（投影位另定，维持现状）。
**OpenWrt**：prefix=`/usr`（ipk → `/usr/sbin`），home=`/etc/mynet`。

### 2.1 启动期取值

**`mynetd.env` 已退役**。服务单元写死 `MYNET_HOME`；CLI 无 env 时用平台默认：

| 项 | 来源 |
|---|---|
| `MYNET_HOME` | 服务单元启动命令；CLI：env > `PlatformDefaultHome()` |
| `MYNET_GATEWAYS` | `conf/gateways.json` |
| `MYNET_BIN` | `<prefix>/bin/mynet` |
| 运行投影 | `apply` 落 `/var/run/mynet/<nodeId>/`（目标；现行见 §5） |

调试用 env 覆盖（`MYNET_CONFIG_HOME` / `MYNET_DATA_HOME` / `MYNET_LOG_DIR` / `MYNET_GNB_BIN`）仍可用，
但**不是配置面**、不写进任何持久文件。

### 2.2 环境变量（覆盖用，非配置面）

| 变量 | 作用 | 缺失时 |
|---|---|---|
| `MYNET_HOME` | home | **平台默认**（§3）——不猜 exe/cwd |
| `MYNET_PREFIX` | 程序前缀 | 平台默认 / exe 反推 |
| `MYNET_CONFIG_HOME` | 覆盖 `conf/` 位置 | 用 `$MYNET_HOME/conf` |
| `MYNET_DATA_HOME` | 覆盖 `var/` 位置 | 用 `$MYNET_HOME/var` |
| `MYNET_GNB_BIN` | 显式 gnb 可执行文件 | 按 §4 解析 |
| `MYNET_LOG_DIR` | 日志目录 | `$MYNET_HOME/var/log` |

---

## 3. 平台默认（SSOT = 代码 `PlatformDefaultHome` / `PlatformDefaultPrefix`）

> 代码：`mynet_agent/shared/utils/paths.go`。install 可不显式给 home/prefix，落到下表默认。

| 平台 | prefix（程序） | **home（配置/数据）** | 运行投影 |
|---|---|---|---|
| **linux / mac / bsd** | **`/opt/mynet`** | **`/opt/mynet`** | `/var/run/mynet/<nodeId>` |
| **OpenWrt** | `/usr`（→ `/usr/sbin`） | `/etc/mynet` | `/var/run/mynet/<nodeId>` |
| **Windows** | `C:\Program Files\MyNet` | `C:\ProgramData\MyNet` | （无 /var/run，维持现状） |

**系统级唯一，无用户级模式**。多环境只走显式 `--home` / `MYNET_HOME` 换树，不是第二套内建模式。

**现场多树（历史，待收敛）**：`/var/lib/mynet`、`/usr/local/opt/mynet`、`/usr/local/mynet` 等
（181/180/188 实测，见 §4）——一律视为 legacy，新装不写、排障只作候选列出。

**收敛目标**：每机只剩一棵 home（`/opt/mynet` 或 `/etc/mynet`），其余改名 `*.legacy-YYYYMMDD` 或移入 backup。

> **install 写入的路径与服务登记名**见 [`lifecycle-contract.md`](lifecycle-contract.md) §3。

---

## 4. 运行根解析（Subject Locator）

**问题**：`gnb`/`gnb_ctl`/conf 分居异构树 → 脚本找不到主主体。

**规则（R1–R6）**：

| # | 规则 | 说明 |
|---|---|---|
| R1 | **进程锚优先** | `ps`/`/proc` 的 cmdline `-c` 与 exe 路径是唯一真相 |
| R2 | **同构建配套** | `gnb_ctl`/`gnb_es` 必须与 `gnb` **同目录**；缺则 `tools_missing`，**禁止**捡异构建 |
| R3 | **多 home 列候选** | 输出 `candidates[]` + `chosen` + `why`，不猜 |
| R4 | **`--systemd` map 探测** | conf 目录 `gnb.map` 与 `/tmp/gnb.<port>.map` 取 mtime 新且 node_num 合理者 |
| R5 | **工具缺失显式** | 不静默；错误信息带「不要捡其它树的 ctl」 |
| R6 | **Windows 认 .exe** | 路径 Clean 比较 |

**CLI**：`mynet subject [--pid N | --node-id ID | --conf DIR] --json`
**缓存**：`$MYNET_HOME/var/subject.json`（供脚本读，不猜树）

**各节点活主体（2026-09-30 实测，收敛前先认这个表）**：

| 节点 | Store Root | 运行根（ps `-c`） | 同目录 ctl? |
|---|---|---|---|
| 181 mac | `/var/lib/mynet` | `/var/lib/mynet/driver/gnb/conf/3764021915150630` | ✗ |
| 180 linux | `/var/lib/mynet` | `/var/lib/mynet/driver/gnb/conf/4997342800601732` | ✗（ctl 在异构建 opengnb） |
| 182 owrt | `/etc/mynet` | `/etc/mynet/driver/gnb/conf/8283289760467957` | ✓ |
| 188 linux | `/opt/mynet` | **`/opt/mynet/var/gnb`** | ✗ |
| 183 win | `C:\mynet` | `C:\mynet\driver\gnb\conf\8107260975133902` | ✓ |

---

## 5. 双层配置：意图/原生配置 vs 运行投影

| | **mynet 自己（意图）** | **原生配置** | **运行投影** |
|---|---|---|---|
| 位置 | `conf/**`（mode/intent/密钥） | `conf/<form>/<nodeId>/` 驱动原生布局 | `/var/run/mynet/<nodeId>/`（目标；现行仍指 conf 节点目录） |
| 格式 | JSON schema v2（密钥材料除外） | gnb 文本 / wg INI | 原生配置副本 + map/pid |
| 写入者 | 用户 / 后端 / wizard | **仅 apply 渲染** | **仅 apply 投影** |
| 手改 | 可 | **禁**（再生成覆盖） | **禁**（重投） |
| 真相 | 是 | 是（配置面产物，进备份） | 否（运行副本，可丢） |

> **用词**：conf 里的驱动文件 = **原生配置**，不叫投影；**投影**只指 `/var/run` 运行副本。

**proxy 两层命名（易混）**：
- `intent.listen.proxy` / `node.conf` 的 `proxy on` = GNB 数据面准出开关
- `plugins/proxy.json` → `conf/<form>/<nodeId>/proxy.conf` = 出口选点/权重
- 「开不开」与「走谁」，不是同一件事

---

## 6. 备份与导出边界

| 进备份 | 不进备份 |
|---|---|
| `conf/**`（含 `conf/mode.json`） | `var/**`（运行态，agent 落盘） |
| `state/last-good/` | 旧 `driver/**` 残留（可再生；需要时单独打） |
| `backups/` | `plugins/**` 包体（可重装） |
| 密钥 `conf/<form>/<nodeId>/`（security/、wg.conf） | `gnb.map` / `gnb.pid` / logs |

私钥备份**必须**含 `conf/<form>/<nodeId>/` 的密钥面（`security/` 或 `wg.conf`，D4-E）；
未迁移节点仍按旧口径 `conf/**/nodes/*/keys/` 或 `driver/gnb/conf/<id>/security/`（以运行根为准）。

---

## 7. 排障速查

| 症状 | 先查 |
|---|---|
| 改了配置不生效 | 改的是 `conf/**` 还是 `driver/**`？Apply 跑了吗？ |
| 脚本找不到 gnb | `mynet subject --json`；`ps` 的 `-c` |
| `gnb_ctl` 读出垃圾 | 是否异构建？同目录吗？（R2） |
| 两处配置打架 | 有几个 Store Root？`tree doctor` |
| 私钥找不到 | 运行根的 `security/`，不是 Store Root 的 |
| 路径不对 | 本表 §2–§4 |

---

**关联**：`archive/2026-09/core-remediation-plan-20260930.md` 主线② · `archive/2026-09/evidence/20260930-subject/` · `shared/utils/paths.go`
