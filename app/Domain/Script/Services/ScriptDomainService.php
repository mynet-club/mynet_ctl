<?php

namespace App\Domain\Script\Services;

/**
 * Script Domain Service
 *
 * 纯业务逻辑：脚本分类、校验和计算、下载 URL 生成
 */
class ScriptDomainService
{
    const CATEGORIES = ['install', 'service', 'setup', 'upgrade', 'tool'];

    const PLATFORMS = ['linux', 'openwrt', 'darwin', 'all'];

    const LANGS = ['bash', 'sh', 'python'];

    public function validateCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    public function validateLang(string $lang): bool
    {
        return in_array($lang, self::LANGS, true);
    }

    /**
     * 计算脚本内容的 SHA256
     */
    public function computeChecksum(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * 从脚本内容中提取 shebang 行，推断语言
     */
    public function detectLang(string $content): string
    {
        $firstLine = strtok($content, "\n");
        if (str_contains($firstLine, 'python')) {
            return 'python';
        }
        if (str_contains($firstLine, 'bash')) {
            return 'bash';
        }

        return 'sh';
    }
}
