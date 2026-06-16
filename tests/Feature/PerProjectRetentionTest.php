<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Per-project retention (§5.12). A project can retain raw events for FEWER days
 * than the global ceiling (privacy / cost) — the override only tightens below
 * the global window, since the global prune is the storage ceiling. A project
 * with no override follows the global retention.
 */
class PerProjectRetentionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.raw_retention_days', 7);
    }

    private function event(int $projectId, int $daysAgo): void
    {
        $at = Carbon::now()->subDays($daysAgo);

        Schema::db()->table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'log',
            'occurred_at' => $at,
            'occurred_date' => $at->toDateString(),
            'received_at' => $at,
            'payload' => null,
        ]);
    }

    public function test_a_project_override_prunes_sooner_than_the_global_window(): void
    {
        $tight = Project::create(['name' => 'Tight', 'slug' => 'tight', 'token' => 't1', 'secret' => 's1', 'active' => true, 'raw_retention_days' => 3]);
        $global = Project::create(['name' => 'Global', 'slug' => 'global', 'token' => 't2', 'secret' => 's2', 'active' => true]);

        // Tight: one event past its 3-day window, one inside it.
        $this->event($tight->id, 5);
        $this->event($tight->id, 1);
        // Global (no override): a 5-day event, well inside the 7-day global window.
        $this->event($global->id, 5);

        Artisan::call('warden:prune');

        $this->assertSame(1, Schema::db()->table('wdn_events')->where('project_id', $tight->id)->count(), 'tight project should keep only its in-window event');
        $this->assertSame(1, Schema::db()->table('wdn_events')->where('project_id', $global->id)->count(), 'global project keeps its 5-day event under the 7-day ceiling');
    }
}
