@extends('layouts.app')

@section('title', 'OpenWrt 设备检测 — MyNet CTL')
@section('description', '查询你的 OpenWrt 路由器支持的架构，推荐安装包下载链接')

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">OpenWrt 设备检测</h1>
    <p class="text-gray-500 mb-10">输入路由器型号或 <code class="font-mono text-sm bg-gray-100 px-1.5 py-0.5 rounded">/etc/openwrt_release</code> 内容，自动推荐对应架构的下载包</p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- 方式 1：搜索型号 --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-1">方式一：搜索路由器型号</h2>
            <p class="text-sm text-gray-500 mb-4">输入品牌 + 型号，如 "Xiaomi Mi Router 4A"</p>
            <div class="flex gap-2">
                <input
                    id="device-search"
                    type="text"
                    placeholder="搜索路由器型号…"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
                    autocomplete="off"
                />
                <button onclick="searchDevice()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">搜索</button>
            </div>
            <div id="search-results" class="mt-3 hidden">
                <div class="text-xs text-gray-400 mb-2">选择你的设备：</div>
                <div id="search-results-list" class="space-y-1 max-h-48 overflow-y-auto"></div>
            </div>
        </div>

        {{-- 方式 2：粘贴 openwrt_release --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-1">方式二：粘贴系统信息</h2>
            <p class="text-sm text-gray-500 mb-4">在路由器 SSH 中运行后粘贴：</p>
            <div class="bg-gray-900 rounded-lg px-4 py-2 mb-3 flex items-center justify-between">
                <code class="text-green-400 font-mono text-xs">cat /etc/openwrt_release</code>
                <button onclick="copyText('cat /etc/openwrt_release', this)" class="text-gray-400 hover:text-white text-xs ml-2 transition-colors">复制</button>
            </div>
            <textarea
                id="release-content"
                rows="5"
                placeholder="DISTRIB_ID=&quot;OpenWrt&quot;&#10;DISTRIB_RELEASE=&quot;23.05.3&quot;&#10;DISTRIB_ARCH=&quot;mipsel_24kc&quot;&#10;DISTRIB_TARGET=&quot;ramips/mt7621&quot;&#10;..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent resize-none"
            ></textarea>
            <button onclick="detectFromRelease()" class="mt-3 w-full px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">检测架构</button>
        </div>
    </div>

    {{-- 一键检测脚本提示 --}}
    <div class="mt-6 bg-indigo-50 border border-indigo-200 rounded-xl p-5">
        <h3 class="font-medium text-indigo-900 mb-2">💡 更快的方式：路由器上直接运行</h3>
        <div class="bg-gray-900 rounded-lg px-4 py-3 flex items-center justify-between">
            <code class="text-green-400 font-mono text-sm">wget -qO- {{ $ctlUrl }}/d/detect/openwrt.sh | sh</code>
            <button onclick="copyText('wget -qO- {{ $ctlUrl }}/d/detect/openwrt.sh | sh', this)"
                    class="text-gray-400 hover:text-white text-xs ml-4 shrink-0 transition-colors">复制</button>
        </div>
        <p class="text-sm text-indigo-700 mt-2">脚本会自动读取系统信息，并输出推荐的下载命令</p>
    </div>

    {{-- 检测结果 --}}
    <div id="detect-result" class="mt-8 hidden">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">检测结果</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div class="px-5 py-4 grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">设备型号</span><div id="r-device" class="font-medium text-gray-900 mt-0.5">—</div></div>
                <div><span class="text-gray-500">SoC / CPU</span><div id="r-soc" class="font-medium text-gray-900 mt-0.5">—</div></div>
                <div><span class="text-gray-500">DISTRIB_ARCH</span><div id="r-distrib-arch" class="font-mono text-gray-900 mt-0.5">—</div></div>
                <div><span class="text-gray-500">CTL 架构</span><div id="r-ctl-arch" class="font-mono text-indigo-600 font-semibold mt-0.5">—</div></div>
                <div><span class="text-gray-500">匹配置信度</span><div id="r-confidence" class="mt-0.5">—</div></div>
                <div><span class="text-gray-500">Target</span><div id="r-target" class="font-mono text-gray-900 mt-0.5">—</div></div>
            </div>
            <div class="px-5 py-4">
                <div class="text-sm text-gray-500 mb-3">推荐下载</div>
                <div id="r-downloads" class="space-y-2"></div>
            </div>
        </div>
    </div>

    {{-- 错误提示 --}}
    <div id="detect-error" class="mt-8 hidden bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-sm text-red-700"></div>

    {{-- 加载中 --}}
    <div id="detect-loading" class="mt-8 hidden text-center text-gray-400 py-8">
        <div class="inline-block w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin mb-2"></div>
        <div class="text-sm">检测中…</div>
    </div>

    {{-- 常见架构映射表 --}}
    <div class="mt-12">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">常见架构映射</h2>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">DISTRIB_ARCH</th>
                        <th class="px-5 py-3 text-left">CTL 架构</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">SoC 示例</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50" id="arch-map-table">
                    <tr><td colspan="3" class="px-5 py-4 text-gray-400 text-center">加载中…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let archMapData = null;

// 读取架构映射表
async function loadArchMap() {
    try {
        const res = await fetch('/api/v2/detect/arch-map');
        const data = await res.json();
        archMapData = data.arch_map || {};
        renderArchTable(data);
    } catch (e) {
        document.querySelector('#arch-map-table').innerHTML =
            '<tr><td colspan="3" class="px-5 py-4 text-red-400 text-center">加载失败</td></tr>';
    }
}

function renderArchTable(data) {
    const table = document.getElementById('arch-map-table');
    const rows = Object.entries(data.arch_map || {}).map(([distrib, ctl]) => `
        <tr class="hover:bg-gray-50">
            <td class="px-5 py-2.5 font-mono text-gray-700">${distrib}</td>
            <td class="px-5 py-2.5 font-mono text-indigo-600 font-medium">${ctl}</td>
            <td class="px-5 py-2.5 text-gray-400 hidden sm:table-cell">${getSocExample(distrib, data.soc_examples || {})}</td>
        </tr>
    `).join('');
    table.innerHTML = rows || '<tr><td colspan="3" class="px-5 py-4 text-gray-400 text-center">无数据</td></tr>';
}

function getSocExample(distribArch, examples) {
    return examples[distribArch] || '—';
}

// 方式一：搜索设备
let searchTimer;
document.getElementById('device-search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) {
        document.getElementById('search-results').classList.add('hidden');
        return;
    }
    searchTimer = setTimeout(() => searchDevice(q), 300);
});

document.getElementById('device-search').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') searchDevice(this.value.trim());
});

async function searchDevice(q) {
    q = q || document.getElementById('device-search').value.trim();
    if (!q) return;
    showLoading(true);
    try {
        const res = await fetch(`/api/v2/detect/openwrt?device_name=${encodeURIComponent(q)}`);
        const data = await res.json();
        showLoading(false);
        if (data.error) { showError(data.error); return; }

        if (data.candidates && data.candidates.length > 1) {
            showCandidates(data.candidates);
        } else {
            showResult(data);
        }
    } catch (e) {
        showLoading(false);
        showError('请求失败，请检查网络连接');
    }
}

function showCandidates(candidates) {
    const list = document.getElementById('search-results-list');
    list.innerHTML = candidates.map(c => `
        <button class="w-full text-left px-3 py-2 rounded-lg hover:bg-indigo-50 text-sm transition-colors"
                onclick="selectDevice('${c.distrib_arch}', '${c.distrib_target}', \`${c.model.replace(/`/g, '')}\`)">
            <span class="font-medium">${c.model}</span>
            <span class="text-gray-400 ml-2 text-xs">${c.distrib_target || ''}</span>
        </button>
    `).join('');
    document.getElementById('search-results').classList.remove('hidden');
}

async function selectDevice(arch, target, model) {
    document.getElementById('search-results').classList.add('hidden');
    document.getElementById('device-search').value = model;
    showLoading(true);
    try {
        const params = new URLSearchParams({ distrib_arch: arch });
        if (target) params.set('distrib_target', target);
        const res = await fetch(`/api/v2/detect/openwrt?${params}`);
        const data = await res.json();
        showLoading(false);
        showResult(data);
    } catch (e) {
        showLoading(false);
        showError('请求失败');
    }
}

// 方式二：解析 /etc/openwrt_release 内容
async function detectFromRelease() {
    const content = document.getElementById('release-content').value.trim();
    if (!content) return;

    const lines = content.split('\n');
    const parsed = {};
    lines.forEach(line => {
        const m = line.match(/^(\w+)="?([^"]*)"?/);
        if (m) parsed[m[1]] = m[2];
    });

    const arch = parsed['DISTRIB_ARCH'] || '';
    const target = parsed['DISTRIB_TARGET'] || '';
    if (!arch) { showError('未能解析出 DISTRIB_ARCH，请粘贴完整的 /etc/openwrt_release 内容'); return; }

    showLoading(true);
    try {
        const params = new URLSearchParams({ distrib_arch: arch });
        if (target) params.set('distrib_target', target);
        const res = await fetch(`/api/v2/detect/openwrt?${params}`);
        const data = await res.json();
        showLoading(false);
        showResult(data);
    } catch (e) {
        showLoading(false);
        showError('请求失败，请检查网络连接');
    }
}

function showResult(data) {
    document.getElementById('detect-error').classList.add('hidden');
    document.getElementById('detect-result').classList.remove('hidden');

    document.getElementById('r-device').textContent = data.device_model || data.model || '未知';
    document.getElementById('r-soc').textContent = data.soc || data.cpu || '未知';
    document.getElementById('r-distrib-arch').textContent = data.distrib_arch || '—';
    document.getElementById('r-ctl-arch').textContent = data.ctl_arch || '—';
    document.getElementById('r-target').textContent = data.distrib_target || '—';

    const conf = data.confidence || 'unknown';
    const confLabels = { exact: '精确匹配', arch_only: '架构匹配', target_only: '目标平台匹配', fallback: '默认回退' };
    const confColors = { exact: 'text-green-600', arch_only: 'text-blue-600', target_only: 'text-yellow-600', fallback: 'text-gray-400' };
    document.getElementById('r-confidence').innerHTML =
        `<span class="font-medium ${confColors[conf] || ''}">${confLabels[conf] || conf}</span>`;

    const dlContainer = document.getElementById('r-downloads');
    if (data.downloads && data.downloads.length > 0) {
        dlContainer.innerHTML = data.downloads.map(d => `
            <div class="flex items-center justify-between py-2 border border-gray-100 rounded-lg px-4">
                <div>
                    <span class="font-medium text-sm text-gray-900">${d.product_label || d.product}</span>
                    <span class="text-xs text-gray-400 ml-2">${d.version}</span>
                </div>
                <a href="${d.url}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">下载</a>
            </div>
        `).join('');
    } else {
        dlContainer.innerHTML = '<p class="text-sm text-gray-400">暂无推荐下载，请前往 <a href="/downloads" class="text-indigo-600">下载页</a> 手动选择</p>';
    }

    document.getElementById('detect-result').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showError(msg) {
    document.getElementById('detect-result').classList.add('hidden');
    const err = document.getElementById('detect-error');
    err.textContent = msg;
    err.classList.remove('hidden');
}

function showLoading(show) {
    document.getElementById('detect-loading').classList.toggle('hidden', !show);
    if (show) {
        document.getElementById('detect-result').classList.add('hidden');
        document.getElementById('detect-error').classList.add('hidden');
    }
}

function copyText(text, el) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = el.textContent;
        el.textContent = '已复制';
        setTimeout(() => { el.textContent = orig; }, 1500);
    });
}

loadArchMap();
</script>
@endpush
