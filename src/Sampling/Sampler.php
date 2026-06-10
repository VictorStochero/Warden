<?php

namespace VictorStochero\Warden\Sampling;

use Illuminate\Contracts\Config\Repository;
use VictorStochero\Warden\Support\Cast;

/**
 * Two-axis sampling (§18.4).
 *
 *  - Axis A (sampleTrace): head-based, one decision per entry point, carried to
 *    downstream jobs so timelines never end up with orphan events.
 *  - Axis B (typeEnabled): a global gate per event type, independent of traces.
 *    A boolean toggles the whole category; a float 0..1 keeps that fraction of
 *    events (leaving that type's timeline intentionally incomplete).
 */
class Sampler
{
    public function __construct(protected Repository $config) {}

    public function sampleTrace(string $entryType): bool
    {
        $rate = Cast::float($this->config->get("warden.child.sample.traces.{$entryType}", 1.0), 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return $this->roll($rate);
    }

    public function typeEnabled(string $type): bool
    {
        $gate = $this->config->get("warden.child.sample.type_gate.{$type}", true);

        if (is_bool($gate)) {
            return $gate;
        }

        if (is_numeric($gate)) {
            $gate = (float) $gate;

            return $gate >= 1.0 ? true : ($gate <= 0.0 ? false : $this->roll($gate));
        }

        return true;
    }

    protected function roll(float $rate): bool
    {
        return (mt_rand() / mt_getrandmax()) < $rate;
    }
}
