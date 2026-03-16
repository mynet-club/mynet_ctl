<?php

namespace App\Services\Release;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\Release;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReleaseService
{
    public function __construct(
        private readonly ReleaseDomainService $domain
    ) {}

    /**
     * 上传并发布一个新版本
     */
    public function upload(array $data, UploadedFile $file): Release
    {
        $this->validateUpload($data);

        $product  = $data['product'];
        $version  = $data['version'];
        $platform = $data['platform'];
        $arch     = $data['arch'];
        $variant  = $data['variant'] ?? '';

        $filename = $this->domain->generateFilename($product, $platform, $arch, $version, $variant);
        $path     = "releases/{$product}/{$version}/{$filename}";

        $sha256   = hash_file('sha256', $file->getRealPath());
        $size     = $file->getSize();

        DB::transaction(function () use ($data, $file, $filename, $path, $sha256, $size, $product, $version, $platform, $arch, $variant, &$release) {
            Storage::disk('local')->putFileAs(
                "releases/{$product}/{$version}",
                $file,
                $filename
            );

            $releaseId = $this->generateId();

            $release = Release::create([
                'release_id'   => $releaseId,
                'product'      => $product,
                'version'      => $version,
                'platform'     => $platform,
                'arch'         => $arch,
                'variant'      => $variant,
                'filename'     => $filename,
                'file_path'    => $path,
                'file_size'    => $size,
                'sha256'       => $sha256,
                'download_url' => $this->buildDownloadUrl('release', $releaseId, $filename),
                'changelog'    => $data['changelog'] ?? null,
                'is_stable'    => $data['is_stable'] ?? true,
                'is_latest'    => false,
                'uploaded_by'  => $data['uploaded_by'] ?? null,
                'status'       => 1,
            ]);

            $this->refreshLatestFlag($product, $platform, $arch, $variant);
        });

        return $release;
    }

    /**
     * 查询最新稳定版（按 product + platform + arch）
     */
    public function getLatest(string $product, string $platform, string $arch, string $variant = ''): ?Release
    {
        return Release::active()->stable()
            ->where('product', $product)
            ->where('platform', $platform)
            ->where('arch', $arch)
            ->where('variant', $variant)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * 列出某个 product 下所有版本（按平台/架构分组）
     */
    public function listByProduct(string $product): array
    {
        return Release::active()
            ->where('product', $product)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * 记录下载并返回文件路径
     */
    public function download(int $releaseId, string $ip, string $userAgent): string
    {
        $release = Release::active()->where('release_id', $releaseId)->firstOrFail();

        Release::where('id', $release->id)->increment('download_cnt');

        $this->logDownload('release', $releaseId, $ip, $userAgent);

        return $release->file_path;
    }

    /**
     * 软删除（下架）
     */
    public function disable(int $releaseId): void
    {
        Release::where('release_id', $releaseId)->update(['status' => 0]);
    }

    // -------------------------------------------------------------------------

    private function validateUpload(array $data): void
    {
        if (!$this->domain->validateProduct($data['product'] ?? '')) {
            throw new RuntimeException('无效的产品类型');
        }
        if (!$this->domain->validateVersion($data['version'] ?? '')) {
            throw new RuntimeException('无效的版本号格式');
        }
        if (!$this->domain->validatePlatform($data['platform'] ?? '')) {
            throw new RuntimeException('无效的平台');
        }
        if (!$this->domain->validateArch($data['arch'] ?? '')) {
            throw new RuntimeException('无效的架构');
        }
        if (!$this->domain->validateCombination($data['platform'], $data['arch'])) {
            throw new RuntimeException("平台 {$data['platform']} 不支持架构 {$data['arch']}");
        }
    }

    /**
     * 刷新 is_latest 标记：同 product+platform+arch+variant 下，只有最新版 = 1
     */
    private function refreshLatestFlag(string $product, string $platform, string $arch, string $variant): void
    {
        $latest = Release::active()->stable()
            ->where('product', $product)
            ->where('platform', $platform)
            ->where('arch', $arch)
            ->where('variant', $variant)
            ->orderByDesc('created_at')
            ->first();

        if (!$latest) {
            return;
        }

        Release::where('product', $product)
               ->where('platform', $platform)
               ->where('arch', $arch)
               ->where('variant', $variant)
               ->update(['is_latest' => 0]);

        Release::where('id', $latest->id)->update(['is_latest' => 1]);
    }

    private function generateId(): int
    {
        // 16位安全整数：时间戳(毫秒) + 随机数，保持在 JS Number.MAX_SAFE_INTEGER 内
        return (int) (substr(microtime(true) * 1000, 0, 10) . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    private function buildDownloadUrl(string $type, int $id, string $filename): string
    {
        return rtrim(config('app.url'), '/') . "/api/v1/{$type}s/{$id}/download/{$filename}";
    }

    private function logDownload(string $type, int $resourceId, string $ip, string $userAgent): void
    {
        \App\Models\DownloadLog::create([
            'resource_type' => $type,
            'resource_id'   => $resourceId,
            'ip'            => $ip,
            'user_agent'    => $userAgent,
            'referer'       => request()->header('Referer', ''),
            'created_at'    => now(),
        ]);
    }
}
