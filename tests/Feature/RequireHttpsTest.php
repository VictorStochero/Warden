<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Transport\Signer;

class RequireHttpsTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'ptoken', 'secret' => 'psecret', 'active' => true]);
    }

    private function ingest(string $scheme)
    {
        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b1', 'events' => [['type' => 'log', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'payload' => ['m' => 1]]]]],
        ]);

        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => (new Signer('psecret'))->sign($body),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $url = $scheme.'://localhost/'.ltrim(route('warden.ingest', [], false), '/');

        return $this->call('POST', $url, [], [], [], $server, $body);
    }

    public function test_insecure_ingest_is_rejected_when_https_required(): void
    {
        config()->set('warden.parent.require_https', true);
        $this->project();

        $this->ingest('http')->assertStatus(403);
    }

    public function test_secure_ingest_is_accepted_when_https_required(): void
    {
        config()->set('warden.parent.require_https', true);
        $this->project();

        $this->ingest('https')->assertStatus(202);
    }

    public function test_insecure_ingest_is_accepted_when_https_not_required(): void
    {
        config()->set('warden.parent.require_https', false);
        $this->project();

        $this->ingest('http')->assertStatus(202);
    }
}
