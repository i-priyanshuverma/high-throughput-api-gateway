<?php

namespace App\Providers;

use App\Services\CircuitBreaker;
use App\Services\MetricsCollector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CircuitBreaker::class, function ($app) {
            return new CircuitBreaker;
        });

        $this->app->singleton(MetricsCollector::class, function ($app) {
            return new MetricsCollector;
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
