<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Process;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;

class AuditCommandTest extends TestCase
{
    public function test_it_ships_a_security_event_with_normalized_advisories(): void
    {
        Process::fake([
            'composer audit*' => Process::result((string) json_encode([
                'advisories' => [
                    'vendor/pkg' => [[
                        'packageName' => 'vendor/pkg',
                        'title' => 'SQL injection in vendor/pkg',
                        'cve' => 'CVE-2024-0001',
                        'link' => 'https://example.test/advisory',
                        'severity' => 'high',
                        'affectedVersions' => '<1.2.3',
                    ]],
                ],
            ])),
            'npm audit*' => Process::result((string) json_encode([
                'vulnerabilities' => [
                    'lodash' => [
                        'name' => 'lodash', 'severity' => 'critical',
                        'via' => [['title' => 'Prototype Pollution', 'url' => 'https://example.test/npm']],
                        'range' => '<4.17.21',
                    ],
                ],
            ])),
        ]);

        $this->artisan('warden:audit')->assertSuccessful();

        $this->assertSame(1, OutboxEntry::count());

        $events = OutboxEntry::first()->batch['events'];
        $this->assertCount(1, $events);
        $this->assertSame('security', $events[0]['type']);

        $payload = $events[0]['payload'];
        $packages = array_column($payload['advisories'], 'package');

        $this->assertContains('vendor/pkg', $packages, 'composer advisory captured');
        $this->assertGreaterThanOrEqual(1, $payload['counts']['high'] ?? 0);
    }

    public function test_composer_audit_falls_back_when_composer_not_on_path(): void
    {
        Process::fake([
            // Primary candidate (configured bin) is what should be used.
            'php composer.phar audit*' => Process::result((string) json_encode([
                'advisories' => [
                    'vendor/pkg' => [[
                        'packageName' => 'vendor/pkg',
                        'title' => 'RCE in vendor/pkg',
                        'cve' => 'CVE-2024-9999',
                        'link' => 'https://example.test/advisory',
                        'severity' => 'high',
                        'affectedVersions' => '<1.0',
                    ]],
                ],
            ])),
            // Bare `composer` is not on PATH: command-not-found style failure.
            'composer audit*' => Process::result('', 'composer: not found', 127),
            'npm audit*' => Process::result((string) json_encode(['vulnerabilities' => []])),
        ]);

        config()->set('warden.child.audit.composer_bin', 'php composer.phar');

        $this->artisan('warden:audit')->assertSuccessful();

        $this->assertSame(1, OutboxEntry::count());

        $payload = OutboxEntry::first()->batch['events'][0]['payload'];

        $this->assertTrue($payload['tools']['composer'], 'composer audit ran via fallback bin');

        $composer = array_values(array_filter(
            $payload['advisories'],
            fn ($a) => $a['ecosystem'] === 'composer',
        ));
        $this->assertCount(1, $composer);
        $this->assertSame('vendor/pkg', $composer[0]['package']);
    }

    public function test_it_fails_when_the_child_is_not_configured(): void
    {
        $this->app['config']->set('warden.child.parent_url', '');
        $this->app['config']->set('warden.child.token', '');

        $this->artisan('warden:audit')->assertFailed();

        $this->assertSame(0, OutboxEntry::count());
    }
}
