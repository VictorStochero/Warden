<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

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

        return ViewFactory::make('warden::incidents.show', array_merge($this->chrome(), [
            'project' => $model,
            'incident' => $row,
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
