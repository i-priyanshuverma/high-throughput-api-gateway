<?php

use App\Http\Middleware\ApiGatewayProxyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong', 'timestamp' => time()]);
});

// Protected microservice proxy endpoints with auth, rate limiting, and circuit breaker
Route::middleware(['gateway.ratelimit', 'gateway.circuitbreaker'])->group(function () {

    // Authenticated Users microservice routes
    Route::middleware(['gateway.auth'])->group(function () {
        Route::any('/v1/users/{any?}', [ApiGatewayProxyMiddleware::class, 'handle'])
            ->where('any', '.*')
            ->name('proxy.users');

        Route::any('/v1/orders/{any?}', [ApiGatewayProxyMiddleware::class, 'handle'])
            ->where('any', '.*')
            ->name('proxy.orders');
    });

    // Public / semi-protected Products microservice route
    Route::any('/v1/products/{any?}', [ApiGatewayProxyMiddleware::class, 'handle'])
        ->where('any', '.*')
        ->name('proxy.products');
});
