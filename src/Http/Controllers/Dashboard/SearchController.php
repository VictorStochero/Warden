<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Support\Cast;

/**
 * Read-only global search endpoint (behind viewWarden gate).
 * Delegates all DB reads to DashboardRepository::search() and builds
 * navigable URLs via route() so callers (JS palette) get ready-to-use links.
 */
class SearchController
{
    public function index(Request $request, DashboardRepository $repo): JsonResponse
    {
        $term = Cast::str($request->query('q'));
        $slugParam = Cast::str($request->query('project'));

        // Resolve project from slug once — reused for id, name and slug below.
        // A second fetch would be a redundant firstOrFail() query for the same row.
        $project = null;
        $projectId = null;
        if ($slugParam !== '') {
            try {
                $project = $repo->project($slugParam);
                $projectId = $project->id;
            } catch (\Throwable) {
                // Unknown slug — silently drop project context.
            }
        }

        $results = $repo->search($term, $projectId);

        $groups = [];

        // --- Projects ---
        $projectItems = [];
        foreach ($results['projects'] as $p) {
            $projectItems[] = [
                'type' => 'project',
                'label' => $p['name'],
                'sublabel' => $p['slug'],
                'url' => route('warden.project', ['project' => $p['slug']]),
            ];
        }
        $groups[] = ['type' => 'projects', 'items' => $projectItems];

        // --- Routes (only when project context exists) ---
        $routeItems = [];
        if ($project !== null) {
            foreach ($results['routes'] as $r) {
                $routeItems[] = [
                    'type' => 'route',
                    'label' => $r['route'],
                    'sublabel' => $project->name,
                    'url' => route('warden.traces', ['project' => $project->slug]).'?route='.urlencode($r['route']),
                ];
            }
        }
        $groups[] = ['type' => 'routes', 'items' => $routeItems];

        // --- Issues ---
        $issueItems = [];
        if ($projectId !== null && $slugParam !== '') {
            foreach ($results['issues'] as $issue) {
                $issueItems[] = [
                    'type' => 'issue',
                    'label' => $issue['class'],
                    'sublabel' => $issue['message'],
                    'url' => route('warden.issue', ['project' => $slugParam, 'issue' => $issue['id']]),
                ];
            }
        }
        $groups[] = ['type' => 'issues', 'items' => $issueItems];

        // --- Traces ---
        $traceItems = [];
        if ($projectId !== null && $slugParam !== '') {
            foreach ($results['traces'] as $t) {
                $traceItems[] = [
                    'type' => 'trace',
                    'label' => $t['label'],
                    'sublabel' => $t['trace_id'],
                    'url' => route('warden.trace', ['project' => $slugParam, 'traceId' => $t['trace_id']]),
                ];
            }
        }
        $groups[] = ['type' => 'traces', 'items' => $traceItems];

        return response()->json(['groups' => $groups]);
    }
}
