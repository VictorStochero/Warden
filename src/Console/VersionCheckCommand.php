<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Updates\VersionCheck;

class VersionCheckCommand extends Command
{
    protected $signature = 'warden:version-check
        {--force : Re-check even if a fresh result is already cached}';

    protected $description = 'Check Packagist for a newer stable release and cache the result (parent)';

    public function handle(VersionCheck $check): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:version-check only runs in parent mode.');

            return self::FAILURE;
        }

        if (! $check->enabled()) {
            $this->components->info('Version check is disabled.');

            return self::SUCCESS;
        }

        $check->run(force: (bool) $this->option('force'));

        $notice = $check->notice();

        if ($notice === null) {
            $this->components->info('Warden is up to date.');
        } else {
            $this->components->warn("Warden {$notice['latest']} is available (you have {$notice['current']}).");
        }

        return self::SUCCESS;
    }
}
