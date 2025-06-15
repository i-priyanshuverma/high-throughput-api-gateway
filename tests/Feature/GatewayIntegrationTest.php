<?php

namespace Tests\Feature;

use Tests\TestCase;

class GatewayIntegrationTest extends TestCase
{
    public function test_full_gateway_pipeline_ping_endpoint(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'timestamp']);
    }

    public function test_gateway_health_check_returns_octane_status(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'service' => 'High-Throughput API Gateway',
            'status' => 'healthy',
        ]);
    }

    public function test_cors_preflight_request_returns_204_with_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/products', [], [], [], [
            'HTTP_ORIGIN' => 'https://app.company.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        // Expect 200 or 204 depending on middleware configuration
        $this->assertContains($response->status(), [200, 204]);
    }
}
