@extends('admin.layout')

@section('title', 'API 令牌管理')

@section('actions')
<a href="/admin/tokens/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">创建令牌</a>
@endsection

@section('content')

{{-- 新创建的令牌展示（仅展示一次） --}}
@if(session('new_token'))
<div class="bg-amber-50 border border-amber-300 rounded-xl p-5 mb-6">
    <h3 class="font-semibold text-amber-900 mb-2">⚠️ 请立即复制令牌</h3>
    <p class="text-sm text-amber-700 mb-3">此令牌仅显示一次，关闭后将无法再次查看。请妥善保管。</p>
    <div class="bg-white rounded-lg px-4 py-3 flex items-center justify-between gap-4 border border-amber-200">
        <code class="font-mono text-sm text-gray-900 break-all select-all" id="new-token-value">{{ session('new_token') }}</code>
        <button onclick="copyToken()" class="shrink-0 px-3 py-1.5 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-700 transition-colors" id="copy-token-btn">复制</button>
    </div>
</div>
@endif

{{-- 列表 --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-left">名称</th>
                <th class="px-5 py-3 text-left">权限</th>
                <th class="px-5 py-3 text-left">Token Hash（前8位）</th>
                <th class="px-5 py-3 text-right">最后使用</th>
                <th class="px-5 py-3 text-right">过期时间</th>
                <th class="px-5 py-3 text-right">状态</th>
                <th class="px-5 py-3 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($tokens as $t)
            <tr class="hover:bg-gray-50 {{ !$t->status ? 'opacity-50' : '' }}">
                <td class="px-5 py-3 font-medium text-gray-900">{{ $t->name }}</td>
                <td class="px-5 py-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach($t->abilities ?? [] as $ability)
                        <span class="text-xs px-2 py-0.5 rounded-full font-mono
                            {{ $ability === '*' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">{{ $ability }}</span>
                        @endforeach
                    </div>
                </td>
                <td class="px-5 py-3 font-mono text-gray-500">{{ substr($t->token_hash, 0, 8) }}…</td>
                <td class="px-5 py-3 text-right text-gray-400">{{ $t->last_used_at?->format('Y-m-d H:i') ?? '从未' }}</td>
                <td class="px-5 py-3 text-right text-gray-400">
                    @if($t->expires_at)
                    <span class="{{ $t->expires_at->isPast() ? 'text-red-500' : '' }}">{{ $t->expires_at->format('Y-m-d') }}</span>
                    @else
                    <span class="text-gray-300">永不</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    @if($t->status)
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">活跃</span>
                    @else
                    <span class="text-xs px-2 py-0.5 bg-red-100 text-red-600 rounded-full">已禁用</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <form method="POST" action="/admin/tokens/{{ $t->token_id }}/toggle" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-gray-500 hover:text-indigo-600 transition-colors">
                                {{ $t->status ? '禁用' : '启用' }}
                            </button>
                        </form>
                        <form method="POST" action="/admin/tokens/{{ $t->token_id }}" class="inline"
                              onsubmit="return confirm('确认删除此令牌？此操作不可恢复。')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors">删除</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($tokens->isEmpty())
            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">暂无令牌</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if($tokens->hasPages())
<div class="mt-6">{{ $tokens->links() }}</div>
@endif

@endsection

@push('scripts')
<script>
function copyToken() {
    const val = document.getElementById('new-token-value')?.textContent;
    if (!val) return;
    navigator.clipboard.writeText(val).then(() => {
        const btn = document.getElementById('copy-token-btn');
        btn.textContent = '已复制';
        setTimeout(() => { btn.textContent = '复制'; }, 2000);
    });
}
</script>
@endpush
