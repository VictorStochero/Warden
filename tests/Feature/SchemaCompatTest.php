<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Http\Controllers\IngestController;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Transport\Signer;

/**
 * Fleet schema-version compatibility (§5.11): the parent ingests children that
 * may run different package versions. The accepted versions are an explicit,
 * documented set; a body outside it is rejected with a clear error that names
 * the supported window — never a silent or fatal mismatch.
 */
class SchemaCompatTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'ptoken', 'secret' => 'psecret', 'active' => true]);
    }

    private function ingest(int $schemaVersion)
    {
        $body = (string) json_encode([
            'schema_version' => $schemaVersion, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b1', 'events' => [['type' => 'log', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'payload' => ['m' => 1]]]]],
        ]);

        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => (new Signer('psecret'))->sign($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    public function test_a_supported_schema_version_is_accepted(): void
    {
        $this->project();

        $this->assertContains(2, IngestController::SUPPORTED_SCHEMA_VERSIONS);
        $this->ingest(2)->assertStatus(202);
    }

    public function test_an_unsupported_schema_version_is_rejected_with_the_supported_window(): void
    {
        $this->project();

        $this->ingest(999)
            ->assertStatus(422)
            ->assertJson([
                'error' => 'unsupported_schema',
                'supported' => IngestController::SUPPORTED_SCHEMA_VERSIONS,
            ]);
    }
}
