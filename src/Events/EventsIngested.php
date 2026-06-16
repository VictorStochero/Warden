<?php

namespace VictorStochero\Warden\Events;

use VictorStochero\Warden\Contracts\EventForwarder;

/**
 * Fired after the parent persists a batch of raw events (§9.2), as the
 * Laravel-native side of the Bridge seam: a host can subscribe with a normal
 * listener instead of binding an {@see EventForwarder}.
 * Carries the same canonical schema_version-2 events that were stored.
 */
class EventsIngested
{
    /**
     * @param  list<array<array-key, mixed>>  $events
     */
    public function __construct(
        public readonly string $projectSlug,
        public readonly array $events,
    ) {}
}
