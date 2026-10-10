<?php

namespace App\Services\Manifest;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Models\Plugin;
use App\Models\Release;
use App\Services\Script\ScriptService;

/**
 * ManifestService
 *
 * 从 releases 表动态生成与 download.mynet.club 兼容的 manifest.json 和 apps.json。
 *
 * 兼容性保证：
 *   - schema_version: "1.1"  与旧 manifest 一致
 *   - artifacts 字段包含 os/arch/file/url/ext/size/sha256
 *   - tui 使用 "mynet" 作为产品名称查询，通过别名映射到 CTL 内部的 "tui"
 *
 * v2.1 新增：
 *   - apps.json 同时返回 products（结构化）和 apps（扁平兼容旧客户端）
 *   - products 含 plugins 子字段（Node.js 插件清单）
 */
class ManifestService
{
    public function __construct(
        private readonly ScriptService $scriptService
    ) {}

    /**
     * 外部名称 → CTL 内部 product 名称
     * tui 二进制在旧体系中称为 "mynet"
     */
    private const ALIASES = [
        'mynet' => 'tui',
    ];

    /**
     * client 产品的 Node.js 插件定义（静态，后续可迁移到数据库）
     */
    private const CLIENT_PLUGINS = [
        'heartbeat' => [
            'name' => '@mynet/heartbeat',
            'description' => '心跳守护插件',
            'required' => false,
            'type' => 'nodejs',
        ],
        'updater' => [
            'name' => '@mynet/updater',
            'description' => '自动更新插件',
            'required' => false,
            'type' => 'nodejs',
        ],
        'proxy' => [
            'name' => '@mynet/proxy',
            'description' => '代理路由插件',
            'required' => false,
            'type' => 'nodejs',
        ],
    ];

    /**
     * 生成 apps.json 索引 — v2.1 格式
     *
     * 同时返回：
     *   - products: 结构化产品信息（新客户端用）
     *   - apps: 扁平 manifest URL 映射（旧客户端兼容）
     *   - aliases: 名称别名表
     */
    public function getAppsIndex(): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $productDefs = ReleaseDomainService::PRODUCTS;

        // 获取所有有效产品及其最新版本
        $latestVersions = Release::where('status', 1)
            ->selectRaw('product, MAX(version) as latest_version')
            ->groupBy('product')
            ->pluck('latest_version', 'product')
            ->all();

        // === v1 扁平格式（向后兼容） ===
        $apps = [];
        foreach (array_keys($latestVersions) as $product) {
            $externalKey = $this->toExternalName($product);
            $apps[$externalKey] = "{$baseUrl}/d/{$externalKey}/manifest.json";
            if ($externalKey !== $product) {
                $apps[$product] = "{$baseUrl}/d/{$product}/manifest.json";
            }
        }

        // === v2.1 结构化格式 ===
        $products = [];
        foreach ($latestVersions as $product => $latestVersion) {
            $externalKey = $this->toExternalName($product);
            $def = $productDefs[$product] ?? [];

            $entry = [
                'name' => $def['name'] ?? $externalKey,
                'description' => $def['desc'] ?? '',
                'latest' => $latestVersion,
                'manifest_url' => "/d/{$externalKey}/manifest.json",
            ];

            // tui 标记为废弃
            if ($product === 'tui') {
                $entry['deprecated'] = true;
                $entry['successor'] = 'client';
            }

            // client 产品附带插件清单
            if ($product === 'client') {
                $entry['plugins'] = $this->getClientPlugins($latestVersion);
            }

            $products[$externalKey] = $entry;
        }

        // 补充尚未有 release 的已定义产品（如 client 可能还没有 release）
        foreach ($productDefs as $product => $def) {
            $externalKey = $def['alias'] ?? $product;
            if (! isset($products[$externalKey]) && ! isset($products[$product])) {
                $entry = [
                    'name' => $def['name'],
                    'description' => $def['desc'],
                    'latest' => null,
                    'manifest_url' => "/d/{$product}/manifest.json",
                ];
                if ($product === 'client') {
                    $entry['plugins'] = $this->getClientPlugins(null);
                }
                $products[$product] = $entry;
            }
        }

        return [
            'schema_version' => '2.1',
            'generated_at' => now()->toIso8601String(),
            'products' => $products,
            'apps' => $apps,        // 旧客户端兼容
            'aliases' => ['tui' => 'mynet'],
        ];
    }

    /**
     * 获取 client 产品的插件清单
     */
    private function getClientPlugins(?string $clientVersion): array
    {
        $plugins = [];
        foreach (self::CLIENT_PLUGINS as $slug => $def) {
            // 查询该插件是否有对应的 plugin release
            $latestPlugin = Plugin::where('name', $slug)
                ->where('status', 1)
                ->orderBy('created_at', 'desc')
                ->first();

            $plugins[$slug] = array_merge($def, [
                'latest' => $latestPlugin->version ?? null,
            ]);
        }

        return $plugins;
    }

    /**
     * 生成 {product}/plugins.json — 某产品可用的插件清单
     *
     * @param  string  $product  产品名（如 client）
     * @return array|null null 表示该产品无插件
     */
    public function getPlugins(string $product): ?array
    {
        $internalProduct = $this->toInternalName($product);

        // 目前只有 client 有 Node.js 插件
        if ($internalProduct !== 'client') {
            return null;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $plugins = [];

        foreach (self::CLIENT_PLUGINS as $slug => $def) {
            // 获取该插件最新的 release（支持 tgz 打包的 nodejs 插件）
            $latestPlugin = Plugin::where('name', $slug)
                ->where('status', 1)
                ->orderBy('created_at', 'desc')
                ->first();

            $entry = [
                'name' => $slug,
                'full_name' => $def['name'],
                'description' => $def['description'],
                'version' => $latestPlugin->version ?? null,
                'type' => $def['type'],
                'required' => $def['required'],
            ];

            if ($latestPlugin) {
                $entry['download_url'] = "{$baseUrl}/d/plugins/{$latestPlugin->filename}";
                $entry['sha256'] = $latestPlugin->sha256;
                $entry['size'] = (int) $latestPlugin->file_size;
            }

            $plugins[] = $entry;
        }

        return [
            'schema_version' => '1.0',
            'product' => $product,
            'generated_at' => now()->toIso8601String(),
            'plugins' => $plugins,
        ];
    }

    /**
     * 生成某个产品的 manifest.json（兼容 tui updater 的 schema v1.1 格式）
     *
     * @param  string  $product  外部名称（如 mynet/tui/gnb/utc）
     * @return array|null null 表示没有该产品的 release
     */
    public function getManifest(string $product): ?array
    {
        $internalProduct = $this->toInternalName($product);

        $releases = Release::where('product', $internalProduct)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($releases->isEmpty()) {
            return null;
        }

        // 找全局最新稳定版（任意 platform/arch 中，同 version 即可）
        $latestStableVersion = Release::where('product', $internalProduct)
            ->where('status', 1)
            ->where('is_stable', 1)
            ->where('is_latest', 1)
            ->orderBy('created_at', 'desc')
            ->value('version');

        // 仍兼容：如无 is_latest 标记，退回按创建时间取最新稳定版的 version
        if (! $latestStableVersion) {
            $latestStableVersion = Release::where('product', $internalProduct)
                ->where('status', 1)
                ->where('is_stable', 1)
                ->orderBy('created_at', 'desc')
                ->value('version');
        }

        // 按版本分组
        $versionGroups = $releases->groupBy('version');

        $versions = [];
        foreach ($versionGroups as $version => $versionReleases) {
            $artifacts = [];
            $checksums = [];
            $matrixMap = [];

            foreach ($versionReleases as $release) {
                $archFull = $release->variant
                    ? "{$release->arch}-{$release->variant}"
                    : $release->arch;

                $ext = pathinfo($release->filename, PATHINFO_EXTENSION) ?: 'tgz';

                // 统一使用 /d/ 分发路径
                $displayName = (new ReleaseDomainService)->getProductFilenamePrefix($internalProduct);
                $dlFilename = (new ReleaseDomainService)->generateFilename($internalProduct, $release->platform, $release->arch, $release->version);
                $url = rtrim(config('app.url'), '/')."/d/{$displayName}/{$dlFilename}";

                $artifacts[] = [
                    'os' => $release->platform,
                    'arch' => $archFull,
                    'file' => $release->filename,
                    'url' => $url,
                    'ext' => $ext,
                    'kind' => $release->kind ?: (new ReleaseDomainService)->artifactKindForExt($ext),
                    'size' => (int) $release->file_size,
                    'sha256' => $release->sha256,
                ];

                $checksums[$release->filename] = $release->sha256;

                // 构建 matrix（按 os 聚合 arch 列表）
                $os = $release->platform;
                if (! isset($matrixMap[$os])) {
                    $matrixMap[$os] = ['os' => $os, 'arch' => [], 'ext' => $ext];
                }
                if (! in_array($archFull, $matrixMap[$os]['arch'], true)) {
                    $matrixMap[$os]['arch'][] = $archFull;
                }
            }

            $firstRelease = $versionReleases->first();
            $channel = $firstRelease->is_stable ? 'stable' : 'beta';

            $versions[$version] = [
                'released_at' => $firstRelease->created_at->toIso8601String(),
                'channel' => $channel,
                'checksums' => $checksums,
                'matrix' => array_values($matrixMap),
                'artifacts' => $artifacts,
                'scripts' => $this->scriptService->getScriptsForVersion($version),
            ];
        }

        // 版本排序：依赖语义化版本（gnb 的 v1.6.0.a 也能 fallback 到字符串排序）
        uksort($versions, fn ($a, $b) => $this->compareVersionDesc($a, $b));

        return [
            'schema_version' => '1.1',
            'project' => $product,             // 对客户端暴露外部名称
            'generated_at' => now()->toIso8601String(),
            'latest' => [
                'stable' => $latestStableVersion,
                'beta' => null,
                'nightly' => null,
            ],
            'latest_stable' => $latestStableVersion,
            'versions' => $versions,
        ];
    }

    // -------------------------------------------------------------------------

    /** "mynet" 等外部别名 → 内部产品名 */
    private function toInternalName(string $external): string
    {
        return self::ALIASES[$external] ?? $external;
    }

    /** 内部产品名 → 对外主要别名（tui → mynet） */
    private function toExternalName(string $internal): string
    {
        $flip = array_flip(self::ALIASES);

        return $flip[$internal] ?? $internal;
    }

    /**
     * 降序版本比较（newest first）
     * 支持语义化版本（v1.2.3）和带字母后缀（v1.6.0.a）
     */
    private function compareVersionDesc(string $a, string $b): int
    {
        $clean = fn (string $v) => ltrim($v, 'v');
        $cmp = version_compare($clean($b), $clean($a));
        if ($cmp !== 0) {
            return $cmp;
        }

        // version_compare 认为相等时，按字符串倒序
        return strcmp($b, $a);
    }
}
