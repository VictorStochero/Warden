<?php

namespace VictorStochero\Warden\Ingestion;

use VictorStochero\Warden\Contracts\Ingestor;

/**
 * Local delivery path for parent self-monitoring (Frente 1). Instead of pushing
 * a captured batch to the outbox to be shipped over HTTP, the parent — being its
 * own database — hands the batch straight to the Ingestor. The write already runs
 * suppressed inside DatabaseIngestor::ingest (withoutRecording), so the parent
 * never observes its own self-ingest (§18.3); the call site in Warden::flush is
 * suppressed too, belt-and-braces.
 */
class SelfDelivery
{
    public function __construct(
        protected Ingestor $ingestor,
        protected string $project,
    ) {}

    public function project(): string
    {
        return $this->project;
    }

    /**
     * Persist a single captured batch locally. The shape mirrors what the ingest
     * endpoint forwards to the Ingestor: a list of {id, events} batches.
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    public function deliver(string $batchId, array $events): void
    {
        $this->ingestor->ingest($this->project, [[
            'id' => $batchId,
            'events' => array_values($events),
        ]]);
    }
}
