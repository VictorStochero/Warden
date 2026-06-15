<?php

namespace VictorStochero\Warden\Trace;

/**
 * Fleet-wide trace propagation over HTTP (§29). A child injects the current
 * trace id, current span id and sampling decision into an outgoing header;
 * a downstream Warden child reads it and continues the SAME trace instead of
 * starting a fresh one — so a request crossing apps becomes one waterfall.
 *
 * A Warden-native header (not W3C `traceparent`) is used deliberately: Warden's
 * trace and span ids are both 32-hex (16 bytes), which don't fit traceparent's
 * 16-hex (8-byte) parent-id field. The format is `<traceId>-<spanId>-<0|1>`,
 * mirroring the queue propagation payload (trace_id / span_id / sampled).
 */
class Propagation
{
    public const HEADER = 'X-Warden-Trace';

    public static function header(TraceContext $trace): string
    {
        return $trace->traceId.'-'.$trace->currentSpan()->id.'-'.($trace->sampled ? '1' : '0');
    }

    /**
     * Parse an inbound header into the inherited-context shape Warden::startTrace
     * accepts, or null when it's absent or malformed (so we just open a fresh
     * trace, never trusting garbage).
     *
     * @return array{trace_id: string, parent_span_id: string, sampled: bool}|null
     */
    public static function parse(?string $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $parts = explode('-', trim($value));

        if (count($parts) !== 3) {
            return null;
        }

        [$traceId, $spanId, $flag] = $parts;

        if (! self::isId($traceId) || ! self::isId($spanId)) {
            return null;
        }

        return [
            'trace_id' => $traceId,
            'parent_span_id' => $spanId,
            'sampled' => $flag === '1',
        ];
    }

    private static function isId(string $value): bool
    {
        return strlen($value) === 32 && ctype_xdigit($value);
    }
}
