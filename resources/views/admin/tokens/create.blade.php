@extends('admin.layout')

@section('title', '创建 API 令牌')

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="/admin/tokens" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">名称 <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="CI/CD 上传令牌"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <p class="text-xs text-gray-400 mt-1">用于标识令牌用途</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">权限 <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    @php
                        $allAbilities = [
                            '*'              => '全部权限（管理员）',
                            'release:upload' => '上传版本',
                            'release:manage' => '管理版本（删除/下架）',
                            'plugin:upload'  => '上传插件',
                            'plugin:manage'  => '管理插件',
                            'script:publish' => '发布/更新脚本',
                            'script:manage'  => '管理脚本（删除）',
                        ];
                        $oldAbilities = old('abilities', []);
                    @endphp
                    @foreach($allAbilities as $key => $label)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="abilities[]" value="{{ $key }}"
                               {{ in_array($key, $oldAbilities) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400"
                               @if($key === '*') onchange="toggleAll(this)" @endif />
                        <span class="text-sm text-gray-700">
                            <code class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded {{ $key === '*' ? 'bg-red-100 text-red-700' : '' }}">{{ $key }}</code>
                            <span class="text-gray-500 ml-1">{{ $label }}</span>
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">过期时间</label>
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                <p class="text-xs text-gray-400 mt-1">留空表示永不过期</p>
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
                创建令牌
            </button>
            <a href="/admin/tokens" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">取消</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
function toggleAll(checkbox) {
    if (checkbox.checked) {
        document.querySelectorAll('input[name="abilities[]"]').forEach(cb => {
            if (cb.value !== '*') cb.checked = false;
        });
    }
}

document.querySelectorAll('input[name="abilities[]"]').forEach(cb => {
    if (cb.value !== '*') {
        cb.addEventListener('change', function() {
            if (this.checked) {
                document.querySelector('input[value="*"]').checked = false;
            }
        });
    }
});
</script>
@endpush
