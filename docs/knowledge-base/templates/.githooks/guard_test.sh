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

newrepo wt_a2
printf -- '-----BEGIN %s PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAE\n-----END %s PRIVATE KEY-----\n' OPENSSH OPENSSH > "$WORK/wt_a2/k.txt"
expect "未跟踪文件里私钥 → A2 拦下" 1 wt_a2 "A2 私钥泄露"

newrepo wt_a3
echo 'APP_KEY=' > "$WORK/wt_a3/.env"
expect "未跟踪的 .env → A3 拦下" 1 wt_a3 "A3 敏感文件不得入库"

newrepo wt_a3ok
echo 'APP_KEY=' > "$WORK/wt_a3ok/.env.example"
expect ".env.example 是允许的模板" 0 wt_a3ok "A3 无敏感文件"

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
expect "非 gnb 仓的 src/ 不触发 B1" 0 gnb_other "B1 mynet_gnb/src/ 未被直改"

# ── 7. C2：端口漂移（8096 是 SSOT）──────────────────────────
newrepo port
echo 'const L = "127.0.0.1:8720"' > "$WORK/port/s.go"
expect "端口漂移 8720 → C2 拦下" 1 port "C2 端口漂移"

newrepo portdoc
mkdir -p "$WORK/portdoc/docs"
echo 'const L = "127.0.0.1:8720"' > "$WORK/portdoc/docs/old.md"
expect "docs/ 下的端口字样不算漂移" 0 portdoc "C2 端口无漂移"

# ── 汇总 ─────────────────────────────────────────────────────
echo
printf 'guard 自测：%s 通过 / %s 失败\n' "$pass" "$fail"
if [ "$fail" -ne 0 ]; then
  printf '失败用例:%s\n' "$FAILED_CASES"
  exit 1
fi
exit 0
