<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesCtlTables;
use Tests\TestCase;

class ManifestEndpointTest extends TestCase
{
    use CreatesCtlTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCtlTables();
        $this->seedTestReleases();
    }

    // =========================================================================
    // apps.json
    // =========================================================================

    public function test_apps_json_returns_200(): void
    {
        $this->getJson('/d/apps.json')->assertOk();
    }

    public function test_apps_json_has_schema_v21(): void
    {
        $this->getJson('/d/apps.json')
            ->assertOk()
            ->assertJsonPath('schema_version', '2.1');
    }

    public function test_apps_json_has_products_section(): void
    {
        $response = $this->getJson('/d/apps.json');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('gnb', $data['products']);
        $this->assertArrayHasKey('utc', $data['products']);
    }

    public function test_apps_json_mynet_is_deprecated(): void
    {
        $this->getJson('/d/apps.json')
            ->assertOk()
            ->assertJsonPath('products.mynet.deprecated', true)
            ->assertJsonPath('products.mynet.successor', 'client');
    }

    public function test_apps_json_client_has_plugins(): void
    {
        $response = $this->getJson('/d/apps.json');
        $data = $response->json();

        $this->assertArrayHasKey('client', $data['products']);
        $this->assertArrayHasKey('plugins', $data['products']['client']);

        $pluginNames = array_keys($data['products']['client']['plugins']);
        $this->assertContains('heartbeat', $pluginNames);
        $this->assertContains('updater', $pluginNames);
        $this->assertContains('proxy', $pluginNames);
    }

    public function test_apps_json_has_aliases(): void
    {
        $this->getJson('/d/apps.json')
            ->assertOk()
            ->assertJsonPath('aliases.tui', 'mynet');
    }

    public function test_apps_json_has_backward_compatible_apps(): void
    {
        $response = $this->getJson('/d/apps.json');
        $data = $response->json();

        $this->assertArrayHasKey('apps', $data);
        // apps section has manifest URLs for products with releases
        $this->assertNotEmpty($data['apps']);
    }

    // =========================================================================
    // manifest.json
    // =========================================================================

    public function test_gnb_manifest_returns_200(): void
    {
        $this->getJson('/d/gnb/manifest.json')->assertOk();
    }

    public function test_gnb_manifest_schema_v11(): void
    {
        $this->getJson('/d/gnb/manifest.json')
            ->assertOk()
            ->assertJsonPath('schema_version', '1.1')
            ->assertJsonPath('project', 'gnb');
    }

    public function test_gnb_manifest_has_latest(): void
    {
        $response = $this->getJson('/d/gnb/manifest.json');
        $data = $response->json();

        $this->assertArrayHasKey('latest', $data);
        $this->assertArrayHasKey('stable', $data['latest']);
        $this->assertEquals('v1.6.0', $data['latest_stable']);
    }

    public function test_gnb_manifest_has_versions_with_artifacts(): void
    {
        $response = $this->getJson('/d/gnb/manifest.json');
        $data = $response->json();

        $this->assertArrayHasKey('versions', $data);
        $this->assertArrayHasKey('v1.6.0', $data['versions']);

        $version = $data['versions']['v1.6.0'];
        $this->assertArrayHasKey('artifacts', $version);
        $this->assertCount(2, $version['artifacts']); // linux/amd64 + linux/arm64

        $artifact = $version['artifacts'][0];
        $this->assertArrayHasKey('os', $artifact);
        $this->assertArrayHasKey('arch', $artifact);
        $this->assertArrayHasKey('file', $artifact);
        $this->assertArrayHasKey('url', $artifact);
        $this->assertArrayHasKey('sha256', $artifact);
    }

    public function test_mynet_manifest_alias_works(): void
    {
        // "mynet" is external alias for internal "tui"
        $response = $this->getJson('/d/mynet/manifest.json');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('versions', $data);
        $this->assertArrayHasKey('v1.4.0', $data['versions']);
    }

    public function test_manifest_unknown_product_returns_404(): void
    {
        $this->getJson('/d/nonexistent/manifest.json')
            ->assertStatus(404);
    }

    // =========================================================================
    // plugins.json
    // =========================================================================

    public function test_client_plugins_returns_200(): void
    {
        $this->getJson('/d/client/plugins.json')->assertOk();
    }

    public function test_client_plugins_has_three_plugins(): void
    {
        $response = $this->getJson('/d/client/plugins.json');
        $data = $response->json();

        $this->assertEquals('1.0', $data['schema_version']);
        $this->assertEquals('client', $data['product']);
        $this->assertCount(3, $data['plugins']);
    }

    public function test_client_plugins_have_correct_structure(): void
    {
        $response = $this->getJson('/d/client/plugins.json');
        $plugins = $response->json('plugins');

        foreach ($plugins as $plugin) {
            $this->assertArrayHasKey('name', $plugin);
            $this->assertArrayHasKey('full_name', $plugin);
            $this->assertArrayHasKey('description', $plugin);
            $this->assertArrayHasKey('type', $plugin);
            $this->assertArrayHasKey('required', $plugin);
        }
    }

    public function test_plugins_for_non_client_product_returns_404(): void
    {
        $this->getJson('/d/gnb/plugins.json')
            ->assertStatus(404);
    }

    // =========================================================================
    // /d/{product}/{filename} 下载：按 filename 精确取件
    // =========================================================================

    public function test_download_serves_exact_filename(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('releases/gnb_linux_amd64_v1.6.0.tgz', 'TARBALL-BYTES');

        $response = $this->get('/d/gnb/gnb_linux_amd64_v1.6.0.tgz');

        $response->assertOk();
        $this->assertStringContainsString(
            'gnb_linux_amd64_v1.6.0.tgz',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_download_rejects_same_tuple_wrong_filename(): void
    {
        // 同四元组但扩展名/文件名不同：不得把 tgz 的行套 .zip 文件名发出去
        $this->get('/d/gnb/gnb_linux_amd64_v1.6.0.zip')->assertNotFound();
    }

    // =========================================================================
    // conventions.json
    // =========================================================================

    public function test_conventions_returns_200(): void
    {
        $this->getJson('/d/conventions.json')->assertOk();
    }

    public function test_conventions_has_products_platforms_architectures(): void
    {
        $response = $this->getJson('/d/conventions.json');
        $data = $response->json();

        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('platforms', $data);
        $this->assertArrayHasKey('architectures', $data);
    }
}
