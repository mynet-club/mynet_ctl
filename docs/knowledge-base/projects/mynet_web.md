# mynet_web — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Vue 3 / TypeScript 5.9 / Vite 7 |
| **域名** | www.mynet.club |
| **本地** | www.mynet.local (dev :5174) |
| **角色** | 官网落地页 |

## 核心职责
- 产品介绍（MyNet 理念：连接选择权）
- 功能展示
- 下载引导
- 文档中心
- 用户注册/登录入口（跳转 my.mynet.club）

## 页面
```
/           首页（Hero + 价值主张）
/features   功能特性
/ai         AI 算力展示
/village    游牧村（默认 Zone）
/docs       文档
/download   下载入口
/about      关于
```

## 部署
```bash
npm run build    → dist/
rsync → /data/app/mynet.club/www/
Nginx SSL + Gzip + SPA fallback
```

## 关键配置
```
VITE_API_BASE_URL → api.mynet.club
VITE_GUI_URL → my.mynet.club
VITE_CTL_URL → ctl.mynet.club
```
