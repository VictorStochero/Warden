<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The real-time transport (§5.4): one cursor-based endpoint hands back the live
 * KPIs as JSON, and coalesces idle polls into a cheap 304 Not Modified so the
 * heavy aggregate read only runs when something actually changed. No build step,
 * no websocket — plain conditional GET that runs on bare PHP-FPM.
 */
class DashboardStreamTest extends TestCase
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

    private function seedRequest(string $sql = 'select 1', int $duration = 4200): Project
    {
        $project = Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'seed-'.uniqid(), 'events' => [
            ['type' => 'request', 'trace_id' => 't'.uniqid(), 'span_id' => 's1', 'occurred_at' => $at, 'duration_us' => $duration, 'payload' => ['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 200]],
        ]]]);

        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        return $project;
    }

    public function test_stream_returns_live_kpis_with_a_cursor_and_etag(): void
    {
        $this->seedRequest();

        $res = $this->get(route('warden.project.stream', 'demo'));

        $res->assertOk()
            ->assertJsonStructure(['cursor', 'kpis' => ['throughput', 'error_rate', 'errors']]);

        $this->assertNotEmpty($res->headers->get('ETag'), 'The stream must expose an ETag for conditional GET');
    }

    public function test_an_unchanged_state_coalesces_into_a_304(): void
    {
        $this->seedRequest();

        $first = $this->get(route('warden.project.stream', 'demo'));
        $etag = $first->headers->get('ETag');

        $second = $this->withHeaders(['If-None-Match' => $etag])
            ->get(route('warden.project.stream', 'demo'));

        $second->assertStatus(304);
        $this->assertEmpty($second->getContent(), 'A 304 carries no body — nothing to re-render');
    }

    public function test_new_aggregated_data_busts_the_cursor(): void
    {
        $this->seedRequest();

        $first = $this->get(route('warden.project.stream', 'demo'));
        $etag = $first->headers->get('ETag');

        // A fresh request rolls up into the aggregates → the cursor must move.
        $this->seedRequest('select 2', 9000);

        $next = $this->withHeaders(['If-None-Match' => $etag])
            ->get(route('warden.project.stream', 'demo'));

        $next->assertOk();
        $this->assertNotSame($etag, $next->headers->get('ETag'), 'New data must produce a new cursor');
    }

    public function test_a_different_range_does_not_reuse_another_ranges_cursor(): void
    {
        $this->seedRequest();

        $hourly = $this->get(route('warden.project.stream', ['project' => 'demo', 'range' => '1h']));
        $daily = $this->get(route('warden.project.stream', ['project' => 'demo', 'range' => '24h']));

        $this->assertNotSame(
            $hourly->headers->get('ETag'),
            $daily->headers->get('ETag'),
            'Each range is its own conditional-GET scope'
        );
    }

    public function test_project_page_polls_the_cursor_instead_of_blind_reloading(): void
    {
        $this->seedRequest();

        $this->get(route('warden.project', 'demo'))
            ->assertOk()
            ->assertSee('If-None-Match')          // the coalesced poller is wired in
            ->assertDontSee('http-equiv', false); // and the blind meta-refresh is gone
    }

    public function test_pages_without_a_stream_keep_the_meta_refresh_fallback(): void
    {
        $this->seedRequest();

        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('http-equiv="refresh"', false);
    }
}
