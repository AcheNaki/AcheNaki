<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubArea;
use App\Services\LiveStatus\PublicLocalityStatusFactory;
use Illuminate\Http\JsonResponse;

class SubAreaLiveStatusController extends Controller
{
    public function __invoke(int $subArea, PublicLocalityStatusFactory $statuses): JsonResponse
    {
        $locality = SubArea::query()
            ->whereKey($subArea)
            ->where('is_active', true)
            ->whereHas('area', fn ($query) => $query->where('is_active', true))
            ->with('area')
            ->firstOrFail();

        return response()->json(['data' => $statuses->make($locality)]);
    }
}
