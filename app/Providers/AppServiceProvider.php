<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (file_exists(base_path('../public_html/build/manifest.json'))) {
            $this->app->usePublicPath(realpath(base_path('../public_html')));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Carbon::setLocale('id');
        \Carbon\Carbon::setLocale('id');

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
