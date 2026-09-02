<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use App\Enums\UtilityType;
use App\Models\Area;
use App\Models\ElectricityOutageEvent;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReadApiTest extends TestCase
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
        $this->area = Area::query()->create(['name' => 'Pallabi', 'slug' => 'pallabi-dashboard', 'city_corporation' => CityCorporation::DNCC, 'is_active' => true]);
        $this->subArea = SubArea::query()->create(['area_id' => $this->area->id, 'name' => 'Palash Nagar', 'slug' => 'palash-nagar-dashboard', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_slug_status_route_respects_parent_relationship_and_returns_safe_snapshot(): void
    {
        $this->getJson('/api/v1/areas/pallabi-dashboard/sub-areas/palash-nagar-dashboard/status')
            ->assertOk()
            ->assertJsonPath('data.sub_area.slug', 'palash-nagar-dashboard')
            ->assertJsonPath('data.electricity.status', 'INSUFFICIENT_DATA');
        $this->getJson('/api/v1/areas/not-pallabi/sub-areas/palash-nagar-dashboard/status')->assertNotFound();
    }

    public function test_dashboard_excludes_stale_projection_and_lists_current_issue_without_reporter_data(): void
    {
        $this->projection('UNAVAILABLE', UtilityType::ELECTRICITY, $this->now->subMinute());
        $response = $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.struggling.0.status', 'UNAVAILABLE')
            ->assertJsonPath('data.struggling.0.sub_area.slug', 'palash-nagar-dashboard');
        $this->assertStringNotContainsString('anonymous_reporter', $response->getContent());
        $this->assertStringNotContainsString('confidence_score', $response->getContent());

        UtilityLiveStatus::query()->update(['last_report_at' => $this->now->subMinutes(31)]);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonCount(0, 'data.struggling');
    }

    public function test_insufficient_data_is_never_listed_as_a_struggling_locality(): void
    {
        // "Not enough evidence" is the absence of a signal, not an issue signal. A locality with
        // no usable evidence must not appear as struggling, or the dashboard would invent problems.
        $this->projection('INSUFFICIENT_DATA', UtilityType::ELECTRICITY, $this->now->subMinute());

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonCount(0, 'data.struggling')
            ->assertJsonCount(0, 'data.recent_changes');
    }

    public function test_dashboard_items_carry_the_parent_major_area_of_each_locality(): void
    {
        // A locality name such as "Block C" repeats across major areas, so every dashboard card
        // needs its parent area in the same payload — the UI must never make a second request.
        $this->projection('UNAVAILABLE', UtilityType::ELECTRICITY, $this->now->subMinute());

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.struggling.0.sub_area.name', 'Palash Nagar')
            ->assertJsonPath('data.struggling.0.sub_area.area.name', 'Pallabi')
            ->assertJsonPath('data.struggling.0.sub_area.area.slug', 'pallabi-dashboard')
            ->assertJsonPath('data.recent_changes.0.sub_area.area.name', 'Pallabi');
    }

    public function test_area_statuses_are_loaded_in_one_area_read_shape(): void
    {
        $this->projection('LOW', UtilityType::GAS, $this->now->subMinute());
        $this->getJson('/api/v1/areas/pallabi-dashboard/statuses')
            ->assertOk()
            ->assertJsonPath('data.area.slug', 'pallabi-dashboard')
            ->assertJsonPath('data.localities.0.gas.status', 'LOW');
    }

    public function test_recently_resolved_events_are_bounded_sorted_and_do_not_leak_reporters(): void
    {
        foreach ([20, 5] as $minutesAgo) {
            ElectricityOutageEvent::query()->create([
                'area_id' => $this->area->id,
                'sub_area_id' => $this->subArea->id,
                'lifecycle' => ElectricityOutageLifecycle::RESOLVED,
                'started_at' => $this->now->subMinutes($minutesAgo + 10),
                'first_supported_at' => $this->now->subMinutes($minutesAgo + 10),
                'confirmed_at' => $this->now->subMinutes($minutesAgo + 9),
                'ended_at' => $this->now->subMinutes($minutesAgo),
                'start_confidence_level' => ConfidenceLevel::MEDIUM,
                'end_confidence_level' => ConfidenceLevel::MEDIUM,
                'inference_version' => 1,
            ]);
        }
        $response = $this->getJson('/api/v1/electricity-events/recently-resolved?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sub_area.name', 'Palash Nagar');
        $this->assertStringNotContainsString('anonymous_reporter', $response->getContent());
        $this->getJson('/api/v1/electricity-events/recently-resolved?limit=21')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors('limit', 'error.details');
    }

    private function projection(string $status, UtilityType $utility, CarbonImmutable $lastReportAt): void
    {
        UtilityLiveStatus::query()->create([
            'area_id' => $this->area->id, 'sub_area_id' => $this->subArea->id, 'utility_type' => $utility,
            'estimated_status' => $status, 'confidence_score' => 65, 'confidence_level' => ConfidenceLevel::MEDIUM,
            'recent_report_count' => 3, 'supporting_report_count' => 3, 'contradicting_report_count' => 0,
            'evidence_window_started_at' => $this->now->subMinutes(30), 'last_report_at' => $lastReportAt, 'calculated_at' => $this->now,
        ]);
    }
}
