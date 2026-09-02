<?php

namespace App\Services\LiveStatus;

use App\Enums\UtilityType;
use App\Http\Resources\V1\LiveUtilityStatusResource;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use Illuminate\Support\Collection;

class PublicLocalityStatusFactory
{
    public function __construct(private readonly PublicLiveStatusFactory $statuses) {}

    /** @param Collection<int, UtilityLiveStatus>|null $projections */
    public function make(SubArea $locality, ?Collection $projections = null): array
    {
        $locality->loadMissing('area');
        $byUtility = ($projections ?? UtilityLiveStatus::query()
            ->where('sub_area_id', $locality->id)
            ->whereIn('utility_type', array_column(UtilityType::cases(), 'value'))
            ->get())->keyBy(fn (UtilityLiveStatus $projection) => $projection->utility_type->value);

        return [
            'sub_area' => [
                'id' => $locality->id,
                'name' => $locality->name,
                'slug' => $locality->slug,
                'area' => [
                    'id' => $locality->area->id,
                    'name' => $locality->area->name,
                    'slug' => $locality->area->slug,
                ],
            ],
            'electricity' => (new LiveUtilityStatusResource($this->statuses->make(
                $byUtility->get(UtilityType::ELECTRICITY->value),
                UtilityType::ELECTRICITY,
            )))->resolve(),
            'gas' => (new LiveUtilityStatusResource($this->statuses->make(
                $byUtility->get(UtilityType::GAS->value),
                UtilityType::GAS,
            )))->resolve(),
        ];
    }
}
