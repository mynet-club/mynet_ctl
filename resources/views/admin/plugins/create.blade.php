@extends('admin.layout')

@section('title', '上传插件')

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="/admin/plugins" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">标识名 <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="proxy"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">显示名称 <span class="text-red-500">*</span></label>
                    <input type="text" name="display_name" value="{{ old('display_name') }}" required placeholder="代理插件"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">分类 <span class="text-red-500">*</span></label>
                    <select name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @foreach(['proxy' => '代理', 'dns' => 'DNS', 'service' => '服务', 'tool' => '工具', 'general' => '通用'] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('category') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">版本 <span class="text-red-500">*</span></label>
                    <input type="text" name="version" value="{{ old('version') }}" required placeholder="v1.0.0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">平台 <span class="text-red-500">*</span></label>
                    <select name="platform" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @foreach(['linux', 'openwrt', 'darwin', 'windows', 'all'] as $p)
                        <option value="{{ $p }}" {{ old('platform') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">架构</label>
                    <input type="text" name="arch" value="{{ old('arch', 'all') }}" placeholder="all"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">描述</label>
                <textarea name="description" rows="3" placeholder="插件功能说明…"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">插件文件 <span class="text-red-500">*</span></label>
                <input type="file" name="file" required
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border file:border-gray-300 file:text-sm file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-50" />
                <p class="text-xs text-gray-400 mt-1">最大 100 MB</p>
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
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">上传</button>
            <a href="/admin/plugins" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">取消</a>
        </div>
    </form>
</div>

@endsection
