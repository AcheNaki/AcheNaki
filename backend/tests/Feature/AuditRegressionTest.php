<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use App\Models\Area;
use App\Models\ElectricityOutageEvent;
use App\Models\SubArea;
use App\Services\LiveStatus\LiveStatusProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Regression coverage for defects found during the pre-deployment engineering review.
 * Each case failed against the implementation as it stood before the accompanying fix.
 */
class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::query()->create([
            'name' => 'Dhanmondi',
            'slug' => 'dhanmondi',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id,
            'name' => 'Dhanmondi 15',
            'slug' => 'dhanmondi-15',
            'is_active' => true,
        ]);
    }

    /**
     * PostgreSQL `LIKE` is case-sensitive while SQLite's is not, so a plain `like`
     * comparison silently stopped matching lowercase input on the production driver.
     */
    public function test_location_search_is_case_insensitive_on_every_supported_driver(): void
    {
        foreach (['dhanmondi', 'DHANMONDI', 'Dhanmondi', 'dHaNmOnDi'] as $query) {
            $response = $this->getJson('/api/v1/locations/search?q='.$query)->assertOk();

            $this->assertSame(
                ['Dhanmondi', 'Dhanmondi 15'],
                collect($response->json('data'))->pluck('name')->all(),
                "Searching for \"{$query}\" must find the canonical locality.",
            );
        }
    }

    /** A typed LIKE wildcard must not silently match the entire canonical taxonomy. */
    public function test_location_search_does_not_treat_user_input_as_a_like_wildcard(): void
    {
        $this->getJson('/api/v1/locations/search?q=%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/locations/search?q=Dh_nmondi')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Carbon 3 returns a signed difference, so calling it on the later instant produced a
     * negative duration for every publicly listed restored outage.
     */
    public function test_recently_resolved_events_expose_a_positive_integer_duration(): void
    {
        $startedAt = CarbonImmutable::now('UTC')->subHours(3);
        ElectricityOutageEvent::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'lifecycle' => ElectricityOutageLifecycle::RESOLVED,
            'started_at' => $startedAt,
            'first_supported_at' => $startedAt,
            'confirmed_at' => $startedAt->addMinutes(3),
            'ended_at' => $startedAt->addHours(2),
            'start_confidence_level' => ConfidenceLevel::MEDIUM,
            'end_confidence_level' => ConfidenceLevel::MEDIUM,
            'inference_version' => 1,
        ]);

        $duration = $this->getJson('/api/v1/electricity-events/recently-resolved')
            ->assertOk()
            ->json('data.0.duration_seconds');

        $this->assertSame(7200, $duration);
    }

    /**
     * PHP records scalar call arguments in exception stack traces. The raw browser token
     * must therefore never sit on the stack while a downstream failure is logged.
     */
    public function test_projection_failure_log_never_contains_the_raw_reporter_token(): void
    {
        $this->mock(LiveStatusProjectionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshByIds')
                ->once()
                ->andThrow(new RuntimeException('Projection unavailable'));
        });

        $captured = [];
        Log::listen(function ($message) use (&$captured): void {
            $captured[] = $message->message.' '.json_encode(array_map(
                fn ($value) => $value instanceof Throwable ? $value->getTraceAsString() : $value,
                $message->context,
            ));
        });

        $token = 'ar1_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->postReport($token)->assertCreated();

        $this->assertNotEmpty($captured, 'The projection failure should still be logged.');
        $log = implode("\n", $captured);
        $this->assertStringNotContainsString($token, $log);
        // PHP truncates trace arguments, so assert the distinctive prefix is absent too.
        $this->assertStringNotContainsString(substr($token, 0, 12), $log);
        $this->assertDatabaseCount('utility_reports', 1);
    }

    /**
     * Keying the report limiter on any non-empty header let a caller mint a fresh bucket
     * per request simply by rotating malformed tokens.
     */
    public function test_rotating_malformed_tokens_cannot_mint_unlimited_report_attempts(): void
    {
        $limit = (int) config('reporting.report_rate_limit_per_minute');
        $lastStatus = null;

        for ($attempt = 0; $attempt < $limit + 3; $attempt++) {
            $lastStatus = $this->postReport('not-a-valid-token-'.$attempt)->getStatusCode();
        }

        $this->assertSame(429, $lastStatus, 'Malformed tokens must share the network-derived bucket.');
    }

    /** A well-formed token keeps its own bucket, so honest reporters are unaffected. */
    public function test_valid_tokens_are_limited_independently_of_other_reporters(): void
    {
        $limit = (int) config('reporting.report_rate_limit_per_minute');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postReport($this->validToken(), 'UNAVAILABLE')->assertCreated();
        }

        $this->postReport($this->validToken(), 'UNAVAILABLE')->assertCreated();
    }

    private function validToken(): string
    {
        return 'ar1_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function postReport(string $token, string $status = 'UNAVAILABLE'): TestResponse
    {
        return $this->postJson('/api/v1/utility-reports', [
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'ELECTRICITY',
            'status' => $status,
            'time_bucket' => 'NOW',
        ], ['X-Anonymous-Reporter' => $token]);
    }
}
