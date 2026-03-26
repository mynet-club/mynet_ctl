<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Script\ScriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ScriptController extends Controller
{
    public function __construct(private readonly ScriptService $service) {}

    /**
     * GET /api/v2/scripts?category=install&platform=linux
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category', 'install');
        $platform = $request->query('platform', 'linux');
        return response()->json(['data' => $this->service->listByCategory($category, $platform)]);
    }

    /**
     * POST /api/v2/scripts  (需要 script:publish 权限)
     * 直接上传脚本内容（JSON body）
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:64',
            'display_name'   => 'nullable|string|max:128',
            'category'       => 'nullable|string',
            'platform'       => 'nullable|string',
            'version'        => 'nullable|string|max:32',
            'target_version' => 'nullable|string|max:32',
            'description'    => 'nullable|string',
            'content'        => 'required|string',
            'lang'           => 'nullable|string|in:bash,sh,python',
            'is_standalone'  => 'nullable|boolean',
        ]);

        $script = $this->service->publish(
            array_merge($request->all(), [
                'uploaded_by' => $request->attributes->get('api_token')?->token_id,
            ])
        );

        return response()->json(['data' => $script], 201);
    }

    /**
     * PUT /api/v2/scripts/{scriptId}  更新脚本内容
     */
    public function update(Request $request, int $scriptId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'version' => 'nullable|string|max:32',
        ]);

        $script = $this->service->updateContent($scriptId, $request->input('content'), $request->input('version'));
        return response()->json(['data' => $script]);
    }

    /**
     * GET /api/v2/scripts/{scriptId}/raw/{name}.sh
     * 返回纯文本脚本内容，支持 curl | bash 用法
     *
     * 示例: curl -fsSL https://ctl.mynet.club/api/v2/scripts/123/raw/install.sh | bash
     */
    public function raw(Request $request, int $scriptId, string $name): Response
    {
        $data = $this->service->getContent(
            $scriptId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response($data['content'], 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'X-Script-Checksum'   => $data['checksum'],
            'X-Script-Lang'       => $data['lang'],
            'Content-Disposition' => "inline; filename=\"{$name}.sh\"",
        ]);
    }

    /**
     * DELETE /api/v2/scripts/{scriptId}
     */
    public function disable(int $scriptId): JsonResponse
    {
        $this->service->disable($scriptId);
        return response()->json(['message' => 'ok']);
    }

    /**
     * GET /d/scripts/{name}  例如 /d/scripts/install.sh
     *
     * 通用版脚本（target_version IS NULL）
     */
    public function rawByRoute(Request $request, string $name): Response
    {
        return $this->serveScript($request, $name, null);
    }

    /**
     * GET /d/scripts/{targetVersion}/{name}  例如 /d/scripts/v1.4.0/install.sh
     *
     * 版本匹配策略：有 target_version 匹配的用版本专属，fallback 到通用版
     */
    public function rawVersioned(Request $request, string $targetVersion, string $name): Response
    {
        return $this->serveScript($request, $name, $targetVersion);
    }

    private function serveScript(Request $request, string $name, ?string $targetVersion): Response
    {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $data = $this->service->getContentByName(
            $baseName,
            $targetVersion,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response($data['content'], 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'X-Script-Checksum'   => $data['checksum'],
            'X-Script-Lang'       => $data['lang'],
            'Content-Disposition' => "inline; filename=\"{$name}\"",
        ]);
    }
}
