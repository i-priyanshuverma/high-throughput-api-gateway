<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ApiGatewayProxyMiddleware
{
    /**
     * Handle an incoming request by proxying to downstream microservices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $serviceKey
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $serviceKey = null): Response
    {
        $services = config('gateway.services', []);
        
        // Auto-detect service key from URI path if not explicitly provided
        if (!$serviceKey) {
            $path = ltrim($request->getPathInfo(), '/');
            foreach ($services as $key => $config) {
                if (str_starts_with($path, ltrim($config['prefix'], '/'))) {
                    $serviceKey = $key;
                    break;
                }
            }
        }

        if (!$serviceKey || !isset($services[$serviceKey])) {
            return response()->json([
                'error' => 'Bad Gateway',
                'message' => 'No downstream microservice configured for this endpoint route.',
                'status' => 502,
            ], 502);
        }

        $serviceConfig = $services[$serviceKey];
        $baseUrl = rtrim($serviceConfig['base_url'], '/');
        
        // Calculate relative subpath
        $prefix = ltrim($serviceConfig['prefix'], '/');
        $fullPath = ltrim($request->getPathInfo(), '/');
        $subPath = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $fullPath);
        $targetUrl = $baseUrl . '/' . ltrim($subPath, '/');

        // Extract forwardable headers
        $headers = collect($request->headers->all())
            ->mapWithKeys(fn ($v, $k) => [$k => is_array($v) ? implode(', ', $v) : $v])
            ->except(['host', 'content-length'])
            ->toArray();

        $headers['X-Forwarded-For'] = $request->ip();
        $headers['X-Forwarded-Proto'] = $request->getScheme();
        $headers['X-Gateway-Request-ID'] = $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid());

        try {
            $method = strtolower($request->getMethod());
            $timeout = $serviceConfig['timeout'] ?? 5.0;

            $httpClient = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withoutVerifying();

            if (in_array($method, ['get', 'head', 'delete'])) {
                $response = $httpClient->$method($targetUrl, $request->query());
            } else {
                $response = $httpClient->withBody($request->getContent(), $request->header('Content-Type', 'application/json'))
                    ->$method($targetUrl, $request->query());
            }

            return response($response->body(), $response->status())
                ->withHeaders(collect($response->headers())->mapWithKeys(fn ($v, $k) => [$k => implode(', ', $v)])->toArray());

        } catch (\Throwable $e) {
            Log::error("Gateway Proxy Connection Error [{$serviceKey}]: " . $e->getMessage());

            return response()->json([
                'error' => 'Bad Gateway',
                'message' => "Failed to reach downstream service [{$serviceKey}]: " . $e->getMessage(),
                'status' => 502,
            ], 502);
        }
    }
}
