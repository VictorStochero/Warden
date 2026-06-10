<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Gate;
use Mockery;
use VictorStochero\Warden\Alerting\Channels\MailAlertChannel;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class AlertEmailConfigTest extends TestCase
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
        // Drop the legacy config fallback so tests resolve purely from the DB.
        config()->set('warden.alerts.mail.to', []);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    private function incident(int $projectId, string $severity = 'critical'): Incident
    {
        return Incident::create([
            'project_id' => $projectId,
            'subject' => 'issue:abc',
            'severity' => $severity,
            'status' => 'open',
            'started_at' => now(),
            'summary' => 'Boom happened',
        ]);
    }

    /**
     * Build a Mailer mock that records the raw body and the resolved recipients.
     * The callback is invoked against a Message double that captures ->to().
     *
     * @param  list<string>  $recipients
     */
    private function captureSend(?string &$body, array &$recipients): Mailer
    {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('to')->andReturnUsing(function ($to) use (&$recipients, $message) {
            $recipients = is_array($to) ? array_values($to) : [$to];

            return $message;
        });
        $message->shouldReceive('subject')->andReturnSelf();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->andReturnUsing(function ($text, $callback) use (&$body, $message) {
            $body = (string) $text;
            $callback($message);

            return null;
        });

        return $mailer;
    }

    public function test_global_settings_persist_from_the_dashboard(): void
    {
        $this->post(route('warden.admin.settings.update'), [
            'email_enabled' => '1',
            'recipients' => "ops@example.com, oncall@example.com\nnight@example.com",
            'min_severity' => 'critical',
            'cooldown' => 600,
        ])->assertRedirect(route('warden.admin.settings'));

        $settings = AlertSetting::current();
        $this->assertTrue($settings->email_enabled);
        $this->assertSame(['ops@example.com', 'oncall@example.com', 'night@example.com'], $settings->recipients);
        $this->assertSame('critical', $settings->min_severity);
        $this->assertSame(600, $settings->cooldown);
    }

    public function test_project_override_persists_from_the_edit_form(): void
    {
        $project = $this->project();

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'off',
            'uptime_window' => '30d',
            'alert_override' => '1',
            'alert_email_enabled' => '1',
            'alert_recipients' => 'team@example.com',
            'alert_min_severity' => 'info',
        ])->assertRedirect(route('warden.admin.projects'));

        $fresh = $project->fresh();
        $this->assertTrue($fresh->alert_email_enabled);
        $this->assertSame(['team@example.com'], $fresh->alert_recipients);
        $this->assertSame('info', $fresh->alert_min_severity);
    }

    public function test_unchecking_override_nulls_the_columns_so_global_is_inherited(): void
    {
        $project = Project::create([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true,
            'alert_email_enabled' => true, 'alert_recipients' => ['x@example.com'], 'alert_min_severity' => 'critical',
        ]);

        $this->post(route('warden.admin.projects.update', $project->id), [
            'name' => 'Demo',
            'audit_frequency' => 'off',
            'uptime_window' => '30d',
            // no alert_override -> inherit global
        ])->assertRedirect(route('warden.admin.projects'));

        $fresh = $project->fresh();
        $this->assertNull($fresh->alert_email_enabled);
        $this->assertNull($fresh->alert_recipients);
        $this->assertNull($fresh->alert_min_severity);
    }

    public function test_sends_when_enabled_and_severity_meets_minimum(): void
    {
        $project = $this->project();
        $settings = AlertSetting::current();
        $settings->forceFill(['email_enabled' => true, 'recipients' => ['ops@example.com'], 'min_severity' => 'warning'])->save();

        $body = null;
        $recipients = [];
        $mailer = $this->captureSend($body, $recipients);
        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident($project->id, 'critical'), 'opened');

        $this->assertNotNull($body);
        $this->assertStringContainsString('critical', (string) $body);
        $this->assertSame(['ops@example.com'], $recipients);
    }

    public function test_does_not_send_when_disabled(): void
    {
        $project = $this->project();
        AlertSetting::current()->forceFill(['email_enabled' => false, 'recipients' => ['ops@example.com']])->save();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldNotReceive('raw');

        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident($project->id), 'opened');
        $this->assertFalse(AlertSetting::current()->email_enabled);
    }

    public function test_does_not_send_without_recipients(): void
    {
        $project = $this->project();
        AlertSetting::current()->forceFill(['email_enabled' => true, 'recipients' => []])->save();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldNotReceive('raw');

        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident($project->id), 'opened');
        $this->assertSame([], AlertSetting::current()->recipients);
    }

    public function test_does_not_send_when_severity_below_minimum(): void
    {
        $project = $this->project();
        AlertSetting::current()->forceFill(['email_enabled' => true, 'recipients' => ['ops@example.com'], 'min_severity' => 'critical'])->save();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldNotReceive('raw');

        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident($project->id, 'warning'), 'opened');
        $this->assertSame('critical', AlertSetting::current()->min_severity);
    }

    public function test_project_override_supersedes_global(): void
    {
        // Global is disabled; the project override enables and lowers the floor.
        AlertSetting::current()->forceFill(['email_enabled' => false, 'recipients' => ['global@example.com'], 'min_severity' => 'critical'])->save();

        $project = Project::create([
            'name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true,
            'alert_email_enabled' => true, 'alert_recipients' => ['proj@example.com'], 'alert_min_severity' => 'info',
        ]);

        $body = null;
        $recipients = [];
        $mailer = $this->captureSend($body, $recipients);
        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident($project->id, 'warning'), 'opened');

        // Sent despite global disabled + warning < critical (global), using the
        // project's own recipients.
        $this->assertNotNull($body);
        $this->assertSame(['proj@example.com'], $recipients);
    }
}
