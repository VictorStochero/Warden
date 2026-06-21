<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\CaptureStatus;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;

class OverviewController
{
    use ResolvesContext;

    public function index(Request $request, DashboardRepository $repo): View
    {
        $group = trim(Cast::str($request->query('group')));
        $tag = trim(Cast::str($request->query('tag')));

        $filters = [];
        if ($group !== '') {
            $filters['group'] = $group;
        }
        if ($tag !== '') {
            $filters['tag'] = $tag;
        }

        return ViewFactory::make('warden::overview', array_merge($this->chrome(), [
            'overview' => $repo->overview($filters),
            'activeGroup' => $group !== '' ? $group : null,
            'activeTag' => $tag !== '' ? $tag : null,
            'selfSlug' => Cast::str(config('warden.parent.self_project', 'parent'), 'parent'),
            'captureFlags' => $this->captureFlags(),
        ]));
    }

    /**
     * Map of project slug => true when its capture is reduced (lean/custom with
     * recorders off or a query threshold), for the discreet overview badge.
     *
     * @return array<string, bool>
     */
    private function captureFlags(): array
    {
        $flags = [];

        foreach (Project::query()->get(['slug', 'config', 'capture_profile']) as $project) {
            $flags[$project->slug] = CaptureStatus::forProject($project)['reduced'];
        }

        return $flags;
    }
}
