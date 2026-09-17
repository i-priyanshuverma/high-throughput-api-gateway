<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class GatewayResponseCacheMiddleware
{
    /**
     * Handle an incoming request with dynamic Redis response caching for GET endpoints.
     */
    public function handle(Request $request, Closure $next, ?int $ttl = null): Response
    {
        $enabled = config('gateway.cache.enabled', true);

        // Only cache idempotent GET and HEAD requests when enabled and no bypass requested
        if (!$enabled || !in_array(strtoupper($request->getMethod()), ['GET', 'HEAD']) || $request->header('X-Cache-Bypass') === 'true') {
            $response = $next($request);
            return $response->header('X-Gateway-Cache', 'BYPASS');
        }

        $cachePrefix = config('gateway.cache.prefix', 'gateway:response_cache:');
        $ttlSeconds = $ttl ?? (int) config('gateway.cache.ttl', 60);

        $cacheKey = $cachePrefix . md5($request->fullUrl() . '|' . $request->header('Authorization', ''));

        try {
            $cachedData = Redis::get($cacheKey);
            if ($cachedData) {
                $payload = json_decode((string) $cachedData, true);
                if (is_array($payload) && isset($payload['body'], $payload['status'])) {
                    return response($payload['body'], $payload['status'])
                        ->withHeaders(array_merge(
                            $payload['headers'] ?? [],
                            ['X-Gateway-Cache' => 'HIT']
                        ));
                }
            }
        } catch (\Throwable $e) {
            // Redis error fallback: proceed without cache
        }

        /** @var Response $response */
        $response = $next($request);

        // Cache 200 OK responses
        if ($response->getStatusCode() === 200) {
            try {
                $payload = [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'headers' => [
                        'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                    ],
                ];
                Redis::setex($cacheKey, $ttlSeconds, json_encode($payload));
            } catch (\Throwable $e) {
                // Ignore Redis save error
            }
        }

        return $response->header('X-Gateway-Cache', 'MISS');
    }
}
