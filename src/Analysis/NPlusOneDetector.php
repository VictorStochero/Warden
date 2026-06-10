<?php

namespace VictorStochero\Warden\Analysis;

use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Cast;

/**
 * Flags N+1 query patterns within a single trace: the same normalized query
 * executed at least `threshold` times. Normalization reuses the exception
 * message normalizer so literal bindings collapse together (§10, §15).
 */
class NPlusOneDetector
{
    public function __construct(protected int $threshold = 3) {}

    /**
     * @param  iterable<array<string, mixed>>  $queryEvents  events of type "query"
     * @return array<string, array{count:int, sql:string, total_us:int}> keyed by normalized hash
     */
    public function detect(iterable $queryEvents): array
    {
        $groups = [];

        foreach ($queryEvents as $event) {
            $payload = Cast::arr($event['payload'] ?? null);
            $sql = Cast::str($payload['sql'] ?? null);
            if ($sql === '') {
                continue;
            }

            $hash = substr(sha1(Fingerprint::normalize($sql)), 0, 16);

            $groups[$hash] ??= ['count' => 0, 'sql' => $sql, 'total_us' => 0];
            $groups[$hash]['count']++;
            $groups[$hash]['total_us'] += Cast::int($event['duration_us'] ?? null);
        }

        return array_filter($groups, fn (array $g): bool => $g['count'] >= $this->threshold);
    }
}
