<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Cast;

/**
 * @phpstan-import-type TraceRow from DashboardRepository
 * @phpstan-import-type TraceSpan from \VictorStochero\Warden\Contracts\WardenRepository
 */
class TraceController
{
    use ResolvesContext;

    public function index(Request $request, DashboardRepository $repo, string $project): View
    {
        $model = $repo->project($project);

        [$traces, $filter] = $this->filteredTraces($request, $repo, $model->id);

        return ViewFactory::make('warden::traces.index', array_merge($this->chrome(), $this->related($repo, $model->id), [
            'project' => $model,
            'traces' => $traces,
            'filter' => $filter,
        ]));
    }

    /**
     * Resolve the trace list from the drill-down query string. `route` filters by
     * the entry-point route; one of `query|http|job|cache` filters by traces that
     * contain a matching event of that dimension. Absent → recent traces.
     *
     * @return array{0: Collection<int, TraceRow>, 1: array{type: string, value: string}|null}
     */
    private function filteredTraces(Request $request, DashboardRepository $repo, int $projectId): array
    {
        // `route` takes precedence over query|http|job|cache when multiple params are sent simultaneously.
        $route = $request->query('route');
        if (is_string($route) && $route !== '') {
            return [$repo->tracesByRoute($projectId, $route, 60), ['type' => 'route', 'value' => $route]];
        }

        foreach (['query', 'http', 'job', 'cache'] as $type) {
            $value = $request->query($type);
            if (is_string($value) && $value !== '') {
                return [$repo->tracesContaining($projectId, $type, $value, 60), ['type' => $type, 'value' => $value]];
            }
        }

        return [$repo->recentTraces($projectId, 60), null];
    }

    public function show(Request $request, DashboardRepository $repo, string $project, string $traceId): View
    {
        $model = $repo->project($project);

        // Fleet stitching (§29): if the trace was propagated across apps, gather
        // all of them into one waterfall labelled by app; otherwise the single
        // project's spans as before.
        $apps = $repo->traceProjects($traceId);
        $crossApp = $apps->count() > 1;

        $spans = $crossApp
            ? $repo->distributedTrace($traceId, $apps)
            : $repo->trace($model->id, $traceId);

        abort_if($spans->isEmpty(), 404);

        return ViewFactory::make('warden::traces.show', array_merge($this->chrome(), $this->related($repo, $model->id, $traceId), [
            'project' => $model,
            'trace_id' => $traceId,
            'spans' => $spans,
            'crossApp' => $crossApp,
            'apps' => $apps,
            'issueLinks' => $this->issueLinks($repo, $model->id, $spans),
        ]));
    }

    /**
     * Map exception spans to their grouped issue id, keyed by span identifier so
     * the view can link a single span to its issue without touching the repo.
     * The fingerprint is derived exactly as `IssueProcessor` does, so it lines up
     * with `wdn_issues.fingerprint`. The read happens here (read layer), one
     * lookup per distinct fingerprint in the waterfall.
     *
     * @param  Collection<int, TraceSpan>|Collection<int, array<string, mixed>>  $spans
     * @return array<string, int> span key (span_id, else span index) → issue id
     */
    private function issueLinks(DashboardRepository $repo, int $projectId, Collection $spans): array
    {
        $links = [];
        $resolved = [];

        foreach ($spans as $index => $span) {
            if (($span['type'] ?? null) !== 'exception') {
                continue;
            }

            $payload = Cast::arr($span['payload'] ?? null);
            $fingerprint = Fingerprint::forPayload($payload);

            if (! array_key_exists($fingerprint, $resolved)) {
                $issue = $repo->issueByFingerprint($projectId, $fingerprint);
                $resolved[$fingerprint] = $issue !== null ? Cast::int($issue->id) : null;
            }

            $issueId = $resolved[$fingerprint];
            if ($issueId === null) {
                continue;
            }

            $key = Cast::str($span['span_id'] ?? null) ?: 'i'.$index;
            $links[$key] = $issueId;
        }

        return $links;
    }
}
