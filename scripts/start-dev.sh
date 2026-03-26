#!/usr/bin/env bash
# =============================================================================
# 启动 mynet.local 开发环境基础服务（MySQL + PHP-FPM + Nginx）
# =============================================================================
# 用法: bash scripts/start-dev.sh
#
# 所有站点现已由 Nginx 直接伺服静态文件或 PHP-FPM，无需 Vite dev server。
# 前端项目（gui / web）需先通过 build-local.sh 构建。
#
# 如需完整构建+启动: bash scripts/build-local.sh && bash scripts/start-dev.sh
# =============================================================================
set -euo pipefail

CYAN='\033[0;36m'  GREEN='\033[0;32m'  YELLOW='\033[0;33m'  RED='\033[0;31m'  NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }

# ── 1. MySQL ──
info "检查 MySQL …"
if ! pgrep -x mysqld > /dev/null 2>&1; then
    info "启动 MySQL …"
    mysqld --daemonize 2>/dev/null || brew services start mysql 2>/dev/null || warn "请手动启动 MySQL"
    sleep 2
fi
if pgrep -x mysqld > /dev/null 2>&1; then
    ok "MySQL 运行中"
else
    fail "MySQL 未运行！PHP 项目将无法正常工作"
fi

# ── 2. PHP-FPM ──
info "检查 PHP-FPM …"
if ! pgrep -f 'php-fpm' > /dev/null 2>&1; then
    info "启动 PHP-FPM …"
    brew services start php 2>/dev/null || /usr/local/opt/php/sbin/php-fpm --daemonize 2>/dev/null || warn "请手动启动: brew services start php"
    sleep 1
fi
if pgrep -f 'php-fpm' > /dev/null 2>&1; then
    ok "PHP-FPM 运行中 ($(pgrep -f 'php-fpm' | wc -l | tr -d ' ') 进程)"
else
    fail "PHP-FPM 未运行！后端服务将无法工作"
fi

# ── 3. Nginx ──
info "检查 Nginx …"
if ! pgrep -x nginx > /dev/null 2>&1; then
    info "启动 Nginx …"
    sudo nginx 2>/dev/null || warn "请手动启动: sudo nginx"
    sleep 1
fi
if pgrep -x nginx > /dev/null 2>&1; then
    ok "Nginx 运行中"
    sudo nginx -s reload 2>/dev/null && ok "Nginx 配置已重载"
else
    fail "Nginx 未运行！"
fi

# ── 总结 ──
echo ""
echo "============================================="
echo "  mynet.local 开发环境（全部 Nginx 直接伺服）"
echo "============================================="
echo ""
echo "  PHP 后端（Nginx + PHP-FPM）:"
echo "    http://api.mynet.local       → mynet_back (API)"
echo "    http://admin.mynet.local     → mynet_back (Admin)"
echo "    http://ctl.mynet.local       → mynet_ctl"
echo ""
echo "  前端（Nginx 伺服构建产物）:"
echo "    http://my.mynet.local        → mynet_gui (Quasar SPA)"
echo "    http://www.mynet.local       → mynet_web (Vue3 SPA)"
echo ""
echo "  前端有改动需重新构建: bash scripts/build-local.sh gui web"
echo "  停止服务: bash scripts/stop-dev.sh"
echo ""
