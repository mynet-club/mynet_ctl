#!/usr/bin/env bash
# =============================================================================
# 构建所有 mynet.local 项目并重载 Nginx
# =============================================================================
# 用法:
#   bash scripts/build-local.sh          # 构建全部
#   bash scripts/build-local.sh gui      # 仅构建 GUI
#   bash scripts/build-local.sh web      # 仅构建 Web
#   bash scripts/build-local.sh ctl      # 仅构建 CTL 前端 + 清缓存
#   bash scripts/build-local.sh back     # 仅清理 Back 缓存
#   bash scripts/build-local.sh gui web  # 构建 GUI + Web
# =============================================================================
set -euo pipefail

WORKSPACE="/Users/yue/Devel/WorkSpacePHP/mynet.club"

CYAN='\033[0;36m'  GREEN='\033[0;32m'  YELLOW='\033[0;33m'  RED='\033[0;31m'  NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; exit 1; }

# ── Laravel 缓存清理 ──
laravel_clear() {
    local name=$1 dir=$2
    info "[$name] 清理 Laravel 缓存 …"
    cd "$dir"
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear  2>/dev/null || true
    php artisan view:clear   2>/dev/null || true
    php artisan cache:clear  2>/dev/null || true
    ok "[$name] 缓存已清理"
}

# ── 前端构建 ──
build_gui() {
    info "[GUI] 构建 Quasar SPA → dist/spa/ …"
    cd "${WORKSPACE}/mynet_gui"
    npm run build
    ok "[GUI] 构建完成 → ${WORKSPACE}/mynet_gui/dist/spa/"
}

build_web() {
    info "[Web] 构建 Vue3 SPA → dist/ …"
    cd "${WORKSPACE}/mynet_web"
    npm run build
    ok "[Web] 构建完成 → ${WORKSPACE}/mynet_web/dist/"
}

build_ctl_frontend() {
    info "[CTL] 构建 Vite 前端 → public/build/ …"
    cd "${WORKSPACE}/mynet_ctl"
    npm run build
    ok "[CTL] 前端构建完成"
}

# ── Nginx 重载 ──
reload_nginx() {
    info "验证 Nginx 配置 …"
    if sudo nginx -t 2>&1; then
        sudo nginx -s reload
        ok "Nginx 已重载"
    else
        fail "Nginx 配置有误，请检查"
    fi
}

# ── 主逻辑 ──
TARGETS=("$@")
if [ ${#TARGETS[@]} -eq 0 ]; then
    TARGETS=(back ctl gui web)
fi

echo ""
echo "============================================="
echo "  mynet.local 本地构建"
echo "============================================="
echo "  目标: ${TARGETS[*]}"
echo "============================================="
echo ""

for target in "${TARGETS[@]}"; do
    case "$target" in
        back)
            laravel_clear "Back" "${WORKSPACE}/mynet_back"
            ;;
        ctl)
            build_ctl_frontend
            laravel_clear "CTL" "${WORKSPACE}/mynet_ctl"
            ;;
        gui)
            build_gui
            ;;
        web)
            build_web
            ;;
        *)
            warn "未知目标: $target (可选: back ctl gui web)"
            ;;
    esac
    echo ""
done

reload_nginx

echo ""
echo "============================================="
echo "  构建完成！可访问:"
echo "============================================="
echo ""
echo "  http://api.mynet.local       → mynet_back (API)"
echo "  http://admin.mynet.local     → mynet_back (Admin)"
echo "  http://ctl.mynet.local       → mynet_ctl"
echo "  http://my.mynet.local        → mynet_gui"
echo "  http://www.mynet.local       → mynet_web"
echo ""
