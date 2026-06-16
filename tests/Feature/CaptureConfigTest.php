<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The child reports which knobs are pinned by its own .env in every ingest body
 * (env_overrides). The parent persists that list on the project so the dashboard
 * can show, honestly, when a toggle is being overridden locally and is therefore
 * ignored. Persistence is best-effort and must never break the ingest.
 */
class CaptureConfigTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function seedProject(): Project
    {
        return Project::create([
            'name' => 'Demo', 'slug' => 'demo',
            'token' => 'ptoken', 'secret' => 'psecret', 'active' => true,
        ]);
    }

    /**
     * @param  list<string>  $envOverrides
     */
    private function ship(array $envOverrides): TestResponse
    {
        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => Carbon::now()->getTimestamp(),
            'config_version' => 0, 'env_overrides' => $envOverrides, 'batches' => [],
        ], JSON_UNESCAPED_SLASHES);

        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => hash_hmac('sha256', $body, 'psecret'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    public function test_ingest_persists_reported_env_overrides(): void
    {
        $project = $this->seedProject();

        $res = $this->ship(['capture.pii', 'sample.traces.request']);

        $res->assertStatus(202);
        $this->assertSame(
            ['capture.pii', 'sample.traces.request'],
            $project->fresh()?->env_overrides,
        );
    }

    public function test_ingest_keeps_only_string_overrides(): void
    {
        $project = $this->seedProject();

        // Non-string entries (and empties) are filtered out before persisting.
        $this->ship(['capture.pii', '', 42, ['nested']]);

        $this->assertSame(['capture.pii'], $project->fresh()?->env_overrides);
    }

    public function test_ingest_clears_env_overrides_when_child_reports_none(): void
    {
        $project = $this->seedProject();
        $project->forceFill(['env_overrides' => ['capture.pii']])->save();

        $this->ship([]);

        $this->assertSame([], $project->fresh()?->env_overrides);
    }

    public function test_identical_env_overrides_do_not_trigger_db_write(): void
    {
        $project = $this->seedProject();
        $project->forceFill(['env_overrides' => ['capture.pii']])->save();

        DB::enableQueryLog();

        $res = $this->ship(['capture.pii']);
        $res->assertStatus(202);

        $updates = array_filter(
            DB::getQueryLog(),
            fn (array $q) => str_contains(strtolower($q['query']), 'update') &&
                             str_contains($q['query'], 'wdn_projects') &&
                             str_contains($q['query'], 'env_overrides'),
        );

        DB::disableQueryLog();

        $this->assertEmpty($updates, 'UPDATE de env_overrides em wdn_projects foi disparado mesmo sem mudança.');
    }
}
