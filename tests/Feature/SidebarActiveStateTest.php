<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Admin views @extends the layout and loop `@foreach($projects as $project)`, which
 * leaks the loop variable into the layout via @extends' get_defined_vars(). The
 * sidebar must only highlight a project on its own pages, not on /admin/projects.
 */
class SidebarActiveStateTest extends TestCase
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

    public function test_manage_projects_does_not_mark_a_sidebar_project_active(): void
    {
        Project::create(['name' => 'Laravel', 'slug' => 'laravel', 'token' => 't', 'secret' => 's', 'active' => true]);

        // `bg-brand-400` is the active project's status dot — it must not appear
        // when no project page is open (otherwise the leaked loop var won).
        $this->get(route('warden.admin.projects'))
            ->assertOk()
            ->assertDontSee('bg-brand-400');
    }

    public function test_a_project_page_marks_its_sidebar_entry_active(): void
    {
        Project::create(['name' => 'Laravel', 'slug' => 'laravel', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project', 'laravel'))
            ->assertOk()
            ->assertSee('bg-brand-400');
    }
}
