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
 *
 * Adaptive sampling (§5.8): an optional per-process boost raises the effective
 * head rate after an error/slow signal (capture more when something's wrong) and
 * decays back toward the base rate along the happy path. Off by default; the
 * boost is per-process state, reset on the Octane/worker boundary (§18.2).
 */
class Sampler
{
    /** Extra rate added on top of the base head rate, decaying toward 0. */
    protected float $boost = 0.0;

    public function __construct(protected Repository $config) {}

    public function sampleTrace(string $entryType): bool
    {
        $base = Cast::float($this->config->get("warden.child.sample.traces.{$entryType}", 1.0), 1.0);
        $rate = $this->effectiveRate($base);

        // Each head decision fades the adaptive boost a step.
        $this->decay();

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return $this->roll($rate);
    }

    /**
     * The head rate after the adaptive boost is applied (capped at the configured
     * max). Equals the base rate when adaptive sampling is disabled.
     */
    public function effectiveRate(float $base): float
    {
        if (! $this->adaptiveEnabled()) {
            return $base;
        }

        return min($this->maxRate(), $base + $this->boost);
    }

    /**
     * Signal that the current entry point was anomalous (errored / slow), so the
     * next few traces are sampled more aggressively. No-op when adaptive sampling
     * is disabled. Idempotent within a trace — repeated calls don't compound.
     */
    public function signalAnomaly(): void
    {
        if (! $this->adaptiveEnabled()) {
            return;
        }

        $this->boost = max($this->boost, $this->boostAmount());
    }

    /** Octane / worker boundary: drop the adaptive boost (§18.2). */
    public function reset(): void
    {
        $this->boost = 0.0;
    }

    protected function decay(): void
    {
        if ($this->boost <= 0.0) {
            return;
        }

        $this->boost *= $this->decayFactor();

        if ($this->boost < 0.001) {
            $this->boost = 0.0;
        }
    }

    protected function adaptiveEnabled(): bool
    {
        return (bool) $this->config->get('warden.child.sample.adaptive.enabled', false);
    }

    protected function maxRate(): float
    {
        return Cast::float($this->config->get('warden.child.sample.adaptive.max_rate', 1.0), 1.0);
    }

    protected function boostAmount(): float
    {
        return Cast::float($this->config->get('warden.child.sample.adaptive.boost', 1.0), 1.0);
    }

    protected function decayFactor(): float
    {
        $decay = Cast::float($this->config->get('warden.child.sample.adaptive.decay', 0.5), 0.5);

        // Keep it a contraction in (0,1) so the boost actually fades.
        return ($decay > 0.0 && $decay < 1.0) ? $decay : 0.5;
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
