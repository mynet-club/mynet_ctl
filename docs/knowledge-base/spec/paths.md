# 路径 SSOT —— 存储 / 运行 / 投影 / 运行态

> **地位**：路径问题的唯一事实源。安装、脚本、排障、文档一律引本文件。
> **动机**：2026-09-30 五节点取证发现同机 3–4 套 home、脚本找不到主主体
> （`archive/2026-09/evidence/20260930-subject/INDEX.md`）。路径不清 = 用户配置一团乱码。
>
> 代码对应：`mynet_agent/shared/utils/paths.go`（实现必须与本文件一致）。

---

## 1. 四类路径（先分清这四个词）

| 词 | 定义 | 谁定 | 进备份? | 可手改? |
|---|---|---|---|---|
| **存储根 Store Root** | 真相源所在根目录 | `MYNET_HOME`（install 写入） | **是** | 只改 `conf/**` |
| **运行根 Runtime Root** | 进程实际打开的目录 | **`ps` cmdline 的 `-c` 参数** | 否 | 否 |
| **投影目录 Projection** | 驱动原生配置（gnb/wg） | `driver.Apply` | 可选（易再生） | **禁**（会被覆盖） |
| **运行态 Runtime State** | map/pid/log/bundle 缓存 | 进程 | **否** | 否 |

**铁律**：
1. 排障时**运行根只认 `ps`**，不认任何「默认树」。
2. 每机**只有一棵** Store Root；其余是 legacy/残留，禁止混写。
3. `MYNET_HOME` 缺失 → **拒绝启动**，不静默落 `~/.mynet`。
4. 手改只准进 `conf/**`；改 `driver/**` 视为事故。

---

## 2. 目录布局（`$MYNET_HOME` = Store Root）

> **定稿（2026-10-12 用户裁定）——三面职责，一个可手改面**：
> · **`conf/` 是人/云端写意图与身份的唯一面**（`mode.json` 也在这里），格式 JSON（**密钥材料除外**，驱动要裸 hex）。
> · **`driver/` 是投影层**：agent 的 apply 链（mynetd 触发 → `mynet apply`）落盘，**手改会被覆盖**。
> · **`var/` 是运行态**：同样由 agent 落盘（bundle 拉取 / detector / tunnel 状态），**不进备份、手改无意义**。
> · **不再有 `mynetd.env`**：`MYNET_HOME` 由**服务单元的启动命令**给出（见 §2.1）。

```text
$MYNET_HOME/                         # Store Root（真相源根；由服务单元启动命令给出）
├── conf/                            # ★唯一可手改面（意图 + 身份），进备份
│   ├── mode.json                    #   激活谁（local|online + tenant/node/driver）
│   ├── gateways.json                #   网关列表（原 mynetd.env 的 MYNET_GATEWAYS）
│   ├── local/                       #   本地模式
│   │   ├── intent.json              #     抽象意图（无 gnb/wg 术语）
│   │   ├── proxy_intent.json        #     代理意图（双轨合一后的 Local 轨）
│   │   ├── nodes/<id>/keys/         #     节点密钥（裸材料，非 JSON；私钥 0600）
│   │   └── plugins/<name>.json      #     插件配置
│   └── online/<tenant>/             #   联网模式
│       ├── profile.json             #     租户身份面（无密钥）
│       ├── nodes/<id>/keys/
│       └── plugins/<name>.json
├── driver/                          # ★投影层：agent apply 写出，禁手改（改了会被覆盖）
│   ├── gnb/
│   │   ├── bin/                     #   gnb gnb_ctl gnb_es gnb_crypto（同套件！）
│   │   └── conf/<nodeId>/           #   node.conf route.conf address.conf proxy.conf
│   │       ├── security/            #   <id>.private / <id>.public
│   │       ├── ed25519/             #   <peer>.public
│   │       ├── gnb.map              #   ctl-block（运行态，可再生）
│   │       └── gnb.pid
│   └── wireguard/<nodeId>/wg0.conf
├── plugins/                         # 插件包体（可重装）
├── var/                             # ★运行态，不进备份（agent 落盘）
│   ├── system.json                  # detector 结果
│   ├── bundle.json (+ .etag)        # 云端 envelope 拉取落盘（唯一一份，勿在 conf/ 再放）
│   ├── tunnel-state.json
│   ├── subject.json                 # subject 解析缓存（见 §4）
│   ├── run/local-api.token
│   └── log/
├── state/
│   └── last-good/<ts>/              # Apply 成功快照
└── backups/                         # config export/import 与升级备份
```

### 2.1 启动期取值（原 `mynetd.env` 的归宿）

**`mynetd.env` 已退役**。服务单元（systemd `Environment=` / procd init / launchd plist）在启动命令里给
`MYNET_HOME`；其余运行参数从 `conf/` 读：

| 原 env 项 | 现在从哪来 |
|---|---|
| `MYNET_HOME` | **服务单元启动命令**（进程起来前就要知道，无法从 conf/ 里取 —— 先有 home 才有 conf/） |
| `MYNET_GATEWAYS` | `conf/gateways.json` |
| `MYNET_BIN` | 由 `MYNET_HOME` + 前缀推出（OpenWrt `/usr/sbin`） |
| `MYNET_EXECUTOR` | 平台默认（OpenWrt `lua`），可 `conf/` 覆盖 |

调试用的 env 覆盖（`MYNET_CONFIG_HOME` / `MYNET_DATA_HOME` / `MYNET_LOG_DIR` / `MYNET_GNB_BIN`）仍可用，
但**不是配置面**、不写进任何持久文件。

### 2.2 环境变量（覆盖用，非配置面）

| 变量 | 作用 | 缺失时 |
|---|---|---|
| `MYNET_HOME` | Store Root | **拒绝启动**（install/service/executor）——由服务单元给 |
| `MYNET_CONFIG_HOME` | 覆盖 `conf/` 位置 | 用 `$MYNET_HOME/conf` |
| `MYNET_DATA_HOME` | 覆盖 `var/` 位置 | 用 `$MYNET_HOME/var` |
| `MYNET_GNB_BIN` | 显式 gnb 可执行文件 | 按 §4 解析 |
| `MYNET_LOG_DIR` | 日志目录 | `$MYNET_HOME/var/log` |

> ⚠️ `MYNET_GATEWAYS` 从本表移除：它是**配置**，归 `conf/gateways.json`；env 里给只作一次性覆盖（调试）。

---

## 3. 平台默认 Store Root（仅 install 时写入，之后以 env 为准）

| 平台 | 默认 | 备注 |
|---|---|---|
| Linux / OpenWrt | `/var/lib/mynet` 或 `/etc/mynet` | OpenWrt 常用 `/etc/mynet`（182 实测） |
| macOS | `/usr/local/opt/mynet` 或 `/var/lib/mynet` | 多树并存是历史问题，要收敛 |
| Windows | `C:\mynet` | 183 实测；勿用 `Program Files`（权限） |

**收敛目标**：每机只剩一个 Store Root，其余改名 `*.legacy-YYYYMMDD` 或移入 backup。

> **install 写入的路径与服务登记名**（B 层目标矩阵、服务名一致性）见 [`lifecycle-contract.md`](lifecycle-contract.md) §3；安装/卸载/升级的动作合同见其 §2。

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

## 5. 双层配置：mynet 意图 vs 驱动投影

| | **mynet 自己** | **驱动 gnb / wg** |
|---|---|---|
| 位置 | `conf/**`（含 `conf/mode.json`） | `driver/**` |
| 格式 | JSON schema v2（密钥材料除外） | gnb 文本 / wg INI |
| 写入者 | 用户 / back / wizard | **仅 `driver.Apply`** |
| 手改 | 可 | **禁**（会被 apply 覆盖） |
| 真相 | 是 | 否（产物） |

**proxy 两层命名（易混）**：
- `intent.listen.proxy` / `node.conf` 的 `proxy on` = GNB 数据面准出开关
- `plugins/proxy.json` → `driver/gnb/conf/<id>/proxy.conf` = 出口选点/权重
- 「开不开」与「走谁」，不是同一件事

---

## 6. 备份与导出边界

| 进备份 | 不进备份 |
|---|---|
| `conf/**`（含 `conf/mode.json`） | `var/**`（运行态，agent 落盘） |
| `state/last-good/` | `driver/**`（可再生；需要时单独打） |
| `backups/` | `plugins/**` 包体（可重装） |
| 密钥 `nodes/*/keys/` | `gnb.map` / `gnb.pid` / logs |

私钥备份**必须**含 `conf/**/nodes/*/keys/` 或 `driver/gnb/conf/<id>/security/`（以运行根为准）。

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
