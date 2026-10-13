<?php

namespace App\Services\Release;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\DownloadLog;
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

        $product = $data['product'];
        $version = $data['version'];
        $platform = $data['platform'];
        $arch = $data['arch'];
        $variant = $data['variant'] ?? '';

        // ⚠️ variant **不是**文件扩展名：它是并入 arch 的限定符
        // （ManifestService 输出 `{arch}-{variant}`，如 mipsel-softfp）。
        // 把 variant 当 ext 传会让文件名变成 `..._v1.0.0.softfp` —— 既不在
        // parseFilename 白名单里（下载 URL 落到 fallback），也篡改了容器格式语义。
        //
        // ⚠️ 扩展名以**上传件**为准，不是平台默认：OpenWrt 的平台默认 ext=tgz，
        // 但运行包是 **.ipk**（原生安装包）—— 重算会把它写成 tgz（O6「显式 ipk
        // 扩展名」实测踩过）。上传件无扩展名时才回落平台默认。
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $filename = $this->domain->generateFilename($product, $platform, $arch, $version, $ext);

        // 产物类型：登记时按扩展名映射一次并落库（读取端不再运行时推断）
        $kind = $this->domain->artifactKindForExt(pathinfo($filename, PATHINFO_EXTENSION));
        $path = "releases/{$product}/{$version}/{$filename}";

        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();

        DB::transaction(function () use ($data, $file, $filename, $path, $sha256, $size, $product, $version, $platform, $arch, $variant, $kind, &$release) {
            Storage::disk('local')->putFileAs(
                "releases/{$product}/{$version}",
                $file,
                $filename
            );

            $releaseId = $this->generateId();

            $release = Release::create([
                'release_id' => $releaseId,
                'product' => $product,
                'version' => $version,
                'platform' => $platform,
                'arch' => $arch,
                'variant' => $variant,
                'kind' => $kind,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $size,
                'sha256' => $sha256,
                'download_url' => $this->buildDownloadUrl('release', $releaseId, $filename),
                'changelog' => $data['changelog'] ?? null,
                'is_stable' => $data['is_stable'] ?? true,
                'is_latest' => false,
                'uploaded_by' => $data['uploaded_by'] ?? null,
                'status' => 1,
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
     * 记录下载并返回文件绝对路径
     */
    public function download(int $releaseId, string $ip, string $userAgent): string
    {
        $release = Release::active()->where('release_id', $releaseId)->firstOrFail();

        Release::where('id', $release->id)->increment('download_cnt');

        $this->logDownload('release', $releaseId, $ip, $userAgent);

        return Storage::disk('local')->path($release->file_path);
    }

    /**
     * 按文件名精确查找并返回文件绝对路径（用于 /d/ 下载路由）
     *
     * ⚠️ 必须按 filename 精确匹配：早先按 (product,platform,arch,version)
     * 四元组 first() 取件，忽略 filename/ext/variant —— 同版本多容器
     * （windows zip 归档 + exe 安装器、openwrt ipk + tgz）时会把 A 文件
     * 套 B 文件名发给用户，下载计数/日志也记错行。
     */
    public function downloadByFilename(string $filename, string $ip, string $userAgent): string
    {
        $release = Release::active()
            ->where('filename', $filename)
            ->firstOrFail();

        Release::where('id', $release->id)->increment('download_cnt');
        $this->logDownload('release', $release->release_id, $ip, $userAgent);

        return Storage::disk('local')->path($release->file_path);
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
        if (! $this->domain->validateProduct($data['product'] ?? '')) {
            throw new RuntimeException('无效的产品类型');
        }
        if (! $this->domain->validateVersion($data['version'] ?? '')) {
            throw new RuntimeException('无效的版本号格式');
        }
        if (! $this->domain->validatePlatform($data['platform'] ?? '')) {
            throw new RuntimeException('无效的平台');
        }
        if (! $this->domain->validateArch($data['arch'] ?? '')) {
            throw new RuntimeException('无效的架构');
        }
        if (! $this->domain->validateCombination($data['platform'], $data['arch'])) {
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

        if (! $latest) {
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
        return (int) (substr(microtime(true) * 1000, 0, 10).str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    private function buildDownloadUrl(string $type, int $id, string $filename): string
    {
        // 新上传的 release 存 /d/ URL，旧数据由 ManifestService 动态生成
        $parsed = $this->domain->parseFilename($filename);
        if ($parsed) {
            $name = $this->domain->getProductFilenamePrefix($parsed['product']);

            return rtrim(config('app.url'), '/')."/d/{$name}/{$filename}";
        }

        // fallback——无法解析时仍用 /d/ 前缀
        return rtrim(config('app.url'), '/')."/d/{$filename}";
    }

    private function logDownload(string $type, int $resourceId, string $ip, string $userAgent): void
    {
        DownloadLog::create([
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => request()->header('Referer', ''),
            'created_at' => now(),
        ]);
    }
}
