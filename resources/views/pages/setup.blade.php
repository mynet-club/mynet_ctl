@extends('layouts.app')

@section('title', '配置部署 — MyNet CTL')
@section('description', '登录 MyNet 账号，一键下载节点全部配置文件')

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">配置部署</h1>
    <p class="text-gray-500 mb-8">登录你的 MyNet 账号，选择节点，一键打包下载全部配置文件</p>

    {{-- ═══ 认证入口 ═══ --}}
    <div id="login-section">
        <div class="max-w-md mx-auto bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-1 text-center">连接 MyNet 账号</h2>
            <p class="text-sm text-gray-500 mb-6 text-center">通过 <a href="{{ $guiUrl }}" class="text-indigo-600 hover:underline" target="_blank">{{ parse_url($guiUrl, PHP_URL_HOST) }}</a> 安全登录</p>

            <div class="space-y-4">
                {{-- OAuth 跳转登录 --}}
                <a href="{{ $guiUrl }}/auth/login?callback={{ urlencode(url('/setup')) }}"
                   class="block w-full py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors text-center">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    前往 {{ parse_url($guiUrl, PHP_URL_HOST) }} 登录
                </a>

                <div id="login-error" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
            </div>
            <p class="mt-4 text-xs text-gray-400 text-center">
                CTL 不收集你的账号密码。登录在用户中心完成后自动跳回。
            </p>
        </div>
    </div>

    {{-- ═══ 已登录：Zone + 节点选择 ═══ --}}
    <div id="main-section" class="hidden">

        {{-- 用户信息栏 --}}
        <div class="flex items-center justify-between bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold" id="user-avatar">U</div>
                <div>
                    <div class="text-sm font-medium text-gray-900" id="user-name">—</div>
                    <div class="text-xs text-gray-400" id="user-email">—</div>
                </div>
            </div>
            <button onclick="doLogout()" class="text-sm text-gray-500 hover:text-red-600 transition-colors">退出</button>
        </div>

        {{-- Zone 选择 --}}
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">选择 Zone</label>
            <div class="flex gap-2 flex-wrap" id="zone-tabs"></div>
        </div>

        {{-- 加载中 --}}
        <div id="nodes-loading" class="hidden text-center text-gray-400 py-12">
            <div class="inline-block w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin mb-2"></div>
            <div class="text-sm">加载节点列表…</div>
        </div>

        {{-- 节点列表 --}}
        <div id="nodes-list" class="space-y-3"></div>

        {{-- 空状态 --}}
        <div id="nodes-empty" class="hidden text-center py-16">
            <div class="text-4xl mb-3">📡</div>
            <div class="text-gray-500 mb-2">当前 Zone 暂无节点</div>
            <p class="text-sm text-gray-400">请前往 <a href="{{ $guiUrl }}" target="_blank" class="text-indigo-600 hover:underline">用户中心</a> 创建节点</p>
        </div>

        {{-- 错误 --}}
        <div id="nodes-error" class="hidden bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-sm text-red-700"></div>

        {{-- ═══ 节点详情 + 下载面板 ═══ --}}
        <div id="node-detail" class="hidden mt-6">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {{-- 详情头部 --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="font-semibold text-gray-900" id="detail-title">节点详情</h3>
                    <button onclick="closeDetail()" class="text-gray-400 hover:text-gray-700 transition-colors text-sm">收起</button>
                </div>

                {{-- 节点信息表格 --}}
                <div class="px-6 py-4">
                    <div id="detail-loading" class="text-center text-gray-400 py-8">
                        <div class="inline-block w-5 h-5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin mb-2"></div>
                        <div class="text-sm">加载节点详情…</div>
                    </div>
                    <div id="detail-content" class="hidden">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-4">
                            <div><span class="text-gray-500">名称</span><div class="font-medium text-gray-900 mt-0.5" id="detail-name">—</div></div>
                            <div><span class="text-gray-500">节点 ID</span><div class="font-medium text-gray-900 font-mono mt-0.5" id="detail-id">—</div></div>
                            <div><span class="text-gray-500">节点 IP</span><div class="font-medium text-gray-900 font-mono mt-0.5" id="detail-ip">—</div></div>
                            <div><span class="text-gray-500">类型</span><div class="font-medium text-gray-900 mt-0.5" id="detail-type">—</div></div>
                            <div><span class="text-gray-500">状态</span><div class="mt-0.5" id="detail-status">—</div></div>
                            <div><span class="text-gray-500">所属 Zone</span><div class="font-medium text-gray-900 mt-0.5" id="detail-zone">—</div></div>
                        </div>

                        {{-- GNB 配置预览 --}}
                        <details class="mt-3 border border-gray-200 rounded-lg">
                            <summary class="px-4 py-2.5 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50 select-none">GNB 配置预览</summary>
                            <pre id="detail-config" class="text-xs font-mono text-gray-600 whitespace-pre-wrap bg-gray-50 px-4 py-3 border-t border-gray-200 max-h-64 overflow-auto"></pre>
                        </details>
                    </div>
                </div>

                {{-- 下载区域 --}}
                <div id="detail-download" class="hidden px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                    <div class="space-y-4">
                        {{-- 选项：含软件包 --}}
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" id="include-binary" onchange="toggleBinaryOptions()" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-400" />
                            <span class="text-sm text-gray-700">同时包含软件包（需选择平台和架构）</span>
                        </label>

                        {{-- 平台+架构选择（默认隐藏） --}}
                        <div id="binary-options" class="hidden space-y-3 pl-7">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1.5">目标平台</label>
                                <div class="flex gap-2 flex-wrap" id="platform-btns"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1.5">芯片架构</label>
                                <div class="flex gap-2 flex-wrap" id="arch-btns"></div>
                            </div>
                        </div>

                        {{-- 下载按钮 --}}
                        <div class="flex gap-3 pt-1">
                            <button id="download-btn" onclick="doDownload()"
                                    class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                下载配置包
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── 状态管理 ──────────────────────────────────────────────
let authToken = null;
let currentZoneId = null;
let selectedNodeId = null;
let nodesData = [];

// 平台/架构矩阵（从 API 加载）
let platformCombinations = {};
let currentPlatform = null;
let currentArch = null;

const API = '/api/v2/setup';

const PLATFORM_LABELS = { linux: 'Linux', openwrt: 'OpenWrt', darwin: 'macOS', windows: 'Windows' };

// ── 自动从 URL 参数获取 Token（OAuth 回调） ──────────────
(async function checkCallbackToken() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    if (!token) return;

    authToken = token;
    // 清理 URL 中的 token 参数
    const url = new URL(window.location);
    url.searchParams.delete('token');
    window.history.replaceState({}, '', url);

    // 验证 token 并获取用户信息
    try {
        const res = await fetch(`${API}/zones`, { headers: authHeaders() });
        if (!res.ok) {
            authToken = null;
            const errEl = document.getElementById('login-error');
            errEl.textContent = 'Token 无效或已过期，请重新登录';
            errEl.classList.remove('hidden');
            return;
        }
        showMainSection();
        await Promise.all([loadZones(), loadPlatforms()]);
    } catch (e) {
        authToken = null;
        const errEl = document.getElementById('login-error');
        errEl.textContent = '连接失败: ' + e.message;
        errEl.classList.remove('hidden');
    }
})();

function showMainSection() {
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('main-section').classList.remove('hidden');
    // 用户信息栏暂时隐藏，等 zones 加载完再显示 token 状态
    document.getElementById('user-name').textContent = '已连接';
    document.getElementById('user-email').textContent = authToken ? authToken.substring(0, 12) + '...' : '';
    document.getElementById('user-avatar').textContent = '✓';
}

function doLogout() {
    authToken = null;
    currentZoneId = null;
    selectedNodeId = null;
    nodesData = [];
    currentPlatform = null;
    currentArch = null;
    document.getElementById('main-section').classList.add('hidden');
    document.getElementById('login-section').classList.remove('hidden');
    closeDetail();
}

// ── API 请求辅助 ──────────────────────────────────────────
function authHeaders() {
    return { 'Authorization': `Bearer ${authToken}`, 'Accept': 'application/json' };
}

// ── 平台/架构矩阵 ────────────────────────────────────────
async function loadPlatforms() {
    try {
        const res = await fetch(`${API}/platforms`);
        const data = await res.json();
        platformCombinations = data.combinations || {};
    } catch (e) {
        // 降级使用默认值
        platformCombinations = {
            linux: ['amd64', 'arm64'], darwin: ['amd64', 'arm64'],
            windows: ['amd64', 'arm64'], openwrt: ['amd64', 'arm64', 'mipsel']
        };
    }
}

// ── Zone ──────────────────────────────────────────────────
async function loadZones() {
    try {
        const res = await fetch(`${API}/zones`, { headers: authHeaders() });
        if (res.status === 401) { doLogout(); return; }
        const data = await res.json();
        const zones = data.data || data.zones || data || [];

        const container = document.getElementById('zone-tabs');
        if (!Array.isArray(zones) || zones.length === 0) {
            container.innerHTML = '<span class="text-sm text-gray-400">暂无可用 Zone</span>';
            return;
        }

        container.innerHTML = zones.map((z, i) => {
            const id = z.zone_id || z.id;
            const name = z.zone_name || z.name || `Zone ${id}`;
            const active = i === 0;
            return `<button onclick="selectZone(${id}, this)"
                class="zone-btn px-4 py-2 rounded-lg text-sm font-medium border transition-colors
                       ${active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400'}">
                ${escHtml(name)}
            </button>`;
        }).join('');

        const firstZone = zones[0];
        currentZoneId = firstZone.zone_id || firstZone.id;
        await doSwitchZoneAndLoadNodes(currentZoneId);
    } catch (e) {
        showNodesError('加载 Zone 列表失败: ' + e.message);
    }
}

function selectZone(zoneId, btn) {
    currentZoneId = zoneId;
    document.querySelectorAll('.zone-btn').forEach(b => {
        b.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
        b.classList.add('bg-white', 'text-gray-600', 'border-gray-300');
    });
    btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
    btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-300');
    closeDetail();
    doSwitchZoneAndLoadNodes(zoneId);
}

async function doSwitchZoneAndLoadNodes(zoneId) {
    try {
        await fetch(`${API}/switch-zone`, {
            method: 'POST',
            headers: { ...authHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ zone_id: zoneId }),
        });
    } catch (e) {
        // switch-zone 失败不阻断，后续请求会通过 X-Zone-ID 参数处理
    }
    await loadNodes();
}

// ── 节点列表 ──────────────────────────────────────────────
async function loadNodes() {
    const container = document.getElementById('nodes-list');
    const loading = document.getElementById('nodes-loading');
    const empty = document.getElementById('nodes-empty');
    const error = document.getElementById('nodes-error');

    container.innerHTML = '';
    empty.classList.add('hidden');
    error.classList.add('hidden');
    loading.classList.remove('hidden');

    try {
        const params = new URLSearchParams();
        if (currentZoneId) params.set('zone_id', currentZoneId);

        const res = await fetch(`${API}/nodes?${params}`, { headers: authHeaders() });
        if (res.status === 401) { doLogout(); return; }
        const data = await res.json();

        // 后端返回 {success, data: {nodes: {data: [...]}}} 格式
        const inner = data.data || data;
        nodesData = inner?.nodes?.data || inner?.data || (Array.isArray(inner) ? inner : []);
        loading.classList.add('hidden');
        renderNodes();
    } catch (e) {
        loading.classList.add('hidden');
        showNodesError('加载节点列表失败: ' + e.message);
    }
}

function renderNodes() {
    const container = document.getElementById('nodes-list');
    const empty = document.getElementById('nodes-empty');

    if (!Array.isArray(nodesData) || nodesData.length === 0) {
        container.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');
    container.innerHTML = nodesData.map(node => {
        const nodeId = node.node_id || node.id;
        const name = node.node_name || node.name || `节点 ${nodeId}`;
        const ip = node.node_ip_display || node.node_ip || '—';
        const status = node.node_status ?? node.status ?? -1;
        const statusInfo = getStatusInfo(status);
        const type = node.node_type_name || getNodeTypeName(node.node_type);
        const selected = nodeId === selectedNodeId;

        return `
        <div onclick="selectNode(${nodeId})"
             class="node-item bg-white rounded-xl border p-5 cursor-pointer transition-colors
                    ${selected ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-gray-200 hover:border-indigo-200'}"
             data-node-id="${nodeId}">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-gray-900">${escHtml(name)}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium ${statusInfo.cls}">${statusInfo.label}</span>
                        <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">${escHtml(type)}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mt-1">
                        <span class="font-mono">${escHtml(String(ip))}</span>
                        <span>ID: ${nodeId}</span>
                    </div>
                </div>
                <div class="shrink-0 text-sm ${selected ? 'text-indigo-600 font-medium' : 'text-gray-400'}">
                    ${selected ? '已选中 ✓' : '点击选择'}
                </div>
            </div>
        </div>`;
    }).join('');
}

function getStatusInfo(status) {
    const map = {
        '-1': { label: '未配置', cls: 'bg-gray-100 text-gray-500' },
        '0':  { label: '已禁用', cls: 'bg-red-100 text-red-600' },
        '1':  { label: '已启用', cls: 'bg-green-100 text-green-700' },
        '2':  { label: '在线', cls: 'bg-emerald-100 text-emerald-700' },
    };
    return map[String(status)] || { label: '未知', cls: 'bg-gray-100 text-gray-500' };
}

function getNodeTypeName(type) {
    const map = { 1: 'Edge', 2: 'Relay', 4: 'Gateway', 8: 'Index' };
    return map[type] || 'Edge';
}

// ── 选中节点 → 展示详情 ──────────────────────────────────
async function selectNode(nodeId) {
    selectedNodeId = nodeId;
    renderNodes(); // 更新选中样式

    const detailEl = document.getElementById('node-detail');
    const loadingEl = document.getElementById('detail-loading');
    const contentEl = document.getElementById('detail-content');
    const downloadEl = document.getElementById('detail-download');

    detailEl.classList.remove('hidden');
    loadingEl.classList.remove('hidden');
    contentEl.classList.add('hidden');
    downloadEl.classList.add('hidden');

    // 滚动到详情面板
    detailEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
        const res = await fetch(`${API}/nodes/${nodeId}?zone_id=${currentZoneId}`, { headers: authHeaders() });
        if (res.status === 401) { doLogout(); return; }
        const data = await res.json();
        const node = data.data || data;

        // 填充节点信息
        const name = node.node_name || `节点 ${nodeId}`;
        document.getElementById('detail-title').textContent = name;
        document.getElementById('detail-name').textContent = name;
        document.getElementById('detail-id').textContent = nodeId;
        document.getElementById('detail-ip').textContent = node.node_ip_display || node.node_ip || '—';
        document.getElementById('detail-type').textContent = getNodeTypeName(node.node_type);
        document.getElementById('detail-zone').textContent = node.zone_name || `Zone ${node.zone_id || '—'}`;

        const status = getStatusInfo(node.node_status ?? -1);
        document.getElementById('detail-status').innerHTML =
            `<span class="text-xs px-2 py-0.5 rounded-full font-medium ${status.cls}">${status.label}</span>`;

        // GNB 配置预览
        const configJson = node.config?.config || node.config || {};
        let configText = '';
        if (typeof configJson === 'object' && !Array.isArray(configJson)) {
            Object.entries(configJson).forEach(([k, v]) => {
                configText += `${k.replace(/_/g, '-')}  ${v}\n`;
            });
        } else if (Array.isArray(configJson)) {
            configJson.forEach(item => {
                configText += `${(item.k || '').replace(/_/g, '-')}  ${item.v || ''}\n`;
            });
        } else {
            configText = JSON.stringify(configJson, null, 2);
        }
        document.getElementById('detail-config').textContent = configText || '（无配置数据）';

        loadingEl.classList.add('hidden');
        contentEl.classList.remove('hidden');
        downloadEl.classList.remove('hidden');

        // 重置下载选项
        document.getElementById('include-binary').checked = false;
        toggleBinaryOptions();
    } catch (e) {
        loadingEl.innerHTML = `<div class="text-red-600 text-sm">加载失败: ${escHtml(e.message)}</div>`;
    }
}

function closeDetail() {
    selectedNodeId = null;
    document.getElementById('node-detail').classList.add('hidden');
    renderNodes();
}

// ── 软件包选项切换 ───────────────────────────────────────
function toggleBinaryOptions() {
    const checked = document.getElementById('include-binary').checked;
    const optEl = document.getElementById('binary-options');
    const downloadBtn = document.getElementById('download-btn');

    if (checked) {
        optEl.classList.remove('hidden');
        renderPlatformButtons();
        updateDownloadBtnState();
    } else {
        optEl.classList.add('hidden');
        currentPlatform = null;
        currentArch = null;
        downloadBtn.disabled = false;
        downloadBtn.innerHTML = svgDownload() + '下载配置包';
    }
}

function renderPlatformButtons() {
    const container = document.getElementById('platform-btns');
    const platforms = Object.keys(platformCombinations);

    // 自动检测平台
    if (!currentPlatform) {
        const ua = navigator.userAgent.toLowerCase();
        if (ua.includes('win')) currentPlatform = 'windows';
        else if (ua.includes('mac')) currentPlatform = 'darwin';
        else currentPlatform = 'linux';
    }

    container.innerHTML = platforms.map(p => {
        const active = p === currentPlatform;
        return `<button onclick="selectPlatform('${p}')"
            class="plat-btn px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors
                   ${active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400'}">
            ${escHtml(PLATFORM_LABELS[p] || p)}
        </button>`;
    }).join('');

    renderArchButtons();
}

function selectPlatform(p) {
    currentPlatform = p;
    currentArch = null; // 切换平台时重置架构
    renderPlatformButtons();
    updateDownloadBtnState();
}

function renderArchButtons() {
    const container = document.getElementById('arch-btns');
    const arches = platformCombinations[currentPlatform] || [];

    if (arches.length === 0) {
        container.innerHTML = '<span class="text-sm text-gray-400">无可用架构</span>';
        return;
    }

    // 自动选中第一个常见架构
    if (!currentArch || !arches.includes(currentArch)) {
        currentArch = arches.includes('amd64') ? 'amd64' : arches[0];
    }

    container.innerHTML = arches.map(a => {
        const active = a === currentArch;
        return `<button onclick="selectArch('${a}')"
            class="arch-btn px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors font-mono
                   ${active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400'}">
            ${escHtml(a)}
        </button>`;
    }).join('');

    updateDownloadBtnState();
}

function selectArch(a) {
    currentArch = a;
    renderArchButtons();
    updateDownloadBtnState();
}

function updateDownloadBtnState() {
    const btn = document.getElementById('download-btn');
    const includeBinary = document.getElementById('include-binary').checked;

    if (includeBinary && (!currentPlatform || !currentArch)) {
        btn.disabled = true;
        btn.innerHTML = svgDownload() + '请选择平台和架构';
    } else if (includeBinary) {
        btn.disabled = false;
        btn.innerHTML = svgDownload() + `下载配置 + 软件包 (${PLATFORM_LABELS[currentPlatform] || currentPlatform}/${currentArch})`;
    } else {
        btn.disabled = false;
        btn.innerHTML = svgDownload() + '下载配置包';
    }
}

// ── 下载 ─────────────────────────────────────────────────
async function doDownload() {
    if (!selectedNodeId) return;

    const btn = document.getElementById('download-btn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-1.5"></span>打包中…';

    const includeBinary = document.getElementById('include-binary').checked;
    let url = `${API}/config-pack/${selectedNodeId}?zone_id=${currentZoneId}`;
    if (includeBinary && currentPlatform && currentArch) {
        url += `&platform=${encodeURIComponent(currentPlatform)}&arch=${encodeURIComponent(currentArch)}`;
    }

    try {
        const res = await fetch(url, {
            headers: { 'Authorization': `Bearer ${authToken}` },
        });

        if (res.status === 401) { doLogout(); return; }
        if (!res.ok) {
            const err = await res.json().catch(() => ({ message: '下载失败' }));
            alert(err.message || '下载失败，请重试');
            return;
        }

        // 触发浏览器下载
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const disposition = res.headers.get('Content-Disposition') || '';
        const filenameMatch = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        a.download = filenameMatch ? filenameMatch[1].replace(/['"]/g, '') : `mynet_node_${selectedNodeId}.zip`;
        a.href = blobUrl;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(blobUrl);

        // 成功反馈
        btn.innerHTML = '<svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>已下载';
        btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        btn.classList.add('bg-green-600');
        setTimeout(() => {
            btn.innerHTML = origHtml;
            btn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            btn.classList.remove('bg-green-600');
        }, 3000);
    } catch (e) {
        alert('下载失败: ' + e.message);
    } finally {
        btn.disabled = false;
        if (btn.textContent.includes('打包中')) btn.innerHTML = origHtml;
    }
}

// ── 工具函数 ──────────────────────────────────────────────
function showNodesError(msg) {
    const el = document.getElementById('nodes-error');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function svgDownload() {
    return '<svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
}
</script>
@endpush
