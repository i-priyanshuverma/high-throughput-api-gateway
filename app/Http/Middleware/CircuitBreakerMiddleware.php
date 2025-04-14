<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CircuitBreaker;
use Symfony\Component\HttpFoundation\Response;

class CircuitBreakerMiddleware
{
    protected CircuitBreaker $circuitBreaker;

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
    }

    /**
     * Handle incoming request with Circuit Breaker protections.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $serviceKey
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $serviceKey = null): Response
    {
        $services = config('gateway.services', []);

        if (!$serviceKey) {
            $path = ltrim($request->getPathInfo(), '/');
            foreach ($services as $key => $config) {
                if (str_starts_with($path, ltrim($config['prefix'], '/'))) {
                    $serviceKey = $key;
                    break;
                }
            }
        }

        $serviceKey = $serviceKey ?? 'default';

        if (!$this->circuitBreaker->isAvailable($serviceKey)) {
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => "Downstream microservice [{$serviceKey}] is experiencing downtime or elevated failure rates. Circuit Breaker is OPEN.",
                'status' => 503,
                'fallback' => true,
                'service' => $serviceKey,
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        /** @var Response $response */
        $response = $next($request);

        // Check if downstream response indicates failure (5xx server errors)
        if ($response->getStatusCode() >= 500) {
            $this->circuitBreaker->recordFailure($serviceKey);
        } else {
            $this->circuitBreaker->recordSuccess($serviceKey);
        }

        return $response;
    }
}
