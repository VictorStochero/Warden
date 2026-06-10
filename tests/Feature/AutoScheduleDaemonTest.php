<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use VictorStochero\Warden\Tests\TestCase;

class AutoScheduleDaemonTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.child.delivery', 'daemon');
    }

    public function test_daemon_delivery_does_not_schedule_ship(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $commands = array_map(fn ($event) => $event->command, $schedule->events());

        $shipScheduled = (bool) array_filter(
            $commands,
            fn ($c) => is_string($c) && str_contains($c, 'warden:ship')
        );

        $this->assertFalse($shipScheduled);
    }
}
