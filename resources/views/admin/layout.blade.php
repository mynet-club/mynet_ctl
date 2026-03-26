<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '管理后台') — MyNet CTL Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-100 flex">

    {{-- 侧边栏 --}}
    <aside class="w-56 bg-gray-900 text-gray-100 flex flex-col min-h-screen shrink-0">
        <div class="px-5 py-5 border-b border-gray-800">
            <a href="/admin" class="flex items-center gap-2 font-bold text-white">
                <span class="text-lg">⚙️</span>
                <span>CTL <span class="text-indigo-400 font-mono text-xs">Admin</span></span>
            </a>
        </div>
        <nav class="flex-1 py-4 space-y-1 px-3">
            <a href="/admin" class="sidebar-link {{ request()->is('admin') && !request()->is('admin/*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                数据看板
            </a>
            <a href="/admin/releases" class="sidebar-link {{ request()->is('admin/releases*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
                版本管理
            </a>
            <a href="/admin/scripts" class="sidebar-link {{ request()->is('admin/scripts*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                脚本管理
            </a>
            <a href="/admin/plugins" class="sidebar-link {{ request()->is('admin/plugins*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                插件管理
            </a>
            <a href="/admin/tokens" class="sidebar-link {{ request()->is('admin/tokens*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }} flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                API 令牌
            </a>
        </nav>
        <div class="border-t border-gray-800 px-3 py-3">
            <div class="flex items-center justify-between">
                <a href="/" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← 返回前台</a>
                <form action="/admin/logout" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-xs text-gray-500 hover:text-red-400 transition-colors">退出</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- 主内容区 --}}
    <div class="flex-1 flex flex-col min-w-0">
        {{-- 顶部栏 --}}
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-900">@yield('title', '管理后台')</h1>
            @yield('actions')
        </header>

        {{-- Flash 消息 --}}
        @if(session('success'))
        <div class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700" id="flash-success">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
        @endif

        {{-- 页面内容 --}}
        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>

    <script>
        // Auto-hide flash messages
        setTimeout(() => {
            document.getElementById('flash-success')?.remove();
        }, 5000);
    </script>
    @stack('scripts')
</body>
</html>
