<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that the health endpoint returns a successful response.
     */
    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'ok',
                     'service' => 'MP-Software Backend',
                     'version' => '1.0.0'
                 ]);
    }

    /**
     * Test that the API health endpoint returns a successful response.
     */
    public function test_api_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'ok',
                     'service' => 'MP-Software API',
                     'version' => 'v1'
                 ]);
    }
}
