@extends('admin.layout')

@section('title', '插件管理')

@section('actions')
<a href="/admin/plugins/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">上传插件</a>
@endsection

@section('content')

{{-- 筛选 --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">分类</label>
            <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">全部</option>
                @foreach(['proxy', 'dns', 'service', 'tool', 'general'] as $c)
                <option value="{{ $c }}" {{ request('category') === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition-colors">筛选</button>
    </form>
</div>

{{-- 列表 --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-left">名称</th>
                <th class="px-5 py-3 text-left">分类</th>
                <th class="px-5 py-3 text-left">版本</th>
                <th class="px-5 py-3 text-left">平台/架构</th>
                <th class="px-5 py-3 text-right">大小</th>
                <th class="px-5 py-3 text-right">下载</th>
                <th class="px-5 py-3 text-right">状态</th>
                <th class="px-5 py-3 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($plugins as $p)
            <tr class="hover:bg-gray-50 {{ !$p->status ? 'opacity-50' : '' }}">
                <td class="px-5 py-3">
                    <div class="font-medium text-gray-900">{{ $p->display_name }}</div>
                    <div class="text-xs text-gray-400 font-mono">{{ $p->name }}</div>
                </td>
                <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full">{{ $p->category }}</span></td>
                <td class="px-5 py-3 font-mono text-gray-600">{{ $p->version }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $p->platform }}/{{ $p->arch }}</td>
                <td class="px-5 py-3 text-right text-gray-500">{{ $p->file_size ? number_format($p->file_size / 1048576, 1).' MB' : '—' }}</td>
                <td class="px-5 py-3 text-right text-gray-500">{{ number_format($p->download_cnt) }}</td>
                <td class="px-5 py-3 text-right">
                    @if($p->status)
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">可用</span>
                    @else
                    <span class="text-xs px-2 py-0.5 bg-red-100 text-red-600 rounded-full">已禁用</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    <form method="POST" action="/admin/plugins/{{ $p->plugin_id }}/toggle" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-gray-500 hover:text-indigo-600 transition-colors">
                            {{ $p->status ? '禁用' : '启用' }}
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if($plugins->isEmpty())
            <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">暂无插件</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if($plugins->hasPages())
<div class="mt-6">{{ $plugins->withQueryString()->links() }}</div>
@endif

@endsection
