<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class IncidentControllerTest extends TestCase
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

    public function test_index_lists_incidents(): void
    {
        $project = $this->project();
        Incident::create([
            'project_id' => $project->id, 'subject' => 'heartbeat:schedule:abc',
            'severity' => 'critical', 'status' => 'open', 'started_at' => now(),
            'summary' => 'No heartbeat for schedule:abc',
        ]);

        $this->get(route('warden.incidents', $project->slug))
            ->assertOk()
            ->assertSee('No heartbeat for schedule:abc');
    }

    public function test_show_renders_incident_detail(): void
    {
        $project = $this->project();
        $incident = Incident::create([
            'project_id' => $project->id, 'subject' => 'issue:abc123',
            'severity' => 'warning', 'status' => 'open', 'started_at' => now(),
            'summary' => 'RuntimeException: boom', 'meta' => ['issue_id' => 7, 'count' => 3],
        ]);

        $this->get(route('warden.incident', [$project->slug, $incident->id]))
            ->assertOk()
            ->assertSee('RuntimeException: boom')
            ->assertSee('issue:abc123');
    }

    public function test_resolve_marks_incident_resolved(): void
    {
        $project = $this->project();
        $incident = Incident::create([
            'project_id' => $project->id, 'subject' => 'issue:abc', 'severity' => 'warning',
            'status' => 'open', 'started_at' => now(),
        ]);

        $this->post(route('warden.incident.resolve', [$project->slug, $incident->id]))
            ->assertRedirect(route('warden.incident', [$project->slug, $incident->id]));

        $this->assertSame('resolved', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_resolve_is_gated_by_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);
        $project = $this->project();
        $incident = Incident::create([
            'project_id' => $project->id, 'subject' => 'issue:abc', 'severity' => 'warning',
            'status' => 'open', 'started_at' => now(),
        ]);

        $this->post(route('warden.incident.resolve', [$project->slug, $incident->id]))->assertForbidden();
    }
}
