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

    public function __construct()
    {
        $this->failureThreshold = (int) config('gateway.circuit_breaker.failure_threshold', 5);
        $this->resetTimeout = (int) config('gateway.circuit_breaker.reset_timeout', 30);
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
                $this->setState($service, self::STATE_HALF_OPEN);
                return true;
            }
            return false;
        }

        // HALF_OPEN state allows trial requests
        return true;
    }

    /**
     * Record a successful downstream call.
     */
    public function recordSuccess(string $service): void
    {
        $state = $this->getState($service);

        if ($state === self::STATE_HALF_OPEN || $state === self::STATE_OPEN) {
            $this->resetCircuit($service);
        } else {
            $this->delRedisKey("circuit:{$service}:failures");
        }
    }

    /**
     * Record a downstream failure.
     */
    public function recordFailure(string $service): void
    {
        $failures = (int) $this->getRedisValue("circuit:{$service}:failures", 0) + 1;
        $this->setRedisValue("circuit:{$service}:failures", $failures, 60);

        if ($failures >= $this->failureThreshold || $this->getState($service) === self::STATE_HALF_OPEN) {
            $this->openCircuit($service);
        }
    }

    /**
     * Transition state to OPEN.
     */
    public function openCircuit(string $service): void
    {
        $this->setState($service, self::STATE_OPEN);
        $this->setRedisValue("circuit:{$service}:opened_at", microtime(true), $this->resetTimeout * 2);
    }

    /**
     * Reset circuit to CLOSED state.
     */
    public function resetCircuit(string $service): void
    {
        $this->setState($service, self::STATE_CLOSED);
        $this->delRedisKey("circuit:{$service}:failures");
        $this->delRedisKey("circuit:{$service}:opened_at");
    }

    /**
     * Get current state of service circuit.
     */
    public function getState(string $service): string
    {
        return $this->getRedisValue("circuit:{$service}:state", self::STATE_CLOSED);
    }

    /**
     * Helper to set state.
     */
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
