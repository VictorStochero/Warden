<?php

namespace VictorStochero\Warden\Outbox;

/** A reserved outbox entry handed to the ship daemon. */
class OutboxBatch
{
    /** @param array<array-key, mixed> $payload */
    public function __construct(
        public int|string $id,
        public array $payload,
        public int $attempts,
    ) {}

    /**
     * The events captured in this batch.
     *
     * @return list<array<array-key, mixed>>
     */
    public function events(): array
    {
        $events = $this->payload['events'] ?? [];

        return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
    }

    /** Stable id of this batch, used by the parent for exactly-once dedupe. */
    public function batchId(): string
    {
        $id = $this->payload['batch_id'] ?? null;

        return is_string($id) ? $id : '';
    }
}
