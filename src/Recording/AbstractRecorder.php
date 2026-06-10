<?php

namespace VictorStochero\Warden\Recording;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use VictorStochero\Warden\Contracts\Recorder;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Warden;

abstract class AbstractRecorder implements Recorder
{
    public function __construct(
        protected Warden $observer,
        protected Dispatcher $events,
        protected Repository $config,
    ) {}

    protected function scrubber(): Scrubber
    {
        $keys = [];
        foreach (Cast::arr($this->config->get('warden.child.scrub', [])) as $key) {
            $keys[] = Cast::str($key);
        }

        return new Scrubber($keys);
    }

    /** @param array<string, mixed> $payload */
    protected function record(array $payload, ?int $durationUs = null, ?string $spanId = null, ?string $parentSpanId = null): void
    {
        $this->observer->record($this->type(), $payload, $durationUs, $spanId, $parentSpanId);
    }

    protected function msToUs(float $ms): int
    {
        return (int) round($ms * 1000);
    }
}
