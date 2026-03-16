<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Token 认证中间件
 *
 * 用法: Authorization: Bearer {token}
 * 权限检查: $request->attributes->get('api_token')->hasAbility('release:upload')
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hash  = hash('sha256', $bearer);
        $token = ApiToken::active()->where('token_hash', $hash)->first();

        if (!$token) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        foreach ($abilities as $ability) {
            if (!$token->hasAbility($ability)) {
                return response()->json(['message' => "Missing ability: {$ability}"], 403);
            }
        }

        $token->updateQuietly(['last_used_at' => now()]);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
