<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubArea extends Model
{
    protected $fillable = [
        'area_id',
        'name',
        'name_bn',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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
