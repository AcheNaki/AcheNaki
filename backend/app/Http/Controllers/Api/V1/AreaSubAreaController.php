<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SubAreaResource;
use App\Models\Area;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaSubAreaController extends Controller
{
    public function __invoke(Area $area): AnonymousResourceCollection
    {
        abort_unless($area->is_active, 404);

        $subAreas = $area->subAreas()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return SubAreaResource::collection($subAreas);
    }
}
