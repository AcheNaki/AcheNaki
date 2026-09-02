<?php

namespace App\Services\UtilityHistory;

use App\Enums\UtilityType;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusAggregation;
use App\Services\LiveStatus\LiveStatusAggregator;
use Carbon\CarbonImmutable;

class HistoricalLiveStatusReplay
{
    public function __construct(private readonly LiveStatusAggregator $aggregator) {}

    /** @param callable(UtilityLiveStatus): void $consume */
    public function replay(
        SubArea $subArea,
        UtilityType $utility,
        callable $consume,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): void {
        $queryFrom = $from?->subSeconds((int) config('reporting.aggregation.window_seconds'));
        $reports = UtilityReport::query()
            ->where('sub_area_id', $subArea->id)
            ->where('utility_type', $utility->value)
            ->when($queryFrom !== null, fn ($query) => $query->where('reported_at', '>=', $queryFrom))
            ->when($until !== null, fn ($query) => $query->where('reported_at', '<', $until))
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get();
        $window = collect();

        foreach ($reports as $report) {
            $at = $report->reported_at->toImmutable();
            $windowStart = $at->subSeconds((int) config('reporting.aggregation.window_seconds'));
            $window = $window
                ->filter(fn (UtilityReport $candidate): bool => $candidate->reported_at->greaterThanOrEqualTo($windowStart))
                ->push($report)
                ->values();

            if ($from !== null && $at->lessThan($from)) {
                continue;
            }

            $aggregation = $this->aggregator->aggregate($window, $utility, $at);
            $consume($this->projection($subArea, $utility, $aggregation));
        }
    }

    private function projection(
        SubArea $subArea,
        UtilityType $utility,
        LiveStatusAggregation $aggregation,
    ): UtilityLiveStatus {
        return (new UtilityLiveStatus)->forceFill([
            'area_id' => $subArea->area_id,
            'sub_area_id' => $subArea->id,
            'utility_type' => $utility,
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
        ]);
    }
}
