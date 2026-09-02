<?php

namespace App\Services\UtilityHistory;

use App\Enums\UtilityType;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;

class UtilityHistoryReconciler
{
    public function __construct(
        private readonly ElectricityEventReconciler $electricity,
        private readonly GasIntervalReconciler $gas,
    ) {}

    public function reconcile(SubArea $subArea, UtilityLiveStatus $projection): void
    {
        if ($projection->utility_type === UtilityType::ELECTRICITY) {
            $this->electricity->reconcile($subArea, $projection);

            return;
        }

        $this->gas->reconcile($subArea, $projection);
    }
}
