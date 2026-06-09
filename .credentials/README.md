# MyNet 凭证集中目录

> ⚠️ 本目录所有「真实凭证」文件**不入 git**（由 [`.gitignore`](.gitignore) 强制）。  
> 仓库内仅保留 [`_templates/`](./_templates/) 占位模板与本 README。

## 用途

集中保存 mynet 生态全部凭证元数据，使「凭证去哪里查」有唯一入口。  
本目录是 **ctl 作为统一配置中心**的体现：所有项目的部署信息、域名、数据库连接、API token、第三方服务配置都从这里查阅。

## 目录结构（建立后）

```
.credentials/
├── .gitignore                    # 强制忽略
├── README.md                     # 本文件（入仓）
├── _templates/                   # 模板（入仓，不含敏感数据）
│   ├── servers.md
│   ├── databases.md
│   ├── api-tokens.md
│   ├── third-party.md
│   ├── domains.md
│   └── env/
│       ├── ctl.env.example
│       ├── back.env.example
│       └── gui.env.example
├── servers.md                    # 实际凭证（不入仓，本地维护）
├── databases.md
├── api-tokens.md
├── third-party.md
├── domains.md
└── env/
    ├── ctl.env                   # 生产 .env 真实备份
    ├── back.env
    └── gui.env
```

## 使用约定

1. **首次使用**：从 `_templates/` 拷贝同名文件到本目录根，填入真实值
2. **凭证写入**：本地维护，**禁止 git add 这些文件**（gitignore 已保护）
3. **同步密码管理器**：每条凭证应在 1Password / Bitwarden 中留备份引用
4. **轮换流程**：每季度审计一次，过期 token / 弱密码立即轮换

## 安全规则

- ❌ 永不 `git add -f` 真实凭证文件
- ❌ 永不在 PR / issue / commit message 中粘贴真实密码
- ❌ 永不通过聊天工具（Slack / 微信）传输真实凭证
- ✅ 通过 1Password 安全分享给授权人员
- ✅ 服务器登录后立即使用 SSH key 替代密码
- ✅ 数据库账户最小权限原则（应用账户≠root）

## 与代码中的 .env 关系

| 位置 | 用途 | 入 git |
|------|------|--------|
| `各项目/.env.example` | 模板 + 文档（占位） | ✅ |
| `各项目/.env` | 实际加载的运行时配置 | ❌（项目 .gitignore） |
| `ctl/.credentials/env/<project>.env` | 集中备份（运维用） | ❌（本目录 .gitignore） |

`.env` 是运行时来源；本目录是「凭证清单 + 备份」，不是运行时加载点。
