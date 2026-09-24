<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\DownloadLog;
use App\Models\Plugin;
use App\Models\Release;
use App\Models\Script;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function login()
    {
        if (session('admin_token_id')) {
            return redirect('/admin');
        }

        return view('admin.login');
    }

    public function doLogin(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $hash = hash('sha256', $request->input('token'));
        $token = ApiToken::active()->where('token_hash', $hash)->first();

        if (! $token || ! $token->hasAbility('*')) {
            return back()->withErrors(['token' => '无效的管理令牌'])->withInput();
        }

        $token->updateQuietly(['last_used_at' => now()]);
        $request->session()->put('admin_token_id', $token->token_id);

        return redirect('/admin');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_token_id');

        return redirect('/admin/login');
    }

    public function index()
    {
        $stats = [
            'releases' => Release::where('status', 1)->count(),
            'scripts' => Script::where('status', 1)->count(),
            'plugins' => Plugin::where('status', 1)->count(),
            'tokens' => ApiToken::where('status', 1)->count(),
            'downloads_7d' => DownloadLog::where('created_at', '>=', now()->subDays(7))->count(),
            'downloads_30d' => DownloadLog::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Recent downloads by type
        $downloadsByType = DownloadLog::select('resource_type', DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('resource_type')
            ->pluck('cnt', 'resource_type')
            ->toArray();

        // Top products by downloads
        $topProducts = Release::select('product', DB::raw('SUM(download_cnt) as total'))
            ->where('status', 1)
            ->groupBy('product')
            ->orderByDesc('total')
            ->get();

        // Platform distribution
        $platformDist = Release::select('platform', DB::raw('SUM(download_cnt) as total'))
            ->where('status', 1)
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get();

        // Arch distribution
        $archDist = Release::select('arch', DB::raw('SUM(download_cnt) as total'))
            ->where('status', 1)
            ->groupBy('arch')
            ->orderByDesc('total')
            ->get();

        // Recent 10 releases
        $recentReleases = Release::where('status', 1)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Daily downloads (last 14 days)
        $dailyDownloads = DownloadLog::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as cnt')
        )
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'downloadsByType', 'topProducts',
            'platformDist', 'archDist', 'recentReleases', 'dailyDownloads'
        ));
    }
}
