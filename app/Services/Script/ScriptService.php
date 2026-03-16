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
        $lang     = $data['lang'] ?? $this->domain->detectLang($data['content'] ?? '');

        if (!$this->domain->validateCategory($category)) {
            throw new RuntimeException('无效的脚本分类');
        }
        if (!$this->domain->validateLang($lang)) {
            throw new RuntimeException('无效的脚本语言');
        }

        $content  = $data['content'];
        $checksum = $this->domain->computeChecksum($content);

        $script = DB::transaction(function () use ($data, $category, $lang, $content, $checksum) {
            $scriptId = $this->generateId();

            return Script::create([
                'script_id'    => $scriptId,
                'name'         => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'category'     => $category,
                'platform'     => $data['platform'] ?? 'linux',
                'version'      => $data['version'] ?? 'latest',
                'description'  => $data['description'] ?? null,
                'content'      => $content,
                'lang'         => $lang,
                'checksum'     => $checksum,
                'is_standalone'=> $data['is_standalone'] ?? true,
                'download_url' => $this->buildDownloadUrl($scriptId, $data['name']),
                'uploaded_by'  => $data['uploaded_by'] ?? null,
                'status'       => 1,
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
            'content'  => $newContent,
            'checksum' => $this->domain->computeChecksum($newContent),
            'version'  => $newVersion ?? $script->version,
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
            'resource_id'   => $scriptId,
            'ip'            => $ip,
            'user_agent'    => $userAgent,
            'referer'       => request()->header('Referer', ''),
            'created_at'    => now(),
        ]);
        return [
            'content'  => $script->content,
            'lang'     => $script->lang,
            'checksum' => $script->checksum,
        ];
    }

    public function disable(int $scriptId): void
    {
        Script::where('script_id', $scriptId)->update(['status' => 0]);
    }

    private function generateId(): int
    {
        return (int) (substr(microtime(true) * 1000, 0, 10) . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    private function buildDownloadUrl(int $id, string $name): string
    {
        return rtrim(config('app.url'), '/') . "/api/v1/scripts/{$id}/raw/{$name}.sh";
    }
}
