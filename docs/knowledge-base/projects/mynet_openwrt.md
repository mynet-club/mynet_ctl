# mynet_openwrt — 项目速查卡

| 属性 | 值 |
|------|-----|
| **技术栈** | Lua 5.1 / LuCI / ubus / rpcd · ipk |
| **角色** | OpenWrt 平台适配层（壳），与 `mynet_desktop` 对等 |
| **来源** | 自 `mynet_lua` 迁出/更名（业务仍在迁往 mynetd） |

## 职责边界

| 做（目标） | 不做 |
|------------|------|
| LuCI 菜单 / 页面壳 | 完整业务（节点同步、代理、心跳、启停） |
| ipk 打包、init、rpcd/ACL | 独立第二套业务守护 |
| 注册 ubus 会话与权限，转发到 **mynetd** | uhttpd/nginx 反代 |

```
LuCI / mynet_openwrt 壳
        │  /ubus  (mynet.*)
        ▼
     mynetd  ←—— mynet_agent
        │
   mynet / gnb
```

## 现状提醒

当前树仍大量保留 `mynet_lua` 业务实现——**迁移源，不是终态**。
`docs/` 与 `mynet_agent/openwrt/docs/` 曾双份同步（2026-10-01 盘点：16 份 MD5 全同），
正本以本仓 `docs/` 为准。
