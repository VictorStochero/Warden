<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Issues\IssueProcessor;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Models\Heartbeat;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Tests\TestCase;

class ParentPipelineTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    /**
     * POST a raw, byte-exact body with the signing headers. We bypass the
     * convenience helpers because they re-encode array payloads, which would
     * change the bytes the HMAC is computed over.
     */
    protected function postSigned(string $body, string $token, string $signature)
    {
        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => $token,
            'X-Warden-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    protected function project(string $slug = 'demo', string $token = 'ptoken', string $secret = 'psecret'): Project
    {
        return Project::create([
            'name' => ucfirst($slug), 'slug' => $slug,
            'token' => $token, 'secret' => $secret, 'active' => true,
        ]);
    }

    public function test_project_command_creates_a_project_with_credentials(): void
    {
        $this->artisan('warden:project', ['name' => 'My App'])->assertSuccessful();

        $project = Project::where('slug', 'my-app')->firstOrFail();
        $this->assertSame('My App', $project->name);
        $this->assertNotEmpty($project->token);
        $this->assertNotEmpty($project->secret);

        // Slug collisions are rejected.
        $this->artisan('warden:project', ['name' => 'My App'])->assertFailed();
    }

    public function test_project_command_prints_child_install_one_liner(): void
    {
        $this->artisan('warden:project', ['name' => 'My App', '--delivery' => 'daemon'])
            ->expectsOutputToContain('warden:install --child')
            ->assertSuccessful();
    }

    public function test_ingest_endpoint_validates_signature_and_persists_events(): void
    {
        $project = $this->project();

        $body = json_encode([
            'schema_version' => 2,
            'project' => 'demo',
            'sent_at' => time(),
            'batches' => [['id' => 'http-1', 'events' => [
                ['type' => 'request', 'trace_id' => 't1', 'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'duration_us' => 1500, 'payload' => ['route' => '/x', 'status' => 200]],
            ]]],
        ], JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $body, 'psecret');

        $response = $this->postSigned($body, 'ptoken', $signature);

        $response->assertStatus(202);
        $this->assertSame(1, Event::where('project_id', $project->id)->count());
        $this->assertNotNull($project->fresh()->last_seen_at);
    }

    public function test_ingest_advertises_audit_due_per_project_schedule(): void
    {
        $project = $this->project();

        // No schedule configured -> never due.
        $b1 = $this->signedAuditBody();
        $this->postSigned($b1, 'ptoken', hash_hmac('sha256', $b1, 'psecret'))
            ->assertStatus(202)->assertJson(['audit_due' => false]);

        // A daily schedule with no prior security event -> due.
        $project->update(['audit_frequency' => 'daily']);
        $b2 = $this->signedAuditBody();
        $this->postSigned($b2, 'ptoken', hash_hmac('sha256', $b2, 'psecret'))
            ->assertStatus(202)->assertJson(['audit_due' => true]);
    }

    public function test_ingest_advertises_audit_due_for_an_instant_request(): void
    {
        $project = $this->project();
        $project->update(['audit_requested_at' => now()]); // "Run audit now"

        $b = $this->signedAuditBody();
        $this->postSigned($b, 'ptoken', hash_hmac('sha256', $b, 'psecret'))
            ->assertStatus(202)->assertJson(['audit_due' => true]);
    }

    protected function signedAuditBody(): string
    {
        return (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => time(),
            'batches' => [['id' => 'b'.bin2hex(random_bytes(5)), 'events' => [$this->requestEvent()]]],
        ], JSON_UNESCAPED_SLASHES);
    }

    public function test_ingest_rejects_a_bad_signature(): void
    {
        $this->project();

        $body = json_encode(['project' => 'demo', 'sent_at' => time(), 'events' => []]);

        $this->postSigned($body, 'ptoken', 'deadbeef')->assertStatus(401);
    }

    public function test_ingest_rejects_a_stale_replayed_body(): void
    {
        $this->project();

        $body = json_encode(['schema_version' => 2, 'project' => 'demo', 'sent_at' => time() - 99999, 'batches' => []]);
        $signature = hash_hmac('sha256', $body, 'psecret');

        $this->postSigned($body, 'ptoken', $signature)->assertStatus(422);
    }

    public function test_identical_exceptions_form_one_issue_then_reopen_on_recurrence(): void
    {
        $project = $this->project();

        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b1', 'events' => [$this->exceptionEvent('t1'), $this->exceptionEvent('t2')]]]);

        $this->app->make(Aggregator::class)->rollup($project->id, 'exception');
        $this->app->make(IssueProcessor::class)->process($project->id);

        $issues = Issue::where('project_id', $project->id)->get();
        $this->assertCount(1, $issues);
        $this->assertSame(2, (int) $issues->first()->count);

        // Resolve, then a recurrence must reopen it (§15).
        $issues->first()->update(['status' => 'resolved']);

        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b2', 'events' => [$this->exceptionEvent('t3')]]]);
        $this->app->make(IssueProcessor::class)->process($project->id);

        $reopened = Issue::where('project_id', $project->id)->first();
        $this->assertSame('open', $reopened->status);
        $this->assertSame(3, (int) $reopened->count);
    }

    public function test_dead_heartbeat_opens_an_incident_and_recovers(): void
    {
        $project = $this->project();

        Heartbeat::create([
            'project_id' => $project->id, 'key' => 'schedule:nightly',
            'expected_interval' => 60, 'grace' => 10,
            'last_seen_at' => Carbon::now()->subMinutes(10), 'alerted' => false,
        ]);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(1, Incident::where('project_id', $project->id)
            ->where('subject', 'heartbeat:schedule:nightly')->where('status', 'open')->count());

        // It beats again -> incident resolves.
        Heartbeat::where('project_id', $project->id)->update(['last_seen_at' => Carbon::now()]);
        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::where('project_id', $project->id)->where('status', 'open')->count());
    }

    public function test_multiple_projects_are_isolated_in_the_overview(): void
    {
        $a = $this->project('app-a', 'tok-a', 'sec-a');
        $b = $this->project('app-b', 'tok-b', 'sec-b');

        $ingestor = $this->app->make(Ingestor::class);
        $ingestor->ingest('app-a', [['id' => 'a1', 'events' => [$this->requestEvent(), $this->requestEvent()]]]);
        $ingestor->ingest('app-b', [['id' => 'b1', 'events' => [$this->requestEvent()]]]);

        $aggregator = $this->app->make(Aggregator::class);
        foreach ([$a, $b] as $p) {
            $aggregator->rollup($p->id, 'request');
        }

        $overview = $this->app->make(DatabaseWardenRepository::class)->projects()->keyBy('slug');

        $this->assertSame(2, $overview['app-a']->throughput);
        $this->assertSame(1, $overview['app-b']->throughput);
    }

    protected function exceptionEvent(string $trace): array
    {
        return [
            'type' => 'exception', 'trace_id' => $trace,
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'payload' => [
                'class' => 'App\\Exceptions\\Boom',
                'message' => 'Record 7 missing',
                'user_id' => 1,
                'stack' => [['file' => '/app/Foo.php', 'line' => 10, 'function' => 'bar']],
            ],
        ];
    }

    protected function requestEvent(): array
    {
        return [
            'type' => 'request', 'trace_id' => bin2hex(random_bytes(8)),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'), 'duration_us' => 5000,
            'payload' => ['route' => '/home', 'status' => 200],
        ];
    }
}
