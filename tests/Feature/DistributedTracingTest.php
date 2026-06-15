<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Trace\Propagation;
use VictorStochero\Warden\Warden;

/**
 * Fleet-wide distributed tracing (§29): a request crossing Warden apps becomes a
 * single trace. The child continues an inbound trace from the propagation header
 * and stamps the same header on its own outgoing requests.
 */
class DistributedTracingTest extends TestCase
{
    public function test_an_inbound_trace_header_continues_the_same_trace(): void
    {
        $upstreamTrace = str_repeat('a', 32);
        $upstreamSpan = str_repeat('b', 32);

        $this->app['router']->get('/_probe', fn () => 'ok');

        $this->withHeaders([Propagation::HEADER => "{$upstreamTrace}-{$upstreamSpan}-1"])
            ->get('/_probe')
            ->assertOk();

        $batch = OutboxEntry::first()->batch;

        $this->assertSame($upstreamTrace, $batch['trace_id'], 'The child must continue the upstream trace, not fork a new one');
    }

    public function test_a_fresh_request_without_the_header_starts_its_own_trace(): void
    {
        $this->app['router']->get('/_probe', fn () => 'ok');

        $this->get('/_probe')->assertOk();

        $batch = OutboxEntry::first()->batch;

        $this->assertNotSame(str_repeat('a', 32), $batch['trace_id']);
        $this->assertSame(32, strlen((string) $batch['trace_id']));
    }

    public function test_outgoing_requests_carry_the_trace_header(): void
    {
        Http::fake();

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');

        Http::get('https://downstream.test/api');

        Http::assertSent(function ($request) use ($observer) {
            $expected = Propagation::header($observer->trace());

            return $request->hasHeader(Propagation::HEADER)
                && $request->header(Propagation::HEADER)[0] === $expected;
        });
    }
}
