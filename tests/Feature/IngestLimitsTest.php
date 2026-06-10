<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Transport\Signer;

class IngestLimitsTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.max_body_bytes', 500);
        $app['config']->set('warden.parent.max_events_per_request', 3);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'ptoken', 'secret' => 'psecret', 'active' => true]);
    }

    private function ingestPost(string $body)
    {
        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => (new Signer('psecret'))->sign($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    public function test_oversized_body_is_rejected_with_413(): void
    {
        $this->project();
        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b1', 'events' => [['type' => 'log', 'payload' => ['x' => str_repeat('a', 1000)]]]]],
        ]);

        $this->ingestPost($body)->assertStatus(413);
    }

    public function test_too_many_events_is_rejected_with_413(): void
    {
        $this->project();
        $event = ['type' => 'log', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'payload' => ['m' => 1]];
        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b1', 'events' => [$event, $event, $event, $event]]],
        ]);

        $this->ingestPost($body)->assertStatus(413);
    }
}
