<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Tests\TestCase;

class UptimeTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function reader(): DatabaseWardenRepository
    {
        /** @var DatabaseWardenRepository $reader */
        $reader = $this->app->make(DatabaseWardenRepository::class);

        return $reader;
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    public function test_no_incidents_is_full_uptime(): void
    {
        $p = $this->project();

        $this->assertSame(100.0, $this->reader()->uptime($p->id, '30d'));
    }

    public function test_open_critical_incident_spanning_window_is_zero_uptime(): void
    {
        $p = $this->project();
        Incident::create([
            'project_id' => $p->id, 'subject' => 'heartbeat:x', 'severity' => 'critical',
            'status' => 'open', 'started_at' => now()->subDays(40), 'resolved_at' => null,
        ]);

        $this->assertSame(0.0, $this->reader()->uptime($p->id, '30d'));
    }

    public function test_warning_incidents_do_not_count_as_downtime(): void
    {
        $p = $this->project();
        Incident::create([
            'project_id' => $p->id, 'subject' => 'issue:x', 'severity' => 'warning',
            'status' => 'open', 'started_at' => now()->subDays(40), 'resolved_at' => null,
        ]);

        $this->assertSame(100.0, $this->reader()->uptime($p->id, '30d'));
    }

    public function test_overlapping_incidents_are_not_double_counted(): void
    {
        $p = $this->project();
        // Two fully-overlapping outages over the whole window -> still 0% (not negative).
        foreach ([0, 1] as $i) {
            Incident::create([
                'project_id' => $p->id, 'subject' => "heartbeat:x{$i}", 'severity' => 'critical',
                'status' => 'open', 'started_at' => now()->subDays(40), 'resolved_at' => null,
            ]);
        }

        $this->assertSame(0.0, $this->reader()->uptime($p->id, '30d'));
    }
}
