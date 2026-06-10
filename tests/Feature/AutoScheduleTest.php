<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use VictorStochero\Warden\Tests\TestCase;

class AutoScheduleTest extends TestCase
{
    /** @param list<string|null> $commands */
    private function isScheduled(array $commands, string $needle): bool
    {
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string|null> */
    private function scheduledCommands(): array
    {
        $schedule = $this->app->make(Schedule::class);

        return array_map(fn ($event) => $event->command, $schedule->events());
    }

    public function test_child_scheduler_mode_registers_ship_only(): void
    {
        $commands = $this->scheduledCommands();

        $this->assertTrue($this->isScheduled($commands, 'warden:ship'));
        $this->assertFalse($this->isScheduled($commands, 'warden:aggregate'));
    }
}
