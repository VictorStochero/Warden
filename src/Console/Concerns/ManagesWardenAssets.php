<?php

namespace VictorStochero\Warden\Console\Concerns;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Cleanup of the dashboard's old published assets (public/vendor/warden). The CSS
 * is no longer published — it is served from the package by AssetController — so
 * this only removes a stale directory left behind by pre-route installs. Called by
 * warden:uninstall and warden:switch --child.
 *
 * @phpstan-require-extends Command
 */
trait ManagesWardenAssets
{
    protected function removeWardenAssets(): void
    {
        $dir = $this->laravel->publicPath('vendor/warden');

        if (! is_dir($dir)) {
            return;
        }

        // Pre-route installs published the CSS, fonts and brand marks here. The
        // package-served build replaces all of it, so the whole directory goes.
        (new Filesystem)->deleteDirectory($dir);
        $this->components->task('Removed legacy dashboard assets (public/vendor/warden)');
    }
}
