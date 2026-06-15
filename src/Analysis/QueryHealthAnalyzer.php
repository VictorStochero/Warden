<?php

namespace VictorStochero\Warden\Analysis;

use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Cast;

/**
 * Pure, DB-free diagnosis of query problems over a set of "query" events.
 *
 * Six categories are reported, each pointing back to the affected trace where
 * applicable:
 *  - n_plus_one  : same normalized query repeated within one trace (reuses
 *                  {@see NPlusOneDetector}); bindings differ.
 *  - duplicates  : exact repeats within one trace (same normalized SQL AND
 *                  same bindings), count >= 2.
 *  - select_star : SELECT * patterns, deduped by fingerprint.
 *  - no_where    : UPDATE/DELETE without a WHERE clause, deduped by fingerprint.
 *  - fat_requests: traces whose query count exceeds the fat-request threshold.
 *  - slow        : queries slower than the slow-query threshold, deduped by
 *                  fingerprint keeping the slowest.
 *
 * Each list is ordered by relevance (count desc / duration desc) and truncated
 * to {@see $perCategoryLimit}; reported SQL is truncated to 200 chars.
 */
final class QueryHealthAnalyzer
{
    private const SQL_MAX = 200;

    public function __construct(
        private int $nPlusOneThreshold = 3,
        private int $fatRequestThreshold = 50,
        private int $slowQueryUs = 100_000,
        private int $perCategoryLimit = 10,
    ) {}

    /**
     * @param  iterable<array{trace_id?:string|null, duration_us?:int|null, payload?:array<string,mixed>|null}>  $queryEvents
     * @return array{
     *   n_plus_one: list<array{sql:string, count:int, trace_id:string}>,
     *   duplicates: list<array{sql:string, count:int, trace_id:string}>,
     *   select_star: list<array{sql:string}>,
     *   no_where: list<array{sql:string}>,
     *   fat_requests: list<array{trace_id:string, count:int}>,
     *   slow: list<array{sql:string, duration_us:int, trace_id:string}>,
     * }
     */
    public function analyze(iterable $queryEvents): array
    {
        /** @var array<string, list<array{sql:string, bindings:string, duration_us:int}>> $byTrace */
        $byTrace = [];
        /** @var list<array{trace_id:string, sql:string, bindings:string, duration_us:int}> $all */
        $all = [];

        foreach ($queryEvents as $event) {
            $payload = Cast::arr($event['payload'] ?? null);
            $sql = Cast::str($payload['sql'] ?? null);
            if ($sql === '') {
                continue;
            }

            $traceId = Cast::str($event['trace_id'] ?? null);
            $durationUs = Cast::int($event['duration_us'] ?? null);
            $bindings = json_encode(Cast::arr($payload['bindings'] ?? null)) ?: '[]';

            if ($traceId !== '') {
                $byTrace[$traceId][] = ['sql' => $sql, 'bindings' => $bindings, 'duration_us' => $durationUs];
            }

            $all[] = ['trace_id' => $traceId, 'sql' => $sql, 'bindings' => $bindings, 'duration_us' => $durationUs];
        }

        return [
            'n_plus_one' => $this->nPlusOne($byTrace),
            'duplicates' => $this->duplicates($byTrace),
            'select_star' => $this->selectStar($all),
            'no_where' => $this->noWhere($all),
            'fat_requests' => $this->fatRequests($byTrace),
            'slow' => $this->slow($all),
        ];
    }

    /**
     * @param  array<string, list<array{sql:string, bindings:string, duration_us:int}>>  $byTrace
     * @return list<array{sql:string, count:int, trace_id:string}>
     */
    private function nPlusOne(array $byTrace): array
    {
        $detector = new NPlusOneDetector($this->nPlusOneThreshold);
        $findings = [];

        foreach ($byTrace as $traceId => $rows) {
            $events = array_map(
                static fn (array $r): array => ['payload' => ['sql' => $r['sql']], 'duration_us' => $r['duration_us']],
                $rows,
            );

            foreach ($detector->detect($events) as $group) {
                $findings[] = [
                    'sql' => $this->truncate($group['sql']),
                    'count' => $group['count'],
                    'trace_id' => $traceId,
                ];
            }
        }

        usort($findings, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($findings, 0, $this->perCategoryLimit);
    }

    /**
     * @param  array<string, list<array{sql:string, bindings:string, duration_us:int}>>  $byTrace
     * @return list<array{sql:string, count:int, trace_id:string}>
     */
    private function duplicates(array $byTrace): array
    {
        $findings = [];

        foreach ($byTrace as $traceId => $rows) {
            /** @var array<string, array{sql:string, count:int}> $groups */
            $groups = [];

            foreach ($rows as $row) {
                $key = Fingerprint::normalize($row['sql']).'|'.$row['bindings'];
                $groups[$key] ??= ['sql' => $row['sql'], 'count' => 0];
                $groups[$key]['count']++;
            }

            foreach ($groups as $group) {
                if ($group['count'] >= 2) {
                    $findings[] = [
                        'sql' => $this->truncate($group['sql']),
                        'count' => $group['count'],
                        'trace_id' => $traceId,
                    ];
                }
            }
        }

        usort($findings, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($findings, 0, $this->perCategoryLimit);
    }

    /**
     * @param  list<array{trace_id:string, sql:string, bindings:string, duration_us:int}>  $all
     * @return list<array{sql:string}>
     */
    private function selectStar(array $all): array
    {
        return $this->staticByFingerprint(
            $all,
            static fn (string $sql): bool => (bool) preg_match('/\bselect\s+\*/i', $sql),
        );
    }

    /**
     * @param  list<array{trace_id:string, sql:string, bindings:string, duration_us:int}>  $all
     * @return list<array{sql:string}>
     */
    private function noWhere(array $all): array
    {
        return $this->staticByFingerprint(
            $all,
            // Heurística: pode sub-reportar se "where" aparecer dentro de um literal de string; preferimos falso-negativo a falso-positivo.
            static fn (string $sql): bool => (bool) preg_match('/^\s*(update|delete)\b(?!.*\bwhere\b)/is', $sql),
        );
    }

    /**
     * Dedup matching SQLs by fingerprint, keeping the first seen.
     *
     * @param  list<array{trace_id:string, sql:string, bindings:string, duration_us:int}>  $all
     * @param  callable(string):bool  $matches
     * @return list<array{sql:string}>
     */
    private function staticByFingerprint(array $all, callable $matches): array
    {
        /** @var array<string, true> $seen */
        $seen = [];
        $findings = [];

        foreach ($all as $row) {
            if (! $matches($row['sql'])) {
                continue;
            }

            $fingerprint = Fingerprint::normalize($row['sql']);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $findings[] = ['sql' => $this->truncate($row['sql'])];
        }

        return array_slice($findings, 0, $this->perCategoryLimit);
    }

    /**
     * @param  array<string, list<array{sql:string, bindings:string, duration_us:int}>>  $byTrace
     * @return list<array{trace_id:string, count:int}>
     */
    private function fatRequests(array $byTrace): array
    {
        $findings = [];

        foreach ($byTrace as $traceId => $rows) {
            $count = count($rows);
            if ($count > $this->fatRequestThreshold) {
                $findings[] = ['trace_id' => $traceId, 'count' => $count];
            }
        }

        usort($findings, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($findings, 0, $this->perCategoryLimit);
    }

    /**
     * @param  list<array{trace_id:string, sql:string, bindings:string, duration_us:int}>  $all
     * @return list<array{sql:string, duration_us:int, trace_id:string}>
     */
    private function slow(array $all): array
    {
        /** @var array<string, array{sql:string, duration_us:int, trace_id:string}> $groups */
        $groups = [];

        foreach ($all as $row) {
            if ($row['duration_us'] < $this->slowQueryUs) {
                continue;
            }

            $fingerprint = Fingerprint::normalize($row['sql']);
            if (! isset($groups[$fingerprint]) || $row['duration_us'] > $groups[$fingerprint]['duration_us']) {
                $groups[$fingerprint] = [
                    'sql' => $this->truncate($row['sql']),
                    'duration_us' => $row['duration_us'],
                    'trace_id' => $row['trace_id'],
                ];
            }
        }

        $findings = array_values($groups);

        usort($findings, static fn (array $a, array $b): int => $b['duration_us'] <=> $a['duration_us']);

        return array_slice($findings, 0, $this->perCategoryLimit);
    }

    private function truncate(string $sql): string
    {
        return mb_substr($sql, 0, self::SQL_MAX);
    }
}
