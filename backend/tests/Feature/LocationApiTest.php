<?php

namespace Tests\Feature;

use App\Enums\CityCorporation;
use App\Models\Area;
use App\Models\SubArea;
use Database\Seeders\LocationDataset;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locations = (new LocationDataset)->load();
        $this->seed(LocationSeeder::class);
    }

    public function test_area_listing_returns_only_the_public_contract(): void
    {
        $response = $this->getJson('/api/v1/areas');

        $response
            ->assertOk()
            ->assertJsonCount(count($this->locations), 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'city_corporation'],
                ],
            ])
            ->assertJsonPath('data.0.name', $this->locations[0]['name'])
            ->assertJsonPath('data.0.slug', $this->locations[0]['slug'])
            ->assertJsonPath('data.0.city_corporation', $this->locations[0]['city_corporation'])
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.is_active');
    }

    public function test_sub_area_listing_returns_only_children_of_the_selected_area(): void
    {
        $pallabi = Area::query()->where('slug', 'pallabi')->firstOrFail();
        $pallabiDataset = collect($this->locations)->firstWhere('slug', 'pallabi');

        $response = $this->getJson("/api/v1/areas/{$pallabi->id}/sub-areas");

        $response
            ->assertOk()
            ->assertJsonCount(count($pallabiDataset['sub_areas']), 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ])
            ->assertJsonPath('data.0.name', $pallabiDataset['sub_areas'][0]['name'])
            ->assertJsonMissing(['name' => 'North Badda'])
            ->assertJsonMissingPath('data.0.area_id')
            ->assertJsonMissingPath('data.0.created_at');
    }

    public function test_invalid_area_returns_the_api_not_found_response(): void
    {
        $this->getJson('/api/v1/areas/999999/sub-areas')
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The requested resource was not found.',
                ],
            ]);
    }

    public function test_inactive_areas_and_sub_areas_are_not_publicly_exposed(): void
    {
        $inactiveArea = Area::query()->create([
            'name' => 'Inactive Area',
            'slug' => 'inactive-area',
            'city_corporation' => CityCorporation::DNCC,
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $pallabi = Area::query()->where('slug', 'pallabi')->firstOrFail();
        SubArea::query()->create([
            'area_id' => $pallabi->id,
            'name' => 'Inactive Sub-area',
            'slug' => 'inactive-sub-area',
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $this->getJson('/api/v1/areas')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'inactive-area']);

        $this->getJson("/api/v1/areas/{$inactiveArea->id}/sub-areas")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->getJson("/api/v1/areas/{$pallabi->id}/sub-areas")
            ->assertOk()
            ->assertJsonMissing(['slug' => 'inactive-sub-area']);
    }

    public function test_location_search_validation_uses_the_public_api_error_shape(): void
    {
        $this->getJson('/api/v1/locations/search?q=a')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors('q', 'error.details');

        $this->getJson('/api/v1/locations/search?q=pallabi&limit=11')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors('limit', 'error.details');
    }
}
