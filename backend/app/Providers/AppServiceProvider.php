<?php

namespace App\Providers;

use App\Services\AnonymousReporterService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('anonymous-sessions', function (Request $request): Limit {
            $networkKey = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

            return Limit::perMinute((int) config('reporting.session_rate_limit_per_minute'))
                ->by('anonymous-session:'.$networkKey)
                ->response(fn (Request $request, array $headers) => $this->rateLimitResponse($headers));
        });

        RateLimiter::for('utility-reports', function (Request $request): Limit {
            $token = $request->header((string) config('reporting.anonymous_header'));
            // Only a well-formed token may claim its own bucket. Keying on arbitrary header
            // values would let a caller mint a fresh limit for every request simply by
            // sending a different malformed token, bypassing the per-reporter limit.
            $key = app(AnonymousReporterService::class)->isValid(is_string($token) ? $token : null)
                ? hash_hmac('sha256', 'utility-report-limit:'.$token, (string) config('app.key'))
                : hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

            return Limit::perMinute((int) config('reporting.report_rate_limit_per_minute'))
                ->by('utility-report:'.$key)
                ->response(fn (Request $request, array $headers) => $this->rateLimitResponse($headers));
        });
    }

    /** @param array<string, string|int> $headers */
    private function rateLimitResponse(array $headers): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Too many requests. Please try again shortly.',
            ],
        ], 429, $headers);
    }
}
