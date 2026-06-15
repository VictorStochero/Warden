<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Audit log (§5.7): every successful manage action in the dashboard leaves an
 * accountability trail — who did what — without touching each controller.
 */
class AuditLogTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    public function test_a_manage_action_is_recorded(): void
    {
        $this->post(route('warden.admin.projects.store'), ['name' => 'New App']);

        $entry = AuditLog::query()->where('action', 'warden.admin.projects.store')->first();

        $this->assertNotNull($entry, 'A manage POST must leave an audit entry');
        $this->assertSame('POST', $entry->method);
        $this->assertNotEmpty($entry->actor);
    }

    public function test_read_requests_are_not_audited(): void
    {
        $this->get(route('warden.admin.projects'))->assertOk();

        $this->assertSame(0, AuditLog::query()->count(), 'GETs are not manage actions');
    }

    public function test_audit_page_lists_the_trail(): void
    {
        AuditLog::create([
            'actor' => 'ana@team.test',
            'action' => 'warden.admin.projects.store',
            'target' => 'demo',
            'method' => 'POST',
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->get(route('warden.admin.audit'))
            ->assertOk()
            ->assertSee('ana@team.test')
            ->assertSee('warden.admin.projects.store');
    }
}
