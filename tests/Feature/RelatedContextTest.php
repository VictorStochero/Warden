<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class RelatedContextTest extends TestCase
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

    public function test_related_context_summarizes_a_trace(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $traceId = 'trace-summary-1';
        $now = Carbon::now();

        // Entry-point request span.
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'request',
            'trace_id' => $traceId,
            'span_id' => 'span-root',
            'occurred_at' => $now->copy()->subSecond(),
            'occurred_date' => $now->toDateString(),
            'duration_us' => 120_000,
            'payload' => json_encode(['method' => 'GET', 'route' => '/checkout', 'status' => 200]),
        ]);

        // Three query spans.
        foreach (range(1, 3) as $i) {
            DB::table('wdn_events')->insert([
                'project_id' => $project->id,
                'type' => 'query',
                'trace_id' => $traceId,
                'span_id' => "span-q{$i}",
                'parent_span_id' => 'span-root',
                'occurred_at' => $now,
                'occurred_date' => $now->toDateString(),
                'duration_us' => 5_000,
                'payload' => json_encode(['sql' => "select * from users where id = {$i}"]),
            ]);
        }

        // One exception span.
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'exception',
            'trace_id' => $traceId,
            'span_id' => 'span-exc',
            'parent_span_id' => 'span-root',
            'occurred_at' => $now,
            'occurred_date' => $now->toDateString(),
            'duration_us' => 0,
            'payload' => json_encode(['class' => 'RuntimeException', 'message' => 'boom']),
        ]);

        $repo = $this->app->make(DashboardRepository::class);
        $related = $repo->relatedContext($project->id, $traceId);

        $this->assertSame($traceId, $related['trace_id']);
        $this->assertNotNull($related['entry']);
        $this->assertSame('request', $related['entry']['type']);
        $this->assertSame(3, $related['counts']['query']);
        $this->assertSame(1, $related['counts']['exception']);
        $this->assertSame('RuntimeException', $related['issues'][0]['class']);
        $this->assertTrue($related['recent_traces']->isEmpty());
    }

    public function test_related_context_falls_back_to_project(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $now = Carbon::now();

        // A recent entry-point trace so the fallback has something to list.
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'request',
            'trace_id' => 'trace-recent-1',
            'span_id' => 'span-root',
            'occurred_at' => $now,
            'occurred_date' => $now->toDateString(),
            'duration_us' => 50_000,
            'payload' => json_encode(['method' => 'GET', 'route' => '/dashboard', 'status' => 200]),
        ]);

        $repo = $this->app->make(DashboardRepository::class);
        $related = $repo->relatedContext($project->id);

        $this->assertNull($related['trace_id']);
        $this->assertNull($related['entry']);
        $this->assertSame([], $related['counts']);
        $this->assertSame([], $related['issues']);
        $this->assertTrue($related['recent_traces']->isNotEmpty());
        $this->assertNotNull($related['open_issues']);
        $this->assertNotNull($related['incidents']);
    }

    /**
     * Two exception spans with the same fingerprint (same class + message + no
     * stack) must be deduplicated in `issues` (one entry) but both counted in
     * `counts['exception']` (two occurrences). A job entry-point span must NOT
     * appear in `counts` — consistent with request/command/schedule behaviour.
     */
    public function test_related_context_deduplicates_exception_fingerprints(): void
    {
        $project = Project::create(['name' => 'Worker', 'slug' => 'worker', 'token' => 'tw', 'secret' => 'sw', 'active' => true]);

        $traceId = 'trace-dedup-exc-1';
        $now = Carbon::now();

        // Entry-point job span — must NOT appear in counts.
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'job',
            'trace_id' => $traceId,
            'span_id' => 'span-job-root',
            'occurred_at' => $now->copy()->subSecond(),
            'occurred_date' => $now->toDateString(),
            'duration_us' => 80_000,
            'payload' => json_encode(['class' => 'App\\Jobs\\ProcessOrder']),
        ]);

        // Two exception spans with identical class + message (no stack) → same fingerprint.
        foreach (range(1, 2) as $i) {
            DB::table('wdn_events')->insert([
                'project_id' => $project->id,
                'type' => 'exception',
                'trace_id' => $traceId,
                'span_id' => "span-exc-{$i}",
                'parent_span_id' => 'span-job-root',
                'occurred_at' => $now,
                'occurred_date' => $now->toDateString(),
                'duration_us' => 0,
                'payload' => json_encode(['class' => 'RuntimeException', 'message' => 'boom']),
            ]);
        }

        $repo = $this->app->make(DashboardRepository::class);
        $related = $repo->relatedContext($project->id, $traceId);

        // Entry is the job span.
        $this->assertNotNull($related['entry']);
        $this->assertSame('job', $related['entry']['type']);

        // The job entry-point must NOT be counted.
        $this->assertArrayNotHasKey('job', $related['counts']);

        // Both exception occurrences are counted.
        $this->assertSame(2, $related['counts']['exception']);

        // But deduplicated to a single issue entry.
        $this->assertCount(1, $related['issues']);
        $this->assertSame('RuntimeException', $related['issues'][0]['class']);
    }
}
