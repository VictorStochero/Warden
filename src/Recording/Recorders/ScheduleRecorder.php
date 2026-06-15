<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use VictorStochero\Warden\Recording\AbstractRecorder;

/**
 * Each scheduled task gets its own trace_id rather than inheriting from
 * schedule:run (§18.1). Schedule events also feed parent-side heartbeats so a
 * dead scheduler can be detected.
 */
class ScheduleRecorder extends AbstractRecorder
{
    protected ?float $startedAt = null;

    public function type(): string
    {
        return 'schedule';
    }

    public function register(): void
    {
        $this->listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->startedAt = microtime(true);
            $this->observer->reset();
            $this->observer->startTrace('schedule', name: $this->taskName($event->task));
        });

        $this->listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            $this->complete($event, 'finished');
        });

        $this->listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            $this->observer->keep();
            $this->complete($event, 'failed', $event->exception->getMessage());
        });

        $this->listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $event) {
            $this->complete($event, 'skipped');
        });
    }

    /** @param ScheduledTaskFinished|ScheduledTaskFailed|ScheduledTaskSkipped $event */
    protected function complete(object $event, string $status, ?string $error = null): void
    {
        if (! $this->observer->hasTrace()) {
            return;
        }

        $task = $event->task;
        $duration = $this->startedAt ? (int) round((microtime(true) - $this->startedAt) * 1_000_000) : null;

        $this->record([
            'task' => $this->taskName($task),
            'expression' => $task->expression,
            'status' => $status,
            'error' => $error,
            // Heartbeat key so the parent can track this task's liveness.
            'heartbeat' => 'schedule:'.md5($this->taskName($task).'|'.$task->expression),
        ], durationUs: $duration);

        $this->observer->flush();
        $this->startedAt = null;
    }

    protected function taskName(Event $task): string
    {
        if (! empty($task->description)) {
            return $task->description;
        }

        if (! empty($task->command)) {
            return trim((string) preg_replace('/\S+\s+artisan/', '', $task->command));
        }

        return 'closure';
    }
}
