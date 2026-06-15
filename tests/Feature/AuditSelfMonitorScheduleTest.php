<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use VictorStochero\Warden\Tests\TestCase;

/**
 * A self-monitoring parent auto-audits itself: it registers warden:audit on its
 * own scheduler, fired only when the self-project's audit schedule (honoured
 * from the UI) says it is due. A parent without self-monitoring does not.
 */
class AuditSelfMonitorScheduleTest extends TestCase
{
    protected bool $selfMonitor = true;

    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', $this->selfMonitor);
    }

    protected function auditScheduled(): bool
    {
        $schedule = $this->app->make(Schedule::class);

        return (bool) array_filter(
            array_map(fn ($event) => $event->command, $schedule->events()),
            fn ($c) => is_string($c) && str_contains($c, 'warden:audit'),
        );
    }

    public function test_self_monitoring_parent_schedules_its_own_audit(): void
    {
        $this->assertTrue($this->auditScheduled());
    }
}
