<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Per-project RBAC primitive (§5.7): the dashboard gates receive the current
 * route's project slug, so a host can authorize access per project. The package
 * stays thin — full user/team RBAC lives in the host gate (or the standalone app).
 */
class ProjectScopedGateTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // 'gate' mode so a denied request is a flat 403, not a login redirect.
        $this->app['config']->set('warden.dashboard.auth', 'gate');

        Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
        Project::firstOrCreate(['slug' => 'secret'], ['name' => 'Secret', 'token' => 't2', 'secret' => 's2', 'active' => true]);
    }

    public function test_the_gate_can_authorize_per_project(): void
    {
        Gate::define('viewWarden', fn ($user = null, ?string $project = null): bool => $project !== 'secret');

        $this->get(route('warden.project', 'demo'))->assertOk();
        $this->get(route('warden.project', 'secret'))->assertForbidden();
    }

    public function test_non_project_routes_pass_a_null_project(): void
    {
        $seen = 'unset';
        Gate::define('viewWarden', function ($user = null, ?string $project = null) use (&$seen): bool {
            $seen = $project;

            return true;
        });

        $this->get(route('warden.overview'))->assertOk();

        $this->assertNull($seen, 'The overview is not project-scoped');
    }

    public function test_a_project_route_passes_its_slug(): void
    {
        $seen = 'unset';
        Gate::define('viewWarden', function ($user = null, ?string $project = null) use (&$seen): bool {
            $seen = $project;

            return true;
        });

        $this->get(route('warden.project', 'demo'))->assertOk();

        $this->assertSame('demo', $seen);
    }
}
