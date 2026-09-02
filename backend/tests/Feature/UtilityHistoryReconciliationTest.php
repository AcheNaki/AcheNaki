<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Models\Area;
use App\Models\ElectricityOutageEvent;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Services\UtilityHistory\ElectricityEventReconciler;
use App\Services\UtilityHistory\GasIntervalReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityHistoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-01T12:00:00Z');
        $this->area = Area::query()->create([
            'name' => 'Pallabi', 'slug' => 'history-pallabi',
            'city_corporation' => CityCorporation::DNCC, 'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id, 'name' => 'Palash Nagar',
            'slug' => 'history-palash-nagar', 'is_active' => true,
        ]);
    }

    public function test_low_confidence_or_available_projection_does_not_create_outage(): void
    {
        $events = app(ElectricityEventReconciler::class);
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::LOW));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::HIGH));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNSTABLE, ConfidenceLevel::HIGH));

        $this->assertDatabaseCount('electricity_outage_events', 0);
    }

    public function test_supported_unavailable_requires_stabilization_then_activates_once(): void
    {
        $events = app(ElectricityEventReconciler::class);
        $events->reconcile($this->subArea, $this->projection(
            UtilityType::ELECTRICITY,
            LiveStatus::UNAVAILABLE,
            ConfidenceLevel::MEDIUM,
            $this->now,
            $this->now->subMinutes(15),
        ));

        $candidate = ElectricityOutageEvent::query()->firstOrFail();
        $this->assertSame(ElectricityOutageLifecycle::CANDIDATE, $candidate->lifecycle);
        $this->assertEquals($this->now->subMinutes(15), $candidate->started_at);

        $events->reconcile($this->subArea, $this->projection(
            UtilityType::ELECTRICITY,
            LiveStatus::UNAVAILABLE,
            ConfidenceLevel::HIGH,
            $this->now->addMinutes(2),
        ));
        $events->reconcile($this->subArea, $this->projection(
            UtilityType::ELECTRICITY,
            LiveStatus::UNAVAILABLE,
            ConfidenceLevel::HIGH,
            $this->now->addMinutes(3),
        ));

        $this->assertDatabaseCount('electricity_outage_events', 1);
        $this->assertSame(ElectricityOutageLifecycle::ACTIVE, $candidate->refresh()->lifecycle);
    }

    public function test_unknown_start_uses_first_supported_time_without_fabrication(): void
    {
        app(ElectricityEventReconciler::class)->reconcile(
            $this->subArea,
            $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM),
        );

        $this->assertEquals($this->now, ElectricityOutageEvent::query()->firstOrFail()->started_at);
    }

    public function test_outage_resolves_only_after_stable_supported_available_evidence(): void
    {
        $events = app(ElectricityEventReconciler::class);
        $this->activateOutage($events);

        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::LOW, $this->now->addMinutes(3)));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::MIXED, ConfidenceLevel::MEDIUM, $this->now->addMinutes(4)));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNSTABLE, ConfidenceLevel::HIGH, $this->now->addMinutes(5)));
        $this->assertSame(ElectricityOutageLifecycle::ACTIVE, ElectricityOutageEvent::query()->firstOrFail()->lifecycle);

        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addMinutes(6)));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::INSUFFICIENT_DATA, null, $this->now->addMinutes(7)));
        $this->assertNull(ElectricityOutageEvent::query()->firstOrFail()->resolution_candidate_at);

        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addMinutes(8)));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::HIGH, $this->now->addMinutes(10)));

        $resolved = ElectricityOutageEvent::query()->firstOrFail();
        $this->assertSame(ElectricityOutageLifecycle::RESOLVED, $resolved->lifecycle);
        $this->assertEquals($this->now->addMinutes(8), $resolved->ended_at);
        $this->assertTrue($resolved->ended_at->greaterThanOrEqualTo($resolved->started_at));
    }

    public function test_brief_available_noise_does_not_split_active_outage(): void
    {
        $events = app(ElectricityEventReconciler::class);
        $this->activateOutage($events);
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::AVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addMinutes(3)));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addMinutes(4)));

        $this->assertDatabaseCount('electricity_outage_events', 1);
        $event = ElectricityOutageEvent::query()->firstOrFail();
        $this->assertSame(ElectricityOutageLifecycle::ACTIVE, $event->lifecycle);
        $this->assertNull($event->resolution_candidate_at);
    }

    public function test_stale_transition_candidate_must_stabilize_again(): void
    {
        $events = app(ElectricityEventReconciler::class);
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addHour()));

        $candidate = ElectricityOutageEvent::query()->firstOrFail();
        $this->assertSame(ElectricityOutageLifecycle::CANDIDATE, $candidate->lifecycle);
        $this->assertEquals($this->now->addHour(), $candidate->first_supported_at);

        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addHour()->addMinutes(2)));
        $this->assertSame(ElectricityOutageLifecycle::ACTIVE, $candidate->refresh()->lifecycle);
    }

    public function test_partial_unique_index_prevents_multiple_open_outages(): void
    {
        $this->createOpenEvent();
        $this->expectException(QueryException::class);
        $this->createOpenEvent();
    }

    public function test_partial_unique_index_prevents_multiple_open_gas_intervals(): void
    {
        app(GasIntervalReconciler::class)->reconcile(
            $this->subArea,
            $this->projection(UtilityType::GAS, LiveStatus::NORMAL, ConfidenceLevel::MEDIUM),
        );

        $this->expectException(QueryException::class);
        GasStateInterval::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'status' => 'LOW',
            'started_at' => $this->now,
            'observed_until_at' => $this->now->addMinutes(30),
            'start_confidence_level' => ConfidenceLevel::MEDIUM,
            'inference_version' => 1,
        ]);
    }

    public function test_first_reliable_gas_state_opens_interval_and_low_noise_is_ignored(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::NORMAL, ConfidenceLevel::MEDIUM));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::LOW, $this->now->addMinute()));

        $interval = GasStateInterval::query()->firstOrFail();
        $this->assertSame('NORMAL', $interval->status->value);
        $this->assertNull($interval->pending_status);
    }

    public function test_gas_transition_requires_stability_and_preserves_one_open_interval(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::NORMAL, ConfidenceLevel::MEDIUM));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM, $this->now->addMinute()));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::HIGH, $this->now->addMinutes(3)));

        $this->assertDatabaseCount('gas_state_intervals', 2);
        $this->assertSame(1, GasStateInterval::query()->whereNull('ended_at')->count());
        $this->assertSame('LOW', GasStateInterval::query()->whereNull('ended_at')->firstOrFail()->status->value);
    }

    public function test_mixed_gas_clears_pending_transition_and_stale_time_is_not_extended(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM));
        $before = GasStateInterval::query()->firstOrFail()->observed_until_at;
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::VERY_LOW, ConfidenceLevel::MEDIUM, $this->now->addMinute()));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::MIXED, ConfidenceLevel::MEDIUM, $this->now->addMinutes(2)));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::INSUFFICIENT_DATA, null, $this->now->addHour()));

        $interval = GasStateInterval::query()->firstOrFail();
        $this->assertNull($interval->pending_status);
        $this->assertEquals($before, $interval->observed_until_at);
    }

    public function test_gas_supports_each_stable_categorical_transition_without_overlap(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::NORMAL, ConfidenceLevel::MEDIUM));
        $minute = 1;

        foreach ([LiveStatus::LOW, LiveStatus::VERY_LOW, LiveStatus::UNAVAILABLE, LiveStatus::NORMAL] as $status) {
            $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, $status, ConfidenceLevel::MEDIUM, $this->now->addMinutes($minute)));
            $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, $status, ConfidenceLevel::MEDIUM, $this->now->addMinutes($minute + 2)));
            $minute += 3;
        }

        $this->assertSame(
            ['NORMAL', 'LOW', 'VERY_LOW', 'UNAVAILABLE', 'NORMAL'],
            GasStateInterval::query()->orderBy('started_at')->get()->map(fn ($interval) => $interval->status->value)->all(),
        );
        $this->assertSame(1, GasStateInterval::query()->whereNull('ended_at')->count());
    }

    public function test_gas_flapping_does_not_create_short_intervals(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::VERY_LOW, ConfidenceLevel::MEDIUM, $this->now->addMinute()));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM, $this->now->addMinutes(2)));
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::VERY_LOW, ConfidenceLevel::MEDIUM, $this->now->addMinutes(3)));

        $this->assertDatabaseCount('gas_state_intervals', 1);
        $this->assertSame('LOW', GasStateInterval::query()->firstOrFail()->status->value);
    }

    public function test_same_gas_state_after_stale_gap_opens_new_coverage_interval(): void
    {
        $gas = app(GasIntervalReconciler::class);
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM));
        $firstObservedUntil = GasStateInterval::query()->firstOrFail()->observed_until_at;
        $gas->reconcile($this->subArea, $this->projection(UtilityType::GAS, LiveStatus::LOW, ConfidenceLevel::MEDIUM, $this->now->addHours(2)));

        $this->assertDatabaseCount('gas_state_intervals', 2);
        $this->assertEquals($firstObservedUntil, GasStateInterval::query()->orderBy('id')->firstOrFail()->ended_at);
        $this->assertEquals($this->now->addHours(2), GasStateInterval::query()->whereNull('ended_at')->firstOrFail()->started_at);
    }

    private function activateOutage(ElectricityEventReconciler $events): void
    {
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM));
        $events->reconcile($this->subArea, $this->projection(UtilityType::ELECTRICITY, LiveStatus::UNAVAILABLE, ConfidenceLevel::MEDIUM, $this->now->addMinutes(2)));
    }

    private function createOpenEvent(): ElectricityOutageEvent
    {
        return ElectricityOutageEvent::query()->create([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'lifecycle' => ElectricityOutageLifecycle::CANDIDATE,
            'started_at' => $this->now,
            'first_supported_at' => $this->now,
            'start_confidence_level' => ConfidenceLevel::MEDIUM,
            'inference_version' => 1,
        ]);
    }

    private function projection(
        UtilityType $utility,
        LiveStatus $status,
        ?ConfidenceLevel $confidence,
        ?CarbonImmutable $at = null,
        ?CarbonImmutable $statusSince = null,
    ): UtilityLiveStatus {
        $calculatedAt = $at ?? $this->now;

        return (new UtilityLiveStatus)->forceFill([
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'estimated_status' => $status,
            'confidence_score' => $confidence === null ? 0 : 70,
            'confidence_level' => $confidence,
            'recent_report_count' => $confidence === null ? 0 : 3,
            'supporting_report_count' => $confidence === null ? 0 : 3,
            'contradicting_report_count' => 0,
            'status_since' => $statusSince,
            'evidence_window_started_at' => $calculatedAt->subMinutes(30),
            'last_report_at' => $confidence === null ? null : $calculatedAt,
            'calculated_at' => $calculatedAt,
        ]);
    }
}
