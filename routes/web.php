<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewayController;

Route::get('/', function () {
    return response()->json([
        'gateway' => 'High-Throughput API Gateway',
        'version' => '1.0.0',
        'status' => 'online',
    ]);
});

Route::get('/health', [GatewayController::class, 'health']);
