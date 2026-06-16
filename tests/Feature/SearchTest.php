<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class SearchTest extends TestCase
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

    private function insertIssue(int $projectId): int
    {
        return DB::table('wdn_issues')->insertGetId([
            'project_id' => $projectId,
            'fingerprint' => sha1('App\\X'),
            'class' => 'App\\X',
            'message' => 'Something went wrong',
            'status' => 'open',
            'count' => 1,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAggregate(int $projectId, string $route): void
    {
        DB::table('wdn_aggregates')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'bucket' => now()->startOfMinute(),
            'key' => $route,
            'count' => 5,
            'sum_duration' => 5000,
            'max_duration' => 1000,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTrace(int $projectId, string $traceId): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'request',
            'trace_id' => $traceId,
            'occurred_at' => now(),
            'occurred_date' => now()->toDateString(),
            'duration_us' => 1234,
            'payload' => json_encode(['method' => 'GET', 'route' => 'checkout', 'status' => 200]),
        ]);
    }

    public function test_search_finds_route_by_term_when_project_provided(): void
    {
        $project = $this->project();
        $this->insertAggregate($project->id, '/checkout');

        $response = $this->getJson(route('warden.search', ['q' => 'checkout', 'project' => $project->slug]));

        $response->assertOk();
        $data = $response->json();
        $routes = collect($data['groups'])->firstWhere('type', 'routes');
        $this->assertNotNull($routes);
        $this->assertNotEmpty($routes['items']);
        $this->assertStringContainsString('checkout', $routes['items'][0]['label']);
    }

    public function test_search_finds_project_by_name(): void
    {
        $this->project();

        $response = $this->getJson(route('warden.search', ['q' => 'API']));

        $response->assertOk();
        $data = $response->json();
        $projects = collect($data['groups'])->firstWhere('type', 'projects');
        $this->assertNotNull($projects);
        $this->assertNotEmpty($projects['items']);
        $this->assertSame('API', $projects['items'][0]['label']);
    }

    public function test_search_finds_issue_by_class(): void
    {
        $project = $this->project();
        $this->insertIssue($project->id);

        $response = $this->getJson(route('warden.search', ['q' => 'App\\X', 'project' => $project->slug]));

        $response->assertOk();
        $data = $response->json();
        $issues = collect($data['groups'])->firstWhere('type', 'issues');
        $this->assertNotNull($issues);
        $this->assertNotEmpty($issues['items']);
        $this->assertStringContainsString('App\\X', $issues['items'][0]['label']);
    }

    public function test_search_returns_empty_groups_for_single_char_term(): void
    {
        $project = $this->project();

        $response = $this->getJson(route('warden.search', ['q' => 'a', 'project' => $project->slug]));

        $response->assertOk();
        $data = $response->json();
        foreach ($data['groups'] as $group) {
            $this->assertEmpty($group['items'], "Group '{$group['type']}' should be empty for 1-char term");
        }
    }

    public function test_search_without_project_returns_empty_routes_issues_traces(): void
    {
        $project = $this->project();
        $this->insertAggregate($project->id, '/checkout');
        $this->insertIssue($project->id);

        $response = $this->getJson(route('warden.search', ['q' => 'checkout']));

        $response->assertOk();
        $data = $response->json();
        foreach ($data['groups'] as $group) {
            if ($group['type'] !== 'projects') {
                $this->assertEmpty($group['items'], "Group '{$group['type']}' should be empty without project context");
            }
        }
    }

    public function test_search_finds_trace_by_id_prefix(): void
    {
        $project = $this->project();
        $traceId = 'abcdef1234567890abcdef1234567890';
        $this->insertTrace($project->id, $traceId);

        $response = $this->getJson(route('warden.search', ['q' => 'abcdef', 'project' => $project->slug]));

        $response->assertOk();
        $data = $response->json();
        $traces = collect($data['groups'])->firstWhere('type', 'traces');
        $this->assertNotNull($traces);
        $this->assertNotEmpty($traces['items']);
        $this->assertStringContainsString('abcdef', $traces['items'][0]['sublabel']);
    }

    public function test_search_requires_view_warden_gate(): void
    {
        Gate::define('viewWarden', fn ($u = null) => false);

        $response = $this->getJson(route('warden.search', ['q' => 'test']));

        $response->assertForbidden();
    }
}
