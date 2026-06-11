<?php

namespace VictorStochero\Warden;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use VictorStochero\Warden\Buffer\EventBuffer;
use VictorStochero\Warden\Ingestion\SelfDelivery;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Sampling\Sampler;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Ids;
use VictorStochero\Warden\Trace\Span;
use VictorStochero\Warden\Trace\TraceContext;

/**
 * The package's per-process brain. Holds the reentrant suppression flag, the
 * active TraceContext and the per-entry-point EventBuffer, and turns recorder
 * calls into buffered events that are flushed to the outbox.
 *
 * Registered as a singleton, but all mutable request state (trace, buffer,
 * suppression) is reset on entry-point boundaries so it is safe under Octane
 * and long-lived queue workers (§18.2).
 */
class Warden
{
    /** Reentrant suppression depth. > 0 means recorders are no-ops (§18.3). */
    protected int $suppression = 0;

    protected ?TraceContext $trace = null;

    protected EventBuffer $buffer;

    /**
     * Local delivery sink for parent self-monitoring. Set by the service provider
     * when (and only when) the parent is self-monitoring; null otherwise, in
     * which case flush falls back to the outbox (child path).
     */
    protected ?SelfDelivery $selfDelivery = null;

    public function __construct(
        protected Container $app,
        protected Repository $config,
        protected Sampler $sampler,
    ) {
        $this->buffer = new EventBuffer;
    }

    // ---------------------------------------------------------------- mode

    public function mode(): string
    {
        return Cast::str($this->config->get('warden.mode', 'child'), 'child');
    }

    public function isChild(): bool
    {
        return $this->mode() === 'child';
    }

    public function isParent(): bool
    {
        return $this->mode() === 'parent';
    }

    /**
     * Whether the parent observes itself (Frente 1). Captures and persists its
     * own events straight to the local database via SelfDelivery, gated by the
     * warden.parent.self_monitor flag (default on). Always false for a child.
     */
    public function selfMonitoring(): bool
    {
        return $this->isParent()
            && Cast::bool($this->config->get('warden.parent.self_monitor', true));
    }

    /**
     * Whether this process should be capturing at all: a configured child, or a
     * self-monitoring parent. The trace middleware and flush key off this so the
     * same recording pipeline serves both roles.
     */
    public function capturing(): bool
    {
        return ($this->isChild() && $this->isChildConfigured()) || $this->selfMonitoring();
    }

    /** Install the local delivery sink used by a self-monitoring parent. */
    public function setSelfDelivery(SelfDelivery $delivery): void
    {
        $this->selfDelivery = $delivery;
    }

    /**
     * Whether this child has the minimum config to ship: a parent URL and a
     * token. A child without them (e.g. freshly installed, not yet configured)
     * stays inert — it captures nothing and schedules no shipping.
     */
    public function isChildConfigured(): bool
    {
        return Cast::str($this->config->get('warden.child.parent_url')) !== ''
            && Cast::str($this->config->get('warden.child.token')) !== '';
    }

    /** Whether a recorder is enabled in config (the §9 enable/disable switch). */
    public function recorderEnabled(string $name): bool
    {
        return in_array($name, (array) $this->config->get('warden.child.recorders', []), true);
    }

    // -------------------------------------------------------- suppression

    public function recording(): bool
    {
        return $this->suppression === 0;
    }

    /**
     * Run a callback with all recording suppressed. Used around every internal
     * I/O path (outbox flush, ship POST, ingest writes, alert channels) so the
     * package never observes itself. Reentrant; resets in finally (§18.3).
     *
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public function withoutRecording(Closure $callback): mixed
    {
        $this->suppression++;

        try {
            return $callback();
        } finally {
            $this->suppression--;
        }
    }

    // -------------------------------------------------------------- trace

    public function hasTrace(): bool
    {
        return $this->trace !== null;
    }

    public function trace(): ?TraceContext
    {
        return $this->trace;
    }

    /**
     * Open a trace for an entry point. If one is already open in this process
     * (e.g. a sync job inside a request) the existing context is reused so we
     * don't fork the timeline (§18.1).
     *
     * @param  array<string, mixed>|null  $inherited  carries trace_id/parent_span_id/sampled across a queue boundary
     */
    public function startTrace(string $entryType, ?array $inherited = null, ?string $name = null): TraceContext
    {
        if ($this->trace !== null) {
            return $this->trace;
        }

        $sampled = isset($inherited['sampled'])
            ? (bool) $inherited['sampled']
            : $this->sampler->sampleTrace($entryType);

        $this->trace = new TraceContext(
            entryType: $entryType,
            sampled: $sampled,
            traceId: isset($inherited['trace_id']) ? Cast::str($inherited['trace_id']) : null,
            parentSpanId: isset($inherited['parent_span_id']) ? Cast::str($inherited['parent_span_id']) : null,
        );

        if ($name !== null) {
            $this->trace->root->name = $name;
        }

        return $this->trace;
    }

    public function startSpan(string $type, ?string $name = null): ?Span
    {
        if ($this->trace === null) {
            return null;
        }

        return $this->trace->push(new Span($type, $this->trace->currentSpan()->id, $name));
    }

    public function endSpan(): void
    {
        $this->trace?->pop();
    }

    // ------------------------------------------------------------ record

    /**
     * Append an event to the buffer. The single entry point every recorder
     * calls. Cheap by design: an array push, no serialization, no I/O (RNF-1).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $type,
        array $payload,
        ?int $durationUs = null,
        ?string $spanId = null,
        ?string $parentSpanId = null,
        ?string $occurredAt = null,
    ): void {
        if (! $this->recording()) {
            return;
        }

        // Axis B — global per-type gate, independent of any trace (§18.4).
        if (! $this->sampler->typeEnabled($type)) {
            return;
        }

        if ($this->trace === null) {
            return;
        }

        $current = $this->trace->currentSpan();

        $this->buffer->add([
            'type' => $type,
            'trace_id' => $this->trace->traceId,
            'span_id' => $spanId ?? Ids::generate(),
            'parent_span_id' => $parentSpanId ?? $current->id,
            'occurred_at' => $occurredAt ?? $this->microNow(),
            'duration_us' => $durationUs,
            'payload' => $payload,
        ]);
    }

    /** Promote the current trace to force-keep (tail-based, §18.4). */
    public function keep(): void
    {
        $this->trace?->forceKeep();
    }

    /** Record the authenticated user for the current entry point. */
    public function setUser(int|string|null $userId): void
    {
        if ($this->trace !== null) {
            $this->trace->userId = $userId;
        }
    }

    public function userId(): int|string|null
    {
        return $this->trace?->userId;
    }

    // --------------------------------------------------------- lifecycle

    /**
     * Close the current entry point: apply tail-based promotion, persist the
     * buffer to the outbox if the trace is kept, then clear state. Called at
     * terminate / CommandFinished / JobProcessed (one trace per flush).
     */
    public function flush(): void
    {
        $trace = $this->trace;

        try {
            if (! $this->capturing() || $trace === null || $this->buffer->isEmpty()) {
                return;
            }

            $this->applySlowKeep($trace);

            if (! $trace->shouldCollect()) {
                return;
            }

            $events = $this->buffer->all();
            $batchId = Ids::generate();

            $this->withoutRecording(function () use ($trace, $events, $batchId) {
                try {
                    // Parent self-monitoring writes straight to the local DB; a
                    // child queues the batch in the outbox for shipping (§Frente 1).
                    if ($this->selfDelivery !== null) {
                        $this->selfDelivery->deliver($batchId, $events);

                        return;
                    }

                    $this->outbox()->push([
                        'schema_version' => 1,
                        'batch_id' => $batchId,
                        'trace_id' => $trace->traceId,
                        'entry_type' => $trace->entryType,
                        'captured_at' => $this->microNow(),
                        'events' => $events,
                    ]);
                } catch (\Throwable) {
                    // RNF-2: capture must never break the host app. If a table is
                    // missing (not migrated yet) or the database is unavailable,
                    // drop this batch silently instead of letting the exception
                    // propagate into the request/command lifecycle.
                }
            });
        } finally {
            $this->resetTrace();
        }
    }

    /** Octane / worker boundary: drop all per-entry-point state. */
    public function reset(): void
    {
        $this->resetTrace();
        $this->suppression = 0;
    }

    protected function resetTrace(): void
    {
        $this->trace = null;
        $this->buffer->clear();
    }

    protected function applySlowKeep(TraceContext $trace): void
    {
        // Exception-based promotion is set live by the recorders (gated on the
        // always_keep.on_exception flag). Here we add the slow-trace promotion.
        $threshold = Cast::int($this->config->get('warden.child.sample.always_keep.slower_than_ms', 0));

        if ($threshold > 0 && $trace->root->elapsedUs() >= $threshold * 1000) {
            $trace->forceKeep();
        }
    }

    protected function outbox(): Outbox
    {
        return $this->app->make(Outbox::class);
    }

    public function buffer(): EventBuffer
    {
        return $this->buffer;
    }

    protected function microNow(): string
    {
        return now()->utc()->format('Y-m-d H:i:s.u');
    }
}
