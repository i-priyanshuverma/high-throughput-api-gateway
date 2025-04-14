<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Response;

class JwtOAuthValidationMiddleware
{
    /**
     * Handle incoming request by validating JWT / OAuth2 token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $requiredScope
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $authorizationHeader = $request->header('Authorization');

        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or malformed Authorization header. Expected Bearer <token>.',
                'status' => 401,
            ], 401);
        }

        $jwtToken = substr($authorizationHeader, 7);
        $secretKey = config('gateway.auth.secret', 'super-secret-jwt-signing-key-for-api-gateway-auth-2025');
        $algorithm = config('gateway.auth.algorithm', 'HS256');

        try {
            $decoded = JWT::decode($jwtToken, new Key($secretKey, $algorithm));

            // Validate token expiration explicitly
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication token has expired.',
                    'status' => 401,
                ], 401);
            }

            // Scope check if required
            if ($requiredScope && isset($decoded->scopes) && is_array($decoded->scopes)) {
                if (!in_array($requiredScope, $decoded->scopes, true)) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => "Insufficient scope. Required scope: {$requiredScope}",
                        'status' => 403,
                    ], 403);
                }
            }

            // Inject token user claims into request attributes
            $request->attributes->set('jwt_claims', (array) $decoded);
            $request->attributes->set('user_id', $decoded->sub ?? null);

            return $next($request);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid OAuth2 / JWT authentication token: ' . $e->getMessage(),
                'status' => 401,
            ], 401);
        }
    }
}
