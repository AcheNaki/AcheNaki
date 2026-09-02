<?php

namespace App\Services\UtilityHistory;

use App\Enums\ElectricityOutageLifecycle;
use App\Enums\LiveStatus;
use App\Models\ElectricityOutageEvent;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use Carbon\CarbonImmutable;

class ElectricityEventReconciler
{
    public function __construct(private readonly DurableTransitionPolicy $policy) {}

    public function reconcile(SubArea $subArea, UtilityLiveStatus $projection): void
    {
        $event = ElectricityOutageEvent::query()
            ->where('sub_area_id', $subArea->id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first();
        $status = $projection->estimated_status;
        $now = $projection->calculated_at->toImmutable();

        if ($status === LiveStatus::UNAVAILABLE && $this->policy->permits($projection->confidence_level)) {
            $this->reconcileUnavailable($subArea, $projection, $event, $now);

            return;
        }

        if ($event?->lifecycle === ElectricityOutageLifecycle::CANDIDATE) {
            if (in_array($status, [LiveStatus::MIXED, LiveStatus::INSUFFICIENT_DATA, LiveStatus::UNSTABLE], true)
                || ($status === LiveStatus::AVAILABLE && $this->policy->permits($projection->confidence_level))) {
                $event->delete();
            }

            return;
        }

        if ($event?->lifecycle !== ElectricityOutageLifecycle::ACTIVE) {
            return;
        }

        if ($status === LiveStatus::AVAILABLE && $this->policy->permits($projection->confidence_level)) {
            if ($event->resolution_candidate_at === null) {
                $event->update(['resolution_candidate_at' => $now]);

                return;
            }

            if (! $this->policy->isFresh($event->resolution_candidate_at, $now)) {
                $event->update(['resolution_candidate_at' => $now]);

                return;
            }

            if ($this->policy->isStableSince($event->resolution_candidate_at, $now)) {
                $endedAt = CarbonImmutable::instance($event->resolution_candidate_at);
                $event->update([
                    'lifecycle' => ElectricityOutageLifecycle::RESOLVED,
                    'ended_at' => $endedAt->greaterThan($event->started_at) ? $endedAt : $event->started_at,
                    'end_confidence_level' => $projection->confidence_level,
                    'resolution_candidate_at' => null,
                ]);
            }

            return;
        }

        if (in_array($status, [LiveStatus::MIXED, LiveStatus::INSUFFICIENT_DATA, LiveStatus::UNSTABLE], true)) {
            $event->update(['resolution_candidate_at' => null]);
        }
    }

    private function reconcileUnavailable(
        SubArea $subArea,
        UtilityLiveStatus $projection,
        ?ElectricityOutageEvent $event,
        CarbonImmutable $now,
    ): void {
        if ($event === null) {
            $estimatedStart = $projection->status_since?->toImmutable();
            $startedAt = $estimatedStart !== null && $estimatedStart->lessThanOrEqualTo($now)
                ? $estimatedStart
                : $now;
            $previousEnd = ElectricityOutageEvent::query()
                ->where('sub_area_id', $subArea->id)
                ->whereNotNull('ended_at')
                ->max('ended_at');
            if ($previousEnd !== null) {
                $previousEnd = CarbonImmutable::parse($previousEnd, 'UTC');
                if ($startedAt->lessThan($previousEnd)) {
                    $startedAt = $previousEnd;
                }
            }
            ElectricityOutageEvent::query()->create([
                'area_id' => $subArea->area_id,
                'sub_area_id' => $subArea->id,
                'lifecycle' => ElectricityOutageLifecycle::CANDIDATE,
                'started_at' => $startedAt,
                'first_supported_at' => $now,
                'start_confidence_level' => $projection->confidence_level,
                'inference_version' => config('reporting.events.inference_version'),
            ]);

            return;
        }

        if ($event->lifecycle === ElectricityOutageLifecycle::CANDIDATE
            && ! $this->policy->isFresh($event->first_supported_at, $now)) {
            $estimatedStart = $projection->status_since?->toImmutable();
            $startedAt = $estimatedStart !== null && $estimatedStart->lessThanOrEqualTo($now)
                ? $estimatedStart
                : $now;
            $previousEnd = ElectricityOutageEvent::query()
                ->where('sub_area_id', $subArea->id)
                ->whereNotNull('ended_at')
                ->max('ended_at');
            if ($previousEnd !== null) {
                $previousEnd = CarbonImmutable::parse($previousEnd, 'UTC');
                if ($startedAt->lessThan($previousEnd)) {
                    $startedAt = $previousEnd;
                }
            }
            $event->update([
                'started_at' => $startedAt,
                'first_supported_at' => $now,
                'start_confidence_level' => $projection->confidence_level,
            ]);

            return;
        }

        if ($event->lifecycle === ElectricityOutageLifecycle::CANDIDATE
            && $this->policy->isStableSince($event->first_supported_at, $now)) {
            $event->update([
                'lifecycle' => ElectricityOutageLifecycle::ACTIVE,
                'confirmed_at' => $now,
                'start_confidence_level' => $projection->confidence_level,
            ]);

            return;
        }

        if ($event->lifecycle === ElectricityOutageLifecycle::ACTIVE
            && $event->resolution_candidate_at !== null) {
            $event->update(['resolution_candidate_at' => null]);
        }
    }
}
