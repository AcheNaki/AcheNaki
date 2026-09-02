<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_local_frontend_preflight_allows_the_anonymous_reporter_header(): void
    {
        $this->call('OPTIONS', '/api/v1/utility-reports', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-anonymous-reporter',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Headers', 'accept, content-type, x-anonymous-reporter');
    }

    public function test_unlisted_origins_do_not_receive_cors_permission(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/utility-reports', server: [
            'HTTP_ORIGIN' => 'https://unlisted.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame(
            'http://localhost:3000',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
        $this->assertNotSame(
            'https://unlisted.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
