@extends('layouts.app')

@section('title', '版本下载 — MyNet CTL')
@section('description', '下载 MyNet 客户端、GNB 和 UTC 各平台二进制文件')

@section('content')

<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">版本下载</h1>
    <p class="text-gray-500 mb-8">选择产品和平台，下载对应架构的安装包</p>

    {{-- 产品 tab --}}
    <div class="flex gap-2 mb-8 border-b border-gray-200" id="product-tabs">
        @foreach(['tui' => 'MyNet Client', 'gnb' => 'GNB', 'utc' => 'UTC'] as $key => $label)
        <button
            data-product="{{ $key }}"
            class="product-tab px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors
                   {{ $key === ($product ?? 'tui') ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-800' }}"
        >{{ $label }}</button>
        @endforeach
    </div>

    {{-- 版本列表 --}}
    @foreach(['tui' => 'MyNet Client', 'gnb' => 'GNB', 'utc' => 'UTC'] as $productKey => $productLabel)
    <div class="product-panel {{ $productKey !== ($product ?? 'tui') ? 'hidden' : '' }}" data-product="{{ $productKey }}">
        @php $productVersions = $releases[$productKey] ?? [] @endphp
        @if(count($productVersions) === 0)
        <div class="py-16 text-center text-gray-400">暂无版本</div>
        @else

        {{-- 平台筛选 --}}
        <div class="flex flex-wrap gap-2 mb-6">
            <button data-platform="all" class="pf-btn active px-3 py-1.5 rounded-full text-xs font-medium bg-indigo-600 text-white transition-colors">全部</button>
            @foreach(['linux' => 'Linux', 'darwin' => 'macOS', 'windows' => 'Windows', 'openwrt' => 'OpenWrt'] as $pKey => $pLabel)
            <button data-platform="{{ $pKey }}" class="pf-btn px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">{{ $pLabel }}</button>
            @endforeach
        </div>

        {{-- 版本条目 --}}
        <div class="space-y-4">
            @foreach($productVersions as $versionGroup)
            @php
                $platforms = array_unique(array_column($versionGroup['files'], 'platform'));
                $isLatest = !empty($versionGroup['is_latest']);
                $date = $versionGroup['date'] ? \Carbon\Carbon::parse($versionGroup['date'])->format('Y-m-d') : '';
            @endphp
            <div class="release-card bg-white rounded-xl border border-gray-200 overflow-hidden"
                 data-platforms="{{ implode(' ', $platforms) }}">
                {{-- 版本头部 --}}
                <div class="flex items-center justify-between px-5 py-4 cursor-pointer select-none version-header">
                    <div class="flex items-center gap-3">
                        @if($isLatest)
                        <span class="text-xs font-medium px-2 py-0.5 bg-green-100 text-green-700 rounded-full">Latest</span>
                        @endif
                        <span class="font-mono font-semibold text-gray-900">{{ $versionGroup['version'] }}</span>
                        @if(!($versionGroup['is_stable'] ?? true))
                        <span class="text-xs font-medium px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full">pre-release</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-gray-400">{{ $date }}</span>
                        <svg class="w-4 h-4 text-gray-400 rotate-icon transition-transform {{ $isLatest ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                {{-- 文件列表 (latest 默认展开) --}}
                <div class="{{ $isLatest ? '' : 'hidden' }} version-body border-t border-gray-100">
                    @if(!empty($versionGroup['changelog']))
                    <div class="px-5 py-3 bg-gray-50 text-sm text-gray-600 border-b border-gray-100 whitespace-pre-wrap">{{ $versionGroup['changelog'] }}</div>
                    @endif
                    <div class="divide-y divide-gray-50">
                        @foreach($versionGroup['files'] as $file)
                        <div class="file-row flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
                             data-platform="{{ $file['platform'] ?? '' }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-sm text-gray-400 w-24 shrink-0">{{ $file['platform'] ?? '' }}/{{ $file['arch'] ?? '' }}{{ ($file['variant'] ?? '') ? ('/' . $file['variant']) : '' }}</span>
                                <span class="font-mono text-sm text-gray-700 truncate">{{ $file['filename'] ?? '' }}</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 ml-4">
                                @if($file['file_size'] ?? null)
                                <span class="text-xs text-gray-400 hidden sm:block">{{ number_format($file['file_size'] / 1048576, 1) }} MB</span>
                                @endif
                                <a href="/d/{{ $file['display_product'] ?? $file['product'] }}/{{ $file['filename'] }}"
                                   class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">下载</a>
                                @if($file['sha256'] ?? null)
                                <button onclick="copyText('{{ $file['sha256'] }}', this)"
                                        class="text-xs text-gray-400 hover:text-gray-700 font-mono hidden sm:block transition-colors"
                                        title="点击复制 SHA256: {{ $file['sha256'] }}">{{ substr($file['sha256'], 0, 8) }}…</button>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @endif
    </div>
    @endforeach
</div>

@endsection

@push('scripts')
<script>
// 产品 tab 切换
document.querySelectorAll('.product-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const product = btn.dataset.product;

        document.querySelectorAll('.product-tab').forEach(t => {
            t.classList.remove('border-indigo-600', 'text-indigo-600');
            t.classList.add('border-transparent', 'text-gray-500');
        });
        btn.classList.add('border-indigo-600', 'text-indigo-600');
        btn.classList.remove('border-transparent', 'text-gray-500');

        document.querySelectorAll('.product-panel').forEach(p => {
            p.classList.toggle('hidden', p.dataset.product !== product);
        });

        history.replaceState(null, '', '?product=' + product);
    });
});

// 版本展开/折叠
document.addEventListener('click', e => {
    const header = e.target.closest('.version-header');
    if (!header) return;
    const card = header.closest('.release-card');
    const body = card.querySelector('.version-body');
    const icon = card.querySelector('.rotate-icon');
    if (!body) return;
    const isOpen = !body.classList.contains('hidden');
    body.classList.toggle('hidden', isOpen);
    icon.style.transform = isOpen ? '' : 'rotate(180deg)';
});

// 平台筛选
document.addEventListener('click', e => {
    const btn = e.target.closest('.pf-btn');
    if (!btn) return;
    const platform = btn.dataset.platform;
    const filterBar = btn.closest('div');
    const panel = btn.closest('.product-panel');

    filterBar.querySelectorAll('.pf-btn').forEach(b => {
        b.classList.remove('active', 'bg-indigo-600', 'text-white');
        b.classList.add('bg-gray-100', 'text-gray-600');
    });
    btn.classList.add('active', 'bg-indigo-600', 'text-white');
    btn.classList.remove('bg-gray-100', 'text-gray-600');

    panel.querySelectorAll('.release-card').forEach(card => {
        if (platform === 'all') {
            card.classList.remove('hidden');
            card.querySelectorAll('.file-row').forEach(r => r.classList.remove('hidden'));
        } else {
            const platforms = (card.dataset.platforms || '').split(' ');
            card.classList.toggle('hidden', !platforms.includes(platform));
            card.querySelectorAll('.file-row').forEach(r => {
                r.classList.toggle('hidden', r.dataset.platform !== platform);
            });
        }
    });
});

function copyText(text, el) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = el.textContent;
        el.textContent = '已复制';
        setTimeout(() => { el.textContent = orig; }, 1500);
    });
}
</script>
@endpush
