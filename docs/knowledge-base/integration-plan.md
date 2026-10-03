> ⚠️ **本文件已被 [`integration-plan-v2.md`](integration-plan-v2.md) 取代（2026-09-21）。**
> v1 为旧线口径（Node client 为统一客户端形态），保留作 **ADR 与历史参考**（尤其 §10 ADR-1/2/5/6 的认证、守护、服务注册、ctl 定位决策仍有效）。
> 新的分层、范围与工作流以 v2 为准。

---

# MyNet 全生态整合规划文档

> **文档版本**: v1.0  
> **生成日期**: 2026-03-20  
> **适用范围**: mynet.club 全部 10 个子项目  
> **目标读者**: 各子项目开发者、架构决策者

---

## 一、生态现状概览

### 1.1 各子项目完成度评估

| 项目 | 技术栈 | 完成度 | 生产就绪 | 核心价值 |
|------|--------|--------|----------|----------|
| **mynet_api** | Laravel 12 | 70-75% | ✅ Alpha/Beta | 全局 API + 多租户 Zone + 节点配置 |
| **mynet_gui** | Vue3 + Quasar + TS | 85% | ✅ 可上线 | 用户中心 + 社交 + 设备管理 |
| **mynet_ctl** | Laravel 12 | 80% | ✅ 可上线 | 软件分发 + 配置部署 |
| **mynet_tui** | Go | 85-90% | ✅ 生产环境 | CLI/TUI 客户端（最成熟） |
| **mynet_lua** | Lua/LuCI | 75-80% | ✅ 稳定运行 | OpenWrt 路由器客户端 |
| **mynet_client** | Node.js | 60-70% | ⚠️ 开发中 | 下一代统一桌面客户端 |
| **mynet_javis** | Laravel 12 | 95% | ✅ 生产环境 | GNB 穿透场景：个人 AI 服务 |
| **mynet_web** | Vue3 + TS | 60% | ⚠️ 开发中 | 官网（理念传达 + 导流） |
| **mynet_gnb** | C + CI/CD | 90% | ✅ 生产环境 | GNB P2P 核心二进制构建 |
| **mynet_utc** | C | 95% | ✅ 生产环境 | UDP→TCP 隧道（17-20 Gbps） |

### 1.2 技术成熟度分布

```
生产就绪 ██████████████████████████████ mynet_tui (90%)
         ████████████████████████████   mynet_utc (95%) / mynet_javis (95%)
         ██████████████████████████     mynet_gnb (90%)
         ████████████████████████       mynet_gui (85%)
         ██████████████████████         mynet_ctl (80%)
         ████████████████████           mynet_lua (78%)
         █████████████████              mynet_api (73%)
开发中   █████████████                  mynet_client (65%)
         ████████████                   mynet_web (60%)
```

### 1.3 数据流全景图

```
┌──────────────────────────────────────────────────────────────────┐
│  用户触点层                                                       │
├────────────┬────────────┬──────────────┬─────────────────────────┤
│ mynet_web  │ mynet_gui  │ mynet_ctl    │ myjarvis.ai             │
│ 官网/导流   │ 用户中心    │ 分发/配置     │ AI 穿透服务              │
│ Vue3       │ Vue3+Quasar│ Laravel      │ Laravel                  │
│ www.       │ my.        │ ctl.         │ *.myjarvis.ai            │
└─────┬──────┴─────┬──────┴──────┬───────┴──────────┬──────────────┘
      │            │             │                   │
      │      ┌─────▼─────────────▼───────────────────▼──────┐
      │      │              mynet_api                       │
      │      │    api.mynet.club / admin.mynet.club          │
      │      │    用户 · Zone · 节点 · 路由 · 配置 · 社交     │
      │      └──┬───────────────────────────────────────┬────┘
      │         │                                       │
      │    ┌────▼─────────────────┐         ┌──────────▼────────┐
      │    │   客户端层（设备上）    │         │    基础设施层       │
      │    ├─────────────────────┤         ├───────────────────┤
      │    │ mynet_tui (Go)      │         │ mynet_gnb (C)     │
      │    │ mynet_client (Node) │         │ → GNB P2P 核心    │
      │    │ mynet_lua (LuCI)    │         │                   │
      │    │                     │         │ mynet_utc (C)     │
      │    │ 管理: 登录/Zone选择   │         │ → UDP→TCP 隧道    │
      │    │ 拉取: 配置/二进制文件  │         │                   │
      │    │ 运行: GNB/UTC 进程   │         │ OpenResty          │
      │    └────────┬────────────┘         │ → Lua 动态路由     │
      │             │                      └───────────────────┘
      │             ▼
      │     ┌───────────────┐
      └────►│  mynet_ctl     │
            │  二进制下载     │
            │  配置包下载     │
            │  脚本分发       │
            └───────────────┘
```

---

## 二、核心瓶颈分析

### 2.1 阻塞链路（Critical Path）

当前用户完整体验链路中，阻塞最严重的环节：

```
用户注册 → 创建 Zone → 创建节点 → 下载二进制 → 配置 GNB → 启动 → 组网成功
   ✅       ✅          ✅        ✅ (CTL)      ⚠️ 手动     ⚠️ 手动   ？
```

**瓶颈**：节点创建后，用户需要：
1. 手动从 CTL 下载 gnb/utc 二进制
2. 手动从 back API 获取配置文件
3. 手动拼装目录结构
4. 手动启动进程

**已实现的解决方案**（本次新增 @ mynet_ctl）：
- `GET /api/v1/setup/config-pack/{nodeId}` — 一键打包下载全部配置
- `/setup` 页面 — Web UI 登录后可视化操作

**仍缺的自动化**：
- 客户端还没对接 config-pack API（mynet_client/tui/lua 都需适配）
- 从「下载配置包」到「GNB 启动」的最后一步自动化

### 2.2 项目间接口不一致

| 问题 | 涉及项目 | 影响 |
|------|----------|------|
| `vpn_interfaces[]` → `vpn_interface{}` API 变更 | tui ↔ back | 心跳数据格式不兼容 |
| tui 对外名 `mynet` vs 内部名 `tui` 的别名混乱 | ctl ↔ client/tui | manifest 查询可能出错 |
| mynet_web 下载页没对接 CTL manifest API | web ↔ ctl | 官网下载链接是死链 |
| mynet_javis 心跳与 back 心跳格式独立实现 | javis ↔ back | 两套心跳逻辑，维护成本 |

### 2.3 测试覆盖率

| 项目 | 单元测试 | 集成测试 | E2E 测试 |
|------|----------|----------|----------|
| back | ❌ 近零 | ❌ 近零 | ❌ 无 |
| gui | ❌ 框架在 | ❌ 无 | ❌ 无 |
| ctl | ❌ 近零 | ❌ 无 | ❌ 无 |
| tui | ✅ 基础 | ✅ 基础 | ❌ 无 |
| client | ❌ 无 | ❌ 无 | ❌ 无 |
| lua | ❌ 无 | ❌ 无 | ❌ 无 |

**结论**：测试几乎为零，是整个生态最大的技术债务。

---

## 三、整合策略

### 3.1 战略原则

1. **用户最短路径优先**：减少从注册到组网成功所需的步骤
2. **Backend First**：back 是全局核心，其 API 稳定性决定所有其他项目
3. **不破坏已稳定的系统**：tui (Go) 和 lua 已稳定，除非有明确收益否则不大改
4. **CTL 作为桥梁**：配置部署能力从 back 延伸到用户设备的中间层
5. **client 是未来主力**：Node.js 版客户端需要尽快上线替代 tui

### 3.2 里程碑定义

| 里程碑 | 目标 | 核心指标 |
|--------|------|----------|
| **M1**: 基础闭环 | 用户能通过 web→gui→ctl 完整走通注册到组网全流程 | 端到端可演示 |
| **M2**: 自动化部署 | 客户端对接 config-pack，一键部署 | 用户无需手动拼配置 |
| **M3**: 产品化 | 官网上线 + 文档完善 + 监控告警 | 可对外运营 |
| **M4**: 规模化 | 负载测试 + 自动扩缩 + 付费体系 | 技术运营成熟度 |

---

## 四、各子项目行动计划

### 4.1 mynet_api（后端核心）— 优先级：🔴 最高

**当前状态**：70-75% 完成，API 主体功能齐全但缺乏文档、测试和一些边缘功能。

**必须完成的事项**：

| 编号 | 任务 | 说明 | 估算工作量 |
|------|------|------|-----------|
| B-1 | **统一心跳 API 格式** | 修复 `vpn_interfaces[]` → `vpn_interface{}` 变更，保持向后兼容（同时接受新旧格式） | 小 |
| B-2 | **config-pack API** | 新增 `GET /api/v1/nodes/{id}/config-pack` 直接返回 ZIP（不经 CTL 代理），这样 client 可直接调 back | 中 |
| B-3 | **API 文档生成** | 用 Scribe/L5-Swagger 自动生成 API 文档，所有端点的请求/响应格式化 | 中 |
| B-4 | **Route Access Rules** | 完成已注释掉的路由访问规则 CRUD，这是组网安全的关键 | 中 |
| B-5 | **Admin Dashboard 补全** | 运营监控面板：节点在线数、Zone 活跃度、心跳延迟分布 | 大 |
| B-6 | **Rate Limiting** | 在关键 API 端点（login、heartbeat、config）加速率限制 | 小 |
| B-7 | **核心 API 测试** | 至少覆盖：认证流程、节点 CRUD、配置生成、心跳处理 | 大 |
| B-8 | **Migration 补全** | 为所有表补齐 Laravel migration 文件（当前直接 SQL，不利于团队协作） | 中 |

**产出物**：
- 自动生成的 API 文档（供所有前端/客户端项目参考）
- 测试套件（CI 可运行）
- 心跳 API v2（向后兼容）

---

### 4.2 mynet_gui（用户中心前端）— 优先级：🟡 高

**当前状态**：85% 完成，功能齐全但部分页面用 mock 数据。

| 编号 | 任务 | 说明 |
|------|------|------|
| G-1 | **Dashboard 真实数据** | 替换 mock 数据对接 back API（在线节点数、流量统计等） |
| G-2 | **一键部署入口** | 在节点详情页增加「下载配置包」按钮，调用 CTL config-pack API |
| G-3 | **路由拓扑可视化** | 完善 D3/ECharts 网络拓扑图，展示节点间连接状态 |
| G-4 | **IP Pool 详情** | 完善 IP 池管理界面（当前只有列表，缺少详情/编辑） |
| G-5 | **Sanctum Token 管理** | 用户可查看/撤销已生成的 API Token |
| G-6 | **错误监控集成** | 接入 Sentry 前端 SDK，上报运行时异常 |
| G-7 | **节点状态实时更新** | 利用已配置的 Laravel Echo/WebSocket 推送节点在线状态变化 |

**产出物**：
- 完整的 Dashboard 数据展示
- 节点管理 → CTL 配置下载的闭环
- WebSocket 实时状态更新

---

### 4.3 mynet_ctl（分发中心）— 优先级：🟡 高

**当前状态**：80% 完成。本次已新增 config-pack 功能。

| 编号 | 任务 | 说明 |
|------|------|------|
| C-1 | ✅ **config-pack API** | 已实现：`GET /api/v1/setup/config-pack/{nodeId}` |
| C-2 | ✅ **setup 页面** | 已实现：登录 → Zone/节点选择 → 一键下载 |
| C-3 | **install 脚本** | 创建 `/install` 端点返回安装脚本（homepage 引用的 `curl \| bash` 目前是死链） |
| C-4 | **Admin 管理后台** | 版本管理、脚本管理、下载统计的 Web UI |
| C-5 | **配置包增强** | 在 ZIP 中加入对应平台的 GNB/UTC 二进制（或下载链接清单） |
| C-6 | **下载统计面板** | 展示各产品/平台/架构的下载量趋势 |
| C-7 | **缓存策略** | manifest.json/apps.json 加 HTTP 缓存头 + 服务端缓存 |

**产出物**：
- 完整的安装脚本（一行命令安装）
- 配置包含二进制或安装指引
- 基础统计功能

---

### 4.4 mynet_client（Node.js 客户端）— 优先级：🔴 最高

**当前状态**：60-70% 完成，架构已建但 WebUI 和打包未完成。

这是**用户体验变革的关键**——从 Go TUI 迁移到 Node.js + WebUI 是提升易用性的核心举措。

| 编号 | 任务 | 说明 |
|------|------|------|
| D-1 | **对接 CTL config-pack** | CLI 命令 `mynet setup` → 调用 CTL config-pack API → 自动解压到正确目录 |
| D-2 | **WebUI 完成** | 登录/Zone/节点/状态/配置 全部 Vue3 页面对接后端 API |
| D-3 | **GNB 进程管理** | 实现 `mynet start/stop/restart` → spawn GNB/UTC 子进程并监控 |
| D-4 | **自动更新** | 定期检查 CTL manifest.json → 提示或自动下载新版 |
| D-5 | **二进制打包** | 使用 pkg / nexe / Sea 将 Node.js 打包为单可执行文件 |
| D-6 | **系统服务** | systemd (Linux) / launchd (macOS) / Windows Service 注册 |
| D-7 | **心跳守护进程** | Node.js 版 mynetd，代替 Go 版的心跳上报 |
| D-8 | **首次运行向导** | `mynet init` → 交互式引导：登录 → 选 Zone → 选节点 → 下载配置 → 启动 |

**核心目标**：用户拿到新机器后，运行一行命令即可完成部署：
```bash
curl -fsSL https://ctl.mynet.club/install | bash
mynet init     # 交互式引导
mynet start    # 启动 GNB
```

**产出物**：
- 可分发的跨平台 CLI + WebUI 二进制
- 一行命令完成初始化到组网的全流程
- 自动更新 + 心跳守护

---

### 4.5 mynet_tui（Go 客户端）— 优先级：🟢 低

**当前状态**：85-90% 完成，生产稳定。

| 编号 | 任务 | 说明 |
|------|------|------|
| T-1 | **对接 config-pack** | 新增 `mynet download-config` 命令，调用 CTL config-pack API |
| T-2 | **VPN interface API 适配** | 适配 back 的心跳 API 格式变更（B-1 完成后） |
| T-3 | **维护模式** | 不再新增大功能，只做 bugfix 和 API 兼容 |

**原则**：mynet_tui 作为过渡产品，保持稳定即可。新功能投入到 mynet_client。

---

### 4.6 mynet_lua（OpenWrt 客户端）— 优先级：🟡 中

**当前状态**：75-80% 完成，功能可用。

| 编号 | 任务 | 说明 |
|------|------|------|
| L-1 | **对接 config-pack** | 在 LuCI 界面增加「一键下载配置」按钮，调 CTL API 后解压到 /etc/mynet/ |
| L-2 | **自动升级** | 检查 CTL manifest，通过 opkg/wget 自动升级 GNB/UTC |
| L-3 | **Mobile 适配** | LuCI 页面在手机浏览器上更好展示 |
| L-4 | **中文化** | 添加 i18n 支持（至少中英双语） |

---

### 4.7 mynet_javis（AI 穿透服务）— 优先级：🟢 中低

**当前状态**：95% 完成，核心功能稳定。

| 编号 | 任务 | 说明 |
|------|------|------|
| J-1 | **Admin 面板** | 子域名管理、套餐管理、质量监控的 Web 管理后台 |
| J-2 | **支付系统** | 对接支付（微信/支付宝），自动发放套餐 |
| J-3 | **用户引导** | 从注册到完成 OpenClaw 配置的 step-by-step 教程 |

**原则**：Javis 与 mynet 弱耦合，可独立迭代。当前 API 已稳定运行。

---

### 4.8 mynet_web（官网）— 优先级：🟡 中

**当前状态**：60% 完成，页面存在但下载链接未对接。

| 编号 | 任务 | 说明 |
|------|------|------|
| W-1 | **对接 CTL manifest** | 下载页调用 `ctl.mynet.club/api/v1/apps.json` 获取真实下载链接 |
| W-2 | **产品介绍完善** | 补全功能特性、使用场景、定价（如果有） |
| W-3 | **实时数据** | 首页展示当前在线节点数、活跃 Zone 数（调 back API） |
| W-4 | **SEO 优化** | SSR 或预渲染，方便搜索引擎收录 |
| W-5 | **文档站** | 集成文档（或链接到外部文档站） |

---

### 4.9 mynet_gnb + mynet_utc（底层二进制）— 优先级：🟢 低

**当前状态**：90-95% 完成，都稳定运行。

| 编号 | 任务 | 说明 |
|------|------|------|
| I-1 | **GNB 版本自动化** | Tag → Build → 自动上传到 CTL releases API（替代手动） |
| I-2 | **UTC 同上** | 同 I-1 |
| I-3 | **构建缓存** | CI/CD 加入增量编译缓存减少构建时间 |

---

## 五、整合时序（推荐执行顺序）

### Phase 1: 基础闭环验证（2-3 周）

```
目标：一个真实用户能从 0 到组网成功
```

| 周 | 项目 | 任务 | 产出 |
|----|------|------|------|
| W1 | back | B-1 心跳格式兼容 + B-6 Rate Limiting | API 稳定无兼容问题 |
| W1 | ctl | C-3 install 脚本 | `curl \| bash` 可用 |
| W1 | web | W-1 下载页对接 CTL | 官网下载链接可用 |
| W2 | gui | G-2 节点详情→配置下载 | 用户中心闭环 |
| W2 | tui | T-1 对接 config-pack | CLI 一键拉配置 |
| W3 | 全局 | 端到端测试全流程 | 可演示的完整链路 |

### Phase 2: 自动化 + 客户端升级（4-6 周）

```
目标：用户体验从「手动拼装」变成「一行命令」
```

| 周 | 项目 | 任务 | 产出 |
|----|------|------|------|
| W4-5 | client | D-1/D-2/D-3 CLI+WebUI+进程管理 | Node.js 客户端核心可用 |
| W4 | back | B-2 直接返回 config-pack ZIP | 减少 CTL 代理延迟 |
| W5-6 | client | D-5/D-6 打包+系统服务 | 可分发的独立二进制 |
| W6 | client | D-8 首次运行向导 | `mynet init` 全自动 |
| W5 | lua | L-1 对接 config-pack | OpenWrt 一键配置 |
| W6 | ctl | C-5 配置包含binary链接 | 配置包更完整 |

### Phase 3: 产品化（4-6 周）

```
目标：可对外宣传、可运营
```

| 周 | 项目 | 任务 | 产出 |
|----|------|------|------|
| W7-8 | back | B-3 API 文档 + B-4 Access Rules | 开发者友好 + 安全 |
| W7-8 | back | B-5 Admin Dashboard | 运营可监控 |
| W8-9 | gui | G-1/G-3/G-7 Dashboard+拓扑+实时 | 用户中心完整体验 |
| W9-10 | web | W-2/W-3/W-4 内容+数据+SEO | 对外可展示 |
| W9-10 | back | B-7 核心测试 | CI/CD 保障 |
| W10-11 | gnb/utc | I-1/I-2 自动化发布 | 零人工发布流水线 |
| W11-12 | client | D-4/D-7 自更新+心跳守护 | 完整客户端 |

### Phase 4: 规模化（持续）

| 项目 | 任务 | 产出 |
|------|------|------|
| back | 负载测试 + 分布式缓存 + 数据库优化 | 支撑千级节点 |
| javis | J-1/J-2 Admin+支付 | Javis 独立运营 |
| gui | Sentry + 性能优化 | 生产级前端 |
| client | 多语言 + 自动诊断 | 国际化拓展 |

---

## 六、关键接口约定

### 6.1 CTL Config Pack API（所有客户端必须适配）

```http
GET https://ctl.mynet.club/api/v1/setup/config-pack/{nodeId}?platform={platform}
Authorization: Bearer {sanctum_token}
Accept: application/zip

Response: ZIP 文件
├── conf/node.conf
├── conf/address.conf
├── conf/route.conf
├── ed25519/{nodeId}.public
├── security/{nodeId}.public
├── setup.sh (或 setup.ps1)
└── README.txt
```

### 6.2 CTL Manifest API（客户端自动更新必须适配）

```http
GET https://ctl.mynet.club/api/v1/apps.json
→ {"apps": {"mynet": "...manifest_url...", "gnb": "...", "utc": "..."}}

GET https://ctl.mynet.club/api/v1/{product}/manifest.json
→ schema v1.1 格式，包含版本列表、artifacts、checksums
```

### 6.3 Back 心跳 API（tui/client/lua 都需遵循）

```http
POST https://api.mynet.club/api/v1/monitor/heartbeat
Headers: X-Node-Id, X-Node-Signature (Ed25519)
Body: {
    "vpn_interface": { ... },    // v2 格式（对象）
    "system_metrics": { ... }
}
```

**向后兼容**：back 同时接受 `vpn_interfaces: [...]` (v1 数组) 和 `vpn_interface: {...}` (v2 对象)。

### 6.4 产品命名约定

| 内部名 | 对外/URL 名 | manifest 中名称 | 说明 |
|--------|------------|----------------|------|
| tui | mynet | mynet | 旧 Go 客户端（向后兼容别名） |
| client | client | client | 新 Node.js 客户端 |
| gnb | gnb | gnb | GNB 核心 |
| utc | utc | utc | UDP→TCP |

CTL ManifestService 自动处理 `mynet ↔ tui` 别名映射。

---

## 七、各项目配置联动清单

### 7.1 环境变量 / .env 配置

| 项目 | 变量 | 值 | 说明 |
|------|-----|----|------|
| **back** | `APP_URL` | `https://api.mynet.club` | 全局 API 根 |
| **ctl** | `APP_URL` | `https://ctl.mynet.club` | CTL 根 |
| **ctl** | `MYNET_BACK_API_URL` | `https://api.mynet.club` | 代理后端地址 |
| **gui** | `VITE_API_URL` | `https://api.mynet.club` | 前端 API 地址 |
| **gui** | `VITE_CTL_URL` | `https://ctl.mynet.club` | CTL 下载地址 |
| **web** | `VITE_CTL_URL` | `https://ctl.mynet.club` | 下载链接前缀 |
| **javis** | `MYNET_BACK_URL` | `https://api.mynet.club` | 账号同步 |
| **client** | `MYNET_API_URL` | `https://api.mynet.club` | 后端 API |
| **client** | `MYNET_CTL_URL` | `https://ctl.mynet.club` | 分发 API |

### 7.2 域名/服务器规划

| 域名 | 用途 | 部署 |
|------|------|------|
| `api.mynet.club` | 后端 API | Laravel (back) |
| `admin.mynet.club` | 管理后台 | Laravel (back) |
| `my.mynet.club` | 用户中心 | 静态 SPA (gui) |
| `ctl.mynet.club` | 软件分发 | Laravel (ctl) |
| `www.mynet.club` | 官网 | 静态 SPA (web) |
| `*.myjarvis.ai` | AI 穿透 | OpenResty + Laravel (javis) |
| `download.mynet.club` | 遗留 | 重定向到 ctl（已弃用） |

---

## 八、风险与建议

### 8.1 高风险项

| 风险 | 影响 | 建议 |
|------|------|------|
| **零测试覆盖** | back API 任何修改可能导致所有客户端+前端故障 | Phase 1 立即加上核心 API 的冒烟测试 |
| **单点依赖 back** | back 宕机 = 全家断线 | Phase 3 加健康检查 + 自动重启 + 缓存回退 |
| **client 进度不确定** | Node.js 客户端未完成 → tui 续命 | 确保 tui 对接 config-pack，双线并行 |
| **无 CI/CD** | 手动部署容易出错 | Phase 1 结束前各项目建立基础 CI |

### 8.2 技术债优先级

1. **back**: 补 migration 文件（团队协作基础）
2. **back**: 核心 API 测试（稳定性保证）
3. **ctl**: install 脚本（用户入口）
4. **gui**: mock 数据替换为真实数据
5. **web**: 下载链接对接 CTL

### 8.3 团队协作建议

- 各子项目的 copilot-instructions.md 统一维护 API 接口约定
- 建立 monorepo 级别的 CHANGELOG 或跨项目 release notes
- 关键 API 变更（如心跳格式）必须先更新文档再发布
- 建议使用 shared `types/` 或 OpenAPI schema 做接口定义

---

## 九、总结

MyNet 生态已经拥有了相当完整的技术基础：

- **底层** (gnb + utc)：成熟稳定，无需投入
- **后端** (back)：功能完整，需要加固（测试 + 文档 + 监控）
- **前端** (gui)：接近完成，需要真实数据替换
- **分发** (ctl)：已具备配置包功能，需补全安装脚本
- **客户端** (tui + lua)：稳定运行，需适配 config-pack
- **新客户端** (client)：投入最大的增长点
- **官网** (web)：需要最少的工作量产出最大的外部影响
- **AI 应用** (javis)：独立运营，可后续迭代

**最关键的下一步**：打通 Phase 1 基础闭环 — 让一个真实用户能从 www.mynet.club 开始，一路走通到 GNB 组网成功。这需要 back(心跳兼容) + ctl(install 脚本) + web(下载对接) + gui(配置下载按钮) 四个项目协同完成。

---

## 十、架构决策记录（ADR）

以下决策基于对全生态认证体系、守护进程实现、存储结构、客户端形态的深入分析。

---

### ADR-1: 客户端认证方式统一采用 Sanctum Bearer Token + Refresh Token

#### 背景

当前生态存在三种认证方式：

| 方式 | 使用者 | 机制 |
|------|--------|------|
| Sanctum Bearer Token + Refresh Token | gui (Vue3)、tui (Go)、lua (LuCI) | Login → 获取 auth_token (30min) + refresh_token (7d) → Redis 存储 |
| HMAC-SHA256 对称签名 | mynetd 心跳（节点→后端） | `{METHOD}\|{PATH}\|{TIMESTAMP}\|{BODY}` → HMAC-SHA256 签名 → 服务端验证 |
| Session Cookie | back admin 面板（浏览器内） | 传统 Laravel session（少量使用） |

**关键发现**：
- 心跳签名用的是 **HMAC-SHA256 对称密钥**（node 的 public_key 其实是共享密钥的 hex 编码），不是真正的 Ed25519 非对称签名
- gui 前端用 localStorage 存 token + 定时器主动刷新（过期前 5 分钟）+ 401 响应拦截刷新
- tui (Go) 用 `~/.config/mynet/config.json` 存 token（0600 权限）
- lua 用 `/etc/config/mynet/credentials.json` 存 token（0600 权限）

#### 决策

**Node.js client 和 LuCI client 统一使用 Sanctum Bearer Token + Refresh Token。**

理由：

| 方案 | 优势 | 劣势 | 结论 |
|------|------|------|------|
| **Sanctum Token** | ✅ 所有客户端统一 ✅ back 已完整实现 ✅ 多设备/多会话管理 ✅ 可撤销 | 需定期刷新（30min） | ✅ 采用 |
| Session Cookie | ✅ 浏览器原生 | ❌ CLI 不友好 ❌ 跨域复杂 ❌ 不适合 API 调用 | ❌ 仅限 admin 面板 |
| Ed25519 非对称签名 | ✅ 无需刷新 ✅ 节点身份不可伪造 | ❌ 仅用于节点心跳 ❌ 当前实现是 HMAC ❌ 不包含用户身份 | ❌ 仅限节点心跳场景 |

#### 实现规范

**Token 生命周期**（全客户端统一）：

```
Login (email + password)
  → auth_token (30min TTL, Sanctum DB 表)
  → refresh_token (7d TTL, Redis cache)

Token Refresh (auth_token 过期前 5 分钟)
  → 持 refresh_token 换新 auth_token + refresh_token
  → 旧 auth_token 立即失效

Logout
  → 删除 auth_token (DB) + refresh_token (Redis)
```

**各客户端存储位置**：

| 客户端 | 文件 | 权限 | 格式 |
|--------|------|------|------|
| Node.js CLI | `~/.config/mynet/credentials.json` | 0600 | JSON: `{token, refresh_token, expires_at, user}` |
| Node.js WebUI | 同上（后端读取，不存浏览器） | — | API 代理模式：WebUI→本地 Express→back |
| LuCI | `/etc/config/mynet/credentials.json` | 0600 | 同格式 |

**Node.js WebUI 特殊说明**：WebUI 是本地 Express 提供的网页，不是独立 SPA。认证 token 存在 Node.js 进程内存 / 配置文件中，浏览器端只有 session cookie 连到本地 Express。这样避免 token 暴露在浏览器 localStorage。

#### 节点心跳认证（独立于用户认证）

心跳认证保持现有 HMAC-SHA256 方案不变，但需修正命名：

```
当前: X-Node-Signature: base64(HMAC-SHA256(shared_key, message))
     ↑ 名为 "signature" 但实质是对称 HMAC

建议: 保持 HMAC 方案（性能好、实现简单）
     文档中统一称为 "HMAC 认证" 而非 "Ed25519 签名"
     
未来考虑: 如需真正的非对称认证（节点不信任传输通道时）
         → 真正的 Ed25519 sign/verify (sodium/tweetnacl)
         → 但当前 GNB 隧道本身已加密，HMAC 足够
```

---

### ADR-2: Node.js / LuCI 实现 mynetd 持久运行的方案

#### 背景

Go 版 mynetd 的实现要点：
- PID 文件 `/var/run/mynetd.pid`（防多实例）
- 信号处理：SIGTERM 优雅退出、SIGHUP 重载配置
- Ticker 循环：每 5 分钟发心跳
- GNB/UTC 子进程管理：spawn → stdout/stderr 捕获 → exit 检测
- 远程命令执行：心跳响应中携带 command，daemon 执行

Node.js client 当前状态：
- 只有基础 `spawn()` + 内存状态，**无 PID 文件、无信号处理、无自动重启、无心跳守护**

#### 决策方案

##### Node.js Client — 推荐方案：内置守护 + 系统服务注册

```
架构:
  mynet daemon → Node.js 长驻进程（等同于 Go 版 mynetd）
    ├─ 心跳循环 (setInterval, 5min)
    ├─ GNB 子进程管理 (spawn + 自动重启)
    ├─ UTC 子进程管理 (spawn + 自动重启)
    ├─ WebUI HTTP 服务器 (Express, localhost:9090)
    └─ 远程命令执行（心跳响应解析）
```

**关键设计点**：

```javascript
// 1. PID 文件（防多实例）
const PID_FILE = '/var/run/mynetd.pid';  // Linux
// macOS: ~/Library/Application Support/mynet/mynetd.pid
// Windows: %APPDATA%/mynet/mynetd.pid

// 2. 进程管理 — 不依赖 pm2/forever，内置实现
class MynetDaemon {
    // GNB 子进程自动重启（指数退避）
    restartDelay = 1000;  // 1s → 2s → 4s → max 60s
    maxRestarts = 10;     // 10 次后停止重试，报告异常

    // 心跳循环
    heartbeatInterval = 5 * 60 * 1000;  // 5min

    // 优雅关闭
    async shutdown() {
        clearInterval(this.heartbeatTimer);
        await this.stopGnb();   // SIGTERM → 5s → SIGKILL
        await this.stopUtc();
        fs.unlinkSync(PID_FILE);
        process.exit(0);
    }
}

// 3. 信号处理
process.on('SIGTERM', () => daemon.shutdown());
process.on('SIGINT',  () => daemon.shutdown());
process.on('SIGHUP',  () => daemon.reloadConfig());

// 4. Uncaught exception → 不崩溃，记日志
process.on('uncaughtException', (err) => {
    logger.error('Uncaught:', err);
    // 不 exit — 保持心跳和进程管理
});
```

**系统服务注册**（由 Node.js 脚本生成，不用 shell）：

| 平台 | 服务机制 | 注册方式 |
|------|----------|----------|
| Linux | systemd | `mynet service install` → 生成 `/etc/systemd/system/mynetd.service` |
| macOS | launchd | `mynet service install` → 生成 `~/Library/LaunchAgents/club.mynet.mynetd.plist` |
| Windows | Windows Service | `mynet service install` → 使用 node-windows 或 winsw 注册 |

**为什么不用 pm2/forever？**
- 多一个外部依赖 = 用户安装步骤多一步
- 用户可能已有 pm2 版本冲突
- 打包后（pkg/sea）不能依赖外部 npm 包
- 内置守护代码量不大（~200 行核心），完全可控

##### LuCI Client — 推荐方案：procd init 脚本（OpenWrt 原生）

OpenWrt 用 procd（不是 systemd），LuCI 生态已有完善的服务管理：

```shell
# /etc/init.d/mynetd — procd init 脚本
#!/bin/sh /etc/rc.common
START=90
STOP=10
USE_PROCD=1

start_service() {
    procd_open_instance
    procd_set_param command /usr/bin/mynetd
    procd_set_param respawn 3600 5 5    # 1h window, 5s间隔, 最多5次
    procd_set_param file /etc/config/mynet  # 配置变更自动重启
    procd_set_param pidfile /var/run/mynetd.pid
    procd_close_instance
}
```

**心跳实现**：LuCI/Lua 环境不适合写长驻进程 → 用 cron + 独立 shell 脚本：

```
# /etc/crontabs/root
*/5 * * * * /usr/lib/mynet/heartbeat.sh
```

`heartbeat.sh` 调用 `curl` 发 HMAC 签名心跳（Lua 签名辅助脚本或轻量 C helper）。

#### 对比总结

| 特性 | Go (现有) | Node.js (推荐) | LuCI (推荐) |
|------|-----------|---------------|-------------|
| 守护方式 | 内置 fork daemon | 内置 + systemd/launchd | procd (OpenWrt 原生) |
| 心跳 | goroutine ticker | setInterval | cron + curl |
| PID 管理 | 自实现 | 自实现 | procd 管理 |
| 自动重启 | 需 systemd | 内置 + systemd | procd respawn |
| 资源占用 | ~10MB RSS | ~30-50MB RSS | ~0 (cron 触发) |
| 适用场景 | 服务器/桌面 | 服务器/桌面 | 路由器 (4-128MB RAM) |

---

### ADR-3: CTL 存储路径优化

#### 现状分析

当前路径：`storage/app/private/releases/{product}/{version}/{filename}`

```
releases/
  tui/
    v1.3.0/
      tui_linux_amd64_v1.3.0.tgz
      tui_linux_arm64_v1.3.0.tgz
      tui_darwin_amd64_v1.3.0.tgz
    v1.4.0/
      tui_linux_amd64_v1.4.0.tgz
```

**问题**：
1. 路径按 product → version 组织，但查询最常用的场景是「给我 linux/amd64 的最新 tui」→ 需遍历所有 version 目录
2. 文件名已包含全部元数据（product_platform_arch_version.tgz），version 目录层是冗余的
3. download.mynet.club 遗留路径是 `/releases/{product}/{version}/{filename}`，两边约定一致，没有独立优化空间
4. 数据库才是查询入口（按 product+platform+arch+is_latest 索引），磁盘路径只是存储实现

#### 决策

**磁盘路径保持现有方案不变**，但增加两个优化：

##### 优化 1：数据库索引优化

```sql
-- 当前缺少的复合索引，加速 manifest 和 latest 查询
ALTER TABLE releases ADD INDEX idx_latest_lookup (product, platform, arch, is_latest);
ALTER TABLE releases ADD INDEX idx_version_lookup (product, version);
```

##### 优化 2：路径规范化 + 新产品 `client` 适配

```
releases/
  tui/v1.4.0/tui_linux_amd64_v1.4.0.tgz        ← 旧产品保持
  client/v1.0.0/client_linux_amd64_v1.0.0.tgz   ← 新 Node.js 客户端
  client/v1.0.0/client_darwin_arm64_v1.0.0.tgz
  gnb/v1.6.0.a/gnb_linux_amd64_v1.6.0.a.tgz
  utc/v1.3.1/utc_linux_amd64_v1.3.1.tgz
```

##### 优化 3：增加 `latest` 符号链接（可选，加速 Nginx 直接分发）

```
releases/
  tui/latest → v1.4.0
  gnb/latest → v1.6.0.a
  client/latest → v1.0.0
```

**不建议大改路径结构**，理由：
- DB 是查询入口，路径只是存储后端
- 已有 releases 数据需要迁移（破坏性变更）
- manifest API 已经在 Service 层做了所有聚合，磁盘结构对用户不可见

---

### ADR-4: 客户端产品形态确认

#### 决策

```
                    ┌─────────────────────────────────┐
                    │        mynet_client (monorepo)    │
                    ├──────────────┬──────────────────┤
                    │ desktop/     │ openwrt/          │
                    │ Node.js      │ Lua/LuCI          │
                    │ CLI + WebUI  │ LuCI 页面          │
                    │ 打包发行      │ .ipk 发行          │
                    ├──────────────┼──────────────────┤
                    │ Linux, macOS │ OpenWrt            │
                    │ Windows      │ (MIPS/ARM 路由器)  │
                    └──────────────┴──────────────────┘

    mynet_tui (Go) → 冻结开发，仅 bugfix + API 兼容维护
```

##### desktop (Node.js) 发行规范

| 项目 | 规范 |
|------|------|
| 打包工具 | Node.js SEA (Single Executable Application, Node 20+) 优先；备选 pkg |
| 输出格式 | Linux: `.tar.gz` (内含 `mynet` 二进制 + README) |
| | macOS: `.tar.gz` (同上；未来可选 `.dmg`) |
| | Windows: `.zip` (内含 `mynet.exe` + README) |
| CTL 产品名 | `client` |
| Manifest 路径 | `GET /api/v1/client/manifest.json` |
| 双模式 | `mynet cli` → 命令行交互模式 |
| | `mynet daemon` → 守护进程 + WebUI (localhost:9090) |
| | `mynet setup` → 交互式首次配置向导 |

##### openwrt (Lua/LuCI) 发行规范

| 项目 | 规范 |
|------|------|
| 发行格式 | `.ipk` (OpenWrt 标准包) |
| 包名 | `luci-app-mynet` |
| 遵循 | OpenWrt 打包规范（Makefile, ipkg-build） |
| CTL 上传 | 作为 plugin 或独立 product `luci-app-mynet` 分发 |
| 安装 | `opkg install luci-app-mynet_*.ipk` |

##### tui (Go) 冻结策略

| 内容 | 规则 |
|------|------|
| 新功能 | ❌ 不添加 |
| Bug 修复 | ✅ 继续 |
| API 兼容 | ✅ 跟随 back API 变更 |
| config-pack | ✅ 适配（轻量改动） |
| 文档 | 标注「推荐使用 mynet client (Node.js)」 |

---

### ADR-5: Node.js 客户端管理脚本用 JS 而非 Shell

#### 决策

**所有管理脚本用 JavaScript 编写**，不使用 shell。

#### 理由

| 维度 | JS (Node.js) | Shell (bash/sh) |
|------|-------------|-----------------|
| **跨平台** | ✅ Windows/macOS/Linux 统一 | ❌ Windows 需要另写 .bat/.ps1 |
| **开发效率** | ✅ async/await, 丰富 npm 生态 | ⚠️ 字符串处理痛苦 |
| **错误处理** | ✅ try/catch, 结构化错误 | ⚠️ set -e 加 trap 勉强可用 |
| **打包** | ✅ 跟随 SEA 一起打包，零额外依赖 | ❌ 需要用户系统有 bash |
| **测试** | ✅ Jest/Vitest 可测 | ⚠️ bats/shunit2 不主流 |
| **类型安全** | ✅ JSDoc / 可选 TS | ❌ 无 |
| **进程管理** | ✅ child_process.spawn 原生 | ✅ 原生但跨平台差异大 |
| **系统服务生成** | ✅ 模板生成 systemd/launchd/winsw 文件 | ⚠️ 只能生成 systemd |

#### 具体实现

client 的 CLI 命令全部由 JS 实现：

```
mynet setup          # 交互式首次配置（inquirer/prompts）
mynet login          # 登录获取 token
mynet daemon         # 启动守护进程（心跳+GNB+UTC+WebUI）
mynet start          # 快速启动 GNB
mynet stop           # 停止 GNB
mynet status         # 显示运行状态
mynet update         # 检查并更新 GNB/UTC/client
mynet service install   # 注册为系统服务
mynet service uninstall # 卸载系统服务
mynet config show    # 查看当前配置
mynet config set KEY VALUE  # 修改配置
mynet zone list      # 列出 Zone
mynet zone switch ID # 切换 Zone
mynet node list      # 列出节点
mynet node config ID # 下载节点配置包
mynet log            # 查看日志
mynet diag           # 网络诊断
mynet web            # 打开 WebUI
```

**service install 的 JS 实现示例**：

```javascript
// 跨平台服务注册 — 纯 JS，无 shell 依赖
function generateSystemdUnit(binaryPath, configPath) {
    return `[Unit]
Description=MyNet Daemon
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${binaryPath} daemon --config ${configPath}
Restart=on-failure
RestartSec=5
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target`;
}

function generateLaunchdPlist(binaryPath, configPath) {
    return `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "...">
<plist version="1.0"><dict>
  <key>Label</key><string>club.mynet.mynetd</string>
  <key>ProgramArguments</key><array>
    <string>${binaryPath}</string>
    <string>daemon</string>
  </array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><true/>
</dict></plist>`;
}
```

#### 唯一例外

CTL 分发的**安装引导脚本** `install.sh` 仍用 shell：
```bash
curl -fsSL https://ctl.mynet.club/install | bash
```
因为这是在用户还没安装 Node.js 的时候执行，此脚本的唯一职责是下载 Node.js + mynet client 二进制。一旦 client 安装完成，后续所有操作都走 JS。

---

### ADR-6: CTL 作为全局控制中心 — 环境初始化与预配置

#### 定位升级

CTL 从「软件分发中心」升级为「**控制中心**」（Control Center），承担：

```
原定位: 分发 binary + 脚本
新定位: 分发 binary + 脚本 + 运行时环境 + 预配置 + 初始化工具
```

#### 新增能力矩阵

| 能力 | 端点 | 说明 |
|------|------|------|
| **Node.js 运行时下载** | `GET /api/v1/runtime/nodejs` | 返回适合当前平台的 Node.js LTS 下载链接（或直接代理分发） |
| **安装引导脚本** | `GET /install` | 一行命令：检测平台 → 下载 Node.js → 下载 mynet client → 初始化 |
| **预配置模板** | `GET /api/v1/presets/{template}` | 常用场景预配置（home-network, server-relay, openwrt-gateway） |
| **环境检测** | `GET /api/v1/detect/env` | 检测目标环境：OS/arch/已安装软件/端口占用/防火墙状态 |
| **配置包生成** | `GET /api/v1/setup/config-pack/{nodeId}` | ✅ 已实现 |
| **健康检查** | `GET /api/v1/health` | 返回 CTL 及依赖服务状态 |

#### 安装引导脚本设计（核心）

`GET https://ctl.mynet.club/install` 返回 POSIX shell 脚本：

```bash
#!/bin/sh
# MyNet 一键安装脚本
# 用法: curl -fsSL https://ctl.mynet.club/install | sh

set -e

# 1. 检测平台
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
ARCH=$(uname -m)
case "$ARCH" in
    x86_64|amd64)  ARCH="amd64" ;;
    aarch64|arm64)  ARCH="arm64" ;;
    armv7*)         ARCH="armv7" ;;
    *)              echo "不支持的架构: $ARCH"; exit 1 ;;
esac

# 2. 检查 Node.js（打包发行后可跳过此步）
# 如果用 SEA 打包，二进制已包含 Node.js，无需额外安装
# 此步仅作为 fallback

# 3. 从 CTL 下载 mynet client
CTL="https://ctl.mynet.club"
MANIFEST=$(curl -fsSL "$CTL/api/v1/client/manifest.json")
VERSION=$(echo "$MANIFEST" | grep -o '"latest_version":"[^"]*"' | cut -d'"' -f4)
URL=$(echo "$MANIFEST" | python3 -c "
import sys, json
m = json.load(sys.stdin)
for a in m['versions']['$VERSION']['artifacts']:
    if a['os'] == '$OS' and a['arch'] == '$ARCH':
        print(a['url'])
        break
")

echo "正在下载 mynet client $VERSION ($OS/$ARCH)..."
curl -fsSL "$URL" -o /tmp/mynet.tgz
tar -xzf /tmp/mynet.tgz -C /usr/local/bin/
chmod +x /usr/local/bin/mynet
rm /tmp/mynet.tgz

# 4. 引导初始化
echo "安装完成！运行以下命令开始配置："
echo "  mynet setup"
```

#### 预配置模板

CTL 维护常用场景的预配置模板，减少用户决策：

```json
// GET /api/v1/presets/home-network
{
    "name": "home-network",
    "description": "家庭组网：连接家里所有设备",
    "recommended_products": ["client", "gnb"],
    "gnb_config_hints": {
        "listen_port": 9001,
        "mtu": 1280,
        "multi_socket": false
    },
    "firewall_rules": [
        "允许 UDP 9001 入站",
        "允许 TUN 接口流量"
    ],
    "post_install_steps": [
        "运行 mynet setup 配置账号",
        "选择或创建家庭 Zone",
        "添加当前设备为节点",
        "启动 mynet daemon"
    ]
}
```

#### 统一预配置存储

将各客户端的默认配置集中在 CTL，客户端首次启动时拉取：

```json
// GET /api/v1/defaults/client.json
// 客户端默认配置（集中管理，统一变更）
{
    "api_url": "https://api.mynet.club",
    "ctl_url": "https://ctl.mynet.club",
    "heartbeat_interval": 300,
    "auto_update": true,
    "auto_update_check_interval": 3600,
    "log_level": "info",
    "webui_port": 9090,
    "webui_bind": "127.0.0.1",
    "gnb_default_port": 9001,
    "gnb_mtu": 1280
}
```

**好处**：
- 客户端不硬编码 API 地址（万一域名变更，CTL 统一推送）
- 新功能开关可以服务端控制（feature flags）
- 心跳间隔、自更新策略等全局可调
- 客户端只需内置一个地址：`ctl.mynet.club`

---

## 十一、修订后的行动计划

基于以上 6 项决策，更新各项目行动清单：

### 11.1 mynet_api — 补充项

| 编号 | 任务 | 关联 ADR |
|------|------|---------|
| B-9 | **心跳 API 文档修正**：将 "Ed25519 签名" 统一改为 "HMAC-SHA256 认证" | ADR-1 |
| B-10 | **Token Refresh 健壮性**：refresh_token 被盗用时的处理（检测到重复使用 → 吊销该用户所有 token） | ADR-1 |
| B-11 | **config-pack 直接端点**：`GET /api/v1/nodes/{id}/config-pack` 不经 CTL 代理 | ADR-6 |

### 11.2 mynet_ctl — 补充项

| 编号 | 任务 | 关联 ADR |
|------|------|---------|
| C-8 | **`GET /install` 安装引导脚本** | ADR-6 |
| C-9 | **`GET /api/v1/runtime/nodejs` 运行时下载** | ADR-6 |
| C-10 | **`GET /api/v1/presets/{template}` 预配置模板** | ADR-6 |
| C-11 | **`GET /api/v1/defaults/client.json` 客户端默认配置** | ADR-6 |
| C-12 | **`GET /api/v1/detect/env` 环境检测端点** | ADR-6 |
| C-13 | **releases 表复合索引优化** | ADR-3 |
| C-14 | **新产品 `client` + `luci-app-mynet` 注册** | ADR-4 |

### 11.3 mynet_client (Node.js) — 重新定义

| 编号 | 任务 | 关联 ADR | 优先级 |
|------|------|---------|--------|
| D-1 | **Sanctum Token 认证**：login → token 存 credentials.json (0600) → 自动刷新 | ADR-1 | P0 |
| D-2 | **mynet daemon 守护进程**：PID文件 + 信号处理 + 心跳循环 + GNB 进程管理 | ADR-2 | P0 |
| D-3 | **mynet setup 交互向导**：login → zone → node → config-pack → start | ADR-4 | P0 |
| D-4 | **WebUI** (Express + Vue3)：token 存后端，浏览器只有 local session | ADR-1 | P1 |
| D-5 | **mynet service install/uninstall**：JS 生成 systemd/launchd/winsw | ADR-5 | P1 |
| D-6 | **SEA 打包**：Node.js Single Executable Application | ADR-4 | P1 |
| D-7 | **mynet update**：从 CTL manifest 检查 + 自动下载 | ADR-6 | P2 |
| D-8 | **mynet diag**：网络诊断（ping 节点、检查端口、测试 GNB 隧道） | — | P2 |
| D-9 | **从 CTL 拉取 defaults**：首次启动读 `/api/v1/defaults/client.json` | ADR-6 | P1 |

### 11.4 mynet_lua (LuCI) — 补充项

| 编号 | 任务 | 关联 ADR |
|------|------|---------|
| L-5 | **procd init 脚本**：实现 `/etc/init.d/mynetd` + respawn | ADR-2 |
| L-6 | **cron 心跳**：`/usr/lib/mynet/heartbeat.sh` + HMAC 签名 | ADR-2 |
| L-7 | **从 CTL 拉取默认配置** | ADR-6 |

---

## 十二、修订后的执行时序

### Phase 1: 核心闭环（2-3 周）

```
 Week 1
  ├─ [back]   B-1 心跳格式兼容 + B-9 文档修正
  ├─ [ctl]    C-8 install 脚本 + C-11 defaults 端点
  └─ [web]    W-1 下载页对接 CTL

 Week 2
  ├─ [client] D-1 Token 认证 + D-3 setup 向导（CLI 版）
  ├─ [gui]    G-2 节点详情→配置下载按钮
  └─ [tui]    T-1 config-pack 适配

 Week 3
  ├─ [client] D-2 daemon 基础版（心跳 + GNB spawn）
  ├─ [ctl]    C-13 索引优化 + C-14 新产品注册
  └─ 端到端测试
```

### Phase 2: 打包发行 + 自动化（3-4 周）

```
 Week 4-5
  ├─ [client] D-6 SEA 打包 → CTL 上传
  ├─ [client] D-5 service install (systemd/launchd)
  ├─ [ctl]    C-9 Node.js runtime 端点
  └─ [ctl]    C-10 预配置模板

 Week 6-7
  ├─ [client] D-4 WebUI 完成
  ├─ [client] D-7 自动更新
  ├─ [lua]    L-5 procd init + L-6 cron 心跳
  └─ [back]   B-11 直接 config-pack 端点
```

### Phase 3: 产品化（4-6 周）

```
 Week 8-10
  ├─ [back]   B-3 API 文档 + B-7 核心测试
  ├─ [gui]    G-1 真实 Dashboard + G-7 WebSocket
  ├─ [web]    W-2 产品内容 + W-4 SEO
  └─ [ctl]    C-4 Admin 管理后台

 Week 11-12
  ├─ [back]   B-4 Route Access Rules + B-5 Admin Dashboard
  ├─ [gnb]    I-1 自动化发布
  └─ [client] D-8 网络诊断
```

---

## 十三、依赖关系图

```
独立可做（无依赖）：
  [back] B-1 心跳兼容
  [back] B-6 Rate Limiting
  [back] B-9 文档修正
  [ctl]  C-8 install 脚本
  [ctl]  C-11 defaults 端点
  [ctl]  C-13 索引优化
  [web]  W-1 下载页对接

需要 ctl 先完成：
  [client] D-3 setup → 依赖 C-8 install 脚本, C-11 defaults
  [client] D-7 update → 依赖 C-14 新产品注册
  [web]    W-1 → 依赖 CTL manifest API（已完成）

需要 back 先完成：
  [client] D-1 token 认证 → 依赖 back login API（已完成）
  [gui]    G-7 WebSocket → 依赖 back Reverb（已配置）
  [tui]    T-2 心跳适配 → 依赖 B-1

需要 client 先完成：
  [client] D-6 SEA 打包 → 依赖 D-1 + D-2 + D-3
  [ctl]    C-14 client 产品 → 依赖 D-6 产出 artifact
```

---

## 十四、安全深度分析 — Token 窃取与认证分层

### 14.1 威胁模型

客户端 token 不同于浏览器，没有 sandbox 隔离。攻击面分析：

```
┌─────────────────────────────────────────────────┐
│ 威胁                     │ 浏览器 (gui) │ CLI (client/tui/lua) │
├─────────────────────────────────────────────────┤
│ 其他进程读取 token       │ ❌ 沙箱隔离   │ ⚠️ 同用户进程可读   │
│ 恶意扩展/插件            │ ⚠️ 有可能     │ ⚠️ 有可能           │
│ 中间人攻击               │ ❌ HTTPS      │ ❌ HTTPS            │
│ 磁盘泄露 (备份/日志)     │ ❌ 内存存储   │ ⚠️ 文件持久存储     │
│ root/admin 权限提升      │ ❌ 浏览器限制 │ ⚠️ root 可读一切    │
│ 物理接触设备             │ ⚠️            │ ⚠️                  │
└─────────────────────────────────────────────────┘
```

### 14.2 现实评估

**credentials.json (0600) 被窃取的条件**：
1. 攻击者已获得**同用户**的进程执行权限
2. 或攻击者已获得 **root 权限**

**如果攻击者已经在目标机器上跑进程** — token 泄露只是最小的问题。攻击者已经可以：
- 直接读取 GNB 配置、密钥
- 注入网络流量
- 控制整个节点

**结论**：token 文件窃取的威胁等级 = **"如果他已经在你机器里了，你有更大的问题"**。  
这与 SSH 私钥 (`~/.ssh/id_rsa`, 0600) 的安全模型完全一致。

### 14.3 HMAC-SHA256 是否还有意义？

**有意义，但目的不同于防窃取**。

三层认证的职责划分：

```
层级 1: Sanctum Token (用户认证)
  │  目的: 证明「这个请求来自已登录的用户 Y」
  │  保护: 误操作、未授权访问
  │  威胁模型: 防止未登录/已过期的访问
  │  
层级 2: HMAC-SHA256 (节点认证)
  │  目的: 证明「这个心跳来自节点 N，且数据未被篡改」
  │  保护: back 验证节点发出的心跳是真的（不是伪造的）
  │  关键价值: ★ 防篡改 ★ — 即使中间人截获心跳，
  │            无法修改内容（改了签名不对）
  │  威胁模型: 防止其他节点冒充、防止心跳数据篡改
  │
层级 3: GNB 隧道加密 (传输层)
  │  目的: 数据包端到端加密
  │  保护: 传输内容机密性
  └──
```

**关键区分**：

| 场景 | Sanctum Token 够吗？ | 需要 HMAC 吗？ |
|------|---------------------|---------------|
| 客户端→ back API (配置/管理) | ✅ 够 | ❌ |
| mynetd→ back (心跳上报) | ⚠️ 不够 — token 有 30min TTL，daemon 长驻 | ✅ 需要 |
| 节点身份证明（我就是节点 #123） | ❌ token 是用户的 | ✅ HMAC key 是节点的 |
| 防止心跳数据被篡改 | ❌ | ✅ |

**HMAC 的价值 = 节点级身份认证 + 消息完整性**，不可替代。

### 14.4 推荐的安全加固措施

不需要重新设计认证体系，只需加固已有方案：

| 措施 | 成本 | 效果 | 优先级 |
|------|------|------|--------|
| ✅ credentials.json 权限 0600 | 零 | 防止其他用户读取 | 已有 |
| **Token Binding** | 小 | 将 token 绑定 IP / 设备指纹 → 偷走了也不能用 | 🔴 推荐 |
| **Shorter Node Token TTL** | 小 | client 的 auth_token 缩短到 15min，更频繁刷新 | 🟡 可选 |
| **Refresh Token 单次使用** | 小 | 每次刷新后旧 refresh_token 立即失效，被盗用时自动触发告警 | 🔴 推荐 |
| **HMAC key 独立于 token** | 零 | 保持独立 — HMAC key 是节点的，token 是用户的，互不替代 | 已有 |
| **keychain/secretservice 集成** | 中 | macOS Keychain / Linux Secret Service 存 token | 🟢 未来 |
| **配置文件加密** | 中 | AES 加密 credentials.json，运行时解密 | 🟢 未来 |

**Token Binding 实现方案**：

```
Login 时:
  client 发送 { email, password, device_fingerprint: sha256(hostname+mac+...) }
  back 将 fingerprint 与 token 关联

API 调用时:
  client 发送 X-Device-Fingerprint: sha256(hostname+mac+...)
  back 验证 fingerprint 匹配 → 通过
  fingerprint 不匹配 → 拒绝 + 吊销 token + 通知用户

效果: 即使 token 被窃取，在另一台机器上无法使用
```

---

## 十五、API v2 统一规划 — 路径优化与迁移策略

### 15.1 现状问题

对 mynet_api 110+ 端点做了全面审计，发现：

| 问题 | 详情 |
|------|------|
| **41% 端点零调用** | /social (13), /notifications (8), /ippools (8), /ids (6) — 无客户端使用 |
| **配置端点重复** | 3 种方式获取同一份配置（`/nodes/{id}/config`, `?render_conf=1`, `/monitor/node/config`） |
| **状态端点混乱** | 3 种方式获取节点状态（`status`, `status-info`, `status-check`） |
| **响应格式不统一** | 至少 3 种 envelope: `{success,data}`, `{data}`, `{data,message,timestamp,request_id}` |
| **密钥操作语义不清** | `POST /keys/generate` vs `POST /keys/upload` 都是 POST |

### 15.2 v2 API 设计

**策略**: v1 和 v2 并存运行，客户端逐步迁移。

```
/api/v1/... → 现有全部端点不动（back 继续支持）
/api/v2/... → 新的精简 API（新客户端优先使用）
```

#### v2 端点清单（从 110+ 精简到 ~45）

```
/api/v2/

── Auth ────────────────────────────────
POST   /auth/login                    ← 全客户端
POST   /auth/logout
POST   /auth/refresh
GET    /auth/verify

── Users ───────────────────────────────
GET    /users/me                      ← 替代 /users/profile
PUT    /users/me
GET    /users/me/zones                ← 替代 /user/zones (去掉 user 单数不一致)

── Zones ───────────────────────────────
GET    /zones
POST   /zones
GET    /zones/{id}
PUT    /zones/{id}
DELETE /zones/{id}
GET    /zones/{id}/services           ← ?visibility=public|private|my
POST   /zones/{id}/services
PUT    /zones/{id}/services/{sid}
DELETE /zones/{id}/services/{sid}

── Nodes ───────────────────────────────
GET    /nodes                         ← ?zone_id=X 过滤
POST   /nodes
GET    /nodes/{id}
PUT    /nodes/{id}
PATCH  /nodes/{id}                    ← 部分更新（含 status）
DELETE /nodes/{id}

── Node 配置（合并 3 个端点为 1 个模式）──
GET    /nodes/{id}/config             ← JSON 结构化配置
GET    /nodes/{id}/config/node.conf   ← text/plain GNB 配置
GET    /nodes/{id}/config/route.conf  ← text/plain 路由
GET    /nodes/{id}/config/address.conf ← text/plain 地址
GET    /nodes/{id}/config-pack        ← application/zip 全打包

── Node 密钥（语义明确）──────────────
GET    /nodes/{id}/keys               ← 列出节点公钥
POST   /nodes/{id}/keys/generate      ← 服务端生成
PUT    /nodes/{id}/keys               ← 客户端上传（PUT = 幂等替换）
DELETE /nodes/{id}/keys/{keyId}

── 路由 ────────────────────────────────
GET    /nodes/{id}/routes             ← 替代 /route/node/{id}
POST   /nodes/{id}/routes
PUT    /nodes/{id}/routes/{rid}
DELETE /nodes/{id}/routes/{rid}

── 监控（签名认证）────────────────────
POST   /monitor/heartbeat
GET    /monitor/health

── 批量操作（新增）─────────────────────
POST   /batch/nodes/status            ← 批量状态更新
POST   /batch/nodes/config            ← 批量配置获取
```

#### 与 v1 对比

| 维度 | v1 | v2 |
|------|----|----|
| 端点数 | 110+ | ~45 |
| 配置获取方式 | 3 种 | 1 个模式（子路径区分格式） |
| 状态操作 | 3 个端点 | 1 个（`PATCH /nodes/{id}`） |
| 响应格式 | 3 种 | 统一 envelope |
| 未用端点 | 39 个 | 0 |
| Routes 归属 | 独立 `/route/` | 归入 `/nodes/{id}/routes` |
| 密钥语义 | POST/POST 冲突 | POST(生成) / PUT(上传) |

#### 统一响应 Envelope

```json
{
  "ok": true,
  "data": { ... },
  "error": null,
  "meta": {
    "api_version": "v2",
    "request_id": "req_abc123",
    "timestamp": "2026-03-21T10:00:00Z"
  }
}

// 错误响应
{
  "ok": false,
  "data": null,
  "error": {
    "code": "AUTH_TOKEN_EXPIRED",
    "message": "Token has expired, please refresh",
    "details": { "expired_at": "..." }
  },
  "meta": { ... }
}
```

### 15.3 v2 迁移策略

```
Phase 1: v2 路由注册（v1 保持不变）
  ├─ back 创建 routes/api_v2.php
  ├─ v2 Controller 继承 v1 逻辑，只加 envelope wrapper
  └─ v1 → v2 映射测试

Phase 2: 新客户端直接用 v2
  ├─ mynet_client (Node.js) 从第一天就用 v2
  ├─ mynet_gui 前端逐步换（一个模块一个模块迁移）
  └─ mynet_lua 新版本用 v2

Phase 3: v1 Deprecation
  ├─ v1 响应头加 Sunset: {date}
  ├─ tui (frozen) 保留 v1 不迁移
  └─ 6 个月后评估 v1 是否可下线
```

---

## 十六、代码疏剪清单

### 16.1 mynet_api — 确认可删除

| 文件/位置 | 内容 | 置信度 | 操作 |
|-----------|------|--------|------|
| routes/api_v1.php ~11 处 `// 未实现` 注释路由 | 路由访问规则、拓扑、模拟等从未实现的端点 | 🔴 HIGH | **删除注释** |
| routes/api_v1.php 2 处废弃路由 | `/{id}/config` 使用已删除的 NodeConfigController | 🔴 HIGH | **删除路由** |
| NodeConfigController.php | 依赖类在 commit 0e1b0e9 中删除 | 🔴 HIGH | **删除整个文件** |
| /social/* 13 个端点 | 零客户端调用 | 🟡 MEDIUM | **不删除代码，标记 @deprecated** |
| /notifications/* 8 个端点 | 零客户端调用 | 🟡 MEDIUM | **同上** |
| /ippools/* 8 个端点 | GUI 有 UI 但零 API 调用 | 🟡 MEDIUM | **保留，可能未来用** |
| /ids/* 6 个端点 | 零调用 | 🟡 MEDIUM | **标记 @deprecated** |

### 16.2 mynet_ctl — 确认可删除

| 文件 | 内容 | 置信度 | 操作 |
|------|------|--------|------|
| resources/views/welcome.blade.php | Laravel 默认欢迎页，无路由指向 | 🔴 HIGH | **删除** |

### 16.3 mynet_gui — 待逐步确认

| 模块 | 现状 | 操作 |
|------|------|------|
| Social sidebar 组件 | 有 UI 组件但 API 未实现 | **Phase 3 再处理** |
| Notification center | 有 UI 但零 API 调用 | **Phase 3 再处理** |
| Dashboard mock 数据 | 写死的假数据 | **Phase 2 替换为真实 API** |
| IP Pool 详情页 | 只有列表无详情 | **Phase 2 完善** |

### 16.4 执行优先级

```
立即可做（零风险）:
  1. 删除 back 中 11 处 "未实现" 注释路由
  2. 删除 back 中 2 处废弃路由 + NodeConfigController
  3. 删除 ctl 中 welcome.blade.php

后续做（需验证）:
  4. back 中零调用端点标记 @deprecated（加 log 记录是否真的无人调用）
  5. gui 中 mock 数据模块替换
```

---

## 十七、本地测试环境搭建方案

### 17.1 本机环境现状

```
已安装（Homebrew）:
  ✅ PHP 8.3.8
  ✅ MySQL 8.3.0 (未运行)
  ✅ Nginx
  ✅ Node.js (多版本: 17, 21, 22)
  ✅ Composer, NPM

未安装:
  ❌ Redis (refresh token 需要)
```

### 17.2 数据库方案

**生产数据库 (mynet_api)**: 有两份备份可用

| 备份文件 | 日期 | 表数 |
|----------|------|------|
| `database_full_backup_20250913_173205.sql.gz` | 2025-09-13 | 38 表 |
| `database_backup_20251029_095515.sql.gz` | 2025-10-29 | 40 表 (新增 binary_assets, notifications) |

**本地域名规划** (hosts + Nginx vhost):

| 域名 | 指向 | 端口 | 项目 |
|------|------|------|------|
| `api.mynet.local` | 127.0.0.1 | 8001 | mynet_api |
| `admin.mynet.local` | 127.0.0.1 | 8001 | mynet_api |
| `ctl.mynet.local` | 127.0.0.1 | 8002 | mynet_ctl |
| `my.mynet.local` | 127.0.0.1 | 9000 | mynet_gui (Vite) |
| `www.mynet.local` | 127.0.0.1 | 9001 | mynet_web (Vite) |

### 17.3 搭建步骤（脚本化）

```bash
#!/bin/bash
# setup-local-env.sh — MyNet 本地开发环境一键搭建
# 从 mynet_ctl 项目根目录执行

set -e
BASE="/Users/yue/Devel/WorkSpacePHP/mynet.club"

echo "=== Step 1: 启动 MySQL ==="
brew services start mysql@8.3

echo "=== Step 2: 安装 Redis ==="
brew install redis
brew services start redis

echo "=== Step 3: 创建数据库 ==="
mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS mynet_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mynet_ctl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mynet_javis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建开发用户
CREATE USER IF NOT EXISTS 'mynet_dev'@'localhost' IDENTIFIED BY 'mynet_dev_2026';
GRANT ALL ON mynet_api.* TO 'mynet_dev'@'localhost';
GRANT ALL ON mynet_ctl.* TO 'mynet_dev'@'localhost';
GRANT ALL ON mynet_javis.* TO 'mynet_dev'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "=== Step 4: 导入生产数据结构（仅 schema，不含数据）==="
# 从最新备份提取 schema-only
gzcat "$BASE/mynet_api/database_backup_20251029_095515.sql.gz" \
  | sed -n '/^CREATE TABLE/,/^).*;/p' \
  | mysql -u mynet_dev -pmynet_dev_2026 mynet_api

# 或：导入完整备份（含数据，适合调试）
# gzcat "$BASE/mynet_api/database_backup_20251029_095515.sql.gz" \
#   | mysql -u mynet_dev -pmynet_dev_2026 mynet_api

echo "=== Step 5: 配置 mynet_api ==="
cat > "$BASE/mynet_api/.env" <<'ENV'
APP_NAME=MyNet
APP_ENV=local
APP_DEBUG=true
APP_URL=http://api.mynet.local:8001

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mynet_api
DB_USERNAME=mynet_dev
DB_PASSWORD=mynet_dev_2026

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=my.mynet.local:9000,admin.mynet.local:8001
ENV

echo "=== Step 6: 配置 mynet_ctl ==="
cat > "$BASE/mynet_ctl/.env.local" <<'ENV'
APP_NAME="MyNet CTL"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://ctl.mynet.local:8002

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mynet_ctl
DB_USERNAME=mynet_dev
DB_PASSWORD=mynet_dev_2026

MYNET_BACK_API_URL=http://api.mynet.local:8001
ENV

echo "=== Step 7: CTL migration ==="
cd "$BASE/mynet_ctl"
php artisan migrate --force

echo "=== Step 8: 配置 hosts ==="
# 需要 sudo
sudo tee -a /etc/hosts <<'HOSTS'
# MyNet Local Dev
127.0.0.1 api.mynet.local admin.mynet.local ctl.mynet.local my.mynet.local www.mynet.local
HOSTS

echo "=== Step 9: 种子数据 ==="
cd "$BASE/mynet_api"
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=DefaultZoneSeeder

echo "=== 完成！==="
echo "启动各项目："
echo "  back: cd $BASE/mynet_api && php artisan serve --port=8001"
echo "  ctl:  cd $BASE/mynet_ctl && php artisan serve --port=8002"
echo "  gui:  cd $BASE/mynet_gui && npm run dev"
echo "  web:  cd $BASE/mynet_web && npm run dev"
```

### 17.4 数据安全注意

```
⚠️ 生产备份 .sql.gz 包含真实用户数据！
   - 不要提交到 Git
   - 本地测试完后清理
   - 可选：导入后 UPDATE users SET email=concat('test_',id,'@example.com')
                   UPDATE users SET password=bcrypt('test123')
```

### 17.5 完整导入 vs Schema-only

| 方案 | 适用场景 | 命令 |
|------|----------|------|
| **Schema-only** | 只需表结构 + 空库 | `sed` 提取 CREATE TABLE 语句 |
| **Full import** | 需要真实数据调试 | 直接 `gzcat \| mysql` |
| **Seeder** | 已有 factory/seeder | `php artisan db:seed` |

推荐：**先 Schema-only + Seeder**，真正需要排查线上 bug 时再用 Full import。

### 17.6 多项目联调启动

```bash
# Terminal 1: mynet_api API
cd /Users/yue/Devel/WorkSpacePHP/mynet.club/mynet_api
php artisan serve --port=8001

# Terminal 2: mynet_ctl
cd /Users/yue/Devel/WorkSpacePHP/mynet.club/mynet_ctl
php artisan serve --port=8002

# Terminal 3: mynet_gui (Vite dev server)
cd /Users/yue/Devel/WorkSpacePHP/mynet.club/mynet_gui
npm run dev -- --port 9000

# Terminal 4: mynet_web (Vite dev server)
cd /Users/yue/Devel/WorkSpacePHP/mynet.club/mynet_web
npm run dev -- --port 9001

# Terminal 5: mynet_client (Node.js CLI + WebUI)
cd /Users/yue/Devel/WorkSpacePHP/mynet.club/mynet_client
npm link && mynet web --port 7070
```
