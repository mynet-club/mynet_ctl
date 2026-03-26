<?php

use App\Http\Controllers\Api\V1\DetectController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\ProxyController;
use App\Http\Controllers\Api\V1\QuickConfigController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\ScriptController;
use App\Http\Controllers\Api\V1\SetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MyNet CTL REST API 路由（/api/v2）
|--------------------------------------------------------------------------
|
| 标准 RESTful 接口。文件类端点（.json / .sh）请见 routes/web.php → /d/ 前缀。
|
| 认证方式: Authorization: Bearer {token}
|
*/

Route::prefix('v2')->group(function () {

    // ── Releases ─────────────────────────────────────────────────────────
    Route::prefix('releases')->group(function () {
        Route::get('/', [ReleaseController::class, 'index']);
        Route::get('/latest', [ReleaseController::class, 'latest']);
        Route::get('/{releaseId}/download/{filename}', [ReleaseController::class, 'download'])
            ->where(['releaseId' => '[0-9]+', 'filename' => '.+']);

        Route::middleware('auth.token:release:upload')
            ->post('/', [ReleaseController::class, 'store']);
        Route::middleware('auth.token:release:manage')
            ->delete('/{releaseId}', [ReleaseController::class, 'disable'])
            ->where('releaseId', '[0-9]+');
    });

    // ── Plugins ──────────────────────────────────────────────────────────
    Route::prefix('plugins')->group(function () {
        Route::get('/', [PluginController::class, 'index']);
        Route::get('/{pluginId}/download/{filename}', [PluginController::class, 'download'])
            ->where(['pluginId' => '[0-9]+', 'filename' => '.+']);

        Route::middleware('auth.token:plugin:upload')
            ->post('/', [PluginController::class, 'store']);
        Route::middleware('auth.token:plugin:manage')
            ->delete('/{pluginId}', [PluginController::class, 'disable'])
            ->where('pluginId', '[0-9]+');
    });

    // ── Scripts ──────────────────────────────────────────────────────────
    Route::prefix('scripts')->group(function () {
        Route::get('/', [ScriptController::class, 'index']);
        Route::get('/{scriptId}/raw/{name}', [ScriptController::class, 'raw'])
            ->where(['scriptId' => '[0-9]+', 'name' => '[^/]+']);

        Route::middleware('auth.token:script:publish')->group(function () {
            Route::post('/', [ScriptController::class, 'store']);
            Route::put('/{scriptId}', [ScriptController::class, 'update'])
                ->where('scriptId', '[0-9]+');
        });
        Route::middleware('auth.token:script:manage')
            ->delete('/{scriptId}', [ScriptController::class, 'disable'])
            ->where('scriptId', '[0-9]+');
    });

    // ── Detect（JSON API） ───────────────────────────────────────────────
    Route::prefix('detect')->group(function () {
        Route::get('/openwrt', [DetectController::class, 'openwrt']);
        Route::get('/arch-map', [DetectController::class, 'archMap']);
    });

    // ── Proxy（JSON API） ────────────────────────────────────────────────
    Route::get('/proxy/latest', [ProxyController::class, 'latest']);

    // ── Setup（代理 mynet_back，Token 认证） ─────────────────────────────
    Route::prefix('setup')->group(function () {
        Route::get('/platforms', [SetupController::class, 'platforms']);
        Route::get('/zones', [SetupController::class, 'zones']);
        Route::post('/switch-zone', [SetupController::class, 'switchZone']);
        Route::get('/nodes', [SetupController::class, 'nodes']);
        Route::get('/nodes/{nodeId}', [SetupController::class, 'nodeDetail'])
            ->where('nodeId', '[0-9]+');
        Route::get('/config-pack/{nodeId}', [SetupController::class, 'configPack'])
            ->where('nodeId', '[0-9]+');
    });

    // ── QuickConfig（快配，无需 mynet_back 认证） ──────────────────────────
    Route::prefix('quickconfig')->group(function () {
        Route::get('/node-id', [QuickConfigController::class, 'nodeId']);
        Route::post('/generate', [QuickConfigController::class, 'generate']);
    });

});
