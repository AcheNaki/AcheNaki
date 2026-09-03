<?php

namespace App\Services\LiveStatus;

use App\Enums\UtilityType;
use App\Models\UtilityLiveStatus;
use App\Models\UtilityReport;
use Carbon\CarbonImmutable;

/**
 * Builds the city-wide live aggregate from the same rolling evidence window and the same
 * staleness guard the locality projections already use, so the summary can never claim a
 * current problem that the locality cards no longer show.
 */
class LiveSummaryService
{
    public function summarize(?CarbonImmutable $requestedAt = null): LiveSummary
    {
        $now = $requestedAt ?? CarbonImmutable::now('UTC');
        $windowSeconds = (int) config('reporting.aggregation.window_seconds');
        $windowStart = $now->subSeconds($windowSeconds);

        $activity = $this->reportActivity($windowStart, $now);
        $issues = $this->issueLocalityCounts($windowStart);

        return new LiveSummary(
            windowMinutes: intdiv($windowSeconds, 60),
            reports: $activity['reports'],
            localitiesUpdated: $activity['localities'],
            electricityIssueLocalities: $issues['electricity'],
            gasIssueLocalities: $issues['gas'],
            currentlyStrugglingLocalities: $issues['struggling'],
            calculatedAt: $now,
        );
    }

    /**
     * Accepted raw reports only. A suppressed duplicate never becomes a row, so it is
     * already excluded here without any extra rule.
     *
     * @return array{reports: int, localities: int}
     */
    private function reportActivity(CarbonImmutable $windowStart, CarbonImmutable $now): array
    {
        $row = UtilityReport::query()
            // `utility_type` leads the recency index, and the enum has exactly two cases, so
            // naming both keeps this a bounded indexed range scan instead of a table scan.
            ->whereIn('utility_type', array_column(UtilityType::cases(), 'value'))
            ->whereBetween('reported_at', [$windowStart, $now])
            ->toBase()
            ->selectRaw('count(*) as report_count, count(distinct sub_area_id) as locality_count')
            ->first();

        return [
            'reports' => (int) ($row->report_count ?? 0),
            'localities' => (int) ($row->locality_count ?? 0),
        ];
    }

    /**
     * Distinct localities, never utility cards: a locality with both an electricity and a gas
     * problem counts once as struggling.
     *
     * @return array{electricity: int, gas: int, struggling: int}
     */
    private function issueLocalityCounts(CarbonImmutable $windowStart): array
    {
        [$electricitySql, $electricityBindings] = $this->issuePredicate(UtilityType::ELECTRICITY);
        [$gasSql, $gasBindings] = $this->issuePredicate(UtilityType::GAS);

        $row = UtilityLiveStatus::query()
            // The same freshness guard the public locality snapshot applies: a projection whose
            // evidence has aged out of the window is not a current problem, row or no row.
            ->where('last_report_at', '>=', $windowStart)
            ->whereHas('subArea', fn ($subArea) => $subArea
                ->where('is_active', true)
                ->whereHas('area', fn ($area) => $area->where('is_active', true)))
            ->toBase()
            ->selectRaw(
                sprintf(
                    'count(distinct case when %1$s then sub_area_id end) as electricity_localities, '
                    .'count(distinct case when %2$s then sub_area_id end) as gas_localities, '
                    .'count(distinct case when %1$s or %2$s then sub_area_id end) as struggling_localities',
                    $electricitySql,
                    $gasSql,
                ),
                [...$electricityBindings, ...$gasBindings, ...$electricityBindings, ...$gasBindings],
            )
            ->first();

        return [
            'electricity' => (int) ($row->electricity_localities ?? 0),
            'gas' => (int) ($row->gas_localities ?? 0),
            'struggling' => (int) ($row->struggling_localities ?? 0),
        ];
    }

    /** @return array{0: string, 1: list<string>} */
    private function issuePredicate(UtilityType $utility): array
    {
        $statuses = $utility->issueStatusValues();

        return [
            sprintf(
                '(utility_type = ? and estimated_status in (%s))',
                implode(', ', array_fill(0, count($statuses), '?')),
            ),
            [$utility->value, ...$statuses],
        ];
    }
}
