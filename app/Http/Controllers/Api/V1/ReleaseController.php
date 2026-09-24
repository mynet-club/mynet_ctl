<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Release\ReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReleaseController extends Controller
{
    public function __construct(private readonly ReleaseService $service) {}

    /**
     * GET /api/v2/releases
     * 查询所有产品的最新版本汇总
     */
    public function index(Request $request): JsonResponse
    {
        $product = $request->query('product');
        $data = $product
            ? $this->service->listByProduct($product)
            : collect(['tui', 'utc', 'gnb'])->flatMap(fn ($p) => $this->service->listByProduct($p))->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v2/releases/latest?product=tui&platform=linux&arch=amd64
     */
    public function latest(Request $request): JsonResponse
    {
        $request->validate([
            'product' => 'required|string',
            'platform' => 'required|string',
            'arch' => 'required|string',
            'variant' => 'nullable|string',
        ]);

        $release = $this->service->getLatest(
            $request->query('product'),
            $request->query('platform'),
            $request->query('arch'),
            $request->query('variant', '')
        );

        if (! $release) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $release]);
    }

    /**
     * POST /api/v2/releases  (需要 release:upload 权限)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product' => 'required|string',
            'version' => 'required|string',
            'platform' => 'required|string',
            'arch' => 'required|string',
            'variant' => 'nullable|string',
            'changelog' => 'nullable|string',
            'is_stable' => 'nullable|boolean',
            'file' => 'required|file|max:102400', // 100MB
        ]);

        $release = $this->service->upload(
            array_merge($request->except('file'), [
                'uploaded_by' => $request->attributes->get('api_token')?->token_id,
            ]),
            $request->file('file')
        );

        return response()->json(['data' => $release], 201);
    }

    /**
     * GET /api/v2/releases/{releaseId}/download/{filename}
     */
    public function download(Request $request, int $releaseId, string $filename): BinaryFileResponse
    {
        $absolutePath = $this->service->download(
            $releaseId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->download($absolutePath, $filename);
    }

    /**
     * DELETE /api/v2/releases/{releaseId}  (需要 release:manage 权限)
     */
    public function disable(int $releaseId): JsonResponse
    {
        $this->service->disable($releaseId);

        return response()->json(['message' => 'ok']);
    }
}
