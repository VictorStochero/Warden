<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class RangePersistenceTest extends TestCase
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

    public function test_thirty_day_preset_is_accepted_and_queues_cookie(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'logs', 'range' => '30d']))
            ->assertOk()
            ->assertCookie('warden_range', '30d');
    }

    public function test_range_is_restored_from_cookie_when_absent_from_query(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        // No ?range in the query — the persisted cookie should drive the resolved
        // range. The live-stream URL is built solely from the resolved range (the
        // preset picker links every preset regardless, so they're not a tell), so
        // it uniquely reflects what the controller resolved.
        $this->withCookie('warden_range', '30d')
            ->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'logs']))
            ->assertOk()
            ->assertSee('stream?range=30d', false);
    }

    public function test_cookie_is_not_rewritten_when_range_absent_from_query(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        // A plain request (no ?range) must not queue a warden_range cookie — the
        // preference is only written when the user picks a preset explicitly.
        $response = $this->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'logs']))
            ->assertOk();

        $this->assertNull($response->getCookie('warden_range', false));
    }

    public function test_invalid_cookie_falls_back_to_default(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->withCookie('warden_range', 'bogus')
            ->get(route('warden.project.section', ['project' => $project->slug, 'section' => 'logs']))
            ->assertOk()
            ->assertSee('stream?range=1h', false)
            ->assertDontSee('stream?range=bogus', false);
    }
}
