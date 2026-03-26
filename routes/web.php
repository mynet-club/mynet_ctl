<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PluginAdminController;
use App\Http\Controllers\Admin\ReleaseAdminController;
use App\Http\Controllers\Admin\ScriptAdminController;
use App\Http\Controllers\Admin\TokenAdminController;
use App\Http\Controllers\Api\V1\DetectController;
use App\Http\Controllers\Api\V1\ManifestController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\ProxyController;
use App\Http\Controllers\Api\V1\ScriptController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// ── 公开页面 ──────────────────────────────────────────────
Route::get('/', [PageController::class, 'home']);
Route::get('/downloads', [PageController::class, 'downloads']);
Route::get('/openwrt', [PageController::class, 'openwrt']);
Route::get('/tools', [PageController::class, 'tools']);
Route::get('/setup', [PageController::class, 'setup']);
Route::get('/quickconfig', [PageController::class, 'quickconfig']);

// ── 分发文件（.json / .sh，非 REST API） ─────────────────
Route::prefix('d')->group(function () {
    Route::get('/apps.json', [ManifestController::class, 'appsIndex']);
    Route::get('/conventions.json', [ManifestController::class, 'conventions']);
    Route::get('/{product}/manifest.json', [ManifestController::class, 'manifest'])
        ->where('product', '[a-z0-9_\-]+');
    Route::get('/{product}/plugins.json', [ManifestController::class, 'plugins'])
        ->where('product', '[a-z0-9_\-]+');
    Route::get('/detect/openwrt.sh', [DetectController::class, 'openwrtScript']);
    Route::get('/proxy/install.sh', [ProxyController::class, 'installScript']);

    // 插件下载
    Route::get('/plugins/{filename}', [PluginController::class, 'downloadByFilename'])
        ->where('filename', '.+\.(tgz|zip|tar\.gz)');

    // 脚本原文下载
    Route::get('/scripts/{targetVersion}/{name}', [ScriptController::class, 'rawVersioned'])
        ->where(['targetVersion' => 'v[\d][\d.]+[a-z]*', 'name' => '[a-z0-9_\-]+\.(sh|bash|py)']);
    Route::get('/scripts/{name}', [ScriptController::class, 'rawByRoute'])
        ->where('name', '[a-z0-9_\-]+\.(sh|bash|py)');

    // 二进制文件下载（按文件名解析 product/platform/arch/version）
    Route::get('/{product}/{filename}', [ManifestController::class, 'downloadRelease'])
        ->where(['product' => '[a-z0-9_\-]+', 'filename' => '.+\.(tgz|zip|tar\.gz|ipk)']);
});

// ── Admin 登录（无需中间件） ──────────────────────────────
Route::get('/admin/login', [DashboardController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [DashboardController::class, 'doLogin']);
Route::post('/admin/logout', [DashboardController::class, 'logout'])->name('admin.logout');

// ── Admin 后台（需要认证） ────────────────────────────────
Route::prefix('admin')->middleware('auth.admin')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Releases
    Route::get('/releases', [ReleaseAdminController::class, 'index'])->name('admin.releases');
    Route::get('/releases/create', [ReleaseAdminController::class, 'create'])->name('admin.releases.create');
    Route::post('/releases', [ReleaseAdminController::class, 'store']);
    Route::post('/releases/{id}/toggle', [ReleaseAdminController::class, 'toggle'])->where('id', '[0-9]+');
    Route::delete('/releases/{id}', [ReleaseAdminController::class, 'destroy'])->where('id', '[0-9]+');

    // Scripts
    Route::get('/scripts', [ScriptAdminController::class, 'index'])->name('admin.scripts');
    Route::get('/scripts/create', [ScriptAdminController::class, 'create'])->name('admin.scripts.create');
    Route::get('/scripts/{id}/edit', [ScriptAdminController::class, 'edit'])->where('id', '[0-9]+')->name('admin.scripts.edit');
    Route::post('/scripts', [ScriptAdminController::class, 'store']);
    Route::put('/scripts/{id}', [ScriptAdminController::class, 'update'])->where('id', '[0-9]+');
    Route::post('/scripts/{id}/toggle', [ScriptAdminController::class, 'toggle'])->where('id', '[0-9]+');

    // Plugins
    Route::get('/plugins', [PluginAdminController::class, 'index'])->name('admin.plugins');
    Route::get('/plugins/create', [PluginAdminController::class, 'create'])->name('admin.plugins.create');
    Route::post('/plugins', [PluginAdminController::class, 'store']);
    Route::post('/plugins/{id}/toggle', [PluginAdminController::class, 'toggle'])->where('id', '[0-9]+');

    // Tokens
    Route::get('/tokens', [TokenAdminController::class, 'index'])->name('admin.tokens');
    Route::get('/tokens/create', [TokenAdminController::class, 'create'])->name('admin.tokens.create');
    Route::post('/tokens', [TokenAdminController::class, 'store']);
    Route::post('/tokens/{id}/toggle', [TokenAdminController::class, 'toggle'])->where('id', '[0-9]+');
    Route::delete('/tokens/{id}', [TokenAdminController::class, 'destroy'])->where('id', '[0-9]+');
});
