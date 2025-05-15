# High-Throughput API Gateway (PHP 8.3 / Laravel 11 / Octane / K8s)

[![PHP Version](https://img.shields.io/badge/PHP-8.3-777BB4.svg?style=flat&logo=php)](https://www.php.net/)
[![Laravel Framework](https://img.shields.io/badge/Laravel-11.0-FF2D20.svg?style=flat&logo=laravel)](https://laravel.com)
[![Laravel Octane](https://img.shields.io/badge/Laravel_Octane-Swoole-4053D6.svg?style=flat&logo=laravel)](https://laravel.com/docs/octane)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D.svg?style=flat&logo=redis)](https://redis.io)
[![Kubernetes](https://img.shields.io/badge/Kubernetes-Helm_3-326CE5.svg?style=flat&logo=kubernetes)](https://kubernetes.io)

High-performance, enterprise-grade API Gateway engineered with **PHP 8.3**, **Laravel 11**, and **Laravel Octane (Swoole driver)**. Designed to serve tens of thousands of requests per second with low latency, robust sliding-window rate limiting, circuit breaker fault tolerance, OAuth 2.0 / JWT security, and native Prometheus monitoring.

---

## 🏛️ System Architecture

```
                                  +---------------------------------------+
                                  |            HTTP Clients /             |
                                  |         Mobile Apps / Web UI          |
                                  +---------------------------------------+
                                                      |
                                                      v
                                        +---------------------------+
                                        |    Kubernetes Ingress     |
                                        +---------------------------+
                                                      |
                                                      v
  +-------------------------------------------------------------------------------------------------------+
  |                                     HIGH-THROUGHPUT API GATEWAY                                       |
  |                                    (Laravel 11 + Swoole Octane)                                       |
  |                                                                                                       |
  |   +-------------------+    +----------------------+    +-----------------------+    +-------------+   |
  |   | JwtOAuthValidation| -> | RedisSlidingWindow   | -> | CircuitBreaker        | -> | Gateway     |   |
  |   | Middleware        |    | RateLimiter          |    | Middleware            |    | Proxy       |   |
  |   +-------------------+    +----------------------+    +-----------------------+    +-------------+   |
  +---------------------------------------|----------------------------|-----------------------|----------+
                                          |                            |                       |
                                          v                            v                       |
                               +--------------------+       +---------------------+            |
                               |    Redis Cluster   |       |  Prometheus Scraper |            |
                               | (Sliding Window &  |       |     (/metrics)      |            |
                               |  Circuit Breaker)  |       +---------------------+            |
                               +--------------------+                                          |
                                                                                               v
                                              +------------------------------------------------+
                                              |                |               |
                                              v                v               v
                                       +------------+   +------------+   +------------+
                                       |  User Service  |   |Order Service|   |Product Service|
                                       |  (:8001)   |   |  (:8002)   |   |  (:8003)   |
                                       +------------+   +------------+   +------------+
```

---

## ✨ Key Technical Features

1. **Laravel Octane & Swoole Engine**: High-throughput non-blocking I/O execution environment keeping application state warm in RAM across worker threads.
2. **Redis Sliding-Window Rate Limiter**: High-precision atomic sliding-window algorithm backed by Redis Sorted Sets (`ZSET`), protecting microservices from DDoS and brute force spikes.
3. **Circuit Breaker Pattern**: Automatic fault detection with `CLOSED`, `OPEN`, and `HALF_OPEN` state transitions. Immediately returns structured `503 Service Unavailable` JSON fallbacks when downstream services drop.
4. **OAuth 2.0 & JWT Security**: High-speed token signature verification, payload claims checking, and scope validation before passing requests downstream.
5. **Observability & Prometheus Integration**: Exposes standard `/metrics` endpoint yielding Prometheus gauges and counters (`gateway_requests_total`, `gateway_rate_limit_hits_total`, `gateway_circuit_breaker_state`).
6. **Kubernetes Helm Charts**: Production-ready chart with Horizontal Pod Autoscaler (HPA), Liveness/Readiness probes, ConfigMaps, and Secrets.

---

## 🚀 Local Quick-Start Guide

### Prerequisites
- Docker & Docker Compose
- PHP 8.3 & Composer (for local development)
- Redis 7.x

### 1. Spin Up Container Stack
```bash
git clone https://github.com/company/high-throughput-api-gateway.git
cd high-throughput-api-gateway

# Spin up Gateway, Redis, and Mock Microservices
docker-compose up --build -d
```

### 2. Verify Health & Metrics
```bash
# Gateway status
curl http://localhost:8000/health

# Prometheus metrics
curl http://localhost:8000/metrics
```

### 3. Test Proxy Downstream Routing
```bash
# Public Products Proxy
curl http://localhost:8000/api/v1/products

# Rate-limited endpoint check
curl -i -H "X-API-Key: demo-client-key" http://localhost:8000/api/v1/products
```

---

## 🧪 PHPUnit Test Suite

Run the comprehensive feature test suite:
```bash
# Run tests inside local environment
composer test

# Or via artisan
php artisan test
```

Test coverage includes:
- `tests/Feature/RateLimitingTest.php`: Sliding window rate limit enforcement, header validation, 429 status response.
- `tests/Feature/CircuitBreakerTest.php`: Closed/Open state transitions, 503 fallback JSON verification, Prometheus metrics formatting.

---

## ☸️ Kubernetes Helm Deployment

Deploy to Kubernetes cluster using the provided Helm chart:

```bash
# Dry-run template generation
helm template api-gateway ./helm/api-gateway

# Install / Upgrade in Kubernetes
helm upgrade --install api-gateway ./helm/api-gateway \
  --namespace gateway \
  --create-namespace \
  --set replicaCount=5
```

---

## 📄 License
This repository is open-sourced software licensed under the [MIT license](LICENSE).
