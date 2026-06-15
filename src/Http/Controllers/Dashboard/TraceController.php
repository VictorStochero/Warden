<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

/**
 * @phpstan-import-type TraceRow from DashboardRepository
 */
class TraceController
{
    use ResolvesContext;

    public function index(Request $request, DashboardRepository $repo, string $project): View
    {
        $model = $repo->project($project);

        [$traces, $filter] = $this->filteredTraces($request, $repo, $model->id);

        return ViewFactory::make('warden::traces.index', array_merge($this->chrome(), [
            'project' => $model,
            'traces' => $traces,
            'filter' => $filter,
        ]));
    }

    /**
     * Resolve the trace list from the drill-down query string. `route` filters by
     * the entry-point route; one of `query|http|job|cache` filters by traces that
     * contain a matching event of that dimension. Absent → recent traces.
     *
     * @return array{0: Collection<int, TraceRow>, 1: array{type: string, value: string}|null}
     */
    private function filteredTraces(Request $request, DashboardRepository $repo, int $projectId): array
    {
        // `route` takes precedence over query|http|job|cache when multiple params are sent simultaneously.
        $route = $request->query('route');
        if (is_string($route) && $route !== '') {
            return [$repo->tracesByRoute($projectId, $route, 60), ['type' => 'route', 'value' => $route]];
        }

        foreach (['query', 'http', 'job', 'cache'] as $type) {
            $value = $request->query($type);
            if (is_string($value) && $value !== '') {
                return [$repo->tracesContaining($projectId, $type, $value, 60), ['type' => $type, 'value' => $value]];
            }
        }

        return [$repo->recentTraces($projectId, 60), null];
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
