<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    public const STATE_CLOSED = 'CLOSED';
    public const STATE_OPEN = 'OPEN';
    public const STATE_HALF_OPEN = 'HALF_OPEN';

    protected int $failureThreshold;
    protected int $resetTimeout;
    protected int $halfOpenSuccessThreshold;

    public function __construct()
    {
        $this->failureThreshold = (int) config('gateway.circuit_breaker.failure_threshold', 5);
        $this->resetTimeout = (int) config('gateway.circuit_breaker.reset_timeout', 30);
        $this->halfOpenSuccessThreshold = (int) config('gateway.circuit_breaker.half_open_success_threshold', 3);
    }

    /**
     * Determine whether the circuit is available for forwarding traffic.
     */
    public function isAvailable(string $service): bool
    {
        $state = $this->getState($service);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $openedAt = (float) $this->getRedisValue("circuit:{$service}:opened_at", 0);
            if ((microtime(true) - $openedAt) >= $this->resetTimeout) {
                $this->transitionToHalfOpen($service);
                return true;
            }
            return false;
        }

        // In HALF_OPEN state, allow limited trial probe requests
        return true;
    }

    /**
     * Record a successful downstream call and handle recovery transitions.
     */
    public function recordSuccess(string $service): void
    {
        $state = $this->getState($service);

        if ($state === self::STATE_HALF_OPEN) {
            $successes = (int) $this->getRedisValue("circuit:{$service}:half_open_successes", 0) + 1;
            $this->setRedisValue("circuit:{$service}:half_open_successes", $successes, 60);

            if ($successes >= $this->halfOpenSuccessThreshold) {
                $this->resetCircuit($service);
            }
        } elseif ($state === self::STATE_OPEN) {
            $this->resetCircuit($service);
        } else {
            $this->delRedisKey("circuit:{$service}:failures");
        }
    }

    /**
     * Record a downstream failure. If in HALF_OPEN, immediately trip back to OPEN.
     */
    public function recordFailure(string $service): void
    {
        $state = $this->getState($service);

        if ($state === self::STATE_HALF_OPEN) {
            // Immediate re-opening upon single failure in probe mode
            $this->openCircuit($service);
            return;
        }

        $failures = (int) $this->getRedisValue("circuit:{$service}:failures", 0) + 1;
        $this->setRedisValue("circuit:{$service}:failures", $failures, 60);

        if ($failures >= $this->failureThreshold) {
            $this->openCircuit($service);
        }
    }

    /**
     * Transition state to HALF_OPEN to probe downstream health.
     */
    public function transitionToHalfOpen(string $service): void
    {
        $this->setState($service, self::STATE_HALF_OPEN);
        $this->setRedisValue("circuit:{$service}:half_open_successes", 0, 60);
    }

    /**
     * Transition state to OPEN.
     */
    public function openCircuit(string $service): void
    {
        $this->setState($service, self::STATE_OPEN);
        $this->setRedisValue("circuit:{$service}:opened_at", microtime(true), $this->resetTimeout * 2);
        $this->delRedisKey("circuit:{$service}:half_open_successes");
    }

    /**
     * Reset circuit to CLOSED state.
     */
    public function resetCircuit(string $service): void
    {
        $this->setState($service, self::STATE_CLOSED);
        $this->delRedisKey("circuit:{$service}:failures");
        $this->delRedisKey("circuit:{$service}:opened_at");
        $this->delRedisKey("circuit:{$service}:half_open_successes");
    }

    /**
     * Get current state of service circuit.
     */
    public function getState(string $service): string
    {
        return $this->getRedisValue("circuit:{$service}:state", self::STATE_CLOSED);
    }

    protected function setState(string $service, string $state): void
    {
        $this->setRedisValue("circuit:{$service}:state", $state, 3600);
    }

    protected function getRedisValue(string $key, mixed $default = null): mixed
    {
        try {
            $val = Redis::get($key);
            return $val !== null ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    protected function setRedisValue(string $key, mixed $value, int $ttl = 3600): void
    {
        try {
            Redis::setex($key, $ttl, (string) $value);
        } catch (\Throwable $e) {
            // Ignore if Redis offline
        }
    }

    protected function delRedisKey(string $key): void
    {
        try {
            Redis::del($key);
        } catch (\Throwable $e) {
            // Ignore if Redis offline
        }
    }
}
