@extends('layouts.app')

@section('title', 'MyNet CTL')
@section('description', '安装 MyNet 客户端，查询 OpenWrt 设备兼容性，一键下载适合你的版本')

@section('content')

{{-- Hero --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-2xl">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-4">安装 MyNet</h1>
            <p class="text-lg text-gray-600 mb-8">一行命令完成安装，支持 Linux、macOS、Windows 和 OpenWrt 路由器</p>

            {{-- 平台 tab --}}
            <div class="flex gap-2 mb-4" id="platform-tabs">
                @foreach(['linux' => 'Linux', 'darwin' => 'macOS', 'windows' => 'Windows', 'openwrt' => 'OpenWrt'] as $key => $label)
                <button
                    data-platform="{{ $key }}"
                    class="platform-tab px-4 py-2 rounded-md text-sm font-medium border transition-colors
                           {{ $key === 'linux' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400' }}"
                >{{ $label }}</button>
                @endforeach
            </div>

            {{-- 安装命令 --}}
            <div class="bg-gray-900 rounded-xl p-4 sm:p-5 flex items-center justify-between gap-4">
                <code id="install-cmd" class="text-green-400 font-mono text-sm sm:text-base break-all">curl -fsSL {{ $ctlUrl }}/install | bash</code>
                <button id="copy-btn" onclick="copyInstall()" class="shrink-0 text-gray-400 hover:text-white transition-colors p-1" title="复制命令">
                    <svg id="copy-icon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg id="check-icon" class="w-5 h-5 hidden text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>

            {{-- 安心提示 --}}
            <div class="mt-4 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3 text-sm text-emerald-800 flex items-start gap-2">
                <span class="mt-0.5 shrink-0">✅</span>
                <div>
                    <strong>安全透明：</strong>安装脚本全文开源可审计，仅下载官方二进制并放置到 <code class="font-mono bg-emerald-100 px-1 rounded">~/.mynet/</code> 目录，不修改系统文件。
                    <a href="{{ $docsUrl }}/install" target="_blank" class="text-emerald-700 hover:text-emerald-900 underline ml-1">查看脚本源码 →</a>
                </div>
            </div>

            <p class="mt-3 text-sm text-gray-500 flex items-center gap-4">
                <a href="{{ $docsUrl }}/install" target="_blank" class="hover:text-indigo-600 transition-colors">查看安装教程 →</a>
                <a href="/downloads" class="hover:text-indigo-600 transition-colors">手动下载</a>
            </p>
        </div>
    </div>
</section>

{{-- 最新版本 --}}
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-12">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">最新版本</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach(['tui' => ['icon' => '💻', 'name' => 'MyNet Client', 'desc' => '终端配置工具（Linux / macOS / Windows）'],
                  'gnb' => ['icon' => '🔗', 'name' => 'GNB', 'desc' => 'P2P 组网核心二进制'],
                  'utc' => ['icon' => '⚡', 'name' => 'UTC', 'desc' => 'UDP→TCP 转换器 + 加速器']] as $product => $info)
        <div class="bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 transition-colors">
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">{{ $info['icon'] }}</span>
                <div>
                    <div class="font-semibold text-gray-900">{{ $info['name'] }}</div>
                    @if(isset($latestReleases[$product]))
                    <div class="text-xs text-indigo-600 font-mono">{{ $latestReleases[$product]->version }}</div>
                    @else
                    <div class="text-xs text-gray-400">暂无版本</div>
                    @endif
                </div>
            </div>
            <p class="text-sm text-gray-500 mb-4">{{ $info['desc'] }}</p>
            <a href="/downloads?product={{ $product }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">查看所有版本 →</a>
        </div>
        @endforeach
    </div>
</section>

{{-- 快速入口 --}}
<section class="max-w-6xl mx-auto px-4 sm:px-6 pb-12">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="/openwrt" class="group bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
            <div class="flex items-start gap-4">
                <span class="text-3xl">📡</span>
                <div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors mb-1">OpenWrt 路由器</h3>
                    <p class="text-sm text-gray-500">搜索你的路由器型号，自动推荐对应架构的安装包。支持 1930+ 款设备。</p>
                </div>
            </div>
        </a>
        <a href="/tools" class="group bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
            <div class="flex items-start gap-4">
                <span class="text-3xl">🛠️</span>
                <div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors mb-1">脚本与插件</h3>
                    <p class="text-sm text-gray-500">安装脚本、服务脚本、升级工具，支持 curl | bash 直接运行。</p>
                </div>
            </div>
        </a>
        <a href="/setup" class="group bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
            <div class="flex items-start gap-4">
                <span class="text-3xl">📦</span>
                <div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors mb-1">配置部署</h3>
                    <p class="text-sm text-gray-500">登录后一键打包下载节点全部配置文件，快速部署到新设备。</p>
                </div>
            </div>
        </a>
    </div>
</section>

@endsection

@push('scripts')
<script>
const ctlUrl = @json($ctlUrl);
const installCmds = {
    linux:   `curl -fsSL ${ctlUrl}/install | bash`,
    darwin:  `curl -fsSL ${ctlUrl}/install | bash`,
    windows: `irm ${ctlUrl}/install.ps1 | iex`,
    openwrt: `wget -qO- ${ctlUrl}/d/detect/openwrt.sh | sh`,
};

// 自动检测平台
function detectPlatform() {
    const ua = navigator.userAgent.toLowerCase();
    if (ua.includes('win')) return 'windows';
    if (ua.includes('mac')) return 'darwin';
    return 'linux';
}

function setActivePlatform(platform) {
    document.querySelectorAll('.platform-tab').forEach(btn => {
        const isActive = btn.dataset.platform === platform;
        btn.className = btn.className
            .replace(/bg-indigo-600 text-white border-indigo-600|bg-white text-gray-600 border-gray-300 hover:border-indigo-400/g, '');
        if (isActive) {
            btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
        } else {
            btn.classList.add('bg-white', 'text-gray-600', 'border-gray-300', 'hover:border-indigo-400');
        }
    });
    document.getElementById('install-cmd').textContent = installCmds[platform] || installCmds.linux;
}

document.querySelectorAll('.platform-tab').forEach(btn => {
    btn.addEventListener('click', () => setActivePlatform(btn.dataset.platform));
});

setActivePlatform(detectPlatform());

function copyInstall() {
    const cmd = document.getElementById('install-cmd').textContent;
    navigator.clipboard.writeText(cmd).then(() => {
        document.getElementById('copy-icon').classList.add('hidden');
        document.getElementById('check-icon').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('copy-icon').classList.remove('hidden');
            document.getElementById('check-icon').classList.add('hidden');
        }, 2000);
    });
}
</script>
@endpush
