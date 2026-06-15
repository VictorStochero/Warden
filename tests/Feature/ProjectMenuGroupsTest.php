<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ProjectMenuGroupsTest extends TestCase
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

    public function test_sidebar_shows_functional_groups_on_a_project_page(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $res = $this->get(route('warden.project', $project->slug))->assertOk();

        // Rótulos de grupo da filosofia Funcional.
        $res->assertSee('Performance');
        $res->assertSee('Reliability');
        $res->assertSee('Diagnostics');
        $res->assertSee('System');
        // A seção fundida aparece no menu.
        $res->assertSee('Database');
        // Itens realocados para grupos e overview restaurado.
        $res->assertSee('Overview');
        $res->assertSee('Traces');
        $res->assertSee('Issues');
        $res->assertSee('Incidents');
    }
}
