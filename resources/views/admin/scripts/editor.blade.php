@extends('admin.layout')

@section('title', $script ? '编辑脚本: ' . $script->name : '新建脚本')

@section('content')

<form method="POST"
      action="{{ $script ? '/admin/scripts/' . $script->script_id : '/admin/scripts' }}"
      class="space-y-6">
    @csrf
    @if($script) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- 左列：元数据 --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="font-semibold text-gray-900">脚本信息</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">标识名 <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $script?->name) }}" required placeholder="install"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                    <p class="text-xs text-gray-400 mt-1">如 install、upgrade、service-manager</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">显示名称</label>
                    <input type="text" name="display_name" value="{{ old('display_name', $script?->display_name) }}" placeholder="自动安装脚本"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">分类 <span class="text-red-500">*</span></label>
                        <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            @foreach(['install' => '安装', 'service' => '服务', 'setup' => '配置', 'upgrade' => '升级', 'tool' => '工具'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('category', $script?->category) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">平台 <span class="text-red-500">*</span></label>
                        <select name="platform" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            @foreach(['linux', 'openwrt', 'darwin', 'windows', 'all'] as $p)
                            <option value="{{ $p }}" {{ old('platform', $script?->platform ?? 'linux') === $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">语言</label>
                        <select name="lang" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            @foreach(['bash' => 'Bash', 'sh' => 'POSIX sh', 'python' => 'Python'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('lang', $script?->lang ?? 'bash') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">版本</label>
                        <input type="text" name="version" value="{{ old('version', $script?->version ?? 'latest') }}" placeholder="latest"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">说明</label>
                    <textarea name="description" rows="3" placeholder="脚本功能描述…"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y">{{ old('description', $script?->description) }}</textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_standalone" value="1" id="is_standalone"
                           {{ old('is_standalone', $script?->is_standalone ?? 1) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" />
                    <label for="is_standalone" class="text-sm text-gray-700">可独立运行</label>
                </div>
            </div>

            @if($script)
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
                <h3 class="font-semibold text-gray-900 mb-2">详情</h3>
                <div class="flex justify-between"><span class="text-gray-500">Script ID</span><span class="font-mono">{{ $script->script_id }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">SHA256</span><span class="font-mono text-xs truncate max-w-32">{{ $script->checksum }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">下载次数</span><span>{{ number_format($script->download_cnt) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">创建时间</span><span>{{ $script->created_at?->format('Y-m-d H:i') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">更新时间</span><span>{{ $script->updated_at?->format('Y-m-d H:i') }}</span></div>
            </div>
            @endif
        </div>

        {{-- 右列：代码编辑器 --}}
        <div class="lg:col-span-2 flex flex-col min-h-[600px]">
            <div class="bg-white rounded-xl border border-gray-200 flex flex-col flex-1 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
                    <span class="text-sm font-medium text-gray-700">脚本内容 <span class="text-red-500">*</span></span>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-400" id="char-count">0 字符</span>
                        <button type="button" onclick="formatContent()" class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">格式化</button>
                    </div>
                </div>
                <textarea
                    name="content"
                    id="script-content"
                    required
                    spellcheck="false"
                    class="flex-1 w-full px-5 py-4 font-mono text-sm leading-relaxed bg-gray-900 text-green-400 focus:outline-none resize-none"
                    placeholder="#!/bin/bash&#10;# 输入脚本内容…"
                >{{ old('content', $script?->content) }}</textarea>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="text-sm text-red-600 list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="flex items-center gap-3">
        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            {{ $script ? '保存修改' : '创建脚本' }}
        </button>
        <a href="/admin/scripts" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">取消</a>
    </div>
</form>

@endsection

@push('scripts')
<script>
const textarea = document.getElementById('script-content');
const charCount = document.getElementById('char-count');

function updateCharCount() {
    charCount.textContent = textarea.value.length.toLocaleString() + ' 字符';
}

textarea.addEventListener('input', updateCharCount);
updateCharCount();

// Tab key support in textarea
textarea.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});

function formatContent() {
    // Trim trailing whitespace per line
    textarea.value = textarea.value
        .split('\n')
        .map(line => line.trimEnd())
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim() + '\n';
    updateCharCount();
}

// Ctrl+S / Cmd+S to submit
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        textarea.closest('form').submit();
    }
});
</script>
@endpush
