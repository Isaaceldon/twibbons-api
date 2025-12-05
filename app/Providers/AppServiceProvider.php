<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ImageProcessingService::class);
        $this->app->singleton(\App\Services\VideoService::class);
        $this->app->singleton(\App\Services\QRCodeService::class);
        $this->app->singleton(\App\Services\StatisticsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        // Fix for MySQL string length
        Schema::defaultStringLength(191);

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Custom validation rules
        \Validator::extend('hex_color', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value);
        });
    }
}
