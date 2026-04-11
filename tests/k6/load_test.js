import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 500 },   // Warm up: 500 virtual users
        { duration: '1m', target: 2000 },   // Steady high load: 2000 VUs
        { duration: '30s', target: 5000 },  // Peak traffic spike scenario: 5000 VUs
        { duration: '1m', target: 2000 },   // Sustained load recovery: 2000 VUs
        { duration: '30s', target: 0 },     // Ramp down: 0 VUs
    ],
    thresholds: {
        http_req_duration: ['p(95)<50', 'p(99)<120'], // 95% under 50ms, 99% under 120ms
        http_req_failed: ['rate<0.01'],              // Under 1% failure rate
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://localhost:8000';

export default function () {
    const params = {
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': `k6-vu-${__VU}`,
            'X-Request-ID': `req-k6-${__VU}-${Date.now()}`,
        },
    };

    // Test proxy route
    const resProduct = http.get(`${BASE_URL}/v1/products`, params);

    check(resProduct, {
        'product endpoint status is valid': (r) => [200, 429, 503].includes(r.status),
        'transaction response time < 50ms': (r) => r.timings.duration < 50,
    });

    // Test healthz readiness route
    const resHealth = http.get(`${BASE_URL}/healthz`);
    check(resHealth, {
        'healthz status is 200': (r) => r.status === 200,
    });

    sleep(0.01);
}

