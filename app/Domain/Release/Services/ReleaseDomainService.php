<?php

namespace App\Domain\Release\Services;

/**
 * Release Domain Service
 *
 * 纯业务逻辑：版本号校验、平台/架构矩阵、latest 标记规则
 * 不依赖框架或数据库
 */
class ReleaseDomainService
{
    // 支持的产品
    const PRODUCTS = ['tui', 'utc', 'gnb'];

    // 支持的平台
    const PLATFORMS = ['linux', 'openwrt', 'darwin', 'windows'];

    // 支持的架构
    const ARCHITECTURES = [
        'amd64', 'arm64', 'armv7', 'i386',
        'mips', 'mips64', 'mipsel', 'mips64el',
        'mips-softfp', 'mipsel-softfp',
        'arm64-softfp', 'armv7-softfp',
        'riscv64',
    ];

    // 有效的 platform+arch 组合（openwrt 不支持所有架构）
    const VALID_COMBINATIONS = [
        'linux'   => ['amd64', 'arm64', 'armv7', 'i386', 'mips', 'mips64', 'mipsel', 'mips64el', 'mips-softfp', 'mipsel-softfp', 'arm64-softfp', 'armv7-softfp', 'riscv64'],
        'openwrt' => ['amd64', 'arm64', 'armv7', 'i386', 'mips', 'mips64', 'mipsel', 'mips64el', 'mips-softfp', 'mipsel-softfp', 'arm64-softfp', 'armv7-softfp'],
        'darwin'  => ['amd64', 'arm64'],
        'windows' => ['amd64', 'arm64'],
    ];

    public function validateProduct(string $product): bool
    {
        return in_array($product, self::PRODUCTS, true);
    }

    public function validatePlatform(string $platform): bool
    {
        return in_array($platform, self::PLATFORMS, true);
    }

    public function validateArch(string $arch): bool
    {
        return in_array($arch, self::ARCHITECTURES, true);
    }

    public function validateCombination(string $platform, string $arch): bool
    {
        return in_array($arch, self::VALID_COMBINATIONS[$platform] ?? [], true);
    }

    /**
     * 生成标准文件名
     * 格式: {product}_{platform}_{arch}_{version}.tgz
     * 示例: mynet_linux_amd64_v1.3.0.tgz
     */
    public function generateFilename(string $product, string $platform, string $arch, string $version, string $variant = ''): string
    {
        $archSuffix = $variant ? "{$arch}-{$variant}" : $arch;
        return "{$product}_{$platform}_{$archSuffix}_{$version}.tgz";
    }

    /**
     * 校验版本号格式（语义化版本，允许带 v 前缀）
     */
    public function validateVersion(string $version): bool
    {
        return (bool) preg_match('/^v?\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', $version);
    }

    /**
     * 比较版本号大小，返回 1/-1/0
     */
    public function compareVersions(string $a, string $b): int
    {
        return version_compare(ltrim($a, 'v'), ltrim($b, 'v'));
    }
}
