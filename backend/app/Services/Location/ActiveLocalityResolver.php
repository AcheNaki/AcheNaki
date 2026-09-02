<?php

namespace App\Services\Location;

use App\Models\SubArea;

class ActiveLocalityResolver
{
    public function resolve(string $areaSlug, string $subAreaSlug): SubArea
    {
        return SubArea::query()
            ->where('slug', $subAreaSlug)
            ->where('is_active', true)
            ->whereHas('area', fn ($query) => $query
                ->where('slug', $areaSlug)
                ->where('is_active', true))
            ->with('area')
            ->firstOrFail();
    }
}
