<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin 后台认证中间件
 *
 * 通过 session 中存储的 api_token_id 验证管理员身份。
 * 要求 token 拥有 '*' 权限。
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenId = $request->session()->get('admin_token_id');

        if (!$tokenId) {
            return redirect('/admin/login');
        }

        $token = ApiToken::active()
            ->where('token_id', $tokenId)
            ->first();

        if (!$token || !$token->hasAbility('*')) {
            $request->session()->forget('admin_token_id');
            return redirect('/admin/login');
        }

        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
