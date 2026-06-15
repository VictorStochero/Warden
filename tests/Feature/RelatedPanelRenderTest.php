<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class RelatedPanelRenderTest extends TestCase
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
        return Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedEvent(int $projectId, string $traceId, string $type, array $payload, int $id): void
    {
        $now = Carbon::now()->toDateTimeString();

        DB::table('wdn_events')->insert([
            'id' => $id,
            'project_id' => $projectId,
            'trace_id' => $traceId,
            'span_id' => 's'.$id,
            'parent_span_id' => null,
            'type' => $type,
            'occurred_at' => $now,
            'occurred_date' => Carbon::now()->toDateString(),
            'duration_us' => 1000,
            'payload' => json_encode($payload),
            'release' => null,
            'received_at' => $now,
        ]);
    }

    public function test_trace_page_shows_related_panel_with_entry_label(): void
    {
        $project = $this->project();

        $this->seedEvent($project->id, 'trace-checkout', 'request', ['method' => 'GET', 'route' => '/checkout', 'status' => 200], 1);
        $this->seedEvent($project->id, 'trace-checkout', 'query', ['sql' => 'select * from carts where id = 1'], 2);

        $this->get(route('warden.trace', ['project' => $project->slug, 'traceId' => 'trace-checkout']))
            ->assertOk()
            ->assertSee(__('warden::related.heading'))
            ->assertSee('GET /checkout');
    }

    public function test_project_section_shows_related_panel_fallback(): void
    {
        $project = $this->project();

        $this->seedEvent($project->id, 'trace-home', 'request', ['method' => 'GET', 'route' => '/home', 'status' => 200], 10);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']))
            ->assertOk()
            ->assertSee(__('warden::related.heading'))
            ->assertSee(__('warden::related.recent_traces'))
            ->assertSee('/home');
    }

    public function test_project_section_shows_empty_state_when_no_data(): void
    {
        $project = $this->project();

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']))
            ->assertOk()
            ->assertSee(__('warden::related.heading'))
            ->assertSee(__('warden::related.empty'));
    }
}
