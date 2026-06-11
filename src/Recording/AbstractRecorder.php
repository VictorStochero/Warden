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

    private ?Scrubber $scrubber = null;

    private ?string $scrubberKey = null;

    /**
     * The Scrubber for this recorder. Building one is non-trivial (it compiles
     * the message-key regex fragments), and the query recorder builds it on the
     * hot path — once per query. Config is stable within a process, so we memoize
     * and only rebuild when the inputs actually change (keyed by a cheap config
     * signature, so a runtime config change is still picked up).
     */
    protected function scrubber(): Scrubber
    {
        $keys = [];
        foreach (Cast::arr($this->config->get('warden.child.scrub', [])) as $key) {
            $keys[] = Cast::str($key);
        }

        $pii = Cast::bool($this->config->get('warden.child.capture.pii', false));
        $noFloor = Cast::bool($this->config->get('warden.child.capture.disable_credential_scrub', false));

        $signature = implode('|', $keys).'#'.($pii ? '1' : '0').($noFloor ? '1' : '0');

        $scrubber = $this->scrubberKey === $signature ? $this->scrubber : null;

        if ($scrubber === null) {
            $scrubber = new Scrubber($keys, $pii, $noFloor);
            $this->scrubber = $scrubber;
            $this->scrubberKey = $signature;
        }

        return $scrubber;
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
