<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUtilityReportRequest;
use App\Http\Resources\V1\UtilityReportResource;
use App\Services\UtilityReportSubmissionService;
use Illuminate\Http\JsonResponse;

class UtilityReportController extends Controller
{
    public function __invoke(
        StoreUtilityReportRequest $request,
        UtilityReportSubmissionService $reports,
    ): JsonResponse {
        $result = $reports->submit($request->validated(), $request->reporterTokenHash());

        return (new UtilityReportResource($result->report))
            ->additional(['meta' => ['duplicate' => $result->duplicate]])
            ->response()
            ->setStatusCode($result->duplicate ? 200 : 201);
    }
}
