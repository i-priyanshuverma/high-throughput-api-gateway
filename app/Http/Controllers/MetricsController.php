<?php

namespace App\Http\Controllers;

use App\Services\MetricsCollector;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    protected MetricsCollector $metricsCollector;

    public function __construct(MetricsCollector $metricsCollector)
    {
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Expose Prometheus metrics for scraping.
     */
    public function __invoke(): Response
    {
        $content = $this->metricsCollector->renderPrometheusMetrics();

        return response($content, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
