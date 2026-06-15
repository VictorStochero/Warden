<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class QueryHealthTest extends TestCase
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

    private function seedQuery(int $projectId, string $trace, string $sql): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'query',
            'trace_id' => $trace,
            'occurred_at' => now(),
            'occurred_date' => now()->toDateString(),
            'duration_us' => 1000,
            'payload' => json_encode(['sql' => $sql]),
        ]);
    }

    public function test_database_section_shows_n_plus_one_health(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->seedQuery($project->id, 'trace-np', 'select * from posts where id = 1');
        $this->seedQuery($project->id, 'trace-np', 'select * from posts where id = 2');
        $this->seedQuery($project->id, 'trace-np', 'select * from posts where id = 3');

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']))
            ->assertOk()
            ->assertSee(__('warden::project.database.health.title'))
            ->assertSee('trace-np');
    }

    public function test_database_section_shows_empty_state_when_no_query_events(): void
    {
        $project = Project::create(['name' => 'Empty', 'slug' => 'empty', 'token' => 'te', 'secret' => 'se', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']))
            ->assertOk()
            ->assertSee(__('warden::project.database.health.empty'));
    }
}
