<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class SearchPaletteRenderTest extends TestCase
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

    public function test_overview_page_renders_command_palette_markup(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('data-wdn-palette', false)
            ->assertSee(__('warden::search.placeholder'))
            ->assertSee(route('warden.search'), false);
    }

    public function test_project_page_renders_command_palette_with_active_slug(): void
    {
        $project = $this->project();

        $this->get(route('warden.project', ['project' => $project->slug]))
            ->assertOk()
            ->assertSee('data-wdn-palette', false)
            ->assertSee(__('warden::search.placeholder'))
            ->assertSee(route('warden.search'), false)
            ->assertSee('data-search-slug="'.$project->slug.'"', false);
    }
}
