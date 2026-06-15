<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Release/deploy tracking (§5.6), read side: the dashboard can list the releases
 * seen for a project and slice "errors since this deploy" by filtering on the
 * release marker stored with each event.
 */
class ReleaseUiTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
    }

    private function project(): Project
    {
        return Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );
    }

    private function seedError(string $release): void
    {
        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b-'.uniqid(), 'events' => [
            ['type' => 'request', 'trace_id' => 't'.uniqid(), 'occurred_at' => $at, 'release' => $release, 'payload' => ['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 500]],
        ]]]);
    }

    private function repo(): DashboardRepository
    {
        return $this->app->make(DashboardRepository::class);
    }

    public function test_releases_lists_the_distinct_releases_seen(): void
    {
        $project = $this->project();
        $this->seedError('v1.0.0');
        $this->seedError('v2.0.0');

        $releases = $this->repo()->releases($project->id)->all();

        $this->assertContains('v1.0.0', $releases);
        $this->assertContains('v2.0.0', $releases);
    }

    public function test_recent_errors_can_be_sliced_by_release(): void
    {
        $project = $this->project();
        $this->seedError('v1.0.0');
        $this->seedError('v2.0.0');

        $only = $this->repo()->recentErrors($project->id, 50, 'v2.0.0');

        $this->assertCount(1, $only);
        $this->assertSame('v2.0.0', $only->first()->release);
    }

    public function test_event_detail_surfaces_the_release(): void
    {
        $this->project();
        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'be', 'events' => [
            ['type' => 'exception', 'trace_id' => 't9', 'occurred_at' => $at, 'release' => 'v3.1.4', 'payload' => ['class' => 'E', 'message' => 'boom']],
        ]]]);

        $id = DB::table('wdn_events')->where('type', 'exception')->value('id');

        $this->get(route('warden.event', ['demo', $id]))
            ->assertOk()
            ->assertSee('v3.1.4');
    }

    public function test_errors_section_renders_the_release_filter(): void
    {
        $this->project();
        $this->seedError('v1.0.0');

        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'errors']))
            ->assertOk()
            ->assertSee(__('warden::project.errors.release_filter'))
            ->assertSee('v1.0.0');
    }
}
