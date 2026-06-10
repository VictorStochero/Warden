<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DashboardDrillDownTest extends TestCase
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

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function event(int $projectId, string $type, array $payload): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $projectId,
            'type' => $type,
            'trace_id' => str_repeat('a', 32),
            'occurred_at' => now(),
            'occurred_date' => now()->toDateString(),
            'duration_us' => 1234,
            'payload' => json_encode($payload),
        ]);
    }

    private function section(string $section): TestResponse
    {
        return $this->get(route('warden.project.section', ['project' => 'demo', 'section' => $section]));
    }

    public function test_logs_section_shows_the_message(): void
    {
        $p = $this->project();
        $this->event($p->id, 'log', ['level' => 'error', 'message' => 'Something broke in checkout']);

        $this->section('logs')->assertOk()->assertSee('Something broke in checkout');
    }

    public function test_logs_section_filters_by_level(): void
    {
        $p = $this->project();
        $this->event($p->id, 'log', ['level' => 'error', 'message' => 'boom error here']);
        $this->event($p->id, 'log', ['level' => 'info', 'message' => 'just info noise']);

        $this->get(route('warden.project.section', ['project' => 'demo', 'section' => 'logs', 'level' => 'error']))
            ->assertOk()
            ->assertSee('boom error here')
            ->assertDontSee('just info noise');
    }

    public function test_mail_section_shows_subject_and_recipient(): void
    {
        $p = $this->project();
        $this->event($p->id, 'mail', ['subject' => 'Welcome aboard', 'to' => ['user@example.com'], 'mailer' => 'array']);

        $this->section('mail')->assertOk()->assertSee('Welcome aboard')->assertSee('user@example.com');
    }

    public function test_http_section_shows_url_and_status(): void
    {
        $p = $this->project();
        $this->event($p->id, 'http', ['method' => 'GET', 'url' => 'https://api.example.com/v1/users', 'host' => 'api.example.com', 'status' => 503]);

        $this->section('http')->assertOk()->assertSee('https://api.example.com/v1/users')->assertSee('503');
    }

    public function test_jobs_section_shows_class_and_error(): void
    {
        $p = $this->project();
        $this->event($p->id, 'job', ['status' => 'failed', 'class' => 'App\\Jobs\\SendInvoice', 'error' => 'Timeout talking to gateway']);

        $this->section('jobs')->assertOk()->assertSee('SendInvoice')->assertSee('Timeout talking to gateway');
    }

    public function test_schedule_section_shows_task_and_status(): void
    {
        $p = $this->project();
        $this->event($p->id, 'schedule', ['task' => 'backup:run', 'status' => 'failed', 'expression' => '0 3 * * *', 'error' => 'disk full']);

        $this->section('schedule')->assertOk()->assertSee('backup:run')->assertSee('disk full');
    }

    public function test_errors_section_lists_5xx_requests(): void
    {
        $p = $this->project();
        $this->event($p->id, 'request', ['method' => 'GET', 'path' => '/checkout', 'route' => 'checkout', 'status' => 500]);

        $this->section('errors')->assertOk()
            ->assertSee('Recent 5xx requests')
            ->assertSee('checkout');
    }

    public function test_uptime_section_shows_windows_and_downtime(): void
    {
        $p = $this->project();
        Incident::create([
            'project_id' => $p->id, 'subject' => 'heartbeat:x', 'severity' => 'critical',
            'status' => 'open', 'started_at' => now()->subHours(2), 'summary' => 'No heartbeat for x',
        ]);

        $this->section('uptime')->assertOk()
            ->assertSee('Downtime episodes')
            ->assertSee('No heartbeat for x');
    }

    public function test_security_section_shows_vulnerabilities(): void
    {
        $p = $this->project();
        $this->event($p->id, 'security', [
            'generated_at' => now()->toIso8601String(),
            'tools' => ['composer' => true, 'npm' => false],
            'counts' => ['high' => 1],
            'total' => 1,
            'advisories' => [[
                'ecosystem' => 'composer', 'package' => 'vendor/pkg', 'severity' => 'high',
                'title' => 'SQL injection in vendor/pkg', 'cve' => 'CVE-2024-0001',
                'link' => 'https://example.test/a', 'affected' => '<1.2.3',
            ]],
        ]);

        $this->section('security')->assertOk()
            ->assertSee('vendor/pkg')
            ->assertSee('SQL injection in vendor/pkg');
    }
}
