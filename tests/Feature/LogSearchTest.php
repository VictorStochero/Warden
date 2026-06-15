<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Full-text-ish log search (§5.9): the logs section can be filtered by a free
 * substring across the message and by level, going to the database so it finds
 * old logs — not just whatever sits in the recent in-memory batch.
 */
class LogSearchTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        $this->seedLogs();
    }

    private function seedLogs(): void
    {
        Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b1', 'events' => [
            ['type' => 'log', 'trace_id' => 't1', 'occurred_at' => $at, 'payload' => ['level' => 'error', 'message' => 'Payment gateway timeout']],
            ['type' => 'log', 'trace_id' => 't2', 'occurred_at' => $at, 'payload' => ['level' => 'info', 'message' => 'Cache warmed successfully']],
        ]]]);
    }

    private function seedLog(int $projectId, string $level, string $message, \DateTimeInterface $at): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'log',
            'trace_id' => 'trace-'.bin2hex(random_bytes(4)),
            'occurred_at' => $at->format('Y-m-d H:i:s.u'),
            'occurred_date' => $at->format('Y-m-d'),
            'duration_us' => 0,
            'payload' => json_encode(['level' => $level, 'message' => $message]),
        ]);
    }

    private function projectId(): int
    {
        return (int) Project::query()->where('slug', 'demo')->value('id');
    }

    public function test_logs_can_be_searched_by_message_substring(): void
    {
        $repo = $this->app->make(DashboardRepository::class);

        $hits = $repo->recentLogs($this->projectId(), null, 100, null, 'payment');

        $this->assertCount(1, $hits);
        $this->assertStringContainsStringIgnoringCase('Payment', $hits->first()->payload['message']);
    }

    public function test_logs_section_renders_the_matching_log_only(): void
    {
        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'logs']).'?q=payment')
            ->assertOk()
            ->assertSee('Payment gateway timeout')
            ->assertDontSee('Cache warmed successfully');
    }

    public function test_search_finds_an_old_log_in_the_database_with_level_filter(): void
    {
        $projectId = $this->projectId();

        // An OLD error log matching both filters — far outside the default window
        // and outside any "recent 500" batch. Going to the DB must still find it.
        $this->seedLog($projectId, 'error', 'payment gateway exploded long ago', now()->subDays(20));

        // Noise: matching text but wrong level; and an old non-matching error.
        $this->seedLog($projectId, 'info', 'payment received fine', now()->subDays(20));
        $this->seedLog($projectId, 'error', 'unrelated failure', now()->subDays(20));

        $url = route('warden.project.section', ['project' => 'demo', 'section' => 'logs'])
            .'?level=error&q=payment&range=30d';

        $this->get($url)
            ->assertOk()
            ->assertSee('payment gateway exploded long ago')
            ->assertDontSee('payment received fine')
            ->assertDontSee('unrelated failure');
    }

    public function test_search_finds_an_old_matching_log_under_high_volume(): void
    {
        $repo = $this->app->make(DashboardRepository::class);
        $projectId = $this->projectId();

        // One OLD error whose message matches the needle. Seeded FIRST so it gets
        // the lowest id — the old `orderByDesc('id')->limit(500)` pass trims it out
        // before the in-PHP substring filter ever sees it.
        $this->seedLog($projectId, 'error', 'needle in the haystack failure', now()->subDays(20));

        // 600 RECENT non-matching error logs — more than the old in-PHP cap of 500,
        // all with higher ids, so they crowd the old match out of the capped window.
        // Batch-inserted for speed.
        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $at = now()->subMinutes($i + 1);
            $rows[] = [
                'project_id' => $projectId,
                'type' => 'log',
                'trace_id' => 'noise-'.bin2hex(random_bytes(4)),
                'occurred_at' => $at->format('Y-m-d H:i:s.u'),
                'occurred_date' => $at->format('Y-m-d'),
                'duration_us' => 0,
                'payload' => json_encode(['level' => 'error', 'message' => 'routine error '.$i]),
            ];
        }
        DB::table('wdn_events')->insert($rows);

        $hits = $repo->recentLogs($projectId, 'error', 100, '30d', 'needle');

        $this->assertCount(1, $hits);
        $this->assertSame('needle in the haystack failure', $hits->first()->payload['message']);
    }

    public function test_level_filter_uses_the_database(): void
    {
        $repo = $this->app->make(DashboardRepository::class);
        $projectId = $this->projectId();

        $this->seedLog($projectId, 'critical', 'old database deadlock', now()->subDays(10));

        $hits = $repo->recentLogs($projectId, 'critical', 100, '30d', null);

        $this->assertCount(1, $hits);
        $this->assertSame('critical', $hits->first()->payload['level']);
        $this->assertSame('old database deadlock', $hits->first()->payload['message']);
    }
}
