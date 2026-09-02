<?php

namespace App\Http\Resources\V1;

use App\Services\LiveStatus\PublicLiveStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PublicLiveStatus */
class LiveUtilityStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'confidence' => $this->confidenceLevel?->value,
            'status_since' => $this->formatUtc($this->statusSince),
            'recent_reports' => $this->recentReportCount,
            'supporting_reports' => $this->supportingReportCount,
            'contradicting_reports' => $this->contradictingReportCount,
            'last_report_at' => $this->formatUtc($this->lastReportAt),
        ];
    }

    private function formatUtc(?CarbonInterface $value): ?string
    {
        return $value?->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
