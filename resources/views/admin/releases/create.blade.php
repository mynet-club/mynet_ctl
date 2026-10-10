@extends('admin.layout')

@section('title', '上传版本')

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="/admin/releases" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">产品 <span class="text-red-500">*</span></label>
                    <select name="product" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @foreach($products as $key => $meta)
                        <option value="{{ $key }}" {{ old('product') === $key ? 'selected' : '' }}>{{ $key }} ({{ $meta['name'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">版本号 <span class="text-red-500">*</span></label>
                    <input type="text" name="version" value="{{ old('version') }}" required placeholder="v1.4.0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">平台 <span class="text-red-500">*</span></label>
                    <select name="platform" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @foreach($platforms as $key => $meta)
                        <option value="{{ $key }}" {{ old('platform') === $key ? 'selected' : '' }}>{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">架构 <span class="text-red-500">*</span></label>
                    <select name="arch" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @foreach(['amd64', 'arm64', 'armv7', 'i386', 'mips', 'mips64', 'mipsel', 'mips64el', 'riscv64'] as $a)
                        <option value="{{ $a }}" {{ old('arch') === $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">变体</label>
                    <input type="text" name="variant" value="{{ old('variant') }}" placeholder="softfp, musl 等"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">二进制文件 <span class="text-red-500">*</span></label>
                <input type="file" name="file" required
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border file:border-gray-300 file:text-sm file:font-medium file:bg-white file:text-gray-700 hover:file:bg-gray-50" />
                <p class="text-xs text-gray-400 mt-1">最大 200 MB</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">更新说明</label>
                <textarea name="changelog" rows="4" placeholder="Markdown 格式的更新说明…"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y">{{ old('changelog') }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_stable" value="1" id="is_stable" {{ old('is_stable', '1') ? 'checked' : '' }}
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" />
                <label for="is_stable" class="text-sm text-gray-700">标记为稳定版</label>
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
                上传
            </button>
            <a href="/admin/releases" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">取消</a>
        </div>
    </form>
</div>

@endsection
