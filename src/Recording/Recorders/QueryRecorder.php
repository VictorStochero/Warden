<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Database\Events\QueryExecuted;
use VictorStochero\Warden\Recording\AbstractRecorder;

class QueryRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'query';
    }

    public function register(): void
    {
        $this->events->listen(QueryExecuted::class, function (QueryExecuted $event) {
            // Never observe the package's own dedicated connection (§18.3).
            $obsConnection = $this->config->get('warden.connection');
            if ($obsConnection !== null && $event->connectionName === $obsConnection) {
                return;
            }

            $bindings = $this->scrubber()->scrub(
                $this->safeBindings($event->bindings)
            );

            $this->record([
                'sql' => $event->sql,
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
