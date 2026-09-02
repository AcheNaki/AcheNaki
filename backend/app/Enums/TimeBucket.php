<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum TimeBucket: string
{
    case NOW = 'NOW';
    case MIN_5 = 'MIN_5';
    case MIN_15 = 'MIN_15';
    case MIN_30 = 'MIN_30';
    case HOUR_1 = 'HOUR_1';
    case HOUR_2 = 'HOUR_2';
    case OVER_2_HOURS = 'OVER_2_HOURS';
    case UNKNOWN = 'UNKNOWN';

    public function estimatedStartedAt(CarbonImmutable $reportedAt): ?CarbonImmutable
    {
        return match ($this) {
            self::NOW => $reportedAt,
            self::MIN_5 => $reportedAt->subMinutes(5),
            self::MIN_15 => $reportedAt->subMinutes(15),
            self::MIN_30 => $reportedAt->subMinutes(30),
            self::HOUR_1 => $reportedAt->subHour(),
            self::HOUR_2 => $reportedAt->subHours(2),
            self::OVER_2_HOURS, self::UNKNOWN => null,
        };
    }
}
