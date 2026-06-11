<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Transport\Signer;

class IngestCompressionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'ptoken', 'secret' => 'psecret', 'active' => true]);
    }

    private function body(): string
    {
        return (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b-gz', 'events' => [[
                'type' => 'log', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'payload' => ['m' => 'hello'],
            ]]]],
        ]);
    }

    private function ingest(string $wire, array $extraHeaders, string $signOver)
    {
        $server = $this->transformHeadersToServerVars(array_merge([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => (new Signer('psecret'))->sign($signOver),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extraHeaders));

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $wire);
    }

    public function test_gzip_body_is_inflated_and_accepted(): void
    {
        $this->project();
        $body = $this->body();

        // Signature is over the uncompressed JSON; the wire body is gzip.
        $this->ingest((string) gzencode($body), ['Content-Encoding' => 'gzip'], $body)
            ->assertStatus(202);

        $this->assertSame(1, Schema::db()->table('wdn_events')->count());
    }

    public function test_uncompressed_body_still_accepted(): void
    {
        $this->project();
        $body = $this->body();

        $this->ingest($body, [], $body)->assertStatus(202);

        $this->assertSame(1, Schema::db()->table('wdn_events')->count());
    }
}
