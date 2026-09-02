<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Models\Area;
use App\Models\SubArea;
use Database\Seeders\LocationDataset;
use Database\Seeders\LocationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_and_sub_area_relationships_work(): void
    {
        $this->seed(LocationSeeder::class);

        $pallabi = Area::query()->where('slug', 'pallabi')->firstOrFail();
        $palashNagar = SubArea::query()->where('slug', 'palash-nagar')->firstOrFail();

        $this->assertTrue($pallabi->subAreas->contains($palashNagar));
        $this->assertTrue($palashNagar->area->is($pallabi));
    }

    public function test_duplicate_sub_area_slug_is_rejected_within_the_same_parent(): void
    {
        $area = $this->createArea('Test Area', 'test-area');

        SubArea::query()->create([
            'area_id' => $area->id,
            'name' => 'First Locality',
            'slug' => 'shared-slug',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->expectException(QueryException::class);

        SubArea::query()->create([
            'area_id' => $area->id,
            'name' => 'Second Locality',
            'slug' => 'shared-slug',
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    public function test_same_sub_area_slug_is_allowed_under_different_parents(): void
    {
        $firstArea = $this->createArea('First Area', 'first-area');
        $secondArea = $this->createArea('Second Area', 'second-area');

        foreach ([$firstArea, $secondArea] as $area) {
            SubArea::query()->create([
                'area_id' => $area->id,
                'name' => 'Shared Name',
                'slug' => 'shared-slug',
                'is_active' => true,
                'sort_order' => 10,
            ]);
        }

        $this->assertDatabaseCount('sub_areas', 2);
    }

    public function test_area_with_sub_areas_cannot_be_deleted(): void
    {
        $area = $this->createArea('Protected Area', 'protected-area');
        SubArea::query()->create([
            'area_id' => $area->id,
            'name' => 'Protected Locality',
            'slug' => 'protected-locality',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->expectException(QueryException::class);

        $area->delete();
    }

    public function test_location_seeder_is_idempotent_and_preserves_ids(): void
    {
        $locations = (new LocationDataset)->load();
        $expectedSubAreaCount = collect($locations)->sum(
            fn (array $location): int => count($location['sub_areas']),
        );

        $this->seed(LocationSeeder::class);

        $areaIds = Area::query()->orderBy('slug')->pluck('id', 'slug');
        $subAreaIds = SubArea::query()->orderBy('slug')->orderBy('area_id')->pluck('id')->all();

        $this->seed(LocationSeeder::class);

        $this->assertDatabaseCount('areas', count($locations));
        $this->assertDatabaseCount('sub_areas', $expectedSubAreaCount);
        $this->assertSame($areaIds->all(), Area::query()->orderBy('slug')->pluck('id', 'slug')->all());
        $this->assertSame(
            $subAreaIds,
            SubArea::query()->orderBy('slug')->orderBy('area_id')->pluck('id')->all(),
        );
    }

    public function test_seeder_reuses_existing_canonical_slugs_and_preserves_their_ids(): void
    {
        $pallabi = $this->createArea('Old Pallabi Name', 'pallabi');
        $pallabiExtension = SubArea::query()->create([
            'area_id' => $pallabi->id,
            'name' => 'Old Extension Name',
            'slug' => 'pallabi-extension',
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $this->seed(LocationSeeder::class);

        $this->assertDatabaseHas('areas', [
            'id' => $pallabi->id,
            'name' => 'Pallabi',
            'slug' => 'pallabi',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sub_areas', [
            'id' => $pallabiExtension->id,
            'name' => 'Pallabi Extension',
            'slug' => 'pallabi-extension',
            'is_active' => true,
        ]);
    }

    public function test_seeder_deactivates_noncanonical_records_without_deleting_them(): void
    {
        $extraArea = $this->createArea('Retired Area', 'retired-area');
        $extraSubArea = SubArea::query()->create([
            'area_id' => $extraArea->id,
            'name' => 'Retired Locality',
            'slug' => 'retired-locality',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $pallabi = $this->createArea('Pallabi', 'pallabi');
        $obsoletePallabiLocality = SubArea::query()->create([
            'area_id' => $pallabi->id,
            'name' => 'Obsolete Pallabi Locality',
            'slug' => 'obsolete-pallabi-locality',
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->seed(LocationSeeder::class);

        $this->assertDatabaseHas('areas', [
            'id' => $extraArea->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('sub_areas', [
            'id' => $extraSubArea->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('sub_areas', [
            'id' => $obsoletePallabiLocality->id,
            'is_active' => false,
        ]);
    }

    private function createArea(string $name, string $slug): Area
    {
        return Area::query()->create([
            'name' => $name,
            'slug' => $slug,
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }
}
