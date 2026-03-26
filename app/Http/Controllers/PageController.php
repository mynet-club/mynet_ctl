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
        $latestReleases = [];
        foreach (['tui', 'gnb', 'utc'] as $product) {
            $r = $this->releaseService->getLatest($product, 'linux', 'amd64');
            if ($r) {
                $latestReleases[$product] = $r;
            }
        }

        return view('pages.home', compact('latestReleases'));
    }

    public function downloads(Request $request)
    {
        $product = $request->query('product', 'tui');
        $allowedProducts = ['tui', 'gnb', 'utc'];
        if (!in_array($product, $allowedProducts)) {
            $product = 'tui';
        }

        // Group flat release records by version for display
        $releases = [];
        foreach ($allowedProducts as $p) {
            $flat = $this->releaseService->listByProduct($p);
            $grouped = [];
            foreach ($flat as $r) {
                $ver = $r['version'];
                if (!isset($grouped[$ver])) {
                    $grouped[$ver] = [
                        'version'   => $ver,
                        'is_latest' => false,
                        'is_stable' => (bool)($r['is_stable'] ?? false),
                        'changelog' => $r['changelog'] ?? '',
                        'date'      => $r['created_at'] ?? null,
                        'files'     => [],
                    ];
                }
                if (!empty($r['is_latest'])) {
                    $grouped[$ver]['is_latest'] = true;
                }
                $grouped[$ver]['files'][] = array_merge($r, [
                    'display_product' => $this->releaseDomain->getProductFilenamePrefix($p),
                ]);
            }
            $releases[$p] = array_values($grouped);
        }

        return view('pages.downloads', compact('releases', 'product'));
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
