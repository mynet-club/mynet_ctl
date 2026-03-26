@extends('layouts.app')

@section('title', '快配工具 — MyNet CTL')
@section('description', '快速生成 GNB 节点配置包，无需登录，离线可用')

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex items-baseline justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">快配工具</h1>
        <span class="text-xs text-gray-400">密钥由服务端生成 · 高安全需求请用 <a href="/setup" class="text-indigo-600 hover:underline">配置部署</a></span>
    </div>

    {{-- 基础参数 + 节点列表一体化 --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5 mb-4">
        <div class="flex flex-wrap items-end gap-3 mb-4 pb-4 border-b border-gray-100">
            <div>
                <label class="block text-xs text-gray-500 mb-1">节点数</label>
                <input type="number" id="node-count" value="3" min="1" max="50"
                       class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">子网</label>
                <div class="flex items-center gap-0.5 text-sm">
                    <span class="px-2 py-1.5 bg-gray-100 border border-gray-300 rounded-l text-gray-500">10.</span>
                    <input type="number" id="subnet-b" value="1" min="0" max="255"
                           class="w-14 px-2 py-1.5 border-y border-gray-300 text-sm focus:ring-1 focus:ring-indigo-500">
                    <span class="px-2 py-1.5 bg-gray-100 border border-gray-300 rounded-r text-gray-500">.0.x</span>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">端口</label>
                <input type="number" id="default-port" value="9001" min="1024" max="65535"
                       class="w-24 px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-indigo-500">
            </div>
            <button onclick="generateNodes()" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm font-medium hover:bg-indigo-700 transition-colors">
                生成列表
            </button>
        </div>

        {{-- 节点列表 --}}
        <div id="nodes-section" class="hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-100">
                        <th class="text-left py-1.5 font-medium">#</th>
                        <th class="text-left py-1.5 font-medium">名称</th>
                        <th class="text-left py-1.5 font-medium">内网 IP</th>
                        <th class="text-left py-1.5 font-medium">端口</th>
                        <th class="text-left py-1.5 font-medium">网卡名</th>
                        <th class="text-left py-1.5 font-medium">索引</th>
                        <th class="text-left py-1.5 font-medium">公网 IP</th>
                    </tr>
                </thead>
                <tbody id="nodes-tbody"></tbody>
            </table>

            {{-- 外部索引 --}}
            <div id="external-index-section" class="hidden mt-3 flex items-center gap-2 text-sm">
                <span class="text-gray-500 whitespace-nowrap">无公网 IP？外部索引：</span>
                <input type="text" id="external-index" placeholder="1.2.3.4:9001"
                       class="flex-1 max-w-xs px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-indigo-500">
            </div>

            {{-- 生成按钮 --}}
            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-3">
                <button onclick="doGenerate()" id="generate-btn"
                        class="px-5 py-2 bg-green-600 text-white rounded text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50">
                    生成密钥并下载
                </button>
                <span id="generate-error" class="hidden text-sm text-red-600"></span>
                <a href="/downloads" class="ml-auto text-sm text-gray-500 hover:text-indigo-600 transition-colors">
                    下载 GNB 二进制 →
                </a>
            </div>
        </div>

        {{-- 未生成列表时的占位 --}}
        <p id="nodes-placeholder" class="text-sm text-gray-400 py-2">点击「生成列表」开始配置节点</p>
    </div>
</div>

<script>
const API_BASE = '{{ rtrim(config("app.url"), "/") }}/api/v2';
let nodesData = [];

function generateNodes() {
    const count = Math.max(1, Math.min(50, parseInt(document.getElementById('node-count').value) || 3));
    const subnetB = Math.max(0, Math.min(255, parseInt(document.getElementById('subnet-b').value) || 1));
    const defaultPort = parseInt(document.getElementById('default-port').value) || 9001;

    nodesData = [];
    for (let i = 0; i < count; i++) {
        nodesData.push({
            node_id: null,
            name: `node${i + 1}`,
            ip: `10.${subnetB}.0.${i + 1}`,
            port: defaultPort,
            is_index: false,
            public_ip: '',
            ifname: 'gnb_tun',
        });
    }

    Promise.all(nodesData.map(() =>
        fetch(`${API_BASE}/quickconfig/node-id`).then(r => r.json())
    )).then(results => {
        results.forEach((r, i) => { nodesData[i].node_id = r.node_id; });
        renderNodes();
    }).catch(() => {
        nodesData.forEach(n => {
            n.node_id = Math.floor(1000000000000000 + Math.random() * 8007199254740991);
        });
        renderNodes();
    });
}

function renderNodes() {
    const tbody = document.getElementById('nodes-tbody');
    tbody.innerHTML = nodesData.map((n, i) => `
        <tr class="border-b border-gray-50 hover:bg-gray-50">
            <td class="py-1.5 text-xs text-gray-400 font-mono">${i + 1}</td>
            <td class="py-1.5"><input type="text" value="${n.name}" onchange="nodesData[${i}].name=this.value"
                class="w-full px-1.5 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-indigo-500"></td>
            <td class="py-1.5"><input type="text" value="${n.ip}" onchange="nodesData[${i}].ip=this.value"
                class="w-full px-1.5 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-indigo-500"></td>
            <td class="py-1.5"><input type="number" value="${n.port}" onchange="nodesData[${i}].port=parseInt(this.value)"
                class="w-20 px-1.5 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-indigo-500"></td>
            <td class="py-1.5"><input type="text" value="${n.ifname}" onchange="nodesData[${i}].ifname=this.value"
                class="w-full px-1.5 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-indigo-500"></td>
            <td class="py-1.5 text-center"><input type="checkbox" ${n.is_index ? 'checked' : ''} onchange="toggleIndex(${i}, this.checked)"
                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"></td>
            <td class="py-1.5"><input type="text" placeholder="-" value="${n.public_ip}" id="public-ip-${i}"
                onchange="nodesData[${i}].public_ip=this.value" ${n.is_index ? '' : 'disabled'}
                class="w-full px-1.5 py-1 border border-gray-200 rounded text-sm disabled:bg-gray-50 disabled:text-gray-300 focus:ring-1 focus:ring-indigo-500"></td>
        </tr>
    `).join('');

    document.getElementById('nodes-section').classList.remove('hidden');
    document.getElementById('nodes-placeholder').classList.add('hidden');
    checkIndexState();
}

function toggleIndex(idx, checked) {
    nodesData[idx].is_index = checked;
    if (!checked) nodesData[idx].public_ip = '';
    const input = document.getElementById(`public-ip-${idx}`);
    input.disabled = !checked;
    if (checked) input.focus();
    checkIndexState();
}

function checkIndexState() {
    const anyChecked = nodesData.some(n => n.is_index);
    document.getElementById('external-index-section').classList.toggle('hidden', anyChecked);
}

async function doGenerate() {
    const btn = document.getElementById('generate-btn');
    const errEl = document.getElementById('generate-error');
    errEl.classList.add('hidden');
    btn.disabled = true;
    btn.textContent = '生成中…';

    const externalIndex = document.getElementById('external-index')?.value?.trim() || null;

    const payload = {
        nodes: nodesData.map(n => ({
            node_id: n.node_id, name: n.name, ip: n.ip, port: n.port,
            is_index: n.is_index || false, public_ip: n.public_ip || null,
            ifname: n.ifname || 'gnb_tun',
        })),
        subnet_mask: '255.255.255.0',
        external_index: externalIndex,
    };

    try {
        const resp = await fetch(`${API_BASE}/quickconfig/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/zip' },
            body: JSON.stringify(payload),
        });

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({ message: `HTTP ${resp.status}` }));
            throw new Error(err.message || '生成失败');
        }

        const blob = await resp.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = resp.headers.get('Content-Disposition')?.match(/filename="?(.+)"?/)?.[1]
                     || `mynet_quickconfig_${nodesData.length}nodes.zip`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        btn.textContent = '✅ 已下载';
    } catch (e) {
        errEl.textContent = e.message;
        errEl.classList.remove('hidden');
        btn.textContent = '生成密钥并下载';
    } finally {
        btn.disabled = false;
    }
}
</script>

@endsection
