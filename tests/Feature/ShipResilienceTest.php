<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Fase 0 — failure injection on the delivery path. The parent being offline,
 * slow or returning 5xx must never affect the host: the shipper holds the batch
 * with an exponential backoff, delivers it once the parent recovers, and only
 * drops a poison batch after the attempt ceiling (RNF-2).
 */
class ShipResilienceTest extends TestCase
{
    private function pushBatch(string $id = 'b1'): void
    {
        $this->app->make(Outbox::class)->push([
            'schema_version' => 1,
            'batch_id' => $id,
            'trace_id' => 't1',
            'entry_type' => 'request',
            'events' => [['type' => 'log', 'payload' => ['message' => 'hi']]],
        ]);
    }

    public function test_an_unreachable_parent_holds_the_batch_with_backoff(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->pushBatch();

        $this->artisan('warden:ship --once')->assertSuccessful();

        // The batch is retained (not dropped, not crashed), its attempt counted
        // and it is rescheduled into the future for retry.
        $this->assertSame(1, OutboxEntry::count());
        $row = OutboxEntry::first();
        $this->assertSame(1, (int) $row->attempts);
        $this->assertNull($row->reserved_at);
        $this->assertTrue($row->available_at->isFuture());
    }

    public function test_the_batch_delivers_once_the_parent_recovers(): void
    {
        // One fake, two outcomes: the first delivery fails (parent down), the
        // second succeeds (parent recovered).
        Http::fake(['*' => Http::sequence()
            ->push('', 500)
            ->push(['accepted' => 1], 202)]);

        $this->pushBatch();

        $this->artisan('warden:ship --once')->assertSuccessful();
        $this->assertSame(1, OutboxEntry::count(), 'held while the parent is down');

        // Clear the backoff so the held row is eligible to retry now.
        OutboxEntry::query()->update(['available_at' => now()->subSecond()]);

        $this->artisan('warden:ship --once')->assertSuccessful();

        $this->assertSame(0, OutboxEntry::count(), 'the held batch ships once the parent is back');
    }

    public function test_a_poison_batch_is_dropped_only_after_the_attempt_ceiling(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->pushBatch();

        // max-attempts=1: the first failed delivery exhausts the ceiling, so the
        // batch is dead-lettered (best-effort report) and removed — the queue is
        // never blocked forever by one poison row.
        $this->artisan('warden:ship --once --max-attempts=1')->assertSuccessful();

        $this->assertSame(0, OutboxEntry::count());
    }
}
