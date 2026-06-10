<?php

namespace VictorStochero\Warden\Contracts;

interface Ingestor
{
    /**
     * Persist shipments for a project. Each shipment is expected to be
     * `['id' => string batch_id, 'events' => list<array>]`, but the array is
     * decoded from untrusted network JSON, so each element is validated at
     * runtime. A batch whose (project, batch_id) was already ingested is
     * skipped (exactly-once).
     *
     * @param  array<int, mixed>  $batches
     * @return int number of events actually persisted
     */
    public function ingest(string $project, array $batches): int;
}
