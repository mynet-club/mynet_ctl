<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Platform\Services\OpenWrtDetectService;
use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\OpenwrtDevice;
use App\Models\OpenwrtDeviceFirmware;
use App\Services\Release\ReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DetectController extends Controller
{
    public function __construct(
        private readonly OpenWrtDetectService $detector,
        private readonly ReleaseService $releaseService,
        private readonly ReleaseDomainService $releaseDomain,
    ) {}

    /**
     * GET /api/v2/detect/openwrt
     *
     * 根据 OpenWrt 系统信息推断 CTL 兼容架构，并返回可直接下载的最新版 URL。
     *
     * 参数：
     *   distrib_arch   必填  /etc/openwrt_release 中 DISTRIB_ARCH 的值（如 mipsel_24kc）
     *   distrib_target 可选  /etc/openwrt_release 中 DISTRIB_TARGET（如 ramips/mt7621）
     *   model          可选  设备人类可读名称（仅作回显，不影响检测）
     */
    public function openwrt(Request $request): JsonResponse
    {
        $deviceName = $request->input('device_name', '');

        // ── 模式一: device_name 搜索 → 返回候选列表或直接结果 ──────────────
        if ($deviceName !== '') {
            return $this->searchByDeviceName($deviceName);
        }

        // ── 模式二: distrib_arch + distrib_target 直接检测 ─────────────────
        $request->validate([
            'distrib_arch'   => ['required', 'string', 'max:64'],
            'distrib_target' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-\/\.]+$/i'],
            'model'          => ['nullable', 'string', 'max:128'],
        ]);

        $distribArch   = $request->input('distrib_arch', '');
        $distribTarget = $request->input('distrib_target', '');
        $model         = $request->input('model', '');

        $detected = $this->detector->detect($distribArch, $distribTarget);
        $arch     = $detected['ctl_arch'] ?? $detected['arch'] ?? 'amd64';

        $ctlBase = rtrim(config('app.url'), '/') . '/api/v2';
        $downloads = $this->buildDownloads($arch, $ctlBase);

        return response()->json([
            'ctl_arch'       => $arch,
            'soc'            => $detected['soc'] ?? null,
            'cpu'            => $detected['cpu'] ?? null,
            'libc'           => $detected['libc'] ?? null,
            'note'           => $detected['note'] ?? null,
            'confidence'     => $detected['confidence'] ?? 'unknown',
            'model'          => $model ?: null,
            'distrib_arch'   => $distribArch,
            'distrib_target' => $distribTarget ?: null,
            'downloads'      => $downloads,
        ]);
    }

    /**
     * 按设备名称搜索 openwrt_devices 表
     */
    private function searchByDeviceName(string $name): JsonResponse
    {
        // 搜索设备表的 brand/model 字段
        $devices = OpenwrtDevice::where(function ($q) use ($name) {
            $q->where('model', 'like', "%{$name}%")
              ->orWhere('brand', 'like', "%{$name}%");
        })
        ->with(['firmware' => function ($q) {
            $q->select('device_id', 'distrib_target', 'distrib_arch', 'ctl_arch', 'libc');
        }])
        ->limit(20)
        ->get();

        if ($devices->isEmpty()) {
            return response()->json(['error' => "未找到匹配 '{$name}' 的设备，请尝试更简短的关键词"], 404);
        }

        // 只有一个结果且有固件信息 → 直接返回检测结果
        if ($devices->count() === 1 && $devices->first()->firmware->isNotEmpty()) {
            $device   = $devices->first();
            $firmware = $device->firmware->first();
            $arch     = $firmware->ctl_arch;
            $ctlBase  = rtrim(config('app.url'), '/') . '/api/v2';
            return response()->json([
                'ctl_arch'       => $arch,
                'device_model'   => trim("{$device->brand} {$device->model} {$device->version}"),
                'soc'            => $device->soc_name ?: null,
                'cpu'            => null,
                'confidence'     => 'exact',
                'distrib_arch'   => $firmware->distrib_arch,
                'distrib_target' => $firmware->distrib_target,
                'downloads'      => $this->buildDownloads($arch, $ctlBase),
            ]);
        }

        // 多个结果 → 返回候选列表
        $candidates = $devices->flatMap(function ($device) {
            if ($device->firmware->isEmpty()) {
                return [[
                    'model'          => trim("{$device->brand} {$device->model} {$device->version}"),
                    'distrib_arch'   => null,
                    'distrib_target' => null,
                    'ctl_arch'       => null,
                ]];
            }
            return $device->firmware->map(fn($fw) => [
                'model'          => trim("{$device->brand} {$device->model} {$device->version}"),
                'distrib_arch'   => $fw->distrib_arch,
                'distrib_target' => $fw->distrib_target,
                'ctl_arch'       => $fw->ctl_arch,
            ]);
        })->unique('distrib_target')->values();

        return response()->json(['candidates' => $candidates]);
    }

    /**
     * GET /d/detect/openwrt.sh
     *
     * 返回一个 POSIX sh 脚本，用户在 OpenWrt 设备上执行：
     *   wget -qO- https://ctl.mynet.club/d/detect/openwrt.sh | sh
     *   或
     *   curl -fsSL https://ctl.mynet.club/d/detect/openwrt.sh | sh
     */
    public function openwrtScript(): Response
    {
$ctlBase = rtrim(config('app.url'), '/') . '/api/v2';
        $script  = $this->generateDetectScript($ctlBase);

        return response($script, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="detect-openwrt.sh"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    /**
     * GET /api/v2/detect/arch-map
     *
     * 返回完整的 DISTRIB_ARCH → CTL arch 映射表（供工具离线使用或 install.sh 参考）。
     */
    public function archMap(): JsonResponse
    {
        $archMap   = $this->detector->getArchMap();   // {distrib => ['arch' => ..., 'note' => ...]}
        $targetMap = $this->detector->getTargetMap();   // {target => ['soc' => ..., 'arch_hint' => ...]}

        // Flatten arch_map to simple string map for frontend: {distrib_arch => ctl_arch}
        $flatArchMap = array_map(fn($v) => $v['arch'], $archMap);

        // Build soc_examples: {distrib_arch => soc_string} using target_map hints
        $socExamples = [];
        foreach ($flatArchMap as $distrib => $ctl) {
            foreach ($targetMap as $target => $info) {
                if (($info['arch_hint'] ?? '') === $ctl && isset($info['soc'])) {
                    $socExamples[$distrib] = $info['soc'];
                    break;
                }
            }
        }

        return response()->json([
            'arch_map'    => $flatArchMap,
            'target_map'  => $targetMap,
            'soc_examples' => $socExamples,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 构建 downloads 对象：product → {latest_url, version, size}
     * 仅包含 openwrt 平台下有对应发布文件的 product
     */
    private function buildDownloads(string $arch, string $ctlBase): array
    {
        $productLabels = ['tui' => 'MyNet Client', 'gnb' => 'GNB', 'utc' => 'UTC'];
        $platform      = 'openwrt';
        $downloads     = [];

        foreach ($productLabels as $product => $label) {
            $release = $this->releaseService->getLatest($product, $platform, $arch);
            if (!$release) {
                // soft-float 变体回退
                $fallbackArch = $this->softfpFallback($arch);
                if ($fallbackArch) {
                    $release = $this->releaseService->getLatest($product, $platform, $fallbackArch);
                }
            }
            if ($release) {
                // 使用规范别名：tui→mynet，构建 /d/ 静态下载 URL
                $displayName = $this->releaseDomain->getProductFilenamePrefix($product);
                $ext = $this->releaseDomain->getDefaultExtension($platform);
                $dlFilename = "{$displayName}_{$platform}_{$release->arch}_{$release->version}.{$ext}";
                $url = rtrim(config('app.url'), '/') . "/d/{$displayName}/{$dlFilename}";

                $downloads[] = [
                    'product'       => $displayName,
                    'product_label' => $label,
                    'version'       => $release->version,
                    'url'           => $url,
                ];
            }
        }

        return $downloads;
    }

    /**
     * hard-float ↔ soft-float 互相回退
     * 覆盖 armv7/arm64/mips/mipsel 四组变体
     */
    private function softfpFallback(string $arch): string
    {
        return match ($arch) {
            'armv7'          => 'armv7-softfp',
            'armv7-softfp'   => 'armv7',
            'arm64'          => 'arm64-softfp',
            'arm64-softfp'   => 'arm64',
            'mips'           => 'mips-softfp',
            'mips-softfp'    => 'mips',
            'mipsel'         => 'mipsel-softfp',
            'mipsel-softfp'  => 'mipsel',
            default          => '',
        };
    }

    /**
     * 生成 POSIX sh 兼容的检测脚本（不依赖 bash）
     */
    private function generateDetectScript(string $ctlBase): string
    {
        $siteBase = rtrim(config('app.url'), '/');
        return <<<SH
#!/bin/sh
# detect-openwrt.sh — MyNet OpenWrt 硬件检测工具
# 用法:
#   wget -qO- {$siteBase}/d/detect/openwrt.sh | sh
#   curl -fsSL {$siteBase}/d/detect/openwrt.sh | sh
#
# 效果: 读取本机硬件信息 → 查询 CTL → 显示推荐下载 URL
#
# 要求: sh (POSIX), wget 或 curl, grep, cut（OpenWrt 标配）

CTL_BASE="{$ctlBase}"

# ── 颜色（TTY 时输出，重定向时自动关闭）─────────────────────────────────
if [ -t 1 ]; then
    BOLD='\\033[1m'; GREEN='\\033[0;32m'; BLUE='\\033[0;34m'
    YELLOW='\\033[1;33m'; RED='\\033[0;31m'; NC='\\033[0m'
else
    BOLD=''; GREEN=''; BLUE=''; YELLOW=''; RED=''; NC=''
fi

die() { printf "%s错误: %s%s\\n" "\$RED" "\$1" "\$NC" >&2; exit 1; }

# ── 检查 OpenWrt 环境 ────────────────────────────────────────────────────
[ -f /etc/openwrt_release ] || die "未检测到 OpenWrt 环境 (/etc/openwrt_release 不存在)"

# ── 读取系统信息 ─────────────────────────────────────────────────────────
_val() { grep "^\$1=" /etc/openwrt_release 2>/dev/null | head -1 | cut -d'=' -f2- | tr -d '"'"'" ; }

DISTRIB_ARCH=\$(_val DISTRIB_ARCH)
DISTRIB_TARGET=\$(_val DISTRIB_TARGET)
DISTRIB_RELEASE=\$(_val DISTRIB_RELEASE)
MODEL=\$(cat /tmp/sysinfo/model      2>/dev/null || echo "")
BOARD=\$(cat /tmp/sysinfo/board_name 2>/dev/null || echo "")

[ -n "\$DISTRIB_ARCH" ] || die "DISTRIB_ARCH 为空，无法继续"

printf "\\n%s=== MyNet OpenWrt 硬件检测 ===%s\\n\\n" "\$BOLD" "\$NC"
printf "%s设备信息%s\\n"           "\$BLUE" "\$NC"
printf "  型号       : %s\\n"    "\${MODEL:-未知}"
printf "  Board      : %s\\n"    "\${BOARD:-未知}"
printf "  OpenWrt    : %s\\n"    "\${DISTRIB_RELEASE:-未知}"
printf "  Target     : %s\\n"    "\${DISTRIB_TARGET:-未知}"
printf "  DISTRIB_ARCH: %s\\n"   "\$DISTRIB_ARCH"
printf "\\n"

# ── URL 编码（仅处理 / 和空格）────────────────────────────────────────────
_urlencode() {
    echo "\$1" | sed 's|/|%2F|g; s| |%20|g'
}

ENCODED_ARCH=\$(_urlencode "\$DISTRIB_ARCH")
ENCODED_TARGET=\$(_urlencode "\$DISTRIB_TARGET")
ENCODED_MODEL=\$(_urlencode "\$MODEL")

QUERY_URL="\${CTL_BASE}/detect/openwrt?distrib_arch=\${ENCODED_ARCH}&distrib_target=\${ENCODED_TARGET}&model=\${ENCODED_MODEL}"

# ── 查询 CTL ─────────────────────────────────────────────────────────────
if command -v curl >/dev/null 2>&1; then
    RESULT=\$(curl -fsS --max-time 10 "\$QUERY_URL" 2>/dev/null)
elif command -v wget >/dev/null 2>&1; then
    RESULT=\$(wget -qO- --timeout=10 "\$QUERY_URL" 2>/dev/null)
else
    die "未找到 curl 或 wget，无法查询 CTL"
fi

[ -n "\$RESULT" ] || die "查询 CTL 失败，请检查网络连接 (\$QUERY_URL)"

# ── 从 JSON 中提取字段（不依赖 jq）──────────────────────────────────────
_json() { echo "\$RESULT" | grep -o "\"\\$1\":\"[^\"]*\"" | head -1 | cut -d'"' -f4 ; }

ARCH=\$(_json arch)
SOC=\$(_json soc)
CPU=\$(_json cpu)
NOTE=\$(_json note)
CONF=\$(_json confidence)

TUI_URL=\$(echo "\$RESULT" | grep -o '"tui":{[^}]*}' | grep -o '"url":"[^"]*"' | cut -d'"' -f4)
TUI_VER=\$(echo "\$RESULT" | grep -o '"tui":{[^}]*}' | grep -o '"version":"[^"]*"' | cut -d'"' -f4)
GNB_URL=\$(echo "\$RESULT" | grep -o '"gnb":{[^}]*}' | grep -o '"url":"[^"]*"' | cut -d'"' -f4)
GNB_VER=\$(echo "\$RESULT" | grep -o '"gnb":{[^}]*}' | grep -o '"version":"[^"]*"' | cut -d'"' -f4)
UTC_URL=\$(echo "\$RESULT" | grep -o '"utc":{[^}]*}' | grep -o '"url":"[^"]*"' | cut -d'"' -f4)
UTC_VER=\$(echo "\$RESULT" | grep -o '"utc":{[^}]*}' | grep -o '"version":"[^"]*"' | cut -d'"' -f4)

# ── 显示结果 ─────────────────────────────────────────────────────────────
printf "%s检测结果%s\\n"                    "\$BLUE" "\$NC"
printf "  芯片 (SoC)  : %s\\n"             "\${SOC:-未知}"
printf "  CPU         : %s\\n"             "\${CPU:-未知}"
printf "  CTL 架构    : %s%s%s\\n"         "\$GREEN" "\${ARCH:-未知}" "\$NC"
printf "  说明        : %s\\n"             "\${NOTE}"
printf "  识别置信度  : %s\\n"             "\${CONF}"
printf "\\n"

if [ -z "\$TUI_URL" ] && [ -z "\$GNB_URL" ]; then
    printf "%s暂无 openwrt/%s 的预编译版本，请访问 %s 查看支持情况%s\\n" \\
        "\$YELLOW" "\${ARCH}" "\$CTL_BASE" "\$NC"
    exit 0
fi

printf "%s推荐下载%s\\n" "\$BLUE" "\$NC"

if [ -n "\$TUI_URL" ]; then
    printf "  mynet (tui) %s : %s\\n" "\${TUI_VER:+(}\${TUI_VER}\${TUI_VER:+)}" "\$TUI_URL"
fi
if [ -n "\$GNB_URL" ]; then
    printf "  gnb         %s : %s\\n" "\${GNB_VER:+(}\${GNB_VER}\${GNB_VER:+)}" "\$GNB_URL"
fi
if [ -n "\$UTC_URL" ]; then
    printf "  utc         %s : %s\\n" "\${UTC_VER:+(}\${UTC_VER}\${UTC_VER:+)}" "\$UTC_URL"
fi

printf "\\n%s下载示例 (wget):%s\\n" "\$YELLOW" "\$NC"
if [ -n "\$TUI_URL" ]; then
    printf "  wget -O /tmp/mynet.tar.gz '%s'\\n" "\$TUI_URL"
fi
printf "\\n"
SH;
    }
}
