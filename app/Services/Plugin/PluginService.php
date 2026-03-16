<?php

namespace App\Services\Plugin;

use App\Domain\Plugin\Services\PluginDomainService;
use App\Models\DownloadLog;
use App\Models\Plugin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PluginService
{
    public function __construct(
        private readonly PluginDomainService $domain
    ) {}

    public function upload(array $data, UploadedFile $file): Plugin
    {
        if (!$this->domain->validateCategory($data['category'] ?? 'general')) {
            throw new RuntimeException('无效的插件分类');
        }
        if (!$this->domain->validatePlatform($data['platform'] ?? '')) {
            throw new RuntimeException('无效的平台');
        }

        $name     = $data['name'];
        $version  = $data['version'];
        $platform = $data['platform'];
        $arch     = $data['arch'] ?? 'all';

        $filename = $this->domain->generateFilename($name, $platform, $arch, $version);
        $path     = "plugins/{$name}/{$version}/{$filename}";
        $sha256   = hash_file('sha256', $file->getRealPath());
        $size     = $file->getSize();

        DB::transaction(function () use ($data, $file, $filename, $path, $sha256, $size, $name, $version, $platform, $arch, &$plugin) {
            Storage::disk('local')->putFileAs(
                "plugins/{$name}/{$version}",
                $file,
                $filename
            );

            $pluginId = $this->generateId();

            $plugin = Plugin::create([
                'plugin_id'    => $pluginId,
                'name'         => $name,
                'display_name' => $data['display_name'] ?? $name,
                'category'     => $data['category'] ?? 'general',
                'version'      => $version,
                'platform'     => $platform,
                'arch'         => $arch,
                'description'  => $data['description'] ?? null,
                'filename'     => $filename,
                'file_path'    => $path,
                'file_size'    => $size,
                'sha256'       => $sha256,
                'download_url' => $this->buildDownloadUrl($pluginId, $filename),
                'dependencies' => $data['dependencies'] ?? null,
                'metadata'     => $data['metadata'] ?? null,
                'uploaded_by'  => $data['uploaded_by'] ?? null,
                'status'       => 1,
            ]);
        });

        return $plugin;
    }

    public function listByPlatform(string $platform): array
    {
        return Plugin::active()->forPlatform($platform)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function download(int $pluginId, string $ip, string $userAgent): string
    {
        $plugin = Plugin::active()->where('plugin_id', $pluginId)->firstOrFail();
        Plugin::where('id', $plugin->id)->increment('download_cnt');
        DownloadLog::create([
            'resource_type' => 'plugin',
            'resource_id'   => $pluginId,
            'ip'            => $ip,
            'user_agent'    => $userAgent,
            'referer'       => request()->header('Referer', ''),
            'created_at'    => now(),
        ]);
        return $plugin->file_path;
    }

    public function disable(int $pluginId): void
    {
        Plugin::where('plugin_id', $pluginId)->update(['status' => 0]);
    }

    private function generateId(): int
    {
        return (int) (substr(microtime(true) * 1000, 0, 10) . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    private function buildDownloadUrl(int $id, string $filename): string
    {
        return rtrim(config('app.url'), '/') . "/api/v1/plugins/{$id}/download/{$filename}";
    }
}
