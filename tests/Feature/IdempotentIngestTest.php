<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class IdempotentIngestTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    private function batch(string $id): array
    {
        return ['id' => $id, 'events' => [
            ['type' => 'request', 'trace_id' => 't1', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'duration_us' => 1000, 'payload' => ['route' => '/x', 'status' => 200]],
        ]];
    }

    public function test_replaying_the_same_batch_does_not_duplicate_events(): void
    {
        $project = $this->project();
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('demo', [$this->batch('batch-1')]);
        $ingestor->ingest('demo', [$this->batch('batch-1')]);

        $this->assertSame(1, Event::where('project_id', $project->id)->count());
    }

    public function test_a_new_batch_after_a_duplicate_still_ingests(): void
    {
        $project = $this->project();
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('demo', [$this->batch('batch-1')]);
        $ingestor->ingest('demo', [$this->batch('batch-1'), $this->batch('batch-2')]);

        $this->assertSame(2, Event::where('project_id', $project->id)->count());
    }

    public function test_same_batch_id_isolated_per_project(): void
    {
        $a = Project::create(['name' => 'A', 'slug' => 'a', 'token' => 'ta', 'secret' => 'sa', 'active' => true]);
        $b = Project::create(['name' => 'B', 'slug' => 'b', 'token' => 'tb', 'secret' => 'sb', 'active' => true]);
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('a', [$this->batch('shared-id')]);
        $ingestor->ingest('b', [$this->batch('shared-id')]);

        $this->assertSame(1, Event::where('project_id', $a->id)->count());
        $this->assertSame(1, Event::where('project_id', $b->id)->count());
    }
}
