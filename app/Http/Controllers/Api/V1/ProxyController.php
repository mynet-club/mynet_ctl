<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * ProxyController
 *
 * 代理插件（mynet_proxy）专用端点：
 *   GET /d/proxy/install.sh        → 在线安装脚本（curl | bash）
 *   GET /api/v2/proxy/latest       → 最新版本信息
 *   GET /api/v2/proxy/iplists      → 代理 IP 列表（chinaip + interip）
 *   GET /api/v2/proxy/iplists/meta → IP 列表元信息
 */
class ProxyController extends Controller
{
    /**
     * GET /api/v2/proxy/latest
     * 返回 proxy 插件的最新版本信息
     */
    public function latest(Request $request): JsonResponse
    {
        $platform = $request->query('platform');
        $arch = $request->query('arch');

        $query = Release::where('product', 'proxy')
            ->where('status', 1)
            ->where('is_stable', 1);

        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($arch) {
            $query->where('arch', $arch);
        }

        $release = $query->orderBy('created_at', 'desc')->first();

        if (! $release) {
            return response()->json(['message' => 'No proxy release found'], 404);
        }

        $displayName = (new ReleaseDomainService)->getProductFilenamePrefix($release->product);
        $dlFilename = (new ReleaseDomainService)->generateFilename($release->product, $release->platform, $release->arch, $release->version);
        $downloadUrl = rtrim(config('app.url'), '/')."/d/{$displayName}/{$dlFilename}";

        return response()->json([
            'data' => [
                'version' => $release->version,
                'platform' => $release->platform,
                'arch' => $release->arch,
                'filename' => $release->filename,
                'file_size' => $release->file_size,
                'sha256' => $release->sha256,
                'download_url' => $downloadUrl,
                'released_at' => $release->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v2/proxy/iplists
     * 返回 chinaip.txt + interip.txt 内容（供 client 一次性下载）
     */
    public function iplists(): JsonResponse
    {
        $dir = storage_path('app/public/proxy-lists');

        $chinaipFile = $dir.'/chinaip.txt';
        $interipFile = $dir.'/interip.txt';

        if (! file_exists($chinaipFile) || ! file_exists($interipFile)) {
            return response()->json(['message' => 'IP lists not generated yet. Run: php artisan proxy:update-iplists'], 404);
        }

        $metaFile = $dir.'/meta.json';
        $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : null;

        return response()->json([
            'chinaip' => file_get_contents($chinaipFile),
            'interip' => file_get_contents($interipFile),
            'meta' => $meta,
        ]);
    }

    /**
     * GET /plugins/mp/interip.txt
     * GET /plugins/mp/chinaip.txt
     * 以纯文本格式直接返回单个 IP 列表文件（供 proxy.sh refresh 的 curl -o 直接保存）
     * 文件由 scripts/proxy-lists/update_proxy_lists.sh 每日生成
     */
    public function iplistFile(string $name): Response
    {
        if (! in_array($name, ['interip.txt', 'chinaip.txt'], true)) {
            abort(404);
        }

        $file = storage_path('app/public/proxy-lists/'.$name);

        if (! file_exists($file)) {
            abort(404, 'IP list not generated yet. Run: php artisan proxy:update-iplists');
        }

        return response(file_get_contents($file), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * GET /api/v2/proxy/iplists/meta
     * 仅返回 IP 列表的元信息（行数、大小、SHA256、更新时间）
     */
    public function iplistsMeta(): JsonResponse
    {
        $metaFile = storage_path('app/public/proxy-lists/meta.json');

        if (! file_exists($metaFile)) {
            return response()->json(['message' => 'IP lists not generated yet'], 404);
        }

        return response()->json(json_decode(file_get_contents($metaFile), true));
    }

    /**
     * GET /d/proxy/install.sh
     * 生成平台感知的安装脚本，供 curl -fsSL ... | bash 使用
     */
    public function installScript(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');

        // 获取最新 stable proxy 版本号
        $latestVersion = Release::where('product', 'proxy')
            ->where('status', 1)
            ->where('is_stable', 1)
            ->orderBy('created_at', 'desc')
            ->value('version') ?? 'unknown';

        $script = $this->generateInstallScript($baseUrl, $latestVersion);

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Version' => $latestVersion,
        ]);
    }

    private function generateInstallScript(string $baseUrl, string $version): string
    {
        $script = <<<'BASH'
#!/bin/bash
#
# MyNet Proxy 插件在线安装脚本
# 用法: curl -fsSL BASE_URL/d/proxy/install.sh | bash
#
# 功能:
#   - 自动检测平台和架构
#   - 下载最新版 mynet_proxy 插件包
#   - 解压并安装到 mynet 目录
#
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_error()   { echo -e "${RED}[✗]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[!]${NC} $1"; }
log_step()    { echo -e "${CYAN}[→]${NC} $1"; }

CTL_BASE="REPLACE_BASE_URL"
VERSION="REPLACE_VERSION"

detect_platform() {
    local os
    os=$(uname -s | tr '[:upper:]' '[:lower:]')
    case "$os" in
        linux)
            if [ -f "/etc/openwrt_release" ]; then
                echo "openwrt"
            else
                echo "linux"
            fi
            ;;
        darwin) echo "darwin" ;;
        *) echo "unknown" ;;
    esac
}

detect_arch() {
    local arch
    arch=$(uname -m)
    case "$arch" in
        x86_64|amd64)   echo "amd64" ;;
        aarch64|arm64)  echo "arm64" ;;
        armv7l|armv7)   echo "armv7" ;;
        mips)           echo "mips" ;;
        mipsel)         echo "mipsel" ;;
        i386|i686)      echo "i386" ;;
        *)              echo "$arch" ;;
    esac
}

find_mynet_home() {
    local platform="$1"
    local paths_to_check=()

    case "$platform" in
        "openwrt") paths_to_check=("/etc/mynet") ;;
        "linux")   paths_to_check=("/usr/local/mynet" "/opt/mynet") ;;
        "darwin")  paths_to_check=("/usr/local/opt/mynet" "/usr/local/mynet" "/opt/mynet") ;;
    esac

    if [ -n "${MYNET_HOME:-}" ]; then
        paths_to_check=("$MYNET_HOME" "${paths_to_check[@]}")
    fi

    for path in "${paths_to_check[@]}"; do
        if [ -f "$path/conf/mynet.conf" ]; then
            echo "$path"
            return 0
        fi
    done
    return 1
}

http_get() {
    local url="$1" output="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$url" -o "$output"
    elif command -v wget >/dev/null 2>&1; then
        wget -q "$url" -O "$output"
    else
        log_error "未找到 curl 或 wget"
        exit 1
    fi
}

main() {
    echo ""
    echo "========================================="
    echo "  MyNet Proxy 插件在线安装"
    echo "  版本: $VERSION"
    echo "========================================="
    echo ""

    local platform arch mynet_home

    platform=$(detect_platform)
    arch=$(detect_arch)

    log_info "平台: $platform"
    log_info "架构: $arch"

    if [ "$platform" = "unknown" ]; then
        log_error "不支持的操作系统: $(uname -s)"
        exit 1
    fi

    # 查找 mynet 安装目录
    log_step "查找 mynet 安装目录..."
    mynet_home=$(find_mynet_home "$platform") || true

    if [ -z "$mynet_home" ]; then
        log_error "未找到 mynet 安装"
        log_info "请先安装 mynet 客户端: $CTL_BASE"
        exit 1
    fi

    log_success "找到 mynet: $mynet_home"

    # 查询最新版本
    log_step "查询最新版本..."
    local tmp_dir
    tmp_dir=$(mktemp -d)
    trap "rm -rf '$tmp_dir'" EXIT

    http_get "$CTL_BASE/api/v2/proxy/latest?platform=$platform&arch=$arch" "$tmp_dir/latest.json"

    # 提取下载信息（兼容无 jq 环境）
    local download_url filename
    if command -v jq >/dev/null 2>&1; then
        download_url=$(jq -r '.data.download_url' "$tmp_dir/latest.json")
        filename=$(jq -r '.data.filename' "$tmp_dir/latest.json")
        VERSION=$(jq -r '.data.version' "$tmp_dir/latest.json")
    else
        download_url=$(grep -o '"download_url":"[^"]*"' "$tmp_dir/latest.json" | head -1 | cut -d'"' -f4)
        filename=$(grep -o '"filename":"[^"]*"' "$tmp_dir/latest.json" | head -1 | cut -d'"' -f4)
        local v
        v=$(grep -o '"version":"[^"]*"' "$tmp_dir/latest.json" | head -1 | cut -d'"' -f4)
        [ -n "$v" ] && VERSION="$v"
    fi

    if [ -z "$download_url" ] || [ "$download_url" = "null" ]; then
        log_error "未找到适合 $platform/$arch 的版本"
        log_info "请检查 $CTL_BASE/d/proxy/manifest.json"
        exit 1
    fi

    log_info "版本: $VERSION"
    log_info "文件: $filename"

    # 下载
    log_step "下载插件包..."
    http_get "$download_url" "$tmp_dir/$filename"
    log_success "下载完成"

    # 解压
    log_step "解压安装..."
    cd "$tmp_dir"
    tar -xzf "$filename" 2>/dev/null || { log_error "解压失败"; exit 1; }

    local extract_dir="mynet_proxy"
    if [ ! -d "$extract_dir" ]; then
        # 尝试 proxy_ 开头的目录
        extract_dir=$(ls -d proxy_* 2>/dev/null | head -1)
    fi

    if [ -z "$extract_dir" ] || [ ! -d "$extract_dir" ]; then
        log_error "解压目录结构异常"
        exit 1
    fi

    # 安装文件
    log_step "安装到 $mynet_home ..."

    # 复制 binary
    if [ -d "$extract_dir/bin" ]; then
        mkdir -p "$mynet_home/bin"
        cp -f "$extract_dir/bin/"* "$mynet_home/bin/" 2>/dev/null || true
        chmod +x "$mynet_home/bin/"* 2>/dev/null || true
        log_success "已安装: bin/mynet_proxy"
    fi

    # 复制配置模板（不覆盖已有配置）
    if [ -d "$extract_dir/conf" ]; then
        if [ ! -d "$mynet_home/conf/proxy" ]; then
            mkdir -p "$mynet_home/conf"
            cp -rf "$extract_dir/conf/proxy" "$mynet_home/conf/"
            log_success "已安装: conf/proxy/"
        else
            # 只更新 proxy_sources（IP列表）
            if [ -d "$extract_dir/conf/proxy/proxy_sources" ]; then
                cp -rf "$extract_dir/conf/proxy/proxy_sources" "$mynet_home/conf/proxy/"
                log_success "已更新: conf/proxy/proxy_sources/"
            fi
            log_warning "配置目录已存在，跳过覆盖（仅更新 IP 源文件）"
        fi
    fi

    # 复制脚本
    if [ -d "$extract_dir/scripts" ]; then
        mkdir -p "$mynet_home/scripts"
        cp -rf "$extract_dir/scripts/"* "$mynet_home/scripts/" 2>/dev/null || true
        find "$mynet_home/scripts" -name "*.sh" -exec chmod +x {} \; 2>/dev/null || true
        log_success "已安装: scripts/"
    fi

    echo ""
    log_success "========================================="
    log_success "安装完成！"
    log_success "========================================="
    echo ""
    log_info "使用方法:"
    echo "  # 初始化配置（交互式）"
    echo "  $mynet_home/bin/mynet_proxy setup --role=client --peers=all"
    echo ""
    echo "  # 应用代理路由"
    echo "  $mynet_home/bin/mynet_proxy apply"
    echo ""
    echo "  # 查看状态"
    echo "  $mynet_home/bin/mynet_proxy status"
    echo ""
    echo "  # 更多帮助"
    echo "  $mynet_home/bin/mynet_proxy --help"
}

main "$@"
BASH;

        return str_replace(
            ['REPLACE_BASE_URL', 'REPLACE_VERSION'],
            [$baseUrl, $version],
            $script
        );
    }
}
