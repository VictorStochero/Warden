<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

class IncidentController
{
    use ResolvesContext;

    public function index(DashboardRepository $repo, string $project): View
    {
        $model = $repo->project($project);

        return ViewFactory::make('warden::incidents.index', array_merge($this->chrome(), [
            'project' => $model,
            'incidents' => $repo->incidents($model->id, 100),
        ]));
    }

    public function show(DashboardRepository $repo, string $project, int $incident): View
    {
        $model = $repo->project($project);
        $row = $repo->incident($model->id, $incident);

        abort_if($row === null, 404);

        // For issue-backed incidents, surface a direct jump to the trace of the
        // last occurrence so the operator goes incident → waterfall in one click.
        // Resolved here (read layer); the view stays query-free.
        $errorTraceId = null;
        $issueId = $row->meta['issue_id'] ?? null;
        if ($issueId !== null) {
            $issue = $repo->issue($model->id, Cast::int($issueId));
            $errorTraceId = $issue?->last_trace_id ?: null;
        }

        return ViewFactory::make('warden::incidents.show', array_merge($this->chrome(), [
            'project' => $model,
            'incident' => $row,
            'errorTraceId' => $errorTraceId,
        ]));
    }

    public function resolve(DashboardRepository $repo, string $project, int $incident): RedirectResponse
    {
        $model = $repo->project($project);
        $row = $repo->incident($model->id, $incident);

        abort_if($row === null, 404);

        if ($row->status === 'open') {
            $row->forceFill(['status' => 'resolved', 'resolved_at' => Carbon::now()])->save();
        }

        return redirect()->route('warden.incident', [$model->slug, $row->id])
            ->with('warden_status', 'Incident resolved.');
    }
}
