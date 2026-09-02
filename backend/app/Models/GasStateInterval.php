<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\GasStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GasStateInterval extends Model
{
    protected $fillable = [
        'area_id', 'sub_area_id', 'status', 'started_at', 'ended_at', 'observed_until_at',
        'start_confidence_level', 'pending_status', 'pending_confidence_level',
        'pending_since', 'inference_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => GasStatus::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'observed_until_at' => 'immutable_datetime',
            'start_confidence_level' => ConfidenceLevel::class,
            'pending_status' => GasStatus::class,
            'pending_confidence_level' => ConfidenceLevel::class,
            'pending_since' => 'immutable_datetime',
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
