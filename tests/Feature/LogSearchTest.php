<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Full-text-ish log search (§5.9): the logs section can be filtered by a free
 * substring across the message, not just by level.
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
}
