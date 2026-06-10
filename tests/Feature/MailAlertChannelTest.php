<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Mail\Mailer;
use Mockery;
use VictorStochero\Warden\Alerting\Channels\MailAlertChannel;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Tests\TestCase;

class MailAlertChannelTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function incident(string $severity = 'critical'): Incident
    {
        return Incident::create([
            'project_id' => 1,
            'subject' => 'issue:abc',
            'severity' => $severity,
            'status' => 'open',
            'started_at' => now(),
            'summary' => 'Boom happened',
        ]);
    }

    public function test_sends_email_to_configured_recipients(): void
    {
        // Enabling is DB-driven now; the legacy config list still provides the
        // recipients when the global settings row has none.
        AlertSetting::current()
            ->forceFill(['email_enabled' => true, 'min_severity' => 'warning'])->save();
        config()->set('warden.alerts.mail.to', ['ops@example.com']);

        // Mockery adapts to the installed Mailer interface (version-proof). We
        // capture the raw body and do NOT invoke the callback, so the test never
        // builds an Illuminate\Mail\Message (which differs across versions).
        $body = null;
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->andReturnUsing(function ($text, $callback) use (&$body) {
            $body = (string) $text;

            return null;
        });

        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident(), 'opened');

        $this->assertStringContainsString('opened', (string) $body);
        $this->assertStringContainsString('critical', (string) $body);
    }

    public function test_does_nothing_without_recipients(): void
    {
        config()->set('warden.alerts.mail.to', []);

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldNotReceive('raw');

        (new MailAlertChannel($mailer, $this->app['config']))->send($this->incident('warning'), 'opened');

        // Mockery verifies shouldNotReceive on teardown; assert to avoid a risky test.
        $this->assertSame([], config('warden.alerts.mail.to'));
    }
}
