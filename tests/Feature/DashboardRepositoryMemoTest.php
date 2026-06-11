<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Database\Connection;
use ReflectionProperty;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DashboardRepositoryMemoTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_request_aggregates_are_read_once_across_sections(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $repo = $this->app->make(DashboardRepository::class);

        $conn = (new ReflectionProperty($repo, 'db'))->getValue($repo);
        $this->assertInstanceOf(Connection::class, $conn);
        $conn->flushQueryLog();
        $conn->enableQueryLog();

        // Three sections that each want the same request slice. Pre-fix this was
        // three separate wdn_aggregates reads; memoized it is one.
        $repo->kpis($project->id, '24h');
        $repo->requestSeries($project->id, '24h');
        $repo->topRoutes($project->id, '24h');

        $requestReads = array_filter(
            $conn->getQueryLog(),
            fn (array $q): bool => str_contains((string) $q['query'], 'wdn_aggregates')
                && in_array('request', $q['bindings'], true)
        );

        $this->assertCount(1, $requestReads, 'the request slice is queried once, not once per section');
    }
}
