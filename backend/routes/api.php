<?php

use App\Http\Controllers\Api\V1\AnonymousSessionController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\AreaLiveStatusesController;
use App\Http\Controllers\Api\V1\AreaSubAreaController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LiveStatusListController;
use App\Http\Controllers\Api\V1\LocationSearchController;
use App\Http\Controllers\Api\V1\RecentlyResolvedElectricityEventsController;
use App\Http\Controllers\Api\V1\SlugLocalityDailyAnalyticsController;
use App\Http\Controllers\Api\V1\SlugLocalityLiveStatusController;
use App\Http\Controllers\Api\V1\SubAreaDailyAnalyticsController;
use App\Http\Controllers\Api\V1\SubAreaLiveStatusController;
use App\Http\Controllers\Api\V1\UtilityReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', function (): JsonResponse {
        return response()->json([
            'status' => 'ok',
            'service' => 'achenaki-api',
        ]);
    })->name('health');

    Route::get('/areas', AreaController::class)->name('areas.index');
    Route::get('/areas/{area}/sub-areas', AreaSubAreaController::class)
        ->whereNumber('area')
        ->name('areas.sub-areas.index');
    Route::get('/areas/{areaSlug}/statuses', AreaLiveStatusesController::class)
        ->name('areas.statuses.index');
    Route::get('/areas/{areaSlug}/sub-areas/{subAreaSlug}/status', SlugLocalityLiveStatusController::class)
        ->name('areas.sub-areas.status.show');
    Route::get('/areas/{areaSlug}/sub-areas/{subAreaSlug}/analytics', SlugLocalityDailyAnalyticsController::class)
        ->name('areas.sub-areas.analytics.show');
    Route::get('/locations/search', LocationSearchController::class)->name('locations.search');

    Route::post('/anonymous-session', AnonymousSessionController::class)
        ->middleware('throttle:anonymous-sessions')
        ->name('anonymous-session.store');
    Route::post('/utility-reports', UtilityReportController::class)
        ->middleware('throttle:utility-reports')
        ->name('utility-reports.store');

    Route::get('/sub-areas/{subArea}/status', SubAreaLiveStatusController::class)
        ->whereNumber('subArea')
        ->name('sub-areas.status.show');
    Route::get('/sub-areas/{subArea}/analytics', SubAreaDailyAnalyticsController::class)
        ->whereNumber('subArea')
        ->name('sub-areas.analytics.show');
    Route::get('/live-statuses', LiveStatusListController::class)
        ->name('live-statuses.index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard.show');
    Route::get('/electricity-events/recently-resolved', RecentlyResolvedElectricityEventsController::class)
        ->name('electricity-events.recently-resolved.index');
});
