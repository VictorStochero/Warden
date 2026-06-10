<?php

namespace VictorStochero\Warden\Buffer;

/**
 * In-memory, per-entry-point accumulator. Recorders append lightweight arrays
 * here during the request; nothing is serialized to JSON or sent over the wire
 * until flush (RNF-1). Reset on every Octane request/task boundary (§18.2).
 */
class EventBuffer
{
    /** @var array<int, array<string, mixed>> */
    protected array $events = [];

    /** @param array<string, mixed> $event */
    public function add(array $event): void
    {
        $this->events[] = $event;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
