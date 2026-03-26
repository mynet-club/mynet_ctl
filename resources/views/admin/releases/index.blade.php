@extends('admin.layout')

@section('title', '版本管理')

@section('actions')
<a href="/admin/releases/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">上传版本</a>
@endsection

@section('content')

{{-- 筛选栏 --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">产品</label>
            <select name="product" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">全部</option>
                @foreach(['tui', 'gnb', 'utc', 'client'] as $p)
                <option value="{{ $p }}" {{ request('product') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">平台</label>
            <select name="platform" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">全部</option>
                @foreach(['linux', 'openwrt', 'darwin', 'windows'] as $p)
                <option value="{{ $p }}" {{ request('platform') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">状态</label>
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">全部</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>可用</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>已禁用</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition-colors">筛选</button>
        @if(request()->hasAny(['product', 'platform', 'status']))
        <a href="/admin/releases" class="px-4 py-2 text-gray-500 text-sm hover:text-gray-700 transition-colors">清除</a>
        @endif
    </form>
</div>

{{-- 列表 --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-left">产品</th>
                <th class="px-5 py-3 text-left">版本</th>
                <th class="px-5 py-3 text-left">平台/架构</th>
                <th class="px-5 py-3 text-left">文件名</th>
                <th class="px-5 py-3 text-right">大小</th>
                <th class="px-5 py-3 text-center">标记</th>
                <th class="px-5 py-3 text-right">下载</th>
                <th class="px-5 py-3 text-right">状态</th>
                <th class="px-5 py-3 text-right">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($releases as $r)
            <tr class="hover:bg-gray-50 {{ !$r->status ? 'opacity-50' : '' }}">
                <td class="px-5 py-3 font-medium text-gray-900">{{ $r->product }}</td>
                <td class="px-5 py-3 font-mono text-indigo-600">{{ $r->version }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $r->platform }}/{{ $r->arch }}{{ $r->variant ? '/'.$r->variant : '' }}</td>
                <td class="px-5 py-3 font-mono text-xs text-gray-500 truncate max-w-48">{{ $r->filename }}</td>
                <td class="px-5 py-3 text-right text-gray-500">{{ $r->file_size ? number_format($r->file_size / 1048576, 1).' MB' : '—' }}</td>
                <td class="px-5 py-3 text-center">
                    @if($r->is_latest)<span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">Latest</span>@endif
                    @if(!$r->is_stable)<span class="text-xs px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full">Pre</span>@endif
                </td>
                <td class="px-5 py-3 text-right text-gray-500">{{ number_format($r->download_cnt) }}</td>
                <td class="px-5 py-3 text-right">
                    @if($r->status)
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">可用</span>
                    @else
                    <span class="text-xs px-2 py-0.5 bg-red-100 text-red-600 rounded-full">已禁用</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <form method="POST" action="/admin/releases/{{ $r->release_id }}/toggle" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-gray-500 hover:text-indigo-600 transition-colors">
                                {{ $r->status ? '禁用' : '启用' }}
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            @if($releases->isEmpty())
            <tr><td colspan="9" class="px-5 py-12 text-center text-gray-400">暂无版本</td></tr>
            @endif
        </tbody>
    </table>
</div>

{{-- 分页 --}}
@if($releases->hasPages())
<div class="mt-6">
    {{ $releases->withQueryString()->links() }}
</div>
@endif

@endsection
