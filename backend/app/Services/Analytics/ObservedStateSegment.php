<?php

namespace App\Services\Analytics;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use Carbon\CarbonImmutable;

readonly class ObservedStateSegment
{
    public function __construct(
        public LiveStatus $status,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $observedUntilAt,
        public ConfidenceLevel $confidenceLevel,
    ) {}
}
