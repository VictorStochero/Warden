<?php

use Illuminate\Support\Facades\Route;
use VictorStochero\Warden\Http\Controllers\Dashboard\IncidentController;
use VictorStochero\Warden\Http\Controllers\Dashboard\IssueController;
use VictorStochero\Warden\Http\Controllers\Dashboard\MaintenanceController;
use VictorStochero\Warden\Http\Controllers\Dashboard\OverviewController;
use VictorStochero\Warden\Http\Controllers\Dashboard\ProjectAdminController;
use VictorStochero\Warden\Http\Controllers\Dashboard\ProjectController;
use VictorStochero\Warden\Http\Controllers\Dashboard\SettingsController;
use VictorStochero\Warden\Http\Controllers\Dashboard\TraceController;
use VictorStochero\Warden\Http\Middleware\Authorize;

/*
 * Parent dashboard routes. Registered under config('warden.parent.route_prefix')
 * and guarded by the "viewWarden" ability. Pure Blade — no build step.
 */
Route::get('/', [OverviewController::class, 'index'])->name('warden.overview');

Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('warden.project');
Route::get('/projects/{project}/{section}', [ProjectController::class, 'show'])
    ->whereIn('section', ['requests', 'errors', 'queries', 'jobs', 'cache', 'schedule', 'http', 'logs', 'mail', 'host', 'security', 'delivery', 'uptime'])
    ->name('warden.project.section');

Route::get('/projects/{project}/issues/list', [IssueController::class, 'index'])->name('warden.issues');
Route::get('/projects/{project}/issues/{issue}', [IssueController::class, 'show'])->name('warden.issue');

Route::get('/projects/{project}/traces/list', [TraceController::class, 'index'])->name('warden.traces');
Route::get('/projects/{project}/traces/{traceId}', [TraceController::class, 'show'])->name('warden.trace');

Route::get('/projects/{project}/incidents/list', [IncidentController::class, 'index'])->name('warden.incidents');
Route::get('/projects/{project}/incidents/{incident}', [IncidentController::class, 'show'])->name('warden.incident');

Route::middleware(Authorize::class.':manageWarden')->group(function () {
    Route::get('/admin/projects', [ProjectAdminController::class, 'index'])->name('warden.admin.projects');
    Route::post('/admin/projects', [ProjectAdminController::class, 'store'])->name('warden.admin.projects.store');
    Route::get('/admin/projects/{project}/edit', [ProjectAdminController::class, 'edit'])->name('warden.admin.projects.edit');
    Route::post('/admin/projects/{project}', [ProjectAdminController::class, 'update'])->name('warden.admin.projects.update');
    Route::post('/admin/projects/{project}/rotate', [ProjectAdminController::class, 'rotate'])->name('warden.admin.projects.rotate');
    Route::post('/admin/projects/{project}/toggle', [ProjectAdminController::class, 'toggle'])->name('warden.admin.projects.toggle');
    Route::post('/admin/projects/{project}/reset', [ProjectAdminController::class, 'reset'])->name('warden.admin.projects.reset');
    Route::post('/admin/projects/{project}/audit-now', [ProjectAdminController::class, 'auditNow'])->name('warden.admin.projects.audit-now');
    Route::post('/admin/projects/{project}/timezone', [ProjectAdminController::class, 'timezone'])->name('warden.admin.projects.timezone');

    Route::get('/admin/maintenance', [MaintenanceController::class, 'index'])->name('warden.admin.maintenance');
    Route::post('/admin/maintenance/run', [MaintenanceController::class, 'run'])->name('warden.admin.maintenance.run');

    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('warden.admin.settings');
    Route::post('/admin/settings', [SettingsController::class, 'update'])->name('warden.admin.settings.update');

    Route::post('/projects/{project}/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('warden.incident.resolve');
});
