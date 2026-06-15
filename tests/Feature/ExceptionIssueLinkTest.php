<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ExceptionIssueLinkTest extends TestCase
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

    public function test_exception_span_links_to_grouped_issue(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $traceId = 'trace-with-exception-1';
        $class = 'RuntimeException';
        $message = 'User 42 not found';
        $now = Carbon::now();

        // Entry-point request so the trace has a root span.
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'request',
            'trace_id' => $traceId,
            'span_id' => 'span-root',
            'occurred_at' => $now->copy()->subSecond(),
            'occurred_date' => $now->toDateString(),
            'duration_us' => 120_000,
            'payload' => json_encode(['method' => 'GET', 'route' => '/checkout', 'status' => 500]),
        ]);

        // Exception span (no stack → fingerprint top frame is empty).
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'exception',
            'trace_id' => $traceId,
            'span_id' => 'span-exc',
            'parent_span_id' => 'span-root',
            'occurred_at' => $now,
            'occurred_date' => $now->toDateString(),
            'duration_us' => 0,
            'payload' => json_encode(['class' => $class, 'message' => $message]),
        ]);

        // The grouped issue, fingerprinted the same way IssueProcessor does.
        $fingerprint = Fingerprint::for($class, $message, null);
        $issueId = DB::table('wdn_issues')->insertGetId([
            'project_id' => $project->id,
            'fingerprint' => $fingerprint,
            'class' => $class,
            'message' => $message,
            'last_trace_id' => $traceId,
            'count' => 1,
            'users_affected' => 0,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->get(route('warden.trace', ['project' => $project->slug, 'traceId' => $traceId]))
            ->assertOk()
            ->assertSee(route('warden.issue', ['project' => $project->slug, 'issue' => $issueId]), false);
    }

    public function test_exception_span_without_issue_renders_no_link(): void
    {
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'token' => 't', 'secret' => 's', 'active' => true]);

        $traceId = 'trace-orphan-exception';
        $now = Carbon::now();

        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'exception',
            'trace_id' => $traceId,
            'span_id' => 'span-exc',
            'occurred_at' => $now,
            'occurred_date' => $now->toDateString(),
            'duration_us' => 0,
            'payload' => json_encode(['class' => 'LogicException', 'message' => 'orphan']),
        ]);

        $this->get(route('warden.trace', ['project' => $project->slug, 'traceId' => $traceId]))
            ->assertOk()
            ->assertDontSee('View grouped issue', false);
    }
}
