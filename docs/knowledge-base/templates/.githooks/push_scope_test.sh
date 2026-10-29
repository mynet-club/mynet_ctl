#!/bin/sh
# push_scope_test.sh — pre-push 范围治理的功能自测（临时仓 + 桩 guard/check/Makefile）
#
# 为什么存在：pre-push 的复杂度全在「哪些提交要验」，而这恰好是最难靠人眼复核的：
# 多 ref、新分支、删除 ref、远程基线缺失都只在特定推送形态下出现。桩掉 guard 与
# 选测引擎后，钩子的判定（选测范围 / 扩大全量 / 阻断 / 跳过）可以逐条断言。
#
# 用法：sh push_scope_test.sh [path/to/pre-push]
#       默认测同目录的 pre-push。跑在临时仓里，不碰任何真实仓库。
#
# 判据：退出码 + 输出标记 + 桩实际被调用的命令三者一起断言。只看退出码会漏掉
#       「阻断对了但理由说错」与「阻断对了却顺手跑了别的检查」。
# ─────────────────────────────────────────────────────────────
set -u

HOOK=${1:-$(dirname "$0")/pre-push}
[ -f "$HOOK" ] || { echo "找不到 pre-push: $HOOK"; exit 2; }
HOOK=$(cd "$(dirname "$HOOK")" && pwd)/$(basename "$HOOK")

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT INT TERM

pass=0; fail=0; FAILED_CASES=""
OUT=""; RC=0; CASE=""; ENVS=""

newrepo() {
  CASE=$1
  mkdir -p "$WORK/$CASE/.githooks" "$WORK/$CASE/scripts"
  cp "$HOOK" "$WORK/$CASE/.githooks/pre-push"
  cat > "$WORK/$CASE/.githooks/guard.sh" <<'SH'
#!/bin/sh
echo "GUARD" >> calls.log
exit ${GUARD_RC:-0}
SH
  cat > "$WORK/$CASE/scripts/check.py" <<'PY'
import os, sys
with open('calls.log', 'a') as handle:
    handle.write('CHECK ' + ' '.join(sys.argv[1:]) + '\n')
if '--version' in sys.argv:
    sys.exit(int(os.environ.get('VERSION_RC', '0')))
sys.exit(int(os.environ.get('CHECK_RC', '0')))
PY
  cat > "$WORK/$CASE/Makefile" <<'MK'
check:
	@echo "MAKECHECK" >> calls.log
MK
  ( cd "$WORK/$CASE" || exit 1
    git init -q .
    printf 'one\n' > first.txt
    git add -A
    git -c user.name=t -c user.email=t@t commit -q -m c1 )
}

commit() { ( cd "$WORK/$CASE" && printf '%s\n' "$2" > "$1" && git add -A && git -c user.name=t -c user.email=t@t commit -q -m "$1" ); }
sha() { ( cd "$WORK/$CASE" && git rev-parse "$1" ); }

# 一次推送：$1 = stdin 的 ref 行（多行用 | 分隔），ENVS 提供桩的开关。
run_hook() {
  : > "$WORK/$CASE/calls.log"
  OUT=$( cd "$WORK/$CASE" && printf '%s\n' "$1" | tr '|' '\n' | env $ENVS sh .githooks/pre-push origin 2>&1 )
  RC=$?
}

has() { printf '%s' "$OUT" | grep -qF -- "$1"; }
called() { grep -qF -- "$1" "$WORK/$CASE/calls.log"; }
not_called() { ! grep -qF -- "$1" "$WORK/$CASE/calls.log"; }

expect() {
  _desc=$1; _want=$2
  _bad=""
  [ "$RC" -eq "$_want" ] || _bad="$_bad exit=$RC(期望 $_want)"
  shift 2
  for _p in "$@"; do has "$_p" || _bad="$_bad 缺输出「$_p」"; done
  if [ -z "$_bad" ]; then
    pass=$((pass + 1))
  else
    fail=$((fail + 1)); FAILED_CASES="$FAILED_CASES\n  - $_desc:$_bad"
    printf '✗ %s\n' "$_desc"
    printf '  输出：%s\n' "$OUT"
    printf '  调用：%s\n' "$(tr '\n' ' ' < "$WORK/$CASE/calls.log")"
    return 0
  fi
  printf '✓ %s\n' "$_desc"
}

# ── 1 普通更新：只跑推送范围 ─────────────────────────────────
newrepo normal
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
expect "普通更新 → 只按 remote..HEAD 选测" 0 "选测 $BASE..$HEAD"
called "CHECK --run --range $BASE..$HEAD" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 普通更新：选测命令不符"; }
not_called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 普通更新：不该跑全量"; }
called "GUARD" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 普通更新：guard 未跑"; }

# ── 2 多 ref：每个 ref 各自的范围都验 ────────────────────────
newrepo multiref
commit second.txt two
BASE1=$(sha HEAD~1); BASE2=$(sha HEAD); HEAD=$(sha HEAD)
run_hook "refs/heads/main $HEAD refs/heads/main $BASE1|refs/tags/v1 $HEAD refs/tags/v1 $BASE2"
expect "多 ref 推送 → 两个范围都验" 0 "选测 $BASE1..$HEAD" "选测 $BASE2..$HEAD"
called "CHECK --run --range $BASE2..$HEAD" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 多 ref：第二个范围漏了"; }

# ── 3 新分支：用远程默认分支的共同基线 ───────────────────────
newrepo newbranch
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
( cd "$WORK/$CASE" && git update-ref refs/remotes/origin/main "$BASE" )
run_hook "refs/heads/feature $HEAD refs/heads/feature 0000000000000000000000000000000000000000"
expect "新分支 → 以 origin/main 共同基线选测" 0 "选测 $BASE..$HEAD"
called "CHECK --run --range $BASE..$HEAD" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 新分支：未用共同基线"; }

# ── 4 新分支且无任何远程基线 → 全量，并说明原因 ──────────────
newrepo nobase
commit second.txt two
HEAD=$(sha HEAD)
run_hook "refs/heads/feature $HEAD refs/heads/feature 0000000000000000000000000000000000000000"
expect "新分支无远程基线 → 全量并打印原因" 0 "默认分支基线" "执行全量 make check"
called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 无基线：未扩到全量"; }

# ── 5 删除 ref：不新增内容，只跑 guard ───────────────────────
newrepo deleted
run_hook "refs/heads/old 0000000000000000000000000000000000000000 refs/heads/old 1111111111111111111111111111111111111111"
expect "删除 ref → 只跑 guard 并说明" 0 "删除 refs/heads/old"
called "GUARD" && not_called "CHECK --run" && not_called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 删除 ref：不该跑内容检查"; }

# ── 6 远程基线本地不存在（远端被改写/未 fetch）→ 全量 ────────
newrepo missingbase
commit second.txt two
HEAD=$(sha HEAD)
run_hook "refs/heads/main $HEAD refs/heads/main 2222222222222222222222222222222222222222"
expect "远程基线本地不存在 → 全量并打印原因" 0 "本地不存在" "执行全量 make check"
called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 缺基线：未扩到全量"; }

# ── 7 推送别的提交（分叉）→ 阻断 ─────────────────────────────
newrepo othertree
commit second.txt two
DEFAULT=$( cd "$WORK/$CASE" && git symbolic-ref --short HEAD )
( cd "$WORK/$CASE" && git checkout -q -b side "$(git rev-parse HEAD~1)" && printf 'side\n' > side.txt && git add -A && git -c user.name=t -c user.email=t@t commit -q -m side && git checkout -q "$DEFAULT" )
SIDE=$(sha side); HEAD=$(sha "$DEFAULT")
run_hook "refs/heads/side $SIDE refs/heads/side 0000000000000000000000000000000000000000"
expect "推送非 HEAD 且非祖先的提交 → 阻断" 1 "不是当前检出提交"
not_called "MAKECHECK" && not_called "CHECK --run" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 别的树：仍跑了检查"; }

# ── 8 祖先提交（如旧 tag）→ 只发布既有 ref，明确跳过 ─────────
newrepo ancestor
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
run_hook "refs/tags/old $BASE refs/tags/old 0000000000000000000000000000000000000000"
expect "祖先提交的 tag → 明确跳过内容检查" 0 "已在当前 HEAD 历史中"
called "GUARD" && not_called "CHECK --run" && not_called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 祖先 tag：不该跑内容检查"; }

# ── 9 未收到 ref 列表（直接调用钩子）→ 全量 ─────────────────
newrepo noinput
run_hook ""
expect "无 ref 列表 → 全量（不猜范围）" 0 "无法判定范围"
called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 无输入：未扩到全量"; }

# ── 10 选测引擎报 unresolved（exit 2）→ 按要求全量 ──────────
newrepo unresolved
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
ENVS="CHECK_RC=2"
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
ENVS=""
expect "选中测试 unresolved → 全量 make check" 0 "有未映射的影响面"
called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - unresolved：未扩到全量"; }

# ── 11 选测失败 → 不推送、不降级为全量 ──────────────────────
newrepo checkfail
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
ENVS="CHECK_RC=1"
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
ENVS=""
expect "选测失败 → 阻断推送" 1 "选测失败"
not_called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 选测失败：不该改跑全量"; }

# ── 12 工作区有未提交的已跟踪改动 → 阻断 ────────────────────
newrepo dirty
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
printf 'dirty\n' >> "$WORK/$CASE/first.txt"
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
expect "工作区脏 → 阻断（不冒充提交结果）" 1 "未提交的已跟踪改动"
not_called "CHECK --run" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 脏工作区：仍跑了选测"; }

# ── 13 guard 失败 → 直接阻断，且不跑后续检查 ────────────────
newrepo guardfail
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
ENVS="GUARD_RC=1"
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
ENVS=""
expect "guard 失败 → 阻断" 1
not_called "CHECK --run" && not_called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - guard 失败：仍跑了后续检查"; }

# ── 14 无 scripts/check.py 的仓 → 维持全量 make check ───────
newrepo plainrepo
commit second.txt two
rm -f "$WORK/$CASE/scripts/check.py"
HEAD=$(sha HEAD)
run_hook "refs/heads/main $HEAD refs/heads/main $HEAD"
expect "无 check.py → 按仓库入口全量" 0 "全量 make check"
called "MAKECHECK" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 无 check.py：未跑 make check"; }

# ── 15 有 check.py 但选测引擎不可用 → 打印原因后全量 ─────────
newrepo noengine
commit second.txt two
BASE=$(sha HEAD~1); HEAD=$(sha HEAD)
ENVS="VERSION_RC=1"
run_hook "refs/heads/main $HEAD refs/heads/main $BASE"
ENVS=""
expect "选测引擎不可用 → 全量并说明" 0 "选测引擎不可用"
called "MAKECHECK" && not_called "CHECK --run" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 引擎缺失：未回退全量"; }

# ── 16 引擎不可用且无全量入口 → 阻断（不静默通过）──────────
newrepo noengine-nocheck
commit second.txt two
rm -f "$WORK/$CASE/Makefile"
HEAD=$(sha HEAD)
ENVS="VERSION_RC=1"
run_hook "refs/heads/main $HEAD refs/heads/main $HEAD"
ENVS=""
expect "引擎不可用且无全量入口 → 阻断" 1 "选测引擎不可用且无全量入口"
not_called "CHECK --run" && pass=$((pass+1)) || { fail=$((fail+1)); FAILED_CASES="$FAILED_CASES\n  - 引擎缺失无入口：仍跑了选测"; }

# ── 汇总 ─────────────────────────────────────────────────────
echo
printf 'pre-push 范围自测：%s 通过 / %s 失败\n' "$pass" "$fail"
if [ "$fail" -ne 0 ]; then
  printf '失败用例:%b\n' "$FAILED_CASES"
  exit 1
fi
exit 0
