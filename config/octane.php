<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | Options: "swoole", "roadrunner", "frankenphp"
    |
    */

    'server' => env('OCTANE_SERVER', 'swoole'),

    'https' => env('OCTANE_HTTPS', false),

    'listeners' => [
        'swoole' => [
            \Laravel\Octane\Events\WorkerStarting::class => [
                // Octane worker startup listeners
            ],
            \Laravel\Octane\Events\RequestReceived::class => [
                \Laravel\Octane\Listeners\CreateDatabaseRelays::class,
            ],
            \Laravel\Octane\Events\RequestTerminated::class => [
                // Flush request state
            ],
            \Laravel\Octane\Events\TaskReceived::class => [],
            \Laravel\Octane\Events\TickReceived::class => [],
            \Laravel\Octane\Events\WorkerErrorOccurred::class => [],
            \Laravel\Octane\Events\WorkerStopping::class => [],
        ],
    ],

    'warm' => [
        ... Laravel\Octane\Octane::defaultServicesToWarm(),
    ],

    'flush' => [
        // Services to reset/flush per request
    ],

    'swoole' => [
        'options' => [
            'worker_num' => swoole_cpu_num() * 2,
            'task_worker_num' => swoole_cpu_num() * 2,
            'max_request' => 10000,
            'task_max_request' => 10000,
            'open_tcp_nodelay' => true,
            'enable_reuse_port' => true,
            'max_coroutine' => 100000,
        ],
    ],

];
