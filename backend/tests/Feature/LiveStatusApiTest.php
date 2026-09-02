<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\UtilityType;
use App\Models\AnonymousReporter;
use App\Models\Area;
use App\Models\SubArea;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-01T12:00:00Z');
        CarbonImmutable::setTestNow($this->now);
        $this->area = Area::query()->create([
            'name' => 'Pallabi',
            'slug' => 'pallabi-api-test',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id,
            'name' => 'Palash Nagar',
            'slug' => 'palash-nagar-api-test',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_locality_without_evidence_returns_insufficient_data_for_both_utilities(): void
    {
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/status")
            ->assertOk()
            ->assertJsonPath('data.sub_area.name', 'Palash Nagar')
            ->assertJsonPath('data.sub_area.area.name', 'Pallabi')
            ->assertJsonPath('data.electricity.status', 'INSUFFICIENT_DATA')
            ->assertJsonPath('data.electricity.confidence', null)
            ->assertJsonPath('data.electricity.recent_reports', 0)
            ->assertJsonPath('data.gas.status', 'INSUFFICIENT_DATA')
            ->assertJsonPath('data.gas.confidence', null)
            ->assertJsonPath('data.gas.last_report_at', null);
    }

    public function test_locality_status_returns_both_projections_without_reporter_identity(): void
    {
        foreach (range(1, 3) as $reporter) {
            $this->createReport($reporter, 'UNAVAILABLE', UtilityType::ELECTRICITY, $reporter);
            $this->createReport($reporter + 10, 'LOW', UtilityType::GAS, $reporter);
        }
        $service = app(LiveStatusProjectionService::class);
        $service->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);
        $service->refresh($this->subArea, UtilityType::GAS, $this->now);

        $response = $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/status")
            ->assertOk()
            ->assertJsonPath('data.electricity.status', 'UNAVAILABLE')
            ->assertJsonPath('data.electricity.confidence', 'MEDIUM')
            ->assertJsonPath('data.electricity.recent_reports', 3)
            ->assertJsonPath('data.electricity.last_report_at', '2026-09-01T11:59:00.000000Z')
            ->assertJsonPath('data.gas.status', 'LOW')
            ->assertJsonPath('data.gas.confidence', 'MEDIUM');

        $this->assertStringNotContainsString('anonymous_reporter', $response->getContent());
        $this->assertStringNotContainsString('token_hash', $response->getContent());
        $this->assertStringNotContainsString('confidence_score', $response->getContent());
    }

    public function test_mixed_state_is_exposed_with_conflicting_counts(): void
    {
        $this->createReport(1, 'AVAILABLE');
        $this->createReport(2, 'AVAILABLE');
        $this->createReport(3, 'UNAVAILABLE');
        $this->createReport(4, 'UNAVAILABLE');
        app(LiveStatusProjectionService::class)
            ->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);

        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/status")
            ->assertOk()
            ->assertJsonPath('data.electricity.status', 'MIXED')
            ->assertJsonPath('data.electricity.supporting_reports', 2)
            ->assertJsonPath('data.electricity.contradicting_reports', 2)
            ->assertJsonPath('data.electricity.status_since', null);
    }

    public function test_query_time_guard_never_returns_stale_projection_as_active_truth(): void
    {
        foreach (range(1, 6) as $reporter) {
            $this->createReport($reporter, 'UNAVAILABLE');
        }
        app(LiveStatusProjectionService::class)
            ->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);
        CarbonImmutable::setTestNow($this->now->addMinutes(31));

        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/status")
            ->assertOk()
            ->assertJsonPath('data.electricity.status', 'INSUFFICIENT_DATA')
            ->assertJsonPath('data.electricity.confidence', null)
            ->assertJsonPath('data.electricity.recent_reports', 0)
            ->assertJsonPath('data.electricity.last_report_at', null);
    }

    public function test_nonexistent_and_inactive_localities_are_not_exposed(): void
    {
        $this->getJson('/api/v1/sub-areas/999999/status')->assertNotFound();
        $this->subArea->update(['is_active' => false]);
        $this->getJson("/api/v1/sub-areas/{$this->subArea->id}/status")->assertNotFound();
    }

    public function test_live_status_listing_is_bounded_filterable_and_privacy_safe(): void
    {
        foreach (range(1, 3) as $reporter) {
            $this->createReport($reporter, 'UNSTABLE');
        }
        app(LiveStatusProjectionService::class)
            ->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);

        $response = $this->getJson('/api/v1/live-statuses?utility=ELECTRICITY&status=UNSTABLE&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.utility_type', 'ELECTRICITY')
            ->assertJsonPath('data.0.status', 'UNSTABLE')
            ->assertJsonPath('data.0.sub_area.name', 'Palash Nagar');

        $this->assertStringNotContainsString('anonymous_reporter', $response->getContent());
        $this->getJson('/api/v1/live-statuses?utility=ELECTRICITY&status=NORMAL')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status', 'error.details');
        $this->getJson('/api/v1/live-statuses?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit', 'error.details');
    }

    public function test_stale_projections_are_omitted_from_listing(): void
    {
        $this->createReport(1, 'AVAILABLE');
        app(LiveStatusProjectionService::class)
            ->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);
        CarbonImmutable::setTestNow($this->now->addMinutes(31));

        $this->getJson('/api/v1/live-statuses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createReport(
        int $reporterNumber,
        string $status,
        UtilityType $utility = UtilityType::ELECTRICITY,
        int $minutesAgo = 1,
    ): UtilityReport {
        $reporter = AnonymousReporter::query()->create([
            'token_hash' => hash('sha256', 'api-reporter-'.$reporterNumber),
        ]);

        return UtilityReport::query()->create([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => 'UNKNOWN',
            'reported_at' => $this->now->subMinutes($minutesAgo),
        ]);
    }
}
