# 对账修复接续证据（2026-10-13）

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
