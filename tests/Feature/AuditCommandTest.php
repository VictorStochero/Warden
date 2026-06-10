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

    public function test_it_fails_when_the_child_is_not_configured(): void
    {
        $this->app['config']->set('warden.child.parent_url', '');
        $this->app['config']->set('warden.child.token', '');

        $this->artisan('warden:audit')->assertFailed();

        $this->assertSame(0, OutboxEntry::count());
    }
}
