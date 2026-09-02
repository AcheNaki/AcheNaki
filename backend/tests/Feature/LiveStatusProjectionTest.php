<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\UtilityType;
use App\Models\AnonymousReporter;
use App\Models\Area;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LiveStatusProjectionTest extends TestCase
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
            'slug' => 'pallabi-test',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id,
            'name' => 'Palash Nagar',
            'slug' => 'palash-nagar-test',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_first_accepted_report_creates_projection_and_duplicate_does_not_add_evidence(): void
    {
        $token = $this->newToken();
        $payload = $this->payload('ELECTRICITY', 'UNAVAILABLE');

        $this->postReport($payload, $token)->assertCreated();
        $this->postReport($payload, $token)->assertOk()->assertJsonPath('meta.duplicate', true);

        $this->assertDatabaseCount('utility_reports', 1);
        $this->assertDatabaseHas('utility_live_statuses', [
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'ELECTRICITY',
            'estimated_status' => 'UNAVAILABLE',
            'confidence_level' => 'LOW',
            'recent_report_count' => 1,
        ]);
    }

    public function test_state_transition_from_same_reporter_replaces_live_evidence(): void
    {
        $token = $this->newToken();
        $this->postReport($this->payload('ELECTRICITY', 'UNAVAILABLE'), $token)->assertCreated();
        CarbonImmutable::setTestNow($this->now->addMinute());
        $this->postReport($this->payload('ELECTRICITY', 'AVAILABLE'), $token)->assertCreated();

        $this->assertDatabaseCount('utility_reports', 2);
        $this->assertDatabaseHas('utility_live_statuses', [
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'ELECTRICITY',
            'estimated_status' => 'AVAILABLE',
            'recent_report_count' => 1,
            'supporting_report_count' => 1,
            'contradicting_report_count' => 0,
        ]);
    }

    public function test_projection_failure_does_not_discard_accepted_raw_report(): void
    {
        $this->mock(LiveStatusProjectionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshByIds')
                ->once()
                ->andThrow(new RuntimeException('Projection unavailable'));
        });

        $this->postReport($this->payload('ELECTRICITY', 'UNAVAILABLE'), $this->newToken())
            ->assertCreated();

        $this->assertDatabaseCount('utility_reports', 1);
        $this->assertDatabaseCount('utility_live_statuses', 0);
    }

    public function test_projection_has_one_row_per_sub_area_and_utility(): void
    {
        $this->createReport(1, 'AVAILABLE');
        $service = app(LiveStatusProjectionService::class);
        $service->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);
        $service->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);

        $this->assertDatabaseCount('utility_live_statuses', 1);
    }

    public function test_database_rejects_mismatched_area_and_sub_area_projection(): void
    {
        $otherArea = Area::query()->create([
            'name' => 'Other Area',
            'slug' => 'other-area-test',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        UtilityLiveStatus::query()->create([
            'area_id' => $otherArea->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => 'ELECTRICITY',
            'estimated_status' => 'INSUFFICIENT_DATA',
            'confidence_score' => 0,
            'confidence_level' => null,
            'evidence_window_started_at' => $this->now->subMinutes(30),
            'calculated_at' => $this->now,
        ]);
    }

    public function test_rebuild_command_recreates_equivalent_projection_without_touching_reports(): void
    {
        $this->createReport(1, 'UNAVAILABLE', 1);
        $this->createReport(2, 'UNAVAILABLE', 2);
        $this->createReport(3, 'AVAILABLE', 3);
        $service = app(LiveStatusProjectionService::class);
        $before = $service->refresh($this->subArea, UtilityType::ELECTRICITY, $this->now);
        $expected = $before->only([
            'estimated_status',
            'confidence_score',
            'confidence_level',
            'recent_report_count',
            'supporting_report_count',
            'contradicting_report_count',
        ]);
        $reportCount = UtilityReport::query()->count();
        UtilityLiveStatus::query()->delete();

        $this->artisan('utility-status:rebuild')
            ->expectsOutput('Rebuilt 1 live utility status projection(s). Raw reports were unchanged.')
            ->assertSuccessful();

        $rebuilt = UtilityLiveStatus::query()->firstOrFail();
        $this->assertSame($expected, $rebuilt->only(array_keys($expected)));
        $this->assertSame($reportCount, UtilityReport::query()->count());
    }

    private function createReport(
        int $reporterNumber,
        string $status,
        int $minutesAgo = 1,
        UtilityType $utility = UtilityType::ELECTRICITY,
    ): UtilityReport {
        $reporter = AnonymousReporter::query()->create([
            'token_hash' => hash('sha256', 'reporter-'.$reporterNumber),
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

    /** @return array<string, mixed> */
    private function payload(string $utility, string $status): array
    {
        return [
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => 'UNKNOWN',
        ];
    }

    private function newToken(): string
    {
        return $this->postJson('/api/v1/anonymous-session')->assertOk()->json('data.token');
    }

    private function postReport(array $payload, string $token): TestResponse
    {
        return $this->postJson('/api/v1/utility-reports', $payload, [
            'X-Anonymous-Reporter' => $token,
        ]);
    }
}
