<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Host gauges are point-in-time readings: when a second rollup run touches the
 * same aggregate bucket, the newest sample must replace the stored gauge —
 * never be summed into it (50% + 60% must not become 110%).
 */
class HostGaugeAggregationTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_a_second_rollup_replaces_host_gauges_instead_of_summing(): void
    {
        $projectId = $this->seedProject();
        $occurredAt = now()->startOfMinute();

        $this->insertHostEvent($projectId, $occurredAt, [
            'hostname' => 'web-1',
            'cpu' => 50.0,
            'memory' => ['used_percent' => 40.0],
            'load' => [1 => 0.5, 5 => 0.4, 15 => 0.3],
            'disk' => ['used_percent' => 30.0],
        ]);

        $this->artisan('warden:aggregate')->assertSuccessful();

        $this->insertHostEvent($projectId, $occurredAt, [
            'hostname' => 'web-1',
            'cpu' => 60.5,
            'memory' => ['used_percent' => 45.0],
            'load' => [1 => 0.7, 5 => 0.5, 15 => 0.3],
            'disk' => ['used_percent' => 31.0],
        ]);

        $this->artisan('warden:aggregate')->assertSuccessful();

        $meta = $this->app->make(DashboardRepository::class)->hostLatest($projectId, '24h');

        $this->assertNotNull($meta);
        $this->assertEqualsWithDelta(60.5, $meta['cpu'], 0.001, 'cpu must be replaced, not summed');
        $this->assertEqualsWithDelta(45.0, $meta['mem'], 0.001);
        $this->assertEqualsWithDelta(31.0, $meta['disk'], 0.001);

        // The row's event count still accumulates like any other aggregate.
        $row = DB::table('wdn_aggregates')
            ->where('project_id', $projectId)
            ->where('type', 'host')
            ->where('key', 'web-1')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row->count);
    }

    private function seedProject(): int
    {
        return (int) DB::table('wdn_projects')->insertGetId([
            'name' => 'Demo',
            'slug' => 'demo',
            'token' => 'tok-'.bin2hex(random_bytes(4)),
            'secret' => 'secret',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function insertHostEvent(int $projectId, \DateTimeInterface $occurredAt, array $payload): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'host',
            'trace_id' => bin2hex(random_bytes(8)),
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.u'),
            'occurred_date' => $occurredAt->format('Y-m-d'),
            'received_at' => now(),
            'duration_us' => 0,
            'payload' => (string) json_encode($payload),
        ]);
    }
}
