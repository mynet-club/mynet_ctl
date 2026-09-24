<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * php artisan proxy:update-iplists
 *
 * 从 v2fly/geoip 下载最新 geoip.dat，提取中国/国际 IP 段，
 * 聚合后输出到 storage/app/public/proxy-lists/。
 *
 * 生成文件：
 *   chinaip.txt   — 中国 IP CIDR 列表
 *   interip.txt   — 国际 IP CIDR 列表
 *   meta.json     — 更新时间、行数、SHA256
 *
 * 用法：
 *   php artisan proxy:update-iplists             # 正常运行
 *   php artisan proxy:update-iplists --dry-run   # 不执行，仅显示命令
 */
class UpdateProxyIpLists extends Command
{
    protected $signature = 'proxy:update-iplists
                            {--dry-run : 不执行，仅显示将要运行的命令}';

    protected $description = '从 v2fly/geoip 更新 chinaip.txt / interip.txt 代理 IP 列表';

    public function handle(): int
    {
        $scriptPath = base_path('scripts/proxy-lists/update_proxy_lists.sh');
        $outputDir = storage_path('app/public/proxy-lists');

        if (! file_exists($scriptPath)) {
            $this->error("脚本不存在: {$scriptPath}");

            return self::FAILURE;
        }

        $cmd = "bash {$scriptPath} --output-dir {$outputDir}";

        if ($this->option('dry-run')) {
            $this->info('[DRY-RUN] 将要执行:');
            $this->line("  {$cmd}");
            $this->line("  输出目录: {$outputDir}");

            return self::SUCCESS;
        }

        $this->info('开始更新代理 IP 列表...');
        $this->line("脚本: {$scriptPath}");
        $this->line("输出: {$outputDir}");
        $this->newLine();

        $result = Process::timeout(600)->run($cmd);

        // 实时输出脚本日志
        if ($result->output()) {
            $this->line($result->output());
        }
        if ($result->errorOutput()) {
            $this->error($result->errorOutput());
        }

        if ($result->successful()) {
            $this->newLine();
            $this->info('✅ 代理 IP 列表更新完成');

            // 显示结果摘要
            $metaFile = $outputDir.'/meta.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) {
                    $this->table(
                        ['文件', '行数', '大小', 'SHA256'],
                        [
                            ['chinaip.txt', $meta['chinaip']['count'] ?? '-', $this->formatSize($meta['chinaip']['size'] ?? 0), substr($meta['chinaip']['sha256'] ?? '', 0, 16).'...'],
                            ['interip.txt', $meta['interip']['count'] ?? '-', $this->formatSize($meta['interip']['size'] ?? 0), substr($meta['interip']['sha256'] ?? '', 0, 16).'...'],
                        ]
                    );
                    $this->line('更新时间: '.($meta['updated_at'] ?? '-'));
                }
            }

            return self::SUCCESS;
        }

        $this->error('❌ 更新失败 (exit code: '.$result->exitCode().')');

        return self::FAILURE;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
