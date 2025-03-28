<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RedisSlidingWindowRateLimiter
{
    /**
     * Handle an incoming request with Redis sliding-window rate limiting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|null  $maxAttempts
     * @param  int|null  $decaySeconds
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?int $maxAttempts = null, ?int $decaySeconds = null): Response
    {
        $maxAttempts = $maxAttempts ?? (int) config('gateway.rate_limiting.max_attempts', 100);
        $decaySeconds = $decaySeconds ?? (int) config('gateway.rate_limiting.decay_seconds', 60);

        $identifier = $request->header('X-API-Key') ?? $request->ip();
        $key = "ratelimit:sliding:" . sha1($identifier . ':' . $request->route()?->getName());

        $now = microtime(true);
        $windowStart = $now - $decaySeconds;

        try {
            // Remove requests outside current sliding window
            Redis::zremrangebyscore($key, '-inf', (string) $windowStart);

            // Count requests in current window
            $currentRequests = Redis::zcard($key);

            if ($currentRequests >= $maxAttempts) {
                // Get oldest element score to calculate exact reset time
                $oldestScores = Redis::zrange($key, 0, 0, ['WITHSCORES' => true]);
                $oldestTime = !empty($oldestScores) ? (float) reset($oldestScores) : $now;
                $resetTime = (int) ceil($oldestTime + $decaySeconds);
                $retryAfter = max(1, $resetTime - (int) $now);

                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => 'API rate limit exceeded. Please wait before sending more requests.',
                    'status' => 429,
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'X-RateLimit-Limit' => $maxAttempts,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => $resetTime,
                    'Retry-After' => $retryAfter,
                ]);
            }

            // Record request timestamp with unique member ID
            $member = $now . ':' . uniqid('', true);
            Redis::zadd($key, $now, $member);
            Redis::expire($key, $decaySeconds + 5);

            $remaining = max(0, $maxAttempts - ($currentRequests + 1));
            $resetTime = (int) ceil($now + $decaySeconds);

            $response = $next($request);

            return $response->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => $remaining,
                'X-RateLimit-Reset' => $resetTime,
            ]);

        } catch (\Throwable $e) {
            // Fallback gracefully if Redis connection is unavailable during testing/local dev
            return $next($request);
        }
    }
}
