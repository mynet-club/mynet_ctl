# 治理计划（2026-10-13）：删 → 修 → 增

> 前提：全面对账已完成（见 [audit-2026-10-13.md](audit-2026-10-13.md)，168 条 + 零消费者端点 28 条组）。
> **治理方向（用户裁定）：删而不是添——先删，再修，最后才允许增。**
> 本计划分步执行；每步独立交付、本地 commit 不 push、实机/生产动作归用户。

## 0. 全程规矩

1. **删字当头**：任何问题先问「能不能把这坨删掉」。假象（骗人端点、mock 数据、假门椒）一律删，不许补实现；功能壳调用不存在的端点 → 删壳，不默认补端点。
2. **删前复核**：判「零引用」必须过全部消费者（路由表、动态 import、菜单、build tag、字符串调用、框架别名、Blade、前端 fetch）。复核不过 = 不删，升级进 G2。
3. **出口判据**：api `make test` 绿 · agent `go build ./... && go test ./...` + `make guard` 绿 · gui vitest 绿且类型棘轮基线**只降不升** · ctl `make test` + `make guard` 绿。
4. **交付纪律**：精确 `git add`（删除用 `git rm` 后 `git add -u`，勿再 add 已删路径）、一仓一 commit（可多个语义 commit）、**不 push**、不碰他人未提交改动（当前 mynet_web 的 `.githooks/guard.sh` 有未提交改动，本轮不动 web）。
5. **口径基线**：命名（后端 `mynet_api`/「后端」，back 称谓废止，库名 `mynet_back` 与 `back_priv` 等标识保留）；gnb 仓 `route.conf` 不可动；`mynet_gnb`/`mynet_utc` 不在治理范围。
6. **测试是证据**：删除后测试计数变化要记录；不许为绿而 skip/删测试断言（删被删功能的测试用例除外，须在 commit message 列明）。

## G1 直接删除批（本轮执行，无决策依赖）

| 仓 | 删什么（源自对账分册） | 附带小修 |
|---|---|---|
| mynet_api | app/domain 整层、app/Services/User、stubs/AuditService 垫片、config/id.php+channel.php（零读取）、app/Exceptions/Handler.php 死类、bootstrap/cache 入库件、3 空目录、node.deprecated 死别名、26 零引用类（~4.4k 行）、30 未挂路由方法、MailTemplateService 垫片（恢复 fail-loud）、零消费者端点 28 条组、`GET /users`+`/users/search`（A4 同径下架，零消费者）、骗人端点族中零消费者者 | 测试文件中的 back 称谓修正 |
| mynet_gui | 12 孤儿组件（~3361 行）、useRecommendations/RecommendationsCard/types 簇、死模块断链件、ApiTestPage/TokenDebugPage/FormStyleGuide 生产路由+菜单+文件 | OAuth callback 开放重定向：callback 机制若零业务使用则整段删；有使用则白名单 |
| mynet_agent | shared/errors 整包、4 死函数、packaging/darwin 残留（删物+重定向全部引用） | Makefile openwrt 腿去 embed；webui.html 按消费者判删/改文档；security_audit.sh 的 mynet_back 路径修正；私钥 argv→stdin（对齐 password-stdin）+ argv 遮蔽 |
| mynet_ctl + mynet_openwrt + 知识库 | openwrt guest.htm.bak、ctl 公开下载页硬编码白名单改 SSOT 派生、install.sh/upgrade.sh 确认死则删 | B2 文档漂移 16 条修正、CPI-71~82 台账归位、workspace-review R 表归位、project-memory 基线刷新、命名修正批（文档/注释/脚本中的 back 称谓与旧仓名）、db-setup.sh 口令下命令行改 stdin/defaults-file、guard 用例数口径统一 |

## G2 双侧协同删除（两侧同改，进裁决后执行）

- 骗人端点族中**有 GUI 消费者**者（check-availability 等）：删两侧 or 补真实现 → 用户二选一。
- GUI 调 404 的功能壳剩余（若 G1 未尽）：删功能 or 补端点 → 默认删，用户可翻案。

## G3 修（删完后留存能力的修复）

- S1 残余：`/users` 族下架后的门面清理（若有残留路由/权限注释）。
- 托盘启停打错标识（macOS label、Windows SCM）；三层路由守卫收敛单点 + 假门椒删除后的真门椒归属。
- Testbench 镜像补齐三块 + IdentifyTenant 组归属修正（测试态=生产态）。
- guard B2 扫描面扩（cmd/mynetd/**、mynet 根包、mynet/driver）+ 存量全量复扫。
- openwrt Lua `/api/v2` 目标改 `/api/v1/node/*`；route.conf 注入路线退役（O1-③，双侧对齐）。
- ctl 发行面：manifest URL 扩展名、`/d/` 按 filename 精确匹配、apps.json latest 版本比较、测试建表补 kind。
- 后端杂项：DomainException catch 类修正、keys 响应去内部字段、refresh 解包统一、心跳 vpn_interfaces 判全、HomeSource 判据、deploy.sh 与生产漂移收敛。
- 测试质量：NodeApiTest 500 断言改真期望、GnbKeyCliFallbackTest 去硬编码路径、test-mysql.sh/gen-schema.sh 方言注释改 MariaDB。

## G4 跨端契约裁定（决策闸门，用户拍板后走两端以上同步）

- B4 驱动术语：MonitorRtCmd 词表、Bundle Gnb*/Wg* VO、NodeConfig 读 gnb 配置 —— 增翻译层 or 维持并备案。
- 发行面「每 platform+arch 一件」vs mac/win 自更新归档 —— 发布矩阵怎么改。
- kind 维度（安装器 vs 归档同 tuple）与 `/d/` 取件语义。
- mock getDashboardData：接真数据 or 删仪表盘数据面。

## G5 实机与生产（用户窗口）

- openwrt 设备实测（route.conf 注入退役、/api/v1 目标、init 收敛 O1）。
- .180 / .182 部署与 CI 出包验证；ctl 生产 3 月→10 月发布。
- mac/win 托盘启停与安装器回归。

## G6 增（最后；仅业务确认需要时）

- check-availability 真实现、users/onboarding 落库等 —— 每项先问「这需求还成立吗」，成立才做。

## 执行台账

| 步 | 状态 | 备注 |
|---|---|---|
| G1-a mynet_api 清理 | 执行中 | |
| G1-b mynet_gui 清理 | 执行中 | |
| G1-c mynet_agent 清理 | 执行中 | |
| G1-d ctl/openwrt/文档/台账 | 执行中 | |
| G2–G6 | 未开始 | G2/G4 待用户裁决 |
