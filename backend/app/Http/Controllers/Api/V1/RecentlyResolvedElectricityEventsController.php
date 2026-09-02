<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ElectricityOutageLifecycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecentlyResolvedElectricityEventsRequest;
use App\Models\ElectricityOutageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class RecentlyResolvedElectricityEventsController extends Controller
{
    public function __invoke(RecentlyResolvedElectricityEventsRequest $request): JsonResponse
    {
        $limit = $request->integer('limit', 6);
        $since = CarbonImmutable::now('UTC')->subDay();
        $events = ElectricityOutageEvent::query()
            ->with(['subArea.area'])
            ->where('lifecycle', ElectricityOutageLifecycle::RESOLVED)
            ->where('ended_at', '>=', $since)
            ->whereHas('subArea', fn ($subArea) => $subArea->where('is_active', true)
                ->whereHas('area', fn ($area) => $area->where('is_active', true)))
            ->orderByDesc('ended_at')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $events->map(fn (ElectricityOutageEvent $event) => [
            'sub_area' => [
                'name' => $event->subArea->name,
                'slug' => $event->subArea->slug,
                'area' => ['name' => $event->subArea->area->name, 'slug' => $event->subArea->area->slug],
            ],
            'started_at' => $event->started_at->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            'ended_at' => $event->ended_at?->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            // Carbon 3 returns a signed float, so the earlier instant must be the receiver
            // for this to stay a positive integer-second duration like every other endpoint.
            'duration_seconds' => $event->ended_at === null
                ? null
                : (int) $event->started_at->diffInSeconds($event->ended_at),
        ])->values()]);
    }
}
