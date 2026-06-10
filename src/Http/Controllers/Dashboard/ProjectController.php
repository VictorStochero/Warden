<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

class ProjectController
{
    use ResolvesContext;

    public function show(Request $request, DashboardRepository $repo, string $project, string $section = 'overview'): View
    {
        $model = $repo->project($project);
        $range = $this->range($request);
        $id = $model->id;

        $data = [
            'kpis' => $repo->kpis($id, $range),
            'section' => $section,
        ];

        $data += match ($section) {
            'requests' => [
                'series' => $repo->requestSeries($id, $range),
                'routes' => $repo->topRoutes($id, $range, 50),
                'recent' => $repo->recentEvents($id, 'request', 60),
            ],
            'errors' => [
                'series' => $repo->requestSeries($id, $range),
                'routes' => $repo->topRoutes($id, $range, 50),
                'recent' => $repo->recentErrors($id, 50),
                'exceptions' => $repo->recentEvents($id, 'exception', 50),
            ],
            'queries' => [
                'slow' => $repo->slowQueries($id, $range, 25),
                'frequent' => $repo->frequentQueries($id, $range, 25),
            ],
            'jobs' => [
                'queues' => $repo->queues($id, $range),
                'recent' => $repo->recentEvents($id, 'job', 60),
            ],
            'cache' => ['stores' => $repo->cacheStores($id, $range)],
            'http' => [
                'hosts' => $repo->httpHosts($id, $range),
                'recent' => $repo->recentEvents($id, 'http', 60),
            ],
            'schedule' => [
                'tasks' => $repo->scheduleTasks($id, $range),
                'heartbeats' => $repo->heartbeats($id),
                'recent' => $repo->recentEvents($id, 'schedule', 60),
            ],
            'logs' => [
                'levels' => $repo->breakdown($id, 'log', $range),
                'recent' => $repo->recentLogs($id, $logLevel = $this->logLevel($request), 100),
                'activeLevel' => $logLevel,
            ],
            'mail' => [
                'mailers' => $repo->breakdown($id, 'mail', $range),
                'notifications' => $repo->breakdown($id, 'notification', $range),
                'recent_mail' => $repo->recentEvents($id, 'mail', 50),
                'recent_notifications' => $repo->recentEvents($id, 'notification', 50),
            ],
            'host' => [
                'latest' => $repo->hostLatest($id, $range),
                'series' => $repo->hostSeries($id, $range),
            ],
            'security' => ['audit' => $repo->recentEvents($id, 'security', 1)->first()],
            'delivery' => ['delivery' => $repo->delivery($id, 60)],
            'uptime' => [
                'windows' => $repo->uptimeWindows($id, $model->uptime_window),
                'incidents' => $repo->downtimeIncidents($id, 30),
            ],
            default => [
                'series' => $repo->requestSeries($id, $range),
                'routes' => $repo->topRoutes($id, $range, 8),
                'slow' => $repo->slowQueries($id, $range, 6),
                'queues' => $repo->queues($id, $range),
                'recent_traces' => $repo->recentTraces($id, 12),
                'recent_issues' => $repo->recentIssues($id, 6),
                'incidents' => $repo->incidents($id, 6),
                'heartbeats' => $repo->heartbeats($id),
            ],
        };

        return ViewFactory::make('warden::project', array_merge($this->chrome(), $data, [
            'project' => $model,
            'range' => $range,
        ]));
    }

    /** Validated log level from the query string, or null for "all". */
    private function logLevel(Request $request): ?string
    {
        $valid = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        $level = $request->query('level');

        return is_string($level) && in_array($level, $valid, true) ? $level : null;
    }
}
