<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Octane / long-lived worker safety (§18.2). The provider listens to the Octane
 * boundary events and calls Warden::reset(); dispatching those event classes by
 * string drives the same listener under Testbench, so the per-entry-point state
 * (trace + buffer + suppression) can be proven NOT to leak across requests that
 * share a worker. These are verification tests: the reset already exists, so a
 * green run is the expected outcome — a red one would be a real leak to fix.
 */
class OctaneResetTest extends TestCase
{
    private const REQUEST_RECEIVED = 'Laravel\Octane\Events\RequestReceived';

    private const REQUEST_TERMINATED = 'Laravel\Octane\Events\RequestTerminated';

    public function test_request_terminated_clears_per_request_state(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        $observer->startTrace('request', name: '/a');
        $observer->record('query', ['sql' => 'select 1']);
        $this->assertTrue($observer->hasTrace());

        $this->app['events']->dispatch(self::REQUEST_TERMINATED);

        $this->assertFalse($observer->hasTrace(), 'Octane boundary must drop the trace');
        $this->assertSame(0, $observer->buffer()->count(), 'Buffer must be empty after reset');
    }

    public function test_state_does_not_leak_between_two_requests_on_one_worker(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        // Request A
        $observer->startTrace('request', name: '/a');
        $observer->keep();
        $observer->record('query', ['sql' => 'select * from a']);
        $traceA = $observer->trace()->traceId;
        $this->app['events']->dispatch(self::REQUEST_TERMINATED); // worker recicla

        // Request B (mesmo processo)
        $observer->startTrace('request', name: '/b');
        $observer->keep();
        $observer->record('query', ['sql' => 'select * from b']);
        $traceB = $observer->trace()->traceId;
        $observer->flush();

        $batch = OutboxEntry::first()->batch;
        $traceIds = collect($batch['events'])->pluck('trace_id')->unique()->values()->all();

        $this->assertNotSame($traceA, $traceB, 'Each request must get its own trace id');
        $this->assertSame([$traceB], $traceIds, 'Request B must contain ONLY its own events');
    }

    public function test_events_are_not_double_captured_after_repeated_boot_signals(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        // Simula vários ciclos de boot/recic. do Octane
        for ($i = 0; $i < 5; $i++) {
            $this->app['events']->dispatch(self::REQUEST_RECEIVED);
        }

        $observer->startTrace('request');
        $observer->keep();
        $observer->record('query', ['sql' => 'select 1']); // UM evento

        $observer->flush();
        $batch = OutboxEntry::first()->batch;

        $queryEvents = collect($batch['events'])->where('type', 'query');
        $this->assertCount(1, $queryEvents, 'A single query must not be captured multiple times');
    }

    public function test_memory_stays_flat_across_many_reset_cycles(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        // Aquecimento para estabilizar alocações iniciais.
        for ($i = 0; $i < 500; $i++) {
            $observer->startTrace('request');
            $observer->record('query', ['sql' => 'select 1']);
            $this->app['events']->dispatch(self::REQUEST_TERMINATED);
        }
        $baseline = memory_get_usage();

        for ($i = 0; $i < 10000; $i++) {
            $observer->startTrace('request');
            $observer->record('query', ['sql' => 'select '.$i]);
            $observer->record('cache', ['action' => 'hit', 'key' => "k$i", 'hit' => true]);
            $this->app['events']->dispatch(self::REQUEST_TERMINATED);
        }

        $growth = memory_get_usage() - $baseline;
        // Teto generoso; o objetivo é pegar VAZAMENTO (crescimento linear), não micro-ruído.
        $this->assertLessThan(5 * 1024 * 1024, $growth, 'Per-request state must not accumulate');
    }
}
