<?php

namespace Tests\Unit;

use App\Services\Manifest\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        // client and lua have no releases but are defined in PRODUCTS
        $this->assertArrayHasKey('client', $result['products']);
        $this->assertArrayHasKey('lua', $result['products']);
    }

    public function test_apps_index_tui_deprecated(): void
    {
        $result = $this->service->getAppsIndex();
        $mynet = $result['products']['mynet'];

        $this->assertTrue($mynet['deprecated']);
        $this->assertEquals('client', $mynet['successor']);
    }

    public function test_apps_index_client_plugins(): void
    {
        $result = $this->service->getAppsIndex();
        $client = $result['products']['client'];

        $this->assertArrayHasKey('plugins', $client);
        $this->assertCount(3, $client['plugins']);
        $this->assertArrayHasKey('heartbeat', $client['plugins']);
        $this->assertArrayHasKey('updater', $client['plugins']);
        $this->assertArrayHasKey('proxy', $client['plugins']);
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
