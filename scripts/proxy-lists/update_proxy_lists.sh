#!/bin/bash
#######################################
# 每日自动更新 GeoIP 代理列表
# 部署位置: mynet_ctl/scripts/proxy-lists/
# 输出目录: storage/app/public/proxy-lists/
#
# 用法:
#   bash update_proxy_lists.sh [--output-dir /path/to/output]
#
# 默认输出: $SCRIPT_DIR/../../storage/app/public/proxy-lists/
#######################################

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DEFAULT_OUTPUT_DIR="$(cd "$SCRIPT_DIR/../../storage/app/public/proxy-lists" 2>/dev/null && pwd || echo "$SCRIPT_DIR/../../storage/app/public/proxy-lists")"
OUTPUT_DIR="$DEFAULT_OUTPUT_DIR"
TEMP_DIR="/tmp/geoip_update_$$"

# URL配置 - v2fly 版本（纯地理数据，适合低内存设备）
GEOIP_URL="https://github.com/v2fly/geoip/releases/latest/download/geoip.dat"

# 解析参数
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --output-dir) OUTPUT_DIR="$2"; shift ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
    shift
done

# 日志函数
log_info() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [INFO] $*"
}

log_error() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [ERROR] $*" >&2
}

# 清理函数
cleanup() {
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
        log_info "清理临时目录: $TEMP_DIR"
    fi
}

trap cleanup EXIT

# 主流程
main() {
    log_info "=========================================="
    log_info "开始代理列表每日更新"
    log_info "=========================================="

    # 确保输出目录存在
    mkdir -p "$OUTPUT_DIR"
    mkdir -p "$TEMP_DIR"
    log_info "输出目录: $OUTPUT_DIR"
    log_info "临时目录: $TEMP_DIR"

    # 步骤1: 下载 geoip.dat
    log_info "步骤 1/3: 下载 geoip.dat..."
    cd "$TEMP_DIR"

    if wget -q --timeout=60 --tries=3 "$GEOIP_URL"; then
        log_info "✓ 下载成功: $(du -h geoip.dat | cut -f1)"
    else
        log_error "✗ 下载失败"
        exit 1
    fi

    # 步骤2: 复制自定义文件到临时目录
    log_info "步骤 2/3: 准备自定义文件..."
    if [ -f "$SCRIPT_DIR/custom_chinaip.txt" ]; then
        cp "$SCRIPT_DIR/custom_chinaip.txt" "$TEMP_DIR/"
        log_info "✓ 已加载 custom_chinaip.txt"
    fi
    if [ -f "$SCRIPT_DIR/custom_interip.txt" ]; then
        cp "$SCRIPT_DIR/custom_interip.txt" "$TEMP_DIR/"
        log_info "✓ 已加载 custom_interip.txt"
    fi

    # 步骤3: 生成代理列表
    log_info "步骤 3/3: 生成代理列表..."
    if python3 "$SCRIPT_DIR/generate_proxy_lists.py" "$TEMP_DIR/geoip.dat" "$TEMP_DIR"; then
        log_info "✓ 处理成功"
    else
        log_error "✗ 处理失败"
        exit 1
    fi

    # 部署到输出目录
    log_info "部署文件到 $OUTPUT_DIR ..."
    cp "$TEMP_DIR/chinaip.txt" "$OUTPUT_DIR/"
    cp "$TEMP_DIR/interip.txt" "$OUTPUT_DIR/"
    chmod 644 "$OUTPUT_DIR/chinaip.txt" "$OUTPUT_DIR/interip.txt"

    # DNS 上游与域名清单（手工维护的模板，存在即发布）
    # D2：dns-domestic / dns-clean / proxy-domains / direct-domains
    for f in dns-domestic.txt dns-clean.txt proxy-domains.txt direct-domains.txt; do
        if [ -f "$SCRIPT_DIR/$f" ]; then
            cp "$SCRIPT_DIR/$f" "$OUTPUT_DIR/"
            chmod 644 "$OUTPUT_DIR/$f"
            log_info "✓ 已发布 $f"
        else
            log_info "  跳过 $f（无源文件）"
        fi
    done

    # 生成 meta.json（供 API / 节点校验）
    list_meta() {
        local f="$1" key="$2"
        local path="$OUTPUT_DIR/$f"
        if [ ! -f "$path" ]; then
            echo "    \"$key\": null"
            return
        fi
        local cnt sz sum
        cnt=$(grep -cvE '^\s*(#|$)' "$path" 2>/dev/null || echo 0)
        sz=$(stat -c%s "$path" 2>/dev/null || stat -f%z "$path")
        sum=$(sha256sum "$path" 2>/dev/null | cut -d' ' -f1 || shasum -a 256 "$path" | cut -d' ' -f1)
        echo "    \"$key\": {\"count\": $cnt, \"size\": $sz, \"sha256\": \"$sum\"}"
    }

    china_count=$(grep -cvE '^\s*(#|$)' "$OUTPUT_DIR/chinaip.txt")
    inter_count=$(grep -cvE '^\s*(#|$)' "$OUTPUT_DIR/interip.txt")

    cat > "$OUTPUT_DIR/meta.json" <<EOF
{
    "updated_at": "$(date -u +'%Y-%m-%dT%H:%M:%SZ')",
$(list_meta chinaip.txt chinaip),
$(list_meta interip.txt interip),
$(list_meta dns-domestic.txt dns_domestic),
$(list_meta dns-clean.txt dns_clean),
$(list_meta proxy-domains.txt proxy_domains),
$(list_meta direct-domains.txt direct_domains)
}
EOF

    log_info "=========================================="
    log_info "✅ 更新完成"
    log_info "=========================================="
    log_info "chinaip.txt: $china_count 条"
    log_info "interip.txt: $inter_count 条"
    for f in dns-domestic.txt dns-clean.txt proxy-domains.txt direct-domains.txt; do
        [ -f "$OUTPUT_DIR/$f" ] && log_info "$f: $(grep -cvE '^\s*(#|$)' "$OUTPUT_DIR/$f") 条"
    done
    log_info "meta.json: 已生成（含 sha256）"
}

main "$@"
