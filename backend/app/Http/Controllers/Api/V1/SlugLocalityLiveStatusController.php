<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LiveStatus\PublicLocalityStatusFactory;
use App\Services\Location\ActiveLocalityResolver;
use Illuminate\Http\JsonResponse;

class SlugLocalityLiveStatusController extends Controller
{
    public function __invoke(
        string $areaSlug,
        string $subAreaSlug,
        ActiveLocalityResolver $localities,
        PublicLocalityStatusFactory $statuses,
    ): JsonResponse {
        return response()->json([
            'data' => $statuses->make($localities->resolve($areaSlug, $subAreaSlug)),
        ]);
    }
}
