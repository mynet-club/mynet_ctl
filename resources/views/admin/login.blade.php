<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 — MyNet CTL Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-gray-100 flex items-center justify-center">

<div class="w-full max-w-sm mx-auto">
    <div class="text-center mb-8">
        <span class="text-3xl">⚙️</span>
        <h1 class="text-xl font-bold text-gray-900 mt-2">MyNet CTL Admin</h1>
        <p class="text-sm text-gray-500 mt-1">使用 API 管理令牌登录</p>
    </div>

    <form method="POST" action="/admin/login" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        @csrf
        <div class="mb-4">
            <label for="token" class="block text-sm font-medium text-gray-700 mb-1">管理令牌</label>
            <input
                id="token"
                name="token"
                type="password"
                placeholder="输入具有 * 权限的 API Token"
                value="{{ old('token') }}"
                required
                autofocus
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
            />
        </div>

        @if($errors->has('token'))
        <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ $errors->first('token') }}
        </div>
        @endif

        <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            登录
        </button>

        <p class="mt-4 text-xs text-gray-400 text-center">
            需要拥有 <code class="font-mono bg-gray-100 px-1 rounded">*</code> 权限的 API Token 才能访问管理后台
        </p>
    </form>

    <div class="mt-4 text-center">
        <a href="/" class="text-sm text-gray-500 hover:text-indigo-600 transition-colors">← 返回首页</a>
    </div>
</div>

</body>
</html>
