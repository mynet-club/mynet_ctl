# mynet_desktop — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | systemd / launchd / NSIS · shell 打包脚本 |
| **角色** | 非 OpenWrt 平台适配层：安装 · 系统服务 · 打包 |
| **对等** | 与 `mynet_openwrt` 一平台一份 |

## 职责边界

| 做 | 不做 |
|---|---|
| systemd / launchd / Windows 计划任务 | 业务逻辑（归 mynetd / mynet） |
| 二进制布局与 PATH、conf 模板、logrotate | WebUI 业务（归 mynet_gui / localapi） |
| 安装器：tgz / macOS .pkg+.dmg / Windows NSIS | 长期保留 Node 客户端 |

## 三平台安装器

| 平台 | 产物 | 入口 |
|---|---|---|
| Linux | `.tgz`（`mynet_agent make dist`） | `sudo ./mynet install` |
| macOS | `.dmg` → `.pkg` | `make pack-dmg` |
| Windows | `.exe`（NSIS） | `make pack-nsis` |

产物命名前缀：**`mynet_agent_*`**（不是 `agent_*`）。
完整安装/卸载见 `docs/INSTALL.md`。

## 知识库

`knowledge-base/spec/paths.md` · `knowledge-base/conventions.md`（产物命名）
