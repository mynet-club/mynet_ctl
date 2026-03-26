@extends('layouts.app')

@section('title', '工具中心 — MyNet CTL')
@section('description', '脚本工具、GNB 生态插件，支持多平台')

@section('content')

<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">工具中心</h1>
    <p class="text-gray-500 mb-8">脚本工具、扩展插件</p>

    {{-- ═══ 一级 tab：工具 | 插件 ═══ --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200" id="top-tabs">
        <button data-top="tools" class="top-tab px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors border-indigo-600 text-indigo-600">工具</button>
        <button data-top="plugins" class="top-tab px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-gray-500 hover:text-gray-800">插件</button>
    </div>

    {{-- ╔═══════════════════════════════════════════════╗ --}}
    {{-- ║ 工具 面板                                       ║ --}}
    {{-- ╚═══════════════════════════════════════════════╝ --}}
    <div class="top-panel" data-top="tools">

        {{-- 脚本列表 --}}
        <h2 class="text-lg font-semibold text-gray-900 mb-4">脚本</h2>
        @if(count($scripts) === 0)
        <div class="py-12 text-center text-gray-400">暂无脚本</div>
        @else
        <div class="space-y-3">
            @foreach($scripts as $script)
            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-200 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-900">{{ $script['name'] }}</span>
                            @if($script['platform'] ?? null)
                            <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full font-mono">{{ $script['platform'] }}</span>
                            @endif
                            @if($script['category'] ?? null)
                            <span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full">{{ $script['category'] }}</span>
                            @endif
                        </div>
                        @if($script['description'] ?? null)
                        <p class="text-sm text-gray-500 mb-3">{{ $script['description'] }}</p>
                        @endif
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="bg-gray-900 rounded-lg px-4 py-2 flex items-center justify-between gap-3 flex-1 min-w-0">
                                <code class="text-green-400 font-mono text-xs truncate">curl -fsSL {{ url('/api/v2/scripts/' . $script['script_id'] . '/raw/' . $script['name']) }} | bash</code>
                                <button onclick="copyText('curl -fsSL {{ url('/api/v2/scripts/' . $script['script_id'] . '/raw/' . $script['name']) }} | bash', this)"
                                        class="text-gray-400 hover:text-white text-xs shrink-0 transition-colors">复制</button>
                            </div>
                            <a href="/api/v2/scripts/{{ $script['script_id'] }}/raw/{{ $script['name'] }}"
                               target="_blank"
                               class="text-xs text-gray-500 hover:text-indigo-600 transition-colors flex items-center gap-1 sm:ml-2 shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                查看源码
                            </a>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400 shrink-0">{{ $script['updated_at'] ? \Carbon\Carbon::parse($script['updated_at'])->format('Y-m-d') : '' }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ╔═══════════════════════════════════════════════╗ --}}
    {{-- ║ 插件 面板                                       ║ --}}
    {{-- ╚═══════════════════════════════════════════════╝ --}}
    <div class="top-panel hidden" data-top="plugins">

        {{-- 二级 tab：透明代理 | 梯子配置转换器 --}}
        <div class="flex gap-1 mb-6">
            <button data-sub="proxy" class="sub-tab px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white">透明代理</button>
            <button data-sub="converter" class="sub-tab px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-white text-gray-600 border border-gray-300 hover:border-indigo-400">梯子配置转换器</button>
        </div>

        {{-- ── 透明代理 ────────────────────────────── --}}
        <div class="sub-panel" data-sub="proxy">

            {{-- 简介 --}}
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl border border-indigo-100 p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">MyNet Proxy 透明代理插件</h3>
                        <p class="text-gray-600 text-sm">基于 GNB P2P 隧道的智能代理路由管理工具。自动检测网络拓扑，按策略路由转发流量（国内直连 / 海外代理），支持 Linux、OpenWrt、macOS。</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {{-- 左：一键安装 --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="text-base font-semibold text-gray-900 mb-4">一键安装</h4>
                    <p class="text-sm text-gray-500 mb-3">在已安装 mynet 客户端的设备上运行（需 root 权限）：</p>
                    <div class="bg-gray-900 rounded-lg px-4 py-3 flex items-center justify-between gap-3 mb-3">
                        <code class="text-green-400 font-mono text-xs truncate" id="proxy-install-cmd">curl -fsSL {{ url('/d/proxy/install.sh') }} | sudo bash</code>
                        <button onclick="copyText(document.getElementById('proxy-install-cmd').textContent, this)"
                                class="text-gray-400 hover:text-white text-xs shrink-0 transition-colors">复制</button>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">或使用 wget：</p>
                    <div class="bg-gray-900 rounded-lg px-4 py-3 flex items-center justify-between gap-3 mb-4">
                        <code class="text-green-400 font-mono text-xs truncate">wget -qO- {{ url('/d/proxy/install.sh') }} | sudo bash</code>
                        <button onclick="copyText('wget -qO- {{ url('/d/proxy/install.sh') }} | sudo bash', this)"
                                class="text-gray-400 hover:text-white text-xs shrink-0 transition-colors">复制</button>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <h5 class="text-sm font-medium text-gray-700 mb-2">手动下载</h5>
                        <p class="text-xs text-gray-500 mb-2">在 manifest 中查找适合你平台的版本：</p>
                        <a href="/d/proxy/manifest.json" target="_blank"
                           class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            proxy/manifest.json
                        </a>
                    </div>
                </div>

                {{-- 右：快速上手 --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="text-base font-semibold text-gray-900 mb-4">快速上手</h4>
                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold shrink-0">1</span>
                                <span class="font-medium text-gray-800">前置条件</span>
                            </div>
                            <p class="text-gray-500 ml-8">已安装 mynet 客户端并配置好 GNB 节点（至少有一个对端节点在线）。</p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold shrink-0">2</span>
                                <span class="font-medium text-gray-800">安装插件</span>
                            </div>
                            <p class="text-gray-500 ml-8">使用上方一键安装命令，或从 manifest 手动下载后解压到 mynet 目录。</p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold shrink-0">3</span>
                                <span class="font-medium text-gray-800">配置角色</span>
                            </div>
                            <div class="ml-8 bg-gray-900 rounded-lg px-3 py-2 mt-1">
                                <code class="text-green-400 font-mono text-xs">mynet_proxy setup --role=client --peers=all</code>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold shrink-0">4</span>
                                <span class="font-medium text-gray-800">应用路由</span>
                            </div>
                            <div class="ml-8 bg-gray-900 rounded-lg px-3 py-2 mt-1">
                                <code class="text-green-400 font-mono text-xs">mynet_proxy apply</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 使用帮助 --}}
            <details class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <summary class="px-6 py-4 text-base font-semibold text-gray-900 cursor-pointer hover:bg-gray-50 select-none">使用帮助</summary>
                <div class="px-6 pb-6 space-y-6 text-sm border-t border-gray-100 pt-4">

                    <div>
                        <h5 class="font-medium text-gray-800 mb-2">代理角色</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                                <div class="font-mono text-blue-700 text-xs mb-1">client</div>
                                <p class="text-gray-600 text-xs">客户端模式。本机流量通过远端 GNB 节点代理出去。适合国内设备访问海外资源。</p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                                <div class="font-mono text-green-700 text-xs mb-1">server</div>
                                <p class="text-gray-600 text-xs">服务端模式。本机接受远端请求并转发。适合海外服务器提供代理出口。</p>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-3 border border-purple-100">
                                <div class="font-mono text-purple-700 text-xs mb-1">both</div>
                                <p class="text-gray-600 text-xs">双向模式。同时扮演 client 和 server。适合双向互访场景。</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h5 class="font-medium text-gray-800 mb-2">常用命令</h5>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs space-y-2">
                            <div><span class="text-gray-500"># 初始配置</span></div>
                            <div class="text-green-400">mynet_proxy setup --role=client --peers=all</div>
                            <div class="mt-3"><span class="text-gray-500"># 生成/合并 IP 列表</span></div>
                            <div class="text-green-400">mynet_proxy ipconfig</div>
                            <div class="mt-3"><span class="text-gray-500"># 应用代理路由规则</span></div>
                            <div class="text-green-400">mynet_proxy apply</div>
                            <div class="mt-3"><span class="text-gray-500"># 查看当前代理状态</span></div>
                            <div class="text-green-400">mynet_proxy status</div>
                            <div class="mt-3"><span class="text-gray-500"># 刷新配置</span></div>
                            <div class="text-green-400">mynet_proxy refresh</div>
                            <div class="mt-3"><span class="text-gray-500"># 清理代理路由</span></div>
                            <div class="text-green-400">mynet_proxy clean</div>
                        </div>
                    </div>

                    <div>
                        <h5 class="font-medium text-gray-800 mb-2">平台支持</h5>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 px-3 font-medium text-gray-700">平台</th>
                                        <th class="text-left py-2 px-3 font-medium text-gray-700">架构</th>
                                        <th class="text-left py-2 px-3 font-medium text-gray-700">说明</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600">
                                    <tr class="border-b border-gray-100"><td class="py-2 px-3">Linux</td><td class="py-2 px-3 font-mono">amd64, arm64, armv7, mipsel</td><td class="py-2 px-3">服务器、树莓派等</td></tr>
                                    <tr class="border-b border-gray-100"><td class="py-2 px-3">OpenWrt</td><td class="py-2 px-3 font-mono">amd64, arm64, mipsel</td><td class="py-2 px-3">路由器全局代理</td></tr>
                                    <tr><td class="py-2 px-3">macOS</td><td class="py-2 px-3 font-mono">amd64, arm64</td><td class="py-2 px-3">Intel + Apple Silicon</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>
        </div>

        {{-- ── 梯子配置转换器 ─────────────────────── --}}
        <div class="sub-panel hidden" data-sub="converter">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- 左：输入 --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">输入节点链接</label>
                        <div class="flex gap-2">
                            <button onclick="pasteFromClipboard()" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">粘贴</button>
                            <button onclick="clearInput()" class="text-xs text-gray-500 hover:text-red-600 transition-colors">清空</button>
                        </div>
                    </div>
                    <textarea id="proxy-input" rows="12" placeholder="每行一个，支持以下格式：&#10;vmess://eyJ2Ij...&#10;ss://Y2hhY2hh...@host:port#name&#10;trojan://password@host:port?sni=xxx#name&#10;vless://uuid@host:port?security=reality&...#name&#10;&#10;也可粘贴 Base64 编码的订阅内容（自动解码）"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent resize-none bg-white"></textarea>

                    <div class="flex items-center gap-4 mt-3">
                        <button onclick="doConvert()" id="convert-btn"
                            class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            转换为 Mihomo 配置
                        </button>
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                            <input type="checkbox" id="full-config" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-400" />
                            生成完整配置文件
                        </label>
                    </div>

                    <div id="parse-stats" class="hidden mt-3 text-sm text-gray-500"></div>
                    <div id="parse-errors" class="hidden mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 max-h-32 overflow-auto"></div>
                </div>

                {{-- 右：输出 --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Mihomo 配置输出</label>
                        <div class="flex gap-2">
                            <button onclick="copyOutput()" id="copy-output-btn" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">复制</button>
                            <button onclick="downloadOutput()" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">下载 YAML</button>
                        </div>
                    </div>
                    <textarea id="proxy-output" rows="12" readonly
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-mono bg-gray-50 focus:outline-none resize-none text-gray-700"></textarea>

                    <div id="nodes-preview" class="hidden mt-3">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">已解析节点</h4>
                        <div id="nodes-preview-list" class="space-y-1 max-h-48 overflow-auto"></div>
                    </div>
                </div>
            </div>

            {{-- 使用说明 --}}
            <details class="mt-6 border border-gray-200 rounded-xl">
                <summary class="px-5 py-3 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50 select-none">使用说明</summary>
                <div class="px-5 py-4 text-sm text-gray-600 border-t border-gray-200 space-y-3">
                    <div>
                        <strong>支持的协议：</strong>
                        <span class="inline-flex gap-2 ml-2">
                            <code class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">vmess://</code>
                            <code class="px-2 py-0.5 bg-green-50 text-green-700 rounded text-xs">ss://</code>
                            <code class="px-2 py-0.5 bg-purple-50 text-purple-700 rounded text-xs">trojan://</code>
                            <code class="px-2 py-0.5 bg-orange-50 text-orange-700 rounded text-xs">vless://</code>
                        </span>
                    </div>
                    <div>
                        <strong>输入方式：</strong> 每行一个节点链接，或粘贴 Base64 编码的订阅内容（自动检测并解码）。
                    </div>
                    <div>
                        <strong>完整配置：</strong> 勾选「生成完整配置文件」将输出包含 DNS、代理组和分流规则的完整 config.yaml，可直接用于 Mihomo。
                    </div>
                    <p class="text-gray-400">所有转换均在浏览器本地完成，节点信息不会上传到服务器。</p>
                </div>
            </details>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── 一级 Tab 切换 ─────────────────────────────────────────
document.querySelectorAll('.top-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const key = btn.dataset.top;
        document.querySelectorAll('.top-tab').forEach(t => {
            t.classList.toggle('border-indigo-600', t.dataset.top === key);
            t.classList.toggle('text-indigo-600', t.dataset.top === key);
            t.classList.toggle('border-transparent', t.dataset.top !== key);
            t.classList.toggle('text-gray-500', t.dataset.top !== key);
        });
        document.querySelectorAll('.top-panel').forEach(p => {
            p.classList.toggle('hidden', p.dataset.top !== key);
        });
    });
});

// ── 二级 Tab 切换（插件子分类） ───────────────────────────
document.querySelectorAll('.sub-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const key = btn.dataset.sub;
        document.querySelectorAll('.sub-tab').forEach(t => {
            const active = t.dataset.sub === key;
            t.classList.toggle('bg-indigo-600', active);
            t.classList.toggle('text-white', active);
            t.classList.toggle('bg-white', !active);
            t.classList.toggle('text-gray-600', !active);
            t.classList.toggle('border', !active);
            t.classList.toggle('border-gray-300', !active);
            if (active) { t.classList.remove('border', 'border-gray-300'); }
        });
        document.querySelectorAll('.sub-panel').forEach(p => {
            p.classList.toggle('hidden', p.dataset.sub !== key);
        });
    });
});

function copyText(text, el) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = el.textContent;
        el.textContent = '已复制';
        setTimeout(() => { el.textContent = orig; }, 1500);
    });
}

// ── 梯子配置转换器 ────────────────────────────────────────

function pasteFromClipboard() {
    navigator.clipboard.readText().then(text => {
        document.getElementById('proxy-input').value = text;
    }).catch(() => {});
}

function clearInput() {
    document.getElementById('proxy-input').value = '';
    document.getElementById('proxy-output').value = '';
    document.getElementById('parse-stats').classList.add('hidden');
    document.getElementById('parse-errors').classList.add('hidden');
    document.getElementById('nodes-preview').classList.add('hidden');
}

function copyOutput() {
    const text = document.getElementById('proxy-output').value;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copy-output-btn');
        btn.textContent = '已复制';
        setTimeout(() => { btn.textContent = '复制'; }, 1500);
    });
}

function downloadOutput() {
    const text = document.getElementById('proxy-output').value;
    if (!text) return;
    const blob = new Blob([text], { type: 'text/yaml' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'config.yaml';
    a.click();
    URL.revokeObjectURL(url);
}

// ── 解析器 ────────────────────────────────────────────────

function tryBase64Decode(str) {
    try {
        // Handle URL-safe base64
        const s = str.replace(/-/g, '+').replace(/_/g, '/');
        const pad = s.length % 4 === 0 ? '' : '='.repeat(4 - s.length % 4);
        return atob(s + pad);
    } catch { return null; }
}

function parseVmess(url) {
    const b64 = url.replace(/^vmess:\/\//, '');
    const json = tryBase64Decode(b64);
    if (!json) return null;
    try {
        const c = JSON.parse(json);
        const node = {
            name: c.ps || c.add || 'vmess',
            type: 'vmess',
            server: c.add,
            port: parseInt(c.port, 10),
            uuid: c.id,
            alterId: parseInt(c.aid || '0', 10),
            cipher: 'auto',
        };
        if (c.net && c.net !== 'tcp') {
            node.network = c.net;
            if (c.net === 'ws') {
                node['ws-opts'] = {};
                if (c.path) node['ws-opts'].path = c.path;
                if (c.host) node['ws-opts'].headers = { Host: c.host };
            } else if (c.net === 'grpc') {
                node['grpc-opts'] = {};
                if (c.path) node['grpc-opts']['grpc-service-name'] = c.path;
            } else if (c.net === 'h2') {
                node['h2-opts'] = {};
                if (c.path) node['h2-opts'].path = c.path;
                if (c.host) node['h2-opts'].host = [c.host];
            }
        }
        if (c.tls === 'tls') {
            node.tls = true;
            if (c.sni) node.servername = c.sni;
            if (c.fp) node['client-fingerprint'] = c.fp;
            if (c.allowInsecure || c.verify_cert === false) node['skip-cert-verify'] = true;
        }
        return node;
    } catch { return null; }
}

function parseSS(url) {
    let rest = url.replace(/^ss:\/\//, '');
    let name = '';
    const hashIdx = rest.lastIndexOf('#');
    if (hashIdx !== -1) {
        name = decodeURIComponent(rest.slice(hashIdx + 1));
        rest = rest.slice(0, hashIdx);
    }
    // Format 1: base64(method:password)@host:port
    // Format 2: base64(method:password@host:port)
    let method, password, server, port;
    const atIdx = rest.indexOf('@');
    if (atIdx !== -1) {
        const userInfo = tryBase64Decode(rest.slice(0, atIdx));
        if (userInfo) {
            const colonIdx = userInfo.indexOf(':');
            method = userInfo.slice(0, colonIdx);
            password = userInfo.slice(colonIdx + 1);
        } else {
            const colonIdx = rest.slice(0, atIdx).indexOf(':');
            method = rest.slice(0, colonIdx);
            password = rest.slice(colonIdx + 1, atIdx);
        }
        const hostPort = rest.slice(atIdx + 1).split('?')[0];
        const lastColon = hostPort.lastIndexOf(':');
        server = hostPort.slice(0, lastColon);
        port = parseInt(hostPort.slice(lastColon + 1), 10);
    } else {
        const decoded = tryBase64Decode(rest.split('?')[0]);
        if (!decoded) return null;
        const match = decoded.match(/^(.+?):(.+?)@(.+?):(\d+)$/);
        if (!match) return null;
        [, method, password, server, port] = match;
        port = parseInt(port, 10);
    }
    if (!server || !port || !method || !password) return null;
    return { name: name || server, type: 'ss', server, port, cipher: method, password };
}

function parseTrojan(url) {
    let rest = url.replace(/^trojan:\/\//, '');
    let name = '';
    const hashIdx = rest.lastIndexOf('#');
    if (hashIdx !== -1) {
        name = decodeURIComponent(rest.slice(hashIdx + 1));
        rest = rest.slice(0, hashIdx);
    }
    const atIdx = rest.indexOf('@');
    if (atIdx === -1) return null;
    const password = decodeURIComponent(rest.slice(0, atIdx));
    const remainder = rest.slice(atIdx + 1);
    const [hostPort, queryStr] = remainder.split('?');
    const lastColon = hostPort.lastIndexOf(':');
    const server = hostPort.slice(0, lastColon);
    const port = parseInt(hostPort.slice(lastColon + 1), 10);
    const params = new URLSearchParams(queryStr || '');

    const node = { name: name || server, type: 'trojan', server, port, password };
    const sni = params.get('sni') || params.get('peer');
    if (sni) node.sni = sni;
    if (params.get('allowInsecure') === '1') node['skip-cert-verify'] = true;
    const network = params.get('type');
    if (network && network !== 'tcp') {
        node.network = network;
        if (network === 'ws') {
            node['ws-opts'] = {};
            if (params.get('path')) node['ws-opts'].path = params.get('path');
            if (params.get('host')) node['ws-opts'].headers = { Host: params.get('host') };
        } else if (network === 'grpc') {
            node['grpc-opts'] = {};
            if (params.get('serviceName')) node['grpc-opts']['grpc-service-name'] = params.get('serviceName');
        }
    }
    return node;
}

function parseVless(url) {
    let rest = url.replace(/^vless:\/\//, '');
    let name = '';
    const hashIdx = rest.lastIndexOf('#');
    if (hashIdx !== -1) {
        name = decodeURIComponent(rest.slice(hashIdx + 1));
        rest = rest.slice(0, hashIdx);
    }
    const atIdx = rest.indexOf('@');
    if (atIdx === -1) return null;
    const uuid = rest.slice(0, atIdx);
    const remainder = rest.slice(atIdx + 1);
    const [hostPort, queryStr] = remainder.split('?');
    const lastColon = hostPort.lastIndexOf(':');
    const server = hostPort.slice(0, lastColon);
    const port = parseInt(hostPort.slice(lastColon + 1), 10);
    const params = new URLSearchParams(queryStr || '');

    const node = { name: name || server, type: 'vless', server, port, uuid };
    const security = params.get('security') || '';
    if (security === 'reality') {
        node.tls = true;
        node['reality-opts'] = {};
        if (params.get('pbk')) node['reality-opts']['public-key'] = params.get('pbk');
        if (params.get('sid')) node['reality-opts']['short-id'] = params.get('sid');
        if (params.get('sni')) node.servername = params.get('sni');
        if (params.get('fp')) node['client-fingerprint'] = params.get('fp');
    } else if (security === 'tls') {
        node.tls = true;
        if (params.get('sni')) node.servername = params.get('sni');
        if (params.get('fp')) node['client-fingerprint'] = params.get('fp');
        if (params.get('allowInsecure') === '1') node['skip-cert-verify'] = true;
    }
    const flow = params.get('flow');
    if (flow) node.flow = flow;
    const network = params.get('type');
    if (network && network !== 'tcp') {
        node.network = network;
        if (network === 'ws') {
            node['ws-opts'] = {};
            if (params.get('path')) node['ws-opts'].path = params.get('path');
            if (params.get('host')) node['ws-opts'].headers = { Host: params.get('host') };
        } else if (network === 'grpc') {
            node['grpc-opts'] = {};
            if (params.get('serviceName')) node['grpc-opts']['grpc-service-name'] = params.get('serviceName');
        } else if (network === 'h2') {
            node['h2-opts'] = {};
            if (params.get('path')) node['h2-opts'].path = params.get('path');
            if (params.get('host')) node['h2-opts'].host = [params.get('host')];
        }
    }
    return node;
}

function parseLine(line) {
    line = line.trim();
    if (!line) return null;
    if (line.startsWith('vmess://')) return parseVmess(line);
    if (line.startsWith('ss://')) return parseSS(line);
    if (line.startsWith('trojan://')) return parseTrojan(line);
    if (line.startsWith('vless://')) return parseVless(line);
    return null;
}

// ── YAML 生成 ─────────────────────────────────────────────

function yamlStr(val) {
    if (typeof val === 'boolean') return val ? 'true' : 'false';
    if (typeof val === 'number') return String(val);
    if (/[:{}\[\],&*?|>!%#@`"'\n]/.test(val) || val === '' || val !== val.trim()) return JSON.stringify(val);
    return val;
}

function nodeToYaml(node, indent = 6) {
    const pad = ' '.repeat(indent);
    const lines = [];
    const simple = ['name', 'type', 'server', 'port', 'uuid', 'password', 'cipher',
        'alterId', 'tls', 'network', 'servername', 'flow', 'sni',
        'skip-cert-verify', 'client-fingerprint'];

    for (const key of simple) {
        if (node[key] !== undefined) {
            lines.push(`${pad}${key}: ${yamlStr(node[key])}`);
        }
    }

    const nested = ['ws-opts', 'grpc-opts', 'h2-opts', 'reality-opts'];
    for (const key of nested) {
        if (node[key]) {
            lines.push(`${pad}${key}:`);
            for (const [k, v] of Object.entries(node[key])) {
                if (typeof v === 'object' && !Array.isArray(v)) {
                    lines.push(`${pad}  ${k}:`);
                    for (const [k2, v2] of Object.entries(v)) {
                        lines.push(`${pad}    ${k2}: ${yamlStr(v2)}`);
                    }
                } else if (Array.isArray(v)) {
                    lines.push(`${pad}  ${k}:`);
                    v.forEach(item => lines.push(`${pad}    - ${yamlStr(item)}`));
                } else {
                    lines.push(`${pad}  ${k}: ${yamlStr(v)}`);
                }
            }
        }
    }
    return lines.join('\n');
}

function generateProxiesOnly(nodes) {
    let yaml = 'proxies:\n';
    for (const node of nodes) {
        yaml += `  - ${nodeToYaml(node, 4).trimStart()}\n\n`;
    }
    return yaml;
}

function generateFullConfig(nodes) {
    const names = nodes.map(n => n.name);
    const proxyList = names.map(n => `      - ${yamlStr(n)}`).join('\n');

    let yaml = `# Mihomo (Clash Meta) 配置文件
# 由 MyNet CTL 节点转换器自动生成
# 生成时间: ${new Date().toISOString()}

mixed-port: 7890
allow-lan: false
mode: rule
log-level: silent
ipv6: false
external-controller: 127.0.0.1:9090

dns:
  enable: true
  ipv6: false
  listen: 127.0.0.1:1053
  enhanced-mode: redir-host
  default-nameserver:
    - 223.5.5.5
    - 114.114.114.114
  nameserver:
    - https://dns.alidns.com/dns-query
    - https://doh.pub/dns-query
  fallback:
    - tls://1.1.1.1:853
    - https://dns.google/dns-query
  fallback-filter:
    geoip: true
    geoip-code: CN
    ipcidr:
      - 240.0.0.0/4
      - 0.0.0.0/8

tun:
  enable: false
  stack: system
  auto-route: true
  auto-detect-interface: true
  dns-hijack:
    - any:53

proxies:
`;

    for (const node of nodes) {
        yaml += `  - ${nodeToYaml(node, 4).trimStart()}\n\n`;
    }

    yaml += `proxy-groups:
  - name: "Auto"
    type: url-test
    proxies:
${proxyList}
    url: "https://www.gstatic.com/generate_204"
    interval: 120
    tolerance: 50

  - name: "Select"
    type: select
    proxies:
      - Auto
${proxyList}
      - DIRECT

  - name: "Proxy"
    type: select
    proxies:
      - Auto
      - Select
      - DIRECT

rules:
  - IP-CIDR,192.168.0.0/16,DIRECT
  - IP-CIDR,10.0.0.0/8,DIRECT
  - IP-CIDR,172.16.0.0/12,DIRECT
  - IP-CIDR,127.0.0.0/8,DIRECT
  - DOMAIN-SUFFIX,baidu.com,DIRECT
  - DOMAIN-SUFFIX,bilibili.com,DIRECT
  - DOMAIN-SUFFIX,qq.com,DIRECT
  - DOMAIN-SUFFIX,wechat.com,DIRECT
  - DOMAIN-SUFFIX,taobao.com,DIRECT
  - DOMAIN-SUFFIX,jd.com,DIRECT
  - DOMAIN-SUFFIX,weibo.com,DIRECT
  - DOMAIN-SUFFIX,zhihu.com,DIRECT
  - DOMAIN-SUFFIX,alipay.com,DIRECT
  - DOMAIN-SUFFIX,aliyun.com,DIRECT
  - DOMAIN-SUFFIX,tencent.com,DIRECT
  - DOMAIN-SUFFIX,douyin.com,DIRECT
  - GEOSITE,telegram,Proxy
  - IP-CIDR,91.108.4.0/22,Proxy
  - IP-CIDR,91.108.56.0/22,Proxy
  - IP-CIDR,149.154.160.0/20,Proxy
  - GEOIP,CN,DIRECT
  - MATCH,Proxy
`;
    return yaml;
}

// ── 主转换逻辑 ────────────────────────────────────────────

function doConvert() {
    const raw = document.getElementById('proxy-input').value.trim();
    if (!raw) return;

    // 尝试 Base64 解码（订阅格式）
    let lines;
    const decoded = tryBase64Decode(raw);
    if (decoded && (decoded.includes('vmess://') || decoded.includes('ss://') || decoded.includes('trojan://') || decoded.includes('vless://'))) {
        lines = decoded.split('\n');
    } else {
        lines = raw.split('\n');
    }

    const nodes = [];
    const errors = [];
    const seen = new Set();

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line || line.startsWith('#')) continue;
        const node = parseLine(line);
        if (node) {
            // 去重
            const key = `${node.type}:${node.server}:${node.port}`;
            if (!seen.has(key)) {
                seen.add(key);
                nodes.push(node);
            }
        } else {
            errors.push(`第 ${i + 1} 行: 无法解析 "${line.slice(0, 50)}${line.length > 50 ? '...' : ''}"`);
        }
    }

    // 统计
    const statsEl = document.getElementById('parse-stats');
    const errorsEl = document.getElementById('parse-errors');
    const previewEl = document.getElementById('nodes-preview');
    const previewList = document.getElementById('nodes-preview-list');

    if (nodes.length > 0) {
        const typeCounts = {};
        nodes.forEach(n => { typeCounts[n.type] = (typeCounts[n.type] || 0) + 1; });
        const typeStr = Object.entries(typeCounts).map(([t, c]) => `${t}: ${c}`).join('，');
        statsEl.textContent = `成功解析 ${nodes.length} 个节点（${typeStr}）`;
        statsEl.classList.remove('hidden');

        // 渲染预览
        const typeColors = { vmess: 'bg-blue-100 text-blue-700', ss: 'bg-green-100 text-green-700', trojan: 'bg-purple-100 text-purple-700', vless: 'bg-orange-100 text-orange-700' };
        previewList.innerHTML = nodes.map(n =>
            `<div class="flex items-center gap-2 text-sm">
                <span class="text-xs px-1.5 py-0.5 rounded font-mono ${typeColors[n.type] || 'bg-gray-100'}">${n.type}</span>
                <span class="text-gray-700 truncate">${escHtmlConv(n.name)}</span>
                <span class="text-gray-400 font-mono text-xs">${escHtmlConv(n.server)}:${n.port}</span>
            </div>`
        ).join('');
        previewEl.classList.remove('hidden');

        // 生成 YAML
        const fullConfig = document.getElementById('full-config').checked;
        const yaml = fullConfig ? generateFullConfig(nodes) : generateProxiesOnly(nodes);
        document.getElementById('proxy-output').value = yaml;
    } else {
        statsEl.textContent = '未能解析出任何节点';
        statsEl.classList.remove('hidden');
        previewEl.classList.add('hidden');
        document.getElementById('proxy-output').value = '';
    }

    if (errors.length > 0) {
        errorsEl.innerHTML = errors.map(e => escHtmlConv(e)).join('<br>');
        errorsEl.classList.remove('hidden');
    } else {
        errorsEl.classList.add('hidden');
    }
}

function escHtmlConv(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
@endpush
