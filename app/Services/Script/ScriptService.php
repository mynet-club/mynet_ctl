<?php

namespace App\Services\Script;

use App\Domain\Script\Services\ScriptDomainService;
use App\Models\DownloadLog;
use App\Models\Script;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScriptService
{
    public function __construct(
        private readonly ScriptDomainService $domain
    ) {}

    /**
     * 发布/更新脚本（内容直接存入数据库，同时可下载）
     */
    public function publish(array $data): Script
    {
        $category = $data['category'] ?? 'install';
        $lang = $data['lang'] ?? $this->domain->detectLang($data['content'] ?? '');

        if (! $this->domain->validateCategory($category)) {
            throw new RuntimeException('无效的脚本分类');
        }
        if (! $this->domain->validateLang($lang)) {
            throw new RuntimeException('无效的脚本语言');
        }

        $content = $data['content'];
        $checksum = $this->domain->computeChecksum($content);
        $targetVersion = $data['target_version'] ?? null;

        $script = DB::transaction(function () use ($data, $category, $lang, $content, $checksum, $targetVersion) {
            $scriptId = $this->generateId();
            $filename = $data['name'].'.'.$lang;

            return Script::create([
                'script_id' => $scriptId,
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'category' => $category,
                'platform' => $data['platform'] ?? 'linux',
                'version' => $data['version'] ?? 'latest',
                'target_version' => $targetVersion,
                'description' => $data['description'] ?? null,
                'content' => $content,
                'lang' => $lang,
                'checksum' => $checksum,
                'is_standalone' => $data['is_standalone'] ?? true,
                'download_url' => $this->buildDownloadUrl($filename, $targetVersion),
                'uploaded_by' => $data['uploaded_by'] ?? null,
                'status' => 1,
            ]);
        });

        return $script;
    }

    /**
     * 更新脚本内容（创建新版本）
     */
    public function updateContent(int $scriptId, string $newContent, ?string $newVersion = null): Script
    {
        $script = Script::where('script_id', $scriptId)->firstOrFail();
        $script->update([
            'content' => $newContent,
            'checksum' => $this->domain->computeChecksum($newContent),
            'version' => $newVersion ?? $script->version,
        ]);

        return $script->fresh();
    }

    public function listByCategory(string $category, string $platform = 'linux'): array
    {
        return Script::active()
            ->where('category', $category)
            ->forPlatform($platform)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * 获取脚本原始内容（用于直接 curl 下载执行）
     */
    public function getContent(int $scriptId, string $ip, string $userAgent): array
    {
        $script = Script::active()->where('script_id', $scriptId)->firstOrFail();
        Script::where('id', $script->id)->increment('download_cnt');
        DownloadLog::create([
            'resource_type' => 'script',
            'resource_id' => $scriptId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => request()->header('Referer', ''),
            'created_at' => now(),
        ]);

        return [
            'content' => $script->content,
            'lang' => $script->lang,
            'checksum' => $script->checksum,
        ];
    }

    /**
     * 按文件名 + 可选版本查找脚本并返回内容
     *
     * 策略：有 target_version 匹配的用版本专属，否则 fallback 到通用版（target_version IS NULL）
     */
    public function getContentByName(string $baseName, ?string $targetVersion, string $ip, string $userAgent): array
    {
        $script = null;

        if ($targetVersion) {
            $script = Script::active()
                ->where('name', $baseName)
                ->where('target_version', $targetVersion)
                ->first();
        }

        // fallback 到通用版
        $script ??= Script::active()
            ->where('name', $baseName)
            ->whereNull('target_version')
            ->firstOrFail();

        Script::where('id', $script->id)->increment('download_cnt');
        DownloadLog::create([
            'resource_type' => 'script',
            'resource_id' => $script->script_id,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => request()->header('Referer', ''),
            'created_at' => now(),
        ]);

        return [
            'content' => $script->content,
            'lang' => $script->lang,
            'checksum' => $script->checksum,
            'target_version' => $script->target_version,
        ];
    }

    public function disable(int $scriptId): void
    {
        Script::where('script_id', $scriptId)->update(['status' => 0]);
    }

    private function generateId(): int
    {
        return (int) (substr(microtime(true) * 1000, 0, 10).str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    private function buildDownloadUrl(string $filename, ?string $targetVersion = null): string
    {
        $base = rtrim(config('app.url'), '/');
        if ($targetVersion) {
            return "{$base}/d/scripts/{$targetVersion}/{$filename}";
        }

        return "{$base}/d/scripts/{$filename}";
    }

    /**
     * 为已有 Script 生成带扩展名的分发 URL
     */
    public function buildDistUrl(Script $script): string
    {
        $ext = $script->lang ?: 'sh';
        $filename = "{$script->name}.{$ext}";

        return $this->buildDownloadUrl($filename, $script->target_version);
    }

    /**
     * 获取指定产品版本关联的所有脚本 URL 映射
     *
     * 返回 ['install' => '/d/scripts/v1.4.0/install.sh', ...]
     * 对每个 name：有 target_version 匹配的用版本专属，否则 fallback 通用版
     */
    public function getScriptsForVersion(string $targetVersion): array
    {
        $base = rtrim(config('app.url'), '/');

        // 所有通用脚本
        $generic = Script::active()->whereNull('target_version')
            ->standalone()->get()->keyBy('name');

        // 该版本的专属脚本
        $versioned = Script::active()->where('target_version', $targetVersion)
            ->standalone()->get()->keyBy('name');

        $result = [];
        $allNames = $generic->keys()->merge($versioned->keys())->unique();

        foreach ($allNames as $name) {
            $script = $versioned->get($name) ?? $generic->get($name);
            $ext = $script->lang ?: 'sh';
            $filename = "{$name}.{$ext}";

            if ($script->target_version) {
                $result[$name] = "{$base}/d/scripts/{$script->target_version}/{$filename}";
            } else {
                $result[$name] = "{$base}/d/scripts/{$filename}";
            }
        }

        return $result;
    }
}
