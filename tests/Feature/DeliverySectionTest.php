<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DeliverySectionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    public function test_delivery_section_shows_recent_arrivals(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        // Two arrivals ~1 minute apart (cron-like cadence).
        foreach ([['b1', 120], ['b2', 60]] as [$batch, $secondsAgo]) {
            DB::table('wdn_ingested_batches')->insert([
                'project_id' => $project->id,
                'batch_id' => $batch,
                'received_at' => now()->subSeconds($secondsAgo),
            ]);
        }

        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'delivery']))
            ->assertOk()
            ->assertSee('Recent arrivals')
            ->assertSee('Last received');
    }

    public function test_delivery_handles_a_silent_child(): void
    {
        $project = Project::create(['name' => 'Quiet', 'slug' => 'quiet', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => 'quiet', 'section' => 'delivery']))
            ->assertOk()
            ->assertSee('Nothing received');
    }
}
