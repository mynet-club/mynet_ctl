<?php

use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\ScriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MyNet CTL API 路由
|--------------------------------------------------------------------------
|
| 公开读取路由：无需认证（下载、查询）
| 写入/管理路由：需要 API Token 认证
|
| 认证方式: Authorization: Bearer {token}
|
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Releases - 软件版本（tui / utc / gnb）
    // -------------------------------------------------------------------------
    Route::prefix('releases')->group(function () {
        // 公开：查询版本列表
        Route::get('/', [ReleaseController::class, 'index']);
        Route::get('/latest', [ReleaseController::class, 'latest']);

        // 公开：下载（文件流，无需认证）
        Route::get('/{releaseId}/download/{filename}', [ReleaseController::class, 'download'])
            ->where(['releaseId' => '[0-9]+', 'filename' => '.+']);

        // 受保护：上传/管理（需要 API Token）
        Route::middleware('auth.token:release:upload')
            ->post('/', [ReleaseController::class, 'store']);

        Route::middleware('auth.token:release:manage')
            ->delete('/{releaseId}', [ReleaseController::class, 'disable'])
            ->where('releaseId', '[0-9]+');
    });

    // -------------------------------------------------------------------------
    // Plugins - 插件
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // Scripts - 安装/服务脚本
    // -------------------------------------------------------------------------
    Route::prefix('scripts')->group(function () {
        Route::get('/', [ScriptController::class, 'index']);

        // 纯文本内容（供 curl | bash 直接使用）
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

});
