<?php

namespace VictorStochero\Warden\Contracts;

interface Transport
{
    /**
     * Deliver shipments to the parent. Each shipment is one outbox row:
     * `['id' => string batch_id, 'events' => list<array>]`. The id makes the
     * parent's ingest idempotent (exactly-once).
     *
     * MUST NOT throw on network failure — return false so the caller keeps the
     * batch in the outbox and retries later (RNF-2).
     *
     * @param  array<int, array{id: string, events: array<int, mixed>}>  $shipments
     */
    public function ship(array $shipments): bool;

    /**
     * Report a dropped (poison) batch to the parent for centralized visibility.
     * Best-effort: MUST NOT throw — returns false if the parent is unreachable.
     */
    public function reportDeadLetter(string $batchId, string $reason, int $attempts): bool;

    /**
     * Directives the parent returned on the most recent successful ship (the
     * control channel — e.g. `['audit_due' => true]`). Empty if none / no ship.
     *
     * @return array<string, mixed>
     */
    public function lastDirectives(): array;
}
