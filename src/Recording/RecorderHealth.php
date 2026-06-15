<?php

namespace VictorStochero\Warden\Recording;

use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;
use Throwable;
use VictorStochero\Warden\Support\Cast;

/**
 * Process-wide health of the recorders. A recorder whose listener throws is
 * isolated by AbstractRecorder::listen() and the failure is reported here.
 *
 * State lives for the life of the process — under Octane that means the worker,
 * which is intentional: a recorder that fails persistently is a process-health
 * fact, not per-request state, so it is NEVER cleared by the per-request reset
 * (Warden::reset()). After `threshold` failures the recorder is tripped (breaker
 * open) and its listener short-circuits for the rest of the process, which also
 * stops any log-storm. We log exactly twice per recorder: the first failure and
 * the trip.
 */
class RecorderHealth
{
    /** @var array<string, int> failures per recorder type */
    protected array $failures = [];

    /** @var array<string, bool> tripped (breaker open) per recorder type */
    protected array $tripped = [];

    public function __construct(
        protected Repository $config,
        protected LoggerInterface $logger,
    ) {}

    /** Whether the recorder of this type is tripped and must short-circuit. */
    public function isTripped(string $type): bool
    {
        return $this->tripped[$type] ?? false;
    }

    /**
     * Report a recorder failure. Counts it, logs the first occurrence and the
     * trip, and opens the breaker once the threshold is reached. Must never
     * throw — a failure in the failure path still cannot reach the host.
     */
    public function fail(string $type, Throwable $e): void
    {
        try {
            $count = ($this->failures[$type] ?? 0) + 1;
            $this->failures[$type] = $count;

            $threshold = max(1, Cast::int($this->config->get('warden.child.recorder_breaker_threshold', 5), 5));

            if ($count === 1) {
                $this->warn("Warden recorder [{$type}] threw and was isolated", $type, $e);
            }

            if ($count >= $threshold && ! ($this->tripped[$type] ?? false)) {
                $this->tripped[$type] = true;
                $this->warn("Warden recorder [{$type}] disabled for this process after {$threshold} failures", $type, $e);
            }
        } catch (Throwable) {
            // The isolation guarantee holds even if reporting itself fails.
        }
    }

    /**
     * Count a dispatch that was short-circuited because the breaker is already
     * open. Keeps the health tally honest without re-running the known-bad
     * handler and without logging (the storm is already silenced). Must never
     * throw — the isolation guarantee holds even in the suppressed path.
     */
    public function skip(string $type): void
    {
        try {
            $this->failures[$type] = ($this->failures[$type] ?? 0) + 1;
        } catch (Throwable) {
            // The isolation guarantee holds even if accounting itself fails.
        }
    }

    /**
     * A snapshot for diagnostics / the dashboard health surface. Stable shape so
     * the parent can later read recorder health without changing the child.
     *
     * @return array<string, array{failures: int, tripped: bool}>
     */
    public function snapshot(): array
    {
        $out = [];

        foreach ($this->failures as $type => $count) {
            $out[$type] = ['failures' => $count, 'tripped' => $this->tripped[$type] ?? false];
        }

        return $out;
    }

    protected function warn(string $message, string $type, Throwable $e): void
    {
        // `warden => true` makes the LogRecorder skip this line (no self-capture),
        // and we deliberately do NOT pass the Throwable under an `exception` key,
        // so the ExceptionRecorder ignores it too — no recursion, no double count.
        $this->logger->warning($message, [
            'warden' => true,
            'recorder' => $type,
            'error' => $e->getMessage(),
            'error_class' => $e::class,
        ]);
    }
}
