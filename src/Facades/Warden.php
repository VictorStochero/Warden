<?php

namespace VictorStochero\Warden\Facades;

use Illuminate\Support\Facades\Facade;
use VictorStochero\Warden\Trace\TraceContext;

/**
 * @method static string mode()
 * @method static bool isChild()
 * @method static bool isParent()
 * @method static bool recording()
 * @method static mixed withoutRecording(\Closure $callback)
 * @method static bool hasTrace()
 * @method static TraceContext|null trace()
 * @method static TraceContext startTrace(string $entryType, array<string, mixed>|null $inherited = null, ?string $name = null)
 * @method static void record(string $type, array<string, mixed> $payload, ?int $durationUs = null, ?string $spanId = null, ?string $parentSpanId = null, ?string $occurredAt = null)
 * @method static mixed measure(string $name, \Closure $callback, array<string, mixed> $context = [])
 * @method static void keep()
 * @method static void flush()
 * @method static void reset()
 *
 * @see \VictorStochero\Warden\Warden
 */
class Warden extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \VictorStochero\Warden\Warden::class;
    }
}
