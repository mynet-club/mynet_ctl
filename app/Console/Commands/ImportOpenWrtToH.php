<?php

namespace App\Console\Commands;

use App\Domain\Platform\Services\OpenWrtDetectService;
use App\Models\OpenwrtDevice;
use App\Models\OpenwrtDeviceFirmware;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * php artisan openwrt:import-toh [version] [options]
 *
 * 数据来源：downloads.openwrt.org/releases/{version}/targets/{target}/{sub}/profiles.json
 *
 * profiles.json 结构：
 *   arch_packages  (顶层) → DISTRIB_ARCH，同 target 所有设备共享
 *   target         (顶层) → DISTRIB_TARGET，如 ramips/mt7621
 *   profiles       (字典) → 每设备 { supported_devices, titles, images, ... }
 *
 * 典型用法：
 *   php artisan openwrt:import-toh               # 使用默认版本，导入内置 target 列表
 *   php artisan openwrt:import-toh 23.05.3       # 指定版本
 *   php artisan openwrt:import-toh --all         # 枚举并导入该版本所有 target
 *   php artisan openwrt:import-toh --target=ramips/mt7621  # 只导入单个 target
 *   php artisan openwrt:import-toh --file=/tmp/profiles.json --target=ramips/mt7621
 *   php artisan openwrt:import-toh --dry-run     # 预览不写库
 */
class ImportOpenWrtToH extends Command
{
    protected $signature = 'openwrt:import-toh
                            {version=25.12.1     : OpenWrt 版本号，如 25.12.1 / 24.10.6 / 23.05.3}
                            {--target=           : 只导入此 target（如 ramips/mt7621）}
                            {--file=             : 读取本地 profiles.json（需同时指定 --target）}
                            {--all               : 从 downloads.openwrt.org 枚举所有 target}
                            {--dry-run           : 预览，不写入数据库}
                            {--fresh             : 导入前清空 openwrt_devices 和 openwrt_device_firmware}';

    protected $description = '从 downloads.openwrt.org 导入 OpenWrt 设备数据（硬件+固件分层入库）';

    /** 内置 target 列表：来自 OpenWrtDetectService::TARGET_MAP 键名 + 常见补充 */
    private const BUILTIN_TARGETS = [
        // MIPS LE (Ralink / MediaTek ramips)
        'ramips/mt7621', 'ramips/mt7620', 'ramips/mt7628', 'ramips/rt305x', 'ramips/rt3883',
        // MIPS BE (Atheros)
        'ath79/generic', 'ath79/nand', 'ath79/mikrotik', 'ath79/tiny',
        // MediaTek ARM
        'mediatek/filogic', 'mediatek/mt7622',
        // Qualcomm IPQ
        'ipq40xx/generic', 'ipq806x/generic', 'ipq807x/generic', 'ipq60xx/generic',
        // Qualcomm NSS (old target names)
        'ipq40xx/mikrotik',
        // Broadcom
        'bcm27xx/bcm2708', 'bcm27xx/bcm2709', 'bcm27xx/bcm2710', 'bcm27xx/bcm2711',
        'bcm47xx/generic', 'bcm47xx/mips74k', 'bcm53xx/generic', 'bcm63xx/generic',
        'bcm4908/generic',
        // Marvell
        'mvebu/cortexa9', 'mvebu/cortexa72', 'kirkwood/generic',
        // Allwinner / Rockchip / NXP
        'sunxi/cortexa7', 'sunxi/cortexa53', 'rockchip/armv8',
        'imx/cortexa7', 'imx/cortexa9',
        // x86
        'x86/64', 'x86/generic', 'x86/geode', 'x86/legacy',
        // MIPS 64-bit
        'octeon/generic', 'octeontx/generic',
        // RISC-V
        'sifive/generic', 'starfive/generic',
        // Realtek
        'realtek/rtl930x', 'realtek/rtl838x',
        // Layerscape / AT91 / misc ARM
        'layerscape/armv7', 'layerscape/armv8',
        'at91/sama5', 'at91/sam9x',
        'mvebu/cortexa9', 'mvebu/cortexa72',
        // Lantiq
        'lantiq/xrx200', 'lantiq/xrx300', 'lantiq/ase', 'lantiq/falcon',
        // APM / Pistachio / Gemini
        'apm821xx/nand', 'apm821xx/sata',
        'pistachio/generic', 'gemini/generic',
        // Malta (QEMU MIPS)
        'malta/be', 'malta/le',
    ];

    public function __construct(private readonly OpenWrtDetectService $detector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $version = $this->argument('version');
        $dryRun  = $this->option('dry-run');
        $target  = $this->option('target');
        $file    = $this->option('file');
        $all     = $this->option('all');
        $fresh   = $this->option('fresh');

        $this->info("OpenWrt 设备数据导入 — 版本 <comment>{$version}</comment>");
        $this->info("数据源: downloads.openwrt.org");
        if ($dryRun) {
            $this->warn('预览模式（dry-run）：不会写入数据库');
        }

        if ($fresh && !$dryRun) {
            $this->warn('--fresh 已指定，清空现有数据...');
            \Schema::disableForeignKeyConstraints();
            OpenwrtDeviceFirmware::truncate();
            OpenwrtDevice::truncate();
            \Schema::enableForeignKeyConstraints();
        }

        // 确定 target 列表
        if ($file && $target) {
            $targets = [$target];
        } elseif ($target) {
            $targets = [$target];
        } elseif ($all) {
            $targets = $this->crawlAllTargets($version);
        } else {
            $targets = $this->getBuiltinTargets();
        }

        $this->info(sprintf('共 <comment>%d</comment> 个 target 待导入', count($targets)));

        $totalDevices   = 0;
        $totalFirmware  = 0;
        $failedTargets  = [];

        foreach ($targets as $t) {
            [$imported, $firmware] = $this->importTarget($version, $t, $file, $dryRun);
            if ($imported === -1) {
                $failedTargets[] = $t;
            } else {
                $totalDevices  += $imported;
                $totalFirmware += $firmware;
            }
        }

        $this->newLine();
        $this->line('─────────────────────────────────────────────────────');
        $this->info(sprintf('完成  devices:%d  firmware_entries:%d', $totalDevices, $totalFirmware));

        if ($failedTargets) {
            $this->warn('失败的 target（网络错误或不存在）：');
            foreach ($failedTargets as $ft) {
                $this->line("  <comment>{$ft}</comment>");
            }
        }

        return empty($failedTargets) ? 0 : 1;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 导入单个 target 的所有设备，返回 [导入数, 固件条目数]，失败返回 [-1, 0]
     */
    private function importTarget(
        string $version,
        string $target,
        ?string $localFile,
        bool $dryRun
    ): array {
        $data = $this->fetchProfilesJson($version, $target, $localFile);
        if (!$data) {
            $this->line("  <fg=red>✗</> {$target} — 获取失败");
            return [-1, 0];
        }

        $archPackages  = $data['arch_packages'] ?? '';
        $targetFull    = $data['target'] ?? $target;
        $profiles      = $data['profiles'] ?? [];

        if (empty($archPackages) || empty($profiles)) {
            $this->line("  <fg=yellow>⚠</> {$target} — profiles 为空，跳过");
            return [0, 0];
        }

        // 解析 target/subtarget
        [$openwrtTarget, $openwrtSub] = $this->splitTarget($targetFull ?: $target);

        // 通过 OpenWrtDetectService 计算 ctl_arch
        $detected = $this->detector->detect($archPackages, $targetFull);
        $ctlArch  = $detected['arch'];
        $libc     = $detected['libc'] ?? 'musl';
        $floatAbi = $this->inferFloatAbi($ctlArch);

        $imported = 0;
        $firmware = 0;

        foreach ($profiles as $profileId => $profile) {
            $titles           = $profile['titles'] ?? [];
            $supportedDevices = $profile['supported_devices'] ?? [];

            if (empty($titles) || empty($profileId)) {
                continue;
            }

            [$brand, $modelName, $variant] = $this->parseTitles($titles);

            if ($dryRun) {
                $this->line("  [dry] {$profileId} — {$brand} {$modelName} {$variant}  [{$ctlArch}]");
                $imported++;
                $firmware++;
                continue;
            }

            // 创建/更新设备（以 openwrt_id 为唯一键）
            $device = OpenwrtDevice::updateOrCreate(
                ['openwrt_id' => $profileId],
                $this->buildDeviceAttributes($brand, $modelName, $variant, $profileId, $supportedDevices)
            );

            // 主 board_name（第一个 supported_device）
            if ($device->wasRecentlyCreated && !empty($supportedDevices)) {
                $this->trySetBoardName($device, $supportedDevices[0]);
            }

            // 创建/更新固件条目（以 device_id + distrib_target + distrib_arch 为唯一键）
            OpenwrtDeviceFirmware::updateOrCreate(
                [
                    'device_id'     => $device->id,
                    'distrib_target' => $targetFull,
                    'distrib_arch'   => $archPackages,
                ],
                [
                    'openwrt_target'   => $openwrtTarget,
                    'openwrt_subtarget' => $openwrtSub,
                    'supported_devices' => $supportedDevices,
                    'ctl_arch'         => $ctlArch,
                    'libc'             => $libc,
                    'float_abi'        => $floatAbi,
                    'support_status'   => 'supported',
                    'is_primary'       => true,
                    'status'           => 1,
                ]
            );

            $imported++;
            $firmware++;
        }

        $this->line(sprintf(
            '  <fg=green>✓</> %-30s  arch=<comment>%-18s</comment>  ctl=<comment>%-12s</comment>  devices=%d',
            $target, $archPackages, $ctlArch, $imported
        ));

        return [$imported, $firmware];
    }

    private const UA = 'Mozilla/5.0 (compatible; mynet-ctl/1.0; +https://mynet.club)';

    /**
     * 获取 profiles.json：优先 localFile，否则从 downloads.openwrt.org 下载
     */
    private function fetchProfilesJson(string $version, string $target, ?string $localFile): ?array
    {
        if ($localFile) {
            $content = @file_get_contents($localFile);
            return $content ? json_decode($content, true) : null;
        }

        $url = "https://downloads.openwrt.org/releases/{$version}/targets/{$target}/profiles.json";

        // CDN（Varnish）偶尔会对短时间多次请求限流，最多重试 3 次，间隔 3s
        $ctx = stream_context_create([
            'http' => ['timeout' => 60, 'user_agent' => self::UA],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $content = @file_get_contents($url, false, $ctx);
                if ($content !== false && str_starts_with(trim($content), '{')) {
                    return json_decode($content, true);
                }
            } catch (\Throwable) {
            }
            if ($attempt < 3) {
                sleep(3);
            }
        }
        return null;
    }

    /**
     * 从 titles 数组中提取品牌/型号/变体
     * titles 格式：[{vendor: "Xiaomi", model: "Mi Router", variant: "4A Gigabit Edition"}, ...]
     */
    private function parseTitles(array $titles): array
    {
        if (empty($titles)) {
            return ['', '', ''];
        }
        $t       = $titles[0];
        $brand   = $t['vendor'] ?? '';
        $model   = $t['model'] ?? '';
        $variant = $t['variant'] ?? '';
        return [trim($brand), trim($model), trim($variant)];
    }

    /**
     * 构建设备基础属性（仅 import 时填充的字段，不覆盖人工补充的硬件规格）
     */
    private function buildDeviceAttributes(
        string $brand,
        string $model,
        string $variant,
        string $profileId,
        array  $supportedDevices
    ): array {
        $slug = Str::slug(str_replace(['_', '/'], '-', $profileId));

        return [
            'brand'        => $brand,
            'model'        => $model,
            'version'      => $variant,
            'device_slug'  => $slug,
            // board_name 单独处理（避免唯一键冲突）
        ];
    }

    /**
     * 尝试写入 board_name（唯一键，跳过已存在的值）
     */
    private function trySetBoardName(OpenwrtDevice $device, string $boardName): void
    {
        if (!$boardName) {
            return;
        }
        try {
            $device->board_name = $boardName;
            $device->saveQuietly();
        } catch (\Throwable) {
            // 忽略唯一键冲突（另一设备已用同一 board_name）
        }
    }

    /**
     * 将 "ramips/mt7621" 拆成 ["ramips", "mt7621"]
     */
    private function splitTarget(string $target): array
    {
        $parts = explode('/', $target, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * 根据 CTL arch 推断浮点 ABI
     * - ARM hard 系列（armv7, arm64）→ hard
     * - *-softfp / MIPS / i386 / riscv64 → soft（OpenWrt 默认 soft-float for MIPS）
     */
    private function inferFloatAbi(string $ctlArch): string
    {
        if (str_ends_with($ctlArch, '-softfp')) {
            return 'soft';
        }
        return match ($ctlArch) {
            'armv7', 'arm64' => 'hard',
            default          => 'soft',
        };
    }

    /**
     * 返回内置 target 列表（去重）
     */
    private function getBuiltinTargets(): array
    {
        // 额外追加 OpenWrtDetectService::TARGET_MAP 中的 target
        $fromService = array_keys($this->detector->getTargetMap());
        return array_unique(array_merge(self::BUILTIN_TARGETS, $fromService));
    }

    /**
     * 从 downloads.openwrt.org 爬取该版本下所有 target/subtarget 组合
     * （使用 HTML 目录列表，无需额外 API）
     */
    private function crawlAllTargets(string $version): array
    {
        $this->info('--all 模式：枚举所有 target，请稍候...');
        $baseUrl = "https://downloads.openwrt.org/releases/{$version}/targets/";

        $topTargets = $this->listDirectoryEntries($baseUrl);
        if (empty($topTargets)) {
            $this->warn("无法枚举 targets（网络问题），退回到内置列表");
            return $this->getBuiltinTargets();
        }

        $allTargets = [];
        foreach ($topTargets as $top) {
            $subtargets = $this->listDirectoryEntries("{$baseUrl}{$top}/");
            foreach ($subtargets as $sub) {
                $allTargets[] = "{$top}/{$sub}";
            }
        }

        $this->info(sprintf('发现 <comment>%d</comment> 个 target/subtarget 组合', count($allTargets)));
        return $allTargets;
    }

    /**
     * 解析 HTML 目录列表，返回子目录名数组
     */
    private function listDirectoryEntries(string $url): array
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 30, 'user_agent' => self::UA],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        try {
            $html = @file_get_contents($url, false, $ctx);
            if (!$html) {
                return [];
            }
            preg_match_all('|href="([a-z0-9_\-]+)/"|', $html, $matches);
            return $matches[1] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
