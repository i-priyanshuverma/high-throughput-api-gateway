<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{
    /**
     * Gateway status and health endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'service' => 'High-Throughput API Gateway',
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'octane' => extension_loaded('swoole') ? 'swoole-active' : 'standard-cli',
        ]);
    }
}
