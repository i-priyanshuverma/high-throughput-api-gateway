<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Throwable;

class Rfc7807ProblemDetails
{
    /**
     * Render an exception into an RFC 7807 compliant Problem Details JSON response.
     */
    public static function render(Throwable $e, int $status = 500, string $title = 'Internal Server Error', ?string $type = null): JsonResponse
    {
        $payload = [
            'type' => $type ?? 'https://tools.ietf.org/html/rfc7807',
            'title' => $title,
            'status' => $status,
            'detail' => $e->getMessage() ?: 'An unhandled server exception occurred within the Gateway pipeline.',
            'instance' => request()->fullUrl(),
            'timestamp' => now()->toIso8601String(),
        ];

        if (config('app.debug')) {
            $payload['exception'] = get_class($e);
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
        }

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
