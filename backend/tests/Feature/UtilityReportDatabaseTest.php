<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Models\AnonymousReporter;
use App\Models\Area;
use App\Models\SubArea;
use App\Models\UtilityReport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class UtilityReportDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_and_hidden_reporter_identity_work(): void
    {
        [$reporter, $area, $subArea] = $this->domainRecords();
        $report = $this->createReport($reporter, $area, $subArea);

        $this->assertTrue($report->reporter->is($reporter));
        $this->assertTrue($report->area->is($area));
        $this->assertTrue($report->subArea->is($subArea));
        $this->assertTrue($reporter->utilityReports->contains($report));
        $this->assertArrayNotHasKey('anonymous_reporter_id', $report->toArray());
        $this->assertArrayNotHasKey('token_hash', $reporter->toArray());
    }

    public function test_accepted_reports_are_immutable_through_the_model(): void
    {
        [$reporter, $area, $subArea] = $this->domainRecords();
        $report = $this->createReport($reporter, $area, $subArea);

        $this->expectException(LogicException::class);
        $report->update(['status' => 'AVAILABLE']);
    }

    public function test_accepted_reports_cannot_be_deleted_through_the_model(): void
    {
        [$reporter, $area, $subArea] = $this->domainRecords();
        $report = $this->createReport($reporter, $area, $subArea);

        $this->expectException(LogicException::class);
        $report->delete();
    }

    public function test_database_rejects_invalid_utility_status_combination(): void
    {
        [$reporter, $area, $subArea] = $this->domainRecords();

        $this->expectException(QueryException::class);
        $this->insertRawReport($reporter, $area, $subArea, [
            'utility_type' => 'ELECTRICITY',
            'status' => 'VERY_LOW',
        ]);
    }

    public function test_database_rejects_gas_context_on_electricity_report(): void
    {
        [$reporter, $area, $subArea] = $this->domainRecords();

        $this->expectException(QueryException::class);
        $this->insertRawReport($reporter, $area, $subArea, [
            'utility_type' => 'ELECTRICITY',
            'status' => 'AVAILABLE',
            'can_cook' => false,
        ]);
    }

    public function test_query_indexes_exist_in_the_migrated_schema(): void
    {
        $indexes = collect(Schema::getIndexes('utility_reports'))->pluck('name');

        $this->assertTrue($indexes->contains('utility_reports_sub_area_id_utility_type_reported_at_index'));
        $this->assertTrue($indexes->contains('utility_reports_utility_type_reported_at_index'));
        $this->assertTrue($indexes->contains('utility_reports_anonymous_reporter_id_reported_at_index'));
        $this->assertTrue($indexes->contains('utility_reports_duplicate_lookup_index'));
        $this->assertTrue($indexes->contains('utility_reports_latest_reporter_evidence_index'));
    }

    /** @return array{AnonymousReporter, Area, SubArea} */
    private function domainRecords(): array
    {
        $reporter = AnonymousReporter::query()->create(['token_hash' => str_repeat('a', 64)]);
        $area = Area::query()->create([
            'name' => 'Test Area',
            'slug' => 'test-area',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
        ]);
        $subArea = SubArea::query()->create([
            'area_id' => $area->id,
            'name' => 'Test Sub-area',
            'slug' => 'test-sub-area',
            'is_active' => true,
        ]);

        return [$reporter, $area, $subArea];
    }

    private function createReport(AnonymousReporter $reporter, Area $area, SubArea $subArea): UtilityReport
    {
        return UtilityReport::query()->create([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $area->id,
            'sub_area_id' => $subArea->id,
            'utility_type' => 'ELECTRICITY',
            'status' => 'UNAVAILABLE',
            'time_bucket' => 'UNKNOWN',
            'reported_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function insertRawReport(AnonymousReporter $reporter, Area $area, SubArea $subArea, array $overrides): void
    {
        DB::table('utility_reports')->insert([
            'anonymous_reporter_id' => $reporter->id,
            'area_id' => $area->id,
            'sub_area_id' => $subArea->id,
            'utility_type' => 'ELECTRICITY',
            'status' => 'UNAVAILABLE',
            'time_bucket' => 'UNKNOWN',
            'reported_at' => now(),
            'estimated_started_at' => null,
            'can_cook' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }
}
