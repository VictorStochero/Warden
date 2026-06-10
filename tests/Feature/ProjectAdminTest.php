<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Dashboard\Format;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ProjectAdminTest extends TestCase
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

    public function test_index_renders(): void
    {
        Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.admin.projects'))->assertOk()->assertSee('Demo');
    }

    public function test_store_creates_project_and_shows_install_command_once(): void
    {
        $this->post(route('warden.admin.projects.store'), ['name' => 'My App'])
            ->assertRedirect(route('warden.admin.projects'));

        $this->assertDatabaseHas('wdn_projects', ['slug' => 'my-app']);

        $this->followingRedirects()
            ->post(route('warden.admin.projects.store'), ['name' => 'Other App'])
            ->assertOk()
            ->assertSee('warden:install --child')
            ->assertSee('WARDEN_TOKEN='); // .env block shown alongside the command
    }

    public function test_rotate_changes_credentials(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'old-token', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.rotate', $project->id))
            ->assertRedirect(route('warden.admin.projects'));

        $this->assertNotSame('old-token', $project->fresh()->token);
    }

    public function test_toggle_deactivates(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.toggle', $project->id))
            ->assertRedirect()
            ->assertSessionHas('warden_status', 'Demo deactivated.');

        $this->assertFalse($project->fresh()->active);
    }

    public function test_reset_clears_saved_metrics_but_keeps_the_project(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        DB::table('wdn_aggregates')->insert([
            'project_id' => $project->id, 'type' => 'request', 'bucket' => '2026-01-01 00:00:00',
            'key' => '/x', 'count' => 5, 'sum_duration' => 100, 'max_duration' => 50,
            'meta' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post(route('warden.admin.projects.reset', $project->id))
            ->assertRedirect(route('warden.admin.projects'))
            ->assertSessionHas('warden_status');

        $this->assertSame(0, DB::table('wdn_aggregates')->where('project_id', $project->id)->count());
        $this->assertDatabaseHas('wdn_projects', ['id' => $project->id]);
    }

    public function test_update_persists_the_audit_schedule_and_uptime_window(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'weekly',
            'audit_day' => 3, // Wednesday
            'audit_hour' => 9,
            'uptime_window' => '7d',
        ])->assertRedirect(route('warden.admin.projects'));

        $fresh = $project->fresh();
        $this->assertSame('weekly', $fresh->audit_frequency);
        $this->assertSame(3, $fresh->audit_day);
        $this->assertSame(9, $fresh->audit_hour);
        $this->assertSame('7d', $fresh->uptime_window);
    }

    public function test_update_clears_audit_day_and_hour_when_frequency_is_off(): void
    {
        $project = Project::create([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true,
            'audit_frequency' => 'weekly', 'audit_day' => 2, 'audit_hour' => 8,
        ]);

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'off',
            'uptime_window' => '30d',
        ])->assertRedirect(route('warden.admin.projects'));

        $fresh = $project->fresh();
        $this->assertSame('off', $fresh->audit_frequency);
        $this->assertNull($fresh->audit_day);
        $this->assertNull($fresh->audit_hour);
    }

    public function test_update_rejects_an_invalid_uptime_window(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'off',
            'uptime_window' => 'forever',
        ])->assertSessionHas('warden_error');

        $this->assertSame('30d', $project->fresh()->uptime_window); // unchanged default
    }

    public function test_audit_now_requests_an_immediate_audit(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
        $this->assertNull($project->audit_requested_at);

        $this->post(route('warden.admin.projects.audit-now', $project->id))->assertRedirect();

        $this->assertNotNull($project->fresh()->audit_requested_at);
    }

    public function test_timezone_can_be_set_and_is_validated(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.timezone', $project->id), ['timezone' => 'America/Mexico_City'])
            ->assertRedirect(route('warden.admin.projects'));
        $this->assertSame('America/Mexico_City', $project->fresh()->timezone);

        $this->post(route('warden.admin.projects.timezone', $project->id), ['timezone' => 'Not/AZone'])
            ->assertSessionHas('warden_error');
        $this->assertSame('America/Mexico_City', $project->fresh()->timezone); // unchanged
    }

    public function test_format_at_converts_to_the_display_timezone(): void
    {
        Format::tz('UTC');
        $this->assertSame('12:00', Format::at('2026-01-01 12:00:00', 'H:i'));

        Format::tz('America/Sao_Paulo'); // -3
        $this->assertSame('09:00', Format::at('2026-01-01 12:00:00', 'H:i'));

        Format::tz(null); // reset shared state
    }

    public function test_admin_is_gated_by_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);

        $this->get(route('warden.admin.projects'))->assertForbidden();
        $this->post(route('warden.admin.projects.store'), ['name' => 'Nope'])->assertForbidden();
    }
}
