<?php

namespace VictorStochero\Warden\Trace;

use VictorStochero\Warden\Support\Ids;

/**
 * Holds the trace_id and span stack for a single entry point (request, command,
 * scheduled task or job). One context per entry point — never a singleton with
 * request state, so it is safe under Octane and long-lived workers (§18.2).
 */
class TraceContext
{
    public string $traceId;

    /** Entry-point kind: request | command | schedule | job. */
    public string $entryType;

    /** Head-based sampling decision, carried to downstream jobs (§18.4). */
    public bool $sampled;

    public Span $root;

    /** @var array<int, Span> active span stack; the last is "current". */
    protected array $stack = [];

    /** Tail-based override: promote this trace to keep regardless of sampling. */
    public bool $forceKeep = false;

    /** Authenticated user resolved during this entry point, if any. */
    public int|string|null $userId = null;

    public function __construct(string $entryType, bool $sampled, ?string $traceId = null, ?string $parentSpanId = null)
    {
        $this->entryType = $entryType;
        $this->sampled = $sampled;
        $this->traceId = $traceId ?? Ids::generate();
        $this->root = new Span($entryType, $parentSpanId);
        $this->stack = [$this->root];
    }

    public function currentSpan(): Span
    {
        return end($this->stack) ?: $this->root;
    }

    public function push(Span $span): Span
    {
        $this->stack[] = $span;

        return $span;
    }

    public function pop(): ?Span
    {
        // Never pop the root.
        return count($this->stack) > 1 ? array_pop($this->stack) : null;
    }

    public function shouldCollect(): bool
    {
        return $this->sampled || $this->forceKeep;
    }

    public function forceKeep(): void
    {
        $this->forceKeep = true;
    }
}
