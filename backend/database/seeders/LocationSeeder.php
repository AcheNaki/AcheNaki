<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\SubArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = (new LocationDataset)->load();

        DB::transaction(function () use ($locations): void {
            $noncanonicalAreaIds = Area::query()
                ->whereNotIn('slug', array_column($locations, 'slug'))
                ->pluck('id');

            SubArea::query()
                ->whereIn('area_id', $noncanonicalAreaIds)
                ->update(['is_active' => false]);

            Area::query()
                ->whereIn('id', $noncanonicalAreaIds)
                ->update(['is_active' => false]);

            foreach ($locations as $areaIndex => $location) {
                $area = Area::query()->updateOrCreate(
                    ['slug' => $location['slug']],
                    [
                        'name' => $location['name'],
                        'name_bn' => $location['bn_name'] ?? null,
                        'city_corporation' => $location['city_corporation'],
                        'is_active' => true,
                        'sort_order' => ($areaIndex + 1) * 10,
                    ],
                );

                $subAreaSlugs = array_column($location['sub_areas'], 'slug');

                SubArea::query()
                    ->where('area_id', $area->id)
                    ->whereNotIn('slug', $subAreaSlugs)
                    ->update(['is_active' => false]);

                foreach ($location['sub_areas'] as $subAreaIndex => $subArea) {
                    SubArea::query()->updateOrCreate(
                        [
                            'area_id' => $area->id,
                            'slug' => $subArea['slug'],
                        ],
                        [
                            'name' => $subArea['name'],
                            'name_bn' => $subArea['bn_name'] ?? null,
                            'is_active' => true,
                            'sort_order' => ($subAreaIndex + 1) * 10,
                        ],
                    );
                }
            }
        });
    }
}
