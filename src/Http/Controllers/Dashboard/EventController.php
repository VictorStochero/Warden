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

        return ViewFactory::make('warden::events.show', array_merge($this->chrome(), [
            'project' => $model,
            'event' => $row,
            // An exception links back to the issue it is grouped under, so the
            // raw occurrence and its rollup are one click apart.
            'issue' => $row->type === 'exception'
                ? $repo->issueForExceptionPayload($model->id, Cast::arr($row->payload))
                : null,
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }
}
