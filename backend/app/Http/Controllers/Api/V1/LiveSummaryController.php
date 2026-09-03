<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LiveStatus\LiveSummaryService;
use Illuminate\Http\JsonResponse;

class LiveSummaryController extends Controller
{
    public function __invoke(LiveSummaryService $summaries): JsonResponse
    {
        $summary = $summaries->summarize();

        return response()->json([
            'data' => [
                'window_minutes' => $summary->windowMinutes,
                'reports' => $summary->reports,
                'localities_updated' => $summary->localitiesUpdated,
                // Locality counts, not reporter counts: a locality with both utilities in
                // trouble is one struggling locality, not two.
                'electricity_issue_localities' => $summary->electricityIssueLocalities,
                'gas_issue_localities' => $summary->gasIssueLocalities,
                'currently_struggling_localities' => $summary->currentlyStrugglingLocalities,
                'calculated_at' => $summary->calculatedAt->format('Y-m-d\\TH:i:s.u\\Z'),
            ],
        ]);
    }
}
