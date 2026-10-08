# 出口授权合同锁定（L0）

> 2026-10-08；来源：`proxy-permission-loop-playbook.md` L0。
> 取证基线：`mynet_api@b38c36a`，`ProxyExitApiTest` + `ProxyExitGrantTest` 13 例全绿
> （`./vendor/bin/phpunit --filter 'ProxyExit'`，2026-10-08）。
> fixture 目录：`fixtures/proxy-exit/`（由 `ProxyExitContractDumpTest` 从真实控制器链路 dump）。

## 1. P0 裁定：DELETE = 撤销出口许可

**代码事实（已取证）**：

| 证据 | 位置 |
|---|---|
| 无 active 授权行 ⇒ `resolvePermission()` 返回 `null` | `ProxyAuthDomainService.php:110-118` |
| publisher 仅在 `permission !== null` 时写 `network.proxy` | `BundleEnvelopePublisher.php:476-479` |
| DELETE 把全部 active 行置 revoked | `ProxyAuthDomainService.php:159-166` + `ProxyExitController.php:104-116` |
| DELETE 后 GET 仍显示 `mode=open` | `resolveForExit()` 无行时返回 `MODE_OPEN`（`ProxyAuthDomainService.php:50-53`）；fixture `04-revoked.json` 实测 |

**裁定（与冻结合同一致）**：

- **DELETE = 撤销出口许可**。撤销后 `resolvePermission=null`，bundle **不带** `network.proxy`，节点按未启用处理。
- **GET 的 `mode=open` 不是出口已启用的证据**。它只表达「无白名单」的 ACL 解算形态；许可存在性是另一维。
- `PUT friend_ids: []` **不等于** DELETE：空数组建一行 active 开放授权（`consumer_node_id=NULL`），许可仍在，bundle 带 `network.proxy={enabled:true, acl:{mode:open}}`。
- 上位 §7.6「空白名单 = 组织内放开」说的是 **ACL 形态**（`ProxyACL::MODE_OPEN`），不是「无授权 = 开放」。二者在 GET 上撞成同一个 `mode=open`，必须靠许可面区分。

若后续用户裁定「删除后继续开放」，停在本条形成新决策，不私自切换含义。

## 2. 五态响应表

信封沿用 `BaseApiController`：成功 `{success, code, message, data, timestamp}`；
失败 `{success, code, message, timestamp, errors?, error_code}`。**不新增响应字段**。

| # | 状态 | 触发 | HTTP | `data` / 关键字段 | `permission_exists` | bundle `network.proxy` |
|---|---|---|---|---|---|---|
| 1 | 无授权 | 从未写过 / 建表后 | GET 200 | `mode=open`, 三个列表皆 `[]` | **false** | **不下发** |
| 2 | 开放 | `PUT {friend_ids:[]}` | PUT 200 / GET 200 | `mode=open`, 列表 `[]` | **true** | `{enabled:true, acl:{mode:open}}` |
| 3 | 白名单 | `PUT {friend_ids:[好友]}` | PUT 200 / GET 200 | `mode=allowlist`, `allowed_node_ids` 含好友节点 + 属主自家节点, `allowed_cidrs` 为 overlay /32 | **true** | `{enabled:true, acl:{mode:allowlist, …}}` |
| 4 | 撤销 | `DELETE` | DELETE 200 / GET 200 | GET 同态 1：`mode=open` | **false** | **不下发** |
| 5 | 非法 | 见下 | 403 / 404 / 422 | 见下 | 不变（失败不改旧授权） | 不变 |

### 2.1 非法请求族（fixture `05-illegal.json` 实测）

| 场景 | HTTP | `error_code` | `message` |
|---|---|---|---|
| 非属主 GET/PUT/DELETE | 403 | `DOMAIN_FORBIDDEN` | 无权操作该资源 |
| 节点不存在 | 404 | `DOMAIN_NOT_FOUND` | 节点不存在 |
| 非好友 | 403 | `DOMAIN_FORBIDDEN` | 仅允许授权给已建立好友关系的用户 |
| 好友无可连节点 | 403 | `DOMAIN_FORBIDDEN` | 所选好友没有可用节点，无法授权 |
| 缺 `friend_ids` | 422 | `VALIDATION_ERROR` | Validation failed + `errors.friend_ids` |

失败请求**不入库**、**不改旧授权**（`ProxyExitApiTest` 断言 `count()===0` 或读面不变）。

## 3. 许可存在性 × ACL 形态（正交矩阵）

| | `get_mode=open` | `get_mode=allowlist` |
|---|---|---|
| **`permission_exists=false`** | ① 无授权 · ④ 撤销 | （不可达：无 active 行不会产出 allowlist） |
| **`permission_exists=true`** | ② 开放（`PUT []`） | ③ 白名单 |

**读面陷阱**：① 与 ④ 在 GET 上完全相同，publisher 均不下发许可；② 的 GET 同样是 open，但 publisher 下发开放许可。
禁止用 GET 的 `mode=open` 推断「出口已启用」；判据只能是 `resolvePermission() !== null`
（或等价：存在 `proxy_exit_grants` 的 active 行）。

## 4. 冻结不变量（L1/L2 不得突破）

| 对象 | 不变量 |
|---|---|
| 响应信封 | 沿用 `BaseApiController`；不新增字段、不改成功/错误形状 |
| 错误码 | 非属主/非好友/无可连节点 → `DOMAIN_FORBIDDEN`；缺失 → `DOMAIN_NOT_FOUND`；校验 → `VALIDATION_ERROR`。禁止改成 500 |
| PUT 语义 | 替换整组；去重后校验；任一不合格整体拒绝且旧授权不变 |
| 空数组 | 建 active 开放授权；**不等于**删除 |
| DELETE | 撤销全部 active 行；`resolvePermission=null`；bundle 不带 `network.proxy` |
| 白名单 | 好友可连节点 + 属主自家节点；CIDR 精确 /32（IPv6 /128）；不扩大网段 |
| wire | `network.proxy` 只有 `enabled` + `acl`；不新增 role/exits/DNS/client 策略字段 |

## 5. 文案对齐（L0 执行项，不改行为）

以下描述原先把 DELETE 说成「回默认：本网络区开放」，与 `resolvePermission=null` 矛盾。
按本裁定改为「撤销出口许可」；**行为不动**。

- [x] `ProxyExitController` 类注释 + `destroy()` 注释
- [x] OpenAPI `back.yaml` 的 DELETE summary/description 与 PUT `friend_ids:[]` 描述
- [x] `ProxyExitApiTest::test_撤销授权回默认开放` 的用例名/注释（断言仍只锁 GET，行为不变；L1 再补许可面）

## 6. fixture 索引

| 文件 | 内容 |
|---|---|
| `fixtures/proxy-exit/00-permission-matrix.json` | 许可×ACL 矩阵 + DELETE 后 live check |
| `fixtures/proxy-exit/01-no-grant.json` | 无授权 GET |
| `fixtures/proxy-exit/02-open.json` | `PUT []` + GET |
| `fixtures/proxy-exit/03-allowlist.json` | `PUT [好友]` + GET |
| `fixtures/proxy-exit/04-revoked.json` | DELETE + DELETE 后 GET |
| `fixtures/proxy-exit/05-illegal.json` | 七类非法请求实测 |

生成器：`mynet_api/tests/Unit/Domain/ProxyExitContractDumpTest.php`
（真实控制器链路 dump；时间戳已脱敏为固定值）。默认输出临时目录；更新在库 fixture 时显式设置 `MYNET_PROXY_CONTRACT_OUT`，普通门禁不跨仓写文件。
