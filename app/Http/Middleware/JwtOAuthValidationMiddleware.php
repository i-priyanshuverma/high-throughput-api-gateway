<?php

namespace App\Http\Middleware;

use App\Services\JwtKeyCacheService;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtOAuthValidationMiddleware
{
    protected JwtKeyCacheService $keyCache;

    public function __construct(JwtKeyCacheService $keyCache)
    {
        $this->keyCache = $keyCache;
    }

    /**
     * Handle incoming request by validating JWT / OAuth2 token with cached public keys.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $authorizationHeader = $request->header('Authorization');

        if (! $authorizationHeader || ! str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or malformed Authorization header. Expected Bearer <token>.',
                'status' => 401,
            ], 401);
        }

        $jwtToken = substr($authorizationHeader, 7);
        $secretKey = $this->keyCache->getPublicKey('default');
        $algorithm = (string) config('gateway.auth.algorithm', 'HS256');

        try {
            $decoded = JWT::decode($jwtToken, new Key($secretKey, $algorithm));

            if (isset($decoded->exp) && $decoded->exp < time()) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication token has expired.',
                    'status' => 401,
                ], 401);
            }

            if ($requiredScope && isset($decoded->scopes) && is_array($decoded->scopes)) {
                if (! in_array($requiredScope, $decoded->scopes, true)) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => "Insufficient scope. Required scope: {$requiredScope}",
                        'status' => 403,
                    ], 403);
                }
            }

            $request->attributes->set('jwt_claims', (array) $decoded);
            $request->attributes->set('user_id', $decoded->sub ?? null);

            return $next($request);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid OAuth2 / JWT authentication token: '.$e->getMessage(),
                'status' => 401,
            ], 401);
        }
    }
}
