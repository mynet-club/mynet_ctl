<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Script;
use Illuminate\Http\Request;

class ScriptAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Script::query();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $scripts = $query->orderByDesc('updated_at')->paginate(30);

        return view('admin.scripts.index', compact('scripts'));
    }

    public function create()
    {
        return view('admin.scripts.editor', ['script' => null]);
    }

    public function edit(int $id)
    {
        $script = Script::where('script_id', $id)->firstOrFail();

        return view('admin.scripts.editor', compact('script'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:64',
            'display_name' => 'nullable|string|max:128',
            'category' => 'required|string|in:install,service,setup,upgrade,tool',
            'platform' => 'required|string|in:linux,openwrt,darwin,windows,all',
            'version' => 'nullable|string|max:32',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'lang' => 'nullable|string|in:bash,sh,python',
            'is_standalone' => 'nullable|boolean',
        ]);

        $data['checksum'] = hash('sha256', $data['content']);
        $data['script_id'] = $this->generateId();
        $data['version'] = $data['version'] ?? 'latest';
        $data['lang'] = $data['lang'] ?? 'bash';

        Script::create($data);

        return redirect('/admin/scripts')
            ->with('success', "脚本 {$data['name']} 创建成功");
    }

    public function update(Request $request, int $id)
    {
        $script = Script::where('script_id', $id)->firstOrFail();

        $data = $request->validate([
            'name' => 'required|string|max:64',
            'display_name' => 'nullable|string|max:128',
            'category' => 'required|string|in:install,service,setup,upgrade,tool',
            'platform' => 'required|string|in:linux,openwrt,darwin,windows,all',
            'version' => 'nullable|string|max:32',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'lang' => 'nullable|string|in:bash,sh,python',
            'is_standalone' => 'nullable|boolean',
        ]);

        $data['checksum'] = hash('sha256', $data['content']);

        $script->update($data);

        return redirect('/admin/scripts')
            ->with('success', "脚本 {$script->name} 更新成功");
    }

    public function toggle(int $id)
    {
        $script = Script::where('script_id', $id)->firstOrFail();
        $script->status = $script->status ? 0 : 1;
        $script->save();

        $action = $script->status ? '启用' : '禁用';

        return back()->with('success', "已{$action} {$script->name}");
    }

    private function generateId(): int
    {
        return (int) (microtime(true) * 10000) + random_int(0, 9999);
    }
}
