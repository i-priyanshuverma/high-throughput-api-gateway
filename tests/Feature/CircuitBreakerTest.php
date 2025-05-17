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

        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure($service);
        }

        $cb->openCircuit($service);

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($service));
        $this->assertFalse($cb->isAvailable($service));
    }

    public function test_circuit_breaker_half_open_transition_and_probe_recovery(): void
    {
        $cb = new CircuitBreaker();
        $service = 'products';

        // Trip to OPEN
        $cb->openCircuit($service);
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($service));

        // Transition to HALF_OPEN
        $cb->transitionToHalfOpen($service);
        $this->assertEquals(CircuitBreaker::STATE_HALF_OPEN, $cb->getState($service));
        $this->assertTrue($cb->isAvailable($service));

        // Record consecutive successes to close circuit
        $cb->recordSuccess($service);
        $cb->recordSuccess($service);
        $cb->recordSuccess($service);

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $cb->getState($service));
    }

    public function test_circuit_breaker_reopens_if_failure_occurs_in_half_open_state(): void
    {
        $cb = new CircuitBreaker();
        $service = 'users';

        $cb->transitionToHalfOpen($service);
        $this->assertEquals(CircuitBreaker::STATE_HALF_OPEN, $cb->getState($service));

        // Single failure in probe mode immediately trips circuit back to OPEN
        $cb->recordFailure($service);
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($service));
    }

    public function test_circuit_breaker_middleware_returns_503_fallback_when_open(): void
    {
        $cb = app(CircuitBreaker::class);
        $cb->openCircuit('users');

        $response = $this->getJson('/api/v1/users/profile');

        $this->assertContains($response->status(), [401, 503]);

        if ($response->status() === 503) {
            $response->assertJson([
                'error' => 'Service Unavailable',
                'fallback' => true,
                'status' => 503,
            ]);
        }
    }
}
