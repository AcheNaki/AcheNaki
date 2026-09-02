<?php

namespace App\Console\Commands;

use App\Enums\UtilityType;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use App\Services\UtilityHistory\GasIntervalReconciler;
use App\Services\UtilityHistory\HistoricalLiveStatusReplay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildGasIntervals extends Command
{
    protected $signature = 'gas-intervals:rebuild {--sub-area= : Rebuild one numeric sub-area ID}';

    protected $description = 'Rebuild inferred gas state intervals from authoritative reports';

    public function handle(HistoricalLiveStatusReplay $replay, GasIntervalReconciler $intervals): int
    {
        $subAreaId = $this->validatedSubAreaId();
        if ($subAreaId === false) {
            return self::FAILURE;
        }

        $subAreas = SubArea::query()
            ->when($subAreaId !== null, fn ($query) => $query->whereKey($subAreaId))
            ->whereHas('utilityReports', fn ($query) => $query->where('utility_type', UtilityType::GAS->value))
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($subAreas, $replay, $intervals, $subAreaId): void {
            GasStateInterval::query()
                ->when($subAreaId !== null, fn ($query) => $query->where('sub_area_id', $subAreaId))
                ->delete();

            foreach ($subAreas as $subArea) {
                SubArea::query()->whereKey($subArea->id)->lockForUpdate()->firstOrFail();
                $replay->replay(
                    $subArea,
                    UtilityType::GAS,
                    fn ($projection) => $intervals->reconcile($subArea, $projection),
                );
            }
        });

        $this->info("Rebuilt gas intervals for {$subAreas->count()} sub-area(s). Interval IDs may change; raw reports were unchanged.");

        return self::SUCCESS;
    }

    private function validatedSubAreaId(): int|false|null
    {
        $value = $this->option('sub-area');
        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || ! SubArea::query()->whereKey((int) $value)->exists()) {
            $this->error('The --sub-area option must identify an existing sub-area.');

            return false;
        }

        return (int) $value;
    }
}
