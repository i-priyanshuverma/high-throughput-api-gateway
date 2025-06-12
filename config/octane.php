<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    */

    'server' => env('OCTANE_SERVER', 'swoole'),

    'https' => env('OCTANE_HTTPS', false),

    'listeners' => [
        'swoole' => [
            \Laravel\Octane\Events\WorkerStarting::class => [],
            \Laravel\Octane\Events\RequestReceived::class => [
                \Laravel\Octane\Listeners\CreateDatabaseRelays::class,
            ],
            \Laravel\Octane\Events\RequestTerminated::class => [],
            \Laravel\Octane\Events\TaskReceived::class => [],
            \Laravel\Octane\Events\TickReceived::class => [],
            \Laravel\Octane\Events\WorkerErrorOccurred::class => [],
            \Laravel\Octane\Events\WorkerStopping::class => [],
        ],
    ],

    'warm' => [
        ... Laravel\Octane\Octane::defaultServicesToWarm(),
    ],

    'flush' => [],

    'swoole' => [
        'options' => [
            'worker_num' => env('OCTANE_WORKERS', swoole_cpu_num() * 4),
            'task_worker_num' => env('OCTANE_TASK_WORKERS', swoole_cpu_num() * 4),
            'max_request' => 25000,
            'task_max_request' => 25000,
            'open_tcp_nodelay' => true,
            'enable_reuse_port' => true,
            'max_coroutine' => 500000,
            'socket_buffer_size' => 128 * 1024 * 1024, // 128MB buffer
            'buffer_output_size' => 64 * 1024 * 1024,
        ],
    ],

];
