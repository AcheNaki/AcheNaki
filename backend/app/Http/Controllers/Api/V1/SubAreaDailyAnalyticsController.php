<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowDailyAnalyticsRequest;
use App\Models\SubArea;
use App\Services\Analytics\DailyAnalysisWindowFactory;
use App\Services\Analytics\DailyUtilityAnalyticsService;
use Illuminate\Http\JsonResponse;

class SubAreaDailyAnalyticsController extends Controller
{
    public function __invoke(
        int $subArea,
        ShowDailyAnalyticsRequest $request,
        DailyAnalysisWindowFactory $windows,
        DailyUtilityAnalyticsService $analytics,
    ): JsonResponse {
        $locality = SubArea::query()
            ->whereKey($subArea)
            ->where('is_active', true)
            ->whereHas('area', fn ($query) => $query->where('is_active', true))
            ->with('area')
            ->firstOrFail();
        $window = $windows->make($request->validated('date'));

        return response()->json(['data' => $analytics->summarize($locality, $window)]);
    }
}
