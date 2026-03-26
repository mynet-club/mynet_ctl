@extends('admin.layout')

@section('title', '脚本管理')

@section('actions')
<a href="/admin/scripts/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">新建脚本</a>
@endsection

@section('content')

{{-- 筛选 --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">分类</label>
            <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">全部</option>
                @foreach(['install', 'service', 'setup', 'upgrade', 'tool'] as $c)
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
                <th class="px-5 py-3 text-left">平台</th>
                <th class="px-5 py-3 text-left">语言</th>
                <th class="px-5 py-3 text-left">版本</th>
                <th class="px-5 py-3 text-right">下载</th>
                <th class="px-5 py-3 text-right">状态</th>
                <th class="px-5 py-3 text-right">更新时间</th>
                <th class="px-5 py-3 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($scripts as $s)
            <tr class="hover:bg-gray-50 {{ !$s->status ? 'opacity-50' : '' }}">
                <td class="px-5 py-3">
                    <div class="font-medium text-gray-900">{{ $s->name }}</div>
                    @if($s->display_name)<div class="text-xs text-gray-400">{{ $s->display_name }}</div>@endif
                </td>
                <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full">{{ $s->category }}</span></td>
                <td class="px-5 py-3 font-mono text-gray-600">{{ $s->platform }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $s->lang }}</td>
                <td class="px-5 py-3 font-mono text-gray-600">{{ $s->version }}</td>
                <td class="px-5 py-3 text-right text-gray-500">{{ number_format($s->download_cnt) }}</td>
                <td class="px-5 py-3 text-right">
                    @if($s->status)
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">可用</span>
                    @else
                    <span class="text-xs px-2 py-0.5 bg-red-100 text-red-600 rounded-full">已禁用</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right text-gray-400">{{ $s->updated_at?->format('Y-m-d H:i') }}</td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="/admin/scripts/{{ $s->script_id }}/edit"
                           class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">编辑</a>
                        <form method="POST" action="/admin/scripts/{{ $s->script_id }}/toggle" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-gray-500 hover:text-indigo-600 transition-colors">
                                {{ $s->status ? '禁用' : '启用' }}
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($scripts->isEmpty())
            <tr><td colspan="9" class="px-5 py-12 text-center text-gray-400">暂无脚本</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if($scripts->hasPages())
<div class="mt-6">{{ $scripts->withQueryString()->links() }}</div>
@endif

@endsection
