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
        $spans = $repo->trace($model->id, $traceId);

        abort_if($spans->isEmpty(), 404);

        return ViewFactory::make('warden::traces.show', array_merge($this->chrome(), [
            'project' => $model,
            'trace_id' => $traceId,
            'spans' => $spans,
        ]));
    }
}
