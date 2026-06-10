<?php

namespace VictorStochero\Warden\Outbox;

interface Outbox
{
    /**
     * Stage one captured batch for delivery. Silently drops when the high-water
     * mark is reached so the host's disk can never be filled (§18.6).
     *
     * @param  array<string, mixed>  $batch
     */
    public function push(array $batch): void;

    /**
     * Reserve up to $limit pending batches for shipping, oldest first.
     *
     * @return array<int, OutboxBatch>
     */
    public function reserve(int $limit): array;

    /** Permanently remove a delivered batch. */
    public function delete(OutboxBatch $batch): void;

    /** Return a failed batch to the queue with a backoff delay. */
    public function release(OutboxBatch $batch, int $delaySeconds): void;

    /** Current number of undelivered batches (drives the high/low-water gate). */
    public function size(): int;

    /** Whether capture is currently paused because the outbox is full (§18.6). */
    public function isFull(): bool;
}
