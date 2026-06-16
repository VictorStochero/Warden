<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Multi-resolution rollups (§5.8): the aggregator produces both the fine base
 * buckets and coarse daily buckets, so a long-window read (30d) is served by a
 * handful of daily rows instead of thousands of per-minute rows — while the
 * totals stay identical to the raw events.
 */
class MultiResolutionRollupTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function seedRequest(int $projectId, Carbon $at): void
    {
        Schema::db()->table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'occurred_at' => $at,
            'occurred_date' => $at->toDateString(),
            'received_at' => $at,
            'duration_us' => 2000,
            'payload' => json_encode(['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 200]),
        ]);
    }

    public function test_rollup_produces_both_base_and_daily_resolutions(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        // 8 requests spread across three distinct days, all within 30d.
        $this->seedRequest($project->id, Carbon::now());
        $this->seedRequest($project->id, Carbon::now());
        $this->seedRequest($project->id, Carbon::now());
        $this->seedRequest($project->id, Carbon::now()->subDay());
        $this->seedRequest($project->id, Carbon::now()->subDay());
        $this->seedRequest($project->id, Carbon::now()->subDays(2));
        $this->seedRequest($project->id, Carbon::now()->subDays(2));
        $this->seedRequest($project->id, Carbon::now()->subDays(2));

        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        $base = Schema::db()->table('wdn_aggregates')->where('project_id', $project->id)->where('resolution', 60);
        $daily = Schema::db()->table('wdn_aggregates')->where('project_id', $project->id)->where('resolution', 86400);

        // Both resolutions exist and each accounts for all 8 events.
        $this->assertGreaterThanOrEqual(1, $daily->count());
        $this->assertSame(8, (int) $base->sum('count'));
        $this->assertSame(8, (int) $daily->sum('count'));
        // Daily collapses the three days into (at most) three rows for the route.
        $this->assertLessThanOrEqual(3, $daily->count());
    }

    public function test_long_window_kpis_match_the_raw_totals(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->seedRequest($project->id, Carbon::now());
        $this->seedRequest($project->id, Carbon::now()->subDays(2));
        $this->seedRequest($project->id, Carbon::now()->subDays(10));

        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        $kpis = $this->app->make(DashboardRepository::class)->kpis($project->id, '30d');

        $this->assertSame(3, $kpis['throughput']);
    }
}
