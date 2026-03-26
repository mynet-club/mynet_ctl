#!/bin/sh
# =============================================================================
# MyNet 一键安装脚本
# =============================================================================
# 用法:
#   curl -fsSL https://ctl.mynet.club/api/v1/scripts/.../raw/install.sh | sh
#   wget -qO- https://ctl.mynet.club/api/v1/scripts/.../raw/install.sh | sh
#
# 环境变量:
#   CTL_BASE_URL    CTL 服务地址（默认 https://ctl.mynet.club）
#   INSTALL_DIR     安装目录（默认 /usr/local/bin）
#   MYNET_VERSION   指定版本（默认 latest stable）
#   NO_SERVICE      设为 1 跳过 systemd 服务注册
#   NO_VERIFY       设为 1 跳过 SHA256 校验（不推荐）
# =============================================================================
set -e

# ── 配置 ────────────────────────────────────────────────────────────────────
CTL_BASE_URL="${CTL_BASE_URL:-https://ctl.mynet.club}"
INSTALL_DIR="${INSTALL_DIR:-/usr/local/bin}"
PRODUCT="mynet"
API_BASE="${CTL_BASE_URL}/api/v1"

# ── 颜色 ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()  { printf "${CYAN}[INFO]${NC}  %s\n" "$*"; }
ok()    { printf "${GREEN}[ OK ]${NC}  %s\n" "$*"; }
warn()  { printf "${YELLOW}[WARN]${NC}  %s\n" "$*"; }
fail()  { printf "${RED}[FAIL]${NC}  %s\n" "$*"; exit 1; }

# ── HTTP 客户端检测 ─────────────────────────────────────────────────────────
detect_http_client() {
    if command -v curl >/dev/null 2>&1; then
        HTTP_CLIENT="curl"
    elif command -v wget >/dev/null 2>&1; then
        HTTP_CLIENT="wget"
    else
        fail "需要 curl 或 wget，请先安装其中之一"
    fi
}

# 下载到 stdout
http_get() {
    case "$HTTP_CLIENT" in
        curl) curl -fsSL "$1" ;;
        wget) wget -qO- "$1" ;;
    esac
}

# 下载到文件
http_download() {
    case "$HTTP_CLIENT" in
        curl) curl -fsSL -o "$2" "$1" ;;
        wget) wget -qO "$2" "$1" ;;
    esac
}

# ── 操作系统检测 ────────────────────────────────────────────────────────────
detect_os() {
    OS_RAW=$(uname -s)
    case "$OS_RAW" in
        Linux*)  PLATFORM="linux" ;;
        Darwin*) PLATFORM="darwin" ;;
        *)       fail "不支持的操作系统: $OS_RAW" ;;
    esac

    # 检测 OpenWrt
    if [ -f /etc/openwrt_release ]; then
        PLATFORM="openwrt"
    fi
}

# ── 架构检测 ────────────────────────────────────────────────────────────────
detect_arch() {
    ARCH_RAW=$(uname -m)
    case "$ARCH_RAW" in
        x86_64|amd64)           CTL_ARCH="amd64" ;;
        aarch64|arm64)          CTL_ARCH="arm64" ;;
        armv7l|armv7)           CTL_ARCH="armv7" ;;
        armv6l)                 CTL_ARCH="armv7" ;;    # armv6 → armv7 兼容
        i686|i386|i586)         CTL_ARCH="i386" ;;
        mips)                   CTL_ARCH="mips" ;;
        mips64)                 CTL_ARCH="mips64" ;;
        mipsel)                 CTL_ARCH="mipsel" ;;
        mips64el)               CTL_ARCH="mips64el" ;;
        riscv64)                CTL_ARCH="riscv64" ;;
        *)                      fail "不支持的架构: $ARCH_RAW" ;;
    esac

    # OpenWrt: 通过 DISTRIB_ARCH 精确检测
    if [ "$PLATFORM" = "openwrt" ] && [ -f /etc/openwrt_release ]; then
        DISTRIB_ARCH=$(. /etc/openwrt_release && echo "${DISTRIB_ARCH:-}")
        DISTRIB_TARGET=$(. /etc/openwrt_release && echo "${DISTRIB_TARGET:-}")
        if [ -n "$DISTRIB_ARCH" ]; then
            info "OpenWrt DISTRIB_ARCH: $DISTRIB_ARCH"
            # 调用 CTL detect API 获取精确 arch 映射
            DETECT_RESULT=$(http_get "${API_BASE}/detect/openwrt?distrib_arch=${DISTRIB_ARCH}&distrib_target=${DISTRIB_TARGET}" 2>/dev/null || true)
            if [ -n "$DETECT_RESULT" ]; then
                DETECTED=$(printf '%s' "$DETECT_RESULT" | grep -o '"ctl_arch":"[^"]*"' | head -1 | cut -d'"' -f4)
                if [ -n "$DETECTED" ]; then
                    CTL_ARCH="$DETECTED"
                    info "CTL detect API 返回架构: $CTL_ARCH"
                fi
            fi
        fi
    fi
}

# ── 获取最新版本信息 ────────────────────────────────────────────────────────
fetch_latest_version() {
    info "查询最新版本 …"
    MANIFEST=$(http_get "${API_BASE}/${PRODUCT}/manifest") || fail "无法获取 manifest: ${API_BASE}/${PRODUCT}/manifest"

    if [ -n "$MYNET_VERSION" ]; then
        VERSION="$MYNET_VERSION"
        info "使用指定版本: $VERSION"
    else
        # 从 manifest 中提取 latest_stable 版本
        VERSION=$(printf '%s' "$MANIFEST" | grep -o '"latest_stable":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ -z "$VERSION" ]; then
            fail "无法从 manifest 中解析最新稳定版本"
        fi
        info "最新稳定版本: $VERSION"
    fi

    # 查找匹配的 artifact（os + arch + version）
    # 提取该 version 的 artifacts 列表中我们需要的 URL 和 sha256
    DOWNLOAD_URL=""
    EXPECTED_SHA256=""

    # 尝试从 releases/latest API 获取精确匹配
    LATEST_RESULT=$(http_get "${API_BASE}/releases/latest?product=tui&platform=${PLATFORM}&arch=${CTL_ARCH}&version=${VERSION}" 2>/dev/null || true)
    if [ -n "$LATEST_RESULT" ]; then
        DOWNLOAD_URL=$(printf '%s' "$LATEST_RESULT" | grep -o '"download_url":"[^"]*"' | head -1 | cut -d'"' -f4)
        EXPECTED_SHA256=$(printf '%s' "$LATEST_RESULT" | grep -o '"sha256":"[^"]*"' | head -1 | cut -d'"' -f4)
    fi

    # 回退: 从 manifest artifacts 中匹配
    if [ -z "$DOWNLOAD_URL" ]; then
        # 简化解析: 找到匹配 os + arch 的 artifact URL
        DOWNLOAD_URL=$(printf '%s' "$MANIFEST" | grep -o "\"url\":\"[^\"]*${PLATFORM}_${CTL_ARCH}[^\"]*\"" | head -1 | cut -d'"' -f4)
        EXPECTED_SHA256=$(printf '%s' "$MANIFEST" | grep -o "\"sha256\":\"[^\"]*\"" | head -1 | cut -d'"' -f4)
    fi

    if [ -z "$DOWNLOAD_URL" ]; then
        fail "未找到 ${PRODUCT} ${VERSION} 的 ${PLATFORM}/${CTL_ARCH} 构建包"
    fi

    FILENAME=$(basename "$DOWNLOAD_URL")
    ok "下载地址: $DOWNLOAD_URL"
}

# ── 下载 & 校验 ─────────────────────────────────────────────────────────────
download_and_verify() {
    TMP_DIR=$(mktemp -d)
    TMP_FILE="${TMP_DIR}/${FILENAME}"

    info "下载 ${FILENAME} …"
    http_download "$DOWNLOAD_URL" "$TMP_FILE" || fail "下载失败"
    FILE_SIZE=$(wc -c < "$TMP_FILE" | tr -d ' ')
    ok "下载完成 (${FILE_SIZE} bytes)"

    # SHA256 校验
    if [ "${NO_VERIFY:-0}" != "1" ] && [ -n "$EXPECTED_SHA256" ]; then
        info "校验 SHA256 …"
        if command -v sha256sum >/dev/null 2>&1; then
            ACTUAL_SHA256=$(sha256sum "$TMP_FILE" | awk '{print $1}')
        elif command -v shasum >/dev/null 2>&1; then
            ACTUAL_SHA256=$(shasum -a 256 "$TMP_FILE" | awk '{print $1}')
        else
            warn "未找到 sha256sum 或 shasum，跳过校验"
            ACTUAL_SHA256="$EXPECTED_SHA256"
        fi

        if [ "$ACTUAL_SHA256" != "$EXPECTED_SHA256" ]; then
            rm -rf "$TMP_DIR"
            fail "SHA256 校验失败!\n  期望: ${EXPECTED_SHA256}\n  实际: ${ACTUAL_SHA256}"
        fi
        ok "SHA256 校验通过"
    fi
}

# ── 安装 ────────────────────────────────────────────────────────────────────
install_binary() {
    info "安装到 ${INSTALL_DIR} …"

    # 确保安装目录存在
    if [ ! -d "$INSTALL_DIR" ]; then
        mkdir -p "$INSTALL_DIR" 2>/dev/null || {
            warn "无法创建 ${INSTALL_DIR}，尝试使用 sudo"
            sudo mkdir -p "$INSTALL_DIR"
        }
    fi

    # 解压 tgz 到临时目录
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
            # 单文件二进制
            cp "$TMP_FILE" "${EXTRACT_DIR}/mynet"
            chmod +x "${EXTRACT_DIR}/mynet"
            ;;
    esac

    # 查找主二进制文件
    BINARY=""
    for name in mynet mynet_tui tui; do
        found=$(find "$EXTRACT_DIR" -name "$name" -type f 2>/dev/null | head -1)
        if [ -n "$found" ]; then
            BINARY="$found"
            break
        fi
    done

    if [ -z "$BINARY" ]; then
        # 取解压出的第一个可执行文件
        BINARY=$(find "$EXTRACT_DIR" -type f -perm +111 2>/dev/null | head -1)
    fi

    if [ -z "$BINARY" ]; then
        rm -rf "$TMP_DIR"
        fail "解压后未找到可执行文件"
    fi

    chmod +x "$BINARY"

    # 复制到安装目录
    cp "$BINARY" "${INSTALL_DIR}/mynet" 2>/dev/null || {
        warn "需要 sudo 权限"
        sudo cp "$BINARY" "${INSTALL_DIR}/mynet"
    }
    chmod +x "${INSTALL_DIR}/mynet"

    # 清理临时文件
    rm -rf "$TMP_DIR"

    ok "已安装: ${INSTALL_DIR}/mynet"

    # 版本验证
    if "${INSTALL_DIR}/mynet" --version >/dev/null 2>&1; then
        INSTALLED_VERSION=$("${INSTALL_DIR}/mynet" --version 2>&1 | head -1)
        ok "版本: ${INSTALLED_VERSION}"
    fi
}

# ── systemd 服务注册 ────────────────────────────────────────────────────────
setup_systemd_service() {
    # 仅 Linux 且非 OpenWrt 时设置 systemd
    if [ "$PLATFORM" != "linux" ]; then
        return
    fi

    if [ "${NO_SERVICE:-0}" = "1" ]; then
        info "跳过 systemd 服务注册（NO_SERVICE=1）"
        return
    fi

    # 检查 systemd 是否可用
    if ! command -v systemctl >/dev/null 2>&1; then
        warn "未检测到 systemd，跳过服务注册"
        return
    fi

    info "注册 systemd 服务 …"
    SERVICE_FILE="/etc/systemd/system/mynet.service"

    SERVICE_CONTENT="[Unit]
Description=MyNet Client
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${INSTALL_DIR}/mynet daemon
Restart=on-failure
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target"

    printf '%s\n' "$SERVICE_CONTENT" | sudo tee "$SERVICE_FILE" >/dev/null 2>&1 || {
        warn "无法写入 systemd 服务文件，跳过"
        return
    }

    sudo systemctl daemon-reload 2>/dev/null || true
    sudo systemctl enable mynet 2>/dev/null || true
    ok "systemd 服务已注册并启用（mynet.service）"
    info "启动服务: sudo systemctl start mynet"
}

# ── 主流程 ───────────────────────────────────────────────────────────────────
main() {
    echo ""
    printf "${CYAN}╔═══════════════════════════════════════╗${NC}\n"
    printf "${CYAN}║     MyNet 一键安装脚本 v1.1.0        ║${NC}\n"
    printf "${CYAN}╚═══════════════════════════════════════╝${NC}\n"
    echo ""

    detect_http_client
    detect_os
    detect_arch

    info "系统: ${PLATFORM} / ${CTL_ARCH}"

    fetch_latest_version
    download_and_verify
    install_binary
    setup_systemd_service

    echo ""
    printf "${GREEN}══════════════════════════════════════════${NC}\n"
    printf "${GREEN}  安装完成！${NC}\n"
    printf "${GREEN}══════════════════════════════════════════${NC}\n"
    echo ""
    echo "  二进制位置: ${INSTALL_DIR}/mynet"
    echo ""
    echo "  快速开始:"
    echo "    mynet login           # 登录 MyNet 账号"
    echo "    mynet zone list       # 查看可用 Zone"
    echo "    mynet start           # 启动 GNB 连接"
    echo ""
    echo "  更多帮助: mynet --help"
    echo "  文档: https://www.mynet.club/docs/install"
    echo ""
}

main "$@"
