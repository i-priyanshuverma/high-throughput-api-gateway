<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong', 'timestamp' => time()]);
});

// Proxy routes mapped to downstream microservices with sliding window rate limiting
Route::middleware(['gateway.ratelimit'])->group(function () {
    Route::any('/v1/users/{any?}', [App\Http\Middleware\ApiGatewayProxyMiddleware::class, 'handle'])
        ->where('any', '.*')
        ->name('proxy.users');

    Route::any('/v1/orders/{any?}', [App\Http\Middleware\ApiGatewayProxyMiddleware::class, 'handle'])
        ->where('any', '.*')
        ->name('proxy.orders');

    Route::any('/v1/products/{any?}', [App\Http\Middleware\ApiGatewayProxyMiddleware::class, 'handle'])
        ->where('any', '.*')
        ->name('proxy.products');
});
