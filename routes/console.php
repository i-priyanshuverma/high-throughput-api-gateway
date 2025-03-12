<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('gateway:status', function () {
    $this->info('API Gateway status: Operational');
})->purpose('Display API Gateway operational status');
