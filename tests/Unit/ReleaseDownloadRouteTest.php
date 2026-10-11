<?php

namespace Tests\Unit;

use App\Domain\Release\Services\ReleaseDomainService;
use Tests\TestCase;

/**
 * 下载路由的 filename 约束必须与 parseFilename 认得的扩展名**一致**。
 *
 * 为什么单独锁：**路由层先于控制器**。约束漏了某个扩展名 → 请求在路由层就 404，
 * 而上传、`parseFilename`、库里的行**全都是对的** —— 两端各自的测试仍然全绿。
 * 2026-10-11 desktop 首次发布实测：H4 给 parseFilename 加了 exe/dmg/pkg，
 * `routes/web.php` 的 `filename` 约束没跟 → 安装器登记成功但下载面永久 404
 * （`/d/mynet_agent/*.dmg`、`*.exe`）。
 *
 * 对端锁：`ReleaseFilenameTest`（反解析契约）、`routes/web.php`。
 */
class ReleaseDownloadRouteTest extends TestCase
{
    public function test_route_filename_constraint_matches_parse_filename(): void
    {
        $parseExts = $this->extsAfterDotParen(
            (string) file_get_contents((new \ReflectionClass(ReleaseDomainService::class))->getFileName()),
            'parseFilename 的扩展名组'
        );

        $routeSrc = (string) file_get_contents(base_path('routes/web.php'));
        $line = null;
        foreach (explode("\n", $routeSrc) as $l) {
            if (str_contains($l, 'filename') && str_contains($l, 'ipk')) {
                $line = $l;
                break;
            }
        }
        $this->assertNotNull($line, 'routes/web.php 里没找到二进制下载路由的 filename 约束');
        $routeExts = $this->extsAfterDotParen($line, '路由 filename 约束的扩展名组');

        $this->assertSame(
            $parseExts,
            $routeExts,
            '路由 filename 约束与 parseFilename 的扩展名不一致 → 不在交集里的件会在路由层 404'
        );
    }

    /**
     * 取 `\.(...)` 里那串 `|` 分隔的扩展名，归一化（去转义）并排序。
     * 不用正则：源码里 `\.(` 只出现一次，定位更稳。
     */
    private function extsAfterDotParen(string $src, string $who): array
    {
        $lp = strpos($src, '\\.(');
        $this->assertNotFalse($lp, $who.'：没找到 `\\.(`');
        $rp = strpos($src, ')', $lp);
        $this->assertNotFalse($rp, $who.'：没找到收尾的 `)`');

        $group = substr($src, $lp + 3, $rp - $lp - 3);
        $exts = array_map(static fn ($e) => str_replace('\\', '', trim($e)), explode('|', $group));
        sort($exts);

        return $exts;
    }
}
