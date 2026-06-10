<?php

namespace VictorStochero\Warden\Aggregation;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Cursor;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Periodic rollups of raw events into wdn_aggregates (M6). Buckets by period
 * and a per-type dimension; numeric meta counters (errors, hits, misses,
 * failures) and a coarse latency histogram are accumulated so the repository
 * can derive error rate and an approximate p95 without scanning raw events.
 *
 * Incremental and idempotent via a wdn_events.id cursor; runs suppressed so it
 * never observes its own reads/writes (§18.3).
 */
class DatabaseAggregator implements Aggregator
{
    /**
     * Latency histogram edges in milliseconds (last bucket is the overflow).
     *
     * @var list<int>
     */
    protected array $edges = [10, 50, 100, 250, 500, 1000, 2500, 5000];

    protected Cursor $cursor;

    public function __construct(
        protected Warden $observer,
        protected Connection $db,
        protected Repository $config,
    ) {
        $this->cursor = new Cursor($db);
    }

    public function rollup(int $projectId, string $type): void
    {
        $this->observer->withoutRecording(function () use ($projectId, $type) {
            $name = "aggregate:{$type}";
            $bucketSeconds = max(1, Cast::int($this->config->get('warden.parent.bucket_seconds', 60), 60));

            // One transaction per (project, step), holding the cursor lock so two
            // hosts running warden:aggregate cannot double-process the same
            // events. At per-minute cadence the backlog per run is small.
            $this->db->transaction(function () use ($projectId, $type, $name, $bucketSeconds) {
                $this->cursor->lock($projectId, $name);

                do {
                    $from = $this->cursor->position($projectId, $name);

                    $events = $this->db->table('wdn_events')
                        ->where('project_id', $projectId)
                        ->where('type', $type)
                        ->where('id', '>', $from)
                        ->orderBy('id')
                        ->limit(5000)
                        ->get();

                    if ($events->isEmpty()) {
                        return;
                    }

                    /** @var array<string, array{count:int,sum:int,max:int,meta:array<string,mixed>}> $deltas */
                    $deltas = [];
                    $maxId = $from;

                    foreach ($events as $event) {
                        $maxId = max($maxId, Cast::int($event->id));
                        $payload = Json::decode($event->payload);
                        $bucket = $this->bucket(Cast::str($event->occurred_at), $bucketSeconds);
                        $key = $this->keyFor($type, $payload);
                        $durationUs = Cast::int($event->duration_us);

                        $ck = $bucket."\0".$key;
                        $deltas[$ck] ??= ['count' => 0, 'sum' => 0, 'max' => 0, 'meta' => []];
                        $deltas[$ck]['count']++;
                        $deltas[$ck]['sum'] += $durationUs;
                        $deltas[$ck]['max'] = max($deltas[$ck]['max'], $durationUs);
                        $this->accumulateMeta($deltas[$ck]['meta'], $type, $payload, $durationUs);
                    }

                    $this->persist($projectId, $type, $deltas);
                    $this->cursor->advance($projectId, $name, $maxId);
                } while ($events->count() === 5000);
            });
        });
    }

    /** @param array<string, array{count:int,sum:int,max:int,meta:array<string,mixed>}> $deltas */
    protected function persist(int $projectId, string $type, array $deltas): void
    {
        foreach ($deltas as $compound => $delta) {
            [$bucket, $key] = explode("\0", $compound, 2);

            $this->db->transaction(function () use ($projectId, $type, $bucket, $key, $delta) {
                $existing = $this->db->table('wdn_aggregates')
                    ->where('project_id', $projectId)
                    ->where('type', $type)
                    ->where('bucket', $bucket)
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->first();

                $now = Carbon::now();

                if ($existing === null) {
                    $this->db->table('wdn_aggregates')->insert([
                        'project_id' => $projectId,
                        'type' => $type,
                        'bucket' => $bucket,
                        'key' => $key,
                        'count' => $delta['count'],
                        'sum_duration' => $delta['sum'],
                        'max_duration' => $delta['max'],
                        'meta' => Json::encode($delta['meta']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return;
                }

                $this->db->table('wdn_aggregates')->where('id', $existing->id)->update([
                    'count' => Cast::int($existing->count) + $delta['count'],
                    'sum_duration' => Cast::int($existing->sum_duration) + $delta['sum'],
                    'max_duration' => max(Cast::int($existing->max_duration), $delta['max']),
                    'meta' => Json::encode($this->mergeMeta(Json::decode($existing->meta), $delta['meta'])),
                    'updated_at' => $now,
                ]);
            });
        }
    }

    /** @param array<string,mixed> $payload */
    protected function keyFor(string $type, array $payload): string
    {
        return match ($type) {
            'request' => Cast::str($payload['route'] ?? $payload['path'] ?? null, 'unknown'),
            'query' => 'q_'.substr(sha1(Fingerprint::normalize(Cast::str($payload['sql'] ?? null))), 0, 16),
            'job' => Cast::str($payload['class'] ?? null, 'unknown'),
            'cache' => Cast::str($payload['store'] ?? null, 'default'),
            'http' => Cast::str($payload['host'] ?? null, 'unknown'),
            'mail' => Cast::str($payload['mailer'] ?? null, 'default'),
            'notification' => Cast::str($payload['channel'] ?? null, 'unknown'),
            'command' => Cast::str($payload['command'] ?? null, 'unknown'),
            'schedule' => Cast::str($payload['task'] ?? null, 'unknown'),
            'log' => Cast::str($payload['level'] ?? null, 'info'),
            'exception' => Cast::str($payload['class'] ?? null, 'unknown'),
            'host' => Cast::str($payload['hostname'] ?? null, 'unknown'),
            default => 'all',
        };
    }

    /**
     * @param  array<string,mixed>  $meta
     * @param  array<string,mixed>  $payload
     */
    protected function accumulateMeta(array &$meta, string $type, array $payload, int $durationUs): void
    {
        $ms = $durationUs / 1000;

        if ($durationUs > 0) {
            $key = 'h_'.$this->histogramBucket($ms);
            $meta[$key] = Cast::int($meta[$key] ?? 0) + 1;
        }

        switch ($type) {
            case 'request':
                if (Cast::int($payload['status'] ?? null) >= 500) {
                    $meta['errors'] = Cast::int($meta['errors'] ?? 0) + 1;
                }
                if ($ms >= Cast::float($this->config->get('warden.parent.slow_request_ms', 1000), 1000)) {
                    $meta['slow'] = Cast::int($meta['slow'] ?? 0) + 1;
                }
                break;

            case 'query':
                $meta['sql'] ??= mb_substr(Cast::str($payload['sql'] ?? null), 0, 500);
                if ($ms >= Cast::float($this->config->get('warden.parent.slow_query_ms', 100), 100)) {
                    $meta['slow'] = Cast::int($meta['slow'] ?? 0) + 1;
                }
                break;

            case 'job':
                $status = $payload['status'] ?? null;
                if ($status === 'failed') {
                    $meta['failures'] = Cast::int($meta['failures'] ?? 0) + 1;
                } elseif ($status === 'processed') {
                    $meta['processed'] = Cast::int($meta['processed'] ?? 0) + 1;
                }
                break;

            case 'cache':
                if (($payload['hit'] ?? false) === true) {
                    $meta['hits'] = Cast::int($meta['hits'] ?? 0) + 1;
                } elseif (($payload['action'] ?? null) === 'miss') {
                    $meta['misses'] = Cast::int($meta['misses'] ?? 0) + 1;
                }
                break;

            case 'http':
                $status = Cast::int($payload['status'] ?? null);
                if ($status === 0 || $status >= 400) {
                    $meta['errors'] = Cast::int($meta['errors'] ?? 0) + 1;
                }
                break;

            case 'host':
                // Keep the latest sampled gauges so the overview can show them.
                $memory = Cast::arr($payload['memory'] ?? null);
                $load = Cast::arr($payload['load'] ?? null);
                $disk = Cast::arr($payload['disk'] ?? null);
                $meta['cpu'] = $payload['cpu'] ?? ($meta['cpu'] ?? null);
                $meta['mem'] = $memory['used_percent'] ?? ($meta['mem'] ?? null);
                $meta['load'] = $load[1] ?? ($meta['load'] ?? null);
                $meta['disk'] = $disk['used_percent'] ?? ($meta['disk'] ?? null);
                break;
        }
    }

    /**
     * @param  array<string,mixed>  $existing
     * @param  array<string,mixed>  $delta
     * @return array<string,mixed>
     */
    protected function mergeMeta(array $existing, array $delta): array
    {
        foreach ($delta as $key => $value) {
            if (is_numeric($value) && is_numeric($existing[$key] ?? null)) {
                $existing[$key] = Cast::int($existing[$key]) + Cast::int($value);
            } elseif (! array_key_exists($key, $existing) || $existing[$key] === null) {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    protected function histogramBucket(float $ms): string
    {
        foreach ($this->edges as $edge) {
            if ($ms < $edge) {
                return (string) $edge;
            }
        }

        return 'inf';
    }

    protected function bucket(string $occurredAt, int $seconds): string
    {
        $ts = Carbon::parse($occurredAt)->getTimestamp();

        return Carbon::createFromTimestamp($ts - ($ts % $seconds))->format('Y-m-d H:i:s');
    }
}
