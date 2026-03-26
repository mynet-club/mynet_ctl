<?php

namespace App\Console\Commands;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan release:import-gnb [options]
 *
 * 从本地目录或远端导入 GNB 发行包到 CTL releases 表。
 *
 * 生产环境典型用法（从旧 download 目录平移）：
 *   php artisan release:import-gnb --source=/data/app/mynet.club/download/gnb/v1.6.0.a --fresh
 *
 * 其他用法：
 *   php artisan release:import-gnb --dry-run               # 预览
 *   php artisan release:import-gnb --skip-download         # 仅入库（文件已在目标路径）
 *   php artisan release:import-gnb --source=/path/to/dir   # 从本地目录扫描并复制
 *   php artisan release:import-gnb --fresh                 # 清空该版本后重新导入
 *
 * --source 模式自动扫描目录中所有 gnb_*.tgz / gnb_*.zip 文件，
 * 通过 ReleaseDomainService::parseFilename() 解析，无需硬编码矩阵。
 */
class ImportGnbFromDownload extends Command
{
    protected $signature = 'release:import-gnb
                            {version=v1.6.0.a    : GNB 版本号}
                            {--source=           : 本地源目录（自动扫描所有 gnb_* 文件并复制入库）}
                            {--skip-download      : 跳过下载，假设文件已在目标路径（自动扫描）}
                            {--dry-run            : 预览，不复制不写入数据库}
                            {--fresh              : 导入前清空该版本所有记录}
                            {--base-url=          : HTTP 下载基址（默认 https://download.mynet.club/gnb）}';

    protected $description = '导入 GNB 发行包到 CTL（从本地目录扫描 / 已有文件 / HTTP 下载）';

    /**
     * HTTP 下载回退矩阵（仅 --base-url 模式使用）
     * 格式：[platform, arch, ext]
     */
    private const DOWNLOAD_MATRIX = [
        ['linux', 'amd64', 'tgz'], ['linux', 'arm64', 'tgz'],
        ['linux', 'armv7', 'tgz'], ['linux', 'armv7-softfp', 'tgz'], ['linux', 'armv7-hardfp', 'tgz'],
        ['linux', 'armv6-softfp', 'tgz'], ['linux', 'i386', 'tgz'],
        ['linux', 'mips', 'tgz'], ['linux', 'mips-softfp', 'tgz'],
        ['linux', 'mipsel', 'tgz'], ['linux', 'mipsel-softfp', 'tgz'],
        ['linux', 'mips64', 'tgz'], ['linux', 'mips64el', 'tgz'], ['linux', 'riscv64', 'tgz'],
        ['openwrt', 'amd64', 'tgz'], ['openwrt', 'arm64', 'tgz'],
        ['openwrt', 'armv7', 'tgz'], ['openwrt', 'armv7-softfp', 'tgz'], ['openwrt', 'armv7-hardfp', 'tgz'],
        ['openwrt', 'armv6-softfp', 'tgz'], ['openwrt', 'i386', 'tgz'],
        ['openwrt', 'mips', 'tgz'], ['openwrt', 'mips-softfp', 'tgz'],
        ['openwrt', 'mipsel', 'tgz'], ['openwrt', 'mipsel-softfp', 'tgz'], ['openwrt', 'mipsel-muslsf', 'tgz'],
        ['openwrt', 'mips64', 'tgz'], ['openwrt', 'mips64el', 'tgz'], ['openwrt', 'riscv64', 'tgz'],
        ['darwin', 'amd64', 'tgz'], ['darwin', 'arm64', 'tgz'],
        ['windows', 'amd64', 'tgz'], ['freebsd', 'amd64', 'tgz'],
    ];

    private ReleaseDomainService $domain;

    public function __construct(ReleaseDomainService $domain)
    {
        parent::__construct();
        $this->domain = $domain;
    }

    public function handle(): int
    {
        $version  = $this->argument('version');
        $dryRun   = $this->option('dry-run');
        $fresh    = $this->option('fresh');
        $skipDl   = $this->option('skip-download');
        $source   = $this->option('source');
        $baseUrl  = rtrim($this->option('base-url') ?: 'https://download.mynet.club/gnb', '/');

        if (!$this->domain->validateVersion($version)) {
            $this->error("无效的版本号: {$version}");
            return self::FAILURE;
        }

        // 判断模式
        $mode = $source ? 'source' : ($skipDl ? 'scan' : 'download');

        $this->info("=== GNB Release Import ===");
        $this->info("版本: {$version}");
        $this->info("模式: " . match ($mode) {
            'source'   => "本地目录扫描 → {$source}",
            'scan'     => "已有文件扫描 → storage/app/private/releases/gnb/{$version}/",
            'download' => "HTTP 下载 → {$baseUrl}",
        });
        $this->info("目标: storage/app/private/releases/gnb/{$version}/");
        if ($dryRun) {
            $this->warn("*** DRY RUN 模式 — 不复制不入库 ***");
        }

        // Fresh 模式：清空该版本记录
        if ($fresh && !$dryRun) {
            $count = Release::where('product', 'gnb')->where('version', $version)->count();
            if ($count > 0) {
                if ($this->confirm("即将删除 GNB {$version} 的 {$count} 条记录，确认？")) {
                    Release::where('product', 'gnb')->where('version', $version)->delete();
                    $this->info("已删除 {$count} 条记录");
                } else {
                    return self::FAILURE;
                }
            }
        }

        // 根据模式构建文件列表
        if ($mode === 'source' || $mode === 'scan') {
            return $this->importFromDirectory($version, $mode, $source, $dryRun, $fresh);
        }

        return $this->importFromHttp($version, $baseUrl, $dryRun, $fresh);
    }

    /**
     * 从本地目录扫描并导入（--source 或 --skip-download）
     */
    private function importFromDirectory(string $version, string $mode, ?string $source, bool $dryRun, bool $fresh): int
    {
        $localDisk   = Storage::disk('local');
        $storagePath = "releases/gnb/{$version}";

        // 确定扫描目录
        $scanDir = ($mode === 'source')
            ? rtrim($source, '/')
            : $localDisk->path($storagePath);

        if (!is_dir($scanDir)) {
            $this->error("目录不存在: {$scanDir}");
            return self::FAILURE;
        }

        // 扫描所有 gnb_*.tgz 和 gnb_*.zip
        $files = glob($scanDir . '/gnb_*.{tgz,zip}', GLOB_BRACE);
        if (empty($files)) {
            $this->error("目录中未找到 gnb_*.tgz / gnb_*.zip 文件");
            return self::FAILURE;
        }

        // 按文件名排序
        sort($files);

        $this->newLine();
        $this->info(sprintf("扫描到 %d 个文件", count($files)));
        $this->newLine();

        $stats = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($files as $srcAbsPath) {
            $originalFilename = basename($srcAbsPath);

            // 解析文件名
            $parsed = $this->domain->parseFilename($originalFilename);
            if (!$parsed) {
                $this->line("  <comment>SKIP</comment> {$originalFilename} (无法解析文件名)");
                $stats['skipped']++;
                continue;
            }

            // 确认是 GNB 产品
            if ($parsed['product'] !== 'gnb') {
                $this->line("  <comment>SKIP</comment> {$originalFilename} (非 gnb 产品: {$parsed['product']})");
                $stats['skipped']++;
                continue;
            }

            // 确认版本匹配
            if ($parsed['version'] !== $version) {
                $this->line("  <comment>SKIP</comment> {$originalFilename} (版本不匹配: {$parsed['version']})");
                $stats['skipped']++;
                continue;
            }

            $platform = $parsed['platform'];
            $arch     = $parsed['arch'];
            $ext      = $parsed['ext'];

            // CTL 标准文件名（确保一致性）
            $filename = $this->domain->generateFilename('gnb', $platform, $arch, $version, $ext);
            $filePath = "{$storagePath}/{$filename}";

            // 检查是否已存在记录
            $exists = Release::where('product', 'gnb')
                ->where('version', $version)
                ->where('platform', $platform)
                ->where('arch', $arch)
                ->exists();

            if ($exists && !$fresh) {
                $this->line("  <comment>SKIP</comment> {$filename} (已存在)");
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $fileSize = filesize($srcAbsPath);
                $this->line(sprintf("  <info>DRY</info>  %s (%s)", $filename, $this->humanSize($fileSize)));
                $stats['success']++;
                continue;
            }

            // ── 复制文件（source 模式）或确认存在（scan 模式）──
            $destAbsPath = $localDisk->path($filePath);

            if ($mode === 'source') {
                $localDisk->makeDirectory($storagePath);
                if (!copy($srcAbsPath, $destAbsPath)) {
                    $this->line("  <error>FAIL</error> {$filename} (复制失败)");
                    $stats['failed']++;
                    continue;
                }
            } else {
                // scan 模式：文件应该已在目标路径
                if (!file_exists($destAbsPath)) {
                    $this->line("  <error>MISS</error> {$filename} (文件不存在)");
                    $stats['failed']++;
                    continue;
                }
            }

            // ── 入库 ──
            $this->createRelease($version, $platform, $arch, $filename, $filePath, $destAbsPath);

            $fileSize = filesize($destAbsPath);
            $sha256   = hash_file('sha256', $destAbsPath);
            $this->line(sprintf("  <info>OK</info>   %s (%s, sha256:%s)", $filename, $this->humanSize($fileSize), $sha256));
            $stats['success']++;

            usleep(1000);
        }

        return $this->printSummary($stats);
    }

    /**
     * 从 HTTP 下载导入（默认模式）
     */
    private function importFromHttp(string $version, string $baseUrl, bool $dryRun, bool $fresh): int
    {
        $localDisk   = Storage::disk('local');
        $storagePath = "releases/gnb/{$version}";
        $stats       = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        $this->newLine();
        $this->info(sprintf("共 %d 个目标文件", count(self::DOWNLOAD_MATRIX)));
        $this->newLine();

        foreach (self::DOWNLOAD_MATRIX as [$platform, $arch, $ext]) {
            $filename = $this->domain->generateFilename('gnb', $platform, $arch, $version, $ext);
            $filePath = "{$storagePath}/{$filename}";

            $exists = Release::where('product', 'gnb')
                ->where('version', $version)
                ->where('platform', $platform)
                ->where('arch', $arch)
                ->exists();

            if ($exists && !$fresh) {
                $this->line("  <comment>SKIP</comment> {$filename} (已存在)");
                $stats['skipped']++;
                continue;
            }

            $url = "{$baseUrl}/{$version}/{$filename}";

            if ($dryRun) {
                $this->line("  <info>DRY</info>  {$filename}");
                $this->line("         URL: {$url}");
                $stats['success']++;
                continue;
            }

            $destAbsPath = $localDisk->path($filePath);
            if (!$this->downloadFile($url, $destAbsPath, $storagePath, $localDisk)) {
                $this->line("  <error>FAIL</error> {$filename} (下载失败)");
                $stats['failed']++;
                continue;
            }

            $this->createRelease($version, $platform, $arch, $filename, $filePath, $destAbsPath);

            $fileSize = filesize($destAbsPath);
            $sha256   = hash_file('sha256', $destAbsPath);
            $this->line(sprintf("  <info>OK</info>   %s (%s, sha256:%s)", $filename, $this->humanSize($fileSize), $sha256));
            $stats['success']++;

            usleep(1000);
        }

        return $this->printSummary($stats);
    }

    /**
     * 创建 Release 记录并刷新 is_latest
     */
    private function createRelease(string $version, string $platform, string $arch, string $filename, string $filePath, string $absPath): void
    {
        $sha256   = hash_file('sha256', $absPath);
        $fileSize = filesize($absPath);
        $downloadUrl = rtrim(config('app.url'), '/') . "/d/gnb/{$filename}";

        DB::transaction(function () use ($version, $platform, $arch, $filename, $filePath, $sha256, $fileSize, $downloadUrl) {
            $releaseId = (int) (substr((string)(microtime(true) * 1000), 0, 10)
                . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT));

            Release::create([
                'release_id'   => $releaseId,
                'product'      => 'gnb',
                'version'      => $version,
                'platform'     => $platform,
                'arch'         => $arch,
                'variant'      => '',
                'filename'     => $filename,
                'file_path'    => $filePath,
                'file_size'    => $fileSize,
                'sha256'       => $sha256,
                'download_url' => $downloadUrl,
                'changelog'    => null,
                'is_stable'    => true,
                'is_latest'    => false,
                'uploaded_by'  => null,
                'status'       => 1,
            ]);
        });

        $this->refreshLatestFlag($platform, $arch);
    }

    /**
     * 下载远端文件到本地路径
     */
    private function downloadFile(string $url, string $destPath, string $storagePath, $disk): bool
    {
        $disk->makeDirectory($storagePath);

        $this->output->write("  <comment>DOWN</comment> {$url} ... ");

        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'user_agent' => 'MyNet-CTL/1.0 (release-import)',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $this->output->writeln('<error>FAILED</error>');
            return false;
        }

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($destPath, $content);
        $size = strlen($content);
        $this->output->writeln(sprintf('<info>OK</info> (%s)', $this->humanSize($size)));
        return true;
    }

    private function printSummary(array $stats): int
    {
        $this->newLine();
        $this->info("=== 导入完成 ===");
        $this->table(
            ['状态', '数量'],
            [
                ['成功', $stats['success']],
                ['跳过', $stats['skipped']],
                ['失败', $stats['failed']],
            ]
        );

        if ($stats['failed'] > 0) {
            $this->warn("有 {$stats['failed']} 个文件导入失败，请检查日志。");
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 刷新 is_latest 标记
     */
    private function refreshLatestFlag(string $platform, string $arch): void
    {
        $latest = Release::where('product', 'gnb')
            ->where('platform', $platform)
            ->where('arch', $arch)
            ->where('variant', '')
            ->where('status', 1)
            ->where('is_stable', true)
            ->orderByDesc('created_at')
            ->first();

        if (!$latest) {
            return;
        }

        Release::where('product', 'gnb')
            ->where('platform', $platform)
            ->where('arch', $arch)
            ->where('variant', '')
            ->update(['is_latest' => 0]);

        Release::where('id', $latest->id)->update(['is_latest' => 1]);
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
