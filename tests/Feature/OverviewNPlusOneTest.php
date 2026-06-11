<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Database\Connection;
use ReflectionProperty;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Tests\TestCase;

class OverviewNPlusOneTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_overview_reads_aggregates_and_incidents_once_regardless_of_project_count(): void
    {
        foreach (['alpha', 'bravo', 'charlie', 'delta'] as $slug) {
            Project::create(['name' => $slug, 'slug' => $slug, 'token' => $slug, 'secret' => 's', 'active' => true]);
        }

        $repo = $this->app->make(DatabaseWardenRepository::class);

        $conn = (new ReflectionProperty($repo, 'db'))->getValue($repo);
        $this->assertInstanceOf(Connection::class, $conn);
        $conn->flushQueryLog();
        $conn->enableQueryLog();

        $projects = $repo->projects();
        $this->assertCount(4, $projects);

        $log = $conn->getQueryLog();
        $aggReads = array_filter($log, fn (array $q): bool => str_contains((string) $q['query'], 'wdn_aggregates'));
        $incidentReads = array_filter($log, fn (array $q): bool => str_contains((string) $q['query'], 'wdn_incidents'));

        // One batched read each, not one per project (was 2N+).
        $this->assertCount(1, $aggReads, 'request aggregates read in a single batched query');
        $this->assertCount(1, $incidentReads, 'uptime incidents read in a single batched query');
    }
}
