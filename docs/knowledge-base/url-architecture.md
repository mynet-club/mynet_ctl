# MyNet CTL — URL 架构与分发协议

> 创建日期：2026-03-25  
> 适用版本：CTL v2.x（Laravel 12）

---

## 一、URL 职责分离

CTL 的 URL 严格分为三类，**禁止混用**：

| 前缀 | 职责 | Content-Type | 认证 | 缓存策略 |
|------|------|-------------|------|---------|
| `/api/v2/` | RESTful 业务接口 | `application/json` | 写操作需 Bearer Token | no-cache |
| `/d/` | 文件分发（CDN-ready） | 按文件类型 | 无 | public（二进制）/ no-cache（脚本/清单） |
| `/` | 公开页面（HTML） | `text/html` | 无 | 标准 |

### 为什么做分离？

- `/api/` 是给程序调用的 JSON 接口，不应返回二进制/脚本
- `/d/` 是给 CDN/客户端直接下载的分发路径，不需要认证
- 页面是给浏览器用户的，有 CSRF 等 web 中间件

---

## 二、`/d/` 分发路径完整目录

```
/d/
├── apps.json                              # 产品索引
├── conventions.json                       # 命名规范（SSOT）
├── docs/{name}.md(.sha256)                # 知识库公开文档（skill-kit 等，白名单）
├── {product}/manifest.json                # 版本清单（含 scripts 映射）
├── {product}/plugins.json                 # 插件列表
├── {product}/{filename}.tgz               # 二进制文件下载
├── detect/openwrt.sh                      # OpenWrt 检测脚本
├── proxy/install.sh                       # 代理安装脚本
├── plugins/{filename}                     # 插件下载
├── scripts/{name}.sh                      # 脚本下载（通用/最新版）
└── scripts/{targetVersion}/{name}.sh      # 脚本下载（版本专属）
```

### URL 示例

```bash
# 产品索引
GET /d/apps.json

# 版本清单（mynet/gnb/utc/client）
GET /d/mynet/manifest.json
GET /d/gnb/manifest.json

# 二进制下载
GET /d/mynet/mynet_linux_amd64_v1.4.0.tgz
GET /d/gnb/gnb_openwrt_mipsel-softfp_v1.6.0.a.tgz

# 脚本下载（通用版 = 最新版）
GET /d/scripts/install.sh
GET /d/scripts/upgrade.sh
GET /d/scripts/mynet-service.bash

# 脚本下载（版本专属，有则用，无则由服务端 fallback 到通用版）
GET /d/scripts/v1.4.0/install.sh
GET /d/scripts/v1.5.0/install.sh

# OpenWrt 检测
GET /d/detect/openwrt.sh

# 插件
GET /d/plugins/mihomo_linux_amd64_v1.0.0.tgz

# 知识库文档（skill 自升级源）
GET /d/docs/skill-kit.md
GET /d/docs/skill-kit.md.sha256
```

---

## 三、脚本版本化协议

### 3.1 设计原则

脚本（install.sh、upgrade.sh 等）采用**产品版本关联**模式：

- 每个脚本有一个**通用版**（`target_version IS NULL`），适用于所有版本
- 可选地，为特定产品版本创建**专属版**（`target_version = 'v1.5.0'`）
- 客户端请求时，**服务端自动 fallback**：有专属版用专属版，无则用通用版

### 3.2 数据模型

```sql
CREATE TABLE scripts (
    ...
    name            VARCHAR(255),       -- 脚本基础名（不含扩展名）
    target_version  VARCHAR(32) NULL,   -- 关联的产品版本，NULL = 通用版
    lang            VARCHAR(10),        -- sh | bash | py → 决定扩展名
    content         LONGTEXT,           -- 脚本内容（直接存 DB）
    ...
    UNIQUE KEY uk_name_platform_target (name, platform, target_version)
);
```

### 3.3 URL 路由

| URL | 含义 | 服务端行为 |
|-----|------|-----------|
| `/d/scripts/{name}.sh` | 通用版 | 查找 `target_version IS NULL` 的记录 |
| `/d/scripts/v1.4.0/{name}.sh` | 版本专属 | 先找 `target_version = 'v1.4.0'`，无则 fallback 到通用版 |

### 3.4 manifest.json 中的 scripts 字段

每个版本条目自动包含 `scripts` 字段：

```json
{
  "versions": {
    "v1.4.0": {
      "version": "v1.4.0",
      "artifacts": [...],
      "scripts": {
        "install": "https://ctl.mynet.club/d/scripts/install.sh",
        "upgrade": "https://ctl.mynet.club/d/scripts/upgrade.sh",
        "mynet-service": "https://ctl.mynet.club/d/scripts/mynet-service.bash"
      }
    }
  }
}
```

当存在 v1.5.0 专属的 install.sh 时：

```json
"scripts": {
  "install": "https://ctl.mynet.club/d/scripts/v1.5.0/install.sh",
  "upgrade": "https://ctl.mynet.club/d/scripts/upgrade.sh"
}
```

### 3.5 客户端消费协议

```
1. GET /d/{product}/manifest.json
2. 找到目标版本条目
3. 读取 scripts.install → 下载并执行
4. 读取 scripts.upgrade → 下载并执行（升级场景）
5. 服务端已处理 fallback，客户端无需额外逻辑
```

---

## 四、客户端 URL 配置要求

### 正确配置

| 项目 | 配置文件 | 字段 | 正确值 |
|------|---------|------|--------|
| mynet_client | `conf/config.json` | `downloads.download_index_url` | `https://ctl.mynet.club/d/apps.json` |
| mynet_lua | `root/etc/mynet/conf/config.json` | `downloads.apps_index_url` | `https://ctl.mynet.club/d/apps.json` |
| mynet_tui | `conf/config.json` | `downloads.apps_index_url` | `https://ctl.mynet.club/d/apps.json` |

### 废弃 URL（禁止使用）

| 废弃 URL | 替代 | 原因 |
|----------|------|------|
| `https://download.mynet.club/apps.json` | `/d/apps.json` | download.mynet.club 已禁止目录访问 |
| `https://ctl.mynet.club/api/v2/apps.json` | `/d/apps.json` | `/api/` 不做文件分发 |
| `https://ctl.mynet.club/api/v1/releases/{id}/download/{file}` | `/d/{product}/{filename}` | 不暴露内部 ID |
| `https://ctl.mynet.club/api/v1/scripts/{id}/raw/{name}` | `/d/scripts/{name}.sh` | 不暴露内部 ID |

### 客户端推导 CTL base URL 的方法

```javascript
// mynet_client 示例：从 apps_index_url 推导 CTL base
const appsUrl = config.downloads.download_index_url;
const ctlBase = appsUrl.replace(/\/d\/apps\.json$/, '');
// ctlBase → "https://ctl.mynet.club"

// 然后：
const manifestUrl = `${ctlBase}/d/${product}/manifest.json`;
```

---

## 五、Nginx 配置要点

```nginx
# /d/ 分发路由 — CORS + 无缓存
location /d/ {
    add_header Access-Control-Allow-Origin "*" always;
    add_header Cache-Control "no-cache, no-store, must-revalidate" always;
    try_files $uri $uri/ /index.php?$query_string;
}
```

注意：`/d/` 下的所有请求最终都由 Laravel 处理（`try_files` 转发到 `index.php`），
不是真实静态文件。未来可将二进制文件放到 CDN，通过 302 重定向实现。

---

## 六、数据流总览

```
mynet_client / mynet_lua / mynet_tui
    │
    ├─ GET /d/apps.json
    │   → { "apps": { "mynet": "/d/mynet/manifest.json", "gnb": "/d/gnb/manifest.json" } }
    │
    ├─ GET /d/{product}/manifest.json
    │   → { versions: { "v1.4.0": { artifacts: [...], scripts: {...} } } }
    │
    ├─ GET /d/{product}/{filename}.tgz          ← 二进制下载
    │
    └─ GET /d/scripts/{name}.sh                 ← 脚本下载
       GET /d/scripts/{targetVersion}/{name}.sh ← 版本专属脚本
```

---

## 七、Laravel 路由注意事项

### 同一 controller method 不能用于不同参数数量的路由

```php
// ❌ 错误：两个路由共用一个 controller method，参数数量不同 → 多参数路由 404
Route::get('/scripts/{targetVersion}/{name}', [ScriptController::class, 'rawByRoute']);
Route::get('/scripts/{name}', [ScriptController::class, 'rawByRoute']);

// ✅ 正确：分开方法，私有共享逻辑
Route::get('/scripts/{targetVersion}/{name}', [ScriptController::class, 'rawVersioned']);
Route::get('/scripts/{name}', [ScriptController::class, 'rawByRoute']);
```

这是 Laravel 12 路由编译的已知行为：同一 controller action 映射多条不同参数数量的路由时，
Symfony RouteCompiler 只会为第一个（少参数版）生成有效的编译正则，导致多参数版 404。
解决方法是使用独立方法名 + 私有共享方法。
