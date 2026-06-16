<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Fase 0 — outbox backpressure hysteresis (§18.6). Capture pauses at the high
 * water mark so the host disk can't fill, and stays paused until the queue
 * drains BELOW the low water mark — not at the first row removed. This proves
 * the resume side that OutboxFullCacheTest's pause side doesn't.
 */
class OutboxBackpressureTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.child.outbox_high_water', 5);
        $app['config']->set('warden.child.outbox_low_water', 2);
    }

    private function push(Outbox $outbox, string $id): void
    {
        $outbox->push(['batch_id' => $id, 'events' => [['type' => 'log', 'payload' => []]]]);
    }

    public function test_capture_pauses_at_high_water_and_resumes_only_below_low_water(): void
    {
        $outbox = $this->app->make(Outbox::class);

        // Fill to the high-water mark → capture is paused.
        for ($i = 0; $i < 5; $i++) {
            $this->push($outbox, "b{$i}");
        }
        $this->assertSame(5, $outbox->size());
        $this->assertTrue($outbox->isFull(), 'must be paused at high water');

        // Drain through the real shipping path (reserve once, delete oldest first).
        $reserved = $outbox->reserve(100);

        // Down to 3 (above low water of 2): still paused — the gate is sticky.
        $outbox->delete($reserved[0]);
        $outbox->delete($reserved[1]);
        $this->assertSame(3, $outbox->size());
        $this->assertTrue($outbox->isFull(), 'must stay paused until below low water');

        // Down to the low water mark (2): now capture resumes.
        $outbox->delete($reserved[2]);
        $this->assertSame(2, $outbox->size());
        $this->assertFalse($outbox->isFull(), 'must resume once drained to low water');

        // And a push is accepted again.
        $this->push($outbox, 'after-resume');
        $this->assertSame(3, OutboxEntry::count());
    }
}
