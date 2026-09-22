<?php

namespace App\Services\Setup;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * mynet_back API 客户端
 *
 * 代理用户 Sanctum Token 向 mynet_back 请求节点配置数据。
 * CTL 不存储用户凭证，仅在请求期间持有 token。
 */
class MynetBackClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mynet_back.api_url'), '/');
        $this->timeout = config('services.mynet_back.timeout', 15);
    }

    // ─── Auth ───────────────────────────────────────────────────────────

    /**
     * 登录获取 Sanctum Token
     *
     * @return array{auth_token: string, ...}
     */
    public function login(string $email, string $password, string $deviceName = 'ctl-setup'): array
    {
        return $this->post('/api/v2/auth/login', [
            'email'       => $email,
            'password'    => $password,
            'device_name' => $deviceName,
        ]);
    }

    // ─── Zone ───────────────────────────────────────────────────────────

    public function listZones(string $token): array
    {
        return $this->get('/api/v2/user/zones', $token);
    }

    public function switchZone(string $token, int $zoneId): array
    {
        return $this->postAuth('/api/v2/user/switch-zone', $token, [
            'zone_id' => $zoneId,
        ]);
    }

    // ─── Node ───────────────────────────────────────────────────────────

    public function listNodes(string $token, int $zoneId, array $query = []): array
    {
        return $this->get('/api/v2/nodes', $token, $query, $zoneId);
    }

    public function getNodeDetail(string $token, int $zoneId, int $nodeId): array
    {
        return $this->get("/api/v2/nodes/{$nodeId}", $token, [], $zoneId);
    }

    /**
     * 获取节点配置（JSON 或 text）
     *
     * v2 端点为 /configuration，支持 section=vpn|network|all
     * render_conf=1 仅在 section=vpn 时生效
     *
     * @param bool $renderConf true 返回 conf 纯文本，false 返回 JSON
     */
    public function getNodeConfig(string $token, int $zoneId, int $nodeId, bool $renderConf = true): string|array
    {
        $query = [
            'section'     => 'vpn',
            'render_conf' => $renderConf ? '1' : '0',
        ];

        if ($renderConf) {
            return $this->getText("/api/v2/nodes/{$nodeId}/configuration", $token, $query, $zoneId);
        }

        return $this->get("/api/v2/nodes/{$nodeId}/configuration", $token, $query, $zoneId);
    }

    /**
     * 获取节点级索引服务列表（JSON only，不支持 render_conf）
     */
    public function getIndexServices(string $token, int $zoneId, int $nodeId): array
    {
        return $this->get("/api/v2/nodes/{$nodeId}/index-services", $token, [], $zoneId);
    }

    /**
     * 获取 Zone 级索引服务列表（address.conf）
     *
     * Zone 级端点 /zones/services/indexes 支持 render_conf=1 输出纯文本
     * 格式: i|0|{ip}|{port}
     */
    public function getZoneIndexServices(string $token, int $zoneId, bool $renderConf = true): string|array
    {
        $query = ['render_conf' => $renderConf ? '1' : '0'];

        if ($renderConf) {
            return $this->getText('/api/v2/zones/services/indexes', $token, $query, $zoneId);
        }

        return $this->get('/api/v2/zones/services/indexes', $token, $query, $zoneId);
    }

    /**
     * 获取路由配置（route.conf）
     */
    public function getRouteConfig(string $token, int $zoneId, int $nodeId, bool $renderConf = true): string|array
    {
        $query = ['render_conf' => $renderConf ? '1' : '0'];

        if ($renderConf) {
            return $this->getText("/api/v2/route/node/{$nodeId}", $token, $query, $zoneId);
        }

        return $this->get("/api/v2/route/node/{$nodeId}", $token, $query, $zoneId);
    }

    /**
     * 获取节点密钥
     */
    public function getNodeKeys(string $token, int $zoneId, int $nodeId): array
    {
        return $this->get("/api/v2/nodes/{$nodeId}/keys", $token, [], $zoneId);
    }

    /**
     * 获取路由节点公钥（批量）
     */
    public function getRouterKeys(string $token, int $zoneId, int $nodeId, array $routerNodeIds): array
    {
        return $this->postAuth("/api/v2/nodes/{$nodeId}/router-keys", $token, [
            'router_node_ids' => $routerNodeIds,
        ], $zoneId);
    }

    /**
     * 一次性获取节点配置包（config-bundle）
     *
     * 返回: { node_id, zone_id, version, files: { node.conf, address.conf, route.conf }, keys: { ed25519: {}, security: {} } }
     */
    public function getConfigBundle(string $token, int $zoneId, int $nodeId): array
    {
        return $this->get("/api/v2/nodes/{$nodeId}/config-bundle", $token, ['zone_id' => $zoneId], $zoneId);
    }

    /**
     * BundleEnvelope schema v2（R8）。GET /nodes/{id}/bundle 返回**裸 Envelope**。
     */
    public function getBundleEnvelope(string $token, int $zoneId, int $nodeId): array
    {
        $request = Http::timeout($this->timeout)->withToken($token)->acceptJson();
        if ($zoneId) {
            $request = $request->withHeaders(['X-Zone-ID' => $zoneId]);
        }
        $response = $request->get($this->baseUrl . "/api/v1/nodes/{$nodeId}/bundle");
        if ($response->failed()) {
            $this->throwFromResponse($response, "/api/v1/nodes/{$nodeId}/bundle");
        }
        $json = $response->json();
        return is_array($json) ? $json : [];
    }

    // ─── HTTP 基础方法 ───────────────────────────────────────────────────

    private function get(string $path, string $token, array $query = [], ?int $zoneId = null): array
    {
        $request = Http::timeout($this->timeout)
            ->withToken($token)
            ->acceptJson();

        if ($zoneId) {
            $request = $request->withHeaders(['X-Zone-ID' => $zoneId]);
        }

        $response = $request->get($this->baseUrl . $path, $query);

        if ($response->failed()) {
            $this->throwFromResponse($response, $path);
        }

        return $response->json();
    }

    private function getText(string $path, string $token, array $query = [], ?int $zoneId = null): string
    {
        $request = Http::timeout($this->timeout)
            ->withToken($token)
            ->accept('text/plain');

        if ($zoneId) {
            $request = $request->withHeaders(['X-Zone-ID' => $zoneId]);
        }

        $response = $request->get($this->baseUrl . $path, $query);

        if ($response->failed()) {
            $this->throwFromResponse($response, $path);
        }

        return $response->body();
    }

    private function post(string $path, array $data): array
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post($this->baseUrl . $path, $data);

        if ($response->failed()) {
            $this->throwFromResponse($response, $path);
        }

        return $response->json();
    }

    private function postAuth(string $path, string $token, array $data, ?int $zoneId = null): array
    {
        $request = Http::timeout($this->timeout)
            ->withToken($token)
            ->acceptJson();

        if ($zoneId) {
            $request = $request->withHeaders(['X-Zone-ID' => $zoneId]);
        }

        $response = $request->post($this->baseUrl . $path, $data);

        if ($response->failed()) {
            $this->throwFromResponse($response, $path);
        }

        return $response->json();
    }

    private function throwFromResponse($response, string $path): never
    {
        $status = $response->status();
        $body   = $response->json();
        $msg    = $body['message'] ?? $body['error'] ?? "请求失败: {$path}";

        throw new RuntimeException("[mynet_back {$status}] {$msg}", $status);
    }
}
