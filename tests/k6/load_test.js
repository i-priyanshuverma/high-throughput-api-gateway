import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 500 },  // Ramp up to 500 virtual users
        { duration: '1m', target: 2000 },  // Spike load to 2000 virtual users
        { duration: '30s', target: 0 },    // Ramp down to 0
    ],
    thresholds: {
        http_req_duration: ['p(95)<50', 'p(99)<100'], // 95% of requests must complete under 50ms
        http_req_failed: ['rate<0.01'],              // Less than 1% failure rate
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://localhost:8000';

export default function () {
    const params = {
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': `k6-vu-${__VU}`,
        },
    };

    const res = http.get(`${BASE_URL}/api/v1/products`, params);

    check(res, {
        'status is 200 or 429 or 502': (r) => [200, 429, 502].includes(r.status),
        'transaction response time < 50ms': (r) => r.timings.duration < 50,
    });

    sleep(0.01);
}
