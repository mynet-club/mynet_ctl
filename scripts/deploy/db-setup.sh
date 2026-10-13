#!/bin/bash
# ════════════════════════════════════════════════════════════
# MyNet CTL 数据库初始化 & 本地数据同步
#
# 用法:
#   ./scripts/deploy/db-setup.sh                 # 完整流程：建库 + 迁移 + 同步
#   ./scripts/deploy/db-setup.sh create-db       # 仅在远程创建数据库和用户
#   ./scripts/deploy/db-setup.sh migrate         # 仅运行迁移
#   ./scripts/deploy/db-setup.sh sync            # 仅导出本地 → 导入远程
#   ./scripts/deploy/db-setup.sh dump-local      # 仅导出本地数据到 SQL 文件
#
# 依赖:
#   - 本地 MySQL 可用（mysqldump）
#   - 远程 SSH 可达
#
# ════════════════════════════════════════════════════════════

set -euo pipefail

# ── 配置 ──
REMOTE_HOST="10.182.236.180"
REMOTE_USER="root"
REMOTE_DIR="/data/app/mynet.club/ctl"

# 本地数据库
LOCAL_DB_HOST="127.0.0.1"
LOCAL_DB_PORT="3306"
LOCAL_DB_NAME="mynet_ctl"
LOCAL_DB_USER="mynet"
# ⚠️ 口令**不写在这个文件里**。此前这里硬编码了本地库口令（形如
# `LOCAL_DB_PASS="<明文>"`，值已脱敏 —— 写进注释也会被 guard A1 正确拦下），
# 触犯 AGENTS.md §3「不硬编码凭据」；而 guard 的 A1 当时还**漏报**它 ——
# 关键字表只有 password|passwd|pwd，匹配不到裸 `PASS=`（模板已补 `pass`，
# 并加了正反两条用例：明文口令要拦、pass=0 / BYPASS="enabled" 不得误报）。
# 🔴 那个值已进 git 历史，**需要轮换**，不是从文件里删掉就算完。
# 取法与 mynet_api/deploy/backup.sh 一致（V3-5）：FILE 优先，其次 env，都没有就报错退出。
if [ -n "${LOCAL_DB_PASSWORD_FILE:-}" ]; then
    [ -f "$LOCAL_DB_PASSWORD_FILE" ] || { echo "✗ LOCAL_DB_PASSWORD_FILE 不存在：${LOCAL_DB_PASSWORD_FILE}" >&2; exit 1; }
    LOCAL_DB_PASS="$(cat "$LOCAL_DB_PASSWORD_FILE")"
elif [ -n "${LOCAL_DB_PASSWORD+x}" ]; then
    # 用 +x 而不是 -n：本机开发库无口令时，显式给空串也算「已提供」
    LOCAL_DB_PASS="$LOCAL_DB_PASSWORD"
else
    echo "✗ 需要 LOCAL_DB_PASSWORD_FILE 或 LOCAL_DB_PASSWORD" >&2
    echo "  推荐：crt use <条目> -- ./scripts/deploy/db-setup.sh dump-local   # 口令只进子进程 env，不入上下文" >&2
    echo "  或：  LOCAL_DB_PASSWORD_FILE=~/.mynet/ctl-local-db.pass ./scripts/deploy/db-setup.sh …" >&2
    exit 1
fi

# 远程数据库（将在远程创建）
REMOTE_DB_NAME="mynet_ctl"
REMOTE_DB_USER="mynet_admin"
# 密码将手动输入或从远程 .env 读取

# 脚本路径
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DUMP_FILE="$SCRIPT_DIR/mynet_ctl_dump.sql"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# ── 颜色 ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }

# ════════════════════════════════════════════════════════════
# 在远程服务器创建数据库和用户
# ════════════════════════════════════════════════════════════

create_remote_db() {
    echo
    log_info "在远程服务器创建数据库..."
    echo -e "${YELLOW}请输入远程 MySQL root 密码（或留空如果远程 MySQL 免密）:${NC}"
    read -rs REMOTE_MYSQL_ROOT_PASS

    echo -e "${YELLOW}请设置生产数据库用户 ${REMOTE_DB_USER} 的密码:${NC}"
    read -rs REMOTE_DB_PASS
    echo

    # ⚠️ 口令不上命令行（与下面 import_to_remote 同一口径）：root 口令原来是
    # `mysql -p"..."` 上 argv（远端 ps 可见），应用口令拼进 `mysql -e` 的 SQL 文本
    # （同样落在 argv）。现在两枚口令只经 **ssh stdin** 送进远端 0600 临时文件，
    # mysql 走 --defaults-extra-file，SQL 走 stdin。（口令含换行会断行——别用带换行的值。）
    printf '%s\n%s\n' "$REMOTE_MYSQL_ROOT_PASS" "$REMOTE_DB_PASS" \
        | ssh "$REMOTE_USER@$REMOTE_HOST" 'umask 077; cat > /tmp/mynet_ctl_dbsetup_secrets'

    ssh "$REMOTE_USER@$REMOTE_HOST" "REMOTE_DB_NAME=$REMOTE_DB_NAME REMOTE_DB_USER=$REMOTE_DB_USER" bash -s << 'EOFDB'
set -e
SECRETS=/tmp/mynet_ctl_dbsetup_secrets
ROOT_PASS=$(sed -n '1p' "$SECRETS")
APP_PASS=$(sed -n '2p' "$SECRETS")
CNF=$(mktemp)
chmod 600 "$CNF"
trap 'rm -f "$CNF" "$SECRETS"' EXIT

{
    echo '[client]'
    echo 'user=root'
    printf 'password="%s"\n' "$(printf '%s' "$ROOT_PASS" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
} > "$CNF"

# SQL 单引号转义（' → ''）。SQL 全程走 stdin，不进任何进程 argv。
APP_PASS_SQL=$(printf '%s' "$APP_PASS" | sed "s/'/''/g")

mysql --defaults-extra-file="$CNF" << EOSQL
CREATE DATABASE IF NOT EXISTS \`${REMOTE_DB_NAME}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${REMOTE_DB_USER}'@'localhost'
    IDENTIFIED BY '${APP_PASS_SQL}';

GRANT ALL PRIVILEGES ON \`${REMOTE_DB_NAME}\`.* TO '${REMOTE_DB_USER}'@'localhost';

FLUSH PRIVILEGES;

SELECT 'Database and user created successfully' AS result;
EOSQL
EOFDB

    log_success "远程数据库 $REMOTE_DB_NAME 创建完成"
    echo
    echo -e "${YELLOW}请将以下内容更新到远程 .env:${NC}"
    echo "  DB_DATABASE=$REMOTE_DB_NAME"
    echo "  DB_USERNAME=$REMOTE_DB_USER"
    echo "  DB_PASSWORD=$REMOTE_DB_PASS"
    echo
}

# ════════════════════════════════════════════════════════════
# 运行远程迁移
# ════════════════════════════════════════════════════════════

run_remote_migrate() {
    log_info "运行远程数据库迁移..."

    ssh "$REMOTE_USER@$REMOTE_HOST" << 'EOF'
cd /data/app/mynet.club/ctl
php artisan migrate --force 2>&1
echo "✅ 迁移完成"
EOF
    log_success "远程迁移完成"
}

# ════════════════════════════════════════════════════════════
# 导出本地数据（结构 + 数据）
# ════════════════════════════════════════════════════════════

dump_local_data() {
    log_info "导出本地数据库..."

    # 需要同步的业务表（排除 Laravel 框架表如 sessions, cache, jobs, migrations）
    TABLES=(
        releases
        plugins
        scripts
        download_logs
        api_tokens
        openwrt_devices
        openwrt_device_firmware
    )

    log_info "导出表: ${TABLES[*]}"

    # 先导出结构（CREATE TABLE），再导出数据（INSERT）
    # 使用 --no-create-info 只导出数据，因为结构由 migration 管理
    #
    # ⚠️ 口令走 --defaults-extra-file，**不上命令行**：原来的 `-p"$LOCAL_DB_PASS"`
    # 会让口令出现在 `ps` 里，同机任何用户都看得到（与 mynet_api/deploy/backup.sh
    # 修掉的是同一个问题，见 V3-5）。
    local CNF DUMP_ERR
    CNF="$(mktemp)"
    DUMP_ERR="$(mktemp)"
    # shellcheck disable=SC2064  # 这里要的是**当前**路径，不是返回时才展开
    trap "rm -f '$CNF' '$DUMP_ERR'" RETURN
    chmod 600 "$CNF"
    # option file 里含引号/反斜杠的口令要转义，否则 mysql 解析出错
    local esc_pass
    esc_pass="$(printf '%s' "$LOCAL_DB_PASS" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
    cat > "$CNF" <<EOF
[client]
host="$LOCAL_DB_HOST"
port=$LOCAL_DB_PORT
user="$LOCAL_DB_USER"
password="$esc_pass"
EOF

    # ⚠️ 不再 2>/dev/null：那会把 mysqldump 的失败原因整个吞掉，
    # 只剩一个空 dump 文件，看起来像「本地没数据」。
    if ! mysqldump \
        --defaults-extra-file="$CNF" \
        --no-create-info \
        --complete-insert \
        --skip-lock-tables \
        --set-gtid-purged=OFF \
        --single-transaction \
        --quick \
        "$LOCAL_DB_NAME" \
        "${TABLES[@]}" \
        > "$DUMP_FILE" 2>"$DUMP_ERR"; then
        log_error "mysqldump 失败："
        cat "$DUMP_ERR" >&2
        return 1
    fi
    if ! grep -q 'Dump completed' "$DUMP_FILE"; then
        log_error "mysqldump 输出里没有结束标记「Dump completed」，dump 可能被截断"
        tail -3 "$DUMP_ERR" >&2
        return 1
    fi

    # 统计行数
    local rows
    rows=$(grep -c '^INSERT' "$DUMP_FILE" 2>/dev/null || echo "0")
    local size
    size=$(du -h "$DUMP_FILE" | cut -f1)

    log_success "导出完成: $DUMP_FILE ($size, $rows 条 INSERT)"
}

# ════════════════════════════════════════════════════════════
# 域名替换（本地 → 生产）
# ════════════════════════════════════════════════════════════

fix_domains_in_dump() {
    log_info "替换 SQL 中的本地域名为生产域名..."

    if [ ! -f "$DUMP_FILE" ]; then
        log_error "找不到 ${DUMP_FILE}，请先运行 dump-local"
        exit 1
    fi

    # 替换域名映射
    sed -i.bak \
        -e 's|http://ctl\.mynet\.local|https://ctl.mynet.club|g' \
        -e 's|http://api\.mynet\.local|https://api.mynet.club|g' \
        -e 's|http://www\.mynet\.local|https://www.mynet.club|g' \
        -e 's|http://my\.mynet\.local|https://my.mynet.club|g' \
        -e 's|ctl\.mynet\.local|ctl.mynet.club|g' \
        -e 's|api\.mynet\.local|api.mynet.club|g' \
        -e 's|www\.mynet\.local|www.mynet.club|g' \
        -e 's|my\.mynet\.local|my.mynet.club|g' \
        -e 's|download\.mynet\.club|ctl.mynet.club|g' \
        "$DUMP_FILE"

    rm -f "${DUMP_FILE}.bak"

    local changes
    changes=$(grep -c 'mynet\.club' "$DUMP_FILE" 2>/dev/null || echo "0")
    log_success "域名替换完成（$changes 处包含 mynet.club）"
}

# ════════════════════════════════════════════════════════════
# 导入数据到远程
# ════════════════════════════════════════════════════════════

import_to_remote() {
    if [ ! -f "$DUMP_FILE" ]; then
        log_error "找不到 ${DUMP_FILE}，请先运行 dump-local"
        exit 1
    fi

    log_info "上传并导入数据到远程..."

    # 上传
    scp "$DUMP_FILE" "$REMOTE_USER@$REMOTE_HOST:/tmp/mynet_ctl_import_${TIMESTAMP}.sql"

    # 导入
    # ⚠️ 口令不上命令行（CPI-43 的远端侧同病）：原来是 `mysql -u "$DB_USER" -p"$DB_PASS"`
    # ×2，口令经 argv 进 `ps`，生产主机任意用户可见。现在远端 mysql 一律走
    # --defaults-extra-file（0600 临时 cnf，用完即删）；口令只经**管道**喂给 sed 转义、
    # 不进任何进程 argv。脚本体用 `<< 'EOFIMPORT'`（不本地展开）+ 环境变量前缀传非敏感值。
    ssh "$REMOTE_USER@$REMOTE_HOST" "REMOTE_DIR=$REMOTE_DIR TIMESTAMP=$TIMESTAMP" bash -s << 'EOFIMPORT'
cd "$REMOTE_DIR"

# 从 .env 读取数据库信息
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2)
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2)
DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2) # GUARD-OK 运行时从 .env 读口令，非硬编码（A1 撞 grep 的取值行）

CNF=$(mktemp)
chmod 600 "$CNF"
{
    echo '[client]'
    printf 'user="%s"\n' "$DB_USER"
    printf 'password="%s"\n' "$(printf '%s' "$DB_PASS" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
} > "$CNF"

echo "导入到数据库: $DB_NAME"
mysql --defaults-extra-file="$CNF" "$DB_NAME" < "/tmp/mynet_ctl_import_${TIMESTAMP}.sql"
echo "✅ 数据导入完成"

# 清理临时文件
rm -f "/tmp/mynet_ctl_import_${TIMESTAMP}.sql"

# 统计行数
mysql --defaults-extra-file="$CNF" "$DB_NAME" -e "
SELECT 'releases' AS tbl, COUNT(*) AS cnt FROM releases
UNION ALL SELECT 'plugins', COUNT(*) FROM plugins
UNION ALL SELECT 'scripts', COUNT(*) FROM scripts
UNION ALL SELECT 'api_tokens', COUNT(*) FROM api_tokens
UNION ALL SELECT 'openwrt_devices', COUNT(*) FROM openwrt_devices
UNION ALL SELECT 'openwrt_device_firmware', COUNT(*) FROM openwrt_device_firmware;
"
rm -f "$CNF"
EOFIMPORT

    log_success "远程数据导入完成"
}

# ════════════════════════════════════════════════════════════
# 同步本地 storage 文件到远程
# ════════════════════════════════════════════════════════════

sync_storage_files() {
    log_info "同步 storage/app/ 文件到远程..."

    for dir in releases plugins scripts; do
        local local_dir="$PROJECT_ROOT/storage/app/$dir"
        if [ -d "$local_dir" ] && [ "$(ls -A "$local_dir" 2>/dev/null)" ]; then
            log_info "同步 storage/app/$dir ..."
            rsync -avz --progress \
                "$local_dir/" \
                "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/storage/app/$dir/"
        else
            log_warn "storage/app/$dir 为空，跳过"
        fi
    done

    # 修复权限
    ssh "$REMOTE_USER@$REMOTE_HOST" << EOF
chown -R www-data:www-data "$REMOTE_DIR/storage/app/"
chmod -R 775 "$REMOTE_DIR/storage/app/"
echo "✅ 文件权限已修复"
EOF

    log_success "文件同步完成"
}

# ════════════════════════════════════════════════════════════
# 完整流程
# ════════════════════════════════════════════════════════════

do_full() {
    echo
    echo -e "${BLUE}════════════════════════════════════════${NC}"
    echo -e "${BLUE}  MyNet CTL 数据库初始化 & 数据同步${NC}"
    echo -e "${BLUE}════════════════════════════════════════${NC}"
    echo

    create_remote_db
    run_remote_migrate
    dump_local_data
    fix_domains_in_dump
    import_to_remote
    sync_storage_files

    echo
    log_success "════════════════════════════════════════"
    log_success "  数据库初始化 & 同步完成"
    log_success "════════════════════════════════════════"
    echo
}

do_sync() {
    dump_local_data
    fix_domains_in_dump
    import_to_remote
    sync_storage_files

    echo
    log_success "数据同步完成"
}

# ════════════════════════════════════════════════════════════
# 入口
# ════════════════════════════════════════════════════════════

CMD="${1:-}"

case "$CMD" in
    create-db)
        create_remote_db
        ;;
    migrate)
        run_remote_migrate
        ;;
    sync)
        do_sync
        ;;
    dump-local)
        dump_local_data
        fix_domains_in_dump
        log_info "SQL 文件: $DUMP_FILE"
        ;;
    "")
        do_full
        ;;
    *)
        echo "用法: $0 [create-db|migrate|sync|dump-local]"
        echo
        echo "  (无参数)    完整流程：建库 + 迁移 + 同步"
        echo "  create-db   仅创建远程数据库和用户"
        echo "  migrate     仅运行远程迁移"
        echo "  sync        导出本地数据 + 域名替换 + 导入远程 + 同步文件"
        echo "  dump-local  仅导出本地数据到 SQL 文件（不上传）"
        exit 1
        ;;
esac
