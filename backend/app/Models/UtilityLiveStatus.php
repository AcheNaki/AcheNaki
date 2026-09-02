<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityLiveStatus extends Model
{
    protected $fillable = [
        'area_id',
        'sub_area_id',
        'utility_type',
        'estimated_status',
        'confidence_score',
        'confidence_level',
        'recent_report_count',
        'supporting_report_count',
        'contradicting_report_count',
        'status_since',
        'evidence_window_started_at',
        'last_report_at',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'utility_type' => UtilityType::class,
            'estimated_status' => LiveStatus::class,
            'confidence_level' => ConfidenceLevel::class,
            'confidence_score' => 'integer',
            'recent_report_count' => 'integer',
            'supporting_report_count' => 'integer',
            'contradicting_report_count' => 'integer',
            'status_since' => 'immutable_datetime',
            'evidence_window_started_at' => 'immutable_datetime',
            'last_report_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
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
