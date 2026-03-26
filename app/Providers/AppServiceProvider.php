<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('ctlUrl', rtrim(config('app.url'), '/'));
        View::share('wwwUrl', rtrim(config('services.mynet_urls.www'), '/'));
        View::share('guiUrl', rtrim(config('services.mynet_urls.gui'), '/'));
        View::share('docsUrl', rtrim(config('services.mynet_urls.docs'), '/'));
    }
}
