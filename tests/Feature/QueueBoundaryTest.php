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
 * A queued job records one tagged cache event while it runs, so two jobs sharing
 * one worker can be told apart in the outbox.
 */
class RecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public string $tag) {}

    public function handle(Warden $warden): void
    {
        $warden->record('cache', ['action' => 'hit', 'key' => $this->tag, 'hit' => true]);
    }
}

/**
 * Jobs never pass through the HTTP terminate cycle, so the JobRecorder owns the
 * worker boundary: JobProcessing resets leftover state and opens the job's own
 * trace; JobProcessed flushes per job, not per worker process (§18.2). This
 * drives a REAL worker (`queue:work --once` over the database driver) to prove
 * each job ships its own batch and never inherits the previous job's events.
 */
class QueueBoundaryTest extends TestCase
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

    public function test_each_job_ships_its_own_batch_without_inheriting_the_previous(): void
    {
        RecordingJob::dispatch('job-a');
        RecordingJob::dispatch('job-b');

        // One real worker, two jobs, processed one at a time.
        $this->artisan('queue:work', ['--once' => true]);
        $this->artisan('queue:work', ['--once' => true]);

        // The dispatch edges (JobQueued) ride the dispatcher's own trace; the two
        // job batches we care about are the ones that carry the in-job cache hit.
        $batches = OutboxEntry::all()
            ->map(fn (OutboxEntry $e) => $e->batch)
            ->filter(fn (array $b) => collect($b['events'])->contains('type', 'cache'))
            ->values();

        $this->assertCount(2, $batches, 'Each job must flush its own batch');

        foreach ($batches as $batch) {
            $cacheKeys = collect($batch['events'])
                ->where('type', 'cache')
                ->pluck('payload.key')
                ->unique()
                ->values();

            $this->assertCount(1, $cacheKeys, 'A job batch must not inherit another job cache event');

            $traceIds = collect($batch['events'])->pluck('trace_id')->unique();
            $this->assertCount(1, $traceIds, 'All events in a job batch share one trace id');
        }

        // The two jobs ran under distinct traces — no leakage across the worker.
        $allTraceIds = $batches
            ->flatMap(fn (array $b) => collect($b['events'])->pluck('trace_id'))
            ->unique();

        $this->assertCount(2, $allTraceIds, 'The two jobs must each get their own trace');
    }
}
