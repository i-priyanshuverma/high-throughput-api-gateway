<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'gateway.auth' => \App\Http\Middleware\JwtOAuthValidationMiddleware::class,
            'gateway.ratelimit' => \App\Http\Middleware\RedisSlidingWindowRateLimiter::class,
            'gateway.circuitbreaker' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
            'gateway.proxy' => \App\Http\Middleware\ApiGatewayProxyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling for API gateway
    })->create();
