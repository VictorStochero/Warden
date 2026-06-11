<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;

class AggregatorBatchPersistTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_persist_reads_existing_aggregates_in_one_query(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        // Three distinct routes -> three distinct aggregate keys in one bucket.
        foreach (['/a', '/b', '/c'] as $route) {
            Schema::db()->table('wdn_events')->insert([
                'project_id' => $project->id,
                'type' => 'request',
                'trace_id' => bin2hex(random_bytes(8)),
                'occurred_at' => now()->format('Y-m-d H:i:s.u'),
                'occurred_date' => now()->format('Y-m-d'),
                'duration_us' => 1500,
                'payload' => json_encode(['route' => $route, 'status' => 200]),
            ]);
        }

        Schema::db()->flushQueryLog();
        Schema::db()->enableQueryLog();

        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        $aggSelects = array_filter(
            Schema::db()->getQueryLog(),
            fn (array $q): bool => str_starts_with(ltrim(strtolower((string) $q['query'])), 'select')
                && str_contains((string) $q['query'], 'wdn_aggregates')
        );

        // One batched lookup, not one SELECT per key.
        $this->assertCount(1, $aggSelects);

        // Correctness preserved: a row per key with the right count.
        $this->assertSame(3, Schema::db()->table('wdn_aggregates')->where('type', 'request')->count());
        $this->assertSame(1, (int) Schema::db()->table('wdn_aggregates')->where('key', '/a')->value('count'));
    }
}
