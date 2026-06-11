<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ConfigAdminUiTest extends TestCase
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

    public function test_update_saves_sparse_config_and_bumps_version(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek']);

        $this->post(route('warden.admin.projects.update', $p), [
            'name' => 'Demo',
            'config' => [
                'host_interval' => 50,
                'sample' => ['traces' => ['request' => 0.25]],
                'unknown' => 'drop-me',
            ],
        ])->assertRedirect();

        $fresh = $p->fresh();
        $this->assertSame(50, $fresh->config['host_interval']);
        $this->assertSame(0.25, $fresh->config['sample']['traces']['request']);
        $this->assertArrayNotHasKey('unknown', $fresh->config);
        $this->assertSame(1, $fresh->config_version);
    }

    public function test_update_does_not_bump_version_when_config_unchanged(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek',
            'config' => ['host_interval' => 50], 'config_version' => 4]);

        $this->post(route('warden.admin.projects.update', $p), [
            'name' => 'Demo',
            'config' => ['host_interval' => 50],
        ])->assertRedirect();

        $this->assertSame(4, $p->fresh()->config_version);
    }

    public function test_edit_page_renders_behaviour_section_with_current_values(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek',
            'config' => ['host_interval' => 42, 'recorders' => ['query']]]);

        $this->get(route('warden.admin.projects.edit', $p))
            ->assertOk()
            ->assertSee('Behaviour (advanced)')
            ->assertSee('config[host_interval]')
            ->assertSee('config[recorders][]');
    }

    public function test_update_persists_checked_recorders_from_form(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek']);

        $this->post(route('warden.admin.projects.update', $p), [
            'name' => 'Demo',
            'config' => ['recorders' => ['query', 'http']],
        ])->assertRedirect();

        $this->assertSame(['query', 'http'], $p->fresh()->config['recorders']);
    }
}
