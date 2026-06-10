<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Outbox\DatabaseOutbox;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class ChildCaptureTest extends TestCase
{
    public function test_a_trace_correlates_all_events_and_flushes_one_batch(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        $trace = $observer->startTrace('request', name: '/checkout');
        $observer->record('query', ['sql' => 'select 1']);
        $observer->record('cache', ['action' => 'hit', 'key' => 'k', 'hit' => true]);
        $observer->flush();

        $this->assertSame(1, OutboxEntry::count());

        $batch = OutboxEntry::first()->batch;
        $traceIds = collect($batch['events'])->pluck('trace_id')->unique();

        $this->assertSame([$trace->traceId], $traceIds->values()->all(), 'All events share the trace_id');
        $this->assertEqualsCanonicalizing(['query', 'cache'], collect($batch['events'])->pluck('type')->all());
    }

    public function test_unsampled_trace_is_dropped_at_flush(): void
    {
        $this->app['config']->set('warden.child.sample.traces.request', 0.0);

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->record('query', ['sql' => 'select 1']);
        $observer->flush();

        $this->assertSame(0, OutboxEntry::count());
    }

    public function test_force_keep_overrides_sampling_for_errored_traces(): void
    {
        $this->app['config']->set('warden.child.sample.traces.request', 0.0);

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->record('exception', ['class' => 'Boom', 'message' => 'x']);
        $observer->keep(); // tail-based promotion
        $observer->flush();

        $this->assertSame(1, OutboxEntry::count());
    }

    public function test_flushed_payload_carries_a_batch_id(): void
    {
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('log', ['level' => 'info', 'message' => 'x']);
        $observer->flush();

        $row = DB::connection()->table('wdn_outbox')->first();

        $this->assertNotNull($row);
        $payload = json_decode((string) $row->batch, true);
        $this->assertArrayHasKey('batch_id', $payload);
        $this->assertSame(32, strlen((string) $payload['batch_id']));
    }

    public function test_flush_never_throws_when_the_outbox_table_is_missing(): void
    {
        // Simulate a freshly-installed child whose migrations haven't run yet.
        Schema::dropIfExists('wdn_outbox');

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('log', ['level' => 'info', 'message' => 'x']);

        // RNF-2: a capture/outbox failure must never propagate into the host app.
        $observer->flush();

        $this->assertFalse($observer->hasTrace());
    }

    public function test_transport_ships_v2_batches_envelope(): void
    {
        Http::fake();

        $transport = $this->app->make(Transport::class);
        $ok = $transport->ship([
            ['id' => 'batch-abc', 'events' => [['type' => 'log', 'payload' => ['m' => 1]]]],
        ]);

        $this->assertTrue($ok);
        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return ($body['schema_version'] ?? null) === 2
                && isset($body['batches'][0]['id'])
                && $body['batches'][0]['id'] === 'batch-abc'
                && isset($body['batches'][0]['events']);
        });
    }

    public function test_transport_exposes_audit_due_directive_from_response(): void
    {
        Http::fake(['*' => Http::response(['accepted' => 1, 'audit_due' => true], 202)]);

        $transport = $this->app->make(Transport::class);
        $transport->ship([['id' => 'b1', 'events' => [['type' => 'log', 'payload' => []]]]]);

        $this->assertTrue($transport->lastDirectives()['audit_due'] ?? false);
    }

    public function test_reserve_still_returns_pending_rows_and_marks_them_reserved(): void
    {
        $outbox = $this->app->make(Outbox::class);
        $outbox->push(['batch_id' => 'b1', 'events' => [['type' => 'log', 'payload' => []]]]);

        $reserved = $outbox->reserve(10);
        $this->assertCount(1, $reserved);
        $this->assertSame('b1', $reserved[0]->batchId());

        // A second reserve within the visibility window must not re-hand the same row.
        $this->assertCount(0, $outbox->reserve(10));
    }

    public function test_outbox_stops_capturing_at_the_high_water_mark(): void
    {
        $this->app['config']->set('warden.child.outbox_high_water', 2);
        $this->app['config']->set('warden.child.outbox_low_water', 1);

        /** @var DatabaseOutbox $outbox */
        $outbox = $this->app->make(Outbox::class);

        $outbox->push(['events' => [1]]);
        $outbox->push(['events' => [2]]);
        $this->assertTrue($outbox->isFull());

        $outbox->push(['events' => [3]]); // dropped — disk safety (§18.6)
        $this->assertSame(2, $outbox->size());
    }

    public function test_transport_reports_dead_letter(): void
    {
        Http::fake();

        $transport = $this->app->make(Transport::class);
        $ok = $transport->reportDeadLetter('batch-x', 'poison', 10);

        $this->assertTrue($ok);
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), 'dead-letter')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return ($body['batch_id'] ?? null) === 'batch-x' && ($body['attempts'] ?? null) === 10;
        });
    }
}
