<?php

namespace VictorStochero\Warden\Contracts;

use VictorStochero\Warden\Bridge\NullEventForwarder;

/**
 * The Warden Bridge seam (§9.2). After the parent persists a batch into
 * wdn_events, the configured forwarder is handed the same canonical events
 * (schema_version 2) so it can re-emit them downstream — e.g. an OTLP exporter
 * to a columnar SaaS for overflow/mirror, without touching the core path.
 *
 * The default binding is {@see NullEventForwarder}
 * (a no-op, zero overhead). The call is best-effort and suppressed: a forwarder
 * that throws must never break the ingest (RNF-2).
 */
interface EventForwarder
{
    /**
     * @param  list<array<array-key, mixed>>  $events  Canonical schema_version-2 events.
     */
    public function forward(string $projectSlug, array $events): void;
}
