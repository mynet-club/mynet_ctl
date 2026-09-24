<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Illuminate\Http\Request;

class PluginAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Plugin::query();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $plugins = $query->orderByDesc('updated_at')->paginate(30);

        return view('admin.plugins.index', compact('plugins'));
    }

    public function create()
    {
        return view('admin.plugins.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:64',
            'display_name' => 'required|string|max:128',
            'category' => 'required|string|in:proxy,dns,service,tool,general',
            'version' => 'required|string|max:32',
            'platform' => 'required|string|in:linux,openwrt,darwin,windows,all',
            'arch' => 'nullable|string|max:32',
            'description' => 'nullable|string',
            'file' => 'required|file|max:102400',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();
        $pluginId = (int) (microtime(true) * 10000) + random_int(0, 9999);

        $path = $file->storeAs(
            "plugins/{$data['name']}/{$data['version']}",
            $filename
        );

        Plugin::create([
            'plugin_id' => $pluginId,
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'category' => $data['category'],
            'version' => $data['version'],
            'platform' => $data['platform'],
            'arch' => $data['arch'] ?? 'all',
            'description' => $data['description'] ?? '',
            'filename' => $filename,
            'file_path' => $path,
            'file_size' => $size,
            'sha256' => $sha256,
            'download_url' => url("/api/v2/plugins/{$pluginId}/download/{$filename}"),
            'status' => 1,
        ]);

        return redirect('/admin/plugins')
            ->with('success', "插件 {$data['display_name']} 上传成功");
    }

    public function toggle(int $id)
    {
        $plugin = Plugin::where('plugin_id', $id)->firstOrFail();
        $plugin->status = $plugin->status ? 0 : 1;
        $plugin->save();

        $action = $plugin->status ? '启用' : '禁用';

        return back()->with('success', "已{$action} {$plugin->display_name}");
    }
}
