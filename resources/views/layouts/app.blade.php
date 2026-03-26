<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MyNet CTL') — 软件分发中心</title>
    <meta name="description" content="@yield('description', 'MyNet 软件分发中心：下载客户端、查询 OpenWrt 设备兼容性、管理安装脚本')">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/images/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/images/icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 flex flex-col">

    {{-- 顶部导航 --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="/" class="flex items-center gap-2 font-bold text-lg text-gray-900 hover:opacity-85 transition-opacity">
                        <img src="/images/icon.png" alt="MyNet" class="h-8 w-8 rounded-xl">
                        <span>MyNet <span class="text-indigo-600 font-mono text-sm font-normal">CTL</span></span>
                    </a>
                    <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                        <a href="/" class="nav-link {{ request()->is('/') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }} transition-colors">首页</a>
                        <a href="/downloads" class="nav-link {{ request()->is('downloads*') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }} transition-colors">下载</a>
                        <a href="/openwrt" class="nav-link {{ request()->is('openwrt*') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }} transition-colors">OpenWrt</a>
                        <a href="/tools" class="nav-link {{ request()->is('tools*') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }} transition-colors">工具</a>
                        <div class="relative group">
                            <a href="/setup" class="nav-link inline-flex items-center gap-1 {{ request()->is('setup*') || request()->is('quickconfig*') ? 'text-indigo-600' : 'text-gray-600 hover:text-gray-900' }} transition-colors">
                                配置部署
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </a>
                            <div class="absolute left-0 top-full pt-1 w-40 z-50 hidden group-hover:block">
                                <div class="bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                                    <a href="/setup" class="block px-4 py-2 text-sm {{ request()->is('setup*') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">配置部署</a>
                                    <a href="/quickconfig" class="block px-4 py-2 text-sm {{ request()->is('quickconfig*') ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">快配工具</a>
                                </div>
                            </div>
                        </div>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ $wwwUrl }}" class="hidden sm:inline-flex text-sm text-gray-500 hover:text-gray-900 transition-colors">mynet.club →</a>
                    <a href="/admin" class="text-sm px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 hover:border-indigo-400 hover:text-indigo-600 transition-colors">管理</a>
                </div>
                {{-- 移动端菜单按钮 --}}
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-900" aria-label="菜单">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            {{-- 移动端导航 --}}
            <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100 py-3 space-y-1">
                <a href="/" class="block px-2 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600">首页</a>
                <a href="/downloads" class="block px-2 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600">下载</a>
                <a href="/openwrt" class="block px-2 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600">OpenWrt</a>
                <a href="/tools" class="block px-2 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600">工具</a>
                <a href="/setup" class="block px-2 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600">配置部署</a>
                <a href="/quickconfig" class="block px-6 py-2 text-sm text-gray-500 hover:text-indigo-600">└ 快配工具</a>
            </div>
        </div>
    </header>

    {{-- 主内容 --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- 页脚 --}}
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <img src="/images/icon.png" alt="MyNet" class="h-5 w-5 opacity-70 rounded-md">
                    <span>MyNet CTL — 软件分发中心</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="{{ $wwwUrl }}" class="hover:text-gray-900 transition-colors">mynet.club</a>
                    <a href="{{ $guiUrl }}" class="hover:text-gray-900 transition-colors">用户控制台</a>
                    <a href="https://github.com/gnbdev/opengnb" target="_blank" class="hover:text-gray-900 transition-colors inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        OpenGNB
                    </a>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-100 text-center text-xs text-gray-400">
                © {{ date('Y') }} MyNet. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });
    </script>
    @stack('scripts')
</body>
</html>
