<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\CircuitBreaker::class, function ($app) {
            return new \App\Services\CircuitBreaker();
        });

        $this->app->singleton(\App\Services\MetricsCollector::class, function ($app) {
            return new \App\Services\MetricsCollector();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
