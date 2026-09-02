<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowDailyAnalyticsRequest;
use App\Services\Analytics\DailyAnalysisWindowFactory;
use App\Services\Analytics\DailyUtilityAnalyticsService;
use App\Services\Location\ActiveLocalityResolver;
use Illuminate\Http\JsonResponse;

class SlugLocalityDailyAnalyticsController extends Controller
{
    public function __invoke(
        string $areaSlug,
        string $subAreaSlug,
        ShowDailyAnalyticsRequest $request,
        ActiveLocalityResolver $localities,
        DailyAnalysisWindowFactory $windows,
        DailyUtilityAnalyticsService $analytics,
    ): JsonResponse {
        $locality = $localities->resolve($areaSlug, $subAreaSlug);

        return response()->json([
            'data' => $analytics->summarize($locality, $windows->make($request->validated('date'))),
        ]);
    }
}
