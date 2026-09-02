<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnonymousReporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnonymousSessionController extends Controller
{
    public function __invoke(Request $request, AnonymousReporterService $reporters): JsonResponse
    {
        $candidate = $request->header((string) config('reporting.anonymous_header'));
        $token = $reporters->issueOrReuse(is_string($candidate) ? $candidate : null);

        return response()->json(['data' => ['token' => $token]]);
    }
}
