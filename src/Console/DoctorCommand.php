<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Schema\WardenTables;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

/**
 * Setup validator: one command that answers "why isn't Warden working?".
 * Checks the database, the mode-specific wiring (child credentials, parent
 * reachability, outbox health / parent tables, projects, schedule) and the
 * host-metrics probes, with an actionable hint next to every failure.
 *
 * Exits non-zero when any blocking check fails, so it can gate deploys.
 */
class DoctorCommand extends Command
{
    protected $signature = 'warden:doctor';

    protected $description = 'Validate the Warden setup and diagnose common misconfigurations';

    /** Whether a blocking check failed (drives the exit code). */
    protected bool $healthy = true;

    public function handle(Warden $warden): int
    {
        $this->components->info('Warden doctor — mode: '.$warden->mode());

        $this->checkDatabase();

        if ($warden->isParent()) {
            $this->checkParent($warden);
        } else {
            $this->checkChild($warden);
        }

        $this->checkHostProbes($warden);

        $this->newLine();

        if ($this->healthy) {
            $this->components->info('All checks passed.');

            return self::SUCCESS;
        }

        $this->components->error('Some checks failed — see the hints above.');

        return self::FAILURE;
    }

    // ------------------------------------------------------------ database

    protected function checkDatabase(): void
    {
        try {
            $db = $this->connection();
            $db->select('select 1');
            $this->passCheck('Database connection ['.$db->getDriverName().']', 'reachable');
        } catch (Throwable $e) {
            $this->failCheck('Database connection', 'unreachable', $e->getMessage());

            return;
        }

        $missing = array_values(array_filter(
            WardenTables::all(),
            fn (string $table): bool => ! $this->connection()->getSchemaBuilder()->hasTable($table),
        ));

        if ($missing === []) {
            $this->passCheck('Warden tables', count(WardenTables::all()).' present');
        } else {
            $this->failCheck('Warden tables', 'missing: '.implode(', ', $missing), 'run `php artisan migrate` (or `php artisan warden:install`)');
        }
    }

    // --------------------------------------------------------------- child

    protected function checkChild(Warden $warden): void
    {
        $url = Cast::str(config('warden.child.parent_url'));
        $token = Cast::str(config('warden.child.token'));
        $secret = Cast::str(config('warden.child.secret'));

        if ($url === '' || $token === '' || $secret === '') {
            $vars = array_keys(array_filter([
                'WARDEN_PARENT_URL' => $url === '',
                'WARDEN_TOKEN' => $token === '',
                'WARDEN_SECRET' => $secret === '',
            ]));
            $this->failCheck('Child credentials', 'incomplete', 'set '.implode(', ', $vars).' (run `php artisan warden:install --child`); the child captures nothing until then');
        } else {
            $this->passCheck('Child credentials', 'configured');

            if (! str_starts_with(strtolower($url), 'https://')) {
                $this->warnCheck('Parent URL', 'not HTTPS', 'batches and the signing secret travel in plaintext — use https:// in production');
            }

            $this->checkParentReachability();
        }

        $this->checkOutbox();

        $delivery = Cast::str(config('warden.child.delivery', 'scheduler'), 'scheduler');
        $scheduled = Cast::bool(config('warden.child.schedule.enabled', true));

        if ($delivery === 'scheduler' && ! $scheduled) {
            $this->warnCheck('Delivery', 'scheduler (disabled)', 'WARDEN_CHILD_SCHEDULE=false: nothing ships unless you run `warden:ship` yourself');
        } else {
            $this->passCheck('Delivery', $delivery.($delivery === 'scheduler' ? ' (every minute via the Laravel scheduler)' : ' (run `warden:ship` under a supervisor)'));
        }

        $recorders = array_map(Cast::str(...), Cast::arr(config('warden.child.recorders', [])));
        if ($recorders === []) {
            $this->warnCheck('Recorders', 'none enabled', 'warden.child.recorders is empty — no events will be captured');
        } else {
            $this->passCheck('Recorders', implode(', ', $recorders));
        }
    }

    protected function checkParentReachability(): void
    {
        try {
            /** @var Transport $transport */
            $transport = $this->laravel->make(Transport::class);

            // A directive-only poll is a full signed ingest round-trip: it
            // exercises URL, TLS, token and HMAC secret in one shot.
            if ($transport->poll()) {
                $this->passCheck('Parent ingest', 'reachable (authenticated round-trip OK)');
            } else {
                $this->failCheck('Parent ingest', 'unreachable or rejected', 'check WARDEN_PARENT_URL, WARDEN_TOKEN and WARDEN_SECRET against the parent project (`warden:project` on the parent shows them)');
            }
        } catch (Throwable $e) {
            $this->failCheck('Parent ingest', 'error', $e->getMessage());
        }
    }

    protected function checkOutbox(): void
    {
        $driver = Cast::str(config('warden.child.outbox', 'database'), 'database');

        try {
            /** @var Outbox $outbox */
            $outbox = $this->laravel->make(Outbox::class);
            $size = $outbox->size();

            if ($outbox->isFull()) {
                $this->warnCheck('Outbox ['.$driver.']', $size.' pending (FULL)', 'capture is paused until `warden:ship` drains it below the low-water mark');
            } else {
                $this->passCheck('Outbox ['.$driver.']', $size.' pending');
            }
        } catch (Throwable $e) {
            $this->failCheck('Outbox ['.$driver.']', 'error', $e->getMessage());
        }
    }

    // -------------------------------------------------------------- parent

    protected function checkParent(Warden $warden): void
    {
        try {
            $total = Project::query()->count();
            $active = Project::query()->where('active', true)->count();

            if ($total === 0) {
                $this->warnCheck('Projects', 'none', 'create one with `php artisan warden:project "My App"` and install the child with its credentials');
            } else {
                $this->passCheck('Projects', "{$active} active / {$total} total");
            }

            $last = $this->connection()->table('wdn_events')->max('received_at');
            $this->passCheck('Last event received', $last !== null ? Cast::str($last) : 'never');
        } catch (Throwable $e) {
            $this->failCheck('Projects', 'error', $e->getMessage());
        }

        if (Cast::bool(config('warden.parent.schedule.enabled', true))) {
            $this->passCheck('Maintenance schedule', 'auto-registered (aggregate/evaluate/partition/prune) — requires the scheduler cron');
        } else {
            $this->warnCheck('Maintenance schedule', 'disabled', 'run warden:aggregate / warden:evaluate / warden:prune yourself or the dashboard stays empty');
        }

        $this->passCheck('Self-monitoring', $warden->selfMonitoring() ? 'on' : 'off');
    }

    // ---------------------------------------------------------- host probes

    protected function checkHostProbes(Warden $warden): void
    {
        if ($warden->isParent() && ! $warden->selfMonitoring()) {
            return;
        }

        if (! is_readable('/proc/stat') || ! is_readable('/proc/meminfo')) {
            $this->warnCheck('Host metrics', '/proc not readable', 'CPU/RAM/process detail needs Linux; load + disk still work');

            return;
        }

        $tmp = sys_get_temp_dir();

        if (! is_writable($tmp)) {
            $this->warnCheck('Host metrics', $tmp.' not writable', 'CPU% needs a cross-process snapshot file there; without it CPU stays empty under PHP-FPM');

            return;
        }

        $this->passCheck('Host metrics', '/proc readable, snapshot dir writable (CPU% appears from the second sample on)');
    }

    // ------------------------------------------------------------- helpers

    protected function passCheck(string $check, string $status): void
    {
        $this->components->twoColumnDetail($check, "<fg=green>{$status}</>");
    }

    protected function warnCheck(string $check, string $status, string $hint): void
    {
        $this->components->twoColumnDetail($check, "<fg=yellow>{$status}</>");
        $this->components->bulletList(["<fg=yellow>{$hint}</>"]);
    }

    protected function failCheck(string $check, string $status, string $hint): void
    {
        $this->healthy = false;
        $this->components->twoColumnDetail($check, "<fg=red>{$status}</>");
        $this->components->bulletList(["<fg=red>{$hint}</>"]);
    }

    protected function connection(): Connection
    {
        $name = Cast::str(config('warden.connection'));

        return DB::connection($name !== '' ? $name : null);
    }
}
