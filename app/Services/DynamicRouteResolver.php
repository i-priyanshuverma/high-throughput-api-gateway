<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class DynamicRouteResolver
{
    protected string $configPath;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $routes = [];

    public function __construct()
    {
        $this->configPath = config_path('routes.json');
        $this->loadRoutes();
    }

    /**
     * Load routes from JSON file with fallback to static array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadRoutes(): array
    {
        if (File::exists($this->configPath)) {
            $json = File::get($this->configPath);
            $decoded = json_decode($json, true);
            if (isset($decoded['services']) && is_array($decoded['services'])) {
                /** @var array<int, array<string, mixed>> $services */
                $services = $decoded['services'];
                $this->routes = $services;

                return $this->routes;
            }
        }

        $this->routes = [
            ['name' => 'users', 'prefix' => 'api/v1/users', 'target' => (string) env('SERVICE_USERS_URL', 'http://127.0.0.1:8001'), 'auth_required' => true],
            ['name' => 'orders', 'prefix' => 'api/v1/orders', 'target' => (string) env('SERVICE_ORDERS_URL', 'http://127.0.0.1:8002'), 'auth_required' => true],
            ['name' => 'products', 'prefix' => 'api/v1/products', 'target' => (string) env('SERVICE_PRODUCTS_URL', 'http://127.0.0.1:8003'), 'auth_required' => false],
        ];

        return $this->routes;
    }

    /**
     * Find matching service for URI path.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $path): ?array
    {
        $cleanPath = ltrim($path, '/');
        foreach ($this->routes as $route) {
            $prefix = is_string($route['prefix'] ?? null) ? $route['prefix'] : '';
            if (str_starts_with($cleanPath, ltrim($prefix, '/'))) {
                return $route;
            }
        }

        return null;
    }
}
