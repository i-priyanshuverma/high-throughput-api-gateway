<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\MetricsController;

Route::get('/', function () {
    return response()->json([
        'gateway' => 'High-Throughput API Gateway',
        'version' => '1.0.0',
        'status' => 'online',
    ]);
});

Route::get('/health', [GatewayController::class, 'health']);
Route::get('/metrics', MetricsController::class)->name('metrics');
