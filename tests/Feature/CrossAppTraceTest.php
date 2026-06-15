<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Cross-app trace stitching (§29): once a trace is propagated, the same trace_id
 * lands under multiple projects. The dashboard gathers all of them into one
 * waterfall labelled by app.
 */
class CrossAppTraceTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);

        Project::firstOrCreate(['slug' => 'api'], ['name' => 'API', 'token' => 't1', 'secret' => 's1', 'active' => true]);
        Project::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'token' => 't2', 'secret' => 's2', 'active' => true]);

        $at = now()->format('Y-m-d H:i:s.u');
        // The same trace crosses the two apps.
        $this->app->make(Ingestor::class)->ingest('web', [['id' => 'bw', 'events' => [
            ['type' => 'request', 'trace_id' => 'shared-trace-0001', 'span_id' => 'w1', 'occurred_at' => $at, 'duration_us' => 5000, 'payload' => ['method' => 'GET', 'route' => '/checkout', 'path' => '/checkout', 'status' => 200]],
        ]]]);
        $this->app->make(Ingestor::class)->ingest('api', [['id' => 'ba', 'events' => [
            ['type' => 'request', 'trace_id' => 'shared-trace-0001', 'span_id' => 'a1', 'parent_span_id' => 'w1', 'occurred_at' => $at, 'duration_us' => 3000, 'payload' => ['method' => 'GET', 'route' => '/orders', 'path' => '/orders', 'status' => 200]],
        ]]]);
    }

    private function repo(): DashboardRepository
    {
        return $this->app->make(DashboardRepository::class);
    }

    public function test_trace_projects_lists_every_app_in_the_trace(): void
    {
        $slugs = $this->repo()->traceProjects('shared-trace-0001')->pluck('slug')->all();

        $this->assertContains('web', $slugs);
        $this->assertContains('api', $slugs);
    }

    public function test_distributed_trace_merges_spans_tagged_by_app(): void
    {
        $projects = $this->repo()->traceProjects('shared-trace-0001');
        $spans = $this->repo()->distributedTrace('shared-trace-0001', $projects);

        $this->assertCount(2, $spans);
        $this->assertEqualsCanonicalizing(['API', 'Web'], $spans->pluck('project_name')->unique()->all());
    }

    public function test_trace_view_stitches_the_other_app_in(): void
    {
        $web = Project::query()->where('slug', 'web')->firstOrFail();

        $this->get(route('warden.trace', [$web->slug, 'shared-trace-0001']))
            ->assertOk()
            ->assertSee('API'); // the downstream app appears in the waterfall
    }
}
