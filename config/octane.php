<?php

use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Listeners\CreateDatabaseRelays;
use Laravel\Octane\Listeners\EnsureDirectivesAreCleaned;
use Laravel\Octane\Listeners\FlushTemporaryContainerInstances;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\Octane;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    */

    'server' => env('OCTANE_SERVER', 'swoole'),

    'https' => env('OCTANE_HTTPS', false),

    'listeners' => [
        WorkerStarting::class => [
            EnsureDirectivesAreCleaned::class,
        ],

        RequestReceived::class => [
            CreateDatabaseRelays::class,
        ],

        RequestHandled::class => [],

        RequestTerminated::class => [
            FlushTemporaryContainerInstances::class,
        ],

        TaskReceived::class => [],

        TickReceived::class => [],

        WorkerErrorOccurred::class => [
            ReportException::class,
        ],

        WorkerStopping::class => [],
    ],

    'warm' => [
        ...Octane::defaultServicesToWarm(),
    ],

    'flush' => [],

    'swoole' => [
        'options' => [
            'worker_num' => env('OCTANE_WORKERS', function_exists('swoole_cpu_num') ? swoole_cpu_num() * 4 : 8),
            'task_worker_num' => env('OCTANE_TASK_WORKERS', function_exists('swoole_cpu_num') ? swoole_cpu_num() * 4 : 8),
            'max_request' => (int) env('OCTANE_MAX_REQUESTS', 50000),
            'task_max_request' => (int) env('OCTANE_TASK_MAX_REQUESTS', 50000),
            'max_wait_time' => 30,
            'memory_limit' => env('OCTANE_MEMORY_LIMIT', '512M'),
            'open_tcp_nodelay' => true,
            'enable_reuse_port' => true,
            'max_coroutine' => 500000,
            'socket_buffer_size' => 128 * 1024 * 1024,
            'buffer_output_size' => 64 * 1024 * 1024,
            'package_max_length' => 128 * 1024 * 1024,
        ],
    ],

];
