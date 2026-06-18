<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Per-project metric selection: the edit form writes a sparse sample.type_gate
 * (only the disabled types) into config and bumps config_version so the child
 * picks it up; the purge action reclaims space a now-disabled type already used.
 */
class ProjectMetricsConfigTest extends TestCase
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

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    /** @param array<string,string> $gate */
    private function update(Project $project, array $gate): void
    {
        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'off',
            'uptime_window' => '30d',
            'config' => ['sample' => ['type_gate' => $gate]],
        ])->assertRedirect(route('warden.admin.projects'));
    }

    public function test_edit_page_renders_metric_toggles_and_purge_form(): void
    {
        $project = $this->project();

        $this->get(route('warden.admin.projects.edit', $project->id))
            ->assertOk()
            ->assertSee('config[sample][type_gate][request]', false) // a metric toggle
            ->assertSee(route('warden.admin.projects.purge-type', $project->id), false); // purge form action
    }

    public function test_update_persists_only_disabled_types_and_bumps_version(): void
    {
        $project = $this->project();

        // request enabled (1), query disabled (0).
        $this->update($project, ['request' => '1', 'query' => '0']);

        $fresh = $project->fresh();
        $this->assertSame(['query' => false], $fresh->config['sample']['type_gate']);
        $this->assertSame(1, $fresh->config_version);
    }

    public function test_update_with_all_types_enabled_stores_no_type_gate(): void
    {
        $project = $this->project();

        $this->update($project, ['request' => '1', 'query' => '1']);

        $config = $project->fresh()->config ?? [];
        $this->assertArrayNotHasKey('type_gate', $config['sample'] ?? []);
    }

    public function test_purge_removes_only_the_target_type(): void
    {
        $project = $this->project();

        foreach (['query', 'request'] as $type) {
            DB::table('wdn_events')->insert([
                'project_id' => $project->id, 'type' => $type,
                'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'received_at' => now(),
            ]);
            DB::table('wdn_aggregates')->insert([
                'project_id' => $project->id, 'type' => $type, 'bucket' => '2026-01-01 00:00:00',
                'key' => '/x', 'count' => 1, 'sum_duration' => 1, 'max_duration' => 1,
                'meta' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->post(route('warden.admin.projects.purge-type', $project->id), ['type' => 'query'])
            ->assertRedirect(route('warden.admin.projects.edit', $project->id))
            ->assertSessionHas('warden_status');

        $this->assertSame(0, DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'query')->count());
        $this->assertSame(0, DB::table('wdn_aggregates')->where('project_id', $project->id)->where('type', 'query')->count());
        // Other types untouched.
        $this->assertSame(1, DB::table('wdn_events')->where('project_id', $project->id)->where('type', 'request')->count());
        $this->assertSame(1, DB::table('wdn_aggregates')->where('project_id', $project->id)->where('type', 'request')->count());
    }

    public function test_purge_rejects_an_unknown_type(): void
    {
        $project = $this->project();
        DB::table('wdn_events')->insert([
            'project_id' => $project->id, 'type' => 'request',
            'occurred_at' => now(), 'occurred_date' => now()->toDateString(), 'received_at' => now(),
        ]);

        $this->post(route('warden.admin.projects.purge-type', $project->id), ['type' => 'bogus'])
            ->assertRedirect(route('warden.admin.projects.edit', $project->id))
            ->assertSessionHas('warden_error');

        $this->assertSame(1, DB::table('wdn_events')->where('project_id', $project->id)->count());
    }

    public function test_purge_is_gated_by_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);
        $project = $this->project();

        $this->post(route('warden.admin.projects.purge-type', $project->id), ['type' => 'query'])
            ->assertForbidden();
    }
}
