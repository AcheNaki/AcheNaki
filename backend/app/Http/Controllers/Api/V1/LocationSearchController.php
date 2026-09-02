<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchLocationsRequest;
use App\Models\Area;
use App\Models\SubArea;
use Illuminate\Http\JsonResponse;

class LocationSearchController extends Controller
{
    /** Mirrors the `min:2` request rule so a stripped term cannot become a match-all. */
    private const MINIMUM_TERM_LENGTH = 2;

    public function __invoke(SearchLocationsRequest $request): JsonResponse
    {
        $input = $request->validated();
        $limit = $input['limit'] ?? 8;
        $term = $this->searchTerm($input['q']);

        if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return response()->json(['data' => []]);
        }

        $pattern = '%'.$term.'%';
        $areas = Area::query()->where('is_active', true)->whereLike('name', $pattern, caseSensitive: false)
            ->orderBy('sort_order')->orderBy('name')->limit($limit)->get()
            ->map(fn (Area $area) => ['kind' => 'AREA', 'name' => $area->name, 'area' => ['name' => $area->name, 'slug' => $area->slug], 'sub_area_slug' => null]);
        $subAreas = SubArea::query()->with('area')->where('is_active', true)->whereLike('name', $pattern, caseSensitive: false)
            ->whereHas('area', fn ($area) => $area->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->limit($limit)->get()
            ->map(fn (SubArea $subArea) => ['kind' => 'SUB_AREA', 'name' => $subArea->name, 'area' => ['name' => $subArea->area->name, 'slug' => $subArea->area->slug], 'sub_area_slug' => $subArea->slug]);

        return response()->json(['data' => $areas->concat($subAreas)->take($limit)->values()]);
    }

    /**
     * Canonical locality names never contain LIKE wildcards, so removing them keeps a
     * typed `%` or `_` from silently matching the whole taxonomy. Portable `ESCAPE`
     * semantics differ between PostgreSQL and SQLite, so the term is stripped instead.
     */
    private function searchTerm(string $query): string
    {
        return str_replace(['\\', '%', '_'], '', $query);
    }
}
