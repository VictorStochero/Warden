<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class EvaluatorHeartbeatTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function scheduleEvent(int $projectId, string $key, int $secondsAgo): void
    {
        $at = now()->subSeconds($secondsAgo);

        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'schedule',
            'trace_id' => str_repeat('b', 32),
            'occurred_at' => $at,
            'occurred_date' => $at->toDateString(),
            'duration_us' => 1000,
            'payload' => json_encode(['task' => 'demo', 'heartbeat' => $key]),
        ]);
    }

    public function test_heartbeat_interval_uses_the_median_gap_not_the_minimum(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
        $key = 'schedule:demo';

        // Gaps between consecutive runs: 300, 300, 60, 300 -> median 300 (min is 60).
        foreach ([0, 300, 600, 660, 960] as $secondsAgo) {
            $this->scheduleEvent($project->id, $key, $secondsAgo);
        }

        $this->artisan('warden:evaluate')->assertSuccessful();

        $hb = DB::table('wdn_heartbeats')->where('project_id', $project->id)->where('key', $key)->first();

        $this->assertNotNull($hb);
        $this->assertSame(300, (int) $hb->expected_interval, 'the bunched 60s pair must not collapse the inferred cadence');
    }
}
