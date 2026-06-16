<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * A queued job that records one uniquely-tagged cache event while it runs, plus
 * a handful of queries, so jobs sharing one long-lived worker can be told apart.
 */
class LoadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public int $n) {}

    public function handle(Warden $warden): void
    {
        $warden->record('cache', ['action' => 'hit', 'key' => "job-{$this->n}", 'hit' => true]);
        for ($i = 0; $i < 3; $i++) {
            $warden->record('query', ['sql' => "select {$this->n}.{$i}", 'bindings' => []]);
        }
    }
}

/**
 * Fase 0 — queue/Horizon under load on ONE long-lived worker. A single
 * `queue:work --stop-when-empty` process drains many jobs back-to-back (the real
 * Horizon supervisor shape). Each job must flush its own batch under its own
 * trace, never inheriting the previous job's events — proving the per-job reset
 * holds at scale, not just for two jobs (§18.2).
 */
class QueueLoadIsolationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function test_many_jobs_on_one_worker_never_cross_contaminate(): void
    {
        $count = 15;
        for ($n = 1; $n <= $count; $n++) {
            LoadJob::dispatch($n);
        }

        // One long-lived worker drains every job in sequence.
        $this->artisan('queue:work', ['--stop-when-empty' => true]);

        // The job batches are the ones carrying an in-job cache hit.
        $jobBatches = OutboxEntry::all()
            ->map(fn (OutboxEntry $e) => $e->batch)
            ->filter(fn (array $b) => collect($b['events'])->contains('type', 'cache'))
            ->values();

        $this->assertCount($count, $jobBatches, 'each job flushes exactly one batch');

        $allKeys = [];
        $allTraces = [];

        foreach ($jobBatches as $batch) {
            $cacheKeys = collect($batch['events'])->where('type', 'cache')->pluck('payload.key')->unique()->values();
            $this->assertCount(1, $cacheKeys, 'a job batch must carry only its own cache event');
            $key = (string) $cacheKeys->first();
            $allKeys[] = $key;
            $n = (int) str_replace('job-', '', $key);

            // Every event in the batch shares one trace id — no leak across jobs.
            $traceIds = collect($batch['events'])->pluck('trace_id')->unique();
            $this->assertCount(1, $traceIds, 'all events in a job batch share one trace');
            $allTraces[] = $traceIds->first();

            // The job's own 3 app queries rode its trace (the batch also legitimately
            // captures the queue driver's SQL, so assert the job's queries specifically
            // rather than the total — proving they didn't leak into another job).
            $ownQueries = collect($batch['events'])
                ->where('type', 'query')
                ->filter(fn (array $e) => str_contains((string) ($e['payload']['sql'] ?? ''), "select {$n}."));
            $this->assertCount(3, $ownQueries, "job {$n} must carry exactly its own 3 queries");
        }

        // All 15 jobs produced distinct keys and distinct traces — zero leakage.
        $this->assertCount($count, array_unique($allKeys));
        $this->assertCount($count, array_unique($allTraces));
    }
}
