<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ConfidenceLevel;
use App\Enums\UtilityType;
use App\Models\AnonymousReporter;
use App\Models\Area;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LiveSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $first;

    private SubArea $second;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-01T12:00:00Z');
        CarbonImmutable::setTestNow($this->now);
        $this->area = Area::query()->create([
            'name' => 'Pallabi', 'slug' => 'pallabi-summary',
            'city_corporation' => CityCorporation::DNCC, 'is_active' => true,
        ]);
        $this->first = $this->subArea('Palash Nagar', 'palash-nagar-summary');
        $this->second = $this->subArea('Block C', 'block-c-summary');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_no_recent_activity_reports_zero_without_inventing_a_status(): void
    {
        $this->summary()
            ->assertJsonPath('data.window_minutes', 30)
            ->assertJsonPath('data.reports', 0)
            ->assertJsonPath('data.localities_updated', 0)
            ->assertJsonPath('data.electricity_issue_localities', 0)
            ->assertJsonPath('data.gas_issue_localities', 0)
            ->assertJsonPath('data.currently_struggling_localities', 0);
    }

    public function test_report_counts_follow_the_rolling_window_and_count_each_locality_once(): void
    {
        $this->report($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinutes(2));
        $this->report($this->first, UtilityType::GAS, 'LOW', $this->now->subMinutes(20));
        $this->report($this->second, UtilityType::ELECTRICITY, 'AVAILABLE', $this->now->subMinutes(29));
        // Aged out of the rolling window: still a row, but no longer current activity.
        $this->report($this->second, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinutes(31));

        $this->summary()
            ->assertJsonPath('data.reports', 3)
            ->assertJsonPath('data.localities_updated', 2);
    }

    public function test_healthy_and_evidence_free_projections_are_never_counted_as_issues(): void
    {
        $this->projection($this->first, UtilityType::ELECTRICITY, 'AVAILABLE', $this->now->subMinute());
        $this->projection($this->first, UtilityType::GAS, 'NORMAL', $this->now->subMinute());
        $this->projection($this->second, UtilityType::ELECTRICITY, 'INSUFFICIENT_DATA', $this->now->subMinute());

        $this->summary()
            ->assertJsonPath('data.electricity_issue_localities', 0)
            ->assertJsonPath('data.gas_issue_localities', 0)
            ->assertJsonPath('data.currently_struggling_localities', 0);
    }

    public function test_electricity_and_gas_problem_states_are_counted_as_distinct_localities(): void
    {
        $this->projection($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinute());
        $this->projection($this->second, UtilityType::ELECTRICITY, 'UNSTABLE', $this->now->subMinute());
        $this->projection($this->first, UtilityType::GAS, 'VERY_LOW', $this->now->subMinute());
        $this->projection($this->second, UtilityType::GAS, 'LOW', $this->now->subMinute());

        $this->summary()
            ->assertJsonPath('data.electricity_issue_localities', 2)
            ->assertJsonPath('data.gas_issue_localities', 2)
            ->assertJsonPath('data.currently_struggling_localities', 2);
    }

    public function test_unavailable_gas_counts_and_one_locality_in_double_trouble_counts_once(): void
    {
        $this->projection($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinute());
        $this->projection($this->first, UtilityType::GAS, 'UNAVAILABLE', $this->now->subMinute());

        $this->summary()
            ->assertJsonPath('data.electricity_issue_localities', 1)
            ->assertJsonPath('data.gas_issue_localities', 1)
            ->assertJsonPath('data.currently_struggling_localities', 1);
    }

    public function test_a_stale_projection_row_is_not_a_current_problem(): void
    {
        $this->projection($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinutes(31));
        $this->projection($this->second, UtilityType::GAS, 'LOW', $this->now->subMinutes(45));

        $this->summary()
            ->assertJsonPath('data.electricity_issue_localities', 0)
            ->assertJsonPath('data.gas_issue_localities', 0)
            ->assertJsonPath('data.currently_struggling_localities', 0);
    }

    public function test_localities_outside_the_active_taxonomy_are_excluded(): void
    {
        $this->projection($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinute());
        $this->first->update(['is_active' => false]);

        $this->summary()->assertJsonPath('data.currently_struggling_localities', 0);
    }

    public function test_the_summary_exposes_counts_only_and_never_reporter_or_scoring_internals(): void
    {
        $this->report($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinute());
        $this->projection($this->first, UtilityType::ELECTRICITY, 'UNAVAILABLE', $this->now->subMinute());

        $response = $this->summary();
        $content = $response->getContent();

        foreach (['anonymous_reporter', 'token_hash', 'confidence_score', 'sub_area_id', 'latitude'] as $leak) {
            $this->assertStringNotContainsString($leak, $content);
        }

        $this->assertSame([
            'window_minutes',
            'reports',
            'localities_updated',
            'electricity_issue_localities',
            'gas_issue_localities',
            'currently_struggling_localities',
            'calculated_at',
        ], array_keys($response->json('data')));
    }

    private function summary(): TestResponse
    {
        return $this->getJson('/api/v1/live-summary')->assertOk();
    }

    private function subArea(string $name, string $slug): SubArea
    {
        return SubArea::query()->create([
            'area_id' => $this->area->id, 'name' => $name, 'slug' => $slug, 'is_active' => true,
        ]);
    }

    private function report(SubArea $subArea, UtilityType $utility, string $status, CarbonImmutable $reportedAt): void
    {
        // A distinct pseudonymous reporter per report; the summary must never surface any of them.
        $reporter = AnonymousReporter::query()->create([
            'token_hash' => hash('sha256', $subArea->id.$utility->value.$status.$reportedAt->toIso8601String()),
        ]);

        UtilityReport::query()->create([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $this->area->id,
            'sub_area_id' => $subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => 'NOW',
            'reported_at' => $reportedAt,
        ]);
    }

    private function projection(SubArea $subArea, UtilityType $utility, string $status, CarbonImmutable $lastReportAt): void
    {
        $insufficient = $status === 'INSUFFICIENT_DATA';

        UtilityLiveStatus::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $subArea->id,
            'utility_type' => $utility,
            'estimated_status' => $status,
            'confidence_score' => $insufficient ? 0 : 65,
            'confidence_level' => $insufficient ? null : ConfidenceLevel::MEDIUM,
            'recent_report_count' => $insufficient ? 0 : 3,
            'supporting_report_count' => $insufficient ? 0 : 3,
            'contradicting_report_count' => 0,
            'evidence_window_started_at' => $this->now->subMinutes(30),
            'last_report_at' => $lastReportAt,
            'calculated_at' => $this->now,
        ]);
    }
}
