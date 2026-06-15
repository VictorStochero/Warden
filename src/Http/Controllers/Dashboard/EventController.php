<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

class EventController
{
    use ResolvesContext;

    /** Rich, per-event detail — every captured field for one raw event. */
    public function show(Request $request, DashboardRepository $repo, string $project, int $event): View
    {
        $model = $repo->project($project);
        $row = $repo->event($model->id, $event);

        abort_if($row === null, 404);

        $traceId = isset($row->trace_id) ? (Cast::str($row->trace_id) ?: null) : null;

        return ViewFactory::make('warden::events.show', array_merge($this->chrome(), $this->related($repo, $model->id, $traceId), [
            'project' => $model,
            'event' => $row,
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }
}
