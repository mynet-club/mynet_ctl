<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Plugin\PluginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PluginController extends Controller
{
    public function __construct(private readonly PluginService $service) {}

    /**
     * GET /api/v1/plugins?platform=linux
     */
    public function index(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'linux');
        return response()->json(['data' => $this->service->listByPlatform($platform)]);
    }

    /**
     * POST /api/v1/plugins  (需要 plugin:upload 权限)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:64',
            'display_name' => 'nullable|string|max:128',
            'category'     => 'nullable|string',
            'version'      => 'required|string|max:32',
            'platform'     => 'required|string',
            'arch'         => 'nullable|string',
            'description'  => 'nullable|string',
            'dependencies' => 'nullable|array',
            'metadata'     => 'nullable|array',
            'file'         => 'required|file|max:51200', // 50MB
        ]);

        $plugin = $this->service->upload(
            array_merge($request->except('file'), [
                'uploaded_by' => $request->attributes->get('api_token')?->token_id,
            ]),
            $request->file('file')
        );

        return response()->json(['data' => $plugin], 201);
    }

    /**
     * GET /api/v1/plugins/{pluginId}/download/{filename}
     */
    public function download(Request $request, int $pluginId, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filePath = $this->service->download(
            $pluginId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->download(storage_path("app/{$filePath}"), $filename);
    }

    /**
     * DELETE /api/v1/plugins/{pluginId}
     */
    public function disable(int $pluginId): JsonResponse
    {
        $this->service->disable($pluginId);
        return response()->json(['message' => 'ok']);
    }
}
