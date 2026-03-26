#!/usr/bin/env bash
# =============================================================================
# 停止所有 mynet.local 开发服务
# =============================================================================
# 用法: bash scripts/stop-dev.sh [back | ctl | gui | web]
# 不带参数 = 停止全部
# =============================================================================
set -euo pipefail

WORKSPACE="/Users/yue/Devel/WorkSpacePHP/mynet.club"
PID_DIR="${WORKSPACE}/mynet_ctl/storage/app/private/dev-pids"

RED='\033[0;31m'  GREEN='\033[0;32m'  YELLOW='\033[0;33m'  NC='\033[0m'
ok()   { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }

stop_service() {
    local name=$1 pidfile="${PID_DIR}/${1}.pid"

    if [ -f "$pidfile" ]; then
        local pid
        pid=$(cat "$pidfile")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null
            # 也清理子进程（artisan serve 会 fork php worker）
            pkill -P "$pid" 2>/dev/null || true
            ok "已停止 ${name} (PID ${pid})"
        else
            warn "${name} 已不在运行 (PID ${pid})"
        fi
        rm -f "$pidfile"
    else
        warn "${name} 无 PID 文件"
    fi
}

if [ $# -eq 0 ]; then
    TARGETS=(back ctl gui web)
else
    TARGETS=("$@")
fi

for t in "${TARGETS[@]}"; do
    stop_service "$t"
done

echo ""
echo "  剩余后台服务可用 'bash scripts/start-dev.sh' 重新启动"
