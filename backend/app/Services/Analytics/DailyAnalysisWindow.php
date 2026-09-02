<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

readonly class DailyAnalysisWindow
{
    public function __construct(
        public string $date,
        public string $timezone,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $partial,
    ) {}

    public function durationSeconds(): int
    {
        return (int) $this->startsAt->diffInSeconds($this->endsAt);
    }
}
