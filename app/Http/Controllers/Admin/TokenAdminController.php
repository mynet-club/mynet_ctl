<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenAdminController extends Controller
{
    public function index()
    {
        $tokens = ApiToken::orderByDesc('created_at')->paginate(30);

        return view('admin.tokens.index', compact('tokens'));
    }

    public function create()
    {
        return view('admin.tokens.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $plainToken = Str::random(48);
        $tokenId = (int) (microtime(true) * 10000) + random_int(0, 9999);

        ApiToken::create([
            'token_id' => $tokenId,
            'name' => $data['name'],
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => $data['abilities'],
            'expires_at' => $data['expires_at'] ?? null,
            'status' => 1,
        ]);

        return redirect('/admin/tokens')
            ->with('success', '令牌创建成功')
            ->with('new_token', $plainToken);
    }

    public function toggle(int $id)
    {
        $token = ApiToken::where('token_id', $id)->firstOrFail();

        // Don't allow disabling the currently logged-in token
        $currentTokenId = session('admin_token_id');
        if ($token->token_id === $currentTokenId && $token->status) {
            return back()->with('error', '不能禁用当前登录使用的令牌');
        }

        $token->status = $token->status ? 0 : 1;
        $token->save();

        $action = $token->status ? '启用' : '禁用';

        return back()->with('success', "已{$action}令牌「{$token->name}」");
    }

    public function destroy(int $id)
    {
        $token = ApiToken::where('token_id', $id)->firstOrFail();

        $currentTokenId = session('admin_token_id');
        if ($token->token_id === $currentTokenId) {
            return back()->with('error', '不能删除当前登录使用的令牌');
        }

        $token->delete();

        return back()->with('success', "已删除令牌「{$token->name}」");
    }
}
