#!/bin/bash
# ════════════════════════════════════════════════════════════
# MyNet CTL 生产部署脚本
#
# 🔴 整仓覆盖 ≠ 日常部署（2026-10-13 对账口径，务必先读）
#
#   生产 /data/app/mynet.club/ctl **不是 git 检出**，代码停在 2026-03，
#   与仓库有约 7 个月漂移（含生产侧手改与锚点补丁）。本脚本的
#   `rsync -avz --delete` 是**整仓覆盖**：会无提示抹掉生产侧所有手改，
#   并按 --delete 清掉仓库里没有的生产独有文件。
#
#   ⇒ **整仓部署是另一次独立发布**（需要真正的 3 月→10 月 diff 评审 + 单独审批），
#     不能拿本脚本当「日常同步」随手跑。日常改生产走**锚点式补丁**
#     （cp -a 备份 → 定点替换 → php -l 断言）。
#
# 用法:
#   ./scripts/deploy/deploy.sh plan         # 只出审查清单（不连生产、不 rsync）
#   ./scripts/deploy/deploy.sh              # 交互式部署
#   ./scripts/deploy/deploy.sh sync         # 仅同步代码（不重启服务）
#   ./scripts/deploy/deploy.sh upgrade      # 同步 + composer + migrate + cache
#   ./scripts/deploy/deploy.sh full         # 完整部署（含 nginx 配置）
#   ./scripts/deploy/deploy.sh nginx        # 仅更新 nginx 配置
#
# 服务器: root@10.182.236.180 (ctl.mynet.club)
# 目录:   /data/app/mynet.club/ctl
# ════════════════════════════════════════════════════════════

set -euo pipefail

# ── 配置 ──
REMOTE_HOST="10.182.236.180"
REMOTE_USER="root"
REMOTE_DIR="/data/app/mynet.club/ctl"
REMOTE_BACKUP_DIR="/data/backup/mynet/ctl"
DOMAIN="ctl.mynet.club"

# 脚本路径
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
RSYNC_IGNORE="$SCRIPT_DIR/.rsyncignore"
NGINX_CONF="$SCRIPT_DIR/nginx.conf"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# ── 颜色 ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }
log_step()    { echo; echo -e "${PURPLE}━━━ $1 ━━━${NC}"; }

# ════════════════════════════════════════════════════════════
# 部署前检查
# ════════════════════════════════════════════════════════════

check_prerequisites() {
    log_step "前置检查"

    # 在项目根目录
    cd "$PROJECT_ROOT"

    # SSH 连通性
    if ssh -o ConnectTimeout=10 "$REMOTE_USER@$REMOTE_HOST" "echo ok" > /dev/null 2>&1; then
        log_success "SSH 连接正常 ($REMOTE_USER@$REMOTE_HOST)"
    else
        log_error "SSH 连接失败: $REMOTE_USER@$REMOTE_HOST"
        exit 1
    fi

    # rsync 排除文件
    if [ ! -f "$RSYNC_IGNORE" ]; then
        log_error "缺少 $RSYNC_IGNORE"
        exit 1
    fi

    # Git 状态
    if command -v git &>/dev/null && [ -d .git ]; then
        local changes
        changes=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
        if [ "$changes" -gt 0 ]; then
            log_warn "有 $changes 个未提交的更改"
        else
            log_success "Git 工作区干净"
        fi
    fi
}

# ════════════════════════════════════════════════════════════
# 记录版本信息
# ════════════════════════════════════════════════════════════

record_version() {
    log_info "记录版本信息..."

    cd "$PROJECT_ROOT"
    local git_commit git_branch git_tag deploy_time deploy_user
    git_commit=$(git rev-parse HEAD 2>/dev/null || echo "unknown")
    git_branch=$(git branch --show-current 2>/dev/null || echo "unknown")
    git_tag=$(git describe --tags 2>/dev/null || echo "no-tag")
    deploy_time=$(date '+%Y-%m-%d %H:%M:%S')
    deploy_user=$(whoami)

    cat > "$PROJECT_ROOT/.deployment-info" << EOF
{
    "deployment": {
        "time": "$deploy_time",
        "user": "$deploy_user",
        "type": "$DEPLOY_TYPE"
    },
    "git": {
        "commit": "$git_commit",
        "branch": "$git_branch",
        "tag": "$git_tag",
        "short_commit": "${git_commit:0:8}"
    },
    "environment": {
        "target": "remote",
        "domain": "$DOMAIN"
    }
}
EOF
    log_success "版本: ${git_commit:0:8} ($git_branch)"
}

# ════════════════════════════════════════════════════════════
# 备份
# ════════════════════════════════════════════════════════════

# plan —— G5.c：出 3月→10月 发布审查清单，不连生产、不改任何文件
plan_release() {
    log_step "发布审查清单（plan，不执行部署）"
    echo "远程: $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"
    echo "本地: $PROJECT_ROOT"
    echo
    echo "【1】整仓覆盖范围（rsync --delete 将抹掉）"
    echo "  - 生产侧所有手改（锚点补丁）"
    echo "  - 生产独有文件（仓库没有的）"
    echo "  - 请在跳板机执行: find $REMOTE_DIR -type f | sort > /tmp/prod-ctl-files.txt"
    echo "    本地: cd $PROJECT_ROOT && git ls-files | sort > /tmp/repo-ctl-files.txt"
    echo "    对照: comm -23 /tmp/prod-ctl-files.txt /tmp/repo-ctl-files.txt  # 目标独有"
    echo "         comm -13 /tmp/prod-ctl-files.txt /tmp/repo-ctl-files.txt  # 仅仓库有"
    echo
    echo "【2】批准范围（必须书面确认）"
    echo "  [ ] 3月→10月 diff 已人工评审"
    echo "  [ ] 目标独有文件/手改清单已列出并决定去留"
    echo "  [ ] schema/迁移依赖：composer install + migrate 的回滚点已知"
    echo "  [ ] 发布矩阵（G4 双件）与 manifest 影响已评估"
    echo
    echo "【3】备份与回滚"
    echo "  备份: $REMOTE_BACKUP_DIR/ctl_backup_<ts>.tar.gz（脚本 backup_remote）"
    echo "  回滚: 1) 解开备份到 $REMOTE_DIR  2) php artisan config:clear  3) 重启 php-fpm/nginx"
    echo "  失败停止: 任一步非零退出即停，不继续 migrate/覆盖"
    echo
    echo "【4】本计划不替代"
    echo "  - 漂移检测（需 rsync --dry-run --itemize-changes 对生产）"
    echo "  - 上线验收（需真实 HTTP/发布链 smoke）"
    echo "  两者都要单独做，且归用户发布窗口。"
}

backup_remote() {
    log_step "备份现有部署"

    ssh "$REMOTE_USER@$REMOTE_HOST" << EOF
if [ -d "$REMOTE_DIR/app" ]; then
    mkdir -p "$REMOTE_BACKUP_DIR"
    tar czf "$REMOTE_BACKUP_DIR/ctl_backup_$TIMESTAMP.tar.gz" \
        -C "$(dirname "$REMOTE_DIR")" "$(basename "$REMOTE_DIR")" \
        --exclude='vendor' --exclude='node_modules' \
        --exclude='storage/logs' --exclude='storage/framework/cache' \
        2>/dev/null || true
    # 保留最近 5 个
    cd "$REMOTE_BACKUP_DIR"
    ls -t ctl_backup_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
    echo "备份完成: ctl_backup_$TIMESTAMP.tar.gz"
else
    echo "首次部署，跳过备份"
fi
EOF
    log_success "远程备份完成"
}

# ════════════════════════════════════════════════════════════
# 同步代码 (rsync)
# ════════════════════════════════════════════════════════════

sync_code() {
    log_step "同步代码"

    cd "$PROJECT_ROOT"
    record_version

    # 确保远程目录结构
    ssh "$REMOTE_USER@$REMOTE_HOST" << EOF
mkdir -p "$REMOTE_DIR"/{storage/{logs,framework/{cache,sessions,views},app/{releases,plugins,scripts,openwrt_cache}},bootstrap/cache}
EOF

    log_info "rsync 同步中..."
    log_warn "整仓覆盖 + --delete：生产侧手改与仓库没有的文件会被抹掉（见脚本头红线）"
    rsync -avz --delete \
        --exclude-from="$RSYNC_IGNORE" \
        --progress \
        "$PROJECT_ROOT/" \
        "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/"

    log_success "代码同步完成"
}

# ════════════════════════════════════════════════════════════
# 远程操作
# ════════════════════════════════════════════════════════════

# 安装/更新 Composer 依赖
remote_composer() {
    log_step "Composer 依赖"

    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
cd /data/app/mynet.club/ctl
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
echo "✅ Composer 完成"
EOF
    log_success "Composer 依赖安装完成"
}

# 数据库迁移
remote_migrate() {
    log_step "数据库迁移"

    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
cd /data/app/mynet.club/ctl
php artisan migrate --force 2>&1
echo "✅ 迁移完成"
EOF
    log_success "数据库迁移完成"
}

# 缓存操作
remote_cache() {
    log_step "缓存优化"

    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
cd /data/app/mynet.club/ctl

echo "清理缓存..."
php artisan cache:clear 2>&1 | grep -E "(INFO|cleared)" || true
php artisan config:clear 2>&1 | grep -E "(INFO|cleared)" || true
php artisan route:clear 2>&1 | grep -E "(INFO|cleared)" || true
php artisan view:clear 2>&1 | grep -E "(INFO|cleared)" || true

echo "重建缓存..."
php artisan config:cache 2>&1 | grep -E "(INFO|cached)" || true
php artisan route:cache 2>&1 | grep -E "(INFO|cached)" || true

echo "✅ 缓存优化完成"
EOF
    log_success "缓存优化完成"
}

# 权限修复
remote_permissions() {
    log_step "修复权限"

    ssh "$REMOTE_USER@$REMOTE_HOST" << EOF
chown -R www-data:www-data "$REMOTE_DIR/storage"
chown -R www-data:www-data "$REMOTE_DIR/bootstrap/cache"
chmod -R 775 "$REMOTE_DIR/storage"
chmod -R 775 "$REMOTE_DIR/bootstrap/cache"
echo "✅ 权限已修复"
EOF
    log_success "权限修复完成"
}

# 重载 PHP-FPM（自动检测版本）
remote_reload_php() {
    log_info "重载 PHP-FPM..."
    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOFPHP'
PHP_FPM_SVC=""
for svc in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        PHP_FPM_SVC="$svc"
        break
    fi
done
if [ -n "$PHP_FPM_SVC" ]; then
    systemctl reload "$PHP_FPM_SVC" && echo "✅ $PHP_FPM_SVC 已重载"
else
    echo "⚠️ 未检测到运行中的 php-fpm 服务"
fi
EOFPHP
}

# ════════════════════════════════════════════════════════════
# Nginx 配置
# ════════════════════════════════════════════════════════════

deploy_nginx() {
    log_step "更新 Nginx 配置"

    if [ ! -f "$NGINX_CONF" ]; then
        log_error "缺少 $NGINX_CONF"
        return 1
    fi

    # 上传配置
    scp "$NGINX_CONF" "$REMOTE_USER@$REMOTE_HOST:/etc/nginx/sites-available/ctl.mynet.club.conf"

    # 测试并重载
    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
# 确保日志目录
mkdir -p /data/log/nginx

# 测试配置
nginx -t 2>&1
if [ $? -eq 0 ]; then
    systemctl reload nginx
    echo "✅ Nginx 已重载"
else
    echo "❌ Nginx 配置有误，未重载"
    exit 1
fi
EOF
    log_success "Nginx 配置已更新"
}

# ════════════════════════════════════════════════════════════
# .env 初始化（首次部署时需要）
# ════════════════════════════════════════════════════════════

ensure_env() {
    local has_env
    has_env=$(ssh "$REMOTE_USER@$REMOTE_HOST" "[ -f $REMOTE_DIR/.env ] && echo yes || echo no")

    if [ "$has_env" = "no" ]; then
        log_warn "服务器缺少 .env 文件"

        # 尝试上传 .env.production 模板
        if [ -f "$PROJECT_ROOT/.env.production" ]; then
            log_info "上传 .env.production 作为初始模板..."
            scp "$PROJECT_ROOT/.env.production" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/.env"
            ssh "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_DIR && php artisan key:generate --force"
            log_success ".env 已从模板创建并生成 APP_KEY"
            echo
            echo -e "${YELLOW}⚠️  请登录服务器修改 DB_PASSWORD:${NC}"
            echo "  ssh $REMOTE_USER@$REMOTE_HOST"
            echo "  vim $REMOTE_DIR/.env"
        else
            echo
            echo -e "${CYAN}请在服务器上创建 .env:${NC}"
            echo "  1. ssh $REMOTE_USER@$REMOTE_HOST"
            echo "  2. cp $REMOTE_DIR/.env.example $REMOTE_DIR/.env"
            echo "  3. vim $REMOTE_DIR/.env  # 修改 DB/APP_URL 等"
            echo "  4. cd $REMOTE_DIR && php artisan key:generate"
        fi
        echo
        echo -e "${YELLOW}生产环境关键配置:${NC}"
        echo "  APP_ENV=production"
        echo "  APP_DEBUG=false"
        echo "  APP_URL=https://ctl.mynet.club"
        echo "  DB_DATABASE=mynet_ctl"
        echo "  DB_USERNAME=mynet_admin"
        echo "  DB_PASSWORD=<production_password>"
        echo "  MYNET_BACK_API_URL=https://api.mynet.club"
        echo
        read -p "已创建并配置好 .env 文件? [y/N] " -r
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_error "请先配置 .env 文件"
            exit 1
        fi
    fi
}

# ════════════════════════════════════════════════════════════
# 部署后验证
# ════════════════════════════════════════════════════════════

verify_deployment() {
    log_step "部署验证"

    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
cd /data/app/mynet.club/ctl

echo "── Laravel 状态 ──"
php artisan --version 2>&1

echo "── 路由数 ──"
php artisan route:list --count 2>/dev/null || php artisan route:list 2>/dev/null | tail -1

echo "── .deployment-info ──"
cat .deployment-info 2>/dev/null | head -5
EOF

    log_success "部署验证完成"
}

# ════════════════════════════════════════════════════════════
# 部署流程
# ════════════════════════════════════════════════════════════

do_sync() {
    check_prerequisites
    sync_code
    remote_permissions
    remote_reload_php
    verify_deployment
}

do_upgrade() {
    check_prerequisites
    backup_remote
    sync_code
    ensure_env
    remote_composer
    remote_migrate
    remote_cache
    remote_permissions
    remote_reload_php
    verify_deployment
}

do_full() {
    check_prerequisites
    backup_remote
    sync_code
    ensure_env
    remote_composer
    remote_migrate
    remote_cache
    remote_permissions
    deploy_nginx
    remote_reload_php
    verify_deployment
}

do_nginx() {
    check_prerequisites
    deploy_nginx
}

# ════════════════════════════════════════════════════════════
# 入口
# ════════════════════════════════════════════════════════════

DEPLOY_TYPE="${1:-}"

show_banner() {
    echo
    echo -e "${CYAN}╔════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     MyNet CTL 部署 → $DOMAIN     ║${NC}"
    echo -e "${CYAN}║     服务器: $REMOTE_USER@$REMOTE_HOST          ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════╝${NC}"
    echo
}

show_banner

if [ -z "$DEPLOY_TYPE" ]; then
    echo "  用法: $0 <command>"
    echo
    echo -e "  ${GREEN}sync${NC}      仅同步代码（快速，不重启服务）"
    echo -e "  ${GREEN}upgrade${NC}   同步 + composer + migrate + cache（常用）"
    echo -e "  ${GREEN}full${NC}      完整部署（含 nginx 配置）"
    echo -e "  ${GREEN}nginx${NC}     仅更新 nginx 配置"
    echo
    read -p "请选择部署类型 [sync/upgrade/full/nginx]: " DEPLOY_TYPE
fi

echo
echo -e "部署类型: ${GREEN}$DEPLOY_TYPE${NC}"
echo -e "目标: ${GREEN}$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR${NC}"
echo
# plan 只出清单，不连生产，无需确认
if [ "$DEPLOY_TYPE" = "plan" ]; then
    plan_release
    exit 0
fi
read -p "确认开始部署? [y/N] " -r
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_info "已取消"
    exit 0
fi

case "$DEPLOY_TYPE" in
    plan)    plan_release; exit 0 ;;
    sync)    do_sync ;;
    upgrade) do_upgrade ;;
    full)    do_full ;;
    nginx)   do_nginx ;;
    *)
        log_error "未知类型: $DEPLOY_TYPE"
        echo "可选: sync | upgrade | full | nginx"
        exit 1
        ;;
esac

echo
log_success "════════════════════════════════════════"
log_success "  部署完成: $DEPLOY_TYPE"
log_success "  域名: https://$DOMAIN"
log_success "════════════════════════════════════════"
echo
