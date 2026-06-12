<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Octane/worker boundary semantics: an ambient batch (trace-less logs or
 * exceptions rescued between entry points) has no boundary of its own and the
 * process-shutdown hook never fires in a long-lived worker — so reset() must
 * ship it, while non-ambient leftovers are still dropped (§18.2).
 */
class AmbientResetFlushTest extends TestCase
{
    public function test_reset_ships_a_pending_ambient_batch_instead_of_dropping_it(): void
    {
        $warden = $this->app->make(Warden::class);

        // A log captured with no entry-point trace open (e.g. between Octane
        // requests) opens an ambient trace below the flush threshold.
        $warden->record('log', ['level' => 'info', 'message' => 'between requests']);

        $this->assertSame('ambient', $warden->trace()?->entryType);

        $warden->reset();

        $this->assertSame(1, OutboxEntry::count(), 'the ambient batch must be shipped, not dropped');
        $this->assertNull($warden->trace());
        $this->assertTrue($warden->buffer()->isEmpty());
    }

    public function test_reset_still_drops_non_ambient_state(): void
    {
        $warden = $this->app->make(Warden::class);

        $warden->startTrace('request');
        $warden->record('log', ['level' => 'info', 'message' => 'mid-request']);

        $warden->reset();

        $this->assertSame(0, OutboxEntry::count(), 'an aborted entry point must not leak a partial batch');
        $this->assertNull($warden->trace());
        $this->assertTrue($warden->buffer()->isEmpty());
    }
}
