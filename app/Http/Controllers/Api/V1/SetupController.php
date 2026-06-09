<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Services\Setup\ConfigPackService;
use App\Services\Setup\MynetBackClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * SetupController
 *
 * 节点配置部署脚手架端点：
 *   GET  /api/v2/setup/platforms       — 平台/架构矩阵（CTL 领域知识）
 *   GET  /api/v2/setup/zones          — 获取用户 Zone 列表
 *   GET  /api/v2/setup/nodes          — 获取 Zone 内节点列表
 *   GET  /api/v2/setup/nodes/{id}     — 获取节点详情
 *   GET  /api/v2/setup/config-pack/{id} — 打包下载节点配置（可选含软件包）
 *
 * 登录不经过 CTL，用户从 my.mynet.club 获取 Token 后使用。
 * 所有端点（platforms 除外）需要请求头携带 mynet_back Sanctum Token：
 *   Authorization: Bearer {sanctum_token}
 */
class SetupController extends Controller
{
    public function __construct(
        private readonly MynetBackClient $backClient,
        private readonly ConfigPackService $packService,
    ) {}

    /**
     * GET /api/v2/setup/platforms
     *
     * 返回 CTL 支持的平台/架构矩阵（无需认证）
     */
    public function platforms(): JsonResponse
    {
        return response()->json([
            'platforms'    => array_keys(ReleaseDomainService::PLATFORMS),
            'combinations' => ReleaseDomainService::VALID_COMBINATIONS,
        ]);
    }

    /**
     * GET /api/v2/setup/zones
     */
    public function zones(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);
        try {
            return response()->json($this->backClient->listZones($token));
        } catch (RuntimeException $e) {
            return $this->handleBackendError($e);
        }
    }

    /**
     * POST /api/v2/setup/switch-zone
     *
     * 切换用户当前 Zone（代理 mynet_back）
     */
    public function switchZone(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => 'required|integer|min:1',
        ]);

        $token = $this->extractToken($request);
        try {
            return response()->json($this->backClient->switchZone($token, (int) $request->input('zone_id')));
        } catch (RuntimeException $e) {
            return $this->handleBackendError($e);
        }
    }

    /**
     * GET /api/v2/setup/nodes?zone_id=xxx
     */
    public function nodes(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);
        $zoneId = (int) $request->query('zone_id', 0);
        if ($zoneId <= 0) {
            return response()->json(['message' => '缺少 zone_id 参数'], 422);
        }

        try {
            $query = array_filter($request->only(['status', 'search', 'page', 'per_page']));
            return response()->json($this->backClient->listNodes($token, $zoneId, $query));
        } catch (RuntimeException $e) {
            return $this->handleBackendError($e);
        }
    }

    /**
     * GET /api/v2/setup/nodes/{nodeId}
     */
    public function nodeDetail(Request $request, int $nodeId): JsonResponse
    {
        $token = $this->extractToken($request);
        $zoneId = (int) $request->query('zone_id', 0);
        if ($zoneId <= 0) {
            return response()->json(['message' => '缺少 zone_id 参数'], 422);
        }

        try {
            return response()->json($this->backClient->getNodeDetail($token, $zoneId, $nodeId));
        } catch (RuntimeException $e) {
            return $this->handleBackendError($e);
        }
    }

    /**
     * GET /api/v2/setup/config-pack/{nodeId}
     *
     * 打包下载节点配置文件（ZIP）。
     * 默认仅含配置文件（平台无关）。
     * 传入 platform + arch 时，额外包含软件包下载脚本。
     *
     * Query: ?platform=linux&arch=amd64  （可选）
     */
    public function configPack(Request $request, int $nodeId): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $token = $this->extractToken($request);
        $zoneId = (int) $request->query('zone_id', 0);
        if ($zoneId <= 0) {
            return response()->json(['message' => '缺少 zone_id 参数'], 422);
        }

        $platform = $request->query('platform');
        $arch = $request->query('arch');

        // 如果提供了 platform，必须合法且配合 arch
        if ($platform) {
            $domainService = new ReleaseDomainService();
            if (!$domainService->validatePlatform($platform)) {
                return response()->json(['message' => '不支持的平台: ' . $platform], 422);
            }
            if (!$arch || !$domainService->validateCombination($platform, $arch)) {
                return response()->json(['message' => '不支持的平台/架构组合: ' . $platform . '/' . ($arch ?? 'null')], 422);
            }
        }

        try {
            $pack = $this->packService->generatePack($token, $nodeId, $platform, $arch, $zoneId);

            return response()->download(
                $pack['path'],
                $pack['filename'],
                ['Content-Type' => 'application/zip']
            )->deleteFileAfterSend(true);
        } catch (RuntimeException $e) {
            return $this->handleBackendError($e);
        }
    }

    // ─── 辅助方法 ─────────────────────────────────────────────────────────

    private function extractToken(Request $request): string
    {
        $token = $request->bearerToken();
        if (!$token) {
            abort(401, '请提供 Authorization: Bearer {token} 头');
        }
        return $token;
    }

    private function handleBackendError(RuntimeException $e): JsonResponse
    {
        $code = $e->getCode();

        $map = [
            401 => ['error_type' => 'auth_invalid',      'message' => 'Token 无效或已过期，请重新登录'],
            403 => ['error_type' => 'permission_denied',  'message' => '无权访问该资源'],
            404 => ['error_type' => 'not_found',          'message' => '节点不存在或不属于当前 Zone'],
            422 => ['error_type' => 'validation_error',   'message' => $e->getMessage()],
            503 => ['error_type' => 'backend_maintenance', 'message' => '后端服务维护中，请稍后重试'],
        ];

        if (isset($map[$code])) {
            return response()->json($map[$code], $code);
        }

        $errorType = $code >= 500 ? 'backend_error' : 'unknown_error';
        return response()->json([
            'error_type' => $errorType,
            'message'    => '后端服务暂时不可用: ' . $e->getMessage(),
        ], $code >= 500 ? 502 : $code);
    }
}
