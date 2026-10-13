<?php

namespace Tests\Feature;

use App\Domain\Release\Services\ReleaseDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesCtlTables;
use Tests\TestCase;

/**
 * 公开页面（首页 / 下载页）的产品面必须与 domain SSOT（ReleaseDomainService::PRODUCTS）同源。
 *
 * 为什么单独锁：PageController 曾硬编码 ['tui','gnb','utc']，而 admin 发布面早已改
 * SSOT 派生 —— 于是 mynet_agent/lua/proxy 产物「上传得了、页面看不到」（2026-10-13
 * 对账 C3 发现 9）。两边各自绿挡不住这种分叉，只有断言「页面 tab 集合 == PRODUCTS
 * 键集合」才挡得住（同 ReleaseFilenameTest::test_domain_whitelists_are_self_consistent 的思路）。
 */
class PageProductsTest extends TestCase
{
    use CreatesCtlTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 页面走 @vite；测试环境没有 public/build/manifest.json，用框架的假 Vite
        // 顶掉资产层（不影响被测的产品面渲染）。
        $this->withoutVite();
        $this->createCtlTables();
        $this->seedTestReleases();
    }

    public function test_downloads_page_tabs_match_domain_ssot(): void
    {
        $response = $this->get('/downloads');
        $response->assertOk();

        foreach (array_keys(ReleaseDomainService::PRODUCTS) as $key) {
            $response->assertSee('data-product="'.$key.'"', false); // 下载页缺产品 tab: {$key}
        }
    }

    public function test_downloads_page_renders_mynet_agent_releases(): void
    {
        // mynet_agent 曾因硬编码白名单「上传得了、页面看不到」——这条是那类回归的最小复现。
        $this->insertRelease('mynet_agent', 'mynet_agent_linux_amd64_v0.1.2.tgz', 'v0.1.2');

        $response = $this->get('/downloads');
        $response->assertOk();
        $response->assertSee('mynet_agent_linux_amd64_v0.1.2.tgz', false);
    }

    public function test_home_page_latest_releases_cover_ssot_products(): void
    {
        $this->insertRelease('mynet_agent', 'mynet_agent_linux_amd64_v0.1.2.tgz', 'v0.1.2');

        $response = $this->get('/');
        $response->assertOk();
        // 首页「最新版本」卡片：mynet_agent 的最新版要能看到
        $response->assertSee('v0.1.2');
    }

    public function test_downloads_page_unknown_product_falls_back_to_ssot_first(): void
    {
        $response = $this->get('/downloads?product=nonexistent-product');
        $response->assertOk();
        // 未知 product 参数应回落到 SSOT 首个产品，而不是硬编码白名单
        $response->assertSee('data-product="'.array_key_first(ReleaseDomainService::PRODUCTS).'"', false);
    }

    private function insertRelease(string $product, string $filename, string $version): void
    {
        DB::table('releases')->insert([
            'release_id' => 9001,
            'product' => $product,
            'version' => $version,
            'platform' => 'linux',
            'arch' => 'amd64',
            'variant' => '',
            'filename' => $filename,
            'file_path' => 'releases/'.$filename,
            'file_size' => 1024,
            'sha256' => str_repeat('f', 64),
            'download_url' => '',
            'is_stable' => 1,
            'is_latest' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
