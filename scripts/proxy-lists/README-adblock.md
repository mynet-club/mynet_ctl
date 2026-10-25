# DNS hook 拦截名单

- ad-domains.txt：广告父域，客户端匹配自身与子域。
- malware-domains.txt：恶意主机，仅精确匹配；勿把被入侵的共享托管主机扩大为父域。

一行一个纯域名，支持#注释/空行，不写IP、URL、hosts前缀或通配符。初始名单35条广告+418条URLhaus主机；恶意源的日期、原始SHA和条款链接在文件头。更新URLhaus数据时审查并同步来源日期；当前不自动抓取上游。

update_proxy_lists.sh在GeoIP下载之前原子发布两份文件到storage/app/public/proxy-lists，并在成功生成meta时记录ad_domains/malware_domains。若GeoIP失败，文本名单仍已发布，meta可能维持旧记录；客户端对下载正文做格式校验并计算版本，不依赖meta决定是否生效。

公开路径：

- https://ctl.mynet.club/d/plugins/mp/ad-domains.txt
- https://ctl.mynet.club/d/plugins/mp/malware-domains.txt

客户端启动hook时及每小时下载，在两份完整校验后作为一组原子保存；任一失败保留旧版本。空名单具有撤回权威性；客户端不会回填内置规则。本机开关、自定义block/allow独立保留。变更不要编辑proxy-domains.txt以代替拦截表。发布后校验两个URL正文与维护源一致；生产采用定点更新并备份，不整仓同步。

2026-10-20生产端点/更新脚本/初始清单已发布，备份 /data/backup/mynet/ctl/dns-block-lists-20261020-003146（服务器时区）。广告SHA256 1ea53b3335b739dc3217ea38c7f82328577b29864a012ecff73d82102462c025；恶意SHA256 5cb85382b8c30b80162e28a3b47a0545bc7fec45892da420850317ac59917699。
