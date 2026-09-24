<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Services\Manifest\ManifestService;
use App\Services\Release\ReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ManifestController
 *
 * 提供与 download.mynet.club 格式兼容的分发索引，供 tui/mynet updating 逻辑使用。
 *
 * 端点：
 *   GET /d/apps.json                       → 产品索引（全量）
 *   GET /d/{product}/manifest.json          → 某产品的版本清单
 *   GET /d/conventions.json                 → 统一命名规范（平台/架构/产品矩阵）
 *
 *   以上两个 .json 端点已从 API 路由移至 /d/ 分发路径
 */
class ManifestController extends Controller
{
    public function __construct(
        private readonly ManifestService $service,
        private readonly ReleaseService $releaseService,
        private readonly ReleaseDomainService $domainService,
    ) {}

    /**
     * GET /d/apps.json
     *
     * 返回各产品的 manifest URL，供 tui 的 updater 查找下载地址。
     * 格式与 download.mynet.club/apps.json 保持兼容。
     */
    public function appsIndex(): JsonResponse
    {
        return response()->json($this->service->getAppsIndex());
    }

    /**
     * GET /d/{product}/manifest.json
     *
     * 返回指定产品的版本清单（schema v1.1）。
     * 支持产品名别名：mynet → tui（向后兼容旧 install.sh 和 tui 配置）。
     *
     * 若产品无任何 release，返回 404。
     */
    public function manifest(string $product): JsonResponse
    {
        $manifest = $this->service->getManifest($product);

        if ($manifest === null) {
            return response()->json(['message' => "No releases found for product: {$product}"], 404);
        }

        return response()->json($manifest);
    }

    /**
     * GET /d/conventions.json
     *
     * 统一命名规范：产品/平台/架构矩阵、文件命名模式、uname 映射。
     * 各项目（tui/gnb/utc/client...）的编译脚本和 workflow 可引用此端点对齐命名。
     */
    public function conventions(): JsonResponse
    {
        return response()->json($this->domainService->exportConventions());
    }

    /**
     * GET /d/{product}/plugins.json
     *
     * 返回指定产品可用的插件清单。目前仅 client 有 Node.js 插件。
     */
    public function plugins(string $product): JsonResponse
    {
        $plugins = $this->service->getPlugins($product);

        if ($plugins === null) {
            return response()->json(['message' => "No plugins available for product: {$product}"], 404);
        }

        return response()->json($plugins);
    }

    /**
     * GET /d/{product}/{filename}
     *
     * 静态风格的文件下载端点。从文件名解析 product/platform/arch/version，
     * 查找对应 release 并返回二进制文件。
     *
     * 示例: /d/mynet/mynet_openwrt_mipsel_v1.4.0.tgz
     */
    public function downloadRelease(Request $request, string $product, string $filename): BinaryFileResponse
    {
        $parsed = $this->domainService->parseFilename($filename);

        if (! $parsed) {
            abort(404, 'Invalid filename format');
        }

        $absolutePath = $this->releaseService->downloadByFilename(
            $parsed['product'],
            $parsed['platform'],
            $parsed['arch'],
            $parsed['version'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->download($absolutePath, $filename);
    }
}
