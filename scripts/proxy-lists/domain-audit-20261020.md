# AI 与视频服务加速域名补充（2026-10-20）

用户要求补全 OpenAI、Anthropic/Claude、Google/Gemini、YouTube 默认云端清单。
依据 MetaCubeX/meta-rules-dat 的 meta 分支 geo/geosite/{openai,anthropic,google-gemini,youtube}.list 当次快照核对；第三方规则不是官方完整网络需求，也不是流量实测。

现有规则按父域覆盖子域；本次保留原886条，新增187条，共1073条。未照搬归属不明的 crixet.com，也未新增第三方统计/问卷条目。现有清单已含 azureedge.net、windows.net、imgix.net、b-cdn.net 等宽泛云资源父域，本次未收缩旧规则，也未为此新增同类共享根域。Google 常见 API、静态资源及 YouTube 视频 CDN 多已覆盖；YouTube 新增大部分为地区入口，不表示播放链路缺少164个必要资源。

云端源模板和公开产物同步发布，原子替换并保留备份；meta只更新proxy_domains和domains_updated_at，其余项保留。
公开地址：https://ctl.mynet.club/d/plugins/mp/proxy-domains.txt
SHA256：cd265449d75728351b098f1b17f79175138109e10fe879ee1ad59fbd6b90a320
备份：/data/backup/mynet/ctl/domains-ai-20261019-225821（服务器时区）

公开下载与本地源文件逐字节一致；发布脚本行为测试通过。客户端按既有下载周期刷新，本次不启动/重启本机DNS hook，不解除此前外部DNS接管中断。

## openai（新增9）

来源：https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/openai.list

源 blob SHA：085bafa315eda028e8c5f555410fb68046b7cfef

```text
openaicom-api-bdcpf8c6d2e9atf6.z01.azurefd.net
chatgpt.site
oaistatsig.com
openai.org
chat.com
host.livekit.cloud
chatgpt.livekit.cloud
turn.livekit.cloud
openai.com.cdn.cloudflare.net
```

## anthropic（新增5）

来源：https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/anthropic.list

源 blob SHA：155242c072426bd97c313adbc99f06501f20e321

```text
clau.de
claude.com
claude.dev
claudemcpclient.com
claudemcpcontent.com
```

## google-gemini（新增9）

来源：https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/google-gemini.list

源 blob SHA：472bb462d876439361c81cb6794b021627b59906

```text
notebook.google
antigravity-unleash.goog
antigravity.google
opal.google
flow.google
generativeai.google
ai.studio
jules.google
gemini.google
```

## YouTube 最终收敛（用户修正）

撤销此前164条扩充中的161条，仅新增 youtube.sg、youtube.com.sg、yt.be。保留既有 youtube.com、youtu.be、ytimg.com、youtube-nocookie.com、youtubekids.com 及 Google 视频/头像 CDN 规则；删除其他地区入口与 .youtube 宽泛后缀。

最终为912条（原886 + OpenAI 9 + Claude 5 + Google AI 9 + YouTube 3）。前文1073条及对应SHA为首次发布历史，已被本次替代。
最终公开文件SHA256：86885bb90565c4f09de787edb7cd5f0cd48d1494dd68006030a822744c610ba2。
公开下载与源文件逐字节一致。备份：/data/backup/mynet/ctl/domains-youtube-trim-20261019-230246（服务器时区）。


## Pornhub 资源补齐（后续用户要求）

保留 pornhub.com，新增 phncdn.com、phprcdn.com 两个资源CDN父域，覆盖其子域。依据 MetaCubeX/meta-rules-dat meta/geo/geosite/pornhub.list，源blob b78e10e848a4680712f66bb4a0d4a9204a0f25e2。未扩大到其列表中的其他成人站点、商城和地区入口；未做真实播放验收。
最终914条，SHA256 f1a4ad429fd066643f94f49103fb447bbc7e216ce30c5d2cfefe5270342dfd59。云端模板/产物/meta已同步，公开下载逐字节相同；备份 /data/backup/mynet/ctl/domains-phcdn-20261019-234836（服务器时区）。


## t66y 与同类主要资源（后续用户要求）

新增10条：t66y.com；xvideos.com、xvideos-cdn.com；xnxx.com、xnxx-cdn.com；xhamster.com、xhcdn.com、xhpingcdn.com；spankbang.com、sb-cd.com。均含子域；ty66.com不能覆盖t66y.com。未扩展地区镜像、广告或不可固定的帖子外链图床；未实测浏览/播放，不称全量资源覆盖。
依据 MetaCubeX/meta-rules-dat meta 分支 geosite 分类，对照快照如下：

- https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/category-porn.list ，blob b89864cbc4f4b9ef1181b1fb919d4fca7cbf14a6
- https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/xvideos.list ，blob e7d50e54b5673b7e26bfe6d6a4df1266d1d57272
- https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/xnxx.list ，blob ea3148f4066723274d016fe1e4f2c0730513cfe1
- https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/xhamster.list ，blob 2a1158bb637c4977bfaedcb42ea0750f8f9ff9eb
- https://github.com/MetaCubeX/meta-rules-dat/blob/meta/geo/geosite/spankbang.list ，blob a605ed9b60b9a8116adaa06719fd557286b6dcad

最终924条，SHA256 6cc07b50d13eb994ecfef769332e129227337995274e540771c01845f822e47d。云端源模板/公开产物/meta已同步，公开下载逐字节一致。备份 /data/backup/mynet/ctl/domains-adult-resources-20261019-235035（服务器时区）。未启停本机hook或改动本机DNS。


## 自定义加速域相关补齐（2026-10-24）

本机 `proxy_intent` 自定义域为 pornhub.com / t66y.com / gmail.com / zhanghao.today。
pornhub 与 t66y 资源此前已上云；本次补齐未覆盖条目：

- gmail.com（不是 google.com 子域，父域规则盖不住）
- googlemail.com
- googlegroups.com
- gsuite.com
- zhanghao.today（本机自定义域上云，多端一致）

924→929。未扩大到其他 Google 产品子域（已有 google.com 父域覆盖 mail.google.com 等）。
云端源模板 / 公开产物 / meta.proxy_domains+domains_updated_at 已同步；
公开下载与源文件逐字节一致，SHA256 c9182f879cd29a1a86ce89e6b2be6fcb6ed0004a0fc17850d5fc52a28f6696d6。
备份：/data/backup/mynet/ctl/domains-custom-related-20261024-043358（服务器时间）。
清单发布 shell 行为测试通过。未改本机 hook 意愿、未启停隧道。
