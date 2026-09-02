<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLiveStatusesRequest;
use App\Http\Resources\V1\LiveUtilityStatusResource;
use App\Models\UtilityLiveStatus;
use App\Services\LiveStatus\PublicLiveStatusFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class LiveStatusListController extends Controller
{
    public function __invoke(
        ListLiveStatusesRequest $request,
        PublicLiveStatusFactory $statuses,
    ): JsonResponse {
        $input = $request->validated();
        $freshAfter = CarbonImmutable::now('UTC')
            ->subSeconds((int) config('reporting.aggregation.window_seconds'));
        $query = UtilityLiveStatus::query()
            ->with(['subArea.area'])
            ->where('last_report_at', '>=', $freshAfter)
            ->where('estimated_status', '!=', 'INSUFFICIENT_DATA')
            ->whereHas('subArea', fn ($subArea) => $subArea
                ->where('is_active', true)
                ->whereHas('area', fn ($area) => $area->where('is_active', true)))
            ->when(isset($input['utility']), fn ($builder) => $builder->where('utility_type', $input['utility']))
            ->when(isset($input['status']), fn ($builder) => $builder->where('estimated_status', $input['status']))
            ->orderByDesc('last_report_at')
            ->orderBy('sub_area_id')
            ->limit($input['limit'] ?? config('reporting.aggregation.listing_default_limit'));

        $data = $query->get()->map(function (UtilityLiveStatus $projection) use ($statuses): array {
            $snapshot = $statuses->make($projection, $projection->utility_type);

            return [
                'sub_area' => [
                    'id' => $projection->subArea->id,
                    'name' => $projection->subArea->name,
                    'slug' => $projection->subArea->slug,
                    'area' => [
                        'id' => $projection->subArea->area->id,
                        'name' => $projection->subArea->area->name,
                        'slug' => $projection->subArea->area->slug,
                    ],
                ],
                'utility_type' => $projection->utility_type->value,
                ...((new LiveUtilityStatusResource($snapshot))->resolve()),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
