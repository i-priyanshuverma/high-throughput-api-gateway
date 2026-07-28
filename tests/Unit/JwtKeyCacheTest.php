<?php

namespace Tests\Unit;

use App\Services\JwtKeyCacheService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

class JwtKeyCacheTest extends TestCase
{
    public function test_key_cache_returns_public_key(): void
    {
        $service = new JwtKeyCacheService;
        $key = $service->getPublicKey('default');

        $this->assertNotEmpty($key);
        $this->assertEquals(config('gateway.auth.secret'), $key);
    }

    public function test_jwt_encode_and_decode_with_cached_key(): void
    {
        $service = new JwtKeyCacheService;
        $secret = $service->getPublicKey('default');

        $payload = [
            'sub' => '12345',
            'name' => 'John Doe',
            'exp' => time() + 3600,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));

        $this->assertEquals('12345', $decoded->sub);
        $this->assertEquals('John Doe', $decoded->name);
    }
}
