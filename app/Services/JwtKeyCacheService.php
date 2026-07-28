<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class JwtKeyCacheService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = 86400; // Cache public key for 24 hours
    }

    /**
     * Get or fetch public key from Redis cache.
     */
    public function getPublicKey(string $keyId = 'default'): string
    {
        $cacheKey = "jwt:public_key:{$keyId}";

        try {
            $cachedKey = Redis::get($cacheKey);
            if ($cachedKey) {
                return (string) $cachedKey;
            }
        } catch (\Throwable $e) {
            // Fallback if Redis fails
        }

        $secret = config('gateway.auth.secret', 'super-secret-jwt-signing-key-for-api-gateway-auth-2025');

        try {
            Redis::setex($cacheKey, $this->ttl, $secret);
        } catch (\Throwable $e) {
            // Ignore Redis write errors
        }

        return $secret;
    }
}
