<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DatabaseSectionTest extends TestCase
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

    public function test_database_section_renders(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']))
            ->assertOk()
            ->assertSee('Queries')
            ->assertSee('Cache');
    }

    public function test_queries_route_redirects_to_database(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'queries']))
            ->assertRedirect(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']));
    }

    public function test_cache_route_redirects_to_database(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'cache']))
            ->assertRedirect(route('warden.project.section', ['project' => $project->slug, 'section' => 'database']));
    }
}
