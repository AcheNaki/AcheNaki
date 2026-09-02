<?php

namespace App\Services\LiveStatus;

use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Models\UtilityLiveStatus;
use Carbon\CarbonImmutable;

class PublicLiveStatusFactory
{
    public function make(
        ?UtilityLiveStatus $projection,
        UtilityType $utilityType,
        ?CarbonImmutable $requestedAt = null,
    ): PublicLiveStatus {
        $now = $requestedAt ?? CarbonImmutable::now('UTC');
        $freshAfter = $now->subSeconds((int) config('reporting.aggregation.window_seconds'));

        if ($projection === null
            || $projection->last_report_at === null
            || $projection->last_report_at->lt($freshAfter)) {
            return new PublicLiveStatus(
                $utilityType,
                LiveStatus::INSUFFICIENT_DATA,
                null,
                null,
                0,
                0,
                0,
                null,
            );
        }

        return new PublicLiveStatus(
            $utilityType,
            $projection->estimated_status,
            $projection->confidence_level,
            $projection->status_since?->toImmutable(),
            $projection->recent_report_count,
            $projection->supporting_report_count,
            $projection->contradicting_report_count,
            $projection->last_report_at->toImmutable(),
        );
    }
}
