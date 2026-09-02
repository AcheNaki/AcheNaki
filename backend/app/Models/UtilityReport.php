<?php

namespace App\Models;

use App\Enums\TimeBucket;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class UtilityReport extends Model
{
    protected $fillable = [
        'anonymous_reporter_id',
        'area_id',
        'sub_area_id',
        'utility_type',
        'status',
        'time_bucket',
        'reported_at',
        'estimated_started_at',
        'can_cook',
    ];

    protected $hidden = ['anonymous_reporter_id'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Accepted utility reports are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Accepted utility reports are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'utility_type' => UtilityType::class,
            'time_bucket' => TimeBucket::class,
            'reported_at' => 'immutable_datetime',
            'estimated_started_at' => 'immutable_datetime',
            'can_cook' => 'boolean',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(AnonymousReporter::class, 'anonymous_reporter_id');
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
