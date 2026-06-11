<?php

namespace VictorStochero\Warden\Console\Audit;

use Symfony\Component\Process\ExecutableFinder;

/**
 * Tier 0 of the composer audit: build an ordered list of composer invocations
 * to try, robust to the many ways the binary is (not) reachable across hosts —
 * Forge daemons with a stripped PATH, cron, bare metal, Docker. Pure (no
 * execution); the caller runs each candidate until one yields parseable JSON.
 *
 * Priority: an explicit override, then a PATH search (ExecutableFinder, seeded
 * with common install dirs), then curated absolute binaries, then a `.phar`
 * run with the *same* PHP that's executing Warden (so it works even when `php`
 * itself isn't on the daemon's PATH), then bare `composer`/`composer.phar`.
 */
class ComposerLocator
{
    public function __construct(
        protected string $basePath,
        protected string $configuredBin = '',
        protected ?ExecutableFinder $finder = null,
        protected string $phpBinary = PHP_BINARY,
        protected ?string $home = null,
    ) {
        $this->finder ??= new ExecutableFinder;
        $this->home ??= (getenv('HOME') ?: getenv('USERPROFILE')) ?: '';
    }

    /** @return list<string> */
    public function candidates(): array
    {
        $cmds = [];

        if ($this->configuredBin !== '') {
            $cmds[] = $this->configuredBin;
        }

        $found = $this->finder?->find('composer', null, $this->extraDirs());
        if (is_string($found) && $found !== '') {
            $cmds[] = escapeshellarg($found);
        }

        foreach ($this->absoluteBinaries() as $path) {
            if (is_file($path)) {
                $cmds[] = escapeshellarg($path);
            }
        }

        foreach ($this->phars() as $phar) {
            if (is_file($phar)) {
                $cmds[] = escapeshellarg($this->phpBinary).' '.escapeshellarg($phar);
            }
        }

        // Last resort: rely on whatever PATH the process happens to have.
        $cmds[] = 'composer';
        $cmds[] = 'composer.phar';

        return array_values(array_unique($cmds));
    }

    /** @return list<string> */
    protected function extraDirs(): array
    {
        $dirs = ['/usr/local/bin', '/usr/bin', '/bin', '/opt/composer'];

        if ($this->home !== '') {
            $dirs[] = $this->home.'/.composer/vendor/bin';
            $dirs[] = $this->home.'/.config/composer/vendor/bin';
        }

        return $dirs;
    }

    /** @return list<string> */
    protected function absoluteBinaries(): array
    {
        return [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/composer/composer',
        ];
    }

    /** @return list<string> */
    protected function phars(): array
    {
        $phars = [$this->basePath.'/composer.phar'];

        if ($this->home !== '') {
            $phars[] = $this->home.'/.composer/composer.phar';
            $phars[] = $this->home.'/.config/composer/composer.phar';
        }

        return $phars;
    }
}
