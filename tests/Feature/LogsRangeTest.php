<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Tests\TestCase;

class LogsRangeTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_recent_logs_excludes_events_older_than_range(): void
    {
        $projectId = DB::table('wdn_projects')->insertGetId([
            'slug' => 'demo', 'name' => 'Demo', 'token' => 'test-token-'.uniqid(), 'secret' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert = function (string $when) use ($projectId) {
            DB::table('wdn_events')->insert([
                'project_id' => $projectId, 'type' => 'log',
                'trace_id' => bin2hex(random_bytes(8)), 'span_id' => bin2hex(random_bytes(4)),
                'occurred_at' => $when, 'received_at' => $when, 'occurred_date' => substr($when, 0, 10),
                'duration_us' => 0, 'payload' => json_encode(['level' => 'warning', 'message' => 'x']),
            ]);
        };
        $insert(now()->subMinutes(10)->toDateTimeString()); // dentro de 1h
        $insert(now()->subHours(10)->toDateTimeString());   // fora de 1h

        $repo = $this->app->make(DashboardRepository::class);

        $this->assertCount(1, $repo->recentLogs($projectId, null, 100, '1h'));
        $this->assertCount(2, $repo->recentLogs($projectId, null, 100, '7d'));
    }
}
