# gnb 四件套运维规程 —— 同构建、同批、同签

> **地位**：gnb 四件套（`gnb` / `gnb_ctl` / `gnb_es` / `gnb_crypto`）运维动作的操作规程。
> 规则本体在 [`paths.md`](spec/paths.md) R2（唯一事实源），本文只讲**怎么遵守**。
> 机器强制（R2 死限）在 `mynet_agent/shared/utils/gnb_suite.go`。

---

## 1. 为什么要这套纪律

四件套**共享 mmap 的 ctl block**（`gnb_conf_t` 嵌在块里，见 `mynet_gnb/src/gnb_ctl_block.h`）。
布局随源码代际变（例：1.6.9 同步改过 `map_file` 尺寸并新增字段）。**混代部署 = 按错位
布局解析共享块** —— 字符串/指针全是垃圾，典型症状是 gnb_es/gnb_ctl 在 libc 里
strlen/memchr 段错误，或 `node_num[0]` 读空表。

**事故锚点（2026-11-18）**：08:05 只换了 gnb_es/ctl/crypto、旧 gnb 继续跑，09:50
gnb_es segfault（libc strlen 扫垃圾字符串，n182）；同日 mac 排雷「换四件不重启 +
忘补签」又翻一次车。规则 R2 早就有，缺的是**执行** —— 于是有了死限和本规程。

## 2. 机器死限（违者拒跑，不是告警）

判据两段（`CheckGNBSuite`）：

| 段 | 语义 | 说明 |
|---|---|---|
| 版本横幅一致 | 源码代际 | 比对 `GNB version Dev …` 字面量；不一致 = 跨代际，必拒 |
| 批次时间 ≤ **1 分钟窗** | 同批次 | 一次 make 秒级出齐；编译时间可见（`Build Time [日期,时刻]`）直接比；`[Hidden]` 旧构建退**落位 mtime** 代理 |

放行点（三处咽喉，起/重起前强制）：

1. `mynet utc driver-run` —— 单元 / procd 直启的必经口；
2. `mynet service start / reload` —— 编排入口（含直 spawn 路径）；
3. `mynet subject` —— 诊断面（R2 同判据，违者带 `suite-mismatch` 返回错误）。

**边界（死限管不到的）**：
- **已在跑的进程**——只拦启动。换件不重启 = 跑旧映像配新工具，混代照旧（必须做 §3 第 5 步）；
- `gnb_crypto` 无版本横幅，仅参与时间窗；
- 测试桩（无横幅）放行，不误伤替身。

> OpenWrt 构建已去掉 `-DGNB_SKIP_BUILD_TIME`（2026-11-18 裁定，弃可重现构建换批次可判）；
> 存量 `[Hidden]` 旧件由 mtime 代理过渡至下次同批部署。

## 3. 运维动作纪律（五步连做，缺一必炸）

```
1) 一次 make 四件    不许单换一件。make 报 up-to-date 时必须
                    rm 四个二进制 + find . -name '*.o' -delete 后全量重编
                    （陈旧 .o 的编译时间戳会伪造「同批」）
2) 备份旧件          *.bak-<日期> 留底
3) 四件同批落位      同一个 cp 批；顺手清 PATH 散件
                    （如 /usr/local/bin/gnb_ctl —— 它曾是「异构建读表」事故源）
4) macOS 逐件补签    sudo codesign --force --sign - <bin>
                    （Taskgated 以 SIGKILL "Code Signature Invalid / Invalid Page"
                    杀未签件；坑点：--version 只碰几页能侥幸跑，完整启动必死）
5) 一起重启          mynet service restart gnb
                    （只换不重启 = 跑旧映像配新工具）
6) 验证              mynet subject            —— 不带 suite-mismatch
                    mynet service status gnb —— running=true
                    dns-hook status 的 exit_probe —— reachable=true
                    ping 对端隧道 IP 一发
```

## 4. 故障速查

| 症状 | 先想 | 判据 |
|---|---|---|
| gnb_es / gnb_ctl 段错误（libc strlen/memchr） | 混代共享块 | 四件 `strings <bin> \| grep -m1 "GNB version"` 横幅对比 |
| `node_num[0]` / 链路全 unknown、exit_probe 不可达 | 旧 ctl 读新 map（同族病） | 同上 + `gnb_ctl -b <map> -c -s` 用同批 ctl 复测 |
| 启动即 `signal: killed`，无输出 | Taskgated 未签/签名失效 | 崩溃报告 `DiagnosticReports/*.ips` 看 termination=CODESIGNING |
| `service start` 报「R2 死限（异构建/非同批拒跑）」 | 死限拦了真雷 | 按报错指认的成员重做 §3 全流程 |
| 换件后一切「照旧」 | 没重启（旧映像仍在跑） | `ps -o lstart -p <gnb pid>` 对比换件时间 |

## 5. 关联

- 规则 SSOT：[`spec/paths.md`](spec/paths.md) R2（同构建配套 / 禁止捡异构建）
- 死限实现：`mynet_agent/shared/utils/gnb_suite.go`（判据、放行点、注释含事故根因）
- 相关事故排障：[`troubleshoot-connectivity.md`](troubleshoot-connectivity.md)（`gnb_ctl 读出垃圾` 一行）
