<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Custom time window (§5b): a `from`/`to` pair (datetime-local) overrides the
 * preset and bounds the reads on BOTH ends, so only data inside the window
 * appears — proven on rows()/recentEvents()/recentLogs() and end-to-end through
 * the controller.
 */
class CustomRangeTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);

        Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );
    }

    private function projectId(): int
    {
        return (int) Project::query()->where('slug', 'demo')->value('id');
    }

    private function seedLog(int $projectId, string $message, \DateTimeInterface $at): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'log',
            'trace_id' => 'trace-'.bin2hex(random_bytes(4)),
            'occurred_at' => $at->format('Y-m-d H:i:s.u'),
            'occurred_date' => $at->format('Y-m-d'),
            'duration_us' => 0,
            'payload' => json_encode(['level' => 'info', 'message' => $message]),
        ]);
    }

    private function seedEvent(int $projectId, string $type, string $route, \DateTimeInterface $at): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => $type,
            'trace_id' => 'trace-'.bin2hex(random_bytes(4)),
            'occurred_at' => $at->format('Y-m-d H:i:s.u'),
            'occurred_date' => $at->format('Y-m-d'),
            'duration_us' => 1000,
            'payload' => json_encode(['method' => 'GET', 'route' => $route, 'status' => 200]),
        ]);
    }

    private function seedAggregate(int $projectId, string $type, string $key, \DateTimeInterface $bucket, int $count): void
    {
        DB::table('wdn_aggregates')->insert([
            'project_id' => $projectId,
            'type' => $type,
            'bucket' => $bucket->format('Y-m-d H:i:s'),
            'key' => $key,
            'count' => $count,
            'sum_duration' => $count * 1000,
            'max_duration' => 1000,
            'meta' => json_encode(['h_50' => $count]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_window_bounds_recent_logs_on_both_ends(): void
    {
        $repo = $this->app->make(DashboardRepository::class);
        $id = $this->projectId();

        $this->seedLog($id, 'before window', now()->subDays(20));
        $this->seedLog($id, 'inside window', now()->subDays(10));
        $this->seedLog($id, 'after window', now()->subDay());

        $start = Carbon::now()->subDays(12);
        $end = Carbon::now()->subDays(5);

        $hits = $repo->withWindow($start, $end)->recentLogs($id, null, 100, '30d', null);

        $messages = $hits->map(fn ($e) => $e->payload['message'])->all();
        $this->assertContains('inside window', $messages);
        $this->assertNotContains('before window', $messages);
        $this->assertNotContains('after window', $messages);
    }

    public function test_window_bounds_recent_events_on_both_ends(): void
    {
        $repo = $this->app->make(DashboardRepository::class);
        $id = $this->projectId();

        $this->seedEvent($id, 'request', '/before', now()->subDays(20));
        $this->seedEvent($id, 'request', '/inside', now()->subDays(10));
        $this->seedEvent($id, 'request', '/after', now()->subDay());

        $start = Carbon::now()->subDays(12);
        $end = Carbon::now()->subDays(5);

        $routes = $repo->withWindow($start, $end)
            ->recentEvents($id, 'request', 60, '30d')
            ->map(fn ($e) => $e->payload['route'])
            ->all();

        $this->assertContains('/inside', $routes);
        $this->assertNotContains('/before', $routes);
        $this->assertNotContains('/after', $routes);
    }

    public function test_window_bounds_aggregate_rows_on_both_ends(): void
    {
        $repo = $this->app->make(DashboardRepository::class);
        $id = $this->projectId();

        $this->seedAggregate($id, 'request', 'GET /before', now()->subDays(20), 5);
        $this->seedAggregate($id, 'request', 'GET /inside', now()->subDays(10), 7);
        $this->seedAggregate($id, 'request', 'GET /after', now()->subDay(), 9);

        $start = Carbon::now()->subDays(12);
        $end = Carbon::now()->subDays(5);

        // throughput sums the aggregate counts in the window — only the inside row.
        $kpis = $repo->withWindow($start, $end)->kpis($id, '30d');

        $this->assertSame(7, $kpis['throughput']);
    }

    public function test_logs_section_via_http_respects_the_custom_window(): void
    {
        $id = $this->projectId();

        $this->seedLog($id, 'OLD log outside window', now()->subDays(20));
        $this->seedLog($id, 'log inside the window', now()->subDays(10));

        $from = Carbon::now()->subDays(12)->format('Y-m-d\TH:i');
        $to = Carbon::now()->subDays(5)->format('Y-m-d\TH:i');

        $url = route('warden.project.section', ['project' => 'demo', 'section' => 'logs'])
            ."?from={$from}&to={$to}";

        $this->get($url)
            ->assertOk()
            ->assertSee('log inside the window')
            ->assertDontSee('OLD log outside window');
    }

    public function test_invalid_from_falls_back_to_preset(): void
    {
        $id = $this->projectId();
        $this->seedLog($id, 'recent log here', now()->subMinutes(5));

        // Garbage `from` → custom=false → falls back to the preset (1h default).
        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'logs']).'?from=not-a-date')
            ->assertOk()
            ->assertSee('recent log here');
    }

    /**
     * (a) Inverted pair (from > to): window() swaps the bounds so start <= end.
     * Data inside the logical window must appear; data outside must not.
     */
    public function test_inverted_from_to_is_normalised_and_data_appears(): void
    {
        $id = $this->projectId();
        $this->seedLog($id, 'inside inverted window', now()->subDays(10));
        $this->seedLog($id, 'outside inverted window', now()->subDays(30));

        // Deliberately pass from=later & to=earlier — window() must swap them.
        $from = Carbon::now()->subDays(5)->format('Y-m-d\TH:i');   // later
        $to = Carbon::now()->subDays(12)->format('Y-m-d\TH:i');  // earlier

        $url = route('warden.project.section', ['project' => 'demo', 'section' => 'logs'])
            ."?from={$from}&to={$to}";

        $this->get($url)
            ->assertOk()
            ->assertSee('inside inverted window')
            ->assertDontSee('outside inverted window');
    }

    /**
     * (b) Only `from` given (no `to`): window runs open-ended to now.
     * An old log (before from) must NOT appear; a recent one (after from) must.
     */
    public function test_from_only_opens_window_to_now(): void
    {
        $id = $this->projectId();
        $this->seedLog($id, 'log after from', now()->subDays(2));
        $this->seedLog($id, 'log before from', now()->subDays(20));

        $from = Carbon::now()->subDays(5)->format('Y-m-d\TH:i');

        $url = route('warden.project.section', ['project' => 'demo', 'section' => 'logs'])
            ."?from={$from}";

        $this->get($url)
            ->assertOk()
            ->assertSee('log after from')
            ->assertDontSee('log before from');
    }

    /**
     * (c) Only `to` given (no `from`): a bare `to` without `from` is ambiguous;
     * window() returns custom=false so the controller falls back to the preset.
     * A very recent log (within the 1h preset) must still appear.
     */
    public function test_to_only_falls_back_to_preset(): void
    {
        $id = $this->projectId();
        $this->seedLog($id, 'recent log for to-only', now()->subMinutes(5));

        // `to` without `from` → custom=false → preset (1h default).
        $to = Carbon::now()->subDays(5)->format('Y-m-d\TH:i');

        $url = route('warden.project.section', ['project' => 'demo', 'section' => 'logs'])
            ."?to={$to}";

        $this->get($url)
            ->assertOk()
            ->assertSee('recent log for to-only');
    }
}
