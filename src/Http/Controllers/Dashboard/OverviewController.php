<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
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
        ]));
    }
}
