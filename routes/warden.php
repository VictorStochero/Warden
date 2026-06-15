<?php

use Illuminate\Support\Facades\Route;
use VictorStochero\Warden\Http\Controllers\DeadLetterController;
use VictorStochero\Warden\Http\Controllers\IngestController;
use VictorStochero\Warden\Http\Controllers\ReadApiController;
use VictorStochero\Warden\Http\Middleware\AuthorizeApiToken;

/*
 * Parent-side ingestion route. Registered only when mode = parent. The rate
 * limiter name "warden-ingest" is bound in the service provider from
 * config('warden.parent.rate_limit').
 */
Route::post('ingest', IngestController::class)
    ->middleware('throttle:warden-ingest')
    ->name('warden.ingest');

Route::post('dead-letter', DeadLetterController::class)
    ->middleware('throttle:warden-deadletter')
    ->name('warden.deadletter');

/*
 * Read-only JSON API (§5.7), authenticated by an API token (Bearer). For
 * automation, status pages and external dashboards.
 */
Route::middleware(AuthorizeApiToken::class)->prefix('api/v1')->group(function () {
    Route::get('overview', [ReadApiController::class, 'overview'])->name('warden.api.overview');
    Route::get('projects/{project}', [ReadApiController::class, 'project'])->name('warden.api.project');
});
