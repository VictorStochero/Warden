<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use VictorStochero\Warden\Recording\AbstractRecorder;

class CommandRecorder extends AbstractRecorder
{
    /**
     * Long-running / self-referential commands that must not own a trace.
     *
     * @var list<string>
     */
    protected array $ignore = [
        'queue:work', 'queue:listen', 'schedule:work', 'schedule:run',
        'horizon', 'horizon:work', 'octane:start', 'octane:reload',
        'warden:ship', 'warden:aggregate', 'warden:evaluate',
        'warden:prune', 'warden:partition', 'warden:install', 'warden:project',
        'warden:demo', 'warden:audit',
    ];

    protected bool $active = false;

    protected ?float $startedAt = null;

    public function type(): string
    {
        return 'command';
    }

    public function register(): void
    {
        $this->listen(CommandStarting::class, function (CommandStarting $event) {
            if ($this->shouldIgnore($event->command)) {
                return;
            }

            $this->startedAt = microtime(true);
            $this->observer->reset();
            $this->observer->startTrace('command', name: $event->command);
            $this->active = true;
        });

        $this->listen(CommandFinished::class, function (CommandFinished $event) {
            if (! $this->active) {
                return;
            }

            $duration = $this->startedAt ? (int) round((microtime(true) - $this->startedAt) * 1_000_000) : null;

            $this->record([
                'command' => $event->command,
                'exit_code' => $event->exitCode,
                'arguments' => $this->scrubber()->scrub($event->input->getArguments()),
                'options' => $this->scrubber()->scrub($event->input->getOptions()),
            ], durationUs: $duration);

            $this->observer->flush();
            $this->active = false;
            $this->startedAt = null;
        });
    }

    protected function shouldIgnore(?string $command): bool
    {
        if ($command === null) {
            return true;
        }

        foreach ($this->ignore as $needle) {
            if ($command === $needle || str_starts_with($command, $needle.':')) {
                return true;
            }
        }

        return false;
    }
}
