<?php

namespace App\Domain\Plugin\Services;

/**
 * Plugin Domain Service
 *
 * 纯业务逻辑：插件分类、平台兼容性、版本规则
 */
class PluginDomainService
{
    const CATEGORIES = ['proxy', 'dns', 'service', 'tool', 'general'];

    const PLATFORMS = ['linux', 'openwrt', 'darwin', 'all'];

    public function validateCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    public function validatePlatform(string $platform): bool
    {
        return in_array($platform, self::PLATFORMS, true);
    }

    /**
     * 判断插件是否兼容目标平台
     */
    public function isCompatible(string $pluginPlatform, string $targetPlatform): bool
    {
        return $pluginPlatform === 'all' || $pluginPlatform === $targetPlatform;
    }

    /**
     * 生成插件文件名
     * 格式: plugin_{name}_{platform}_{arch}_{version}.tgz
     */
    public function generateFilename(string $name, string $platform, string $arch, string $version): string
    {
        $archPart = ($arch === 'all') ? 'universal' : $arch;
        return "plugin_{$name}_{$platform}_{$archPart}_{$version}.tgz";
    }
}
