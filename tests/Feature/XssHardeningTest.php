<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Stored-XSS hardening: the parent aggregates data shipped by (untrusted)
 * children. A malicious child must not be able to inject script/markup into the
 * parent dashboard via host metrics or via a `javascript:` advisory link.
 */
class XssHardeningTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    /** A host event carrying markup in a numeric gauge is coerced to a number. */
    public function test_aggregation_coerces_malicious_host_gauges_to_numbers(): void
    {
        $projectId = $this->seedProject();

        $this->insertHostEvent($projectId, [
            'hostname' => 'web-1',
            'cpu' => '<script>alert(1)</script>',
            'memory' => ['used_percent' => '"><img src=x onerror=alert(1)>'],
            'load' => [0.0, '<script>evil()</script>', 0.0],
            'disk' => ['used_percent' => 42.5],
        ]);

        $this->artisan('warden:aggregate')->assertSuccessful();

        $meta = $this->app->make(DashboardRepository::class)->hostLatest($projectId, '24h');

        $this->assertNotNull($meta);

        // Markup gauges are dropped to null (view renders "—"); never a string.
        $this->assertFalse(is_string($meta['cpu'] ?? null), 'cpu must not be a string');
        $this->assertFalse(is_string($meta['mem'] ?? null), 'mem must not be a string');
        $this->assertFalse(is_string($meta['load'] ?? null), 'load must not be a string');
        $this->assertNull($meta['cpu'] ?? null);
        $this->assertNull($meta['mem'] ?? null);
        $this->assertNull($meta['load'] ?? null);

        // A legitimate numeric gauge survives as a number (regression).
        $this->assertIsNumeric($meta['disk'] ?? null);
        $this->assertEqualsWithDelta(42.5, $meta['disk'], 0.001);
    }

    /** Normal numeric host gauges keep flowing through aggregation. */
    public function test_aggregation_keeps_normal_host_gauges(): void
    {
        $projectId = $this->seedProject();

        $this->insertHostEvent($projectId, [
            'hostname' => 'web-1',
            'cpu' => 12.5,
            'memory' => ['used_percent' => 63.0],
            'load' => [0.1, 0.4, 0.2],
            'disk' => ['used_percent' => 71.0],
        ]);

        $this->artisan('warden:aggregate')->assertSuccessful();

        $meta = $this->app->make(DashboardRepository::class)->hostLatest($projectId, '24h');

        $this->assertNotNull($meta);
        $this->assertEqualsWithDelta(12.5, $meta['cpu'], 0.001);
        $this->assertEqualsWithDelta(63.0, $meta['mem'], 0.001);
        $this->assertEqualsWithDelta(0.4, $meta['load'], 0.001);
        $this->assertEqualsWithDelta(71.0, $meta['disk'], 0.001);
    }

    /** AuditCommand drops a `javascript:` advisory link at ingestion. */
    public function test_audit_command_drops_non_http_advisory_links(): void
    {
        Process::fake([
            'composer audit*' => Process::result((string) json_encode([
                'advisories' => [
                    'vendor/pkg' => [[
                        'packageName' => 'vendor/pkg',
                        'title' => 'XSS in vendor/pkg',
                        'cve' => 'CVE-2024-0002',
                        'link' => 'javascript:alert(document.cookie)',
                        'severity' => 'high',
                        'affectedVersions' => '<1.0',
                    ]],
                ],
            ])),
            'npm audit*' => Process::result((string) json_encode([
                'vulnerabilities' => [
                    'lodash' => [
                        'name' => 'lodash', 'severity' => 'critical',
                        'via' => [['title' => 'Proto Pollution', 'url' => 'data:text/html,<script>alert(1)</script>']],
                        'range' => '<4.17.21',
                    ],
                ],
            ])),
        ]);

        // warden:audit only runs in child mode.
        config()->set('warden.mode', 'child');

        $this->artisan('warden:audit')->assertSuccessful();

        $payload = OutboxEntry::first()->batch['events'][0]['payload'];
        $links = array_column($payload['advisories'], 'link');

        foreach ($links as $link) {
            $this->assertNull($link, 'non-http(s) advisory link must be dropped to null');
        }
    }

    /** A legitimate http advisory link survives ingestion (regression). */
    public function test_audit_command_keeps_http_advisory_links(): void
    {
        Process::fake([
            'composer audit*' => Process::result((string) json_encode([
                'advisories' => [
                    'vendor/pkg' => [[
                        'packageName' => 'vendor/pkg',
                        'title' => 'SQLi in vendor/pkg',
                        'cve' => 'CVE-2024-0003',
                        'link' => 'https://example.test/advisory',
                        'severity' => 'high',
                        'affectedVersions' => '<1.0',
                    ]],
                ],
            ])),
            'npm audit*' => Process::result((string) json_encode(['vulnerabilities' => []])),
        ]);

        config()->set('warden.mode', 'child');

        $this->artisan('warden:audit')->assertSuccessful();

        $payload = OutboxEntry::first()->batch['events'][0]['payload'];
        $composer = array_values(array_filter(
            $payload['advisories'],
            fn ($a) => $a['ecosystem'] === 'composer',
        ));

        $this->assertSame('https://example.test/advisory', $composer[0]['link']);
    }

    /** The security section renders a malicious link as text, never as href. */
    public function test_security_view_does_not_emit_javascript_href(): void
    {
        $audit = (object) [
            'occurred_at' => now()->toDateTimeString(),
            'payload' => [
                'total' => 1,
                'counts' => ['high' => 1],
                'tools' => ['composer' => true],
                'advisories' => [[
                    'ecosystem' => 'composer',
                    'package' => 'vendor/pkg',
                    'severity' => 'high',
                    'title' => 'XSS in vendor/pkg',
                    'cve' => 'CVE-2024-0002',
                    'link' => 'javascript:alert(1)',
                    'affected' => '<1.0',
                ]],
            ],
        ];

        $html = view('warden::partials.sections.security', [
            'audit' => $audit,
            'project' => (object) ['id' => 1],
        ])->render();

        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        // The advisory title still shows (as plain text, no link).
        $this->assertStringContainsString('XSS in vendor/pkg', $html);
    }

    /** A normal http link in the security view stays clickable (regression). */
    public function test_security_view_keeps_http_links(): void
    {
        $audit = (object) [
            'occurred_at' => now()->toDateTimeString(),
            'payload' => [
                'total' => 1,
                'counts' => ['high' => 1],
                'tools' => ['composer' => true],
                'advisories' => [[
                    'ecosystem' => 'composer',
                    'package' => 'vendor/pkg',
                    'severity' => 'high',
                    'title' => 'SQLi in vendor/pkg',
                    'cve' => 'CVE-2024-0003',
                    'link' => 'https://example.test/advisory',
                    'affected' => '<1.0',
                ]],
            ],
        ];

        $html = view('warden::partials.sections.security', [
            'audit' => $audit,
            'project' => (object) ['id' => 1],
        ])->render();

        $this->assertStringContainsString('href="https://example.test/advisory"', $html);
    }

    private function seedProject(): int
    {
        return (int) DB::table('wdn_projects')->insertGetId([
            'name' => 'Demo',
            'slug' => 'demo',
            'token' => 'tok-'.bin2hex(random_bytes(4)),
            'secret' => 'secret',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function insertHostEvent(int $projectId, array $payload): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => 'host',
            'trace_id' => bin2hex(random_bytes(8)),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'occurred_date' => now()->toDateString(),
            'received_at' => now(),
            'duration_us' => 0,
            'payload' => (string) json_encode($payload),
        ]);
    }
}
