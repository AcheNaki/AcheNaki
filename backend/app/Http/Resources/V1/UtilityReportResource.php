<?php

namespace App\Http\Resources\V1;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilityReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'utility_type' => $this->utility_type->value,
            'status' => $this->status,
            'area_id' => $this->area_id,
            'sub_area_id' => $this->sub_area_id,
            'reported_at' => $this->formatUtc($this->reported_at),
            'time_bucket' => $this->time_bucket->value,
            'estimated_started_at' => $this->formatUtc($this->estimated_started_at),
            'can_cook' => $this->when($this->utility_type->value === 'GAS', $this->can_cook),
        ];
    }

    private function formatUtc(?CarbonInterface $value): ?string
    {
        return $value?->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
