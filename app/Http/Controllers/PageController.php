<?php

namespace App\Http\Controllers;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Services\Plugin\PluginService;
use App\Services\Release\ReleaseService;
use App\Services\Script\ScriptService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        private ReleaseService $releaseService,
        private ScriptService $scriptService,
        private PluginService $pluginService,
        private ReleaseDomainService $releaseDomain,
    ) {}

    public function home()
    {
        // 产品面取自 domain SSOT（ReleaseDomainService::PRODUCTS），不在控制器里另抄一份
        // （曾硬编码 ['tui','gnb','utc'] → mynet_agent/lua/proxy 产物「上传得了、页面看不到」）。
        $products = ReleaseDomainService::PRODUCTS;

        $latestReleases = [];
        foreach (array_keys($products) as $product) {
            $r = $this->releaseService->getLatest($product, 'linux', 'amd64');
            if ($r) {
                $latestReleases[$product] = $r;
            }
        }

        return view('pages.home', compact('latestReleases', 'products'));
    }

    public function downloads(Request $request)
    {
        // 白名单与 domain SSOT 同源（同 ReleaseAdminController::store）：硬编码清单会漂。
        $productLabels = array_map(static fn ($p) => $p['name'], ReleaseDomainService::PRODUCTS);

        $product = $request->query('product', 'tui');
        if (! array_key_exists($product, $productLabels)) {
            $product = array_key_first($productLabels);
        }

        // Group flat release records by version for display
        $releases = [];
        foreach (array_keys($productLabels) as $p) {
            $flat = $this->releaseService->listByProduct($p);
            $grouped = [];
            foreach ($flat as $r) {
                $ver = $r['version'];
                if (! isset($grouped[$ver])) {
                    $grouped[$ver] = [
                        'version' => $ver,
                        'is_latest' => false,
                        'is_stable' => (bool) ($r['is_stable'] ?? false),
                        'changelog' => $r['changelog'] ?? '',
                        'date' => $r['created_at'] ?? null,
                        'files' => [],
                    ];
                }
                if (! empty($r['is_latest'])) {
                    $grouped[$ver]['is_latest'] = true;
                }
                $grouped[$ver]['files'][] = array_merge($r, [
                    'display_product' => $this->releaseDomain->getProductFilenamePrefix($p),
                ]);
            }
            $releases[$p] = array_values($grouped);
        }

        return view('pages.downloads', compact('releases', 'product', 'productLabels'));
    }

    public function openwrt()
    {
        return view('pages.openwrt');
    }

    public function tools(Request $request)
    {
        $platform = $request->query('platform', 'linux');
        $category = $request->query('category', '');

        $scripts = $this->scriptService->listByCategory($category ?: 'install', $platform);
        $plugins = $this->pluginService->listByPlatform($platform);

        return view('pages.tools', compact('scripts', 'plugins', 'platform', 'category'));
    }

    public function setup()
    {
        return view('pages.setup');
    }

    public function quickconfig()
    {
        return view('pages.quickconfig');
    }
}
