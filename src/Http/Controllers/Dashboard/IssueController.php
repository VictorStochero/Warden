<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Issues\IssueWorkflow;
use VictorStochero\Warden\Models\Issue;
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

        return ViewFactory::make('warden::issues.index', array_merge($this->chrome(), $this->related($repo, $model->id), [
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

        $traceId = isset($row->last_trace_id) ? (Cast::str($row->last_trace_id) ?: null) : null;

        return ViewFactory::make('warden::issues.show', array_merge($this->chrome(), $this->related($repo, $model->id, $traceId), [
            'project' => $model,
            'issue' => $row,
        ]));
    }

    public function resolve(DashboardRepository $repo, IssueWorkflow $workflow, string $project, int $issue): RedirectResponse
    {
        $workflow->resolve($this->issueModel($repo, $project, $issue));

        return $this->back($project, $issue, 'resolved');
    }

    public function ignore(DashboardRepository $repo, IssueWorkflow $workflow, string $project, int $issue): RedirectResponse
    {
        $workflow->ignore($this->issueModel($repo, $project, $issue));

        return $this->back($project, $issue, 'ignored');
    }

    public function reopen(DashboardRepository $repo, IssueWorkflow $workflow, string $project, int $issue): RedirectResponse
    {
        $workflow->reopen($this->issueModel($repo, $project, $issue));

        return $this->back($project, $issue, 'reopened');
    }

    public function assign(Request $request, DashboardRepository $repo, IssueWorkflow $workflow, string $project, int $issue): RedirectResponse
    {
        $assignee = trim(Cast::str($request->input('assignee')));
        $workflow->assign($this->issueModel($repo, $project, $issue), $assignee === '' ? null : $assignee);

        return $this->back($project, $issue, 'assigned');
    }

    public function snooze(Request $request, DashboardRepository $repo, IssueWorkflow $workflow, string $project, int $issue): RedirectResponse
    {
        $minutes = max(1, Cast::int($request->input('minutes'), 60));
        $workflow->snooze($this->issueModel($repo, $project, $issue), $minutes);

        return $this->back($project, $issue, 'snoozed');
    }

    /** Load the issue scoped to its project, 404 if it doesn't belong there. */
    private function issueModel(DashboardRepository $repo, string $project, int $issue): Issue
    {
        $projectId = $repo->project($project)->id;

        return Issue::query()->where('project_id', $projectId)->findOrFail($issue);
    }

    private function back(string $project, int $issue, string $action): RedirectResponse
    {
        return redirect()
            ->route('warden.issue', [$project, $issue])
            ->with('warden_status', __('warden::issues.actions.status_'.$action));
    }
}
