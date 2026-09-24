<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Setup\QuickConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * QuickConfigController
 *
 * 快配端点 — 用户输入节点参数，服务端生成 Ed25519 密钥对和全部 GNB 配置：
 *   POST /api/v2/quickconfig/generate  — 批量生成配置包（ZIP）
 *   GET  /api/v2/quickconfig/node-id   — 生成随机节点 ID
 *
 * 无需 mynet_back 认证，完全离线。
 */
class QuickConfigController extends Controller
{
    public function __construct(
        private readonly QuickConfigService $quickConfigService,
    ) {}

    /**
     * GET /api/v2/quickconfig/node-id
     *
     * 返回一个随机合法的 GNB 节点 ID
     */
    public function nodeId(): JsonResponse
    {
        return response()->json([
            'node_id' => QuickConfigService::generateNodeId(),
        ]);
    }

    /**
     * POST /api/v2/quickconfig/generate
     *
     * 批量生成 GNB 配置包
     *
     * Body:
     *   {
     *     "nodes": [
     *       { "node_id": 1001001001001001, "name": "node1", "ip": "10.1.0.1", "port": 9001, "is_index": true, "public_ip": "1.2.3.4" },
     *       { "node_id": 1001001001001002, "name": "node2", "ip": "10.1.0.2", "port": 9001 }
     *     ],
     *     "subnet_mask": "255.255.255.0",
     *     "external_index": "5.6.7.8:9001"   // 可选
     *   }
     */
    public function generate(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'nodes' => 'required|array|min:1|max:50',
            'nodes.*.node_id' => 'required|integer|min:1000000000000000',
            'nodes.*.name' => 'required|string|max:64|regex:/^[a-zA-Z0-9_\-]+$/',
            'nodes.*.ip' => 'required|ip',
            'nodes.*.port' => 'required|integer|min:1024|max:65535',
            'nodes.*.is_index' => 'sometimes|boolean',
            'nodes.*.public_ip' => 'nullable|ip',
            'nodes.*.ifname' => 'nullable|string|max:15|regex:/^[a-zA-Z0-9_]+$/',
            'subnet_mask' => 'sometimes|string|in:255.255.255.0,255.255.0.0,255.0.0.0',
            'external_index' => ['nullable', 'string', 'regex:/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d{1,5})?$/'],
        ]);

        // 校验：必须存在至少一个索引节点或提供外部索引
        $hasIndex = collect($validated['nodes'])->contains(fn ($n) => ! empty($n['is_index']) && ! empty($n['public_ip']));
        if (! $hasIndex && empty($validated['external_index'])) {
            return response()->json([
                'message' => '至少需要一个节点设为索引（并填写公网 IP），或者提供外部索引地址',
            ], 422);
        }

        // 校验：节点 ID 不能重复
        $nodeIds = array_column($validated['nodes'], 'node_id');
        if (count($nodeIds) !== count(array_unique($nodeIds))) {
            return response()->json(['message' => '节点 ID 不能重复'], 422);
        }

        // 校验：IP 不能重复
        $ips = array_column($validated['nodes'], 'ip');
        if (count($ips) !== count(array_unique($ips))) {
            return response()->json(['message' => '节点 IP 不能重复'], 422);
        }

        try {
            $pack = $this->quickConfigService->generateBatch($validated);

            return response()->download(
                $pack['path'],
                $pack['filename'],
                ['Content-Type' => 'application/zip']
            )->deleteFileAfterSend(true);
        } catch (RuntimeException $e) {
            return response()->json(['message' => '生成失败: '.$e->getMessage()], 500);
        }
    }
}
