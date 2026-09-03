<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LiveUtilityStatusResource;
use App\Models\UtilityLiveStatus;
use App\Services\LiveStatus\PublicLiveStatusFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const MAX_ITEMS = 12;

    public function __invoke(PublicLiveStatusFactory $statuses): JsonResponse
    {
        $freshAfter = CarbonImmutable::now('UTC')->subSeconds((int) config('reporting.aggregation.window_seconds'));
        $projections = UtilityLiveStatus::query()
            ->with(['subArea.area'])
            ->where('last_report_at', '>=', $freshAfter)
            ->where('estimated_status', '!=', LiveStatus::INSUFFICIENT_DATA)
            ->whereHas('subArea', fn ($subArea) => $subArea
                ->where('is_active', true)
                ->whereHas('area', fn ($area) => $area->where('is_active', true)))
            ->orderByDesc('last_report_at')
            ->limit(60)
            ->get();

        $items = $projections->map(fn (UtilityLiveStatus $projection) => $this->item($projection, $statuses));
        $struggling = $items->filter(fn (array $item) => $this->isIssue($item['utility_type'], $item['status']))
            ->sortBy([
                [fn (array $item) => -$this->severity($item['utility_type'], $item['status']), 'asc'],
                [fn (array $item) => -$this->confidenceRank($item['confidence']), 'asc'],
                [fn (array $item) => $item['last_report_at'], 'desc'],
            ])->take(self::MAX_ITEMS)->values();

        return response()->json([
            'data' => [
                'calculated_at' => CarbonImmutable::now('UTC')->format('Y-m-d\\TH:i:s.u\\Z'),
                // These counts cover fresh, non-insufficient locality projections only. They are not Dhaka-wide availability rates.
                'fresh_projection_counts' => [
                    'electricity' => $items->where('utility_type', UtilityType::ELECTRICITY->value)->count(),
                    'gas' => $items->where('utility_type', UtilityType::GAS->value)->count(),
                ],
                'struggling' => $struggling,
                'recent_changes' => $items->sortByDesc('last_report_at')->take(self::MAX_ITEMS)->values(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function item(UtilityLiveStatus $projection, PublicLiveStatusFactory $statuses): array
    {
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
            ...((new LiveUtilityStatusResource($statuses->make($projection, $projection->utility_type)))->resolve()),
        ];
    }

    private function isIssue(string $utility, string $status): bool
    {
        return in_array($status, UtilityType::from($utility)->issueStatusValues(), true);
    }

    private function severity(string $utility, string $status): int
    {
        return $utility === UtilityType::ELECTRICITY->value
            ? match ($status) {
                'UNAVAILABLE' => 4, 'UNSTABLE' => 3, 'MIXED' => 2, default => 0
            }
        : match ($status) {
            'UNAVAILABLE' => 4, 'VERY_LOW' => 3, 'LOW' => 2, 'MIXED' => 1, default => 0
        };
    }

    private function confidenceRank(?string $confidence): int
    {
        return match ($confidence) {
            ConfidenceLevel::HIGH->value => 3,
            ConfidenceLevel::MEDIUM->value => 2,
            ConfidenceLevel::LOW->value => 1,
            default => 0,
        };
    }
}
