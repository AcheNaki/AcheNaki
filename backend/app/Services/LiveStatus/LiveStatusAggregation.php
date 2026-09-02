<?php

namespace App\Services\LiveStatus;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use Carbon\CarbonImmutable;

readonly class LiveStatusAggregation
{
    public function __construct(
        public LiveStatus $status,
        public int $confidenceScore,
        public ?ConfidenceLevel $confidenceLevel,
        public int $recentReportCount,
        public int $supportingReportCount,
        public int $contradictingReportCount,
        public ?CarbonImmutable $statusSince,
        public CarbonImmutable $evidenceWindowStartedAt,
        public ?CarbonImmutable $lastReportAt,
        public CarbonImmutable $calculatedAt,
    ) {}
}
