<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Models\Area;
use App\Models\SubArea;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UtilityReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::query()->create([
            'name' => 'Test Area',
            'slug' => 'test-area',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id,
            'name' => 'Test Sub-area',
            'slug' => 'test-sub-area',
            'is_active' => true,
        ]);
        $this->token = $this->newToken();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T09:30:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_valid_electricity_statuses_are_accepted(): void
    {
        foreach (['AVAILABLE', 'UNAVAILABLE', 'UNSTABLE'] as $status) {
            $this->postReport($this->electricityPayload($status), $this->newToken())
                ->assertCreated()
                ->assertJsonPath('data.utility_type', 'ELECTRICITY')
                ->assertJsonPath('data.status', $status)
                ->assertJsonPath('meta.duplicate', false);
        }

        $this->assertDatabaseCount('utility_reports', 3);
    }

    public function test_gas_statuses_and_optional_cookability_are_accepted(): void
    {
        $cases = [
            ['NORMAL', true],
            ['LOW', false],
            ['VERY_LOW', null],
            ['UNAVAILABLE', null],
        ];

        foreach ($cases as [$status, $canCook]) {
            $payload = $this->gasPayload($status);
            if ($canCook !== null) {
                $payload['can_cook'] = $canCook;
            }

            $response = $this->postReport($payload, $this->newToken())
                ->assertCreated()
                ->assertJsonPath('data.utility_type', 'GAS')
                ->assertJsonPath('data.status', $status);

            if ($canCook === null) {
                $response->assertJsonPath('data.can_cook', null);
            } else {
                $response->assertJsonPath('data.can_cook', $canCook);
            }
        }

        $this->assertDatabaseCount('utility_reports', 4);
    }

    public function test_normal_gas_with_can_cook_false_is_preserved_as_an_observation(): void
    {
        $this->postReport([...$this->gasPayload('NORMAL'), 'can_cook' => false])
            ->assertCreated()
            ->assertJsonPath('data.can_cook', false);
    }

    public function test_utility_specific_statuses_are_rejected(): void
    {
        $this->postReport($this->electricityPayload('VERY_LOW'))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors('status', 'error.details');

        $this->postReport($this->gasPayload('UNSTABLE'), $this->newToken())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status', 'error.details');

        $this->assertDatabaseCount('utility_reports', 0);
    }

    public function test_valid_active_parent_child_location_is_accepted(): void
    {
        $this->postReport($this->electricityPayload())
            ->assertCreated()
            ->assertJsonPath('data.area_id', $this->area->id)
            ->assertJsonPath('data.sub_area_id', $this->subArea->id);
    }

    public function test_mismatched_parent_is_rejected(): void
    {
        $other = $this->createArea('Other Area', 'other-area');

        $this->postReport([...$this->electricityPayload(), 'area_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sub_area_id', 'error.details');
    }

    public function test_inactive_area_and_inactive_sub_area_are_rejected(): void
    {
        $this->area->update(['is_active' => false]);
        $this->postReport($this->electricityPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area_id', 'error.details');

        $this->area->update(['is_active' => true]);
        $this->subArea->update(['is_active' => false]);
        $this->postReport($this->electricityPayload(), $this->newToken())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sub_area_id', 'error.details');
    }

    public function test_reported_at_is_server_authoritative_and_exact_buckets_are_resolved(): void
    {
        $expected = [
            'NOW' => '2026-09-01T09:30:00.000000Z',
            'MIN_5' => '2026-09-01T09:25:00.000000Z',
            'MIN_15' => '2026-09-01T09:15:00.000000Z',
            'MIN_30' => '2026-09-01T09:00:00.000000Z',
            'HOUR_1' => '2026-09-01T08:30:00.000000Z',
            'HOUR_2' => '2026-09-01T07:30:00.000000Z',
        ];

        foreach ($expected as $bucket => $estimated) {
            $this->postReport([
                ...$this->electricityPayload(),
                'time_bucket' => $bucket,
                'reported_at' => '2000-01-01T00:00:00Z',
            ], $this->newToken())
                ->assertCreated()
                ->assertJsonPath('data.reported_at', '2026-09-01T09:30:00.000000Z')
                ->assertJsonPath('data.estimated_started_at', $estimated);
        }
    }

    public function test_uncertain_time_buckets_do_not_fabricate_start_times(): void
    {
        foreach (['UNKNOWN', 'OVER_2_HOURS'] as $bucket) {
            $this->postReport([...$this->electricityPayload(), 'time_bucket' => $bucket], $this->newToken())
                ->assertCreated()
                ->assertJsonPath('data.time_bucket', $bucket)
                ->assertJsonPath('data.estimated_started_at', null);
        }
    }

    public function test_immediate_identical_duplicate_is_idempotent(): void
    {
        $firstId = $this->postReport($this->electricityPayload())
            ->assertCreated()
            ->json('data.id');

        $this->postReport($this->electricityPayload())
            ->assertOk()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('meta.duplicate', true);

        $this->assertDatabaseCount('utility_reports', 1);
    }

    public function test_state_change_different_sub_area_and_different_utility_are_not_duplicates(): void
    {
        $otherSubArea = SubArea::query()->create([
            'area_id' => $this->area->id,
            'name' => 'Other Sub-area',
            'slug' => 'other-sub-area',
            'is_active' => true,
        ]);

        $this->postReport($this->electricityPayload('UNAVAILABLE'))->assertCreated();
        $this->postReport($this->electricityPayload('AVAILABLE'))->assertCreated();
        $this->postReport([...$this->electricityPayload('UNAVAILABLE'), 'sub_area_id' => $otherSubArea->id])->assertCreated();
        $this->postReport($this->gasPayload('UNAVAILABLE'))->assertCreated();

        $this->assertDatabaseCount('utility_reports', 4);
    }

    public function test_same_observation_after_duplicate_window_is_accepted(): void
    {
        $this->postReport($this->electricityPayload())->assertCreated();
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(181));
        $this->postReport($this->electricityPayload())->assertCreated();

        $this->assertDatabaseCount('utility_reports', 2);
    }

    public function test_duplicate_window_includes_the_exact_180_second_boundary(): void
    {
        $this->postReport($this->electricityPayload())->assertCreated();

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(179));
        $this->postReport($this->electricityPayload())
            ->assertOk()
            ->assertJsonPath('meta.duplicate', true);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T09:33:00Z'));
        $this->postReport($this->electricityPayload())
            ->assertOk()
            ->assertJsonPath('meta.duplicate', true);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01T09:33:01Z'));
        $this->postReport($this->electricityPayload())
            ->assertCreated();

        $this->assertDatabaseCount('utility_reports', 2);
    }

    public function test_missing_or_malformed_anonymous_token_is_rejected_without_exposing_hashes(): void
    {
        $this->postJson('/api/v1/utility-reports', $this->electricityPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anonymous_reporter', 'error.details');

        $this->postReport($this->electricityPayload(), 'bad-token')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anonymous_reporter', 'error.details');

        $body = $this->postReport($this->electricityPayload(), $this->newToken())
            ->assertCreated()
            ->getContent();
        $this->assertStringNotContainsString('anonymous_reporter_id', $body);
        $this->assertStringNotContainsString('token_hash', $body);
    }

    public function test_missing_invalid_and_malformed_input_use_consistent_json_errors(): void
    {
        $this->postReport([])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);

        $this->postReport([...$this->electricityPayload(), 'time_bucket' => 'YESTERDAY'], $this->newToken())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_bucket', 'error.details');

        $this->call(
            'POST',
            '/api/v1/utility-reports',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_ANONYMOUS_REPORTER' => $this->newToken(),
            ],
            '{invalid-json',
        )->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_wrong_json_types_are_rejected_without_query_errors(): void
    {
        $this->postReport([
            'area_id' => ['not-an-id'],
            'sub_area_id' => ['not-an-id'],
            'utility_type' => ['ELECTRICITY'],
            'status' => ['UNAVAILABLE'],
            'time_bucket' => ['NOW'],
            'can_cook' => ['false'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseCount('utility_reports', 0);
    }

    public function test_can_cook_is_rejected_for_electricity(): void
    {
        $this->postReport([...$this->electricityPayload(), 'can_cook' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('can_cook', 'error.details');
    }

    public function test_report_rate_limiter_is_attached_and_testable(): void
    {
        config(['reporting.report_rate_limit_per_minute' => 1]);
        RateLimiter::clear('utility-report:'.hash('sha256', $this->token));

        $this->postReport($this->electricityPayload('UNAVAILABLE'))->assertCreated();
        $this->postReport($this->electricityPayload('AVAILABLE'))
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
    }

    /** @return array<string, mixed> */
    private function electricityPayload(string $status = 'UNAVAILABLE'): array
    {
        return [
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'ELECTRICITY',
            'status' => $status,
            'time_bucket' => 'MIN_15',
        ];
    }

    /** @return array<string, mixed> */
    private function gasPayload(string $status): array
    {
        return [
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'GAS',
            'status' => $status,
            'time_bucket' => 'MIN_30',
        ];
    }

    private function postReport(array $payload, ?string $token = null): TestResponse
    {
        return $this->postJson('/api/v1/utility-reports', $payload, [
            'X-Anonymous-Reporter' => $token ?? $this->token,
        ]);
    }

    private function newToken(): string
    {
        return $this->postJson('/api/v1/anonymous-session')->assertOk()->json('data.token');
    }

    private function createArea(string $name, string $slug): Area
    {
        return Area::query()->create([
            'name' => $name,
            'slug' => $slug,
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
    }
}
