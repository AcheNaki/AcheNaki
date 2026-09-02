<?php

namespace App\Services\LiveStatus;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use Carbon\CarbonImmutable;

readonly class PublicLiveStatus
{
    public function __construct(
        public UtilityType $utilityType,
        public LiveStatus $status,
        public ?ConfidenceLevel $confidenceLevel,
        public ?CarbonImmutable $statusSince,
        public int $recentReportCount,
        public int $supportingReportCount,
        public int $contradictingReportCount,
        public ?CarbonImmutable $lastReportAt,
    ) {}
}
