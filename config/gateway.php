<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Downstream Microservices Mapping
    |--------------------------------------------------------------------------
    |
    | Maps API route prefixes to downstream target URLs.
    |
    */

    'services' => [
        'users' => [
            'prefix' => 'api/v1/users',
            'base_url' => env('SERVICE_USERS_URL', 'http://127.0.0.1:8001'),
            'timeout' => 5.0,
            'retry' => 2,
        ],
        'orders' => [
            'prefix' => 'api/v1/orders',
            'base_url' => env('SERVICE_ORDERS_URL', 'http://127.0.0.1:8002'),
            'timeout' => 5.0,
            'retry' => 2,
        ],
        'products' => [
            'prefix' => 'api/v1/products',
            'base_url' => env('SERVICE_PRODUCTS_URL', 'http://127.0.0.1:8003'),
            'timeout' => 3.0,
            'retry' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Sliding Window Rate Limiting Options
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'max_attempts' => (int) env('RATE_LIMIT_REQUESTS', 100),
        'decay_seconds' => (int) env('RATE_LIMIT_WINDOW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Options
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'reset_timeout' => (int) env('CIRCUIT_BREAKER_RESET_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 / JWT Auth Options
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'secret' => env('GATEWAY_AUTH_SECRET', 'super-secret-jwt-signing-key-for-api-gateway-auth-2025'),
        'algorithm' => 'HS256',
    ],

];
