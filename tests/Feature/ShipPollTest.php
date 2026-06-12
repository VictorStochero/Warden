<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The control channel (audit_due, pushed config) rides the ingest response — so
 * it only reaches the child when the child POSTs. A quiet child with an empty
 * outbox never POSTed, so "Run audit now" and config pushes silently stalled.
 * The shipper now polls the parent with an empty batch when idle.
 */
class ShipPollTest extends TestCase
{
    public function test_poll_round_trips_with_empty_batches_and_captures_directives(): void
    {
        Http::fake(['*' => Http::response(['accepted' => 0, 'audit_due' => true, 'config_version' => 3], 202)]);

        $transport = $this->app->make(Transport::class);

        $this->assertTrue($transport->poll());
        $this->assertTrue($transport->lastDirectives()['audit_due'] ?? false);

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return ($body['schema_version'] ?? null) === 2 && ($body['batches'] ?? null) === [];
        });
    }

    public function test_an_idle_shipper_polls_the_parent_and_runs_a_due_audit(): void
    {
        // Match the audit invocation, not the binary name — the locator may
        // resolve composer to a quoted absolute path. Stray real processes are
        // blocked so the test never shells out for real.
        Process::preventStrayProcesses();
        Process::fake([
            '*audit --format=json*' => Process::result((string) json_encode(['advisories' => []])),
            'npm audit*' => Process::result((string) json_encode(['vulnerabilities' => []])),
        ]);
        Http::fake(['*' => Http::response(['accepted' => 0, 'audit_due' => true, 'config_version' => 0], 202)]);

        // The outbox is empty: before this fix the daemon would never contact the
        // parent and the audit_due directive would never arrive.
        $this->artisan('warden:ship --once')->assertSuccessful();

        Http::assertSent(fn (Request $r): bool => (json_decode($r->body(), true)['batches'] ?? null) === []);

        // The poll surfaced audit_due → warden:audit ran → a security snapshot is queued.
        $this->assertSame(1, OutboxEntry::count());
        $this->assertSame('security', OutboxEntry::first()->batch['events'][0]['type']);
    }

    public function test_a_quiet_shipper_with_an_unreachable_parent_does_not_crash(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('warden:ship --once')->assertSuccessful();

        $this->assertSame(0, OutboxEntry::count(), 'no directives, no audit, no crash');
    }
}
