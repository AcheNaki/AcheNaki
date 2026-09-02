<?php

namespace App\Console\Commands;

use App\Enums\UtilityType;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RebuildUtilityStatuses extends Command
{
    protected $signature = 'utility-status:rebuild
        {--sub-area= : Rebuild only one numeric sub-area ID}
        {--utility= : Rebuild only ELECTRICITY or GAS}';

    protected $description = 'Rebuild live utility status projections from authoritative reports';

    public function handle(LiveStatusProjectionService $projections): int
    {
        $subAreaId = $this->option('sub-area');
        if ($subAreaId !== null && filter_var($subAreaId, FILTER_VALIDATE_INT) === false) {
            $this->error('The --sub-area option must be a numeric ID.');

            return self::FAILURE;
        }

        $subArea = $subAreaId !== null ? SubArea::query()->find((int) $subAreaId) : null;
        if ($subAreaId !== null && $subArea === null) {
            $this->error('The requested sub-area does not exist.');

            return self::FAILURE;
        }

        $utilityOption = $this->option('utility');
        $utility = is_string($utilityOption) ? UtilityType::tryFrom(strtoupper($utilityOption)) : null;
        if ($utilityOption !== null && $utility === null) {
            $this->error('The --utility option must be ELECTRICITY or GAS.');

            return self::FAILURE;
        }

        $pairs = $this->reportPairs($subArea?->id, $utility);
        if ($subArea !== null && $utility !== null && $pairs->isEmpty()) {
            $pairs->push((object) [
                'sub_area_id' => $subArea->id,
                'utility_type' => $utility->value,
            ]);
        }

        $calculatedAt = CarbonImmutable::now('UTC');
        DB::transaction(function () use ($pairs, $projections, $subArea, $utility, $calculatedAt): void {
            UtilityLiveStatus::query()
                ->when($subArea !== null, fn ($query) => $query->where('sub_area_id', $subArea->id))
                ->when($utility !== null, fn ($query) => $query->where('utility_type', $utility->value))
                ->delete();

            foreach ($pairs as $pair) {
                $projections->refreshByIds(
                    (int) $pair->sub_area_id,
                    UtilityType::from($pair->utility_type),
                    $calculatedAt,
                    false,
                );
            }
        });

        $this->info("Rebuilt {$pairs->count()} live utility status projection(s). Raw reports were unchanged.");

        return self::SUCCESS;
    }

    /** @return Collection<int, object{sub_area_id: int, utility_type: string}> */
    private function reportPairs(?int $subAreaId, ?UtilityType $utility): Collection
    {
        return UtilityReport::query()
            ->select(['sub_area_id', 'utility_type'])
            ->when($subAreaId !== null, fn ($query) => $query->where('sub_area_id', $subAreaId))
            ->when($utility !== null, fn ($query) => $query->where('utility_type', $utility->value))
            ->distinct()
            ->orderBy('sub_area_id')
            ->orderBy('utility_type')
            ->toBase()
            ->get();
    }
}
