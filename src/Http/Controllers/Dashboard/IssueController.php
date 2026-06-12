<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

class IssueController
{
    use ResolvesContext;

    public function index(Request $request, DashboardRepository $repo, string $project): View
    {
        $model = $repo->project($project);
        $status = in_array($request->query('status'), ['open', 'resolved', 'ignored'], true)
            ? Cast::str($request->query('status'))
            : 'open';

        return ViewFactory::make('warden::issues.index', array_merge($this->chrome(), [
            'project' => $model,
            'status' => $status,
            'issues' => $repo->issues($model->id, ['status' => $status, 'limit' => 200]),
        ]));
    }

    public function show(Request $request, DashboardRepository $repo, string $project, int $issue): View
    {
        $model = $repo->project($project);
        $row = $repo->issue($model->id, $issue);

        abort_if($row === null, 404);

        return ViewFactory::make('warden::issues.show', array_merge($this->chrome(), [
            'project' => $model,
            'issue' => $row,
            // Each raw occurrence links to its event detail and trace, so the
            // rollup and the concrete failures are one click apart.
            'occurrences' => $repo->issueOccurrences($model->id, Cast::str($row->fingerprint)),
        ]));
    }
}
