<?php

namespace App\Services\LiveStatus;

use Carbon\CarbonImmutable;

/**
 * City-wide aggregate counts for the public "Dhaka right now" card. Every field is a count of
 * localities or accepted reports; nothing here identifies a reporter or a household.
 */
readonly class LiveSummary
{
    public function __construct(
        public int $windowMinutes,
        public int $reports,
        public int $localitiesUpdated,
        public int $electricityIssueLocalities,
        public int $gasIssueLocalities,
        public int $currentlyStrugglingLocalities,
        public CarbonImmutable $calculatedAt,
    ) {}
}
