# v1 交付收口 Playbook

2026-10-15。用户授权：建立 playbook 后持续开发。默认单执行者，按依赖连续推进；不自动启动子代理或后台定时任务。

## 1. 定位与范围

MyNet 让用户加入隔离的网络空间，管理节点，并按授权连接节点、实体网络与服务。
首个参考场景为企业员工电脑经获准出口访问远程网络；部署包括 API、gateway、节点执行器、GUI/LuCI、分发中心。
本轮收口已有能力，不重建领域模型，不实现 UTC、计费、贡献市场、复杂选路，不改 GNB 上游 src。
沿用 v1 Playbook、生命周期 H0–H6、OpenWrt O 段、Proxy L1/L2；本文仅编排交付缺口，不重编号既有任务。

## 2. 当前基线与证据

本机 2026-10-15：agent `make check` 通过；API 519 tests/4908 assertions/1skip；ctl 82/467/1skip；GUI 58 tests，类型棘轮183条/31文件；desktop40项通过。
agent HEAD 6f876f8、API 6b13644、GUI 4fd64b9、ctl c9a7c0f、desktop739ac90、OpenWrt6d7be69。六仓工作树均干净；API/OpenWrt 相对本地跟踪引用领先25/8，未 fetch，非远端实时结论。
guard 在干净工作树没有检查文件，不能视作全仓安全复检。上述本机测试不等于真实设备、真实流量或生产验收。

## 3. 冻结合同与行为决策

| 对象/边界 | 唯一权威与不变量 |
|---|---|
| 节点 | 字符串 NodeID、归属与授权仍由 API 决定；不改变密钥布局、生命周期或 wire 字段 |
| home | 显式 MYNET_HOME > 平台默认；Unix /opt/mynet、OpenWrt /etc/mynet、Windows ProgramData；不猜 cwd/exe，不创建目录来读取路径；相对路径拒绝 |
| 运行态路径 | MYNET_DATA_HOME（完整运行态目录）> home/var；snapshot与执行器复用纯解析接口，读取不创建目录；移除~/.mynet回退 |
| install | 保留 --home 与环境冲突拒绝的现有合同；不增加所有子命令通用 --home，避免与现有 flag parser 冲突 |
| bundle | 沿用 checksum/generation 与既有 Cloud/Local 优先级；乱序拒绝、撤销不能由回滚重新开放 |
| 发行 | product/platform/arch/version/kind 沿用 ctl；桌面/服务器 embed 原生 WebUI 与同源 GUI；OpenWrt 独立 openwrt 标签，无 GUI |
| 来源 | agent/gui/desktop 的实际提交 SHA 必须进入发布证据；未知 ref 和缺 GUI 失败，不回退默认分支冒充锁定版本 |
| 执行权 | mynetd 只通信；系统写面归 mynet；安装器委托 install；gateway 透传、不承担业务鉴权 |

本轮无数据库迁移、无密钥轮换。路径只统一解析，不搬移旧树；旧树迁移仍走显式迁移命令、备份与失败停止。
CLI 旧的“必须设置环境变量”由平台默认替代；环境覆盖继续保留。发行修复先本地构建与检查，线上推广后才能声明发布完成。

## 4. 工作包与接口

| 包 | 责任/文件边界 | 输入→输出、验收 | 依赖/禁止 |
|---|---|---|---|
| DC-1 | agent commands、路径读取助手、状态/驱动查找 | env/default→唯一绝对home；入口拒绝相对路径；无env可进入命令；显式env优先，cwd不参与，读路径无写入 | 立即；不改install参数冲突语义、不迁移数据 |
| DC-2 | agent Makefile/scripts/build.yml；desktop构建调用 | GUI dist→内嵌目录；共享准备脚本校验index/assets，缺失/错误前缀失败；CI明确GUI ref，记录实际SHA，原生WebUI与GUI可从编译产物读取 | DC-1可独立；不发布测试产物、不改变OpenWrt瘦构建 |
| DC-3 | agent打包、desktop/ctl发行来源证据 | 固定多仓SHA→包哈希/来源记录；验证真实打包内容、kind选择、失败不产生可发布半包 | DC-2；不把metadata存在等同正确，不上传、不部署 |
| DC-4 | Proxy既有测试/隔离场景工具 | 复用L1/L2 §10：授权/拒绝/撤销、离线fail-closed、旧连接、并发apply、通知补拉、重放；输出逐项真实流量证据 | DC-3后；先确认隔离设备，禁止用主路由做破坏性实验 |
| DC-5 | 平台生命周期原有H/O段与验收工具 | 新装→接入→启停→升级→卸载→恢复；四平台分开记代码/编译/模拟/实机结果 | DC-3；Wintun异常根治走补丁包；无目标机不勾实机完成 |
| DC-6 | 知识库入口、发布记录 | 更新可追溯状态和剩余项，代码交付、实机、线上三个状态分列 | 持续；不按历史勾选宣布完成 |

DC-1 → DC-2 → DC-3 → DC-4/DC-5 → DC-6。可并行性仅说明文件边界；当前顺序执行。
内部优先复用 `utils.RequireMyNetHome() (string,error)`、`GetMyNetHome()`；准备GUI接口为 `sh scripts/prepare-gui.sh SOURCE DEST`，返回0成功，非0拒绝；不添加业务wire字段。

## 5. 安全、失败与回滚

路径/构建处理为metadata-only或静态资产复制，不接触用户流量payload；不保存原始bundle、口令、cookie或私钥。凭证只经crt/crtvault。
审计证据仅含版本、提交、平台、命令退出码、包哈希、脱敏节点指纹。构建错误立即停止；先校验后替换内嵌GUI，避免错误输入破坏已有目录。
所有测试使用临时目录或隔离设备；不自动升级/重启现网。需要设备或发布授权时保留未验项，继续不依赖设备的开发。
DC-1/DC-2回滚为恢复代码与重新构建，不回滚生产数据；流量变更先比较计划再隔离单点应用，失败恢复自身规则，不能恢复已撤销许可。

## 6. 验收与交付

- DC-1：默认/覆盖/相对路径拒绝/cwd不参与/无写入的行为测试；`make check`。
- DC-2：准备脚本真实执行的成功、缺index、错误前缀、缺asset、陈旧文件清理测试；embed构建可读取两套页面；CI输入和SHA记录静态检查须明确标静态。
- DC-3：包内资产与provenance校验、归档/安装器选择错误拒绝；目标矩阵编译。
- DC-4/DC-5：每项记录环境、产物SHA、前置、动作、期望/实际、清理和回滚；超时、并发、重放、撤销及断网不可省略。
- 各包交付记录：文件、接口、行为/迁移/安全影响、命令与结果、限制、后续依赖；不扩大测试为全平台验收。

## 7. 执行账

| 包 | 开发 | 本机验证 | 实机/线上 |
|---|---|---|---|
| DC-1 | 已实现 | make check、路径/入口/快照行为测试通过 | 按用户要求暂缓 |
| DC-2 | 已实现 | 准备脚本正/拒绝/重跑；embed HTTP首页+JS/CSS；标签测试通过；workflow静态校验 | CI未执行，实机暂缓 |
| DC-3 | 归档侧+安装器侧已实现；上传/实机验收待续 | 归档：三平台真实归档fixture、来源一致/冲突拒绝、GUI指纹。安装器：SOURCES 嵌入+sidecar、check-installer-sources 复核、构建脚本缺来源/kind错配拒绝不产半包；workflow 已存在版本做来源复核后才 SKIP；目标矩阵六腿交叉编译通过 | 上传/安装器验收未执行 |
| DC-4 | 复用L1/L2清单 | 不勾实测 | 用户明确暂缓实机验收 |
| DC-5 | 复用H/O合同 | Windows/Linux/OpenWrt交叉编译通过；非实机运行 | 用户明确暂缓实机验收 |
| DC-6 | 本文已建立 | 已核对本机基线 | 不适用 |

### 2026-10-15 首批开发证据

- DC-1：`mynet/commands.go`使用requireHome；conf/status/deviceinfo/驱动查找统一平台home；`shared/utils.VarDirPath`新增纯解析接口，snapshot不再回退用户目录。新增commands_home与system_snapshot_path测试。`env -u MYNET_HOME go run ./cmd/mynet status`已进入status，读取`/opt/mynet/var/system.json`；本机普通用户权限拒绝如实显示，不表示安装节点健康。
- DC-2：`scripts/prepare-gui.sh SOURCE DEST`校验入口引用并阶段替换；Makefile/CI共用；embed禁止磁盘GUI回退，开发/无embed保留原路径模式。`check-embedded-ui.go`通过本机随机端口实测/ui/、/gui/及入口JS/CSS均200；使用已有GUI dist，未声称本轮重新构建GUI源码。
- DC-3：`build-sources.sh AGENT GUI GUI_DIST DESKTOP_OR_DASH OUTPUT`记录实际SHA、tracked_dirty、GUI资产树SHA256；tracked_dirty不包含未跟踪文件，源码SHA不能单独证明本地脏树可复现。`pack.sh`预检mynet+mynetd+INSTALL+SOURCES、macOS/windows使用zip、临时归档验证后替换；CI复用同一打包器。同版本已存在时解出SOURCES逐字比对，来源不一致拒绝；HTTP非200/404停止。
- 自动化：agent make check通过（实际扫描本批改动）；Windows amd64/Linux amd64全仓与OpenWrt mipsle执行器/daemon交叉编译通过；shellcheck、workflow YAML解析和各run块bash语法通过（静态，不冒充CI运行）；新增真实脚本/归档行为测试通过。
- 本轮只改agent与ctl知识库；不部署、不上传、不轮换密钥、不操作实机。用户选择“先完成本地开发，实机验收暂缓”。下一本地包：DC-3安装器来源记录与复核；DC-4/5的实机项继续保留未验，不据此勾M4/M6。

### 2026-10-15 第二批：DC-3 安装器来源记录与复核

范围：mynet_desktop（安装器构建/复核/门禁）+ 发布 workflow；不改 agent 运行时、不上传、不部署、不操作实机。

- **来源记录**：`build-nsis.sh` / `build-pkg.sh` 要求 agent dist 带 `SOURCES.txt`（缺失即失败、不产半包）；NSIS 将 SOURCES 落 `$INSTDIR`（`mynet-agent.nsi` 无 `/nonfatal`，缺则编译失败）、pkg 落 `/opt/mynet/share/SOURCES.txt`、dmg 内带 `SOURCES.txt`；三者均写 `*.SOURCES.txt` sidecar 供无解包工具环境复核。
- **来源复核**：新增 `scripts/check-installer-sources.sh PKG EXPECTED`。archive（tgz/zip/tar.gz）解包内 SOURCES；installer（exe/pkg/dmg）优先 sidecar、否则解内嵌；未知扩展名拒绝；取不到 SOURCES 一律失败（「复核不了」不算通过）。目录形态与 pack.sh 输入同源。
- **kind 选择错误拒绝**：build-nsis 只接受 `mynet_agent_windows_*`、build-pkg 只接受 `mynet_agent_darwin_*`（kind 检查先于二进制检查，错配报 kind 而非「缺 mynet」）；check 脚本不猜未知扩展名。
- **发布侧**：desktop `release.yml` publish 步已存在版本改为下载后 `check-installer-sources` 逐字比对，不一致拒绝（不覆盖已发布 provenance）；HTTP 非 200/404 停止；sidecar 不进 GitHub Release 资产、不进 ctl 上传循环。publish 作业检出 mynet_desktop 以取复核脚本。
- **行为测试**（desktop `make check`）：`test_installer_sources` 10 项（目录/exe sidecar/归档一致与不一致、缺 SOURCES、未知 kind、空期望）；`test_installer_build_rejects` 6 项（build-nsis/pkg 缺 SOURCES、kind 错配、缺 sidecar、不产半包）。lint 新增 `lint_installer_sources` 9 项静态契约。
- **自动化**：desktop `make check` **41 lint + 25 test 全绿**；agent `make check` 通过；release.yml YAML 解析通过、12 个 run 块 `bash -n` 通过（静态，不冒充 CI）；六腿目标矩阵（linux amd64/arm64/mipsle、darwin amd64/arm64、windows amd64）交叉编译通过。
- **限制**：macOS pkg/dmg 与 NSIS exe 的**真实打包**未在本机跑（缺 makensis/pkgbuild 产物链不阻塞脚本级验收）；ctl 上传、安装器实机安装、升级 kind 选择器仍待实机/线上，不据此勾 M4/M6。Linux tgz 走归档侧 SOURCES（首批已覆盖），本轮不重复打 Linux 安装器。

### 提交锚点（2026-10-15）

agent：`03b8d92`（DC-1路径）与`92b06b6`（DC-2及DC-3归档侧）。提交前make check、跨平台编译与上述本机验证通过；提交hooks安全/结构门禁通过。尚未push或部署。

desktop（DC-3安装器侧，工作树未提交）：`nsis/build-nsis.sh`、`nsis/mynet-agent.nsi`、`macos/build-pkg.sh`、`macos/build-dmg.sh`、`scripts/check-installer-sources.sh`（新）、`scripts/check.sh`、`.github/workflows/release.yml`。提交前 desktop make check 41+25 全绿、agent make check 通过、六腿交叉编译通过。
