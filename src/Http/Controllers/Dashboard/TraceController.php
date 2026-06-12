<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

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

        // Exception spans link out to the issue they are grouped under
        // (event id => issue row), so the timeline is a hub, not a dead end.
        $issues = [];
        foreach ($spans as $span) {
            if ($span['type'] === 'exception') {
                $issue = $repo->issueForExceptionPayload($model->id, Cast::arr($span['payload']));

                if ($issue !== null) {
                    $issues[Cast::int($span['id'])] = $issue;
                }
            }
        }

        return ViewFactory::make('warden::traces.show', array_merge($this->chrome(), [
            'project' => $model,
            'trace_id' => $traceId,
            'spans' => $spans,
            'issues' => $issues,
        ]));
    }
}
