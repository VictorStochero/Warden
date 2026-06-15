<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Dashboard\StreamCursor;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

/**
 * Real-time transport endpoint (§5.4): a single cursor-based conditional GET.
 * The client polls with the last ETag; when nothing moved we answer 304 and the
 * aggregate read never runs (idle polls are nearly free). Pure JSON deltas, no
 * markup round-trip — the opposite of a Livewire/htmx model — so it runs on
 * bare PHP-FPM today and upgrades to SSE later behind the same payload.
 */
class StreamController
{
    use ResolvesContext;

    public function project(Request $request, string $project, DashboardRepository $repo, StreamCursor $cursor): Response|JsonResponse
    {
        $proj = $repo->project($project);
        $range = $this->range($request);

        $token = $cursor->forProject($proj->id, $range);

        // Probe the ETag before touching the heavy read: an unchanged state
        // short-circuits to 304 with an empty body and zero aggregate queries.
        $probe = response('')->setEtag($token);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        return response()
            ->json([
                'cursor' => $token,
                'range' => $range,
                'kpis' => $repo->kpis($proj->id, $range),
            ])
            ->setEtag($token);
    }

    public function overview(Request $request, DashboardRepository $repo, StreamCursor $cursor): Response|JsonResponse
    {
        $group = trim(Cast::str($request->query('group')));
        $tag = trim(Cast::str($request->query('tag')));

        $token = $cursor->forOverview($group.'|'.$tag);

        $probe = response('')->setEtag($token);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $filters = [];
        if ($group !== '') {
            $filters['group'] = $group;
        }
        if ($tag !== '') {
            $filters['tag'] = $tag;
        }

        $overview = $repo->overview($filters);

        return response()
            ->json([
                'cursor' => $token,
                'open_issues' => $overview['open_issues'],
                'open_incidents' => $overview['open_incidents'],
                'throughput' => $overview['throughput'],
                'projects' => $overview['projects'],
            ])
            ->setEtag($token);
    }
}
