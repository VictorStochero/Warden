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

    /**
     * Regression: the palette overlay carries the `flex` utility for centering,
     * which overrides Tailwind's preflight `[hidden]{display:none}` — so the
     * `hidden` attribute alone never hid it and the palette stayed permanently
     * open, blocking the UI on mobile. A scoped, higher-specificity rule must
     * keep the hidden state working.
     */
    public function test_hidden_palette_is_actually_hidden_by_css(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('[data-wdn-palette][hidden]{display:none}', false);
    }
}
