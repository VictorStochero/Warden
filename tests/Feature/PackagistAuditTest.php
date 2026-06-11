<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Console\Audit\PackagistAudit;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class PackagistAuditTest extends TestCase
{
    private function lock(string $name, string $version): string
    {
        return json_encode([
            'packages' => [['name' => $name, 'version' => $version]],
            'packages-dev' => [],
        ]) ?: '{}';
    }

    private function audit(): PackagistAudit
    {
        return new PackagistAudit($this->app->make(Warden::class));
    }

    public function test_reports_an_advisory_when_the_installed_version_is_affected(): void
    {
        Http::fake(['packagist.org/*' => Http::response(['advisories' => [
            'monolog/monolog' => [[
                'advisoryId' => 'PKSA-1',
                'packageName' => 'monolog/monolog',
                'affectedVersions' => '>=1.0.0,<1.10.1',
                'title' => 'Remote code execution',
                'cve' => 'CVE-2022-0001',
                'link' => 'https://example.test/advisory',
                'severity' => 'high',
            ]],
        ]])]);

        $result = $this->audit()->run($this->lock('monolog/monolog', '1.5.0'));

        $this->assertTrue($result['ran']);
        $this->assertNull($result['reason']);
        $this->assertCount(1, $result['advisories']);
        $this->assertSame('monolog/monolog', $result['advisories'][0]['package']);
        $this->assertSame('high', $result['advisories'][0]['severity']);
        $this->assertSame('composer', $result['advisories'][0]['ecosystem']);
        $this->assertSame(['type' => 'upgrade', 'version' => '1.10.1'], $result['advisories'][0]['fix']);
    }

    public function test_ignores_an_advisory_when_the_installed_version_is_not_affected(): void
    {
        Http::fake(['packagist.org/*' => Http::response(['advisories' => [
            'monolog/monolog' => [[
                'packageName' => 'monolog/monolog',
                'affectedVersions' => '>=1.0.0,<1.10.1',
                'title' => 'old bug',
                'severity' => 'high',
            ]],
        ]])]);

        $result = $this->audit()->run($this->lock('monolog/monolog', '2.9.0'));

        $this->assertTrue($result['ran']);
        $this->assertCount(0, $result['advisories'], 'a patched version is not flagged');
    }

    public function test_a_network_failure_is_a_diagnosed_skip_not_a_crash(): void
    {
        Http::fake(['packagist.org/*' => Http::response('', 500)]);

        $result = $this->audit()->run($this->lock('monolog/monolog', '1.5.0'));

        $this->assertFalse($result['ran']);
        $this->assertSame('network_error', $result['reason']);
    }

    public function test_an_empty_lock_is_a_diagnosed_skip(): void
    {
        $result = $this->audit()->run('{"packages":[],"packages-dev":[]}');

        $this->assertFalse($result['ran']);
        $this->assertSame('lock_missing', $result['reason']);
    }
}
