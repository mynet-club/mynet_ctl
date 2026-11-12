#!/bin/sh
# pre_push_release_test.sh — 分层 pre-push 的功能自测（临时仓）
#
# 判据：退出码 + 输出标记 + gate-status 三者一起断言。只看退出码会漏掉
#       「拒绝了但理由说错」和「放行了却没记录状态」。
# 用法：sh pre_push_release_test.sh [path/to/pre-push]
set -u

HOOK=${1:-$(dirname "$0")/pre-push}
[ -f "$HOOK" ] || { echo "找不到 pre-push: $HOOK"; exit 2; }
HOOK=$(cd "$(dirname "$HOOK")" && pwd)/$(basename "$HOOK")

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT INT TERM

pass=0; fail=0; FAILED=""
ok()  { printf '  ✓ %s\n' "$1"; pass=$((pass+1)); }
bad() { printf '  ✗ %s\n' "$1"; fail=$((fail+1)); FAILED="${FAILED}
  - $1"; }

REPO="$WORK/repo"
git init -q "$REPO" || exit 1
cd "$REPO" || exit 1
git config user.email test@example.invalid
git config user.name test
git config commit.gpgsign false
mkdir -p .githooks
cp "$HOOK" .githooks/pre-push
chmod +x .githooks/pre-push
git config core.hooksPath .githooks
printf 'x\n' > file.txt
git add file.txt
git commit -qm init
SHA=$(git rev-parse HEAD)
ZERO=0000000000000000000000000000000000000000

run_hook() { # <ref 行...>
  printf '%s\n' "$@" | sh .githooks/pre-push >"$WORK/out" 2>&1
  RC=$?
  OUT=$(cat "$WORK/out")
}
status_field() { sed -n "s/^$1=//p" .git/gate-status 2>/dev/null | head -1; }

# 1. 分支推送放行，gate-status=skipped
run_hook "refs/heads/main $SHA refs/heads/main $ZERO"
if [ "$RC" -eq 0 ] && [ "$(status_field result)" = skipped ]; then
  ok "分支推送放行并记 skipped"
else
  bad "分支推送应放行并记 skipped（rc=$RC, status=$(status_field result))"
fi

# 2. 无批准的 tag 拒绝
run_hook "refs/tags/v1 $SHA refs/tags/v1 $ZERO"
if [ "$RC" -ne 0 ] && [ "$(status_field result)" = rejected ]; then
  ok "无批准 tag 被拒绝"
else
  bad "无批准 tag 应拒绝（rc=$RC, status=$(status_field result))"
fi

# 3. 批准匹配时放行
mkdir -p .git/release-approvals
printf 'sha=%s\ntag=v1\n' "$SHA" > .git/release-approvals/v1
run_hook "refs/tags/v1 $SHA refs/tags/v1 $ZERO"
if [ "$RC" -eq 0 ] && [ "$(status_field result)" = approved ]; then
  ok "批准匹配的 tag 放行"
else
  bad "批准匹配的 tag 应放行（rc=$RC, status=$(status_field result))"
fi

# 4. sha 不匹配拒绝
printf 'sha=%040d\ntag=v1\n' 0 > .git/release-approvals/v1
run_hook "refs/tags/v1 $SHA refs/tags/v1 $ZERO"
if [ "$RC" -ne 0 ]; then
  ok "批准 sha 不匹配时拒绝"
else
  bad "批准 sha 不匹配时应拒绝（rc=$RC）"
fi

# 5. tag 删除拒绝
printf 'sha=%s\ntag=v1\n' "$SHA" > .git/release-approvals/v1
run_hook "refs/tags/v1 $ZERO refs/tags/v1 $SHA"
if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -q '拒绝 tag 删除'; then
  ok "tag 删除被拒绝"
else
  bad "tag 删除应拒绝（rc=$RC）"
fi

# 6. 分支 + 无批准 tag 混合推送：整批拒绝
rm -rf .git/release-approvals
run_hook "refs/heads/main $SHA refs/heads/main $ZERO" \
         "refs/tags/v2 $SHA refs/tags/v2 $ZERO"
if [ "$RC" -ne 0 ]; then
  ok "混合推送中的无批准 tag 拒绝整批"
else
  bad "混合推送含无批准 tag 时应拒绝（rc=$RC）"
fi

echo
printf 'pre-push 自测：%s 通过 / %s 失败\n' "$pass" "$fail"
[ "$fail" -eq 0 ] || { printf '失败项:%s\n' "$FAILED"; exit 1; }
exit 0
