<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Outbox\OutboxBatch;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Fase 0 — the single write at flush() must never throw into the host's
 * request/command/job lifecycle (RNF-2). A missing table, a full disk or a dead
 * database has to drop the batch silently and reset cleanly, not propagate.
 */
class FlushResilienceTest extends TestCase
{
    public function test_flush_swallows_a_throwing_outbox_and_resets(): void
    {
        // An outbox whose write blows up (e.g. disk full / DB down).
        $this->app->instance(Outbox::class, new class implements Outbox
        {
            public function push(array $batch): void
            {
                throw new \RuntimeException('disk full');
            }

            public function reserve(int $limit): array
            {
                return [];
            }

            public function delete(OutboxBatch $batch): void {}

            public function release(OutboxBatch $batch, int $delaySeconds): void {}

            public function size(): int
            {
                return 0;
            }

            public function isFull(): bool
            {
                return false;
            }
        });

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('log', ['level' => 'error', 'message' => 'boom']);

        // Reaching the line after flush() IS the assertion: nothing propagated.
        $observer->flush();

        $this->assertFalse($observer->hasTrace(), 'flush must reset even when delivery throws');
    }

    public function test_flush_swallows_a_missing_outbox_table(): void
    {
        Schema::connection()->dropIfExists('wdn_outbox');

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('log', ['level' => 'error', 'message' => 'boom']);

        $observer->flush();

        $this->assertFalse($observer->hasTrace());
    }
}
