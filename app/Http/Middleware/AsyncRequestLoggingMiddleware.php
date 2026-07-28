<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AsyncRequestLoggingMiddleware
{
    /**
     * Handle incoming request by asynchronously recording request telemetry to Redis stream.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $logData = [
            'timestamp' => now()->toIso8601String(),
            'request_id' => $request->header('X-Gateway-Request-ID', (string) Str::uuid()),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
            'status' => (string) $response->getStatusCode(),
            'duration_ms' => (string) $durationMs,
            'user_agent' => substr((string) $request->userAgent(), 0, 150),
        ];

        try {
            // Push asynchronous log entry into Redis Stream
            Redis::xadd('gateway:logs:stream', '*', $logData);
        } catch (\Throwable $e) {
            // Fail silently to prevent logging layer from affecting gateway latency
        }

        return $response;
    }
}
