<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * GET /d/docs/{name}          — 分发知识库公开文档（skill-kit 等）
 * GET /d/docs/{name}.sha256   — 同文校验和
 *
 * 与 /d/ 其它端点一致：无认证、可 CDN、脚本/清单类 no-cache。
 * 内容直接读知识库 SSOT（docs/knowledge-base/），避免 public/ 二次拷贝漂移。
 */
class DocsController extends Controller
{
    /**
     * 允许外发的文档：URL 名 → 知识库相对路径。
     * 只放 security_class=public 的文件；禁止目录遍历（路由已限文件名字符集）。
     */
    private const ALLOWLIST = [
        'skill-kit.md' => 'docs/knowledge-base/skill-kit.md',
    ];

    public function show(string $name): Response
    {
        $wantChecksum = str_ends_with($name, '.sha256');
        $base = $wantChecksum ? substr($name, 0, -strlen('.sha256')) : $name;

        $rel = self::ALLOWLIST[$base] ?? null;
        if ($rel === null) {
            abort(404, "unknown doc: {$base}");
        }

        $path = base_path($rel);
        if (! is_file($path)) {
            abort(404, "doc missing on server: {$base}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("unreadable doc: {$base}");
        }

        $sha = hash('sha256', $content);

        if ($wantChecksum) {
            return response($sha.'  '.$base."\n", 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
                'X-Doc-Version' => $this->frontmatterVersion($content),
            ]);
        }

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
            'X-Doc-Checksum' => $sha,
            'X-Doc-Version' => $this->frontmatterVersion($content),
            'Content-Disposition' => "inline; filename=\"{$base}\"",
        ]);
    }

    /** 解析 YAML frontmatter 的 version: 字段（缺省空串，不挡分发）。 */
    private function frontmatterVersion(string $content): string
    {
        if (preg_match('/^---\R(.*?)\R---\R/s', $content, $m) !== 1) {
            return '';
        }
        if (preg_match('/^version:\s*[\'"]?([^\s\'"#]+)[\'"]?/m', $m[1], $v) !== 1) {
            return '';
        }

        return $v[1];
    }
}
