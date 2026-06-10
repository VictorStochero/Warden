<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use VictorStochero\Warden\Tests\TestCase;

class AutoScheduleParentTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_parent_registers_maintenance_and_not_ship(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $commands = array_map(fn ($event) => $event->command, $schedule->events());

        $present = fn (string $needle): bool => (bool) array_filter(
            $commands,
            fn ($c) => is_string($c) && str_contains($c, $needle)
        );

        $this->assertTrue($present('warden:aggregate'));
        $this->assertTrue($present('warden:evaluate'));
        $this->assertTrue($present('warden:partition'));
        $this->assertTrue($present('warden:prune'));
        $this->assertFalse($present('warden:ship'));
    }
}
