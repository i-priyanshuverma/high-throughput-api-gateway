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
     * Dangerous or internal headers that must be stripped before forwarding.
     */
    protected array $blacklistedHeaders = [
        'host',
        'content-length',
        'x-envoy-internal',
        'x-internal-token',
        'x-admin-override',
    ];

    /**
     * Handle an incoming request by proxying to downstream microservices with connection reset protection.
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

        if (!$serviceKey || !isset($services[$serviceKey])) {
            return response()->json([
                'error' => 'Bad Gateway',
                'message' => 'No downstream microservice configured for this endpoint route.',
                'status' => 502,
            ], 502);
        }

        $serviceConfig = $services[$serviceKey];
        $baseUrl = rtrim($serviceConfig['base_url'], '/');
        
        $prefix = ltrim($serviceConfig['prefix'], '/');
        $fullPath = ltrim($request->getPathInfo(), '/');
        $subPath = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $fullPath);
        $targetUrl = $baseUrl . '/' . ltrim($subPath, '/');

        $headers = collect($request->headers->all())
            ->mapWithKeys(function ($v, $k) {
                $headerKey = strtolower((string) $k);
                $val = is_array($v) ? implode(', ', $v) : (string) $v;
                $cleanVal = str_replace(["\r", "\n"], '', $val);
                return [$headerKey => $cleanVal];
            })
            ->except($this->blacklistedHeaders)
            ->toArray();

        $headers['x-forwarded-for'] = $request->ip();
        $headers['x-forwarded-proto'] = $request->getScheme();
        $headers['x-gateway-request-id'] = $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid());
        $headers['connection'] = 'keep-alive';

        try {
            $method = strtolower($request->getMethod());
            $timeout = $serviceConfig['timeout'] ?? 5.0;
            $retry = $serviceConfig['retry'] ?? 2;

            // Retries with 100ms backoff on connection resets under high concurrency
            $httpClient = Http::retry($retry, 100, function (\Throwable $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->withHeaders($headers)
            ->timeout($timeout)
            ->withoutVerifying();

            if (in_array($method, ['get', 'head', 'delete'])) {
                $response = $httpClient->$method($targetUrl, $request->query());
            } else {
                $response = $httpClient->withBody($request->getContent(), $request->header('Content-Type', 'application/json'))
                    ->$method($targetUrl, $request->query());
            }

            $responseHeaders = collect($response->headers())
                ->reject(fn ($v, $k) => in_array(strtolower((string) $k), ['transfer-encoding', 'content-length', 'connection']))
                ->mapWithKeys(function ($v, $k) {
                    $val = is_array($v) ? implode('; ', array_filter($v)) : (string) $v;
                    return [$k => $val];
                })
                ->toArray();

            return response($response->body(), $response->status())
                ->withHeaders($responseHeaders);

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
