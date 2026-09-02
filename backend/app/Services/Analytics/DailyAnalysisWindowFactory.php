<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

class DailyAnalysisWindowFactory
{
    public function make(?string $date = null, ?CarbonImmutable $now = null): DailyAnalysisWindow
    {
        $timezone = (string) config('reporting.analytics.timezone');
        $current = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $localNow = $current->setTimezone($timezone);
        $localDate = $date ?? $localNow->format('Y-m-d');
        $localStart = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $localDate,
            $timezone,
        )->startOfDay();
        $localEnd = $localStart->addDay();
        $partial = $localStart->isSameDay($localNow);

        return new DailyAnalysisWindow(
            $localDate,
            $timezone,
            $localStart->utc(),
            ($partial ? $localNow : $localEnd)->utc(),
            $partial,
        );
    }
}
