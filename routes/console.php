<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 定时任务
|--------------------------------------------------------------------------
|
| crontab 只需一条：
|   * * * * * cd /data/app/mynet.club/ctl && php artisan schedule:run >> /dev/null 2>&1
|
*/

// 每天 02:00 更新代理 IP 列表（chinaip.txt / interip.txt）
Schedule::command('proxy:update-iplists')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/proxy-iplists.log'));
