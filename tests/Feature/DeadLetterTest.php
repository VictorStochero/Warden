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
        $body = (string) json_encode(['batch_id' => 'b-123', 'reason' => 'poison', 'attempts' => 10, 'sent_at' => time()]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(202);

        $this->assertSame(1, DeadLetter::where('project_id', $project->id)->where('batch_id', 'b-123')->count());
    }

    public function test_stale_report_is_rejected(): void
    {
        $this->project();
        $body = (string) json_encode(['batch_id' => 'b-old', 'attempts' => 1, 'sent_at' => time() - 999]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(422);

        $this->assertSame(0, DeadLetter::count());
    }

    public function test_report_without_sent_at_is_rejected(): void
    {
        $this->project();
        $body = (string) json_encode(['batch_id' => 'b-nostamp', 'attempts' => 1]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(422);
    }

    public function test_oversized_batch_id_is_capped_not_fatal(): void
    {
        $this->project();
        $huge = str_repeat('x', 300);
        $body = (string) json_encode(['batch_id' => $huge, 'attempts' => 1, 'sent_at' => time()]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(202);

        $stored = (string) DeadLetter::query()->value('batch_id');
        $this->assertSame(64, mb_strlen($stored));
    }

    public function test_same_batch_is_deduplicated(): void
    {
        $project = $this->project();

        $first = (string) json_encode(['batch_id' => 'b-dup', 'reason' => 'first', 'attempts' => 3, 'sent_at' => time()]);
        $this->deadLetterPost($first, (new Signer('psecret'))->sign($first))->assertStatus(202);

        $second = (string) json_encode(['batch_id' => 'b-dup', 'reason' => 'retry', 'attempts' => 5, 'sent_at' => time()]);
        $this->deadLetterPost($second, (new Signer('psecret'))->sign($second))->assertStatus(202);

        $this->assertSame(1, DeadLetter::where('batch_id', 'b-dup')->count());
        $this->assertSame(5, (int) DeadLetter::where('batch_id', 'b-dup')->value('attempts'));
        $this->assertSame('retry', DeadLetter::where('batch_id', 'b-dup')->value('reason'));
    }

    public function test_endpoint_rejects_bad_signature(): void
    {
        $this->project();
        $body = (string) json_encode(['batch_id' => 'b-1']);

        $this->deadLetterPost($body, 'deadbeef')->assertStatus(401);
    }

    public function test_oversized_body_is_rejected_with_413(): void
    {
        config()->set('warden.parent.max_body_bytes', 200);
        $this->project();

        $body = (string) json_encode([
            'batch_id' => 'b-big',
            'reason' => str_repeat('a', 1000),
            'attempts' => 1,
        ]);

        $this->deadLetterPost($body, (new Signer('psecret'))->sign($body))->assertStatus(413);
    }
}
