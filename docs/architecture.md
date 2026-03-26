# MyNet CTL — 架构与产品规划

## 核心定位

**CTL 是功能和交互的载体，mynet_web 是信息和内容的载体，两者联动互补。**

| 站点 | 职责 | 典型内容 |
|------|------|----------|
| **CTL** `ctl.mynet.club` | 功能与交互 | 设备检测、下载、版本查询、工具目录 |
| **mynet_web** `www.mynet.club` | 信息与内容 | 产品介绍、使用教程、设备兼容说明、FAQ |

CTL 页面中**只做功能**，需要教程/说明时，通过链接跳转到 mynet_web 对应页面。  
除 `/admin` 外，**所有页面无需登录**，降低用户门槛，方便用户第一时间判断：
- 我的设备支持吗？
- 该下哪个版本？
- 有什么工具可以用？

---

## 一、页面结构总览

```
ctl.mynet.club/
  /                     首页（平台检测 + 快速安装命令）
  /downloads            下载中心（所有产品版本）
  /downloads/{product}  产品专页（client / gnb / utc）
  /openwrt              OpenWrt 设备查询（品牌/型号/架构检测）
  /tools                工具目录（脚本 + 插件）
  /admin/               管理后台（唯一需要登录的区域）
    /admin/releases     版本管理
    /admin/scripts      脚本编辑器
    /admin/plugins      插件管理
    /admin/tokens       API Token 管理
    /admin/stats        数据看板
```

---

## 二、各页面功能设计

### `/` 首页

**目标**：用户到达后 10 秒内拿到安装命令，不需要任何额外判断。

```
┌─────────────────────────────────────────────────────┐
│  下载并安装 MyNet                                    │
│                                                     │
│  [Linux]  [macOS]  [Windows]  [OpenWrt]             │  ← JS 自动高亮当前平台
│                                                     │
│  curl -fsSL https://ctl.mynet.club/install | bash   │  ← 一键复制
│                                                     │
│  最新版本 client v1.4.0 · 发布于 2026-03-01         │
│  [查看 changelog]  [查看安装教程 → mynet_web]        │
└─────────────────────────────────────────────────────┘

当前各产品最新版本：
  client v1.4.0  ·  gnb v1.6.0.a  ·  utc v1.2.0
  [前往下载中心 →]

路由器用户？
  [→ 查询我的 OpenWrt 设备]
```

**联动 mynet_web**：
- "查看安装教程" → `www.mynet.club/docs/install`
- "什么是 MyNet？" → `www.mynet.club`

---

### `/downloads` 下载中心

**目标**：让用户找到任意版本、任意平台/架构的文件，支持手动下载。

```
[client]  [gnb]  [utc]  [插件]        ← tab 切换

过滤：平台 [全部 ▾]  架构 [全部 ▾]  版本 [全部 ▾]

▼ v1.4.0  stable  2026-03-01
  ├─ linux   · amd64    client_linux_amd64_v1.4.0.tgz     12.3 MB  abc123  [↓]
  ├─ linux   · arm64    client_linux_arm64_v1.4.0.tgz     11.8 MB  ...     [↓]
  ├─ linux   · mipsel   client_linux_mipsel_v1.4.0.tgz    9.1 MB   ...     [↓]
  ├─ openwrt · mipsel   client_openwrt_mipsel_v1.4.0.tgz  4.2 MB   ...     [↓]
  ├─ darwin  · amd64    client_darwin_amd64_v1.4.0.tgz    13.1 MB  ...     [↓]
  ├─ windows · amd64    client_windows_amd64_v1.4.0.tgz   15.0 MB  ...     [↓]
  └─ ...（13 个架构）

▶ v1.3.2  stable  2026-01-15  [展开]
▶ v1.3.0  stable  2025-12-01  [展开]
```

**不搞**：登录、购买、权限控制（全部公开下载）。  
**联动 mynet_web**：各产品 tab 旁有"了解更多 →" 链到 web 的产品介绍。

---

### `/openwrt` OpenWrt 设备查询

**目标**：路由器用户不需要知道"什么是 mipsel"，直接告诉他下哪个文件。

这是 CTL **最有独特价值的页面**，1930 条设备数据库在此发挥作用。

```
┌──────────────────────────────────────────────────────────┐
│  找到适合你路由器的安装包                                  │
│                                                          │
│  方式一：搜索设备                                        │
│  [   输入品牌 / 型号 / SoC，如 "Xiaomi 4A"  🔍 ]        │
│                                                          │
│  方式二：粘贴 /etc/openwrt_release 内容                  │
│  [   粘贴内容...                                  ]      │
│  [识别并推荐]                                            │
│                                                          │
│  方式三：在路由器 SSH 中运行（最准确）                   │
│  wget -qO- https://ctl.mynet.club/api/v1/detect/...      │
│  [复制命令]                                              │
└──────────────────────────────────────────────────────────┘

── 搜索结果 ─────────────────────────────────────────────────

  小米 Mi Router 4A (Gigabit Edition)
  SoC: MediaTek MT7621A · RAM: 128 MB · Flash: 16 MB
  架构: mipsel · libc: musl · OpenWrt: 25.12.1 / 24.10.6

  推荐下载：
  [↓ client v1.4.0 (openwrt/mipsel)]
  [↓ gnb v1.6.0.a (openwrt/mipsel)]

  安装命令（SSH 到路由器执行）：
  wget -O /tmp/client.tgz https://ctl.mynet.club/...
  [复制]

  [查看 OpenWrt 路由器安装教程 → www.mynet.club/docs/openwrt]
```

**联动 mynet_web**：
- 每个设备结果下方 → "查看路由器安装教程"
- 页面顶部 → "OpenWrt 版 mynet 能做什么？ → www.mynet.club/openwrt"

---

### `/tools` 工具目录

**目标**：脚本和插件的公开目录，方便用户发现和使用工具。

```
分类：[全部]  [安装]  [服务管理]  [升级]  [工具]
平台：[linux ▾]

─────────────────────────────────────────────────────────
install.sh             v2.1  · linux · 安装
  自动检测架构并安装 mynet client
  curl -fsSL https://ctl.mynet.club/api/v1/scripts/.../raw/install.sh | bash
  [复制命令]  [查看源码]  校验: abc123...

service.sh             v1.3  · linux · 服务管理
  注册为 systemd 服务并设置开机自启
  [复制命令]  [查看源码]

upgrade.sh             v1.1  · linux / openwrt · 升级
  检测并升级到最新版本
  [复制命令]  [查看源码]
─────────────────────────────────────────────────────────

插件 tab：
  proxy-plugin  v0.3  · linux · proxy
    [↓ 下载]  [查看说明 → mynet_web]
```

---

### `/admin` 管理后台（唯一需要登录的区域）

登录方式：独立的 Session 认证（区别于 API Token），或复用 API Token。

```
/admin/releases    上传版本 · 标记 stable/beta · 下架
/admin/scripts     在线编辑脚本（Monaco Editor）· 版本历史
/admin/plugins     插件管理
/admin/tokens      创建/吊销 API Token · 查看使用记录
/admin/stats       下载量趋势 · 架构分布 · 版本分布 · 设备型号 Top 20
```

---

## 三、CTL ↔ mynet_web 联动点汇总

| CTL 页面 | 跳转目标（mynet_web） | 内容 |
|----------|----------------------|------|
| 首页安装命令下方 | `/docs/install` | 完整安装教程 |
| 首页 | `/` | 什么是 MyNet？ |
| `/downloads` 各产品 tab | `/docs/{product}` | 产品功能介绍 |
| `/openwrt` 设备结果下方 | `/docs/openwrt` | OpenWrt 版安装教程 |
| `/openwrt` 页面顶部 | `/openwrt` | OpenWrt 版能做什么 |
| `/tools` 每个插件下方 | `/docs/plugins/{name}` | 插件详细说明 |

**原则**：CTL 只放操作型内容（命令、下载、结果），解释型内容（为什么、怎么做、注意事项）交给 mynet_web。

---

## 四、近期优先实现顺序

| 优先级 | 工作项 | 原因 |
|--------|--------|------|
| P0 | 注册 Manifest + Detect 路由 | client 和 javis 的调用完全依赖，现在是死路 |
| P0 | 编写 install.sh 脚本并发布到 scripts 表 | 首页的核心功能 |
| P1 | `/openwrt` 设备查询页 | 1930 条数据现在完全没有前端入口 |
| P1 | `/downloads` 下载中心页 | 补全公共展示面 |
| P1 | 首页（`/`） | 用户第一个接触点 |
| P2 | `/tools` 工具目录 | 脚本/插件的前端入口 |
| P2 | 管理员：统计看板 | 运营数据可视化 |
| P3 | 管理员：脚本在线编辑器 | 管理员体验优化 |


---
