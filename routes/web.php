<?php

use App\Http\Controllers\GatewayController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'gateway' => 'High-Throughput API Gateway',
        'version' => '1.0.0',
        'status' => 'online',
    ]);
});

Route::get('/health', [GatewayController::class, 'health']);
Route::get('/healthz', [GatewayController::class, 'readiness']);
Route::get('/metrics', MetricsController::class)->name('metrics');
