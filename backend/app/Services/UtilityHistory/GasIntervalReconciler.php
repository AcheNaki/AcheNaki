<?php

namespace App\Services\UtilityHistory;

use App\Enums\GasStatus;
use App\Models\GasStateInterval;
use App\Models\SubArea;
use App\Models\UtilityLiveStatus;
use Carbon\CarbonImmutable;

class GasIntervalReconciler
{
    public function __construct(private readonly DurableTransitionPolicy $policy) {}

    public function reconcile(SubArea $subArea, UtilityLiveStatus $projection): void
    {
        $interval = GasStateInterval::query()
            ->where('sub_area_id', $subArea->id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first();
        $status = GasStatus::tryFrom($projection->estimated_status->value);

        if ($status === null) {
            if ($interval?->pending_status !== null) {
                $interval->update($this->clearedPending());
            }

            return;
        }

        if (! $this->policy->permits($projection->confidence_level)) {
            return;
        }

        $now = $projection->calculated_at->toImmutable();
        $observedUntil = ($projection->last_report_at?->toImmutable() ?? $now)
            ->addSeconds((int) config('reporting.aggregation.window_seconds'));

        if ($interval === null) {
            $this->openInterval($subArea, $projection, $status, $now, $observedUntil);

            return;
        }

        if ($now->greaterThan($interval->observed_until_at)) {
            $previousObservedUntil = $interval->observed_until_at->toImmutable();
            $interval->update([
                'ended_at' => $previousObservedUntil,
                ...$this->clearedPending(),
            ]);
            $this->openInterval(
                $subArea,
                $projection,
                $status,
                $now,
                $observedUntil,
                $previousObservedUntil,
            );

            return;
        }

        if ($interval->status === $status) {
            $interval->update([
                'observed_until_at' => $observedUntil->greaterThan($interval->observed_until_at)
                    ? $observedUntil
                    : $interval->observed_until_at,
                ...$this->clearedPending(),
            ]);

            return;
        }

        if ($interval->pending_status !== $status) {
            $interval->update([
                'pending_status' => $status,
                'pending_confidence_level' => $projection->confidence_level,
                'pending_since' => $now,
            ]);

            return;
        }

        if (! $this->policy->isStableSince($interval->pending_since, $now)) {
            return;
        }

        $transitionAt = CarbonImmutable::instance($interval->pending_since);
        $interval->update([
            'ended_at' => $transitionAt,
            'observed_until_at' => $transitionAt,
            ...$this->clearedPending(),
        ]);
        GasStateInterval::query()->create([
            'area_id' => $subArea->area_id,
            'sub_area_id' => $subArea->id,
            'status' => $status,
            'started_at' => $transitionAt,
            'observed_until_at' => $observedUntil,
            'start_confidence_level' => $projection->confidence_level,
            'inference_version' => config('reporting.events.inference_version'),
        ]);
    }

    /** @return array{pending_status: null, pending_confidence_level: null, pending_since: null} */
    private function clearedPending(): array
    {
        return [
            'pending_status' => null,
            'pending_confidence_level' => null,
            'pending_since' => null,
        ];
    }

    private function openInterval(
        SubArea $subArea,
        UtilityLiveStatus $projection,
        GasStatus $status,
        CarbonImmutable $now,
        CarbonImmutable $observedUntil,
        ?CarbonImmutable $notBefore = null,
    ): void {
        $estimatedStart = $projection->status_since?->toImmutable();
        $startedAt = $estimatedStart !== null && $estimatedStart->lessThanOrEqualTo($now)
            ? $estimatedStart
            : $now;
        if ($notBefore !== null && $startedAt->lessThan($notBefore)) {
            $startedAt = $notBefore;
        }

        GasStateInterval::query()->create([
            'area_id' => $subArea->area_id,
            'sub_area_id' => $subArea->id,
            'status' => $status,
            'started_at' => $startedAt,
            'observed_until_at' => $observedUntil,
            'start_confidence_level' => $projection->confidence_level,
            'inference_version' => config('reporting.events.inference_version'),
        ]);
    }
}
