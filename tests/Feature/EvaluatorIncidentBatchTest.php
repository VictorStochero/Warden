<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Tests\TestCase;

class EvaluatorIncidentBatchTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_open_incidents_are_read_once_not_per_issue(): void
    {
        $projectId = (int) DB::table('wdn_projects')->insertGetId([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['fp-a', 'fp-b', 'fp-c'] as $fp) {
            DB::table('wdn_issues')->insert([
                'project_id' => $projectId, 'fingerprint' => $fp, 'class' => 'Boom', 'message' => 'x',
                'count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $this->app->make(Evaluator::class)->evaluate($projectId);

        $incidentSelects = array_filter(
            DB::connection()->getQueryLog(),
            fn (array $q): bool => str_starts_with(ltrim(strtolower((string) $q['query'])), 'select')
                && str_contains((string) $q['query'], 'wdn_incidents')
        );

        // One preload, regardless of how many open issues there are.
        $this->assertCount(1, $incidentSelects);

        // Behaviour preserved: an incident was opened for each issue.
        $this->assertSame(3, DB::table('wdn_incidents')->where('status', 'open')->count());
    }
}
