<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Console\Concerns\ManagesWardenSchema;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\EnvWriter;

/**
 * Remove every trace Warden left in the host app: drop all wdn_ tables, strip
 * the WARDEN_* keys from the .env and delete the published config. The package
 * itself stays in composer.json — `composer remove victorstochero/warden`
 * finishes the job. Published migration files are intentionally left in place:
 * deleting versioned files the host committed is more surprising than helpful.
 */
class UninstallCommand extends Command
{
    use ManagesWardenSchema;

    protected $signature = 'warden:uninstall {--force : Skip the destructive-action confirmation}';

    protected $description = 'Remove Warden completely: drop its tables, strip WARDEN_* from .env and delete the published config';

    public function handle(): int
    {
        if (! $this->confirmDestructive()) {
            return self::FAILURE;
        }

        $this->components->info('Uninstalling Warden.');

        $this->dropWardenSchema();
        $this->components->task('Dropped all Warden tables');

        $this->stripEnv();
        $this->components->task('Removed WARDEN_* keys from .env');

        $this->deletePublishedConfig();

        $this->callSilently('config:clear');
        $this->callSilently('route:clear');
        $this->components->task('Cleared config + route cache');

        $this->newLine();
        $this->components->info('Warden removed. To drop the package too: composer remove victorstochero/warden');

        return self::SUCCESS;
    }

    protected function confirmDestructive(): bool
    {
        if (Cast::bool($this->option('force'))) {
            return true;
        }

        $tables = $this->existingWardenTables();

        $this->components->warn(sprintf(
            'This drops %d Warden table(s), strips WARDEN_* from .env and deletes config/warden.php — irreversible.',
            count($tables),
        ));

        if (! $this->input->isInteractive()) {
            $this->components->error('Refusing to uninstall non-interactively. Pass --force to proceed.');

            return false;
        }

        return $this->confirm('Continue?', false);
    }

    protected function stripEnv(): void
    {
        (new EnvWriter($this->laravel->environmentFilePath()))->forget($this->wardenEnvKeys());
    }

    /**
     * Every WARDEN_* key currently present in the .env, discovered by scanning
     * rather than a hardcoded list so custom/tuning keys are removed too.
     *
     * @return list<string>
     */
    protected function wardenEnvKeys(): array
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            return [];
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^(WARDEN_[A-Z0-9_]+)=/', $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }

    protected function deletePublishedConfig(): void
    {
        $path = $this->laravel->configPath('warden.php');

        if (is_file($path)) {
            @unlink($path);
            $this->components->task('Deleted config/warden.php');
        }
    }
}
