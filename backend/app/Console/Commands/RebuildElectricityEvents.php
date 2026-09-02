<?php

namespace App\Console\Commands;

use App\Enums\UtilityType;
use App\Models\ElectricityOutageEvent;
use App\Models\SubArea;
use App\Services\UtilityHistory\ElectricityEventReconciler;
use App\Services\UtilityHistory\HistoricalLiveStatusReplay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildElectricityEvents extends Command
{
    protected $signature = 'electricity-events:rebuild {--sub-area= : Rebuild one numeric sub-area ID}';

    protected $description = 'Rebuild inferred electricity outage events from authoritative reports';

    public function handle(HistoricalLiveStatusReplay $replay, ElectricityEventReconciler $events): int
    {
        $subAreaId = $this->validatedSubAreaId();
        if ($subAreaId === false) {
            return self::FAILURE;
        }

        $subAreas = SubArea::query()
            ->when($subAreaId !== null, fn ($query) => $query->whereKey($subAreaId))
            ->whereHas('utilityReports', fn ($query) => $query->where('utility_type', UtilityType::ELECTRICITY->value))
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($subAreas, $replay, $events, $subAreaId): void {
            ElectricityOutageEvent::query()
                ->when($subAreaId !== null, fn ($query) => $query->where('sub_area_id', $subAreaId))
                ->delete();

            foreach ($subAreas as $subArea) {
                SubArea::query()->whereKey($subArea->id)->lockForUpdate()->firstOrFail();
                $replay->replay(
                    $subArea,
                    UtilityType::ELECTRICITY,
                    fn ($projection) => $events->reconcile($subArea, $projection),
                );
            }
        });

        $this->info("Rebuilt electricity events for {$subAreas->count()} sub-area(s). Event IDs may change; raw reports were unchanged.");

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
