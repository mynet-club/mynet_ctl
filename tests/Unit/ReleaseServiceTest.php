<?php

namespace Tests\Unit;

use App\Models\Release;
use App\Services\Manifest\ManifestService;
use App\Services\Release\ReleaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesCtlTables;
use Tests\TestCase;

/**
 * ReleaseService 发行面契约：
 *   1. kind 落库路径（upload → releases.kind → manifest 读回）
 *   2. /d/ 取件按 **filename 精确匹配**（不再按四元组 first() 猜）
 *
 * 为什么单独锁：
 *   - kind 是 2026-10-10 迁移加的列，upload→manifest 往返此前零测试
 *     （CPI-47 家族：测试态 schema 与迁移分叉时没人发现）；
 *   - 四元组 first() 取件会忽略 filename/ext/variant，同 tuple 多容器时
 *     把 A 文件套 B 文件名发给用户（C3 对账发现 2）。
 */
class ReleaseServiceTest extends TestCase
{
    use CreatesCtlTables;
    use RefreshDatabase;

    private ReleaseService $service;

    private ManifestService $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCtlTables();
        $this->service = app(ReleaseService::class);
        $this->manifest = app(ManifestService::class);
    }

    // =========================================================================
    // kind 落库（upload → manifest 往返）
    // =========================================================================

    public function test_upload_persists_kind_installer_for_ipk(): void
    {
        Storage::fake('local');
        $release = $this->upload('lua', 'openwrt', 'all', 'v1.0.0', 'lua_openwrt_all_v1.0.0.ipk');

        $this->assertSame('installer', $release->kind);
        $this->assertSame('lua_openwrt_all_v1.0.0.ipk', $release->filename);
    }

    public function test_upload_persists_kind_archive_for_tgz(): void
    {
        Storage::fake('local');
        $release = $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0.tgz');

        $this->assertSame('archive', $release->kind);
    }

    public function test_manifest_reads_back_kind_and_real_filename(): void
    {
        Storage::fake('local');
        $this->upload('lua', 'openwrt', 'all', 'v1.0.0', 'lua_openwrt_all_v1.0.0.ipk');

        $manifest = $this->manifest->getManifest('lua');
        $this->assertNotNull($manifest);
        $artifacts = $manifest['versions']['v1.0.0']['artifacts'];
        $this->assertCount(1, $artifacts);

        $a = $artifacts[0];
        $this->assertSame('lua_openwrt_all_v1.0.0.ipk', $a['file']);
        $this->assertSame('ipk', $a['ext']);
        $this->assertSame('installer', $a['kind']);
    }

    public function test_manifest_url_keeps_real_extension(): void
    {
        Storage::fake('local');
        $this->upload('lua', 'openwrt', 'all', 'v1.0.0', 'lua_openwrt_all_v1.0.0.ipk');

        $artifacts = $this->manifest->getManifest('lua')['versions']['v1.0.0']['artifacts'];

        // URL 必须以落库文件名收尾（含 .ipk）——用平台默认 ext 生成会指向不存在的 .tgz
        $this->assertStringEndsWith('/d/lua/lua_openwrt_all_v1.0.0.ipk', $artifacts[0]['url']);
    }

    // =========================================================================
    // /d/ 取件：按 filename 精确匹配
    // =========================================================================

    public function test_download_by_filename_rejects_wrong_ext_same_tuple(): void
    {
        Storage::fake('local');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0.tgz');

        // 同四元组、扩展名不同：不得把 tgz 的内容套 zip 文件名发出去
        $this->expectException(ModelNotFoundException::class);
        $this->service->downloadByFilename('mynet_agent_linux_amd64_v1.0.0.zip', '127.0.0.1', 'phpunit');
    }

    public function test_download_by_filename_picks_exact_row_among_same_tuple(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('releases/a.tgz', 'ARCHIVE-CONTENT');
        Storage::disk('local')->put('releases/b.ipk', 'IPK-CONTENT');

        // 同 (product,platform,arch,version) 两行、filename 不同 —— 四元组 first()
        // 会恒取第一行；按 filename 精确匹配才能各取各的
        foreach ([
            ['release_id' => 2001, 'variant' => '', 'filename' => 'gnb_linux_amd64_v2.0.0.tgz', 'file_path' => 'releases/a.tgz'],
            ['release_id' => 2002, 'variant' => 'x', 'filename' => 'gnb_linux_amd64_v2.0.0.ipk', 'file_path' => 'releases/b.ipk'],
        ] as $row) {
            DB::table('releases')->insert(array_merge($row, [
                'product' => 'gnb', 'version' => 'v2.0.0', 'platform' => 'linux', 'arch' => 'amd64',
                'kind' => str_ends_with($row['filename'], '.ipk') ? 'installer' : 'archive',
                'file_size' => 10, 'sha256' => str_repeat('a', 64), 'download_url' => '',
                'is_stable' => 1, 'is_latest' => 0, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        $path = $this->service->downloadByFilename('gnb_linux_amd64_v2.0.0.ipk', '127.0.0.1', 'phpunit');
        $this->assertSame('IPK-CONTENT', file_get_contents($path));

        $path = $this->service->downloadByFilename('gnb_linux_amd64_v2.0.0.tgz', '127.0.0.1', 'phpunit');
        $this->assertSame('ARCHIVE-CONTENT', file_get_contents($path));
    }

    // -------------------------------------------------------------------------

    // =========================================================================
    // G4-D：未知扩展 / 空 kind / 重复 filename 一律拒收（不得生成假 /d/ 链）
    // =========================================================================

    public function test_upload_rejects_unknown_extension(): void
    {
        Storage::fake('local');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未知的产物扩展名');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0.rar');
    }

    public function test_upload_rejects_version_suffix_mistaken_for_ext(): void
    {
        // `..._v1.0.0` 无真实扩展名时 pathinfo 会把「.0」当成 ext —— 同样必须拒收
        Storage::fake('local');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未知的产物扩展名');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0');
    }

    public function test_upload_rejects_truly_empty_extension(): void
    {
        Storage::fake('local');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('缺少扩展名');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'bare-artifact');
    }

    public function test_upload_rejects_duplicate_filename(): void
    {
        Storage::fake('local');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0.tgz');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('filename 已存在');
        $this->upload('mynet_agent', 'linux', 'amd64', 'v1.0.0', 'mynet_agent_linux_amd64_v1.0.0.tgz');
    }

    public function test_upload_rejects_retired_product_tui(): void
    {
        Storage::fake('local');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('无效的产品类型');
        $this->upload('tui', 'linux', 'amd64', 'v1.0.0', 'mynet_linux_amd64_v1.0.0.tgz');
    }

    // =========================================================================
    // D4-B 双件：同 tuple 可并存 installer + archive，is_latest 各记各的
    // =========================================================================

    public function test_same_tuple_accepts_installer_and_archive(): void
    {
        Storage::fake('local');
        $exe = $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.exe');
        $zip = $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.zip');

        $this->assertSame('installer', $exe->kind);
        $this->assertSame('archive', $zip->kind);
        $this->assertNotSame($exe->filename, $zip->filename);
    }

    public function test_get_latest_filters_by_kind(): void
    {
        Storage::fake('local');
        $this->upload('mynet_agent', 'darwin', 'arm64', 'v1.2.0', 'mynet_agent_darwin_arm64_v1.2.0.dmg');
        $this->upload('mynet_agent', 'darwin', 'arm64', 'v1.2.0', 'mynet_agent_darwin_arm64_v1.2.0.tgz');

        $installer = $this->service->getLatest('mynet_agent', 'darwin', 'arm64', '', 'installer');
        $archive = $this->service->getLatest('mynet_agent', 'darwin', 'arm64', '', 'archive');
        $any = $this->service->getLatest('mynet_agent', 'darwin', 'arm64');

        $this->assertSame('installer', $installer->kind);
        $this->assertSame('mynet_agent_darwin_arm64_v1.2.0.dmg', $installer->filename);
        $this->assertSame('archive', $archive->kind);
        $this->assertSame('mynet_agent_darwin_arm64_v1.2.0.tgz', $archive->filename);
        $this->assertNotNull($any);
    }

    public function test_is_latest_is_per_kind(): void
    {
        Storage::fake('local');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.exe');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.zip');

        $installer = $this->service->getLatest('mynet_agent', 'windows', 'amd64', '', 'installer');
        $archive = $this->service->getLatest('mynet_agent', 'windows', 'amd64', '', 'archive');
        $this->assertTrue((bool) $installer->is_latest);
        $this->assertTrue((bool) $archive->is_latest);
    }

    public function test_newer_version_marks_both_kinds_latest(): void
    {
        Storage::fake('local');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.exe');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.2.0', 'mynet_agent_windows_amd64_v1.2.0.zip');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.3.0', 'mynet_agent_windows_amd64_v1.3.0.exe');
        $this->upload('mynet_agent', 'windows', 'amd64', 'v1.3.0', 'mynet_agent_windows_amd64_v1.3.0.zip');

        $old = Release::where('version', 'v1.2.0')->get();
        foreach ($old as $row) {
            $this->assertFalse((bool) $row->is_latest, "v1.2.0 {$row->kind} 不应再是 latest");
        }
        $new = Release::where('version', 'v1.3.0')->get();
        foreach ($new as $row) {
            $this->assertTrue((bool) $row->is_latest, "v1.3.0 {$row->kind} 应是 latest");
        }
    }

    private function upload(string $product, string $platform, string $arch, string $version, string $filename)
    {
        $file = UploadedFile::fake()->create($filename, 10);

        return $this->service->upload([
            'product' => $product,
            'version' => $version,
            'platform' => $platform,
            'arch' => $arch,
            'is_stable' => true,
        ], $file);
    }
}
