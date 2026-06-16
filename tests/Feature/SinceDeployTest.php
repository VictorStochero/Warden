<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;

/**
 * "Since the last deploy" snapshot (§5.6): error count, throughput, error-rate
 * and new issues since the first event of the latest release. Empty state when
 * the project has no release marker yet.
 */
class SinceDeployTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function request(int $projectId, string $release, int $status, int $minutesAgo): void
    {
        $at = Carbon::now()->subMinutes($minutesAgo);

        Schema::db()->table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'occurred_at' => $at,
            'occurred_date' => $at->toDateString(),
            'received_at' => $at,
            'duration_us' => 5000,
            'release' => $release,
            'payload' => json_encode(['status' => $status]),
        ]);
    }

    public function test_snapshot_reflects_the_latest_release(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        // Older release (v1) — should be excluded from the snapshot.
        $this->request($project->id, 'v1', 200, 600);
        // Latest release (v2): 4 requests, 1 server error.
        $this->request($project->id, 'v2', 200, 30);
        $this->request($project->id, 'v2', 200, 20);
        $this->request($project->id, 'v2', 500, 10);
        $this->request($project->id, 'v2', 200, 5);

        // An issue first seen after the v2 deploy → counts as new since deploy.
        Schema::db()->table('wdn_issues')->insert([
            'project_id' => $project->id, 'fingerprint' => 'new', 'class' => 'E', 'message' => 'm',
            'count' => 1, 'users_affected' => 0, 'status' => 'open', 'first_seen_at' => Carbon::now()->subMinutes(8),
        ]);
        // An older issue, before the deploy → excluded.
        Schema::db()->table('wdn_issues')->insert([
            'project_id' => $project->id, 'fingerprint' => 'old', 'class' => 'E', 'message' => 'm',
            'count' => 1, 'users_affected' => 0, 'status' => 'open', 'first_seen_at' => Carbon::now()->subMinutes(900),
        ]);

        $snap = $this->app->make(DashboardRepository::class)->sinceDeploy($project->id);

        $this->assertSame('v2', $snap['release']);
        $this->assertSame(4, $snap['throughput']);
        $this->assertSame(1, $snap['errors']);
        $this->assertSame(25.0, $snap['error_rate']);
        $this->assertSame(1, $snap['new_issues']);
        $this->assertNotNull($snap['since']);
    }

    public function test_empty_state_when_no_release_is_known(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $snap = $this->app->make(DashboardRepository::class)->sinceDeploy($project->id);

        $this->assertNull($snap['release']);
        $this->assertSame(0, $snap['throughput']);
        $this->assertSame(0, $snap['errors']);
        $this->assertSame(0, $snap['new_issues']);
    }
}
