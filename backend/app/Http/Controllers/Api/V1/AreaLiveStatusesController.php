<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\UtilityLiveStatus;
use App\Services\LiveStatus\PublicLocalityStatusFactory;
use Illuminate\Http\JsonResponse;

class AreaLiveStatusesController extends Controller
{
    public function __invoke(string $areaSlug, PublicLocalityStatusFactory $statuses): JsonResponse
    {
        $area = Area::query()
            ->where('slug', $areaSlug)
            ->where('is_active', true)
            ->with(['subAreas' => fn ($query) => $query
                ->where('is_active', true)
                ->with('area')
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->firstOrFail();
        $subAreaIds = $area->subAreas->pluck('id');
        $projections = UtilityLiveStatus::query()
            ->whereIn('sub_area_id', $subAreaIds)
            ->get()
            ->groupBy('sub_area_id');

        return response()->json([
            'data' => [
                'area' => ['id' => $area->id, 'name' => $area->name, 'slug' => $area->slug],
                'localities' => $area->subAreas->map(fn ($subArea) => $statuses->make(
                    $subArea,
                    $projections->get($subArea->id, collect()),
                ))->values(),
            ],
        ]);
    }
}
