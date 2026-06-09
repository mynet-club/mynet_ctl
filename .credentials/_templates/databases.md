# 数据库凭证清单（模板）

> 从本模板拷贝到 `../databases.md` 后填入真实值。

## CTL 数据库（mynet_ctl）

| 项 | 值 |
|----|---|
| 引擎 | MySQL 8.x |
| Host | `<填写>` |
| Port | 3306 |
| Database | `mynet_ctl` |
| User | `<填写>` |
| Password | `<由 .env 注入；1Password ref: ...>` |

env key：`DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`  
配置文件：[mynet_ctl/config/database.php](../../config/database.php)

## Back 数据库（mynet_back）

| 项 | 值 |
|----|---|
| 引擎 | MySQL 8.x |
| Host | `<填写>` |
| Port | 3306 |
| Database | `mynet_back` |
| User | `<填写>` |
| Password | `<由 .env 注入；1Password ref: ...>` |

env key：同上

## Redis（共享）

| 项 | 值 |
|----|---|
| Host | `<填写>` |
| Port | 6379 |
| Password | `<由 .env 注入>` |
| 数据库分配 | ctl=DB0 / back=DB1 / queue=DB2 |

## 备份策略

- **频率**：每日 03:00
- **路径**：`/data/backup/mynet/db/{date}/`
- **保留**：7 天
- **脚本**：`<填写>`
- **恢复演练**：每季度一次
