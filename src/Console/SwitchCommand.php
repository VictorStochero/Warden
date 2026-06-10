<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Console\Concerns\ManagesWardenSchema;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\EnvWriter;

/**
 * Convert an already-installed app between parent and child. Unlike a fresh
 * warden:install, the two roles do not share the same schema usefully — the
 * cleanest, least surprising switch is to rebuild the wdn_ tables from zero.
 * This is destructive (all captured data is dropped) and guarded accordingly.
 */
class SwitchCommand extends Command
{
    use ManagesWardenSchema;

    protected $signature = 'warden:switch
        {mode : Target mode: parent or child}
        {--parent-url= : (child) URL of the parent}
        {--project= : (child) project slug}
        {--token= : (child) ingest token}
        {--secret= : (child) signing secret}
        {--delivery= : (child) scheduler|daemon}
        {--force : Skip the destructive-action confirmation}';

    protected $description = 'Switch Warden between parent and child, rebuilding the wdn_ schema from scratch';

    public function handle(): int
    {
        $target = strtolower(Cast::str($this->argument('mode')));

        if (! in_array($target, ['parent', 'child'], true)) {
            $this->components->error('Mode must be "parent" or "child".');

            return self::FAILURE;
        }

        if (Cast::str(config('warden.mode'), 'child') === $target) {
            $this->components->info("Warden is already in {$target} mode. Nothing to do.");

            return self::SUCCESS;
        }

        if (! $this->confirmDestructive($target)) {
            return self::FAILURE;
        }

        $this->components->info("Switching Warden to {$target}.");

        $this->writeEnv($target);
        $this->components->task('Updated .env');

        $this->dropWardenSchema();
        $this->components->task('Dropped existing Warden tables');

        // Rebuild only Warden's own schema — scoped to the package migrations so
        // unrelated host migrations are never touched.
        $this->call('migrate', [
            '--force' => true,
            '--path' => dirname(__DIR__, 2).'/database/migrations',
            '--realpath' => true,
        ]);

        $this->callSilently('config:clear');
        $this->callSilently('route:clear');
        $this->components->task('Cleared config + route cache');

        if ($target === 'parent') {
            $this->ensureSelfProject();
        }

        $this->printNextSteps($target);

        return self::SUCCESS;
    }

    protected function confirmDestructive(string $target): bool
    {
        if (Cast::bool($this->option('force'))) {
            return true;
        }

        $tables = $this->existingWardenTables();

        $this->components->warn(sprintf(
            'This drops %d Warden table(s) and rebuilds the schema for %s mode — all captured data is lost.',
            count($tables),
            $target,
        ));

        if (! $this->input->isInteractive()) {
            $this->components->error('Refusing to switch non-interactively. Pass --force to proceed.');

            return false;
        }

        return $this->confirm('Continue?', false);
    }

    protected function writeEnv(string $mode): void
    {
        $values = ['WARDEN_MODE' => $mode];

        if ($mode === 'child') {
            $values['WARDEN_PARENT_URL'] = Cast::str($this->option('parent-url'));
            $values['WARDEN_PROJECT'] = Cast::str($this->option('project'));
            $values['WARDEN_TOKEN'] = Cast::str($this->option('token'));
            $values['WARDEN_SECRET'] = Cast::str($this->option('secret'));

            $delivery = Cast::str($this->option('delivery'));
            if ($delivery !== '') {
                $values['WARDEN_DELIVERY'] = $delivery;
            }
        }

        (new EnvWriter($this->laravel->environmentFilePath()))->upsert($values);
    }

    protected function ensureSelfProject(): void
    {
        if (! Cast::bool(config('warden.parent.self_monitor', true))) {
            return;
        }

        $slug = Cast::str(config('warden.parent.self_project', 'parent'), 'parent');

        $this->laravel->make(ProjectManager::class)->ensureSelfProject($slug);
        $this->components->task("Ensured self-monitoring project [{$slug}]");
    }

    protected function printNextSteps(string $mode): void
    {
        $this->newLine();

        if ($mode === 'parent') {
            $this->components->info('Now a parent. Open the dashboard at /warden and create a project to mint child credentials.');

            return;
        }

        $this->components->info('Now a child. With parent_url/token/secret set and the scheduler cron running, data ships every minute.');
    }
}
