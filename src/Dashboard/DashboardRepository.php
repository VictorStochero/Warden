<?php

namespace VictorStochero\Warden\Dashboard;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VictorStochero\Warden\Analysis\QueryHealthAnalyzer;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;

/**
 * UI-facing read layer for the dashboard. Builds the dashboard views from the
 * small wdn_aggregates table (and wdn_events only for traces), so the dashboard
 * never scans the raw stream. Honours RNF-6: this is the read surface, no UI
 * library or external package involved.
 *
 * `meta` is the one genuinely heterogeneous value (decoded JSON), so it stays
 * array<string,mixed>; every derived figure is narrowed to a concrete type.
 *
 * @phpstan-import-type TraceSpan from \VictorStochero\Warden\Contracts\WardenRepository
 *
 * @phpstan-type AggRow array{bucket:string, key:string, count:int, sum_duration:int, max_duration:int, meta:array<string,mixed>}
 * @phpstan-type Overview array{projects:Collection<int,\stdClass>, open_issues:int, open_incidents:int, throughput:int, groups:Collection<int,\stdClass>, tags:Collection<int,\stdClass>}
 * @phpstan-type Kpis array{throughput:int, error_rate:float, errors:int, p95:int|null, slow:int, failed_jobs:int, cache_hit_rate:float|null, open_issues:int, open_incidents:int, host:array<string,mixed>|null, uptime:float}
 * @phpstan-type SeriesPoint array{bucket:string, count:int, errors:int, p95:int|null}
 * @phpstan-type RouteRow array{key:string, count:int, avg:int, max:int, errors:int, p95:int|null}
 * @phpstan-type QueryRow array{key:string, sql:string, count:int, avg:int, max:int, slow:int, total:int}
 * @phpstan-type QueueRow array{key:string, count:int, processed:int, failures:int, avg:int, max:int}
 * @phpstan-type CacheRow array{key:string, hits:int, misses:int, rate:float, writes:int}
 * @phpstan-type HttpRow array{key:string, count:int, errors:int, avg:int, max:int}
 * @phpstan-type BreakdownRow array{key:string, count:int, avg:int, max:int}
 * @phpstan-type HeartbeatRow array{key:string, last_seen:string|null, interval:int, healthy:bool}
 * @phpstan-type HostPoint array{bucket:string, cpu:float|null, mem:float|null}
 * @phpstan-type TraceRow array{trace_id:string, type:string, label:string, duration_us:int, occurred_at:string|null, errored:bool}
 */
class DashboardRepository
{
    /**
     * Latency histogram edges, must match the aggregator.
     *
     * @var list<int>
     */
    protected array $edges = [10, 50, 100, 250, 500, 1000, 2500, 5000];

    /**
     * Per-instance memo of aggregate reads. A single page render asks for the
     * same (project, type, range) slice from several sections (kpis, series,
     * top-routes…); without this each one re-queries wdn_aggregates. The
     * repository is resolved fresh per request (not a singleton), so the cache
     * is naturally request-scoped and never leaks across Octane requests.
     *
     * @var array<string, Collection<int, AggRow>>
     */
    protected array $rowsCache = [];

    public function __construct(
        protected Connection $db,
        protected DatabaseWardenRepository $reader,
    ) {}

    // -------------------------------------------------------- overview

    /**
     * @param  array<string, mixed>  $filters  optional `group` / `tag` slugs
     * @return Overview
     */
    public function overview(array $filters = []): array
    {
        $projects = $this->reader->projects($filters);

        return [
            'projects' => $projects,
            'open_issues' => $this->db->table('wdn_issues')->where('status', 'open')->count(),
            'open_incidents' => $this->db->table('wdn_incidents')->where('status', 'open')->count(),
            'throughput' => Cast::int($projects->sum('throughput')),
            'groups' => $this->db->table('wdn_groups')->orderBy('name')->get(['name', 'slug']),
            'tags' => $this->db->table('wdn_tags')->orderBy('name')->get(['name', 'slug']),
        ];
    }

    public function project(string $idOrSlug): Project
    {
        // Match by slug, and by id only when the value is numeric — comparing a
        // bigint column to a non-numeric string errors on strict drivers (pgsql).
        return Project::query()
            ->where('slug', $idOrSlug)
            ->when(ctype_digit($idOrSlug), fn ($q) => $q->orWhere('id', (int) $idOrSlug))
            ->firstOrFail();
    }

    // ----------------------------------------------------------- KPIs

    /** @return Kpis */
    public function kpis(int $projectId, string $range): array
    {
        $requests = $this->rows($projectId, 'request', $range);
        $count = Cast::int($requests->sum('count'));
        $errors = $this->sumMeta($requests, 'errors');
        $slow = $this->sumMeta($requests, 'slow');

        $cache = $this->rows($projectId, 'cache', $range);
        $hits = $this->sumMeta($cache, 'hits');
        $misses = $this->sumMeta($cache, 'misses');

        $failed = $this->sumMeta($this->rows($projectId, 'job', $range), 'failures');

        return [
            'throughput' => $count,
            'error_rate' => $count > 0 ? round($errors / $count * 100, 2) : 0.0,
            'errors' => $errors,
            'p95' => $this->percentile($this->mergeHistograms($requests)),
            'slow' => $slow,
            'failed_jobs' => $failed,
            'cache_hit_rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : null,
            'open_issues' => $this->db->table('wdn_issues')->where('project_id', $projectId)->where('status', 'open')->count(),
            'open_incidents' => $this->db->table('wdn_incidents')->where('project_id', $projectId)->where('status', 'open')->count(),
            'host' => $this->hostLatest($projectId, $range),
            'uptime' => $this->uptime($projectId),
        ];
    }

    /** Availability over a window (default 30d), derived from critical incidents. */
    public function uptime(int $projectId, string $range = '30d'): float
    {
        return $this->reader->uptime($projectId, $range);
    }

    /**
     * Uptime across the standard windows for the Uptime history section. The
     * project's configured window (default 30d) is flagged so the view can
     * highlight it as the headline KPI.
     *
     * @return list<array{label:string, pct:float, active:bool}>
     */
    public function uptimeWindows(int $projectId, string $configured = '30d'): array
    {
        $windows = ['24h', '7d', '30d'];
        if (! in_array($configured, $windows, true)) {
            $configured = '30d';
        }

        return array_map(fn (string $w): array => [
            'label' => $w,
            'pct' => $this->reader->uptime($projectId, $w),
            'active' => $w === $configured,
        ], $windows);
    }

    /**
     * Critical incidents (downtime episodes) in the window, newest first.
     *
     * @return Collection<int, \stdClass>
     */
    public function downtimeIncidents(int $projectId, int $days = 30, int $limit = 50): Collection
    {
        $since = Carbon::now()->subDays($days);

        return $this->db->table('wdn_incidents')
            ->where('project_id', $projectId)
            ->where('severity', 'critical')
            ->where(function (Builder $q) use ($since) {
                $q->whereNull('resolved_at')->orWhere('resolved_at', '>=', $since);
            })
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------- requests

    /**
     * Per-bucket throughput / errors / p95 for the request timeline chart.
     *
     * @return Collection<int, SeriesPoint>
     */
    public function requestSeries(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'request', $range)
            ->groupBy('bucket')
            ->map(fn (Collection $rows, int|string $bucket): array => [
                'bucket' => (string) $bucket,
                'count' => Cast::int($rows->sum('count')),
                'errors' => $this->sumMeta($rows, 'errors'),
                'p95' => $this->percentile($this->mergeHistograms($rows)),
            ])
            ->values();
    }

    /** @return Collection<int, RouteRow> */
    public function topRoutes(int $projectId, string $range, int $limit = 12, bool $includeWarden = true): Collection
    {
        $groups = $this->rows($projectId, 'request', $range)->groupBy('key');

        if (! $includeWarden) {
            $groups = $groups->reject(fn (Collection $rows, int|string $key): bool => $this->isWardenRouteName($key));
        }

        return $groups
            ->map(fn (Collection $rows, int|string $key): array => [
                'key' => (string) $key,
                'count' => Cast::int($rows->sum('count')),
                'avg' => $this->avg($rows),
                'max' => Cast::int($rows->max('max_duration')),
                'errors' => $this->sumMeta($rows, 'errors'),
                'p95' => $this->percentile($this->mergeHistograms($rows)),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    // -------------------------------------------------------- queries

    /**
     * Slowest queries by average duration.
     *
     * @return Collection<int, QueryRow>
     */
    public function slowQueries(int $projectId, string $range, int $limit = 15): Collection
    {
        return $this->queryGroups($projectId, $range)->sortByDesc('avg')->take($limit)->values();
    }

    /**
     * Cumulatively expensive queries — individually quick but called a lot (§10).
     *
     * @return Collection<int, QueryRow>
     */
    public function frequentQueries(int $projectId, string $range, int $limit = 15): Collection
    {
        return $this->queryGroups($projectId, $range)->sortByDesc('total')->take($limit)->values();
    }

    /**
     * Query health analysis: reads the latest `query_health_sample` raw query
     * events in the range, delegates to QueryHealthAnalyzer, and returns the
     * structured findings. All raw reads stay in this read layer (RNF-6).
     *
     * @return array{findings: array<string, list<array<string, mixed>>>, sampled: int, limit: int}
     */
    public function queryHealth(int $projectId, string $range): array
    {
        $limit = Cast::int(config('warden.parent.query_health_sample'), 2000);

        $events = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('type', 'query')
            ->where('occurred_at', '>=', $this->rangeStart($range))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['trace_id', 'duration_us', 'payload'])
            ->map(fn (\stdClass $e): array => [
                'trace_id' => isset($e->trace_id) ? Cast::str($e->trace_id) : '',
                'duration_us' => isset($e->duration_us) ? Cast::int($e->duration_us) : 0,
                'payload' => Json::decode($e->payload ?? null),
            ])
            ->all();

        $analyzer = new QueryHealthAnalyzer(
            nPlusOneThreshold: Cast::int(config('warden.parent.n_plus_one_threshold'), 3),
            fatRequestThreshold: Cast::int(config('warden.parent.fat_request_queries'), 50),
            slowQueryUs: Cast::int(config('warden.parent.slow_query_ms'), 100) * 1000,
        );

        return [
            'findings' => $analyzer->analyze($events),
            'sampled' => count($events),
            'limit' => $limit,
        ];
    }

    /** @return Collection<int, QueryRow> */
    protected function queryGroups(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'query', $range)
            ->groupBy('key')
            ->map(fn (Collection $rows, int|string $key): array => [
                'key' => (string) $key,
                'sql' => Cast::str(Cast::arr($rows->first()['meta'] ?? null)['sql'] ?? null, '(unknown)'),
                'count' => Cast::int($rows->sum('count')),
                'avg' => $this->avg($rows),
                'max' => Cast::int($rows->max('max_duration')),
                'slow' => $this->sumMeta($rows, 'slow'),
                'total' => Cast::int($rows->sum('sum_duration')),
            ])
            ->values();
    }

    // ----------------------------------------------------- jobs/cache/http

    /** @return Collection<int, QueueRow> */
    public function queues(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'job', $range)
            ->groupBy('key')
            ->map(fn (Collection $rows, int|string $key): array => [
                'key' => (string) $key,
                'count' => Cast::int($rows->sum('count')),
                'processed' => $this->sumMeta($rows, 'processed'),
                'failures' => $this->sumMeta($rows, 'failures'),
                'avg' => $this->avg($rows),
                'max' => Cast::int($rows->max('max_duration')),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /** @return Collection<int, CacheRow> */
    public function cacheStores(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'cache', $range)
            ->groupBy('key')
            ->map(function (Collection $rows, int|string $key): array {
                $hits = $this->sumMeta($rows, 'hits');
                $misses = $this->sumMeta($rows, 'misses');

                return [
                    'key' => (string) $key,
                    'hits' => $hits,
                    'misses' => $misses,
                    'rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : 0.0,
                    'writes' => Cast::int($rows->sum('count')) - $hits - $misses,
                ];
            })
            ->sortByDesc(fn (array $r): int => $r['hits'] + $r['misses'])
            ->values();
    }

    /** @return Collection<int, HttpRow> */
    public function httpHosts(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'http', $range)
            ->groupBy('key')
            ->map(fn (Collection $rows, int|string $key): array => [
                'key' => (string) $key,
                'count' => Cast::int($rows->sum('count')),
                'errors' => $this->sumMeta($rows, 'errors'),
                'avg' => $this->avg($rows),
                'max' => Cast::int($rows->max('max_duration')),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Generic count-by-key listing for log/mail/notification/command.
     *
     * @return Collection<int, BreakdownRow>
     */
    public function breakdown(int $projectId, string $type, string $range): Collection
    {
        return $this->rows($projectId, $type, $range)
            ->groupBy('key')
            ->map(fn (Collection $rows, int|string $key): array => [
                'key' => (string) $key,
                'count' => Cast::int($rows->sum('count')),
                'avg' => $this->avg($rows),
                'max' => Cast::int($rows->max('max_duration')),
            ])
            ->sortByDesc('count')
            ->values();
    }

    // ---------------------------------------------------- schedule/host

    /** @return Collection<int, HeartbeatRow> */
    public function heartbeats(int $projectId): Collection
    {
        return $this->db->table('wdn_heartbeats')
            ->where('project_id', $projectId)
            ->orderBy('key')
            ->get()
            ->map(function (\stdClass $hb): array {
                $lastSeen = isset($hb->last_seen_at) ? Cast::str($hb->last_seen_at) : null;
                $deadline = $lastSeen !== null
                    ? Carbon::parse($lastSeen, 'UTC')->addSeconds(Cast::int($hb->expected_interval) + Cast::int($hb->grace))
                    : null;

                return [
                    'key' => Cast::str($hb->key),
                    'last_seen' => $lastSeen,
                    'interval' => Cast::int($hb->expected_interval),
                    'healthy' => $deadline !== null && Carbon::now()->lte($deadline),
                ];
            })
            ->values();
    }

    /** @return Collection<int, BreakdownRow> */
    public function scheduleTasks(int $projectId, string $range): Collection
    {
        return $this->breakdown($projectId, 'schedule', $range);
    }

    /** @return array<string, mixed>|null the raw host meta (gauges) */
    public function hostLatest(int $projectId, string $range): ?array
    {
        $row = $this->rows($projectId, 'host', $range)->sortByDesc('bucket')->first();

        return $row['meta'] ?? null;
    }

    /** @return Collection<int, HostPoint> */
    public function hostSeries(int $projectId, string $range): Collection
    {
        return $this->rows($projectId, 'host', $range)
            ->groupBy('bucket')
            ->map(function (Collection $rows, int|string $bucket): array {
                $meta = Cast::arr($rows->first()['meta'] ?? null);

                return [
                    'bucket' => (string) $bucket,
                    'cpu' => isset($meta['cpu']) ? Cast::float($meta['cpu']) : null,
                    'mem' => isset($meta['mem']) ? Cast::float($meta['mem']) : null,
                ];
            })
            ->values();
    }

    // ------------------------------------------------------ traces/issues

    /**
     * Recent entry-point traces (request/command/schedule/job roots).
     *
     * @return Collection<int, TraceRow>
     */
    public function recentTraces(int $projectId, int $limit = 30): Collection
    {
        $rows = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->whereIn('type', ['request', 'command', 'schedule', 'job'])
            ->whereNotNull('trace_id')
            ->orderByDesc('id')
            ->limit($limit * 4)
            ->get(['type', 'trace_id', 'occurred_at', 'duration_us', 'payload']);

        return $rows->groupBy('trace_id')->map(function (Collection $events): array {
            /** @var \stdClass $entry */
            $entry = $events->first();
            $payload = Json::decode($entry->payload ?? null);
            $type = Cast::str($entry->type);

            return [
                'trace_id' => Cast::str($entry->trace_id),
                'type' => $type,
                'label' => $this->traceLabel($type, $payload),
                'duration_us' => Cast::int($entry->duration_us ?? null),
                'occurred_at' => isset($entry->occurred_at) ? Cast::str($entry->occurred_at) : null,
                'errored' => Cast::int($payload['status'] ?? null) >= 500,
            ];
        })->sortByDesc('occurred_at')->take($limit)->values();
    }

    /** @return Collection<int, TraceSpan> the trace spans (see WardenRepository::trace) */
    public function trace(int $projectId, string $traceId): Collection
    {
        return $this->reader->trace($projectId, $traceId);
    }

    /**
     * The dashboard audit trail (§5.7), newest first.
     *
     * @return Collection<int, \stdClass>
     */
    public function auditLog(int $limit = 200): Collection
    {
        return $this->db->table('wdn_audit_log')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The apps a trace touches (§29). Once a trace is propagated across the
     * fleet the same trace_id lands under several projects; this lists them so
     * the viewer can stitch a cross-app waterfall.
     *
     * @return Collection<int, \stdClass>
     */
    public function traceProjects(string $traceId): Collection
    {
        return $this->db->table('wdn_events')
            ->join('wdn_projects', 'wdn_projects.id', '=', 'wdn_events.project_id')
            ->where('wdn_events.trace_id', $traceId)
            ->distinct()
            ->orderBy('wdn_projects.name')
            ->get(['wdn_projects.id', 'wdn_projects.name', 'wdn_projects.slug']);
    }

    /**
     * Gather a propagated trace's spans across every app it touches into one
     * timeline, each span tagged with its origin app. N+1 detection still runs
     * per app (a query storm is an in-app concern).
     *
     * @param  Collection<int, \stdClass>  $projects
     * @return Collection<int, array<string, mixed>>
     */
    public function distributedTrace(string $traceId, Collection $projects): Collection
    {
        return $projects
            ->flatMap(fn (\stdClass $project): Collection => $this->reader->trace(Cast::int($project->id), $traceId)
                ->map(fn (array $span): array => $this->tagSpanApp($span, $project)))
            ->sortBy([['occurred_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Tag a span with its origin app for the cross-app waterfall.
     *
     * @param  array<string, mixed>  $span
     * @return array<string, mixed>
     */
    private function tagSpanApp(array $span, \stdClass $project): array
    {
        $span['project_name'] = Cast::str($project->name);
        $span['project_slug'] = Cast::str($project->slug);

        return $span;
    }

    /**
     * Recent raw events of one type for the per-section drill-down panels. Reads
     * wdn_events directly — scoped to project+type and hard-limited — the same
     * controlled raw access trace() uses, backed by the (project_id, type, id)
     * index. Aggregates stay the default; this is the "show me the actual events"
     * surface for logs, mail, jobs, http, schedule and requests.
     *
     * @return Collection<int, \stdClass>
     */
    public function recentEvents(int $projectId, string $type, int $limit = 50, ?string $range = null): Collection
    {
        $query = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('type', $type);

        if ($range !== null) {
            $query->where('occurred_at', '>=', $this->rangeStart($range));
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'trace_id', 'span_id', 'occurred_at', 'duration_us', 'payload', 'release'])
            ->map(function (\stdClass $e): \stdClass {
                $e->payload = Json::decode($e->payload ?? null);

                return $e;
            });
    }

    /**
     * Recent request events for the Requests drill-down. On a self-monitoring
     * parent the dashboard's own `warden.*` traffic (the live poller especially)
     * floods this list, so it is hidden by default; `$includeWarden` (the
     * `?warden=1` toggle) brings it back. Mirrors recentLogs: when filtering we
     * scan a wider window and trim in PHP so the result stays driver-portable.
     *
     * @return Collection<int, \stdClass>
     */
    public function recentRequests(int $projectId, int $limit = 60, ?string $range = null, bool $includeWarden = true): Collection
    {
        if ($includeWarden) {
            return $this->recentEvents($projectId, 'request', $limit, $range);
        }

        return $this->recentEvents($projectId, 'request', max($limit * 5, 300), $range)
            ->reject(fn (\stdClass $e): bool => $this->isWardenRouteName(
                is_array($e->payload) ? ($e->payload['route'] ?? null) : null
            ))
            ->take($limit)
            ->values();
    }

    /** A dashboard self-request — a route named `warden.*`. */
    private function isWardenRouteName(mixed $route): bool
    {
        return is_string($route) && str_starts_with($route, 'warden.');
    }

    /**
     * One raw event by id, scoped to the project, for the rich per-event detail
     * view. A second deliberate raw wdn_events read (alongside trace()); returns
     * the full row so the detail can surface every captured field.
     */
    public function event(int $projectId, int $eventId): ?\stdClass
    {
        $event = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('id', $eventId)
            ->first(['id', 'trace_id', 'span_id', 'parent_span_id', 'type', 'occurred_at', 'duration_us', 'payload', 'release']);

        if ($event === null) {
            return null;
        }

        $event->payload = Json::decode($event->payload ?? null);

        return $event;
    }

    /** @return Collection<int, \stdClass> */
    public function recentIssues(int $projectId, int $limit = 8): Collection
    {
        return $this->issues($projectId, ['status' => 'open', 'limit' => $limit]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \stdClass>
     */
    public function issues(int $projectId, array $filters): Collection
    {
        return $this->reader->issues($projectId, $filters);
    }

    public function issue(int $projectId, int $issueId): ?\stdClass
    {
        $issue = $this->db->table('wdn_issues')->where('project_id', $projectId)->where('id', $issueId)->first();

        if ($issue !== null) {
            $issue->stack = Json::decode($issue->stack ?? null);
        }

        return $issue;
    }

    /** @return Collection<int, \stdClass> */
    public function incidents(int $projectId, int $limit = 20): Collection
    {
        return $this->db->table('wdn_incidents')
            ->where('project_id', $projectId)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function incident(int $projectId, int $incidentId): ?Incident
    {
        return Incident::query()
            ->where('project_id', $projectId)
            ->where('id', $incidentId)
            ->first();
    }

    // -------------------------------------------------------- delivery

    /**
     * Child delivery activity: when batches arrived (wdn_ingested_batches), so an
     * operator can see at a glance whether shipping is continuous (daemon) or
     * once a minute (cron). Reads the small batch-receipt log, not the stream.
     *
     * @return array{last: string|null, window: int, batches: int, events: int, cadence: int|null, series: list<int>, recent: Collection<int, \stdClass>}
     */
    public function delivery(int $projectId, int $minutes = 60): array
    {
        $since = Carbon::now()->subMinutes($minutes);
        $baseTs = $since->copy()->startOfMinute()->getTimestamp();

        $rows = $this->db->table('wdn_ingested_batches')
            ->where('project_id', $projectId)
            ->where('received_at', '>=', $since)
            ->orderBy('received_at')
            ->get(['received_at']);

        $last = $this->db->table('wdn_ingested_batches')
            ->where('project_id', $projectId)
            ->max('received_at');

        /** @var Collection<int|string, int> $eventCounts */
        $eventCounts = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('received_at', '>=', $since)
            ->selectRaw('received_at as ra, count(*) as c')
            ->groupBy('received_at')
            ->pluck('c', 'ra');

        $buckets = array_fill(0, $minutes, 0);
        $timestamps = [];
        foreach ($rows as $r) {
            $ts = Carbon::parse(Cast::str($r->received_at))->getTimestamp();
            $timestamps[] = $ts;
            $idx = intdiv($ts - $baseTs, 60);
            if ($idx >= 0 && $idx < $minutes) {
                $buckets[$idx]++;
            }
        }

        sort($timestamps);
        $gaps = [];
        for ($i = 1; $i < count($timestamps); $i++) {
            $g = $timestamps[$i] - $timestamps[$i - 1];
            if ($g > 0) {
                $gaps[] = $g;
            }
        }

        $recent = $rows->groupBy(fn (\stdClass $r): string => Cast::str($r->received_at))
            ->map(function (Collection $group, int|string $ra) use ($eventCounts): \stdClass {
                $o = new \stdClass;
                $o->received_at = (string) $ra;
                $o->batches = $group->count();
                $o->events = Cast::int($eventCounts[$ra] ?? 0);

                return $o;
            })
            ->sortByDesc('received_at')
            ->take(60)
            ->values();

        return [
            'last' => $last !== null ? Cast::str($last) : null,
            'window' => $minutes,
            'batches' => $rows->count(),
            'events' => Cast::int($eventCounts->sum()),
            'cadence' => $this->medianInt($gaps),
            'series' => array_values($buckets),
            'recent' => $recent,
        ];
    }

    /**
     * Recent log events, optionally filtered to a single level (driven by the
     * clickable "Logs by level" breakdown) and optionally scoped to a time range
     * so the list stays consistent with the "Logs by level" card. Filters in PHP
     * so it stays portable across drivers; fetches a wider window when a level is
     * selected.
     *
     * @return Collection<int, \stdClass>
     */
    public function recentLogs(int $projectId, ?string $level, int $limit = 100, ?string $range = null, ?string $search = null): Collection
    {
        $search = $search !== null ? trim($search) : '';
        $scan = $level !== null || $search !== '';

        $events = $this->recentEvents($projectId, 'log', $scan ? 500 : $limit, $range);

        if ($level !== null) {
            $events = $events->filter(fn (\stdClass $e): bool => (is_array($e->payload) ? ($e->payload['level'] ?? null) : null) === $level);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $events = $events->filter(fn (\stdClass $e): bool => str_contains(
                mb_strtolower(Cast::str(is_array($e->payload) ? ($e->payload['message'] ?? '') : '')),
                $needle
            ));
        }

        return $scan ? $events->take($limit)->values() : $events;
    }

    /**
     * Recent errored requests (HTTP 5xx) for the Errors section — read from the
     * raw request stream and filtered by status.
     *
     * @return Collection<int, \stdClass>
     */
    public function recentErrors(int $projectId, int $limit = 50, ?string $release = null): Collection
    {
        return $this->recentEvents($projectId, 'request', 300)
            ->filter(fn (\stdClass $e): bool => Cast::int(is_array($e->payload) ? ($e->payload['status'] ?? 0) : 0) >= 500)
            ->when($release !== null && $release !== '', fn (Collection $errors): Collection => $errors
                ->filter(fn (\stdClass $e): bool => Cast::str($e->release ?? null) === $release))
            ->take($limit)
            ->values();
    }

    /**
     * Deploy markers: each release seen within the range and when it first
     * appeared, for an "a deploy happened here" strip on the timelines (§5.6).
     *
     * @return Collection<int, \stdClass>
     */
    public function releaseMarkers(int $projectId, string $range): Collection
    {
        return $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->whereNotNull('release')
            ->where('occurred_at', '>=', $this->rangeStart($range))
            ->groupBy('release')
            ->orderByRaw('min(occurred_at) asc')
            ->get([
                'release',
                $this->db->raw('min(occurred_at) as first_seen'),
            ]);
    }

    /**
     * Distinct release markers seen for a project, most recent first — feeds the
     * "errors since this deploy" filter (§5.6).
     *
     * @return Collection<int, string>
     */
    public function releases(int $projectId, int $limit = 20): Collection
    {
        return $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->whereNotNull('release')
            ->groupBy('release')
            ->orderByRaw('max(id) desc')
            ->limit($limit)
            ->pluck('release')
            ->map(fn (mixed $r): string => Cast::str($r))
            ->values();
    }

    /** @param list<int> $values */
    protected function medianInt(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $values[$mid] : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    // -------------------------------------------------------- internals

    /** @return Collection<int, AggRow> */
    protected function rows(int $projectId, string $type, string $range): Collection
    {
        $key = "{$projectId}.{$type}.{$range}";

        return $this->rowsCache[$key] ??= $this->db->table('wdn_aggregates')
            ->where('project_id', $projectId)
            ->where('type', $type)
            ->where('bucket', '>=', $this->rangeStart($range))
            ->get()
            ->map(fn (\stdClass $r): array => [
                'bucket' => Cast::str($r->bucket),
                'key' => Cast::str($r->key),
                'count' => Cast::int($r->count),
                'sum_duration' => Cast::int($r->sum_duration),
                'max_duration' => Cast::int($r->max_duration),
                'meta' => Json::decode($r->meta ?? null),
            ])
            ->values();
    }

    /**
     * Sum one numeric meta counter across a group of aggregate rows.
     *
     * @param  Collection<int, AggRow>  $rows
     */
    protected function sumMeta(Collection $rows, string $key): int
    {
        return Cast::int($rows->sum(fn (array $r): int => Cast::int($r['meta'][$key] ?? null)));
    }

    /** @param Collection<int, AggRow> $rows */
    protected function avg(Collection $rows): int
    {
        $count = Cast::int($rows->sum('count'));

        return $count > 0 ? intdiv(Cast::int($rows->sum('sum_duration')), $count) : 0;
    }

    /**
     * @param  Collection<int, AggRow>  $rows
     * @return array<string, int> merged h_<edge> counters
     */
    protected function mergeHistograms(Collection $rows): array
    {
        $histogram = [];

        foreach ($rows as $row) {
            foreach ($row['meta'] as $k => $v) {
                if (str_starts_with((string) $k, 'h_')) {
                    $histogram[$k] = ($histogram[$k] ?? 0) + Cast::int($v);
                }
            }
        }

        return $histogram;
    }

    /** @param array<string, int> $histogram */
    protected function percentile(array $histogram, float $p = 0.95): ?int
    {
        $total = array_sum($histogram);
        if ($total === 0) {
            return null;
        }

        $target = (int) ceil($total * $p);
        $cumulative = 0;

        foreach ($this->edges as $edge) {
            $cumulative += $histogram['h_'.$edge] ?? 0;
            if ($cumulative >= $target) {
                return $edge;
            }
        }

        return end($this->edges) * 2;
    }

    /** @param array<string, mixed> $payload */
    protected function traceLabel(string $type, array $payload): string
    {
        return match ($type) {
            'request' => Cast::str($payload['method'] ?? null).' '.Cast::str($payload['route'] ?? $payload['path'] ?? null, '/'),
            'command' => Cast::str($payload['command'] ?? null, 'command'),
            'schedule' => Cast::str($payload['task'] ?? null, 'scheduled task'),
            'job' => Cast::str($payload['class'] ?? null, 'job'),
            default => $type,
        };
    }

    protected function rangeStart(string $range): Carbon
    {
        if (! preg_match('/^(\d+)([mhd])$/', $range, $m)) {
            return Carbon::now()->subHour();
        }

        $unitSeconds = ['m' => 60, 'h' => 3600, 'd' => 86400];

        return Carbon::now()->subSeconds($unitSeconds[$m[2]] * (int) $m[1]);
    }
}
