<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Fase 0 — the overhead budget for the hot path (RNF-1). During a
 * request/command/job, recorders only push arrays into the in-memory buffer:
 * no serialization, no I/O, zero database round-trips per recorded event. The
 * single write happens at flush(). This is the deterministic, CI-friendly form
 * of an overhead budget — it bounds the per-event cost structurally instead of
 * by a flaky wall-clock threshold.
 */
class OverheadBudgetTest extends TestCase
{
    public function test_the_hot_path_does_zero_database_io(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        // A realistic burst of mixed capture during one entry point.
        for ($i = 0; $i < 100; $i++) {
            $observer->record('query', ['sql' => 'select '.$i, 'bindings' => []]);
            $observer->record('cache', ['action' => 'hit', 'key' => "k{$i}", 'hit' => true]);
            $observer->record('log', ['level' => 'info', 'message' => "m{$i}"]);
        }

        // Not a single query touched the database while capturing.
        $this->assertSame([], DB::connection()->getQueryLog(), 'capture must perform no DB I/O on the hot path');

        // All 300 events are sitting in memory, ready for the single flush write.
        $this->assertSame(300, $observer->buffer()->count());
    }

    public function test_flush_is_the_single_write(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('query', ['sql' => 'select 1', 'bindings' => []]);

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $observer->flush();

        $inserts = array_values(array_filter(
            DB::connection()->getQueryLog(),
            fn (array $q): bool => str_starts_with(strtolower(ltrim((string) $q['query'])), 'insert')
        ));

        $this->assertCount(1, $inserts, 'flush must issue exactly one insert');
        $this->assertStringContainsString('wdn_outbox', strtolower((string) $inserts[0]['query']));
        $this->assertSame(1, OutboxEntry::count());
    }
}
