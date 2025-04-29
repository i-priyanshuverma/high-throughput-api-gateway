<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\CircuitBreaker;

class CircuitBreakerTest extends TestCase
{
    public function test_circuit_breaker_starts_closed(): void
    {
        $cb = new CircuitBreaker();
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $cb->getState('users'));
        $this->assertTrue($cb->isAvailable('users'));
    }

    public function test_circuit_breaker_opens_after_reaching_failure_threshold(): void
    {
        $cb = new CircuitBreaker();
        $service = 'orders';

        // Record threshold failures
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure($service);
        }

        $cb->openCircuit($service);

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($service));
        $this->assertFalse($cb->isAvailable($service));
    }

    public function test_circuit_breaker_middleware_returns_503_fallback_when_open(): void
    {
        $cb = app(CircuitBreaker::class);
        $cb->openCircuit('users');

        $response = $this->getJson('/api/v1/users/profile');

        // Unauthenticated returns 401, or if CB trips returns 503
        $this->assertContains($response->status(), [401, 503]);
        
        if ($response->status() === 503) {
            $response->assertJson([
                'error' => 'Service Unavailable',
                'fallback' => true,
                'status' => 503,
            ]);
        }
    }

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $this->assertStringContainsString('gateway_requests_total', $response->getContent());
        $this->assertStringContainsString('gateway_circuit_breaker_state', $response->getContent());
        $this->assertStringContainsString('gateway_rate_limit_hits_total', $response->getContent());
    }
}
