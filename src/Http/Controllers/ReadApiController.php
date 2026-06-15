<?php

namespace VictorStochero\Warden\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

/**
 * Read-only JSON API (§5.7) for automation and external dashboards. Token-authed
 * (AuthorizeApiToken) and served from the same read layer the dashboard uses, so
 * it never touches the raw stream directly (RNF-6).
 */
class ReadApiController
{
    use ResolvesContext;

    public function overview(DashboardRepository $repo): JsonResponse
    {
        return response()->json($repo->overview([]));
    }

    public function project(Request $request, DashboardRepository $repo, string $project): JsonResponse
    {
        $model = $repo->project($project);
        $range = $this->range($request);

        return response()->json([
            'project' => ['id' => $model->id, 'name' => $model->name, 'slug' => $model->slug],
            'range' => $range,
            'kpis' => $repo->kpis($model->id, $range),
        ]);
    }
}
