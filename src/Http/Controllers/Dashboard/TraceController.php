<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

class TraceController
{
    use ResolvesContext;

    public function index(Request $request, DashboardRepository $repo, string $project): View
    {
        $model = $repo->project($project);

        return ViewFactory::make('warden::traces.index', array_merge($this->chrome(), [
            'project' => $model,
            'traces' => $repo->recentTraces($model->id, 60),
        ]));
    }

    public function show(Request $request, DashboardRepository $repo, string $project, string $traceId): View
    {
        $model = $repo->project($project);

        // Fleet stitching (§29): if the trace was propagated across apps, gather
        // all of them into one waterfall labelled by app; otherwise the single
        // project's spans as before.
        $apps = $repo->traceProjects($traceId);
        $crossApp = $apps->count() > 1;

        $spans = $crossApp
            ? $repo->distributedTrace($traceId, $apps)
            : $repo->trace($model->id, $traceId);

        abort_if($spans->isEmpty(), 404);

        return ViewFactory::make('warden::traces.show', array_merge($this->chrome(), [
            'project' => $model,
            'trace_id' => $traceId,
            'spans' => $spans,
            'crossApp' => $crossApp,
            'apps' => $apps,
        ]));
    }
}
