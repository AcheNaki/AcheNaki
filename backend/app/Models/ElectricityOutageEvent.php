<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricityOutageEvent extends Model
{
    protected $fillable = [
        'area_id', 'sub_area_id', 'lifecycle', 'started_at', 'first_supported_at',
        'confirmed_at', 'resolution_candidate_at', 'ended_at',
        'start_confidence_level', 'end_confidence_level', 'inference_version',
    ];

    protected function casts(): array
    {
        return [
            'lifecycle' => ElectricityOutageLifecycle::class,
            'started_at' => 'immutable_datetime',
            'first_supported_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'resolution_candidate_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'start_confidence_level' => ConfidenceLevel::class,
            'end_confidence_level' => ConfidenceLevel::class,
            'inference_version' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function subArea(): BelongsTo
    {
        return $this->belongsTo(SubArea::class);
    }
}
