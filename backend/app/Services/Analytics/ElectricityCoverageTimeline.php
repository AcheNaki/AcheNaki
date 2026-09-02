<?php

namespace App\Services\Analytics;

use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Services\UtilityHistory\DurableTransitionPolicy;
use App\Services\UtilityHistory\HistoricalLiveStatusReplay;

class ElectricityCoverageTimeline
{
    public function __construct(
        private readonly HistoricalLiveStatusReplay $replay,
        private readonly DurableTransitionPolicy $policy,
    ) {}

    /** @return list<ObservedStateSegment> */
    public function build(SubArea $subArea, DailyAnalysisWindow $window): array
    {
        $segments = [];
        $current = null;
        $pendingStatus = null;
        $pendingSince = null;
        $lastObservedUntil = null;
        $replayFrom = $window->startsAt->subSeconds((int) config('reporting.aggregation.window_seconds'));

        $this->replay->replay(
            $subArea,
            UtilityType::ELECTRICITY,
            function (UtilityLiveStatus $projection) use (&$segments, &$current, &$pendingStatus, &$pendingSince, &$lastObservedUntil): void {
                $now = $projection->calculated_at->toImmutable();
                $status = $projection->estimated_status;
                $isClassified = in_array($status, [LiveStatus::AVAILABLE, LiveStatus::UNAVAILABLE, LiveStatus::UNSTABLE], true);

                if (! $isClassified) {
                    $pendingStatus = null;
                    $pendingSince = null;

                    return;
                }

                if (! $this->policy->permits($projection->confidence_level)) {
                    return;
                }

                if ($current !== null && $now->greaterThan($current['observed_until'])) {
                    $segments[] = new ObservedStateSegment(
                        $current['status'], $current['started_at'], $current['observed_until'], $current['confidence'],
                    );
                    $lastObservedUntil = $current['observed_until'];
                    $current = null;
                    $pendingStatus = null;
                    $pendingSince = null;
                }

                $observedUntil = ($projection->last_report_at?->toImmutable() ?? $now)
                    ->addSeconds((int) config('reporting.aggregation.window_seconds'));

                if ($current === null) {
                    $estimatedStart = $projection->status_since?->toImmutable();
                    $startedAt = $estimatedStart !== null && $estimatedStart->lessThanOrEqualTo($now)
                        ? $estimatedStart
                        : $now;
                    if ($lastObservedUntil !== null && $startedAt->lessThan($lastObservedUntil)) {
                        $startedAt = $lastObservedUntil;
                    }
                    $current = [
                        'status' => $status,
                        'started_at' => $startedAt,
                        'observed_until' => $observedUntil,
                        'confidence' => $projection->confidence_level,
                    ];

                    return;
                }

                if ($current['status'] === $status) {
                    if ($observedUntil->greaterThan($current['observed_until'])) {
                        $current['observed_until'] = $observedUntil;
                    }
                    $pendingStatus = null;
                    $pendingSince = null;

                    return;
                }

                if ($pendingStatus !== $status) {
                    $pendingStatus = $status;
                    $pendingSince = $now;

                    return;
                }

                if (! $this->policy->isStableSince($pendingSince, $now)) {
                    return;
                }

                $segments[] = new ObservedStateSegment(
                    $current['status'],
                    $current['started_at'],
                    $pendingSince->lessThan($current['observed_until']) ? $pendingSince : $current['observed_until'],
                    $current['confidence'],
                );
                $current = [
                    'status' => $status,
                    'started_at' => $pendingSince,
                    'observed_until' => $observedUntil,
                    'confidence' => $projection->confidence_level,
                ];
                $pendingStatus = null;
                $pendingSince = null;
            },
            $replayFrom,
            $window->endsAt,
        );

        if ($current !== null) {
            $segments[] = new ObservedStateSegment(
                $current['status'], $current['started_at'], $current['observed_until'], $current['confidence'],
            );
        }

        return $segments;
    }
}
