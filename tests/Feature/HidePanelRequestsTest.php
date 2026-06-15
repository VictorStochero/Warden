<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class HidePanelRequestsTest extends TestCase
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

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    private function requestEvent(int $projectId, string $route): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'trace_id' => str_repeat('a', 32),
            'occurred_at' => now(),
            'occurred_date' => now()->toDateString(),
            'duration_us' => 1234,
            'payload' => json_encode(['method' => 'GET', 'route' => $route, 'status' => 200]),
        ]);
    }

    private function aggregateRoute(int $projectId, string $route, int $count): void
    {
        DB::table('wdn_aggregates')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'bucket' => now()->startOfMinute(),
            'key' => $route,
            'count' => $count,
            'sum_duration' => $count * 1000,
            'max_duration' => 1000,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repo(): DashboardRepository
    {
        return $this->app->make(DashboardRepository::class);
    }

    public function test_recent_requests_hide_warden_routes_by_default(): void
    {
        $p = $this->project();
        $this->requestEvent($p->id, 'warden.project.stream');
        $this->requestEvent($p->id, 'checkout');

        $routes = $this->repo()->recentRequests($p->id, 60, null, false)
            ->map(fn (\stdClass $e) => $e->payload['route'])->all();

        $this->assertContains('checkout', $routes);
        $this->assertNotContains('warden.project.stream', $routes);
    }

    public function test_recent_requests_include_warden_routes_when_requested(): void
    {
        $p = $this->project();
        $this->requestEvent($p->id, 'warden.project.stream');
        $this->requestEvent($p->id, 'checkout');

        $routes = $this->repo()->recentRequests($p->id, 60, null, true)
            ->map(fn (\stdClass $e) => $e->payload['route'])->all();

        $this->assertContains('checkout', $routes);
        $this->assertContains('warden.project.stream', $routes);
    }

    public function test_top_routes_drop_warden_keys_when_excluded(): void
    {
        $p = $this->project();
        $this->aggregateRoute($p->id, 'warden.overview', 5);
        $this->aggregateRoute($p->id, 'checkout', 3);

        $hidden = $this->repo()->topRoutes($p->id, '1h', 50, false)->pluck('key')->all();
        $this->assertSame(['checkout'], $hidden);

        $shown = $this->repo()->topRoutes($p->id, '1h', 50, true)->pluck('key')->all();
        $this->assertContains('warden.overview', $shown);
        $this->assertContains('checkout', $shown);
    }

    public function test_requests_section_hides_panel_routes_by_default_and_toggles(): void
    {
        $p = $this->project();
        $this->requestEvent($p->id, 'warden.test.panel');
        $this->requestEvent($p->id, 'app.checkout');

        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'requests']))
            ->assertOk()
            ->assertSee('app.checkout')
            ->assertDontSee('warden.test.panel');

        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'requests', 'warden' => 1]))
            ->assertOk()
            ->assertSee('app.checkout')
            ->assertSee('warden.test.panel');
    }
}
