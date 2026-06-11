<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Recording\Recorders\QueryRecorder;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class CredentialSuppressionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_credential_rotate_is_not_recorded_by_the_query_recorder(): void
    {
        // No dedicated connection here, so the QueryRecorder would observe every
        // wdn_projects write — exactly the path we want suppressed.
        config()->set('warden.connection', null);

        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $this->app->make(QueryRecorder::class)->register();

        // Control: an ordinary read of wdn_projects IS recorded, proving the
        // recorder is live and does observe this table.
        DB::table('wdn_projects')->count();

        // The credential write must produce no event at all (§18.3).
        $this->app->make(ProjectManager::class)->rotate($project);

        $queries = array_filter(
            $observer->buffer()->all(),
            fn (array $e): bool => ($e['type'] ?? null) === 'query'
        );

        $this->assertNotEmpty($queries, 'the control read was recorded');

        foreach ($queries as $event) {
            $sql = strtolower((string) ($event['payload']['sql'] ?? ''));
            $this->assertStringNotContainsString('update', $sql, 'no credential UPDATE leaked into the buffer');
        }
    }
}
