<?php

namespace App\Services\LiveStatus;

use App\Enums\UtilityType;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use App\Services\UtilityHistory\UtilityHistoryReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LiveStatusProjectionService
{
    public function __construct(
        private readonly LiveStatusAggregator $aggregator,
        private readonly UtilityHistoryReconciler $history,
    ) {}

    public function refresh(
        SubArea $subArea,
        UtilityType $utilityType,
        ?CarbonImmutable $calculatedAt = null,
        bool $reconcileHistory = true,
    ): UtilityLiveStatus {
        $now = $calculatedAt ?? CarbonImmutable::now('UTC');
        $windowStart = $now->subSeconds((int) config('reporting.aggregation.window_seconds'));

        return DB::transaction(function () use ($subArea, $utilityType, $now, $windowStart, $reconcileHistory): UtilityLiveStatus {
            $lockedSubArea = SubArea::query()->lockForUpdate()->findOrFail($subArea->id);
            $reports = UtilityReport::query()
                ->where('sub_area_id', $lockedSubArea->id)
                ->where('utility_type', $utilityType->value)
                ->whereBetween('reported_at', [$windowStart, $now])
                ->orderByDesc('reported_at')
                ->orderByDesc('id')
                ->get();
            $aggregation = $this->aggregator->aggregate($reports, $utilityType, $now);

            $projection = UtilityLiveStatus::query()->updateOrCreate(
                [
                    'sub_area_id' => $lockedSubArea->id,
                    'utility_type' => $utilityType,
                ],
                [
                    'area_id' => $lockedSubArea->area_id,
                    'estimated_status' => $aggregation->status,
                    'confidence_score' => $aggregation->confidenceScore,
                    'confidence_level' => $aggregation->confidenceLevel,
                    'recent_report_count' => $aggregation->recentReportCount,
                    'supporting_report_count' => $aggregation->supportingReportCount,
                    'contradicting_report_count' => $aggregation->contradictingReportCount,
                    'status_since' => $aggregation->statusSince,
                    'evidence_window_started_at' => $aggregation->evidenceWindowStartedAt,
                    'last_report_at' => $aggregation->lastReportAt,
                    'calculated_at' => $aggregation->calculatedAt,
                ],
            );

            if ($reconcileHistory) {
                $this->history->reconcile($lockedSubArea, $projection);
            }

            return $projection;
        }, 3);
    }

    public function refreshByIds(
        int $subAreaId,
        UtilityType $utilityType,
        ?CarbonImmutable $calculatedAt = null,
        bool $reconcileHistory = true,
    ): UtilityLiveStatus {
        return $this->refresh(
            SubArea::query()->findOrFail($subAreaId),
            $utilityType,
            $calculatedAt,
            $reconcileHistory,
        );
    }
}
