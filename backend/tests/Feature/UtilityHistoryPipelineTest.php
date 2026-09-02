<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Enums\ElectricityOutageLifecycle;
use App\Models\Area;
use App\Models\ElectricityOutageEvent;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityHistoryPipelineTest extends TestCase
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
            'name' => 'Pallabi', 'slug' => 'pipeline-pallabi',
            'city_corporation' => CityCorporation::DNCC, 'is_active' => true,
        ]);
        $this->subArea = SubArea::query()->create([
            'area_id' => $this->area->id, 'name' => 'Palash Nagar',
            'slug' => 'pipeline-palash-nagar', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_accepted_reports_flow_through_live_projection_into_utility_history(): void
    {
        foreach (range(1, 3) as $reporter) {
            $this->submit('ELECTRICITY', 'UNAVAILABLE', $this->token());
        }
        $this->assertSame(ElectricityOutageLifecycle::CANDIDATE, ElectricityOutageEvent::query()->firstOrFail()->lifecycle);

        CarbonImmutable::setTestNow($this->now->addMinutes(2));
        $this->submit('ELECTRICITY', 'UNAVAILABLE', $this->token());
        $this->assertSame(ElectricityOutageLifecycle::ACTIVE, ElectricityOutageEvent::query()->firstOrFail()->refresh()->lifecycle);

        foreach (range(1, 3) as $reporter) {
            $this->submit('GAS', 'NORMAL', $this->token());
        }
        $this->assertSame('NORMAL', GasStateInterval::query()->firstOrFail()->status->value);
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/anonymous-session')->assertOk()->json('data.token');
    }

    private function submit(string $utility, string $status, string $token): void
    {
        $this->postJson('/api/v1/utility-reports', [
            'area_id' => $this->area->id,
            'sub_area_id' => $this->subArea->id,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => 'UNKNOWN',
        ], ['X-Anonymous-Reporter' => $token])->assertCreated();
    }
}
