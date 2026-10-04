#!/bin/sh
# guard_test.sh — guard.sh 的功能自测（变异测试）
#
# 为什么存在：guard.sh 曾在索引为空时直接 `exit 0`，于是**脏工作区一条红线都不查**，
# 九个仓的 guard 环节全线空转却都报「✓ 通过」。这类缺陷靠人眼看不出来 ——
# 只有把「本该被拦下的东西」逐条造成用例，才能证明守护真的在守。
#
# 用法：sh guard_test.sh [path/to/guard.sh]
#       默认测同目录的 guard.sh。跑在临时仓里，不碰任何真实仓库。
#
# 判据：每条用例断言**退出码**与**输出标记**两者。只断言退出码会漏掉
#       「拦对了但理由说错」的情况（例如把 A1 报成 A3）。
# ─────────────────────────────────────────────────────────────
set -u

GUARD=${1:-$(dirname "$0")/guard.sh}
[ -f "$GUARD" ] || { echo "找不到 guard.sh: $GUARD"; exit 2; }
GUARD=$(cd "$(dirname "$GUARD")" && pwd)/$(basename "$GUARD")

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT INT TERM

pass=0; fail=0
FAILED_CASES=""

# 建一个临时仓（含一个空提交，好让 `git diff --cached` 有 HEAD 可比）。
# hook 自身必须提交进去 —— 否则它在每个用例里都是「未跟踪文件」，
# 既让「干净仓」不干净，又把它自带的 GUARD-OK 自检正则混进豁免统计。
newrepo() {
  _d=$1
  mkdir -p "$WORK/$_d/.githooks"
  cp "$GUARD" "$WORK/$_d/.githooks/guard.sh"
  chmod +x "$WORK/$_d/.githooks/guard.sh"
  ( cd "$WORK/$_d" \
    && git init -q . \
    && git add .githooks/guard.sh \
    && git -c user.name=t -c user.email=t@t commit -q -m 'init + hook' )
}

# run <repo> → 把输出放进 OUT，退出码放进 RC
run() {
  OUT=$(cd "$WORK/$1" && sh .githooks/guard.sh 2>&1)
  RC=$?
}

# expect <用例名> <期望退出码> <repo> <输出必须含>
expect() {
  _name=$1; _want=$2; _repo=$3; _needle=$4
  run "$_repo"
  if [ "$RC" -ne "$_want" ]; then
    printf '  ✗ %s：退出码 %s，期望 %s\n' "$_name" "$RC" "$_want"
    printf '%s\n' "$OUT" | sed 's/^/      /'
    fail=$((fail+1)); FAILED_CASES="$FAILED_CASES $_name"; return
  fi
  case "$OUT" in
    *"$_needle"*) ;;
    *)
      printf '  ✗ %s：输出未含「%s」\n' "$_name" "$_needle"
      printf '%s\n' "$OUT" | sed 's/^/      /'
      fail=$((fail+1)); FAILED_CASES="$FAILED_CASES $_name"; return ;;
  esac
  printf '  ✓ %s\n' "$_name"
  pass=$((pass+1))
}

echo "▸ guard.sh 功能自测（被测：${GUARD}）"

# ── 1. 无可检文件时必须**说出来**，不能假装通过 ──────────────
newrepo clean
expect "干净仓：明说未检查任何红线" 0 clean "本次未检查任何红线"

# ── 1b. bare 仓：取不到工作树时必须**说出来**，不能 exit 0 装作通过 ──
# 2026-10-04 的事故里，含生产私钥的 .tgz 就是从 bare 仓目录直接 push 的，
# guard / pre-push 一次都没跑过，却都报了绿。
mkdir -p "$WORK/bare/.githooks"
cp "$GUARD" "$WORK/bare/.githooks/guard.sh"
( cd "$WORK/bare" && git init -q --bare . )
expect "bare 仓：明说跳过而不是假装通过" 0 bare "guard 跳过"

# ── 2. 工作区模式：未跟踪文件也要查（旧版在这里 exit 0）──────
newrepo wt_ok
echo 'package main' > "$WORK/wt_ok/main.go"
expect "未跟踪的普通文件：进入工作区范围并通过" 0 wt_ok "范围：工作区"

# ⚠️ 夹具一律用 printf 拼装，**不让违规字面量出现在本文件里**。
# 否则本仓自己的 guard 会把这些「故意造的违规」当成真违规拦下（实测已发生：
# mynet_ctl 的 make guard 因本文件的 A1/A2 夹具而红）。用 %s 占位后，
# 源文件里的形状不再匹配 A1/A2 的正则，而**生成的夹具文件里仍然是真违规** ——
# 用例的强度不变。
newrepo wt_a1
printf '<?php\n$db_password = "%s";\n' 'Sup3rSecretValue' > "$WORK/wt_a1/conf.php"
expect "未跟踪文件里硬编码凭据 → A1 拦下" 1 wt_a1 "A1 硬编码凭据"

# A1 刻意要求关键字前是**非字母**（防 DirToken / refresh_token 误报）。
# 这条用例钉住那个收窄：驼峰里的 password 不该被当成硬编码凭据。
newrepo wt_a1_narrow
printf '<?php\n$DirToken = $request->header(%s);\n' "'X-Dir-Token'" > "$WORK/wt_a1_narrow/conf.php"
expect "驼峰标识符不误报 A1（收窄仍有效）" 0 wt_a1_narrow "A1 无硬编码凭据"

# 裸 PASS= 也要抓得到：db-setup.sh 曾这样明文存口令，而 password|passwd|pwd 都匹配不到
newrepo wt_a1_pass
printf 'LOCAL_DB_PASS = "%s"\n' 'Mynet@Dev2026' > "$WORK/wt_a1_pass/deploy.sh"
expect "裸 PASS= 的明文口令 → A1 拦下" 1 wt_a1_pass "A1 硬编码凭据"

# 反向：良性写法不得误报（误报多了守护会被 --no-verify 绕过，反而失效）
newrepo wt_a1_benign
printf 'pass=0\nPASS_COUNT=3\nBYPASS="%s"\npass=$((pass+1))\nCOMPASS="%s"\n' enabled north > "$WORK/wt_a1_benign/x.sh"
expect "良性 pass 写法不误报" 0 wt_a1_benign "A1 无硬编码凭据"

newrepo wt_a2
printf -- '-----BEGIN %s PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAE\n-----END %s PRIVATE KEY-----\n' OPENSSH OPENSSH > "$WORK/wt_a2/k.txt"
expect "未跟踪文件里私钥 → A2 拦下" 1 wt_a2 "A2 私钥泄露"

newrepo wt_a3
echo 'APP_KEY=' > "$WORK/wt_a3/.env"
expect "未跟踪的 .env → A3 拦下" 1 wt_a3 "A3 敏感文件不得入库"

newrepo wt_a3ok
echo 'APP_KEY=' > "$WORK/wt_a3ok/.env.example"
expect ".env.example 是允许的模板" 0 wt_a3ok "A3 无敏感文件"

# A3 的**文件级**豁免（判据比 A1/A2 的行内豁免更严，两条同时成立才降级）：
#   ① 文件已在 HEAD 里（历史遗留，不是本次新入库）　② 文件内自带一行 GUARD-OK 理由
# 立这条判据的场景：mynet_gui 的 .env/.env.dev/.env.embed/.env.prod 是团队共享的
# 前端构建期配置（只有 API 域名与客户端公开值，无凭据），而 Quasar 按**固定文件名**
# 查找（@quasar/app-vite/lib/utils/env.js:31，命名法里没有 development/production），
# 改名即失效、必须入库。没有这条路，日常改一次 .env.dev 就撞一次 A3 ——
# 结局就是教人用 --no-verify，守护反而失效。
newrepo a3_hist_unmark
echo 'API_URL=https://api.example' > "$WORK/a3_hist_unmark/.env.dev"
( cd "$WORK/a3_hist_unmark" && git add .env.dev \
  && git -c user.name=t -c user.email=t@t commit -q -m base )
printf '\nNODE_ENV=development\n' >> "$WORK/a3_hist_unmark/.env.dev"
expect "历史在库但未标注的 .env.dev → A3 仍硬拦" 1 a3_hist_unmark "A3 敏感文件不得入库"

newrepo a3_hist_mark
echo 'API_URL=https://api.example' > "$WORK/a3_hist_mark/.env.dev"
( cd "$WORK/a3_hist_mark" && git add .env.dev \
  && git -c user.name=t -c user.email=t@t commit -q -m base )
printf '\n# %s 前端构建期配置，无凭据，需入库\nNODE_ENV=development\n' 'GUARD-OK' >> "$WORK/a3_hist_mark/.env.dev"
expect "历史在库 + 文件内标注 → A3 降级豁免并通过" 0 a3_hist_mark "A3 已豁免"
[ -s "$WORK/a3_hist_mark/.git/guard-exempt.log" ] \
  && { printf '  ✓ A3 文件级豁免记入审计日志\n'; pass=$((pass+1)); } \
  || { printf '  ✗ A3 豁免未记审计日志\n'; fail=$((fail+1)); FAILED_CASES="$FAILED_CASES A3豁免审计"; }

# 硬拦的那半边：**新**入库的敏感名文件即使写了标注也照样拦。gnb 私钥事故（§AGENTS 2.6）
# 的形态就是「新文件」，所以「已在 HEAD」是豁免的前置条件，不是可选项。
newrepo a3_new_mark
printf 'API_URL=https://api.example\n# %s 写了标注也没用\n' 'GUARD-OK' > "$WORK/a3_new_mark/.env.prod"
expect "新增敏感名文件即使带标注 → A3 仍硬拦" 1 a3_new_mark "A3 敏感文件不得入库"

# ── 2b. A4：归档**里面**的敏感文件（A2/A3 都看不见）──────────
# 起因（2026-10-04 真实事故）：mynet_gnb 把生产节点与公司网关的 *.private、
# credential.json 打进 .tgz 提交并推到 GitHub。A2 只认 PEM 文本而 GNB 私钥是
# 裸 32 字节二进制；A3 只认入库文件名而入库的是 _etc_opengnb.tgz —— 两层全瞎。
# 下面每条用例都复刻那次泄露的一个侧面。

# 裸二进制私钥（无 PEM 头）打进 tgz —— A2 抓不到，A4 必须抓到
newrepo wt_a4_tgz
mkdir -p "$WORK/wt_a4_tgz/bk/security"
head -c 32 /dev/urandom > "$WORK/wt_a4_tgz/bk/security/1002.private"
( cd "$WORK/wt_a4_tgz" && tar czf leak.tgz bk && rm -rf bk )
expect "tgz 里的裸 .private → A4 拦下" 1 wt_a4_tgz "A4 归档内含敏感文件"

# zip 里的 credential.json（事故里 etc/mynet/conf/credential.json 就是这么进去的）
newrepo wt_a4_zip
mkdir -p "$WORK/wt_a4_zip/conf"
echo '{}' > "$WORK/wt_a4_zip/conf/credential.json"
( cd "$WORK/wt_a4_zip" && zip -qr leak.zip conf && rm -rf conf )
expect "zip 里的 credential.json → A4 拦下" 1 wt_a4_zip "A4 归档内含敏感文件"

# 反向：良性归档不得误报（gnb 主线就跟踪着两个 author 源码 zip）
newrepo wt_a4_ok
mkdir -p "$WORK/wt_a4_ok/src"
echo 'int main(void){return 0;}' > "$WORK/wt_a4_ok/src/x.c"
echo '# readme' > "$WORK/wt_a4_ok/src/README.md"
( cd "$WORK/wt_a4_ok" && tar czf src.tgz src && rm -rf src )
expect "良性 tgz 不误报 A4" 0 wt_a4_ok "A4 已扫描 1 个归档"

# 归档内的 .env.example 仍按 A3 的豁免规则放行（判据必须与 A3 一致，否则两处会打架）
newrepo wt_a4_envexample
mkdir -p "$WORK/wt_a4_envexample/cfg"
echo 'APP_KEY=' > "$WORK/wt_a4_envexample/cfg/.env.example"
( cd "$WORK/wt_a4_envexample" && tar czf cfg.tgz cfg && rm -rf cfg )
expect "归档内 .env.example 豁免（与 A3 一致）" 0 wt_a4_envexample "A4 已扫描 1 个归档"

# 暂存区模式（pre-commit 语义）：内容在索引里而不在盘上，A4 必须照样能扫
newrepo st_a4
mkdir -p "$WORK/st_a4/bk"
head -c 32 /dev/urandom > "$WORK/st_a4/bk/node.private"
( cd "$WORK/st_a4" && tar czf leak.tgz bk && rm -rf bk && git add leak.tgz )
expect "暂存区里的 tgz 含 .private → A4 拦下" 1 st_a4 "范围：暂存区"

# 本批没有归档时必须**明说**未触发扫描，不能静默（同开头「没东西可查型门禁」教训）
expect "无归档时 A4 明说未触发" 0 wt_ok "A4 本批无归档文件"

# 损坏的归档：列不出清单也要响，不许当成通过
newrepo wt_a4_corrupt
printf 'not an archive at all' > "$WORK/wt_a4_corrupt/broken.tgz"
expect "损坏归档 → A4 报未能扫描而非通过" 1 wt_a4_corrupt "A4 列不出归档内容"

# ── 3. 工作区模式：已跟踪但未暂存的修改也要查 ────────────────
newrepo wt_mod
echo 'ok = 1' > "$WORK/wt_mod/a.conf"
( cd "$WORK/wt_mod" && git add a.conf && git -c user.name=t -c user.email=t@t commit -q -m base )
printf 'token = "%s"\n' 'abcdefghijkl' >> "$WORK/wt_mod/a.conf"
expect "未暂存的修改里硬编码凭据 → A1 拦下" 1 wt_mod "A1 硬编码凭据"

# ── 4. 暂存区模式仍然有效（pre-commit 语义不能被削弱）────────
newrepo st_bad
printf 'secret = "%s"\n' 'abcdefghijkl' > "$WORK/st_bad/b.conf"
( cd "$WORK/st_bad" && git add b.conf )
expect "暂存区里的违规 → 拦下且标明范围" 1 st_bad "范围：暂存区"

newrepo st_ok
echo 'hello = 1' > "$WORK/st_ok/c.conf"
( cd "$WORK/st_ok" && git add c.conf )
expect "暂存区干净 → 通过" 0 st_ok "guard 通过"

# ── 5. GUARD-OK 豁免：写在违规那一行才生效，且要留审计痕迹 ───
newrepo exempt
printf 'password = "%s" # GUARD-OK 测试夹具，非真凭据\n' 'abcdefghijkl' > "$WORK/exempt/d.conf"
expect "GUARD-OK 行内豁免生效" 0 exempt "已豁免"
[ -s "$WORK/exempt/.git/guard-exempt.log" ] \
  && { printf '  ✓ 豁免记入 .git/guard-exempt.log\n'; pass=$((pass+1)); } \
  || { printf '  ✗ 豁免未记审计日志\n'; fail=$((fail+1)); FAILED_CASES="$FAILED_CASES 豁免审计"; }

# ── 6. B1：mynet_gnb 的 src/ 只读（仓名匹配 + 路径匹配）──────
newrepo mynet_gnb
mkdir -p "$WORK/mynet_gnb/src"
echo 'int main(void){return 0;}' > "$WORK/mynet_gnb/src/x.c"
expect "gnb 仓未跟踪的 src/ 文件 → B1 拦下" 1 mynet_gnb "B1 mynet_gnb/src/"

newrepo gnb_other
mkdir -p "$WORK/gnb_other/src"
echo 'int main(void){return 0;}' > "$WORK/gnb_other/src/x.c"
# 非 gnb 仓里这条红线**什么都没查**，所以必须明说跳过 —— 打「✓ 未被直改」就是
# 把「没跑」伪装成「通过」（同 guard.sh 开头那条教训）。
expect "非 gnb 仓的 src/ 不触发 B1，且明说跳过" 0 gnb_other "B1 跳过"

# gnb 的真身是 bare 仓 + linked worktree，编辑入口（.wt/gnb-actions）路径里
# **不含** mynet_gnb。B1 曾按工作树路径判仓，于是在 gnb 上永远静默跳过 ——
# 「src/ 只读」这条红线一次都没生效过。判仓必须走 --absolute-git-dir。
mkdir -p "$WORK/gnbwt"
newrepo gnbwt/mynet_gnb
mkdir -p "$WORK/gnbwt/mynet_gnb/packages"
echo x > "$WORK/gnbwt/mynet_gnb/packages/README"
( cd "$WORK/gnbwt/mynet_gnb" && git add packages \
  && git -c user.name=t -c user.email=t@t commit -q -m base \
  && git worktree add -q ../wt-actions -b wt )
mkdir -p "$WORK/gnbwt/wt-actions/.githooks"
cp "$GUARD" "$WORK/gnbwt/wt-actions/.githooks/guard.sh"
mkdir -p "$WORK/gnbwt/wt-actions/src"
echo 'int main(void){return 0;}' > "$WORK/gnbwt/wt-actions/src/x.c"
expect "gnb 的 linked worktree 里改 src/ → B1 照样拦下" 1 gnbwt/wt-actions "B1 mynet_gnb/src/"

# ── 7. C2：端口漂移（8096 是 SSOT）──────────────────────────
newrepo port
echo 'const L = "127.0.0.1:8720"' > "$WORK/port/s.go"
expect "端口漂移 8720 → C2 拦下" 1 port "C2 端口漂移"

newrepo portdoc
mkdir -p "$WORK/portdoc/docs"
echo 'const L = "127.0.0.1:8720"' > "$WORK/portdoc/docs/old.md"
expect "docs/ 下的端口字样不算漂移" 0 portdoc "C2 端口无漂移"

# 黑名单只认「已知写错过的端口」，追不上新错值。8711 是 mynet_gateway 的历史端口，
# mynet_desktop/nsis 曾把 mynetd 起在它上面，而面板快捷方式指 8096 → 装完点开空页面
# （181 节点真机复现）。下面三条钉住改判据后的不变量：提到 mynetd 的行必须是 8096。
newrepo port_mynetd
printf 'FileWrite $0 %s\n' "'\"\$INSTDIR\\bin\\mynetd.exe\" -listen 127.0.0.1:8711'" > "$WORK/port_mynetd/i.nsi"
expect "mynetd 起在 8711 → C2 拦下（黑名单外的新错值）" 1 port_mynetd "mynetd 本机 API 应为 8096"

newrepo port_mynetd_ok
printf 'ExecStart=/opt/mynet/bin/mynetd -listen 127.0.0.1:8096\n' > "$WORK/port_mynetd_ok/mynetd.service"
expect "mynetd 起在 8096 → 通过" 0 port_mynetd_ok "C2 端口无漂移"

# e2e / debug 脚手架故意用不冲突的临时端口（18733/18811/18096），拦了就是误报，
# 而误报多了守护会被 --no-verify 绕过 —— 反而失效。
newrepo port_e2e
mkdir -p "$WORK/port_e2e/scripts/e2e"
printf '"$BIN/mynetd" -listen "127.0.0.1:18733" > log 2>&1 &\n' > "$WORK/port_e2e/scripts/e2e/pull.sh"
expect "e2e 脚本的临时端口不算漂移" 0 port_e2e "C2 端口无漂移"

newrepo port_debug
mkdir -p "$WORK/port_debug/debug"
printf '/usr/sbin/mynetd -once -listen 127.0.0.1:18096\n' > "$WORK/port_debug/debug/install.sh"
expect "debug 脚手架的临时端口不算漂移" 0 port_debug "C2 端口无漂移"

# ── 汇总 ─────────────────────────────────────────────────────
echo
printf 'guard 自测：%s 通过 / %s 失败\n' "$pass" "$fail"
if [ "$fail" -ne 0 ]; then
  printf '失败用例:%s\n' "$FAILED_CASES"
  exit 1
fi
exit 0
