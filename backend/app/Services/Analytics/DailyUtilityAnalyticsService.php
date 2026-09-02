<?php

namespace App\Services\Analytics;

use App\Enums\ElectricityOutageLifecycle;
use App\Enums\LiveStatus;
use App\Models\ElectricityOutageEvent;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DailyUtilityAnalyticsService
{
    public function __construct(private readonly ElectricityCoverageTimeline $electricityCoverage) {}

    /** @return array<string, mixed> */
    public function summarize(SubArea $subArea, DailyAnalysisWindow $window): array
    {
        return [
            'sub_area' => [
                'id' => $subArea->id,
                'name' => $subArea->name,
                'area' => ['id' => $subArea->area->id, 'name' => $subArea->area->name],
            ],
            'date' => $window->date,
            'timezone' => $window->timezone,
            'window' => [
                'started_at' => $this->formatUtc($window->startsAt),
                'ended_at' => $this->formatUtc($window->endsAt),
                'duration_seconds' => $window->durationSeconds(),
                'partial' => $window->partial,
            ],
            'electricity' => $this->electricity($subArea, $window),
            'gas' => $this->gas($subArea, $window),
        ];
    }

    /** @return array<string, mixed> */
    private function electricity(SubArea $subArea, DailyAnalysisWindow $window): array
    {
        $events = ElectricityOutageEvent::query()
            ->where('sub_area_id', $subArea->id)
            ->whereIn('lifecycle', [ElectricityOutageLifecycle::ACTIVE, ElectricityOutageLifecycle::RESOLVED])
            ->where('started_at', '<', $window->endsAt)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $window->startsAt))
            ->orderBy('started_at')
            ->get();
        $publicEvents = [];
        $totalOutage = 0;
        $longestOutage = 0;
        $ongoingOutage = 0;

        foreach ($events as $event) {
            $duration = $this->overlapSeconds($event->started_at, $event->ended_at, $window);
            $totalOutage += $duration;
            $longestOutage = max($longestOutage, $duration);
            if ($event->lifecycle === ElectricityOutageLifecycle::ACTIVE) {
                $ongoingOutage += $duration;
            }
            $publicEvents[] = [
                'id' => $event->id,
                'started_at' => $this->formatUtc($event->started_at),
                'ended_at' => $this->formatUtc($event->ended_at),
                'ongoing' => $event->lifecycle === ElectricityOutageLifecycle::ACTIVE,
                'start_confidence' => $event->start_confidence_level->value,
                'end_confidence' => $event->end_confidence_level?->value,
                'duration_seconds' => $duration,
            ];
        }

        $stateSeconds = ['AVAILABLE' => 0, 'UNAVAILABLE' => 0, 'UNSTABLE' => 0];
        $publicSegments = [];
        foreach ($this->electricityCoverage->build($subArea, $window) as $segment) {
            $duration = $this->overlapSeconds(
                $segment->startedAt,
                $segment->observedUntilAt,
                $window,
            );
            $stateSeconds[$segment->status->value] += $duration;
            if ($duration > 0) {
                $publicSegments[] = [
                    'status' => $segment->status->value,
                    'started_at' => $this->formatUtc($segment->startedAt->greaterThan($window->startsAt) ? $segment->startedAt : $window->startsAt),
                    'ended_at' => $this->formatUtc($segment->observedUntilAt->lessThan($window->endsAt) ? $segment->observedUntilAt : $window->endsAt),
                    'duration_seconds' => $duration,
                ];
            }
        }
        $observed = array_sum($stateSeconds);

        return [
            'outage_count' => $events->count(),
            'total_outage_seconds' => $totalOutage,
            'longest_outage_seconds' => $longestOutage,
            'ongoing_outage_seconds' => $ongoingOutage,
            'state_seconds' => [
                'available' => $stateSeconds[LiveStatus::AVAILABLE->value],
                'unavailable' => $stateSeconds[LiveStatus::UNAVAILABLE->value],
                'unstable' => $stateSeconds[LiveStatus::UNSTABLE->value],
            ],
            'coverage' => [
                'observed_seconds' => $observed,
                'unknown_seconds' => max(0, $window->durationSeconds() - $observed),
            ],
            'segments' => $publicSegments,
            'events' => $publicEvents,
        ];
    }

    /** @return array<string, mixed> */
    private function gas(SubArea $subArea, DailyAnalysisWindow $window): array
    {
        $intervals = GasStateInterval::query()
            ->where('sub_area_id', $subArea->id)
            ->where('started_at', '<', $window->endsAt)
            ->where('observed_until_at', '>', $window->startsAt)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $window->startsAt))
            ->orderBy('started_at')
            ->get();
        $durations = ['NORMAL' => 0, 'LOW' => 0, 'VERY_LOW' => 0, 'UNAVAILABLE' => 0];
        $publicIntervals = [];
        $now = CarbonImmutable::now('UTC');

        foreach ($intervals as $interval) {
            $effectiveEnd = $interval->ended_at === null || $interval->observed_until_at->lessThan($interval->ended_at)
                ? $interval->observed_until_at
                : $interval->ended_at;
            $duration = $this->overlapSeconds($interval->started_at, $effectiveEnd, $window);
            $durations[$interval->status->value] += $duration;
            $publicIntervals[] = [
                'id' => $interval->id,
                'status' => $interval->status->value,
                'started_at' => $this->formatUtc($interval->started_at),
                'ended_at' => $this->formatUtc($interval->ended_at),
                'observed_until_at' => $this->formatUtc($interval->observed_until_at),
                'ongoing' => $interval->ended_at === null && $interval->observed_until_at->greaterThan($now),
                'start_confidence' => $interval->start_confidence_level->value,
                'duration_seconds' => $duration,
            ];
        }
        $observed = array_sum($durations);

        return [
            'state_seconds' => [
                'normal' => $durations['NORMAL'],
                'low' => $durations['LOW'],
                'very_low' => $durations['VERY_LOW'],
                'unavailable' => $durations['UNAVAILABLE'],
            ],
            'coverage' => [
                'observed_seconds' => $observed,
                'unknown_seconds' => max(0, $window->durationSeconds() - $observed),
            ],
            'intervals' => $publicIntervals,
        ];
    }

    private function overlapSeconds(
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        DailyAnalysisWindow $window,
    ): int {
        $start = CarbonImmutable::instance($startsAt)->greaterThan($window->startsAt)
            ? CarbonImmutable::instance($startsAt)
            : $window->startsAt;
        $candidateEnd = $endsAt === null ? $window->endsAt : CarbonImmutable::instance($endsAt);
        $end = $candidateEnd->lessThan($window->endsAt) ? $candidateEnd : $window->endsAt;

        return $end->greaterThan($start) ? $start->diffInSeconds($end) : 0;
    }

    private function formatUtc(?CarbonInterface $value): ?string
    {
        return $value?->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
