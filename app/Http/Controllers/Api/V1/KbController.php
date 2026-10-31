<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * GET /d/kb/tips-kb.json          — tips/小助手知识库载荷（manifest + chunks）
 * GET /d/kb/tips-kb.json.sha256   — 同文校验和
 *
 * 与 /d/ 其它端点一致：无认证、可 CDN、脚本/清单类 no-cache。
 * 内容读蒸馏产物 scripts/tips-kb/tips-kb.json（build_tips_kb.py 生成；
 * 知识库 md 是源，手改产物不算源变更）。
 * 契约：docs/knowledge-base/spec/tips-kb-contract.md §3.2。
 */
class KbController extends Controller
{
    private const ARTIFACT = 'scripts/tips-kb/tips-kb.json';

    public function show(Request $request, string $name): Response
    {
        $wantChecksum = str_ends_with($name, '.sha256');
        $base = $wantChecksum ? substr($name, 0, -strlen('.sha256')) : $name;

        if ($base !== 'tips-kb.json') {
            abort(404, "unknown kb artifact: {$base}");
        }

        $path = base_path(self::ARTIFACT);
        if (! is_file($path)) {
            abort(404, 'kb artifact missing on server');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('unreadable kb artifact');
        }

        $sha = hash('sha256', $content);
        $version = $this->artifactVersion($content);

        if ($wantChecksum) {
            return response($sha.'  '.$base."\n", 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
                'X-KB-Version' => $version,
            ]);
        }

        // 条件拉取（spec §3.2）：ETag = 全文 sha256；If-None-Match 命中 → 304
        if ($this->ifNoneMatchHits($request, $sha)) {
            return response('', 304, [
                'ETag' => '"'.$sha.'"',
                'X-KB-Checksum' => $sha,
                'X-KB-Version' => $version,
            ]);
        }

        return response($content, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
            'X-KB-Checksum' => $sha,
            'X-KB-Version' => $version,
            'ETag' => '"'.$sha.'"',
        ]);
    }

    private function artifactVersion(string $content): string
    {
        $doc = json_decode($content, true);

        return is_array($doc) && isset($doc['version']) && is_string($doc['version'])
            ? $doc['version']
            : '';
    }

    private function ifNoneMatchHits(Request $request, string $sha): bool
    {
        $header = (string) $request->header('If-None-Match', '');
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }
            $candidate = trim($candidate, '"');
            if ($candidate === $sha || $candidate === '*') {
                return true;
            }
        }

        return false;
    }
}
