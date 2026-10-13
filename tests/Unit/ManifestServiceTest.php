<?php

namespace Tests\Unit;

use App\Services\Manifest\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesCtlTables;
use Tests\TestCase;

class ManifestServiceTest extends TestCase
{
    use CreatesCtlTables;
    use RefreshDatabase;

    private ManifestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCtlTables();
        $this->seedTestReleases();
        $this->service = app(ManifestService::class);
    }

    // =========================================================================
    // getAppsIndex
    // =========================================================================

    public function test_apps_index_schema_version(): void
    {
        $result = $this->service->getAppsIndex();
        $this->assertEquals('2.1', $result['schema_version']);
    }

    public function test_apps_index_has_generated_at(): void
    {
        $result = $this->service->getAppsIndex();
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertNotEmpty($result['generated_at']);
    }

    public function test_apps_index_products_include_seeded(): void
    {
        $result = $this->service->getAppsIndex();
        $products = $result['products'];

        // Seeded products: gnb, tui(→mynet), utc, proxy
        $this->assertArrayHasKey('gnb', $products);
        $this->assertArrayHasKey('mynet', $products); // tui → mynet alias
        $this->assertArrayHasKey('utc', $products);
        $this->assertArrayHasKey('proxy', $products);
    }

    public function test_apps_index_includes_unreleased_products(): void
    {
        $result = $this->service->getAppsIndex();
        // lua 无 release 但仍在现役 PRODUCTS；client 已退役（C3-08），不得出现在产品面
        $this->assertArrayHasKey('lua', $result['products']);
        $this->assertArrayNotHasKey('client', $result['products']);
    }

    public function test_apps_index_tui_deprecated(): void
    {
        $result = $this->service->getAppsIndex();
        $mynet = $result['products']['mynet'];

        $this->assertTrue($mynet['deprecated']);
        // successor 必须真实：指向现役 mynet_agent，不得指向已冻结的 client
        $this->assertEquals('mynet_agent', $mynet['successor']);
    }

    public function test_apps_index_client_plugins_retired(): void
    {
        $result = $this->service->getAppsIndex();
        // C3-08：client / @mynet 插件面整面退役，新产品面不得再广告 plugins
        $this->assertArrayNotHasKey('client', $result['products']);
        foreach ($result['products'] as $key => $entry) {
            $this->assertArrayNotHasKey('plugins', $entry, "product {$key} 不得再带 plugins");
        }
    }

    public function test_apps_index_aliases(): void
    {
        $result = $this->service->getAppsIndex();
        $this->assertEquals(['tui' => 'mynet'], $result['aliases']);
    }

    public function test_apps_index_backward_compatible_apps(): void
    {
        $result = $this->service->getAppsIndex();
        $apps = $result['apps'];

        // Should contain manifest URLs for products with releases
        $this->assertNotEmpty($apps);
        foreach ($apps as $url) {
            $this->assertStringContainsString('/manifest.json', $url);
        }
    }

    public function test_apps_index_latest_is_version_aware_not_string_max(): void
    {
        // 字符串 MAX('v1.10.0','v1.9.0') = 'v1.9.0'；latest 必须版本感知
        foreach ([['v1.9.0', 'gnb_linux_armv7_v1.9.0.tgz'], ['v1.10.0', 'gnb_linux_armv7_v1.10.0.tgz']] as [$v, $fn]) {
            DB::table('releases')->insert([
                'release_id' => random_int(3000, 3999),
                'product' => 'gnb', 'version' => $v, 'platform' => 'linux', 'arch' => 'armv7',
                'variant' => '', 'filename' => $fn, 'file_path' => "releases/{$fn}",
                'file_size' => 100, 'sha256' => str_repeat('f', 64), 'download_url' => '',
                'is_stable' => 1, 'is_latest' => 0, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $result = $this->service->getAppsIndex();
        $this->assertSame('v1.10.0', $result['products']['gnb']['latest']);
    }

    // =========================================================================
    // getManifest
    // =========================================================================

    public function test_manifest_gnb_schema(): void
    {
        $result = $this->service->getManifest('gnb');

        $this->assertNotNull($result);
        $this->assertEquals('1.1', $result['schema_version']);
        $this->assertEquals('gnb', $result['project']);
    }

    public function test_manifest_gnb_latest_version(): void
    {
        $result = $this->service->getManifest('gnb');

        $this->assertEquals('v1.6.0', $result['latest_stable']);
        $this->assertEquals('v1.6.0', $result['latest']['stable']);
    }

    public function test_manifest_gnb_artifacts(): void
    {
        $result = $this->service->getManifest('gnb');
        $versions = $result['versions'];

        $this->assertArrayHasKey('v1.6.0', $versions);
        $artifacts = $versions['v1.6.0']['artifacts'];

        $this->assertCount(2, $artifacts);

        $arches = array_column($artifacts, 'arch');
        $this->assertContains('amd64', $arches);
        $this->assertContains('arm64', $arches);
    }

    public function test_manifest_mynet_alias_resolves_to_tui(): void
    {
        $result = $this->service->getManifest('mynet');

        $this->assertNotNull($result);
        $this->assertEquals('mynet', $result['project']); // external name
        $this->assertArrayHasKey('v1.4.0', $result['versions']);
    }

    public function test_manifest_unknown_product_returns_null(): void
    {
        $result = $this->service->getManifest('does_not_exist');
        $this->assertNull($result);
    }

    public function test_manifest_has_checksums_and_matrix(): void
    {
        $result = $this->service->getManifest('gnb');
        $version = $result['versions']['v1.6.0'];

        $this->assertArrayHasKey('checksums', $version);
        $this->assertArrayHasKey('matrix', $version);
        $this->assertArrayHasKey('channel', $version);
        $this->assertEquals('stable', $version['channel']);
    }

    // =========================================================================
    // getPlugins
    // =========================================================================

    public function test_plugins_client_returns_data(): void
    {
        $result = $this->service->getPlugins('client');

        $this->assertNotNull($result);
        $this->assertEquals('1.0', $result['schema_version']);
        $this->assertEquals('client', $result['product']);
    }

    public function test_plugins_client_has_three(): void
    {
        $result = $this->service->getPlugins('client');
        $this->assertCount(3, $result['plugins']);

        $names = array_column($result['plugins'], 'name');
        $this->assertContains('heartbeat', $names);
        $this->assertContains('updater', $names);
        $this->assertContains('proxy', $names);
    }

    public function test_plugins_non_client_returns_null(): void
    {
        $this->assertNull($this->service->getPlugins('gnb'));
        $this->assertNull($this->service->getPlugins('utc'));
    }

    public function test_plugins_structure(): void
    {
        $result = $this->service->getPlugins('client');

        foreach ($result['plugins'] as $plugin) {
            $this->assertArrayHasKey('name', $plugin);
            $this->assertArrayHasKey('full_name', $plugin);
            $this->assertStringStartsWith('@mynet/', $plugin['full_name']);
            $this->assertArrayHasKey('type', $plugin);
            $this->assertEquals('nodejs', $plugin['type']);
        }
    }
}
