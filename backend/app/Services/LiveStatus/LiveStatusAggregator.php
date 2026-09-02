<?php

namespace App\Services\LiveStatus;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Models\UtilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class LiveStatusAggregator
{
    /** @param iterable<UtilityReport> $reports */
    public function aggregate(
        iterable $reports,
        UtilityType $utilityType,
        ?CarbonImmutable $calculatedAt = null,
    ): LiveStatusAggregation {
        $now = $calculatedAt ?? CarbonImmutable::now('UTC');
        $windowStart = $now->subSeconds((int) config('reporting.aggregation.window_seconds'));
        $evidence = $this->latestEvidencePerReporter($reports, $utilityType, $windowStart, $now);

        if ($evidence->isEmpty()) {
            return new LiveStatusAggregation(
                LiveStatus::INSUFFICIENT_DATA,
                0,
                null,
                0,
                0,
                0,
                null,
                $windowStart,
                null,
                $now,
            );
        }

        /** @var array<string, array{weight: int, count: int, reports: Collection<int, UtilityReport>}> $groups */
        $groups = [];
        $totalWeight = 0;

        foreach ($evidence as $report) {
            $weight = $this->weightFor($report->reported_at->toImmutable(), $now);
            $status = $report->status;
            $groups[$status] ??= ['weight' => 0, 'count' => 0, 'reports' => collect()];
            $groups[$status]['weight'] += $weight;
            $groups[$status]['count']++;
            $groups[$status]['reports']->push($report);
            $totalWeight += $weight;
        }

        uasort($groups, fn (array $left, array $right): int => $right['weight'] <=> $left['weight']);
        $rankedStatuses = array_keys($groups);
        $topStatus = $rankedStatuses[0];
        $secondStatus = $rankedStatuses[1] ?? null;
        $topWeight = $groups[$topStatus]['weight'];
        $topCount = $groups[$topStatus]['count'];
        $isMixed = $secondStatus !== null
            && ($topWeight - $groups[$secondStatus]['weight']) * 100
                <= $totalWeight * (int) config('reporting.aggregation.mixed_max_weight_difference_percent');

        $status = $isMixed ? LiveStatus::MIXED : LiveStatus::from($topStatus);
        $supportingCount = $topCount;
        $recentCount = $evidence->count();
        $score = $this->confidenceScore($topWeight, $totalWeight, $recentCount);

        return new LiveStatusAggregation(
            $status,
            $score,
            $this->confidenceLevel($score, $recentCount, $topCount),
            $recentCount,
            $supportingCount,
            $recentCount - $supportingCount,
            $isMixed ? null : $this->statusSince($groups[$topStatus]['reports']),
            $windowStart,
            $evidence->first()->reported_at->toImmutable(),
            $now,
        );
    }

    /**
     * @param  iterable<UtilityReport>  $reports
     * @return Collection<int, UtilityReport>
     */
    private function latestEvidencePerReporter(
        iterable $reports,
        UtilityType $utilityType,
        CarbonImmutable $windowStart,
        CarbonImmutable $now,
    ): Collection {
        return collect($reports)
            ->filter(fn (UtilityReport $report): bool => $report->utility_type === $utilityType
                && $report->reported_at->betweenIncluded($windowStart, $now))
            ->sortByDesc(fn (UtilityReport $report): string => sprintf(
                '%020d-%020d',
                $report->reported_at->getTimestamp(),
                $report->id ?? 0,
            ))
            ->unique('anonymous_reporter_id')
            ->values();
    }

    private function weightFor(CarbonImmutable $reportedAt, CarbonImmutable $now): int
    {
        $age = max(0, $now->getTimestamp() - $reportedAt->getTimestamp());

        foreach (config('reporting.aggregation.recency_weights') as $band) {
            if ($age <= $band['max_age_seconds']) {
                return $band['weight'];
            }
        }

        return 0;
    }

    private function confidenceScore(int $topWeight, int $totalWeight, int $reporterCount): int
    {
        $agreement = (int) round($topWeight * 100 / $totalWeight);
        $recency = (int) round($totalWeight * 100 / ($reporterCount * 100));
        $volumeScores = config('reporting.aggregation.volume_scores');
        $volume = $volumeScores[min($reporterCount, max(array_keys($volumeScores)))];
        $components = config('reporting.aggregation.score_components');

        return (int) round((
            $agreement * $components['agreement_percent']
            + $recency * $components['recency_percent']
            + $volume * $components['volume_percent']
        ) / 100);
    }

    private function confidenceLevel(int $score, int $reporterCount, int $topSupporters): ConfidenceLevel
    {
        $high = config('reporting.aggregation.confidence.high');
        if ($score >= $high['minimum_score']
            && $reporterCount >= $high['minimum_reporters']
            && $topSupporters >= $high['minimum_supporters']) {
            return ConfidenceLevel::HIGH;
        }

        $medium = config('reporting.aggregation.confidence.medium');
        if ($score >= $medium['minimum_score']
            && $reporterCount >= $medium['minimum_reporters']
            && $topSupporters >= $medium['minimum_supporters']) {
            return ConfidenceLevel::MEDIUM;
        }

        return ConfidenceLevel::LOW;
    }

    /** @param Collection<int, UtilityReport> $supportingReports */
    private function statusSince(Collection $supportingReports): ?CarbonImmutable
    {
        $estimates = $supportingReports
            ->pluck('estimated_started_at')
            ->filter()
            ->map(fn ($value) => $value->toImmutable())
            ->sort()
            ->values();
        $policy = config('reporting.aggregation.status_since');

        if ($estimates->count() < $policy['minimum_supporters_with_estimates']) {
            return null;
        }

        $earliest = $estimates->first();
        $latest = $estimates->last();
        if ($latest->getTimestamp() - $earliest->getTimestamp() > $policy['maximum_estimate_spread_seconds']) {
            return null;
        }

        return $latest;
    }
}
