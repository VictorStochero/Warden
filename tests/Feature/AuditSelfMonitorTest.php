<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Process;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;

/**
 * A self-monitoring parent must be able to audit itself: the security snapshot
 * is recorded and self-delivered to the parent's own project (no child, no
 * outbox, no HTTP), so the Security tab is populated like every other section.
 */
class AuditSelfMonitorTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', true);
    }

    public function test_a_self_monitoring_parent_audits_itself_into_the_self_project(): void
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
            'npm audit*' => Process::result((string) json_encode(['vulnerabilities' => []])),
        ]);

        $project = $this->app->make(ProjectManager::class)->ensureSelfProject('parent');

        $this->artisan('warden:audit')->assertSuccessful();

        // Self-delivered straight to the local DB, attached to the self project.
        $this->assertSame(1, Event::where('project_id', $project->id)->where('type', 'security')->count());

        // Local delivery: nothing went through the outbox.
        $this->assertSame(0, OutboxEntry::count());
    }
}
