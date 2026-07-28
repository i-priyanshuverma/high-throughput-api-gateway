<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiGatewayProxyMiddleware
{
    /**
     * Dangerous or internal headers that must be stripped before forwarding.
     *
     * @var array<string>
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
     */
    public function handle(Request $request, Closure $next, ?string $serviceKey = null): Response
    {
        $services = config('gateway.services', []);

        if (! $serviceKey) {
            $path = ltrim($request->getPathInfo(), '/');
            foreach ($services as $key => $config) {
                if (str_starts_with($path, ltrim((string) $config['prefix'], '/'))) {
                    $serviceKey = (string) $key;
                    break;
                }
            }
        }

        if (! $serviceKey || ! isset($services[$serviceKey])) {
            return response()->json([
                'error' => 'Bad Gateway',
                'message' => 'No downstream microservice configured for this endpoint route.',
                'status' => 502,
            ], 502);
        }

        $serviceConfig = $services[$serviceKey];
        $baseUrl = rtrim((string) $serviceConfig['base_url'], '/');

        $prefix = ltrim((string) $serviceConfig['prefix'], '/');
        $fullPath = ltrim($request->getPathInfo(), '/');
        $subPath = (string) preg_replace('/^'.preg_quote($prefix, '/').'/', '', $fullPath);
        $targetUrl = $baseUrl.'/'.ltrim($subPath, '/');

        $headers = [];
        foreach ($request->headers->all() as $k => $v) {
            $headerKey = strtolower((string) $k);
            if (in_array($headerKey, $this->blacklistedHeaders, true)) {
                continue;
            }
            $val = implode(', ', $v);
            $headers[$headerKey] = str_replace(["\r", "\n"], '', $val);
        }

        $headers['x-forwarded-for'] = (string) $request->ip();
        $headers['x-forwarded-proto'] = $request->getScheme();
        $headers['x-gateway-request-id'] = $request->header('X-Request-ID', (string) Str::uuid());
        $headers['connection'] = 'keep-alive';

        try {
            $method = strtolower($request->getMethod());
            $timeout = (float) ($serviceConfig['timeout'] ?? 5.0);
            $retry = (int) ($serviceConfig['retry'] ?? 2);

            // Retries with 100ms backoff on connection resets under high concurrency
            $httpClient = Http::retry($retry, 100, function (\Throwable $exception) {
                return $exception instanceof ConnectionException;
            }, throw: false)
                ->withHeaders($headers)
                ->timeout($timeout)
                ->withoutVerifying();

            if (in_array($method, ['get', 'head', 'delete'], true)) {
                $response = $httpClient->$method($targetUrl, $request->query());
            } else {
                $response = $httpClient->withBody((string) $request->getContent(), (string) $request->header('Content-Type', 'application/json'))
                    ->$method($targetUrl, $request->query());
            }

            $responseHeaders = [];
            foreach ($response->headers() as $k => $v) {
                if (! in_array(strtolower((string) $k), ['transfer-encoding', 'content-length', 'connection'], true)) {
                    $responseHeaders[$k] = is_array($v) ? implode('; ', array_filter($v)) : (string) $v;
                }
            }

            return response((string) $response->body(), $response->status())
                ->withHeaders($responseHeaders);

        } catch (\Throwable $e) {
            Log::error("Gateway Proxy Connection Error [{$serviceKey}]: ".$e->getMessage());

            return response()->json([
                'error' => 'Bad Gateway',
                'message' => "Failed to reach downstream service [{$serviceKey}]: ".$e->getMessage(),
                'status' => 502,
            ], 502);
        }
    }
}
