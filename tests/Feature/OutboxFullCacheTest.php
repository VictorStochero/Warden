<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Tests\TestCase;

class OutboxFullCacheTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.child.outbox_high_water', 3);
        $app['config']->set('warden.child.outbox_low_water', 1);
    }

    public function test_gate_pauses_capture_at_high_water_without_recounting_each_push(): void
    {
        $outbox = $this->app->make(Outbox::class);

        for ($i = 0; $i < 8; $i++) {
            $outbox->push(['batch_id' => "b{$i}", 'events' => [['type' => 'log', 'payload' => []]]]);
        }

        // high_water=3: the incremental counter trips the gate exactly at 3 rows.
        $this->assertSame(3, $outbox->size());
    }
}
