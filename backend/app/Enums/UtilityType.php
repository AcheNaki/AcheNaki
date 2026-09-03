<?php

namespace App\Enums;

enum UtilityType: string
{
    case ELECTRICITY = 'ELECTRICITY';
    case GAS = 'GAS';

    /**
     * Projection statuses that represent an actual current problem for this utility.
     * `AVAILABLE`/`NORMAL` and `INSUFFICIENT_DATA` are deliberately absent: the absence of
     * evidence is not an issue signal. Every public "currently struggling" surface reads
     * this single definition so the counts and the cards can never disagree.
     *
     * @return list<string>
     */
    public function issueStatusValues(): array
    {
        return array_column(match ($this) {
            self::ELECTRICITY => [LiveStatus::UNAVAILABLE, LiveStatus::UNSTABLE, LiveStatus::MIXED],
            self::GAS => [LiveStatus::LOW, LiveStatus::VERY_LOW, LiveStatus::UNAVAILABLE, LiveStatus::MIXED],
        }, 'value');
    }
}
