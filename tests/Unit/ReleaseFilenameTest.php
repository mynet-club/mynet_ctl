<?php

namespace Tests\Unit;

use App\Domain\Release\Services\ReleaseDomainService;
use Tests\TestCase;

/**
 * 产物文件名的生成与反解析契约。
 *
 * 为什么值得单独锁：这是 **agent ↔ ctl 的跨仓约定**。agent 侧 `make dist`
 * 按 `{prefix}_{platform}_{arch}_{version}.{ext}` 命名产物并上传，
 * ctl 侧 `parseFilename` 反解析出 product/platform/arch/version 再查库。
 * 任一侧漂了就表现为**下载 404**，且两端各自的测试都还是绿的。
 *
 * 对端锁：mynet_agent 的 Makefile 注释与 scripts/pack.sh。
 */
class ReleaseFilenameTest extends TestCase
{
    private ReleaseDomainService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ReleaseDomainService;
    }

    public function test_generates_agent_filename_with_underscore_prefix(): void
    {
        // 产物前缀的 SSOT 是 mynet_agent_*（agent 侧 guard C3 强制）
        $this->assertSame(
            'mynet_agent_linux_amd64_v1.0.0.tgz',
            $this->svc->generateFilename('mynet_agent', 'linux', 'amd64', 'v1.0.0')
        );
    }

    /**
     * 回归：name 段原先的 `[a-z0-9]+` 不允许下划线，
     * mynet_agent_* 会被解析成 null → 下载 404。
     */
    public function test_parses_agent_filename_with_underscore(): void
    {
        $got = $this->svc->parseFilename('mynet_agent_linux_amd64_v1.0.0.tgz');

        $this->assertNotNull($got, 'mynet_agent_* 必须可解析（原正则不允许下划线）');
        $this->assertSame('mynet_agent', $got['product']);
        $this->assertSame('linux', $got['platform']);
        $this->assertSame('amd64', $got['arch']);
        $this->assertSame('v1.0.0', $got['version']);
        $this->assertSame('tgz', $got['ext']);
    }

    /** 放开下划线后不能把 `mynet_agent` 切成 name=mynet + platform=agent */
    public function test_underscore_prefix_does_not_mis_split(): void
    {
        $got = $this->svc->parseFilename('mynet_agent_darwin_arm64_v0.2.0.tgz');

        $this->assertNotNull($got);
        $this->assertSame('mynet_agent', $got['product'], 'name 段应完整吃掉下划线');
        $this->assertSame('darwin', $got['platform'], 'platform 不得被切歪');
        $this->assertSame('arm64', $got['arch']);
    }

    /** 显式平台枚举里 openwrt-sdk 必须排在 openwrt 之前，否则 `-sdk` 落不进去 */
    public function test_openwrt_sdk_is_not_swallowed_by_openwrt(): void
    {
        $got = $this->svc->parseFilename('mynet_agent_openwrt-sdk_mipsel_v1.0.0.tgz');

        $this->assertNotNull($got);
        $this->assertSame('openwrt-sdk', $got['platform']);
        $this->assertSame('mipsel', $got['arch']);
    }

    /** Go 的 mipsle 在 ctl 侧叫 mipsel —— agent 侧产物名必须用后者 */
    public function test_mipsel_arch_is_accepted(): void
    {
        $got = $this->svc->parseFilename('mynet_agent_linux_mipsel_v1.0.0.tgz');

        $this->assertNotNull($got);
        $this->assertSame('mipsel', $got['arch']);
        $this->assertTrue($this->svc->validateArch($got['arch']));
        $this->assertTrue($this->svc->validateCombination($got['platform'], $got['arch']));
    }

    /** tui 的别名行为不能被破坏：文件名用 mynet，反解析回 tui */
    public function test_tui_alias_still_round_trips(): void
    {
        $this->assertSame(
            'mynet_linux_amd64_v0.9.8.10.tgz',
            $this->svc->generateFilename('tui', 'linux', 'amd64', 'v0.9.8.10')
        );

        $got = $this->svc->parseFilename('mynet_linux_amd64_v0.9.8.10.tgz');
        $this->assertNotNull($got);
        $this->assertSame('tui', $got['product'], 'mynet 是 tui 的对外别名，须反解回 tui');
        $this->assertNotSame('mynet_agent', $got['product'], 'mynet 不得被当成 agent');
    }

    /**
     * 全产品 × 全平台往返一致。
     * 这是防「改了一处忘了另一处」的主判据。
     */
    public function test_round_trip_for_all_products_and_platforms(): void
    {
        $archs = ['linux' => 'amd64', 'darwin' => 'arm64', 'windows' => 'amd64',
            'freebsd' => 'amd64', 'openbsd' => 'amd64', 'openwrt' => 'mipsel', 'openwrt-sdk' => 'mipsel'];

        foreach (array_keys(ReleaseDomainService::PRODUCTS) as $product) {
            foreach ($archs as $platform => $arch) {
                $version = 'v1.2.3';
                $filename = $this->svc->generateFilename($product, $platform, $arch, $version);

                $got = $this->svc->parseFilename($filename);
                $this->assertNotNull($got, "解析失败: {$filename}");
                $this->assertSame($product, $got['product'], "product 往返不一致: {$filename}");
                $this->assertSame($platform, $got['platform'], "platform 往返不一致: {$filename}");
                $this->assertSame($arch, $got['arch'], "arch 往返不一致: {$filename}");
                $this->assertSame($version, $got['version'], "version 往返不一致: {$filename}");
            }
        }
    }

    /** windows 的扩展名必须是 zip（agent 侧 pack.sh 按平台选扩展名） */
    public function test_windows_defaults_to_zip(): void
    {
        $this->assertSame(
            'mynet_agent_windows_amd64_v1.0.0.zip',
            $this->svc->generateFilename('mynet_agent', 'windows', 'amd64', 'v1.0.0')
        );
    }

    /** 版本段必须接受 agent 侧 `git describe` 产出的形态 */
    public function test_accepts_git_describe_versions(): void
    {
        foreach (['v1.0.0', 'v0.0.0-e831eb8', 'v0.0.0-e831eb8-dirty', 'v1.6.0.a'] as $v) {
            $filename = "mynet_agent_linux_amd64_{$v}.tgz";
            $got = $this->svc->parseFilename($filename);
            $this->assertNotNull($got, "版本 {$v} 应被接受");
            $this->assertSame($v, $got['version']);
        }
    }

    public function test_rejects_malformed_filenames(): void
    {
        $bad = [
            '',
            'mynet_agent_linux_amd64.tgz',          // 缺版本
            'mynet_agent_linux_amd64_1.0.0.tgz',    // 版本缺前导 v
            'mynet_agent_linux_amd64_v1.0.0.rar',   // 扩展名不在白名单
            'mynet_agent_linux_amd64_v1.0.0',       // 缺扩展名
            'mynet_agent_unknownos_amd64_v1.0.0.tgz', // 平台不在枚举里
            '../mynet_agent_linux_amd64_v1.0.0.tgz',  // 带路径
        ];
        foreach ($bad as $f) {
            $this->assertNull($this->svc->parseFilename($f), "应拒绝: {$f}");
        }
    }

    /** agent 必须作为一等产品存在于白名单，否则 validateProduct 会拒收上传 */
    public function test_agent_is_a_registered_product(): void
    {
        $this->assertTrue($this->svc->validateProduct('mynet_agent'));
        $this->assertNull(
            ReleaseDomainService::PRODUCTS['mynet_agent']['alias'],
            'mynet_agent 不应有别名（有别名会让文件名前缀变样）'
        );
    }

    /**
     * 原生安装器扩展名（exe/dmg/pkg）必须能反解析。
     * 它们与自更新归档（tgz/zip）是不同 artifact kind，都要进白名单；
     * 否则 buildDownloadUrl 落到无前缀 fallback、分发出错。
     */
    public function test_parses_native_installer_extensions(): void
    {
        foreach (['exe' => 'windows', 'pkg' => 'darwin', 'dmg' => 'darwin'] as $ext => $platform) {
            $filename = "mynet_agent_{$platform}_amd64_v1.0.0.{$ext}";
            $got = $this->svc->parseFilename($filename);
            $this->assertNotNull($got, "应解析: {$filename}");
            $this->assertSame($ext, $got['ext']);
            $this->assertSame($platform, $got['platform']);
            $this->assertSame('v1.0.0', $got['version']);
        }
        // 不在白名单的容器仍必须拒绝
        $this->assertNull($this->svc->parseFilename('mynet_agent_windows_amd64_v1.0.0.rar'));
    }

    /**
     * 上传白名单取自 domain SSOT（控制器不再硬编码）：PRODUCTS / PLATFORMS 的
     * **每个键**都必须通过各自的 validate*。任一条被 domain 加了却又校验不过，
     * 就等于「清单里点得到、提交却 422」。
     */
    public function test_domain_whitelists_are_self_consistent(): void
    {
        foreach (array_keys(ReleaseDomainService::PRODUCTS) as $product) {
            $this->assertTrue($this->svc->validateProduct($product), "产品应可上传: {$product}");
        }
        foreach (array_keys(ReleaseDomainService::PLATFORMS) as $platform) {
            $this->assertTrue($this->svc->validatePlatform($platform), "平台应可上传: {$platform}");
        }
        // 发布面必须点得到 mynet_agent 与 openwrt-sdk（曾因控制器硬编码清单漏掉）
        $this->assertContains('mynet_agent', array_keys(ReleaseDomainService::PRODUCTS));
        $this->assertContains('openwrt-sdk', array_keys(ReleaseDomainService::PLATFORMS));
    }

    /**
     * 产物类型（artifact kind，§4.3）：原生安装包 vs 自更新归档必须**显式**区分，
     * 不从扩展名运行时猜 —— 登记时按本表映射一次并落库。
     */
    public function test_artifact_kind_maps_installer_vs_archive(): void
    {
        foreach (['exe', 'dmg', 'pkg', 'ipk'] as $ext) {
            $this->assertSame('installer', $this->svc->artifactKindForExt($ext), "{$ext} 应为 installer");
        }
        foreach (['tgz', 'zip', 'tar.gz'] as $ext) {
            $this->assertSame('archive', $this->svc->artifactKindForExt($ext), "{$ext} 应为 archive");
        }
        // 未登记扩展名不得瞎归类
        $this->assertSame('', $this->svc->artifactKindForExt('rar'));
        $this->assertSame('', $this->svc->artifactKindForExt(''));
    }

    /** parseFilename 必须一并给出 kind（windows 的 zip 归档 vs exe 安装器同名同版本也要分得开）。 */
    public function test_parse_filename_returns_kind(): void
    {
        $exe = $this->svc->parseFilename('mynet_agent_windows_amd64_v1.0.0.exe');
        $this->assertSame('installer', $exe['kind']);
        $zip = $this->svc->parseFilename('mynet_agent_windows_amd64_v1.0.0.zip');
        $this->assertSame('archive', $zip['kind']);
        $ipk = $this->svc->parseFilename('mynet_agent_openwrt_mipsel_v1.0.0.ipk');
        $this->assertSame('installer', $ipk['kind']);
    }

    /**
     * O6 冻结：OpenWrt ipk 的外部文件名（SDK 产出的 `mynet-agent_*.ipk` /
     * `luci-app-mynet_*.ipk` 不在 ctl 枚举里 → 必须在出包侧改名成本形态）。
     *    runtime  = mynet_agent_openwrt_<arch>_<ver>.ipk（架构相关）
     *    LuCI 壳  = lua_openwrt_all_<ver>.ipk（opkg arch=all）
     */
    public function test_openwrt_ipk_frozen_names(): void
    {
        $this->assertSame(
            'mynet_agent_openwrt_amd64_v1.0.0.ipk',
            $this->svc->generateFilename('mynet_agent', 'openwrt', 'amd64', 'v1.0.0', 'ipk')
        );
        $this->assertSame(
            'lua_openwrt_all_v1.0.0.ipk',
            $this->svc->generateFilename('lua', 'openwrt', 'all', 'v1.0.0', 'ipk')
        );

        // 反解析回 product/platform/arch/kind，且组合有效
        $rt = $this->svc->parseFilename('mynet_agent_openwrt_amd64_v1.0.0.ipk');
        $this->assertSame('mynet_agent', $rt['product']);
        $this->assertSame('openwrt', $rt['platform']);
        $this->assertSame('amd64', $rt['arch']);
        $this->assertSame('installer', $rt['kind']);

        $luci = $this->svc->parseFilename('lua_openwrt_all_v1.0.0.ipk');
        $this->assertSame('lua', $luci['product']);
        $this->assertSame('all', $luci['arch']);

        $this->assertTrue($this->svc->validateCombination('openwrt', 'all'), 'openwrt 需接受 arch=all（LuCI 壳）');
        $this->assertTrue($this->svc->validateArch('all'));
    }
}
