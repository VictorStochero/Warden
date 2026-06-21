<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Config\CaptureProfiles;
use VictorStochero\Warden\Dashboard\CaptureStatus;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;

class CaptureBannerTest extends TestCase
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

    public function test_capture_status_flags_existing_project_for_opt_in(): void
    {
        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $status = CaptureStatus::forProject($project);

        $this->assertTrue($status['needs_opt_in']);
        $this->assertFalse($status['reduced']); // full capture by default
    }

    public function test_override_less_project_is_not_reduced_even_on_a_lean_parent(): void
    {
        // Regression: the status reads ONLY the project's own stored config, never
        // the parent's runtime type gate — a lean self-monitoring parent would
        // otherwise flag every override-less child as reduced.
        config()->set('warden.child.sample.type_gate.cache', false);

        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->assertFalse(CaptureStatus::forProject($project)['reduced']);
    }

    public function test_capture_status_reports_reduced_for_a_lean_project(): void
    {
        $project = (new ProjectManager)->create('Lean App')['project'];

        $status = CaptureStatus::forProject($project);

        $this->assertFalse($status['needs_opt_in']);
        $this->assertTrue($status['reduced']);
        $this->assertContains('cache', $status['off']);
        $this->assertSame(100, $status['query_min_ms']);
    }

    public function test_opt_in_notice_renders_for_existing_project(): void
    {
        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project', $project->slug))
            ->assertOk()
            ->assertSee(__('warden::capture.optin_title'));
    }

    public function test_migrate_applies_the_lean_profile(): void
    {
        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.capture.migrate', $project->id))
            ->assertRedirect(route('warden.project', $project->slug));

        $project->refresh();
        $this->assertSame(CaptureProfiles::LEAN, $project->capture_profile);
        $this->assertSame(CaptureProfiles::lean(), $project->config);
        $this->assertSame(1, $project->config_version);
    }

    public function test_dismiss_keeps_full_capture(): void
    {
        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.capture.dismiss', $project->id))
            ->assertRedirect(route('warden.project', $project->slug));

        $this->assertSame(CaptureProfiles::FULL, $project->refresh()->capture_profile);
    }

    public function test_editing_recorders_marks_the_project_custom(): void
    {
        $project = Project::create(['name' => 'Legacy', 'slug' => 'legacy', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Legacy',
            'audit_frequency' => 'off',
            'uptime_window' => '30d',
            'config' => ['recorders' => ['request', 'exception']],
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(CaptureProfiles::CUSTOM, $project->capture_profile);
        $this->assertSame(['request', 'exception'], $project->config['recorders']);
    }
}
