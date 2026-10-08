#!/usr/bin/env bash
# =============================================================================
# mynet.local 开发环境一次性初始化
# =============================================================================
# 用法: sudo bash scripts/setup-local-env.sh
# 需要 sudo 权限：写 /etc/hosts、操作 MySQL 权限、安装 Nginx 配置
# =============================================================================
set -euo pipefail

WORKSPACE="${MYNET_WORKSPACE:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CYAN='\033[0;36m'  GREEN='\033[0;32m'  RED='\033[0;31m'  NC='\033[0m'

info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[ OK ]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }

# ── 1. /etc/hosts ────────────────────────────────────────────────────────────
info "检查 /etc/hosts …"
DOMAINS="api.mynet.local admin.mynet.local ctl.mynet.local my.mynet.local www.mynet.local"
HOSTS_LINE="127.0.0.1  ${DOMAINS}"

if grep -q "mynet.local" /etc/hosts; then
    ok "/etc/hosts 已包含 mynet.local 条目（跳过）"
else
    echo "" >> /etc/hosts
    echo "# mynet.local 开发域名" >> /etc/hosts
    echo "${HOSTS_LINE}" >> /etc/hosts
    ok "/etc/hosts 已写入: ${HOSTS_LINE}"
fi

# ── 2. MySQL ─────────────────────────────────────────────────────────────────
info "检查 MySQL …"
MYSQL_DATA_DIR="/usr/local/var/mysql"

if [ -d "$MYSQL_DATA_DIR" ]; then
    CURRENT_USER=$(stat -f '%Su' "$MYSQL_DATA_DIR")
    EXPECTED_USER=$(logname 2>/dev/null || echo "$SUDO_USER")
    if [ "$CURRENT_USER" != "$EXPECTED_USER" ]; then
        info "修复 MySQL 数据目录权限: $CURRENT_USER → $EXPECTED_USER"
        chown -R "$EXPECTED_USER" "$MYSQL_DATA_DIR"
        ok "MySQL 数据目录权限已修复"
    else
        ok "MySQL 数据目录权限正常 (owner=$CURRENT_USER)"
    fi
fi

# 启动 MySQL（以非 root 用户身份）
if ! pgrep -x mysqld > /dev/null 2>&1; then
    info "启动 MySQL …"
    su "${SUDO_USER:-$(logname)}" -c "mysqld --daemonize" 2>/dev/null || true
    sleep 2
    if pgrep -x mysqld > /dev/null 2>&1; then
        ok "MySQL 已启动"
    else
        fail "MySQL 启动失败，请手动检查: mysqld --verbose --help | tail -5"
        fail "错误日志: ${MYSQL_DATA_DIR}/*.err"
    fi
else
    ok "MySQL 已在运行"
fi

# 创建数据库和用户
if pgrep -x mysqld > /dev/null 2>&1; then
    info "创建数据库和用户 …"
    mysql -u root <<'SQL' 2>/dev/null || true
CREATE DATABASE IF NOT EXISTS mynet_back  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mynet_ctl   DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'mynet'@'localhost' IDENTIFIED BY 'mynet_dev';
GRANT ALL PRIVILEGES ON mynet_back.* TO 'mynet'@'localhost';
GRANT ALL PRIVILEGES ON mynet_ctl.*  TO 'mynet'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "数据库 mynet_back / mynet_ctl 已创建，用户 mynet 已授权"
fi

# ── 3. Redis ─────────────────────────────────────────────────────────────────
info "检查 Redis …"
if command -v redis-server &>/dev/null; then
    ok "Redis 已安装 ($(redis-server --version | head -c 40))"
else
    info "安装 Redis …"
    su "${SUDO_USER:-$(logname)}" -c "brew install redis" 2>/dev/null || true
    if command -v redis-server &>/dev/null; then
        ok "Redis 安装完成"
    else
        fail "Redis 安装失败，请手动: brew install redis"
    fi
fi

if ! pgrep -x redis-server > /dev/null 2>&1; then
    info "启动 Redis …"
    su "${SUDO_USER:-$(logname)}" -c "redis-server --daemonize yes" 2>/dev/null || true
    sleep 1
    if pgrep -x redis-server > /dev/null 2>&1; then
        ok "Redis 已启动"
    else
        fail "Redis 启动失败"
    fi
else
    ok "Redis 已在运行"
fi

# ── 3b. PHP-FPM ──────────────────────────────────────────────────────────────
info "检查 PHP-FPM …"
PHP_FPM="/usr/local/opt/php/sbin/php-fpm"
if [ -x "$PHP_FPM" ]; then
    ok "PHP-FPM 已安装: $($PHP_FPM -v 2>/dev/null | head -1)"
else
    fail "PHP-FPM 未找到，请安装: brew install php"
fi

if ! pgrep -f 'php-fpm' > /dev/null 2>&1; then
    info "启动 PHP-FPM …"
    su "${SUDO_USER:-$(logname)}" -c "brew services start php" 2>/dev/null || true
    sleep 1
    if pgrep -f 'php-fpm' > /dev/null 2>&1; then
        ok "PHP-FPM 已启动"
    else
        fail "PHP-FPM 启动失败，请手动: brew services start php"
    fi
else
    ok "PHP-FPM 已在运行"
fi

# ── 4. Nginx 配置 ────────────────────────────────────────────────────────────
info "配置 Nginx worker 用户 …"
EXPECTED_USER="${SUDO_USER:-$(logname)}"
NGINX_MAIN_CONF="/usr/local/etc/nginx/nginx.conf"
if grep -q "^user ${EXPECTED_USER}" "$NGINX_MAIN_CONF" 2>/dev/null; then
    ok "Nginx 已配置为用户 ${EXPECTED_USER}"
elif grep -q "^#user" "$NGINX_MAIN_CONF" 2>/dev/null; then
    sed -i '' "s/^#user.*/user ${EXPECTED_USER} staff;/" "$NGINX_MAIN_CONF"
    ok "Nginx worker 用户已设为 ${EXPECTED_USER} staff（避免文件权限问题）"
elif grep -q "^user" "$NGINX_MAIN_CONF" 2>/dev/null; then
    sed -i '' "s/^user.*/user ${EXPECTED_USER} staff;/" "$NGINX_MAIN_CONF"
    ok "Nginx worker 用户已更新为 ${EXPECTED_USER} staff"
else
    sed -i '' "1i\\
user ${EXPECTED_USER} staff;
" "$NGINX_MAIN_CONF"
    ok "Nginx worker 用户已插入为 ${EXPECTED_USER} staff"
fi

info "安装 Nginx 配置 …"
NGINX_CONF_SRC="${SCRIPT_DIR}/nginx/mynet-local.conf"
NGINX_CONF_DST="/usr/local/etc/nginx/servers/mynet-local.conf"

if [ -f "$NGINX_CONF_SRC" ]; then
    ln -sf "$NGINX_CONF_SRC" "$NGINX_CONF_DST"
    ok "Nginx 配置已链接: $NGINX_CONF_DST"
else
    fail "找不到 $NGINX_CONF_SRC"
fi

# 检查 Nginx 配置语法
nginx -t 2>&1 && ok "Nginx 配置语法检查通过" || fail "Nginx 配置语法错误"

# 启动或重载 Nginx
if pgrep -x nginx > /dev/null 2>&1; then
    nginx -s reload
    ok "Nginx 已重载"
else
    nginx
    ok "Nginx 已启动"
fi

# ── 5. CTL 数据库迁移 ────────────────────────────────────────────────────────
info "运行 mynet_ctl 数据库迁移 …"
if pgrep -x mysqld > /dev/null 2>&1; then
    cd "${WORKSPACE}/mynet_ctl"
    su "${SUDO_USER:-$(logname)}" -c "cd '${WORKSPACE}/mynet_ctl' && php artisan migrate --force" 2>/dev/null || true
    ok "mynet_ctl 迁移完成"
fi

# ── 完成 ─────────────────────────────────────────────────────────────────────
echo ""
echo "============================================="
echo "  mynet.local 开发环境初始化完成"
echo "  (nginx + php-fpm 模式)"
echo "============================================="
echo ""
echo "  浏览器直接访问（端口 80, nginx + php-fpm）:"
echo "    http://api.mynet.local      → mynet_api API"
echo "    http://admin.mynet.local    → mynet_api 管理后台"
echo "    http://ctl.mynet.local      → mynet_ctl 分发中心"
echo ""
echo "  前端需额外启动 Vite dev server:"
echo "    http://my.mynet.local       → mynet_gui (quasar dev :5173)"
echo "    http://www.mynet.local      → mynet_web (vite :5174)"
echo ""
echo "  MySQL 账号: mynet / mynet_dev"
echo "  数据库: mynet_back, mynet_ctl"
echo ""
echo "  下一步: bash scripts/start-dev.sh       # 仅检查服务状态"
echo "          bash scripts/start-dev.sh --all  # 同时启动前端 dev server"
echo ""
