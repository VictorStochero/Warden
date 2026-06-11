<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Console\Audit\ComposerAudit;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class ComposerAuditTest extends TestCase
{
    private string $base = '';

    protected function tearDown(): void
    {
        if ($this->base !== '' && is_dir($this->base)) {
            @unlink($this->base.'/composer.lock');
            @rmdir($this->base);
        }
        parent::tearDown();
    }

    private function tempBase(bool $withLock = false): string
    {
        $this->base = sys_get_temp_dir().'/wdn_ca_'.bin2hex(random_bytes(4));
        mkdir($this->base);

        if ($withLock) {
            file_put_contents($this->base.'/composer.lock', json_encode([
                'packages' => [['name' => 'monolog/monolog', 'version' => '1.5.0']],
                'packages-dev' => [],
            ]));
        }

        return $this->base;
    }

    private const COMPOSER_JSON = '{"advisories":{"foo/bar":[{"packageName":"foo/bar","severity":"critical","title":"RCE","cve":"CVE-9","link":"https://e.test/a","affectedVersions":">=1.0,<2.0"}]},"abandoned":[]}';

    private function make(string $base, \Closure $runner): ComposerAudit
    {
        return new ComposerAudit($this->app->make(Warden::class), $base, runner: $runner);
    }

    public function test_tier0_uses_the_composer_binary_when_it_returns_json(): void
    {
        $audit = $this->make($this->tempBase(), fn (string $cmd): string => self::COMPOSER_JSON);

        $result = $audit->run();

        $this->assertSame('binary', $result['status']['method']);
        $this->assertTrue($result['status']['ran']);
        $this->assertCount(1, $result['advisories']);
        $this->assertSame('foo/bar', $result['advisories'][0]['package']);
        $this->assertSame('critical', $result['advisories'][0]['severity']);
    }

    public function test_tier0_tolerates_leading_noise_before_the_json(): void
    {
        $runner = fn (string $cmd): string => "Deprecation Notice: blah\n".self::COMPOSER_JSON;

        $result = $this->make($this->tempBase(), $runner)->run();

        $this->assertSame('binary', $result['status']['method']);
        $this->assertCount(1, $result['advisories']);
    }

    public function test_tier1_falls_back_to_packagist_when_no_binary_yields_json(): void
    {
        Http::fake(['packagist.org/*' => Http::response(['advisories' => [
            'monolog/monolog' => [[
                'packageName' => 'monolog/monolog',
                'affectedVersions' => '>=1.0.0,<1.10.1',
                'title' => 'bug',
                'severity' => 'high',
            ]],
        ]])]);

        // Runner yields nothing for every candidate → no binary works.
        $result = $this->make($this->tempBase(withLock: true), fn (string $cmd): string => '')->run();

        $this->assertSame('packagist', $result['status']['method']);
        $this->assertTrue($result['status']['ran']);
        $this->assertCount(1, $result['advisories']);
        $this->assertSame('monolog/monolog', $result['advisories'][0]['package']);
    }

    public function test_tier2_diagnosed_skip_when_no_binary_and_no_lock(): void
    {
        $result = $this->make($this->tempBase(withLock: false), fn (string $cmd): string => '')->run();

        $this->assertFalse($result['status']['ran']);
        $this->assertSame('composer_not_found', $result['status']['reason']);
    }
}
