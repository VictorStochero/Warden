<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Group;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Models\Tag;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Tests\TestCase;

class ProjectMetadataTest extends TestCase
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

    private function project(string $name, string $slug): Project
    {
        return Project::create([
            'name' => $name, 'slug' => $slug, 'token' => 't-'.$slug, 'secret' => 's', 'active' => true,
        ]);
    }

    public function test_update_sets_metadata_and_creates_group_and_tags(): void
    {
        $project = $this->project('Demo', 'demo');

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo Renamed',
            'client' => 'Acme Inc.',
            'contact' => 'ops@acme.test',
            'group' => 'Internal Tools',
            'tags' => 'prod, billing',
        ])->assertRedirect(route('warden.admin.projects'));

        $fresh = $project->fresh()->load('group', 'tags');

        $this->assertSame('Demo Renamed', $fresh->name);
        $this->assertSame('Acme Inc.', $fresh->client);
        $this->assertSame('ops@acme.test', $fresh->contact);

        $this->assertNotNull($fresh->group);
        $this->assertSame('Internal Tools', $fresh->group->name);
        $this->assertSame('internal-tools', $fresh->group->slug);

        $this->assertEqualsCanonicalizing(['prod', 'billing'], $fresh->tags->pluck('name')->all());
        $this->assertDatabaseHas('wdn_groups', ['slug' => 'internal-tools']);
        $this->assertDatabaseHas('wdn_tags', ['slug' => 'prod']);
        $this->assertDatabaseHas('wdn_tags', ['slug' => 'billing']);
    }

    public function test_update_deduplicates_repeated_tags(): void
    {
        $project = $this->project('Demo', 'demo');

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'tags' => 'prod, Prod ,  prod',
        ])->assertRedirect();

        $this->assertSame(1, Tag::query()->count());
        $this->assertSame(1, $project->fresh()->tags()->count());
        $this->assertSame(1, (int) DB::table('wdn_project_tag')->where('project_id', $project->id)->count());
    }

    public function test_update_can_clear_group_and_tags(): void
    {
        $project = $this->project('Demo', 'demo');
        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo', 'group' => 'Ops', 'tags' => 'prod',
        ])->assertRedirect();

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo', 'group' => '', 'tags' => '',
        ])->assertRedirect();

        $fresh = $project->fresh()->load('group', 'tags');
        $this->assertNull($fresh->group_id);
        $this->assertNull($fresh->group);
        $this->assertCount(0, $fresh->tags);
    }

    public function test_overview_filters_by_tag(): void
    {
        $billing = $this->project('Billing', 'billing-app');
        $this->project('Marketing', 'marketing-app');

        $tag = Tag::create(['name' => 'prod', 'slug' => 'prod']);
        $billing->tags()->sync([$tag->id]);

        // The repository is the read surface that applies the filter (the layout
        // sidebar always lists every project, so the rendered HTML can't be the
        // assertion target).
        $repo = app(DatabaseWardenRepository::class);

        $slugs = $repo->projects(['tag' => 'prod'])->pluck('slug')->all();
        $this->assertSame(['billing-app'], $slugs);

        $this->assertCount(2, $repo->projects()); // unfiltered shows both

        $this->get(route('warden.overview', ['tag' => 'prod']))->assertOk();
    }

    public function test_overview_filters_by_group(): void
    {
        $group = Group::create(['name' => 'Internal', 'slug' => 'internal']);
        $a = $this->project('Alpha', 'alpha');
        $this->project('Beta', 'beta');
        $a->forceFill(['group_id' => $group->id])->save();

        $repo = app(DatabaseWardenRepository::class);

        $slugs = $repo->projects(['group' => 'internal'])->pluck('slug')->all();
        $this->assertSame(['alpha'], $slugs);

        $this->assertCount(2, $repo->projects());

        $this->get(route('warden.overview', ['group' => 'internal']))->assertOk();
    }

    public function test_overview_groups_projects_by_group_with_ungrouped_section(): void
    {
        $group = Group::create(['name' => 'Internal Tools', 'slug' => 'internal-tools']);
        $grouped = $this->project('Grouped App', 'grouped-app');
        $grouped->forceFill(['group_id' => $group->id])->save();
        $this->project('Loose App', 'loose-app');

        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('Internal Tools')   // group header
            ->assertSee('Grouped App')
            ->assertSee('Ungrouped')        // ungrouped section header
            ->assertSee('Loose App');
    }
}
