<?php

namespace VictorStochero\Warden\Trace;

use VictorStochero\Warden\Support\Ids;

/**
 * A unit of work within a trace (OTel-like). The trace_id is constant for the
 * whole logical operation; each span carries its own span_id and points at its
 * parent so the timeline can be nested rather than flat (§18.1).
 */
class Span
{
    public string $id;

    public float $startedAt;

    public function __construct(
        public string $type,
        public ?string $parentId = null,
        public ?string $name = null,
        ?string $id = null,
    ) {
        $this->id = $id ?? Ids::generate();
        $this->startedAt = microtime(true);
    }

    /** Elapsed time since the span opened, in microseconds. */
    public function elapsedUs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1_000_000);
    }
}
