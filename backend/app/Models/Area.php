<?php

namespace App\Models;

use App\Enums\CityCorporation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = [
        'name',
        'name_bn',
        'slug',
        'city_corporation',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'city_corporation' => CityCorporation::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subAreas(): HasMany
    {
        return $this->hasMany(SubArea::class);
    }

    public function utilityReports(): HasMany
    {
        return $this->hasMany(UtilityReport::class);
    }

    public function utilityLiveStatuses(): HasMany
    {
        return $this->hasMany(UtilityLiveStatus::class);
    }

    public function electricityOutageEvents(): HasMany
    {
        return $this->hasMany(ElectricityOutageEvent::class);
    }

    public function gasStateIntervals(): HasMany
    {
        return $this->hasMany(GasStateInterval::class);
    }
}
