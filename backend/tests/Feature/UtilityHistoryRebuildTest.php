<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
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

class UtilityHistoryRebuildTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SubArea $subArea;

    /** @var array<int, AnonymousReporter> */
    private array $reporters = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = Area::query()->create([
            'name' => 'Pallabi', 'slug' => 'rebuild-pallabi',
            'city_corporation' => CityCorporation::DNCC, 'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id, 'name' => 'Palash Nagar',
            'slug' => 'rebuild-palash-nagar', 'is_active' => true,
        ]);
    }

    public function test_rebuild_commands_are_idempotent_and_preserve_raw_reports(): void
    {
        $start = CarbonImmutable::parse('2026-09-01T06:00:00Z');
        foreach (range(1, 3) as $reporter) {
            $this->report($reporter, UtilityType::ELECTRICITY, 'UNAVAILABLE', $start->addSeconds($reporter));
        }
        $this->report(4, UtilityType::ELECTRICITY, 'UNAVAILABLE', $start->addSeconds(123));
        foreach (range(1, 3) as $reporter) {
            $this->report($reporter, UtilityType::ELECTRICITY, 'AVAILABLE', $start->addSeconds(300 + $reporter));
        }
        $this->report(4, UtilityType::ELECTRICITY, 'AVAILABLE', $start->addSeconds(423));

        foreach (range(10, 12) as $offset => $reporter) {
            $this->report($reporter, UtilityType::GAS, 'NORMAL', $start->addSeconds($offset));
        }
        foreach (range(10, 12) as $offset => $reporter) {
            $this->report($reporter, UtilityType::GAS, 'LOW', $start->addSeconds(300 + $offset));
        }
        $this->report(13, UtilityType::GAS, 'LOW', $start->addSeconds(422));
        $rawCount = UtilityReport::query()->count();

        $this->artisan('electricity-events:rebuild')
            ->expectsOutput('Rebuilt electricity events for 1 sub-area(s). Event IDs may change; raw reports were unchanged.')
            ->assertSuccessful();
        $this->artisan('gas-intervals:rebuild')
            ->expectsOutput('Rebuilt gas intervals for 1 sub-area(s). Interval IDs may change; raw reports were unchanged.')
            ->assertSuccessful();

        $this->assertSame($rawCount, UtilityReport::query()->count());
        $this->assertSame(ElectricityOutageLifecycle::RESOLVED, ElectricityOutageEvent::query()->firstOrFail()->lifecycle);
        $this->assertDatabaseCount('electricity_outage_events', 1);
        $this->assertDatabaseCount('gas_state_intervals', 2);
        $this->assertSame('LOW', GasStateInterval::query()->whereNull('ended_at')->firstOrFail()->status->value);

        $this->artisan('electricity-events:rebuild')->assertSuccessful();
        $this->artisan('gas-intervals:rebuild')->assertSuccessful();
        $this->assertSame($rawCount, UtilityReport::query()->count());
        $this->assertDatabaseCount('electricity_outage_events', 1);
        $this->assertDatabaseCount('gas_state_intervals', 2);
    }

    private function report(
        int $reporterNumber,
        UtilityType $utility,
        string $status,
        CarbonImmutable $reportedAt,
    ): UtilityReport {
        $reporter = $this->reporters[$reporterNumber] ??= AnonymousReporter::query()->create([
            'token_hash' => hash('sha256', 'rebuild-'.$reporterNumber),
        ]);

        return UtilityReport::query()->create([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => 'UNKNOWN',
            'reported_at' => $reportedAt,
        ]);
    }
}
