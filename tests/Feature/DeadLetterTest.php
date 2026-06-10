<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\DeadLetter;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Transport\Signer;

class DeadLetterTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 'ptoken', 'secret' => 'psecret', 'active' => true]);
    }

    private function deadLetterPost(string $body, string $signature)
    {
        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.deadletter'), [], [], [], $server, $body);
    }

    public function test_endpoint_records_a_dead_letter(): void
    {
        $project = $this->project();
        $body = (string) json_encode(['batch_id' => 'b-123', 'reason' => 'poison', 'attempts' => 10]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(202);

        $this->assertSame(1, DeadLetter::where('project_id', $project->id)->where('batch_id', 'b-123')->count());
    }

    public function test_endpoint_rejects_bad_signature(): void
    {
        $this->project();
        $body = (string) json_encode(['batch_id' => 'b-1']);

        $this->deadLetterPost($body, 'deadbeef')->assertStatus(401);
    }
}
