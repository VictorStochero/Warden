<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class TraceFilterTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedEvent(int $projectId, string $traceId, string $type, array $payload, int $id): void
    {
        $now = Carbon::now()->toDateTimeString();

        DB::table('wdn_events')->insert([
            'id' => $id,
            'project_id' => $projectId,
            'trace_id' => $traceId,
            'span_id' => 's'.$id,
            'parent_span_id' => null,
            'type' => $type,
            'occurred_at' => $now,
            'occurred_date' => Carbon::now()->toDateString(),
            'duration_us' => 1000,
            'payload' => json_encode($payload),
            'release' => null,
            'received_at' => $now,
        ]);
    }

    public function test_filters_traces_by_route(): void
    {
        $project = $this->project();

        $this->seedEvent($project->id, 'trace-checkout', 'request', ['method' => 'GET', 'route' => '/checkout', 'status' => 200], 1);
        $this->seedEvent($project->id, 'trace-home', 'request', ['method' => 'GET', 'route' => '/home', 'status' => 200], 2);

        $this->get(route('warden.traces', ['project' => $project->slug, 'route' => '/checkout']))
            ->assertOk()
            ->assertSee('/checkout')
            // Scope the exclusion to the filtered list itself: the right-hand
            // "Related" panel renders project-wide recent traces and may list
            // /home there — the filter only governs the main table.
            ->assertViewHas('traces', fn ($traces) => $traces->pluck('trace_id')->doesntContain('trace-home'));
    }

    public function test_filters_traces_by_query_fingerprint(): void
    {
        $project = $this->project();

        $sql = 'select * from orders where id = 1';
        $this->seedEvent($project->id, 'trace-orders', 'request', ['method' => 'GET', 'route' => '/orders', 'status' => 200], 10);
        $this->seedEvent($project->id, 'trace-orders', 'query', ['sql' => $sql], 11);

        // A second, unrelated trace that must NOT appear.
        $this->seedEvent($project->id, 'trace-other', 'request', ['method' => 'GET', 'route' => '/other', 'status' => 200], 20);
        $this->seedEvent($project->id, 'trace-other', 'query', ['sql' => 'select * from products where id = 9'], 21);

        $fp = substr(sha1(Fingerprint::normalize($sql)), 0, 16);

        $this->get(route('warden.traces', ['project' => $project->slug, 'query' => $fp]))
            ->assertOk()
            ->assertSee('/orders')
            // Exclusion scoped to the filtered table (see note above); the
            // Related panel lists project-wide recent traces.
            ->assertViewHas('traces', fn ($traces) => $traces->pluck('trace_id')->doesntContain('trace-other'));
    }

    public function test_filters_traces_by_job_class(): void
    {
        $project = $this->project();

        // Trace alvo: entry-point job + evento job com payload.class = SendMail.
        $this->seedEvent($project->id, 'trace-mail', 'job', ['class' => 'App\\Jobs\\SendMail', 'status' => 0], 30);

        // Segundo trace com classe diferente — não deve aparecer no resultado.
        $this->seedEvent($project->id, 'trace-notify', 'job', ['class' => 'App\\Jobs\\SendNotification', 'status' => 0], 40);

        $this->get(route('warden.traces', ['project' => $project->slug, 'job' => 'App\\Jobs\\SendMail']))
            ->assertOk()
            ->assertSee('App\\Jobs\\SendMail')
            // Exclusion scoped to the filtered table (see note above); the
            // Related panel lists project-wide recent traces.
            ->assertViewHas('traces', fn ($traces) => $traces->pluck('trace_id')->doesntContain('trace-notify'));
    }
}
