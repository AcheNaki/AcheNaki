<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use App\Enums\UtilityType;
use App\Models\AnonymousReporter;
use App\Models\Area;
use App\Models\ElectricityOutageEvent;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use App\Models\UtilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyUtilityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-01T09:00:00Z'); // 15:00 in Dhaka.
        CarbonImmutable::setTestNow($this->now);
        $this->area = Area::query()->create([
            'name' => 'Pallabi', 'slug' => 'analytics-pallabi',
            'city_corporation' => CityCorporation::DNCC, 'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id, 'name' => 'Palash Nagar',
            'slug' => 'analytics-palash-nagar', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_omitted_date_uses_partial_current_dhaka_day_without_future_unknown_time(): void
    {
        $response = $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-01')
            ->assertJsonPath('data.timezone', 'Asia/Dhaka')
            ->assertJsonPath('data.window.started_at', '2026-08-31T18:00:00.000000Z')
            ->assertJsonPath('data.window.ended_at', '2026-09-01T09:00:00.000000Z')
            ->assertJsonPath('data.window.duration_seconds', 54000)
            ->assertJsonPath('data.window.partial', true)
            ->assertJsonPath('data.electricity.outage_count', 0)
            ->assertJsonPath('data.electricity.coverage.observed_seconds', 0)
            ->assertJsonPath('data.electricity.coverage.unknown_seconds', 54000)
            ->assertJsonPath('data.gas.coverage.unknown_seconds', 54000);

        $this->assertStringNotContainsString('anonymous_reporter', $response->getContent());
        $this->assertStringNotContainsString('token_hash', $response->getContent());
    }

    public function test_electricity_events_are_clipped_across_midnight_and_ongoing_duration_is_derived(): void
    {
        $this->event(
            CarbonImmutable::parse('2026-08-31T17:30:00Z'),
            CarbonImmutable::parse('2026-08-31T19:00:00Z'),
            ElectricityOutageLifecycle::RESOLVED,
        );
        $this->event(
            CarbonImmutable::parse('2026-09-01T08:00:00Z'),
            null,
            ElectricityOutageLifecycle::ACTIVE,
        );

        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-09-01")
            ->assertOk()
            ->assertJsonPath('data.electricity.outage_count', 2)
            ->assertJsonPath('data.electricity.total_outage_seconds', 7200)
            ->assertJsonPath('data.electricity.longest_outage_seconds', 3600)
            ->assertJsonPath('data.electricity.ongoing_outage_seconds', 3600)
            ->assertJsonPath('data.electricity.events.0.duration_seconds', 3600)
            ->assertJsonPath('data.electricity.events.1.ongoing', true);
    }

    public function test_electricity_coverage_uses_reliable_classified_evidence_and_keeps_gaps_unknown(): void
    {
        $at = CarbonImmutable::parse('2026-08-31T18:00:00Z');
        foreach (range(1, 3) as $reporter) {
            $this->report($reporter, UtilityType::ELECTRICITY, 'AVAILABLE', $at->addSeconds($reporter), $at);
        }

        $response = $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-09-01")
            ->assertOk();
        $observed = $response->json('data.electricity.coverage.observed_seconds');
        $unknown = $response->json('data.electricity.coverage.unknown_seconds');

        $this->assertGreaterThanOrEqual(1800, $observed);
        $this->assertLessThanOrEqual(1803, $observed);
        $this->assertSame(54000, $observed + $unknown);
        $this->assertSame($observed, $response->json('data.electricity.state_seconds.available'));
        $this->assertSame('AVAILABLE', $response->json('data.electricity.segments.0.status'));
        $this->assertSame($observed, $response->json('data.electricity.segments.0.duration_seconds'));
    }

    public function test_gas_durations_cross_midnight_and_unknown_gaps_reconcile_to_window(): void
    {
        $this->gasInterval('LOW', '2026-08-31T17:30:00Z', '2026-08-31T19:00:00Z');
        $this->gasInterval('VERY_LOW', '2026-09-01T07:00:00Z', null, '2026-09-01T08:00:00Z');

        $response = $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-09-01")
            ->assertOk()
            ->assertJsonPath('data.gas.state_seconds.low', 3600)
            ->assertJsonPath('data.gas.state_seconds.very_low', 3600)
            ->assertJsonPath('data.gas.coverage.observed_seconds', 7200)
            ->assertJsonPath('data.gas.coverage.unknown_seconds', 46800)
            ->assertJsonPath('data.gas.intervals.1.ongoing', false);

        $categorized = array_sum($response->json('data.gas.state_seconds'));
        $this->assertSame(54000, $categorized + $response->json('data.gas.coverage.unknown_seconds'));
    }

    public function test_explicit_historical_day_uses_full_dhaka_calendar_window(): void
    {
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-08-31")
            ->assertOk()
            ->assertJsonPath('data.window.started_at', '2026-08-30T18:00:00.000000Z')
            ->assertJsonPath('data.window.ended_at', '2026-08-31T18:00:00.000000Z')
            ->assertJsonPath('data.window.duration_seconds', 86400)
            ->assertJsonPath('data.window.partial', false);
    }

    public function test_all_gas_categories_and_ongoing_coverage_are_allocated_in_integer_seconds(): void
    {
        $this->gasInterval('NORMAL', '2026-08-31T18:00:00Z', '2026-08-31T18:10:00Z');
        $this->gasInterval('LOW', '2026-08-31T18:10:00Z', '2026-08-31T18:20:00Z');
        $this->gasInterval('VERY_LOW', '2026-08-31T18:20:00Z', '2026-08-31T18:30:00Z');
        $this->gasInterval('UNAVAILABLE', '2026-08-31T18:30:00Z', null, '2026-09-01T09:30:00Z');

        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-09-01")
            ->assertOk()
            ->assertJsonPath('data.gas.state_seconds.normal', 600)
            ->assertJsonPath('data.gas.state_seconds.low', 600)
            ->assertJsonPath('data.gas.state_seconds.very_low', 600)
            ->assertJsonPath('data.gas.state_seconds.unavailable', 52200)
            ->assertJsonPath('data.gas.coverage.observed_seconds', 54000)
            ->assertJsonPath('data.gas.coverage.unknown_seconds', 0)
            ->assertJsonPath('data.gas.intervals.3.ongoing', true);
    }

    public function test_invalid_future_and_inactive_locality_requests_are_rejected(): void
    {
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=not-a-date")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date', 'error.details');
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics?date=2026-09-02")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date', 'error.details');
        $this->getJson('/api/v1/sub-areas/999999/analytics')->assertNotFound();
        $this->subArea->update(['is_active' => false]);
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/analytics")->assertNotFound();
    }

    private function event(
        CarbonImmutable $start,
        ?CarbonImmutable $end,
        ElectricityOutageLifecycle $lifecycle,
    ): ElectricityOutageEvent {
        return ElectricityOutageEvent::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'lifecycle' => $lifecycle,
            'started_at' => $start,
            'first_supported_at' => $start,
            'confirmed_at' => $start,
            'ended_at' => $end,
            'start_confidence_level' => ConfidenceLevel::MEDIUM,
            'end_confidence_level' => $end === null ? null : ConfidenceLevel::MEDIUM,
            'inference_version' => 1,
        ]);
    }

    private function gasInterval(
        string $status,
        string $start,
        ?string $end,
        ?string $observedUntil = null,
    ): GasStateInterval {
        return GasStateInterval::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'status' => $status,
            'started_at' => CarbonImmutable::parse($start),
            'ended_at' => $end === null ? null : CarbonImmutable::parse($end),
            'observed_until_at' => CarbonImmutable::parse($observedUntil ?? $end),
            'start_confidence_level' => ConfidenceLevel::MEDIUM,
            'inference_version' => 1,
        ]);
    }

    private function report(
        int $reporterNumber,
        UtilityType $utility,
        string $status,
        CarbonImmutable $reportedAt,
        ?CarbonImmutable $estimatedStart = null,
    ): UtilityReport {
        $reporter = AnonymousReporter::query()->create(['token_hash' => hash('sha256', 'analytics-'.$reporterNumber)]);

        return UtilityReport::query()->create([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => $estimatedStart === null ? 'UNKNOWN' : 'NOW',
            'reported_at' => $reportedAt,
            'estimated_started_at' => $estimatedStart,
        ]);
    }
}
