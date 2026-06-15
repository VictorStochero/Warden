<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Alerting\Channels\DiscordAlertChannel;
use VictorStochero\Warden\Alerting\Channels\SlackAlertChannel;
use VictorStochero\Warden\Alerting\Channels\WebhookAlertChannel;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Chat/webhook alert channels (§5.5): Slack, Discord and a generic JSON webhook.
 * Each is config-driven (a webhook URL), self-silences when unconfigured or when
 * the incident is below its severity floor, and is best-effort — a delivery
 * failure can never break the evaluate run.
 */
class AlertWebhookChannelTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function incident(string $severity = 'critical'): Incident
    {
        return new Incident([
            'project_id' => 1,
            'subject' => 'Database unreachable',
            'summary' => 'SQLSTATE connection refused',
            'severity' => $severity,
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    public function test_slack_posts_a_formatted_payload_to_the_configured_webhook(): void
    {
        Http::fake();
        config()->set('warden.alerts.slack.webhook_url', 'https://hooks.slack.test/T/B/xxx');

        $this->app->make(SlackAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.test/T/B/xxx'
                && str_contains((string) json_encode($request->data()), 'Database unreachable');
        });
    }

    public function test_discord_posts_to_its_configured_webhook(): void
    {
        Http::fake();
        config()->set('warden.alerts.discord.webhook_url', 'https://discord.test/api/webhooks/1/x');

        $this->app->make(DiscordAlertChannel::class)->send($this->incident(), 'resolved');

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/api/webhooks/1/x');
    }

    public function test_generic_webhook_posts_a_structured_json_body(): void
    {
        Http::fake();
        config()->set('warden.alerts.webhook.url', 'https://ops.test/hook');

        $this->app->make(WebhookAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://ops.test/hook'
                && ($data['event'] ?? null) === 'opened'
                && ($data['severity'] ?? null) === 'critical'
                && ($data['subject'] ?? null) === 'Database unreachable';
        });
    }

    public function test_channel_is_silent_without_a_webhook_url(): void
    {
        Http::fake();
        config()->set('warden.alerts.slack.webhook_url', '');

        $this->app->make(SlackAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertNothingSent();
    }

    public function test_channel_respects_its_minimum_severity_floor(): void
    {
        Http::fake();
        config()->set('warden.alerts.slack.webhook_url', 'https://hooks.slack.test/x');
        config()->set('warden.alerts.slack.min_severity', 'critical');

        // 'warning' is below the 'critical' floor → suppressed.
        $this->app->make(SlackAlertChannel::class)->send($this->incident('warning'), 'opened');

        Http::assertNothingSent();
    }

    public function test_delivery_never_throws_when_the_webhook_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('network down'));
        config()->set('warden.alerts.webhook.url', 'https://ops.test/hook');

        // Must swallow the failure — evaluate() cannot be broken by an alert sink.
        $this->app->make(WebhookAlertChannel::class)->send($this->incident(), 'opened');

        $this->assertTrue(true);
    }
}
