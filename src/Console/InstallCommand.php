<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\EnvWriter;

use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    protected $signature = 'warden:install
        {--parent : Configure this app as the parent (collector + dashboard)}
        {--child : Configure this app as a child (observed app)}
        {--parent-url= : (child) URL of the parent}
        {--project= : (child) project slug}
        {--token= : (child) ingest token}
        {--secret= : (child) signing secret}
        {--connection= : Database connection name for Warden tables}
        {--delivery= : (child) scheduler|daemon}
        {--no-migrate : Do not run database migrations}
        {--force : Overwrite any existing published files}';

    protected $description = 'Install Warden as a parent or child (writes .env, publishes, migrates)';

    public function handle(): int
    {
        $mode = $this->resolveMode();

        if ($mode === null) {
            return self::FAILURE;
        }

        $this->components->info("Installing Warden ({$mode}).");

        $this->callSilently('vendor:publish', ['--tag' => 'warden-config', '--force' => (bool) $this->option('force')]);
        $this->components->task('Published config/warden.php');

        $this->callSilently('vendor:publish', ['--tag' => 'warden-migrations', '--force' => (bool) $this->option('force')]);
        $this->components->task('Published migrations');

        $this->writeEnv($mode);
        $this->components->task('Updated .env');

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        if ($mode === 'parent') {
            $this->ensureSelfProject();
        }

        $this->printNextSteps($mode);

        return self::SUCCESS;
    }

    protected function resolveMode(): ?string
    {
        $parent = (bool) $this->option('parent');
        $child = (bool) $this->option('child');

        if ($parent && $child) {
            $this->components->error('Pass either --parent or --child, not both.');

            return null;
        }

        if ($parent) {
            return 'parent';
        }

        if ($child) {
            return 'child';
        }

        if ($this->input->isInteractive()) {
            return Cast::str(select(
                label: 'Install Warden as…',
                options: ['parent' => 'Parent (collector + dashboard)', 'child' => 'Child (observed app)'],
                default: 'child',
            ), 'child');
        }

        $this->components->error('Specify --parent or --child.');

        return null;
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

        $connection = Cast::str($this->option('connection'));
        if ($connection !== '') {
            $values['WARDEN_CONNECTION'] = $connection;
        }

        (new EnvWriter($this->laravel->environmentFilePath()))->upsert($values);
    }

    /**
     * Create the self-monitoring project so the parent can record itself from
     * the first request. Skipped silently when self-monitoring is disabled; the
     * migrate step above guarantees the table exists.
     */
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
            $this->components->info('Parent ready. Open the dashboard and create a project to mint child credentials.');

            return;
        }

        $this->components->info('Child ready. With the scheduler cron running, data ships automatically every minute.');
        $this->line('  <fg=gray>Daemon mode?</> set WARDEN_DELIVERY=daemon and supervise `php artisan warden:ship`.');
    }
}
