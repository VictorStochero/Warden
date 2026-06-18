<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Defense-in-depth (§ per-project metrics): the parent drops events whose type
 * is gated off for the project BEFORE they hit wdn_events, so a disabled metric
 * can never bloat the parent DB — even if a stale child keeps shipping it.
 */
class ParentTypeGateTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    /** @param array<string,mixed>|null $config */
    private function project(?array $config = null): Project
    {
        return Project::create([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's',
            'active' => true, 'config' => $config,
        ]);
    }

    /** @param list<string> $types */
    private function batch(array $types): array
    {
        $events = [];
        foreach ($types as $i => $type) {
            $events[] = [
                'type' => $type, 'trace_id' => 'tr'.$i,
                'occurred_at' => now()->format('Y-m-d H:i:s.u'),
                'payload' => ['k' => $type],
            ];
        }

        return ['id' => 'b-'.implode('-', $types), 'events' => $events];
    }

    public function test_a_type_gated_off_is_dropped_before_insert(): void
    {
        $project = $this->project(['sample' => ['type_gate' => ['query' => false]]]);
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('demo', [$this->batch(['request', 'query', 'job'])]);

        $this->assertSame(0, Event::where('project_id', $project->id)->where('type', 'query')->count());
        $this->assertSame(1, Event::where('project_id', $project->id)->where('type', 'request')->count());
        $this->assertSame(1, Event::where('project_id', $project->id)->where('type', 'job')->count());
    }

    public function test_types_not_gated_off_are_kept(): void
    {
        // request explicitly on, query absent — both must be stored.
        $project = $this->project(['sample' => ['type_gate' => ['request' => true]]]);
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('demo', [$this->batch(['request', 'query'])]);

        $this->assertSame(2, Event::where('project_id', $project->id)->count());
    }

    public function test_no_config_keeps_everything(): void
    {
        $project = $this->project(null);
        $ingestor = $this->app->make(Ingestor::class);

        $ingestor->ingest('demo', [$this->batch(['request', 'query', 'job'])]);

        $this->assertSame(3, Event::where('project_id', $project->id)->count());
    }

    public function test_accepted_count_reflects_received_not_persisted(): void
    {
        // The drop is parent policy, not rejection: the child must see its whole
        // batch accepted so it does not warn or retry.
        $project = $this->project(['sample' => ['type_gate' => ['query' => false]]]);
        $ingestor = $this->app->make(Ingestor::class);

        $accepted = $ingestor->ingest('demo', [$this->batch(['request', 'query', 'job'])]);

        $this->assertSame(3, $accepted);
        $this->assertSame(2, Event::where('project_id', $project->id)->count());
    }
}
