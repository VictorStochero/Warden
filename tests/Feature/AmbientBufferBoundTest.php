<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Fase 0 — long-running command / daemon safety. Logs and exceptions emitted
 * outside any entry-point trace (a daemon loop, boot) are rescued into an
 * ambient trace that has NO terminate boundary to flush it. The buffer must
 * therefore self-bound: ship and reset once it crosses flush_threshold, so a
 * daemon's memory stays flat instead of growing without limit.
 */
class AmbientBufferBoundTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.child.ambient.enabled', true);
        $app['config']->set('warden.child.ambient.flush_threshold', 5);
    }

    public function test_ambient_buffer_flushes_at_the_threshold_and_stays_flat(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        // No entry-point trace open: every log is rescued into the ambient trace.
        // Record far more events than the threshold; the invariant under test is
        // that the buffer is bounded — it ships and resets at the threshold rather
        // than growing with the number of records (a daemon's memory stays flat).
        $maxBuffer = 0;
        for ($i = 0; $i < 200; $i++) {
            $observer->record('log', ['level' => 'info', 'message' => "tick {$i}"]);
            $maxBuffer = max($maxBuffer, $observer->buffer()->count());
            $this->assertLessThan(5, $observer->buffer()->count(), 'ambient buffer must never exceed the threshold');
        }

        // The buffer really did fill toward the threshold (the flush is doing work,
        // not flushing on every single record), yet stayed bounded across all 200.
        $this->assertGreaterThanOrEqual(4, $maxBuffer);

        // Periodic flushing produced many batches instead of one giant buffer:
        // ~200 events / threshold 5 ≈ dozens of shipped batches.
        $this->assertGreaterThanOrEqual(30, OutboxEntry::count(), 'ambient capture must ship periodically, not accumulate');
    }
}
