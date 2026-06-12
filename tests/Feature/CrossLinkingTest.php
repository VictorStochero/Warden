<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The same failure shows up as an issue, an exception event, a request and a
 * trace — these tests pin the shortcuts that tie them together: issue ⇄ raw
 * occurrences ⇄ event detail ⇄ trace timeline, all joined by trace_id and the
 * exception fingerprint.
 */
class CrossLinkingTest extends TestCase
{
    private const TRACE_ID = 'cafebabecafebabecafebabecafebabe';

    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    public function test_issue_event_and_trace_link_to_each_other(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->insertEvent($project->id, 'request', [
            'method' => 'GET', 'route' => 'checkout', 'path' => '/checkout', 'status' => 500,
        ]);
        $this->insertEvent($project->id, 'exception', $this->exceptionPayload());

        // Groups the exception into a wdn_issues row (fingerprint-based).
        $this->artisan('warden:aggregate')->assertSuccessful();

        $issueId = (int) DB::table('wdn_issues')->where('project_id', $project->id)->value('id');
        $exceptionId = (int) DB::table('wdn_events')->where('type', 'exception')->value('id');
        $requestId = (int) DB::table('wdn_events')->where('type', 'request')->value('id');

        $this->assertGreaterThan(0, $issueId, 'aggregate must have created the issue');

        // Issue page → recent occurrences linking to event detail + trace.
        $this->get(route('warden.issue', ['demo', $issueId]))
            ->assertOk()
            ->assertSee(route('warden.event', ['demo', $exceptionId]))
            ->assertSee(route('warden.trace', ['demo', self::TRACE_ID]));

        // Exception event detail → its issue and its trace.
        $this->get(route('warden.event', ['demo', $exceptionId]))
            ->assertOk()
            ->assertSee(route('warden.issue', ['demo', $issueId]))
            ->assertSee(route('warden.trace', ['demo', self::TRACE_ID]));

        // Trace timeline → every event's detail page + the exception's issue.
        $this->get(route('warden.trace', ['demo', self::TRACE_ID]))
            ->assertOk()
            ->assertSee(route('warden.event', ['demo', $exceptionId]))
            ->assertSee(route('warden.event', ['demo', $requestId]))
            ->assertSee(route('warden.issue', ['demo', $issueId]));
    }

    public function test_an_exception_without_an_issue_yet_still_renders(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        // No warden:aggregate run — the issue does not exist yet.
        $this->insertEvent($project->id, 'exception', $this->exceptionPayload());

        $exceptionId = (int) DB::table('wdn_events')->where('type', 'exception')->value('id');

        $this->get(route('warden.event', ['demo', $exceptionId]))->assertOk();
        $this->get(route('warden.trace', ['demo', self::TRACE_ID]))->assertOk();
    }

    /** @return array<string, mixed> */
    private function exceptionPayload(): array
    {
        return [
            'class' => 'App\\Exceptions\\CheckoutFailed',
            'message' => 'Payment gateway timed out for order 42',
            'method' => 'GET',
            'route' => 'checkout',
            'path' => '/checkout',
            'user_id' => 7,
            'stack' => [
                ['file' => 'app/Services/Gateway.php', 'line' => 120, 'function' => 'charge', 'class' => 'App\\Services\\Gateway'],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function insertEvent(int $projectId, string $type, array $payload): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => $type,
            'trace_id' => self::TRACE_ID,
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'occurred_date' => now()->toDateString(),
            'received_at' => now(),
            'duration_us' => 1234,
            'payload' => (string) json_encode($payload),
        ]);
    }
}
