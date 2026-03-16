# MyNet CTL（控制分发中心）— Copilot 指南

本仓库是 **mynet.club 站点软件分发中心**，基于 Laravel 12 / PHP 8.2。

## 🎯 项目定位

CTL（Control）负责**软件分发**，不负责节点配置（配置在 mynet_back 中）：

| 职责 | 属于 CTL | 不属于 CTL |
|------|----------|-----------|
| tui/utc/gnb 版本二进制管理 | ✅ | |
| 插件上传/下载 | ✅ | |
| 安装脚本/服务脚本发布 | ✅ | |
| 节点 VPN 配置 | | ❌ → mynet_back |
| 用户账号/Zone 管理 | | ❌ → mynet_back |

## 🚨 强制规则

### 禁止新增 Migration
- ❌ 不要创建 `database/migrations/`
- ✅ 表结构由 `database/sql/ctl_tables.sql` 统一管理

### Controller 必须薄
```php
// ✅ 正确
public function store(Request $request): JsonResponse {
    $resource = $this->service->upload($request->validated(), $request->file('file'));
    return response()->json(['data' => $resource], 201);
}
```

### DDD 架构
- `app/Domain/`：纯业务逻辑（平台/架构矩阵、版本号校验），不依赖框架
- `app/Services/`：协调 Domain + Storage + Model
- `app/Http/Controllers/`：参数校验 → 调用 Service → 返回 JSON

## 📦 支持的产品矩阵

### 产品（product）
- `tui` — Go 终端客户端
- `utc` — C UDP→TCP 隧道转换器
- `gnb` — GNB VPN 核心

### 平台（platform）
- `linux` + `openwrt` + `darwin` + `windows`

### 架构（arch）
```
amd64 | arm64 | armv7 | i386
mips | mips64 | mipsel | mips64el
mips-softfp | mipsel-softfp | arm64-softfp | armv7-softfp
riscv64
```

## 🔑 API 认证

所有**写操作**需要 Bearer Token：

```
Authorization: Bearer {token}
```

Token 存储在 `api_tokens` 表，使用 SHA256 哈希。权限（abilities）：
- `release:upload` — 上传版本
- `release:manage` — 删除版本
- `plugin:upload` — 上传插件
- `plugin:manage` — 管理插件
- `script:publish` — 发布脚本
- `script:manage` — 管理脚本
- `*` — 全部权限

**读操作（下载/查询）无需认证**。

## 🛠️ 常用命令

```bash
# 启动开发服务
php artisan serve

# 验证路由
php artisan route:list

# 清理缓存
php artisan config:clear && php artisan route:clear
```

## 📁 目录结构

```
app/
  Domain/
    Release/Services/ReleaseDomainService.php   # 平台/架构矩阵、版本校验
    Plugin/Services/PluginDomainService.php     # 插件分类/兼容性
    Script/Services/ScriptDomainService.php     # 脚本校验/SHA256
  Services/
    Release/ReleaseService.php    # 上传、下载、latest 标记
    Plugin/PluginService.php      # 插件上传下载
    Script/ScriptService.php      # 脚本发布、原始内容
  Http/
    Controllers/Api/V1/
      ReleaseController.php
      PluginController.php
      ScriptController.php
    Middleware/ApiTokenAuth.php
  Models/
    Release.php | Plugin.php | Script.php | DownloadLog.php | ApiToken.php
database/sql/ctl_tables.sql       # 所有表定义（禁止用 migrations）
routes/api.php                    # API 路由（所有端点）
storage/app/
  releases/   # 二进制文件
  plugins/    # 插件文件
  scripts/    # 脚本文件（备份，内容主存 DB）
```

## 📡 API 端点总览

| Method | Path | 认证 | 说明 |
|--------|------|------|------|
| GET | /api/v1/releases | 无 | 列出版本 |
| GET | /api/v1/releases/latest | 无 | 最新版本 |
| GET | /api/v1/releases/{id}/download/{file} | 无 | 下载 |
| POST | /api/v1/releases | release:upload | 上传版本 |
| DELETE | /api/v1/releases/{id} | release:manage | 下架 |
| GET | /api/v1/plugins | 无 | 列出插件 |
| GET | /api/v1/plugins/{id}/download/{file} | 无 | 下载插件 |
| POST | /api/v1/plugins | plugin:upload | 上传插件 |
| DELETE | /api/v1/plugins/{id} | plugin:manage | 下架插件 |
| GET | /api/v1/scripts | 无 | 列出脚本 |
| GET | /api/v1/scripts/{id}/raw/{name} | 无 | 获取脚本原文 |
| POST | /api/v1/scripts | script:publish | 发布脚本 |
| PUT | /api/v1/scripts/{id} | script:publish | 更新脚本 |
| DELETE | /api/v1/scripts/{id} | script:manage | 下架脚本 |

### 脚本直接执行示例

```bash
# 安装脚本（curl | bash 模式）
curl -fsSL https://ctl.mynet.club/api/v1/scripts/123/raw/install.sh | bash

# 检查 SHA256
curl -I https://ctl.mynet.club/api/v1/scripts/123/raw/install.sh
# 响应头: X-Script-Checksum: {sha256}
```
