<?php

namespace App\Services\UtilityHistory;

use App\Enums\ConfidenceLevel;

class DurableTransitionPolicy
{
    public function permits(?ConfidenceLevel $confidence): bool
    {
        if ($confidence === null) {
            return false;
        }

        $minimum = ConfidenceLevel::from(config('reporting.events.minimum_confidence'));
        $rank = [
            ConfidenceLevel::LOW->value => 1,
            ConfidenceLevel::MEDIUM->value => 2,
            ConfidenceLevel::HIGH->value => 3,
        ];

        return $rank[$confidence->value] >= $rank[$minimum->value];
    }

    public function isStableSince(\DateTimeInterface $candidateSince, \DateTimeInterface $now): bool
    {
        return $now->getTimestamp() - $candidateSince->getTimestamp()
            >= (int) config('reporting.events.stabilization_seconds');
    }

    public function isFresh(\DateTimeInterface $candidateSince, \DateTimeInterface $now): bool
    {
        return $now->getTimestamp() - $candidateSince->getTimestamp()
            <= (int) config('reporting.aggregation.window_seconds');
    }
}
