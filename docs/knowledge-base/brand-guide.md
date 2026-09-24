# MyNet 品牌视觉与术语规范 (Brand Guide)

> **SSOT (Single Source of Truth)** — 所有子项目必须遵守本规范。
> 预览页面: `ctl.mynet.club/brand-preview.html`

---

## 1. 品牌定位

| 属性 | 值 |
|------|-----|
| **品牌名** | MyNet（首字母大写，中间无空格） |
| **一句话定位** | 自主组网平台 |
| **English** | Self-sovereign networking platform |
| **禁用描述** | ~~VPN 管理系统~~、~~私密社交网络平台~~、~~网络连接平台~~ |

---

## 2. 色彩系统

### 2.1 Brand Indigo（主色）

基于 Tailwind Indigo 色阶，`600` 为默认主色。

| Token | Hex | 用途 |
|-------|-----|------|
| brand-50 | `#eef2ff` | 浅背景、hover bg |
| brand-100 | `#e0e7ff` | 选中背景 |
| brand-200 | `#c7d2fe` | 边框高亮 |
| brand-300 | `#a5b4fc` | — |
| brand-400 | `#818cf8` | — |
| brand-500 | `#6366f1` | — |
| **brand-600** | **`#4f46e5`** | **主色：按钮、链接、选中态** |
| brand-700 | `#4338ca` | hover 态 |
| brand-800 | `#3730a3` | active 态 |
| brand-900 | `#312e81` | — |
| brand-950 | `#1e1b4b` | — |

### 2.2 品牌渐变（仅 Hero / 强品牌区域）

```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

- 用于：Hero 区、登录页背景、CTA 大按钮
- 不用于：普通卡片、导航、表单

### 2.3 状态色

| 语义 | Hex | Tailwind | 场景 |
|------|-----|----------|------|
| Success / 在线 | `#22c55e` | `emerald-500` | 设备在线、操作成功 |
| Warning / 待处理 | `#f59e0b` | `amber-500` | 预发布、等待中 |
| Error / 离线 | `#ef4444` | `red-500` | 设备离线、操作失败 |
| Info / 提示 | `#3b82f6` | `blue-500` | 信息提示、帮助 |

### 2.4 中性灰

使用 Tailwind 默认 `gray` 色阶：
- 背景: `gray-50` (#f9fafb)
- 边框: `gray-200` (#e5e7eb)
- 辅助文字: `gray-500` (#6b7280)
- 正文: `gray-700` (#374151)
- 标题: `gray-900` (#111827)

---

## 3. 字体系统

### 3.1 正文 / UI（Sans）

```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI',
             'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei',
             sans-serif;
```

### 3.2 代码 / 版本号（Mono）

```css
font-family: 'JetBrains Mono', 'Fira Code', Consolas,
             'Liberation Mono', Monaco, 'Courier New', monospace;
```

### 3.3 字号层级（Tailwind）

| Class | Size | 用途 |
|-------|------|------|
| `text-3xl` / `text-4xl` | 30-36px | Hero 大标题 |
| `text-2xl` | 24px | 区块标题 |
| `text-xl` | 20px | 卡片标题 |
| `text-base` | 16px | 正文 |
| `text-sm` | 14px | 导航、辅助 |
| `text-xs` | 12px | Badge、时间戳 |

---

## 4. Logo

### 4.1 SSOT

**唯一源文件位于 `mynet_ctl/public/images/`**:
- `icon.png` — 方形图标（favicon、头像）
- `icon.svg` — 矢量图标
- `logo.png` — 横排（icon + "MyNet" 文字）
- `logo.svg` — 横排矢量

### 4.2 使用规则

| 场景 | 写法 |
|------|------|
| Header | `<img> + "MyNet"` 文字（加子站标识，如 `CTL`） |
| Favicon | 各站引用 `https://ctl.mynet.club/favicon.ico` 或本地复制 |
| 深色背景 | icon + 白色文字 |
| 最小尺寸 | 不小于 16×16px |

---

## 5. 统一术语表

### 5.1 核心术语

| 术语 | 中文 | English | 适用范围 | 备注 |
|------|------|---------|----------|------|
| MyNet | MyNet | MyNet | 全局 | 品牌名，M 和 N 大写 |
| — | 自主组网平台 | Self-sovereign networking platform | 全局 | 一句话定位 |
| Zone | 组网空间 | Zone | 全局 | 隔离边界。不叫"时空" |
| Device | **设备** | Device | **GUI / CLI 用户面** | 面向用户统一叫"设备" |
| Node | 节点 | Node | API / DB / 文档技术面 | 技术场景保持 node |
| GNB | GNB (OpenGNB) | GNB | 全局 | 底层 P2P 协议 |
| UTC | 隧道加速器 | Tunnel Accelerator | 全局 | UDP→TCP 转换 |

### 5.2 Device vs Node 上下文规则

| 场景 | 用词 | 示例 |
|------|------|------|
| GUI 页面标题 | **设备** | "我的设备"、"设备列表"、"添加设备" |
| GUI 提示消息 | **设备** | "设备已上线"、"3 台设备在线" |
| CLI 命令 | **devices** | `mynet devices`、`mynet devices list` |
| CLI 用户输出 | **设备** | "发现 3 台设备" |
| API 端点 / JSON | node | `/api/v2/nodes`、`{ "node_id": 1001 }` |
| 数据库表名 | nodes | `SELECT * FROM nodes` |
| CTL 技术页面 | 节点 | "节点配置包" |
| 文档 / 架构 | Node / 节点 | "每个 Node 拥有唯一 Ed25519 密钥对" |

### 5.3 子站命名

| 域名 | 中文名 | English | 项目 |
|------|--------|---------|------|
| my.mynet.club | 用户控制台 | User Console | mynet_gui |
| ctl.mynet.club | 软件分发中心 | Distribution Center | mynet_ctl |
| console.mynet.club | 租户管理 | Tenant Console | mynet_back |
| www.mynet.club | 官网 | Website | mynet_web |
| myjarvis.ai | Jarvis AI | Jarvis AI | mynet_javis |

### 5.4 禁用表达

| ❌ 禁用 | ✅ 替换为 |
|---------|----------|
| VPN 管理系统 | 组网平台 / 自主组网平台 |
| MyNet VPN | MyNet |
| 私密社交网络平台 | 自主组网平台 |
| 节点（面向用户 UI） | 设备 |
| 时空（Zone） | 组网空间 |
| 用户中心 | 用户控制台 |

---

## 6. 统一页脚

所有 Web 项目使用相同页脚结构：

```
┌─────────────────────────────────────────────────────────┐
│ [icon] MyNet — {子站说明}          mynet.club · 用户控制台 · OpenGNB ↗ │
│────────────────────────────────────────────────────────────│
│                  © 2026 MyNet. All rights reserved.                    │
└─────────────────────────────────────────────────────────┘
```

各站 `{子站说明}`:
- CTL: "软件分发中心"
- GUI: "用户控制台"
- Web: "自主组网平台"
- Back Admin: "管理后台"

---

## 7. 组件约定（Web 项目）

### 7.1 卡片

```
白色背景 · rounded-xl · border border-gray-200
hover: border-brand-300 shadow-md
内边距: p-5
```

### 7.2 按钮

| 类型 | 样式 |
|------|------|
| Primary | `bg-brand-600 text-white rounded-lg hover:bg-brand-700` |
| Secondary | `border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50` |
| Text | `text-brand-600 hover:bg-brand-50 rounded-lg` |
| Danger | `bg-red-600 text-white rounded-lg hover:bg-red-700` |
| Disabled | `bg-gray-100 text-gray-400 cursor-not-allowed` |

### 7.3 Badge / 标签

| 类型 | 样式 |
|------|------|
| Brand | `bg-brand-50 text-brand-700 rounded-full` |
| Online | `bg-emerald-50 text-emerald-700 rounded-full` |
| Offline | `bg-red-50 text-red-700 rounded-full` |
| Platform | `bg-gray-100 text-gray-600 rounded-full` |
| New | `bg-brand-600 text-white rounded text-[10px]` |

### 7.4 代码块

```
bg-gray-900 rounded-xl p-5
命令前缀: text-emerald-400 "$"
注释: text-gray-500
```

### 7.5 Alert 提示框

```
圆角: rounded-lg
内边距: px-4 py-3
结构: icon (w-5 h-5) + text
Info:    bg-blue-50    border-blue-200    text-blue-800
Success: bg-emerald-50 border-emerald-200 text-emerald-800
Warning: bg-amber-50   border-amber-200   text-amber-800
Error:   bg-red-50     border-red-200     text-red-800
```

---

## 8. 导航样式

### 8.1 顶部导航（公共站）

```
bg-white · border-b border-gray-200 · sticky top-0 z-40
max-w-6xl · h-16
Logo: icon (h-8 w-8 rounded-xl) + "MyNet" bold + 子站标识 (brand-600 font-mono text-sm)
Nav items: px-3 py-2 text-sm font-medium rounded-lg
  Active: text-brand-600 bg-brand-50
  Normal: text-gray-600 hover:text-gray-900 hover:bg-gray-50
```

---

## 9. 项目适用矩阵

| 规范项 | CTL | Web | GUI | Back | Client | Lua |
|--------|-----|-----|-----|------|--------|-----|
| 品牌色 (Indigo) | ✅ | ✅ | ✅ | ✅ | — | — |
| 品牌渐变 | ✅ | ✅ | ✅ | — | — | — |
| 系统字体栈 | ✅ | ✅ | ✅ | ✅ | — | — |
| 统一术语 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Device/设备 | — | — | ✅ | — | ✅ | ✅ |
| 统一页脚 | ✅ | ✅ | ✅ | — | — | — |
| Logo SSOT | ✅ | ✅ | ✅ | ✅ | — | — |
| 组件库 | ✅ | ✅ | 参考 | — | — | — |

---

## 变更日志

| 日期 | 内容 |
|------|------|
| 2026-03-24 | v1.0 — 初版制定，涵盖色彩/字体/术语/Logo/组件规范 |
