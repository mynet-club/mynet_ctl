@extends('admin.layout')

@section('title', '数据看板')

@section('content')

{{-- 概览卡片 --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    @foreach([
        ['label' => '版本', 'value' => $stats['releases'], 'icon' => '📦', 'href' => '/admin/releases'],
        ['label' => '脚本', 'value' => $stats['scripts'], 'icon' => '📝', 'href' => '/admin/scripts'],
        ['label' => '插件', 'value' => $stats['plugins'], 'icon' => '🔌', 'href' => '/admin/plugins'],
        ['label' => '令牌', 'value' => $stats['tokens'], 'icon' => '🔑', 'href' => '/admin/tokens'],
        ['label' => '7 天下载', 'value' => $stats['downloads_7d'], 'icon' => '📊'],
        ['label' => '30 天下载', 'value' => $stats['downloads_30d'], 'icon' => '📈'],
    ] as $card)
    <a href="{{ $card['href'] ?? '#' }}" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-200 transition-colors">
        <div class="flex items-center gap-2 mb-2">
            <span>{{ $card['icon'] }}</span>
            <span class="text-xs text-gray-500 uppercase tracking-wide">{{ $card['label'] }}</span>
        </div>
        <div class="text-2xl font-bold text-gray-900">{{ number_format($card['value']) }}</div>
    </a>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    {{-- 下载趋势（14天） --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">近 14 天下载趋势</h3>
        <div class="h-48 flex items-end gap-1" id="chart-daily">
            @php
                $maxDl = $dailyDownloads->max('cnt') ?: 1;
            @endphp
            @foreach($dailyDownloads as $day)
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="text-xs text-gray-400">{{ $day->cnt }}</div>
                <div class="w-full bg-indigo-500 rounded-t"
                     style="height: {{ max(4, ($day->cnt / $maxDl) * 160) }}px"
                     title="{{ $day->date }}: {{ $day->cnt }} 次"></div>
                <div class="text-xs text-gray-400 truncate w-full text-center">{{ \Carbon\Carbon::parse($day->date)->format('m/d') }}</div>
            </div>
            @endforeach
            @if($dailyDownloads->isEmpty())
            <div class="flex-1 text-center text-gray-400 text-sm py-16">暂无数据</div>
            @endif
        </div>
    </div>

    {{-- 产品下载分布 --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">产品下载量</h3>
        @if($topProducts->isEmpty())
        <div class="text-center text-gray-400 text-sm py-16">暂无数据</div>
        @else
        <div class="space-y-3">
            @php $maxTotal = $topProducts->max('total') ?: 1 @endphp
            @foreach($topProducts as $p)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700">{{ $p->product }}</span>
                    <span class="text-sm text-gray-500">{{ number_format($p->total) }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ ($p->total / $maxTotal) * 100 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- 平台分布 --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">平台分布</h3>
        @if($platformDist->isEmpty())
        <div class="text-center text-gray-400 text-sm py-16">暂无数据</div>
        @else
        <div class="space-y-3">
            @php $maxP = $platformDist->max('total') ?: 1 @endphp
            @foreach($platformDist as $p)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700">{{ $p->platform }}</span>
                    <span class="text-sm text-gray-500">{{ number_format($p->total) }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ ($p->total / $maxP) * 100 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- 架构分布 --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">架构分布</h3>
        @if($archDist->isEmpty())
        <div class="text-center text-gray-400 text-sm py-16">暂无数据</div>
        @else
        <div class="space-y-3">
            @php $maxA = $archDist->max('total') ?: 1 @endphp
            @foreach($archDist as $a)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-mono text-gray-700">{{ $a->arch }}</span>
                    <span class="text-sm text-gray-500">{{ number_format($a->total) }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-amber-500 h-2 rounded-full" style="width: {{ ($a->total / $maxA) * 100 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- 最新版本 --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">最近上传</h3>
        <a href="/admin/releases" class="text-sm text-indigo-600 hover:text-indigo-800 transition-colors">查看全部 →</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-left">产品</th>
                <th class="px-5 py-3 text-left">版本</th>
                <th class="px-5 py-3 text-left">平台</th>
                <th class="px-5 py-3 text-left">架构</th>
                <th class="px-5 py-3 text-right">下载</th>
                <th class="px-5 py-3 text-right">上传时间</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($recentReleases as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-900">{{ $r->product }}</td>
                <td class="px-5 py-3 font-mono text-indigo-600">{{ $r->version }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $r->platform }}</td>
                <td class="px-5 py-3 font-mono text-gray-600">{{ $r->arch }}</td>
                <td class="px-5 py-3 text-right text-gray-500">{{ number_format($r->download_cnt) }}</td>
                <td class="px-5 py-3 text-right text-gray-400">{{ $r->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            @endforeach
            @if($recentReleases->isEmpty())
            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">暂无版本</td></tr>
            @endif
        </tbody>
    </table>
</div>

@endsection
