<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Release\Services\ReleaseDomainService;
use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Services\Release\ReleaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReleaseAdminController extends Controller
{
    public function __construct(private ReleaseService $service) {}

    public function index(Request $request)
    {
        $query = Release::query();

        if ($product = $request->query('product')) {
            $query->where('product', $product);
        }
        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }
        if ($request->query('status') !== null && $request->query('status') !== '') {
            $query->where('status', (int) $request->query('status'));
        }

        $releases = $query->orderByDesc('created_at')->paginate(30);

        return view('admin.releases.index', compact('releases'));
    }

    public function create()
    {
        // 产品/平台清单取自 domain SSOT，不在控制器里另抄一份
        // （曾经硬编码 in:tui,gnb,utc,client 而漏掉 mynet_agent → 传不上去）。
        return view('admin.releases.create', [
            'products' => ReleaseDomainService::PRODUCTS,
            'platforms' => ReleaseDomainService::PLATFORMS,
        ]);
    }

    public function store(Request $request)
    {
        // 白名单与 domain SSOT 同源：硬编码清单会漂（曾漏 mynet_agent / openwrt-sdk）。
        $data = $request->validate([
            'product' => ['required', 'string', Rule::in(array_keys(ReleaseDomainService::PRODUCTS))],
            'version' => 'required|string|max:32',
            'platform' => ['required', 'string', Rule::in(array_keys(ReleaseDomainService::PLATFORMS))],
            'arch' => 'required|string|max:32',
            'variant' => 'nullable|string|max:32',
            'file' => 'required|file|max:204800',
            'changelog' => 'nullable|string',
            'is_stable' => 'nullable|boolean',
        ]);

        $release = $this->service->upload($data, $request->file('file'));

        return redirect('/admin/releases')
            ->with('success', "版本 {$release->product} {$release->version} 上传成功");
    }

    public function toggle(int $id)
    {
        $release = Release::where('release_id', $id)->firstOrFail();
        $release->status = $release->status ? 0 : 1;
        $release->save();

        $action = $release->status ? '启用' : '禁用';

        return back()->with('success', "已{$action} {$release->product} {$release->version}");
    }

    public function destroy(int $id)
    {
        $release = Release::where('release_id', $id)->firstOrFail();
        $release->update(['status' => 0]);

        return back()->with('success', "已下架 {$release->product} {$release->version}");
    }
}
