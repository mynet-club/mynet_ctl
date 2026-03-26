#!/bin/sh
# =============================================================================
# MyNet 升级脚本
# =============================================================================
# 用法:
#   curl -fsSL https://ctl.mynet.club/api/v1/scripts/.../raw/upgrade.sh | sh
#
# 环境变量:
#   CTL_BASE_URL    CTL 服务地址（默认 https://ctl.mynet.club）
#   INSTALL_DIR     安装目录（默认 /usr/local/bin）
#   MYNET_VERSION   指定版本（默认 latest stable）
# =============================================================================
set -e

CTL_BASE_URL="${CTL_BASE_URL:-https://ctl.mynet.club}"
INSTALL_DIR="${INSTALL_DIR:-/usr/local/bin}"
PRODUCT="mynet"
API_BASE="${CTL_BASE_URL}/api/v1"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()  { printf "${CYAN}[INFO]${NC}  %s\n" "$*"; }
ok()    { printf "${GREEN}[ OK ]${NC}  %s\n" "$*"; }
warn()  { printf "${YELLOW}[WARN]${NC}  %s\n" "$*"; }
fail()  { printf "${RED}[FAIL]${NC}  %s\n" "$*"; exit 1; }

# ── HTTP 客户端 ──────────────────────────────────────────────────────────────
http_get() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$1"
    elif command -v wget >/dev/null 2>&1; then
        wget -qO- "$1"
    else
        fail "需要 curl 或 wget"
    fi
}

http_download() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL -o "$2" "$1"
    elif command -v wget >/dev/null 2>&1; then
        wget -qO "$2" "$1"
    fi
}

# ── 检查当前安装 ────────────────────────────────────────────────────────────
check_current() {
    MYNET_BIN="${INSTALL_DIR}/mynet"
    if [ ! -x "$MYNET_BIN" ]; then
        fail "未找到 MyNet: ${MYNET_BIN}\n  请先运行安装脚本: curl -fsSL ${CTL_BASE_URL}/api/v1/scripts/.../raw/install.sh | sh"
    fi

    CURRENT_VERSION=$("$MYNET_BIN" --version 2>&1 | head -1 | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+' || echo "unknown")
    info "当前版本: ${CURRENT_VERSION}"
}

# ── 检测系统 ────────────────────────────────────────────────────────────────
detect_system() {
    OS_RAW=$(uname -s)
    case "$OS_RAW" in
        Linux*)  PLATFORM="linux" ;;
        Darwin*) PLATFORM="darwin" ;;
        *)       fail "不支持的操作系统: $OS_RAW" ;;
    esac

    if [ -f /etc/openwrt_release ]; then
        PLATFORM="openwrt"
    fi

    ARCH_RAW=$(uname -m)
    case "$ARCH_RAW" in
        x86_64|amd64)     CTL_ARCH="amd64" ;;
        aarch64|arm64)    CTL_ARCH="arm64" ;;
        armv7l|armv7)     CTL_ARCH="armv7" ;;
        armv6l)           CTL_ARCH="armv7" ;;
        i686|i386|i586)   CTL_ARCH="i386" ;;
        mips)             CTL_ARCH="mips" ;;
        mips64)           CTL_ARCH="mips64" ;;
        mipsel)           CTL_ARCH="mipsel" ;;
        mips64el)         CTL_ARCH="mips64el" ;;
        riscv64)          CTL_ARCH="riscv64" ;;
        *)                fail "不支持的架构: $ARCH_RAW" ;;
    esac

    # OpenWrt 精确检测
    if [ "$PLATFORM" = "openwrt" ] && [ -f /etc/openwrt_release ]; then
        DISTRIB_ARCH=$(. /etc/openwrt_release && echo "${DISTRIB_ARCH:-}")
        DISTRIB_TARGET=$(. /etc/openwrt_release && echo "${DISTRIB_TARGET:-}")
        if [ -n "$DISTRIB_ARCH" ]; then
            DETECT_RESULT=$(http_get "${API_BASE}/detect/openwrt?distrib_arch=${DISTRIB_ARCH}&distrib_target=${DISTRIB_TARGET}" 2>/dev/null || true)
            if [ -n "$DETECT_RESULT" ]; then
                DETECTED=$(printf '%s' "$DETECT_RESULT" | grep -o '"ctl_arch":"[^"]*"' | head -1 | cut -d'"' -f4)
                if [ -n "$DETECTED" ]; then
                    CTL_ARCH="$DETECTED"
                fi
            fi
        fi
    fi
}

# ── 检查新版本 ──────────────────────────────────────────────────────────────
check_latest() {
    info "查询最新版本 …"

    MANIFEST=$(http_get "${API_BASE}/${PRODUCT}/manifest") || fail "无法获取 manifest"

    LATEST_VERSION=$(printf '%s' "$MANIFEST" | grep -o '"latest_stable":"[^"]*"' | head -1 | cut -d'"' -f4)

    if [ -z "$LATEST_VERSION" ]; then
        fail "无法获取最新版本号"
    fi

    TARGET_VERSION="${MYNET_VERSION:-$LATEST_VERSION}"
    info "目标版本: ${TARGET_VERSION}"

    # 版本比较
    if [ "$CURRENT_VERSION" = "$TARGET_VERSION" ]; then
        ok "当前已是最新版本 (${CURRENT_VERSION})"
        exit 0
    fi

    info "升级: ${CURRENT_VERSION} → ${TARGET_VERSION}"

    # 查找下载 URL
    DOWNLOAD_URL=$(printf '%s' "$MANIFEST" | grep -o "\"url\":\"[^\"]*${PLATFORM}_${CTL_ARCH}[^\"]*\"" | head -1 | cut -d'"' -f4)

    if [ -z "$DOWNLOAD_URL" ]; then
        fail "未找到 ${TARGET_VERSION} 的 ${PLATFORM}/${CTL_ARCH} 构建包"
    fi

    FILENAME=$(basename "$DOWNLOAD_URL")
}

# ── 下载并替换 ──────────────────────────────────────────────────────────────
upgrade() {
    TMP_DIR=$(mktemp -d)
    TMP_FILE="${TMP_DIR}/${FILENAME}"

    info "下载 ${FILENAME} …"
    http_download "$DOWNLOAD_URL" "$TMP_FILE" || fail "下载失败"
    ok "下载完成"

    # 解压
    EXTRACT_DIR="${TMP_DIR}/extract"
    mkdir -p "$EXTRACT_DIR"

    case "$FILENAME" in
        *.tgz|*.tar.gz)
            tar xzf "$TMP_FILE" -C "$EXTRACT_DIR"
            ;;
        *.zip)
            unzip -q "$TMP_FILE" -d "$EXTRACT_DIR"
            ;;
        *)
            cp "$TMP_FILE" "${EXTRACT_DIR}/mynet"
            chmod +x "${EXTRACT_DIR}/mynet"
            ;;
    esac

    # 查找二进制
    BINARY=""
    for name in mynet mynet_tui tui; do
        found=$(find "$EXTRACT_DIR" -name "$name" -type f 2>/dev/null | head -1)
        if [ -n "$found" ]; then
            BINARY="$found"
            break
        fi
    done

    if [ -z "$BINARY" ]; then
        BINARY=$(find "$EXTRACT_DIR" -type f -perm +111 2>/dev/null | head -1)
    fi

    if [ -z "$BINARY" ]; then
        rm -rf "$TMP_DIR"
        fail "解压后未找到可执行文件"
    fi

    chmod +x "$BINARY"

    # 停止服务（如果在运行）
    if command -v systemctl >/dev/null 2>&1 && systemctl is-active mynet >/dev/null 2>&1; then
        info "停止 mynet 服务 …"
        sudo systemctl stop mynet 2>/dev/null || true
        SERVICE_WAS_RUNNING=1
    fi

    # 备份旧版本
    if [ -f "${MYNET_BIN}" ]; then
        cp "${MYNET_BIN}" "${MYNET_BIN}.bak" 2>/dev/null || sudo cp "${MYNET_BIN}" "${MYNET_BIN}.bak" 2>/dev/null || true
    fi

    # 替换
    cp "$BINARY" "${MYNET_BIN}" 2>/dev/null || sudo cp "$BINARY" "${MYNET_BIN}"
    chmod +x "${MYNET_BIN}"

    rm -rf "$TMP_DIR"

    # 重启服务
    if [ "${SERVICE_WAS_RUNNING:-0}" = "1" ]; then
        info "重启 mynet 服务 …"
        sudo systemctl start mynet 2>/dev/null || true
    fi

    NEW_VERSION=$("${MYNET_BIN}" --version 2>&1 | head -1 || echo "$TARGET_VERSION")
    ok "升级完成: ${NEW_VERSION}"
}

# ── 主流程 ───────────────────────────────────────────────────────────────────
main() {
    echo ""
    printf "${CYAN}╔═══════════════════════════════════════╗${NC}\n"
    printf "${CYAN}║     MyNet 升级脚本 v1.1.0            ║${NC}\n"
    printf "${CYAN}╚═══════════════════════════════════════╝${NC}\n"
    echo ""

    check_current
    detect_system
    info "系统: ${PLATFORM} / ${CTL_ARCH}"
    check_latest
    upgrade

    echo ""
    printf "${GREEN}  升级完成！${NC}\n"
    echo ""
}

main "$@"
