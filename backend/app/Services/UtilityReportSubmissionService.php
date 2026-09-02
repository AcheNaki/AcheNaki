<?php

namespace App\Services;

use App\Enums\TimeBucket;
use App\Enums\UtilityType;
use App\Models\AnonymousReporter;
use App\Models\UtilityReport;
use App\Services\LiveStatus\LiveStatusProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UtilityReportSubmissionService
{
    public function __construct(
        private readonly LiveStatusProjectionService $liveStatuses,
    ) {}

    /**
     * Accepts the pseudonymous reporter hash rather than the raw browser token: PHP puts
     * scalar call arguments into exception stack traces, and this frame stays on the stack
     * across the report transaction and the projection refresh that logs its failures.
     *
     * @param  array<string, mixed>  $input
     */
    public function submit(array $input, string $tokenHash): UtilityReportSubmissionResult
    {
        $now = CarbonImmutable::now('UTC');

        AnonymousReporter::query()->insertOrIgnore([
            'token_hash' => $tokenHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = DB::transaction(function () use ($input, $tokenHash, $now): UtilityReportSubmissionResult {
            $reporter = AnonymousReporter::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();

            $duplicate = UtilityReport::query()
                ->where('anonymous_reporter_id', $reporter->id)
                ->where('sub_area_id', $input['sub_area_id'])
                ->where('utility_type', $input['utility_type'])
                ->where('status', $input['status'])
                ->where('reported_at', '>=', $now->subSeconds((int) config('reporting.duplicate_window_seconds')))
                ->latest('reported_at')
                ->first();

            if ($duplicate !== null) {
                return new UtilityReportSubmissionResult($duplicate, true);
            }

            $timeBucket = TimeBucket::from($input['time_bucket']);
            $utilityType = UtilityType::from($input['utility_type']);

            $report = UtilityReport::query()->create([
                'anonymous_reporter_id' => $reporter->id,
                'area_id' => $input['area_id'],
                'sub_area_id' => $input['sub_area_id'],
                'utility_type' => $utilityType,
                'status' => $input['status'],
                'time_bucket' => $timeBucket,
                'reported_at' => $now,
                'estimated_started_at' => $timeBucket->estimatedStartedAt($now),
                'can_cook' => $utilityType === UtilityType::GAS ? ($input['can_cook'] ?? null) : null,
            ]);

            return new UtilityReportSubmissionResult($report, false);
        }, 3);

        if (! $result->duplicate) {
            try {
                $this->liveStatuses->refreshByIds(
                    $result->report->sub_area_id,
                    $result->report->utility_type,
                    $now,
                );
            } catch (Throwable $exception) {
                Log::error('Live utility status projection refresh failed after report acceptance.', [
                    'report_id' => $result->report->id,
                    'sub_area_id' => $result->report->sub_area_id,
                    'utility_type' => $result->report->utility_type->value,
                    'exception' => $exception,
                ]);
            }
        }

        return $result;
    }
}
