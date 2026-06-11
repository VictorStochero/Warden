<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use VictorStochero\Warden\Console\Audit\ComposerLocator;

class ComposerLocatorTest extends TestCase
{
    private string $base = '';

    protected function tearDown(): void
    {
        if ($this->base !== '' && is_dir($this->base)) {
            @unlink($this->base.'/composer.phar');
            @rmdir($this->base);
        }
        parent::tearDown();
    }

    private function tempBase(): string
    {
        $this->base = sys_get_temp_dir().'/wdn_loc_'.bin2hex(random_bytes(4));
        mkdir($this->base);

        return $this->base;
    }

    private function nullFinder(): ExecutableFinder
    {
        return new class extends ExecutableFinder
        {
            public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
            {
                return $default;
            }
        };
    }

    private function fixedFinder(string $path): ExecutableFinder
    {
        return new class($path) extends ExecutableFinder
        {
            public function __construct(private string $path) {}

            public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
            {
                return $this->path;
            }
        };
    }

    public function test_configured_bin_is_tried_first(): void
    {
        $locator = new ComposerLocator($this->tempBase(), '/custom/composer', $this->nullFinder());

        $this->assertSame('/custom/composer', $locator->candidates()[0]);
    }

    public function test_executable_finder_result_is_included_quoted(): void
    {
        $locator = new ComposerLocator($this->tempBase(), '', $this->fixedFinder('/opt/bin/composer'));

        $this->assertContains(escapeshellarg('/opt/bin/composer'), $locator->candidates());
    }

    public function test_a_project_phar_is_run_with_the_php_binary(): void
    {
        $base = $this->tempBase();
        touch($base.'/composer.phar');

        $locator = new ComposerLocator($base, '', $this->nullFinder(), '/usr/bin/php');

        $expected = escapeshellarg('/usr/bin/php').' '.escapeshellarg($base.'/composer.phar');
        $this->assertContains($expected, $locator->candidates());
    }

    public function test_bare_path_fallbacks_are_present_and_last(): void
    {
        $candidates = (new ComposerLocator($this->tempBase(), '', $this->nullFinder()))->candidates();

        $this->assertContains('composer', $candidates);
        $this->assertSame('composer.phar', end($candidates), 'the bare phar is the last resort');
    }

    public function test_candidates_are_deduplicated(): void
    {
        // configured bin equal to a bare fallback must not appear twice.
        $candidates = (new ComposerLocator($this->tempBase(), 'composer', $this->nullFinder()))->candidates();

        $this->assertSame(array_values(array_unique($candidates)), $candidates);
    }
}
