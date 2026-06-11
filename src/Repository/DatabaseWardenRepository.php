<?php

namespace VictorStochero\Warden\Repository;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VictorStochero\Warden\Analysis\NPlusOneDetector;
use VictorStochero\Warden\Contracts\WardenRepository;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;

/**
 * The single read surface for any UI (RNF-6). Reads only from wdn_ tables —
 * mostly the small wdn_aggregates so dashboards never scan the raw stream.
 * Timelines (and N+1 flags) are the one place raw wdn_events are read, scoped
 * to a single trace_id.
 *
 * @phpstan-import-type TraceSpan from WardenRepository
 */
class DatabaseWardenRepository implements WardenRepository
{
    /**
     * Histogram edges, must match the aggregator.
     *
     * @var list<int>
     */
    protected array $edges = [10, 50, 100, 250, 500, 1000, 2500, 5000];

    public function __construct(protected Connection $db) {}

    /**
     * @param  array<string, mixed>  $filters  optional `group` / `tag` slugs
     * @return Collection<int, \stdClass>
     */
    public function projects(array $filters = []): Collection
    {
        $since = Carbon::now()->subMinutes(5);

        $query = $this->db->table('wdn_projects');

        $groupSlug = Cast::str($filters['group'] ?? null);
        if ($groupSlug !== '') {
            $query->whereExists(function (Builder $q) use ($groupSlug): void {
                $q->select($this->db->raw('1'))
                    ->from('wdn_groups')
                    ->whereColumn('wdn_groups.id', 'wdn_projects.group_id')
                    ->where('wdn_groups.slug', $groupSlug);
            });
        }

        $tagSlug = Cast::str($filters['tag'] ?? null);
        if ($tagSlug !== '') {
            $query->whereExists(function (Builder $q) use ($tagSlug): void {
                $q->select($this->db->raw('1'))
                    ->from('wdn_project_tag')
                    ->join('wdn_tags', 'wdn_tags.id', '=', 'wdn_project_tag.tag_id')
                    ->whereColumn('wdn_project_tag.project_id', 'wdn_projects.id')
                    ->where('wdn_tags.slug', $tagSlug);
            });
        }

        $tagsByProject = $this->tagsByProject();
        $groupsById = $this->groupsById();

        $projects = $query->get();
        $ids = array_values($projects->map(fn (\stdClass $p): int => Cast::int($p->id))->all());

        // Batch the request aggregates for every project in one query, grouped by
        // project_id — was one query per project (N+1 on the most-visited screen).
        $aggByProject = [];
        foreach ($this->db->table('wdn_aggregates')
            ->whereIn('project_id', $ids)
            ->where('type', 'request')
            ->where('bucket', '>=', $since)
            ->get() as $row) {
            $aggByProject[Cast::int($row->project_id)][] = $row;
        }

        // And availability for all projects in a single wdn_incidents read.
        $uptimes = $this->uptimeForProjects($ids, '30d');

        return $projects->map(function (\stdClass $project) use ($aggByProject, $uptimes, $tagsByProject, $groupsById): \stdClass {
            $count = 0;
            $errors = 0;
            $histogram = [];
            foreach ($aggByProject[Cast::int($project->id)] ?? [] as $row) {
                $count += Cast::int($row->count);
                $meta = Json::decode($row->meta ?? null);
                $errors += Cast::int($meta['errors'] ?? null);
                foreach ($meta as $k => $v) {
                    if (str_starts_with((string) $k, 'h_')) {
                        $histogram[$k] = ($histogram[$k] ?? 0) + Cast::int($v);
                    }
                }
            }

            $errorRate = $count > 0 ? round($errors / $count * 100, 2) : 0.0;
            $p95 = $this->percentile($histogram);
            $lastSeen = isset($project->last_seen_at) ? Cast::str($project->last_seen_at) : null;

            $summary = new \stdClass;
            $summary->id = Cast::int($project->id);
            $summary->name = Cast::str($project->name);
            $summary->slug = Cast::str($project->slug);
            $summary->last_seen_at = $lastSeen;
            $summary->throughput = $count;
            $summary->error_rate = $errorRate;
            $summary->p95_ms = $p95;
            $summary->health = $this->health($lastSeen, $errorRate, $p95);
            $summary->uptime = $uptimes[Cast::int($project->id)] ?? 100.0;

            $groupId = isset($project->group_id) ? Cast::int($project->group_id) : 0;
            $summary->group = $groupId > 0 ? ($groupsById[$groupId] ?? null) : null;
            $summary->tags = $tagsByProject[Cast::int($project->id)] ?? [];

            return $summary;
        })->values();
    }

    /**
     * Tags for every project, keyed by project id, as `{name, slug}` rows — one
     * query instead of N, so the overview cards can render chips cheaply.
     *
     * @return array<int, list<array{name: string, slug: string}>>
     */
    protected function tagsByProject(): array
    {
        $rows = $this->db->table('wdn_project_tag')
            ->join('wdn_tags', 'wdn_tags.id', '=', 'wdn_project_tag.tag_id')
            ->orderBy('wdn_tags.name')
            ->get(['wdn_project_tag.project_id as project_id', 'wdn_tags.name as name', 'wdn_tags.slug as slug']);

        /** @var array<int, list<array{name: string, slug: string}>> $byProject */
        $byProject = [];
        foreach ($rows as $row) {
            $byProject[Cast::int($row->project_id)][] = [
                'name' => Cast::str($row->name),
                'slug' => Cast::str($row->slug),
            ];
        }

        return $byProject;
    }

    /**
     * Every group keyed by id, as `{name, slug}` rows.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    protected function groupsById(): array
    {
        /** @var array<int, array{name: string, slug: string}> $byId */
        $byId = [];
        foreach ($this->db->table('wdn_groups')->get(['id', 'name', 'slug']) as $row) {
            $byId[Cast::int($row->id)] = [
                'name' => Cast::str($row->name),
                'slug' => Cast::str($row->slug),
            ];
        }

        return $byId;
    }

    /**
     * Availability over a window: the share of time with no open *critical*
     * incident (a down heartbeat or a high-severity issue), derived from
     * wdn_incidents. Overlapping incidents are merged so concurrent outages are
     * not double-counted. No extra capture — it reuses the incident timeline.
     */
    public function uptime(int $projectId, string $range = '30d'): float
    {
        return $this->uptimeForProjects([$projectId], $range)[$projectId] ?? 100.0;
    }

    /**
     * Availability for several projects at once — a single wdn_incidents read,
     * intervals grouped and merged per project (overview avoids N queries).
     *
     * @param  list<int>  $projectIds
     * @return array<int, float>
     */
    protected function uptimeForProjects(array $projectIds, string $range): array
    {
        if ($projectIds === []) {
            return [];
        }

        $start = $this->rangeStart($range);
        $startTs = $start->getTimestamp();
        $nowTs = Carbon::now()->getTimestamp();
        $window = max(1, $nowTs - $startTs);

        $incidents = $this->db->table('wdn_incidents')
            ->whereIn('project_id', $projectIds)
            ->where('severity', 'critical')
            ->whereNotNull('started_at')
            ->where(function (Builder $q) use ($start) {
                $q->whereNull('resolved_at')->orWhere('resolved_at', '>=', $start);
            })
            ->get(['project_id', 'started_at', 'resolved_at']);

        /** @var array<int, list<array{0:int,1:int}>> $byProject */
        $byProject = [];
        foreach ($incidents as $inc) {
            $s = max($startTs, Carbon::parse(Cast::str($inc->started_at))->getTimestamp());
            $e = $inc->resolved_at !== null ? Carbon::parse(Cast::str($inc->resolved_at))->getTimestamp() : $nowTs;
            $e = min($e, $nowTs);
            if ($e > $s) {
                $byProject[Cast::int($inc->project_id)][] = [$s, $e];
            }
        }

        $out = [];
        foreach ($projectIds as $id) {
            $downtime = $this->mergeDowntime($byProject[$id] ?? []);
            $out[$id] = round(max(0.0, min(100.0, (1 - $downtime / $window) * 100)), 2);
        }

        return $out;
    }

    /**
     * Total non-overlapping downtime seconds across a set of [start, end]
     * intervals (concurrent outages are merged, not double-counted).
     *
     * @param  list<array{0:int,1:int}>  $intervals
     */
    protected function mergeDowntime(array $intervals): int
    {
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $downtime = 0;
        $cur = null;
        foreach ($intervals as [$s, $e]) {
            if ($cur === null) {
                $cur = [$s, $e];
            } elseif ($s <= $cur[1]) {
                $cur[1] = max($cur[1], $e);
            } else {
                $downtime += $cur[1] - $cur[0];
                $cur = [$s, $e];
            }
        }
        if ($cur !== null) {
            $downtime += $cur[1] - $cur[0];
        }

        return $downtime;
    }

    /** @return Collection<int, TraceSpan> */
    public function trace(int $projectId, string $traceId): Collection
    {
        $events = $this->db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('trace_id', $traceId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (\stdClass $e): array => [
                'id' => Cast::int($e->id),
                'type' => Cast::str($e->type),
                'span_id' => isset($e->span_id) ? Cast::str($e->span_id) : null,
                'parent_span_id' => isset($e->parent_span_id) ? Cast::str($e->parent_span_id) : null,
                'occurred_at' => Cast::str($e->occurred_at),
                'duration_us' => isset($e->duration_us) ? Cast::int($e->duration_us) : null,
                'payload' => Json::decode($e->payload ?? null),
                'n_plus_one' => false,
                'repeat_count' => 0,
            ])
            ->values();

        // Annotate N+1 query repetitions within this trace (M7).
        $nPlusOne = (new NPlusOneDetector)->detect($events->where('type', 'query')->all());

        return $events->map(function (array $event) use ($nPlusOne): array {
            if ($event['type'] === 'query') {
                $hash = substr(sha1(Fingerprint::normalize(Cast::str($event['payload']['sql'] ?? null))), 0, 16);
                if (isset($nPlusOne[$hash])) {
                    $event['n_plus_one'] = true;
                    $event['repeat_count'] = $nPlusOne[$hash]['count'];
                }
            }

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \stdClass>
     */
    public function issues(int $projectId, array $filters): Collection
    {
        $query = $this->db->table('wdn_issues')->where('project_id', $projectId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['assignee'])) {
            $query->where('assignee', $filters['assignee']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Allowlist the order column inside the repository (independent of the
        // caller): orderByDesc()'s argument is an identifier, not a bound value,
        // so anything outside the known columns must be clamped (#13).
        $order = Cast::str($filters['order'] ?? null, 'last_seen_at');
        $order = in_array($order, ['last_seen_at', 'first_seen_at', 'count'], true) ? $order : 'last_seen_at';

        return $query->orderByDesc($order)
            ->limit(Cast::int($filters['limit'] ?? null, 100))
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    public function aggregate(int $projectId, string $type, string $range): Collection
    {
        return $this->db->table('wdn_aggregates')
            ->where('project_id', $projectId)
            ->where('type', $type)
            ->where('bucket', '>=', $this->rangeStart($range))
            ->orderBy('bucket')
            ->get()
            ->map(function (\stdClass $row): \stdClass {
                $count = Cast::int($row->count);
                $sum = Cast::int($row->sum_duration);

                $agg = new \stdClass;
                $agg->bucket = Cast::str($row->bucket);
                $agg->key = Cast::str($row->key);
                $agg->count = $count;
                $agg->sum_duration = $sum;
                $agg->max_duration = Cast::int($row->max_duration);
                $agg->avg_us = $count > 0 ? intdiv($sum, $count) : 0;
                $agg->meta = Json::decode($row->meta ?? null);

                return $agg;
            });
    }

    /** @return Collection<int, \stdClass> */
    public function hostMetrics(int $projectId, string $range): Collection
    {
        return $this->aggregate($projectId, 'host', $range);
    }

    // --------------------------------------------------------- internals

    protected function health(?string $lastSeenAt, float $errorRate, ?int $p95): string
    {
        if ($lastSeenAt !== null && Carbon::parse($lastSeenAt)->lt(Carbon::now()->subMinutes(10))) {
            return 'red'; // silent — likely down or shipper stopped
        }

        $slow = Cast::int(config('warden.parent.slow_request_ms', 1000), 1000);

        if ($errorRate >= 5 || ($p95 !== null && $p95 >= $slow * 2)) {
            return 'red';
        }

        if ($errorRate >= 1 || ($p95 !== null && $p95 >= $slow)) {
            return 'yellow';
        }

        return 'green';
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

        return end($this->edges) * 2; // overflow ("inf") bucket
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
