<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Database\Events\QueryExecuted;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

class QueryRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'query';
    }

    public function register(): void
    {
        $this->listen(QueryExecuted::class, function (QueryExecuted $event) {
            // Never observe the package's own dedicated connection (§18.3).
            $obsConnection = $this->config->get('warden.connection');
            if ($obsConnection !== null && $event->connectionName === $obsConnection) {
                return;
            }

            // Lean capture: drop queries faster than the configured threshold
            // before any scrubbing/buffering. 0/null keeps every query (full).
            // Trade-off: with a positive threshold the parent's N+1 and
            // frequent-query analysis won't see the fast queries.
            $minMs = Cast::float($this->config->get('warden.child.query.capture_min_ms'));
            if ($minMs > 0 && $event->time < $minMs) {
                return;
            }

            $scrubber = $this->scrubber();

            // Bindings are positional (int keys), so key-based scrubbing is a
            // no-op — scrubBindings correlates each `?` to its column and masks
            // by value heuristic. scrubSql masks any inline sensitive literals.
            $bindings = $scrubber->scrubBindings(
                $event->sql,
                $this->safeBindings($event->bindings)
            );

            $this->record([
                'sql' => $scrubber->scrubSql($event->sql),
                'bindings' => $bindings,
                'connection' => $event->connectionName,
                'time_ms' => $event->time,
            ], durationUs: $this->msToUs((float) $event->time));
        });
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    protected function safeBindings(array $bindings): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            if (is_object($value)) {
                return $value instanceof \Stringable ? (string) $value : get_class($value);
            }

            return $value;
        }, $bindings);
    }
}
