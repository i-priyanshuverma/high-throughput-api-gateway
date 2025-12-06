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

    /**
     * Kubernetes readiness check endpoint (/healthz).
     */
    public function readiness(): JsonResponse
    {
        $redisStatus = 'ok';
        try {
            \Illuminate\Support\Facades\Redis::ping();
        } catch (\Throwable $e) {
            $redisStatus = 'degraded';
        }

        $isReady = $redisStatus === 'ok';

        return response()->json([
            'status' => $isReady ? 'ready' : 'not_ready',
            'redis' => $redisStatus,
            'timestamp' => now()->toIso8601String(),
        ], $isReady ? 200 : 503);
    }
}

