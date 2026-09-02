<?php

namespace Tests\Unit;

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusAggregator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LiveStatusAggregatorTest extends TestCase
{
    private CarbonImmutable $now;

    private LiveStatusAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-01T12:00:00Z');
        $this->aggregator = app(LiveStatusAggregator::class);
    }

    public function test_zero_evidence_is_insufficient_data(): void
    {
        $result = $this->aggregate([]);

        $this->assertSame(LiveStatus::INSUFFICIENT_DATA, $result->status);
        $this->assertSame(0, $result->confidenceScore);
        $this->assertNull($result->confidenceLevel);
        $this->assertSame(0, $result->recentReportCount);
    }

    public function test_one_report_is_a_low_confidence_estimate_not_locality_truth(): void
    {
        $available = $this->aggregate([$this->report(1, 'AVAILABLE', 1)]);
        $unavailable = $this->aggregate([$this->report(1, 'UNAVAILABLE', 1)]);

        $this->assertSame(LiveStatus::AVAILABLE, $available->status);
        $this->assertSame(LiveStatus::UNAVAILABLE, $unavailable->status);
        $this->assertSame(ConfidenceLevel::LOW, $available->confidenceLevel);
        $this->assertSame(ConfidenceLevel::LOW, $unavailable->confidenceLevel);
        $this->assertLessThan(80, $available->confidenceScore);
    }

    #[DataProvider('electricityConsensusProvider')]
    public function test_electricity_consensus_preserves_each_domain_status(string $status): void
    {
        $result = $this->aggregate([
            $this->report(1, $status, 1),
            $this->report(2, $status, 2),
            $this->report(3, $status, 3),
        ]);

        $this->assertSame(LiveStatus::from($status), $result->status);
        $this->assertSame(ConfidenceLevel::MEDIUM, $result->confidenceLevel);
        $this->assertSame(3, $result->supportingReportCount);
        $this->assertSame(0, $result->contradictingReportCount);
    }

    /** @return iterable<string, array{string}> */
    public static function electricityConsensusProvider(): iterable
    {
        yield 'available' => ['AVAILABLE'];
        yield 'unavailable' => ['UNAVAILABLE'];
        yield 'unstable' => ['UNSTABLE'];
    }

    public function test_strong_majority_wins_while_minority_evidence_is_counted(): void
    {
        $reports = [];
        for ($reporter = 1; $reporter <= 7; $reporter++) {
            $reports[] = $this->report($reporter, 'UNAVAILABLE', 1);
        }
        for ($reporter = 8; $reporter <= 10; $reporter++) {
            $reports[] = $this->report($reporter, 'AVAILABLE', 1);
        }

        $result = $this->aggregate($reports);

        $this->assertSame(LiveStatus::UNAVAILABLE, $result->status);
        $this->assertSame(7, $result->supportingReportCount);
        $this->assertSame(3, $result->contradictingReportCount);
    }

    public function test_near_even_electricity_evidence_is_mixed(): void
    {
        $reports = [];
        for ($reporter = 1; $reporter <= 5; $reporter++) {
            $reports[] = $this->report($reporter, 'UNAVAILABLE', 1);
            $reports[] = $this->report($reporter + 5, 'AVAILABLE', 1);
        }

        $result = $this->aggregate($reports);

        $this->assertSame(LiveStatus::MIXED, $result->status);
        $this->assertSame(5, $result->supportingReportCount);
        $this->assertSame(5, $result->contradictingReportCount);
        $this->assertNull($result->statusSince);
    }

    public function test_latest_report_from_one_reporter_replaces_earlier_evidence(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNAVAILABLE', 10, id: 1),
            $this->report(1, 'AVAILABLE', 1, id: 2),
            $this->report(2, 'AVAILABLE', 2, id: 3),
        ]);

        $this->assertSame(LiveStatus::AVAILABLE, $result->status);
        $this->assertSame(2, $result->recentReportCount);
        $this->assertSame(2, $result->supportingReportCount);
    }

    public function test_different_reporters_count_independently(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNSTABLE', 1),
            $this->report(2, 'UNSTABLE', 1),
            $this->report(3, 'UNSTABLE', 1),
        ]);

        $this->assertSame(3, $result->recentReportCount);
        $this->assertSame(ConfidenceLevel::MEDIUM, $result->confidenceLevel);
    }

    public function test_reports_outside_window_do_not_contribute(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNAVAILABLE', 31),
            $this->report(2, 'UNAVAILABLE', 120),
        ]);

        $this->assertSame(LiveStatus::INSUFFICIENT_DATA, $result->status);
    }

    public function test_recent_evidence_can_outweigh_more_older_reporters(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'AVAILABLE', 20),
            $this->report(2, 'UNAVAILABLE', 1),
        ]);

        $this->assertSame(LiveStatus::UNAVAILABLE, $result->status);
        $this->assertSame(1, $result->supportingReportCount);
        $this->assertSame(1, $result->contradictingReportCount);
    }

    #[DataProvider('gasConsensusProvider')]
    public function test_gas_uses_weighted_categorical_consensus(string $status): void
    {
        $result = $this->aggregate([
            $this->report(1, $status, 1, utility: UtilityType::GAS),
            $this->report(2, $status, 2, utility: UtilityType::GAS),
            $this->report(3, $status, 3, utility: UtilityType::GAS),
        ], UtilityType::GAS);

        $this->assertSame(LiveStatus::from($status), $result->status);
    }

    /** @return iterable<string, array{string}> */
    public static function gasConsensusProvider(): iterable
    {
        yield 'normal' => ['NORMAL'];
        yield 'low' => ['LOW'];
        yield 'very low' => ['VERY_LOW'];
        yield 'unavailable' => ['UNAVAILABLE'];
    }

    public function test_gas_majority_wins_without_averaging_severity_values(): void
    {
        $reports = [];
        for ($reporter = 1; $reporter <= 8; $reporter++) {
            $reports[] = $this->report($reporter, 'VERY_LOW', 1, utility: UtilityType::GAS);
        }
        $reports[] = $this->report(9, 'LOW', 1, utility: UtilityType::GAS);
        $reports[] = $this->report(10, 'UNAVAILABLE', 1, utility: UtilityType::GAS);

        $result = $this->aggregate($reports, UtilityType::GAS);

        $this->assertSame(LiveStatus::VERY_LOW, $result->status);
        $this->assertSame(2, $result->contradictingReportCount);
    }

    public function test_nearby_gas_severity_disagreement_uses_same_mixed_rule(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'LOW', 1, utility: UtilityType::GAS),
            $this->report(2, 'LOW', 1, utility: UtilityType::GAS),
            $this->report(3, 'VERY_LOW', 1, utility: UtilityType::GAS),
            $this->report(4, 'VERY_LOW', 1, utility: UtilityType::GAS),
        ], UtilityType::GAS);

        $this->assertSame(LiveStatus::MIXED, $result->status);
    }

    public function test_conflicting_normal_and_unavailable_gas_is_mixed(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'NORMAL', 1, utility: UtilityType::GAS),
            $this->report(2, 'NORMAL', 1, utility: UtilityType::GAS),
            $this->report(3, 'UNAVAILABLE', 1, utility: UtilityType::GAS),
            $this->report(4, 'UNAVAILABLE', 1, utility: UtilityType::GAS),
        ], UtilityType::GAS);

        $this->assertSame(LiveStatus::MIXED, $result->status);
    }

    public function test_cookability_does_not_change_gas_status_vote(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'LOW', 1, canCook: true, utility: UtilityType::GAS),
            $this->report(2, 'LOW', 1, canCook: false, utility: UtilityType::GAS),
            $this->report(3, 'LOW', 1, canCook: null, utility: UtilityType::GAS),
        ], UtilityType::GAS);

        $this->assertSame(LiveStatus::LOW, $result->status);
    }

    public function test_confidence_boundaries_require_independent_support(): void
    {
        $one = $this->aggregate([$this->report(1, 'AVAILABLE', 1)]);
        $three = $this->aggregate([
            $this->report(1, 'AVAILABLE', 1),
            $this->report(2, 'AVAILABLE', 1),
            $this->report(3, 'AVAILABLE', 1),
        ]);
        $six = $this->aggregate(array_map(
            fn (int $reporter) => $this->report($reporter, 'AVAILABLE', 1),
            range(1, 6),
        ));

        $this->assertSame(ConfidenceLevel::LOW, $one->confidenceLevel);
        $this->assertSame(ConfidenceLevel::MEDIUM, $three->confidenceLevel);
        $this->assertSame(ConfidenceLevel::HIGH, $six->confidenceLevel);
        $this->assertGreaterThan($one->confidenceScore, $three->confidenceScore);
        $this->assertGreaterThan($three->confidenceScore, $six->confidenceScore);
    }

    public function test_contradictions_reduce_the_deterministic_confidence_score(): void
    {
        $consensus = $this->aggregate(array_map(
            fn (int $reporter) => $this->report($reporter, 'UNAVAILABLE', 1),
            range(1, 6),
        ));
        $contradicted = $this->aggregate([
            ...array_map(fn (int $reporter) => $this->report($reporter, 'UNAVAILABLE', 1), range(1, 4)),
            $this->report(5, 'AVAILABLE', 1),
            $this->report(6, 'AVAILABLE', 1),
        ]);

        $this->assertLessThan($consensus->confidenceScore, $contradicted->confidenceScore);
        $this->assertSame($contradicted->confidenceScore, $this->aggregate([
            ...array_map(fn (int $reporter) => $this->report($reporter, 'UNAVAILABLE', 1), range(1, 4)),
            $this->report(5, 'AVAILABLE', 1),
            $this->report(6, 'AVAILABLE', 1),
        ])->confidenceScore);
    }

    public function test_status_since_uses_latest_close_supporting_estimate_conservatively(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNAVAILABLE', 1, estimatedMinutesAgo: 12),
            $this->report(2, 'UNAVAILABLE', 2, estimatedMinutesAgo: 10),
            $this->report(3, 'UNAVAILABLE', 3, estimatedMinutesAgo: 8),
        ]);

        $this->assertEquals($this->now->subMinutes(8), $result->statusSince);
    }

    public function test_unknown_and_over_two_hour_reports_do_not_invent_status_since(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNAVAILABLE', 1, estimatedMinutesAgo: null),
            $this->report(2, 'UNAVAILABLE', 2, estimatedMinutesAgo: null),
        ]);

        $this->assertNull($result->statusSince);
    }

    public function test_conflicting_temporal_estimates_produce_null_status_since(): void
    {
        $result = $this->aggregate([
            $this->report(1, 'UNAVAILABLE', 1, estimatedMinutesAgo: 5),
            $this->report(2, 'UNAVAILABLE', 1, estimatedMinutesAgo: 30),
        ]);

        $this->assertNull($result->statusSince);
    }

    /** @param list<UtilityReport> $reports */
    private function aggregate(array $reports, UtilityType $utility = UtilityType::ELECTRICITY)
    {
        return $this->aggregator->aggregate($reports, $utility, $this->now);
    }

    private function report(
        int $reporterId,
        string $status,
        int $minutesAgo,
        ?int $estimatedMinutesAgo = null,
        ?bool $canCook = null,
        UtilityType $utility = UtilityType::ELECTRICITY,
        int $id = 1,
    ): UtilityReport {
        $report = new UtilityReport;
        $report->forceFill([
            'id' => $id,
            'anonymous_reporter_id' => $reporterId,
            'area_id' => 1,
            'sub_area_id' => 1,
            'utility_type' => $utility,
            'status' => $status,
            'time_bucket' => $estimatedMinutesAgo === null ? 'UNKNOWN' : 'MIN_15',
            'reported_at' => $this->now->subMinutes($minutesAgo),
            'estimated_started_at' => $estimatedMinutesAgo === null ? null : $this->now->subMinutes($estimatedMinutesAgo),
            'can_cook' => $canCook,
        ]);

        return $report;
    }
}
