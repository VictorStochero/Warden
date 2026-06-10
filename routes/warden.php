<?php

use Illuminate\Support\Facades\Route;
use VictorStochero\Warden\Http\Controllers\DeadLetterController;
use VictorStochero\Warden\Http\Controllers\IngestController;

/*
 * Parent-side ingestion route. Registered only when mode = parent. The rate
 * limiter name "warden-ingest" is bound in the service provider from
 * config('warden.parent.rate_limit').
 */
Route::post('ingest', IngestController::class)
    ->middleware('throttle:warden-ingest')
    ->name('warden.ingest');

Route::post('dead-letter', DeadLetterController::class)
    ->middleware('throttle:warden-ingest')
    ->name('warden.deadletter');
