<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Outbox\OutboxBatch;
use VictorStochero\Warden\Support\Cast;

/**
 * Supervised daemon (M3): drains the outbox and ships batches to the parent.
 * On failure the batch stays in the outbox with an exponential backoff; the
 * host app is never affected (RNF-2). Designed to be run under a process
 * supervisor; --once makes it scheduler-friendly.
 */
class ShipCommand extends Command
{
    protected $signature = 'warden:ship
        {--once : Drain what is currently pending, then exit}
        {--batch=50 : Outbox rows to combine per POST}
        {--sleep=1 : Seconds to idle when the outbox is empty}
        {--max-attempts=10 : Drop a batch after this many failed deliveries}';

    protected $description = 'Drain the child outbox and ship event batches to the parent';

    protected bool $shouldStop = false;

    /** Monotonic seconds of the last parent-requested audit (in-process throttle). */
    protected float $lastAuditAt = 0.0;

    public function handle(Outbox $outbox, Transport $transport): int
    {
        if (config('warden.mode') !== 'child') {
            $this->components->error('warden:ship only runs in child mode.');

            return self::FAILURE;
        }

        $this->trapSignals();

        $limit = (int) $this->option('batch');
        $sleep = max(1, (int) $this->option('sleep'));

        $this->components->info('Warden shipper started.');

        do {
            $batches = $outbox->reserve($limit);

            if ($batches === []) {
                if ($this->option('once')) {
                    break;
                }
                sleep($sleep);

                continue;
            }

            $this->shipBatches($outbox, $transport, $batches);
            $this->maybeRunAudit($transport);
        } while (! $this->shouldStop && ! $this->option('once'));

        $this->components->info('Warden shipper stopped.');

        return self::SUCCESS;
    }

    /** @param array<int, OutboxBatch> $batches */
    protected function shipBatches(Outbox $outbox, Transport $transport, array $batches): void
    {
        // One shipment per reserved row, carrying its stable batch_id so the
        // parent can dedupe (exactly-once). Rows are still combined into one POST.
        $shipments = [];
        $eventCount = 0;
        foreach ($batches as $batch) {
            $events = $batch->events();
            $eventCount += count($events);
            $shipments[] = ['id' => $batch->batchId(), 'events' => $events];
        }

        $ok = $shipments === [] ? true : $transport->ship($shipments);

        foreach ($batches as $batch) {
            if ($ok) {
                $outbox->delete($batch);

                continue;
            }

            if ($batch->attempts + 1 >= Cast::int($this->option('max-attempts'), 10)) {
                // Give up on a poison batch rather than block the queue forever.
                // Report to the parent (best-effort); fall back to an error log
                // so the drop is never silent.
                $attempts = $batch->attempts + 1;
                if (! $transport->reportDeadLetter($batch->batchId(), 'max_attempts_exceeded', $attempts)) {
                    Log::error('[warden] dead-letter drop', [
                        'warden' => true,
                        'batch_id' => $batch->batchId(),
                        'attempts' => $attempts,
                    ]);
                }
                $outbox->delete($batch);
                $this->components->warn("Dropped batch #{$batch->id} after {$batch->attempts} failed attempts.");

                continue;
            }

            $outbox->release($batch, $this->backoff($batch->attempts));
        }

        if ($ok) {
            $this->components->twoColumnDetail('Shipped', $eventCount.' events / '.count($batches).' batches');
        } else {
            $this->components->warn('Parent unreachable — '.count($batches).' batches held for retry.');
        }
    }

    /** Exponential backoff capped at 5 minutes. */
    protected function backoff(int $attempts): int
    {
        return (int) min(2 ** $attempts, 300);
    }

    /**
     * Parent-driven scheduling: if the last ingest response asked for a
     * dependency audit, run warden:audit — throttled to once per 5 minutes per
     * process so a daemon doesn't re-run it on every drain before the result ships.
     */
    protected function maybeRunAudit(Transport $transport): void
    {
        if (($transport->lastDirectives()['audit_due'] ?? false) !== true) {
            return;
        }

        $now = microtime(true);
        if ($this->lastAuditAt > 0.0 && ($now - $this->lastAuditAt) < 300) {
            return;
        }
        $this->lastAuditAt = $now;

        $this->components->info('Parent requested a dependency audit — running warden:audit.');
        Artisan::call('warden:audit');
    }

    protected function trapSignals(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        $stop = function () {
            $this->shouldStop = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
