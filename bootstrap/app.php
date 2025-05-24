<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\Rfc7807ProblemDetails;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'gateway.auth' => \App\Http\Middleware\JwtOAuthValidationMiddleware::class,
            'gateway.ratelimit' => \App\Http\Middleware\RedisSlidingWindowRateLimiter::class,
            'gateway.circuitbreaker' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
            'gateway.proxy' => \App\Http\Middleware\ApiGatewayProxyMiddleware::class,
            'gateway.async_log' => \App\Http\Middleware\AsyncRequestLoggingMiddleware::class,
            'gateway.cors' => \App\Http\Middleware\CorsHandlingMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $title = match ($status) {
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Resource Not Found',
                    429 => 'Too Many Requests',
                    502 => 'Bad Gateway',
                    503 => 'Service Unavailable',
                    default => 'Internal Server Error',
                };
                return Rfc7807ProblemDetails::render($e, $status, $title);
            }
        });
    })->create();
