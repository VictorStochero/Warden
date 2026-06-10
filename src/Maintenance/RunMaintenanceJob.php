<?php

namespace VictorStochero\Warden\Maintenance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use VictorStochero\Warden\Models\CommandRun;

/**
 * Runs a whitelisted warden maintenance command off the request path and
 * records the outcome in wdn_command_runs. The whitelist is the security
 * boundary — only these short names may be triggered from the dashboard.
 */
class RunMaintenanceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<string> */
    public const ALLOWED = ['aggregate', 'evaluate', 'prune', 'partition'];

    /**
     * Human-readable explanation of what each command does, surfaced on the
     * maintenance dashboard so operators know the effect before triggering one.
     *
     * @var array<string, string>
     */
    public const DESCRIPTIONS = [
        'aggregate' => 'Rolls recent raw events up into the aggregates the dashboard reads (throughput, p95, error rate, issues). The scheduler runs this every minute.',
        'evaluate' => 'Opens or resolves incidents from issues and heartbeats and fires the configured alert channels (with a per-subject cooldown). The scheduler runs this every five minutes.',
        'prune' => 'Deletes raw events past the retention window (DROP PARTITION where supported) and trims aggregates older than their retention. Destructive — removes old data. Runs daily.',
        'partition' => 'Pre-creates upcoming date partitions for wdn_events on MySQL so ingestion never hits a missing partition. No-op on SQLite. Runs daily.',
    ];

    public function __construct(public string $command, public int $runId) {}

    public function handle(): void
    {
        $run = CommandRun::find($this->runId);

        if ($run === null) {
            return;
        }

        if (! in_array($this->command, self::ALLOWED, true)) {
            $run->update(['status' => 'failed', 'message' => 'Command not allowed.', 'finished_at' => now()]);

            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);
        $start = microtime(true);

        try {
            Artisan::call('warden:'.$this->command);
            $output = trim(Artisan::output());

            $run->update([
                'status' => 'ok',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'message' => $output === '' ? null : mb_substr($output, 0, 1000),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }
}
