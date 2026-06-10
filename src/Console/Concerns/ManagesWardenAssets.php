<?php

namespace VictorStochero\Warden\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Publishing and cleanup of the dashboard's static stylesheet
 * (public/vendor/warden/warden.css). Shared by warden:install and warden:switch
 * (publish on the parent side) and warden:uninstall / warden:switch --child
 * (remove). Serving the CSS as a real static file — rather than a PHP route
 * ending in `.css` — keeps it from being intercepted and 404'd by the common
 * web-server static-file rules that match on extension.
 *
 * @phpstan-require-extends Command
 */
trait ManagesWardenAssets
{
    protected function publishWardenAssets(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'warden-assets', '--force' => true]);
        $this->components->task('Published dashboard assets (public/vendor/warden)');
    }

    protected function removeWardenAssets(): void
    {
        $file = $this->laravel->publicPath('vendor/warden/warden.css');

        if (is_file($file)) {
            @unlink($file);
            $this->components->task('Removed dashboard assets (public/vendor/warden)');
        }

        // Drop the directory too, but only when nothing else lives in it.
        $dir = $this->laravel->publicPath('vendor/warden');

        if (is_dir($dir) && (glob($dir.'/*') ?: []) === []) {
            @rmdir($dir);
        }
    }
}
