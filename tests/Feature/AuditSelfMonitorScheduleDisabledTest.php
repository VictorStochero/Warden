<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use VictorStochero\Warden\Tests\TestCase;

/**
 * A parent with self-monitoring off has no self-project to audit, so it must not
 * register warden:audit on its scheduler (the maintenance commands still run).
 */
class AuditSelfMonitorScheduleDisabledTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', false);
    }

    public function test_a_non_self_monitoring_parent_does_not_schedule_audit(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $commands = array_map(fn ($event) => $event->command, $schedule->events());

        $present = fn (string $needle): bool => (bool) array_filter(
            $commands,
            fn ($c) => is_string($c) && str_contains($c, $needle),
        );

        $this->assertFalse($present('warden:audit'));
        // Parent maintenance is unaffected.
        $this->assertTrue($present('warden:aggregate'));
    }
}
