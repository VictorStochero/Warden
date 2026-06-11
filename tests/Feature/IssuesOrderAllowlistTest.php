<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Contracts\WardenRepository;
use VictorStochero\Warden\Tests\TestCase;

/**
 * #13 — issues() must validate the `order` filter against an allowlist inside
 * the repository, independent of the caller, so a hostile value can never reach
 * orderByDesc() unparameterized.
 */
class IssuesOrderAllowlistTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function seedIssues(): int
    {
        $projectId = (int) DB::table('wdn_projects')->insertGetId([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Two rows whose orderings diverge between count and last_seen_at, so a
        // mis-applied order column would change which row comes first.
        DB::table('wdn_issues')->insert([
            [
                'project_id' => $projectId, 'fingerprint' => 'fp-old-busy', 'class' => 'Zzz', 'message' => 'old but busy',
                'count' => 100, 'first_seen_at' => now()->subDays(10), 'last_seen_at' => now()->subDays(5),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'project_id' => $projectId, 'fingerprint' => 'fp-new-rare', 'class' => 'B', 'message' => 'new but rare',
                'count' => 1, 'first_seen_at' => now()->subDay(), 'last_seen_at' => now(),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        return $projectId;
    }

    public function test_malicious_order_falls_back_to_last_seen_at_without_error(): void
    {
        $projectId = $this->seedIssues();
        $repo = $this->app->make(WardenRepository::class);

        $issues = $repo->issues($projectId, ['order' => 'id); drop table wdn_issues;--']);

        $this->assertCount(2, $issues);
        $this->assertSame(1, DB::table('wdn_issues')->count() > 0 ? 1 : 0); // table intact
        // Default order = last_seen_at desc -> the most-recently-seen row leads.
        $this->assertSame('new but rare', $issues->first()->message);
    }

    public function test_disallowed_real_column_is_clamped_to_default(): void
    {
        // `count` is allowed, but `class` is a real column NOT on the allowlist:
        // it must be clamped to last_seen_at, never used to order.
        $projectId = $this->seedIssues();
        $repo = $this->app->make(WardenRepository::class);

        $byClass = $repo->issues($projectId, ['order' => 'class']);
        $this->assertSame('new but rare', $byClass->first()->message); // last_seen_at, not class
    }

    public function test_valid_order_is_applied(): void
    {
        $projectId = $this->seedIssues();
        $repo = $this->app->make(WardenRepository::class);

        // count desc -> the busy (old) row leads, proving the column is honoured.
        $this->assertSame('old but busy', $repo->issues($projectId, ['order' => 'count'])->first()->message);
        // first_seen_at desc -> the newest first_seen leads.
        $this->assertSame('new but rare', $repo->issues($projectId, ['order' => 'first_seen_at'])->first()->message);
    }
}
