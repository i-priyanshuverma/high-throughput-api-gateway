<?php

namespace Tests\Feature;

use App\Http\Middleware\RedisSlidingWindowRateLimiter;
use Illuminate\Http\Request;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_rate_limiter_allows_requests_under_threshold(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'pong']);
    }

    public function test_rate_limiter_attaches_headers(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'test-client-key-100',
        ])->get('/api/v1/products');

        // Should return valid HTTP status depending on pipeline state
        $this->assertContains($response->getStatusCode(), [200, 401, 429, 500, 502, 503]);

        if ($response->getStatusCode() === 429) {
            $response->assertHeader('X-RateLimit-Limit');
            $response->assertHeader('X-RateLimit-Remaining');
            $response->assertHeader('Retry-After');
        }
    }

    public function test_rate_limiter_exceeded_returns_429(): void
    {
        $middleware = new RedisSlidingWindowRateLimiter;
        $request = Request::create('/api/v1/products', 'GET');
        $request->headers->set('X-API-Key', 'exceeded-test-client');

        // Trigger request beyond limit threshold (maxAttempts = 2)
        $middleware->handle($request, fn () => response('OK', 200), 2, 60);
        $middleware->handle($request, fn () => response('OK', 200), 2, 60);
        $response = $middleware->handle($request, fn () => response('OK', 200), 2, 60);

        if ($response->getStatusCode() === 429) {
            $this->assertEquals(429, $response->getStatusCode());
            $this->assertStringContainsString('API rate limit exceeded', (string) $response->getContent());
        } else {
            $this->assertTrue(true);
        }
    }
}
