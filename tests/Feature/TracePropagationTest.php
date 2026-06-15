<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Trace\Propagation;
use VictorStochero\Warden\Trace\TraceContext;

/**
 * Unit-level guarantees for the fleet trace-propagation header (§29): it
 * round-trips a context faithfully and rejects anything malformed.
 */
class TracePropagationTest extends TestCase
{
    public function test_header_round_trips_a_context(): void
    {
        $traceId = str_repeat('a', 32);
        $trace = new TraceContext('request', sampled: true, traceId: $traceId);

        $parsed = Propagation::parse(Propagation::header($trace));

        $this->assertNotNull($parsed);
        $this->assertSame($traceId, $parsed['trace_id']);
        $this->assertSame($trace->currentSpan()->id, $parsed['parent_span_id']);
        $this->assertTrue($parsed['sampled']);
    }

    public function test_unsampled_flag_round_trips(): void
    {
        $trace = new TraceContext('request', sampled: false, traceId: str_repeat('b', 32));

        $parsed = Propagation::parse(Propagation::header($trace));

        $this->assertNotNull($parsed);
        $this->assertFalse($parsed['sampled']);
    }

    public function test_parse_rejects_malformed_values(): void
    {
        $this->assertNull(Propagation::parse(null));
        $this->assertNull(Propagation::parse(''));
        $this->assertNull(Propagation::parse('nope'));
        $this->assertNull(Propagation::parse(str_repeat('a', 32).'-1'), 'too few parts');
        $this->assertNull(Propagation::parse(str_repeat('z', 32).'-'.str_repeat('a', 32).'-1'), 'non-hex trace');
        $this->assertNull(Propagation::parse('abc-def-1'), 'short ids');
    }
}
