<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

/**
 * Jobs cross process boundaries, so this recorder is also the worker-side half
 * of trace propagation (§18.1):
 *
 *  - JobQueued     records the dispatch edge inside the *current* trace.
 *  - JobProcessing opens a trace, restoring trace_id/span/sampled from the
 *    payload injected at dispatch (or a fresh trace if there is none).
 *  - JobProcessed / JobFailed flush per-job, never per worker process (§18.2).
 */
class JobRecorder extends AbstractRecorder
{
    /** Start time + descriptor of the job currently processing, keyed by job id. */
    protected ?float $startedAt = null;

    public function type(): string
    {
        return 'job';
    }

    public function register(): void
    {
        $this->events->listen(JobQueued::class, fn (JobQueued $e) => $this->onQueued($e));
        $this->events->listen(JobProcessing::class, fn (JobProcessing $e) => $this->onProcessing($e));
        $this->events->listen(JobProcessed::class, fn (JobProcessed $e) => $this->onProcessed($e));
        $this->events->listen(JobFailed::class, fn (JobFailed $e) => $this->onFailed($e));
        $this->events->listen(JobReleasedAfterException::class, fn (JobReleasedAfterException $e) => $this->onReleased($e));
    }

    protected function onQueued(JobQueued $event): void
    {
        // Edge: where the job was dispatched from (its parent trace).
        $this->record([
            'status' => 'queued',
            'class' => is_object($event->job) ? get_class($event->job) : Cast::str($event->job),
            'connection' => $event->connectionName,
            'queue' => $event->queue ?? null,
        ]);
    }

    protected function onProcessing(JobProcessing $event): void
    {
        $this->startedAt = microtime(true);

        $payload = $event->job->payload();

        // Sync jobs run inline during dispatch — stay in the current trace
        // rather than forking a second context (§18.1).
        if ($event->connectionName === 'sync' && $this->observer->hasTrace()) {
            return;
        }

        // Async worker: clean any leftover state, then open the job's trace.
        $this->observer->reset();

        $this->observer->startTrace('job', [
            'trace_id' => $payload['wdn_trace_id'] ?? null,
            'parent_span_id' => $payload['wdn_span_id'] ?? null,
            'sampled' => $payload['wdn_sampled'] ?? null,
        ], name: $this->jobName($event->job));
    }

    protected function onProcessed(JobProcessed $event): void
    {
        $this->recordCompletion($event, 'processed');
        $this->finish($event);
    }

    protected function onFailed(JobFailed $event): void
    {
        $this->observer->keep();
        $this->recordCompletion($event, 'failed', $event->exception->getMessage());
        $this->finish($event);
    }

    protected function onReleased(JobReleasedAfterException $event): void
    {
        $this->observer->keep();
        $this->recordCompletion($event, 'released');
    }

    /** @param JobProcessed|JobFailed|JobReleasedAfterException $event */
    protected function recordCompletion(object $event, string $status, ?string $error = null): void
    {
        $job = $event->job;
        $duration = $this->startedAt ? (int) round((microtime(true) - $this->startedAt) * 1_000_000) : null;

        $this->record([
            'status' => $status,
            'class' => $this->jobName($job),
            'queue' => $job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $job->attempts(),
            'error' => $error,
        ], durationUs: $duration);
    }

    /** @param JobProcessed|JobFailed|JobReleasedAfterException $event */
    protected function finish(object $event): void
    {
        // Sync jobs flush with their parent entry point, not here.
        if ($event->connectionName !== 'sync') {
            $this->observer->flush();
        }

        $this->startedAt = null;
    }

    protected function jobName(Job $job): string
    {
        return $job->resolveName();
    }
}
