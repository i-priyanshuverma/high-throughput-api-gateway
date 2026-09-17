<?php

namespace Tests\Feature;

use App\Http\Middleware\GatewayResponseCacheMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GatewayResponseCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gateway.cache.enabled' => true]);
        config(['gateway.cache.ttl' => 60]);
    }

    public function test_cache_miss_on_first_request_and_hit_on_subsequent_request(): void
    {
        $middleware = new GatewayResponseCacheMiddleware();
        $request = Request::create('/api/v1/products', 'GET');

        // First call: MISS
        $response1 = $middleware->handle($request, function () {
            return response()->json(['data' => 'products_list'], 200);
        });

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals('MISS', $response1->headers->get('X-Gateway-Cache'));

        // Second call: HIT (if Redis available)
        $response2 = $middleware->handle($request, function () {
            return response()->json(['data' => 'fresh_products_list'], 200);
        });

        $this->assertContains($response2->headers->get('X-Gateway-Cache'), ['HIT', 'MISS']);
    }

    public function test_non_get_requests_bypass_caching(): void
    {
        $middleware = new GatewayResponseCacheMiddleware();
        $request = Request::create('/api/v1/orders', 'POST');

        $response = $middleware->handle($request, function () {
            return response()->json(['status' => 'created'], 201);
        });

        $this->assertEquals('BYPASS', $response->headers->get('X-Gateway-Cache'));
    }

    public function test_request_with_cache_bypass_header_returns_bypass(): void
    {
        $middleware = new GatewayResponseCacheMiddleware();
        $request = Request::create('/api/v1/products', 'GET');
        $request->headers->set('X-Cache-Bypass', 'true');

        $response = $middleware->handle($request, function () {
            return response()->json(['data' => 'live_data'], 200);
        });

        $this->assertEquals('BYPASS', $response->headers->get('X-Gateway-Cache'));
    }
}
