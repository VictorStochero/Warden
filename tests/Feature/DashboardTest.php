<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Issues\IssueProcessor;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DashboardTest extends TestCase
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

    protected function seedData(): Project
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'seed-1', 'events' => [
            ['type' => 'request', 'trace_id' => 'trace1', 'span_id' => 's1', 'occurred_at' => $at, 'duration_us' => 4200, 'payload' => ['method' => 'GET', 'route' => '/checkout', 'path' => '/checkout', 'status' => 200]],
            ['type' => 'query', 'trace_id' => 'trace1', 'span_id' => 's2', 'parent_span_id' => 's1', 'occurred_at' => $at, 'duration_us' => 800, 'payload' => ['sql' => 'select * from orders where id = 1']],
            ['type' => 'query', 'trace_id' => 'trace1', 'span_id' => 's3', 'parent_span_id' => 's1', 'occurred_at' => $at, 'duration_us' => 820, 'payload' => ['sql' => 'select * from orders where id = 2']],
            ['type' => 'query', 'trace_id' => 'trace1', 'span_id' => 's4', 'parent_span_id' => 's1', 'occurred_at' => $at, 'duration_us' => 790, 'payload' => ['sql' => 'select * from orders where id = 3']],
            ['type' => 'cache', 'trace_id' => 'trace1', 'occurred_at' => $at, 'payload' => ['action' => 'hit', 'store' => 'redis', 'key' => 'k', 'hit' => true]],
            ['type' => 'job', 'trace_id' => 'trace2', 'occurred_at' => $at, 'duration_us' => 12000, 'payload' => ['status' => 'processed', 'class' => 'App\\Jobs\\Ship', 'queue' => 'default']],
            ['type' => 'exception', 'trace_id' => 'trace1', 'occurred_at' => $at, 'payload' => ['class' => 'RuntimeException', 'message' => 'boom 7', 'user_id' => 1, 'stack' => [['file' => '/app/X.php', 'line' => 9, 'function' => 'go']]]],
            ['type' => 'host', 'trace_id' => 'trace3', 'occurred_at' => $at, 'payload' => ['hostname' => 'web1', 'cpu' => 12.5, 'memory' => ['used_percent' => 40], 'load' => [1 => 0.4], 'disk' => ['used_percent' => 55]]],
        ]]]);

        $agg = $this->app->make(Aggregator::class);
        foreach (['request', 'query', 'cache', 'job', 'exception', 'host'] as $type) {
            $agg->rollup($project->id, $type);
        }
        $this->app->make(IssueProcessor::class)->process($project->id);
        $this->app->make(Evaluator::class)->evaluate($project->id);

        return $project;
    }

    public function test_overview_renders(): void
    {
        $this->seedData();

        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Demo');
    }

    public function test_project_sections_render(): void
    {
        $this->seedData();

        $this->get(route('warden.project', 'demo'))->assertOk()->assertSee('Throughput');

        foreach (['requests', 'queries', 'jobs', 'cache', 'schedule', 'http', 'logs', 'mail', 'host'] as $section) {
            $this->get(route('warden.project.section', ['project' => 'demo', 'section' => $section]))
                ->assertOk();
        }
    }

    public function test_issues_pages_render(): void
    {
        $project = $this->seedData();
        $issueId = Issue::where('project_id', $project->id)->value('id');

        $this->get(route('warden.issues', 'demo'))->assertOk()->assertSee('RuntimeException');
        $this->get(route('warden.issue', ['demo', $issueId]))->assertOk()->assertSee('boom 7');
    }

    public function test_traces_pages_render_with_n_plus_one(): void
    {
        $this->seedData();

        $this->get(route('warden.traces', 'demo'))->assertOk()->assertSee('/checkout');

        // The trace repeats the same normalized query 3× -> N+1 flagged.
        $this->get(route('warden.trace', ['demo', 'trace1']))
            ->assertOk()
            ->assertSee('N+1');
    }

    public function test_dashboard_is_gated(): void
    {
        Gate::define('viewWarden', fn ($u = null) => false);
        $this->seedData();

        $this->get(route('warden.overview'))->assertForbidden();
    }
}
