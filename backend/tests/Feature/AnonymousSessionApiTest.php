<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AnonymousSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_a_cryptographically_random_opaque_token(): void
    {
        $first = $this->postJson('/api/v1/anonymous-session')
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']])
            ->json('data.token');
        $second = $this->postJson('/api/v1/anonymous-session')->json('data.token');

        $this->assertMatchesRegularExpression('/^ar1_[A-Za-z0-9_-]{43}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_it_reuses_a_valid_token(): void
    {
        $token = $this->postJson('/api/v1/anonymous-session')->json('data.token');

        $this->postJson('/api/v1/anonymous-session', [], ['X-Anonymous-Reporter' => $token])
            ->assertOk()
            ->assertJsonPath('data.token', $token);
    }

    public function test_it_replaces_a_malformed_token_without_persisting_it(): void
    {
        $token = $this->postJson(
            '/api/v1/anonymous-session',
            [],
            ['X-Anonymous-Reporter' => 'device-fingerprint-123'],
        )->assertOk()->json('data.token');

        $this->assertNotSame('device-fingerprint-123', $token);
        $this->assertDatabaseCount('anonymous_reporters', 0);
    }

    public function test_session_issuance_is_rate_limited_with_a_consistent_error(): void
    {
        config(['reporting.session_rate_limit_per_minute' => 1]);
        $key = hash_hmac('sha256', '127.0.0.1', (string) config('app.key'));
        RateLimiter::clear('anonymous-session:'.$key);

        $this->postJson('/api/v1/anonymous-session')->assertOk();
        $this->postJson('/api/v1/anonymous-session')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
    }
}
