# 对账修复接续证据（2026-10-13）

> 本文件仅保存完成批的详细证据。全部发现与剩余项看 [合并台账](audit-consolidated-2026-10-13.md)，完整执行顺序看 [治理计划](governance-plan-2026-10-13.md)。

依据：`audit-2026-10-13.md` 与 `governance-plan-2026-10-13.md`。原审计冻结；本文件记录接续批的实际交付，不将全部治理计划标为完成。

## OpenWrt 旧路由注入入口退役

提交：mynet_openwrt `b45a2e7`（本地，未 push）。对应总报告 §4 S2-16 的旧入口残留。

- `luasrc/model/mynet/node.lua:193-209`：保存 route 配置仍生成系统路由配置，但不再加载 Proxy 配置或调用旧 `route_inject()` 写入钩子。
- `luasrc/model/mynet/proxy.lua`：删除 `route_inject()`、`route_restore()`；删除前全仓复核，前者仅由上述钩子调用，后者无代码消费者。历史 CHANGELOG/测试报告引文保留。
- `luasrc/model/mynet/proxy.lua:334`：保留 `strip_route_injections()`，用于生成系统路由前过滤历史 marker 段；保留 UI 的历史注入状态查询。
- `tests/local_config_test.lua`：运行实际 Lua 模块，验证 route 保存不加载旧 Proxy 模块、原始配置内容与换行、node/address 保存、生成失败与未知类型错误传播、历史 marker 过滤及正常 GNB 路由保留。文件系统与配置依赖用内存替身；非设备验收。
- `Makefile.guard`：行为测试接入 `test`，可通过 `LUA` 指定目标运行时；缺 Lua 时失败，不静默跳过。

实测：`make -f Makefile.guard check` 通过（guard、Lua 语法、打包不变量、桥/ACL/LuCI 19 方法一致、上述行为测试）；`git diff --check` 通过。环境 Lua 5.4.8，**目标 Lua 5.1 与设备实测未做**。

审计口径核正：修复前 `route_inject()` 实际已经只清理历史 marker 段，不再添加 /8、/16 路由。因此不能继续把该函数描述为会回灌大段路由。本批解决旧生产调用与死入口；**不代表 O1「桥→Go apply --auto 唯一路径」整体完成**，Lua 自行配置/路由生成与完整装配迁移仍需后续治理。gnb 仓正式 route.conf 未动；未部署或重启设备。

## 避免重复工作的取证

接续时已存在的本地提交（只核代码/提交记录，本批不重测其全部出口）：

- agent `9525391`：私钥改 stdin 与 argv 遮蔽；`9a578a6`：OpenWrt embed/产物清理；`ee83dd9`：security_audit 路径修正。
- 后端 `c1e8fff`：心跳接口判全；`8c1fa4b`：keys 响应收窄；`51498f6`：DomainException 修正；`110f1d8`：500 断言修正。
- GUI `6678e11`：登出、状态请求、refresh、IP 池删除；`e08ad98`：残余死码清理。
- ctl `50f398b`：manifest 扩展名、filename 取件、版本比较、测试 kind。
- OpenWrt `bdb2939`：节点面 API 路径映射。

agent 托盘/服务名/home 与后端 `tests/TestCase.php` 在接续时有他人未提交改动，本批未碰。治理计划中的「执行中/未开始」不是上述提交是否存在的可靠依据；继续开工须核 HEAD 与工作树。

## 后端 GNB CLI 假绿与私钥日志收口

提交：mynet_api `873ebc9`（本地，未 push）。对应 A3 的 GnbKeyCliFallbackTest 假绿项，并新增核实私钥日志出口。

- `tests/Unit/Domain/GnbKeyCliFallbackTest.php`：删除旧 workspace 绝对路径与依赖缺失 skip，直接加载生产 `config/mynet.php`（配置变量 `MYNET_GNB_CRYPTO_BIN`，默认 PATH 上 `gnb_crypto`）。原生扩展存在时直接调用 CLI 分支，仍验证回退。默认套件需要真 CLI，缺少即失败。
- `app/Modules/VPN/Domain/Services/NodeKeyDomainService.php:523`：CLI 可执行路径作为单参数引用，支持空格路径；缺配置明确拒绝；输出必须合法 64/128 hex；成功、失败、格式异常都在 finally 清理临时私钥/公钥/目录。CLI 原始输出不进入异常与日志，仅保留退出码。
- 同服务的 Ed25519/GNB/WireGuard 三处成功日志删除私钥预览；`NodeKeyResponseDTO::getSummary()` 删除私钥预览。新增 `NodeKeyLogSafetyTest` 用实际生成与日志事件验证 Ed25519/WireGuard 日志及 DTO 输出无私钥片段；本机无 GNB 原生扩展，该分支日志删除为代码验证，CLI 分支已真跑。
- `TenantServiceApiTest` 的无 Accept 头请求按生产 `shouldRenderJsonWhen(api/*)` 断言 401 JSON，替换旧空正文断言。

实测：真 CLI 与失败路径 **3 tests / 10 assertions**，日志事件 **1 test / 6 assertions**；全量 `make test` **497 tests / 4790 assertions / 1 skip**（既有 SQLite 并发）；guard、改动文件 Pint 与 diff 检查通过。

全量测试依据为**当前工作树**，包含其他修复批尚未提交的 `tests/TestCase.php` 镜像；该文件未被本批改动或提交。401 JSON 断言依赖这份生产镜像，整批发布需一起集成，不能将此记录当作仅 `873ebc9` 干净 checkout 的全量验收。未测试或部署生产。

## GUI 三层路由守卫收敛

提交：mynet_gui `a874bc3`（本地，未 push）。对应 S2-15。

- `src/boot/auth.ts`：恢复认证后注册唯一的 `setupRouteGuards`，不再自建 beforeEach；认证初始化失败抛出，阻止无守卫启动。
- `src/router/middleware.ts`：读认证 store 的实时 `isAuthenticated/token`，包含过期时间与用户状态，不再以本地 token 存在作为登录依据。统一登录重定向与完整 redirect 参数、node ID 校验、页面标题；删除恒 true 的编辑权限函数，授权归后端。
- `src/router/index.ts`/`nodeRoutes.ts`：删除重复节点守卫注册与实现，其余导航工具保留。
- 既有 callback 白名单保留；用 URL.searchParams 设置 token，避免 fragment 吞参或旧 token 重复。外域拒绝与会话过期均由真实 memory router 导航测试覆盖。

实测：新增 5 项导航测试；全量 vitest **58/58**；`make check` 通过（eslint 零错误，存量 warnings；i18n；类型棘轮 **184 未升**；guard；vitest）；SPA 正式构建通过。最后移除未用 boot 参数与 EOF 空行后 diff 检查与提交 guard 通过。构建产生的已跟踪 `.quasar/prod-spa/client-entry.js` 在提交前恢复，本批不提交生成物。**未浏览器实测、未发布**。
