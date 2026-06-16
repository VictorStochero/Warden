<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Alerting\Channels\OpsgenieAlertChannel;
use VictorStochero\Warden\Alerting\Channels\PagerDutyAlertChannel;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Tests\TestCase;

/**
 * On-call paging channels (§5.5): PagerDuty Events API v2 and Opsgenie Alerts
 * API. Unlike the chat channels these hit a fixed endpoint with a credential
 * (routing key / GenieKey) rather than a user webhook URL, and map the
 * incident transition to the provider's trigger/resolve verbs. Best-effort and
 * suppressed, like every other channel.
 */
class PagerDutyOpsgenieChannelTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function incident(string $severity = 'critical'): Incident
    {
        return new Incident([
            'project_id' => 7,
            'subject' => 'Database unreachable',
            'summary' => 'SQLSTATE connection refused',
            'severity' => $severity,
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    public function test_pagerduty_triggers_with_an_events_v2_payload(): void
    {
        Http::fake();
        config()->set('warden.alerts.pagerduty.routing_key', 'R0UTING');

        $this->app->make(PagerDutyAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://events.pagerduty.com/v2/enqueue'
                && ($data['routing_key'] ?? null) === 'R0UTING'
                && ($data['event_action'] ?? null) === 'trigger'
                && ! empty($data['dedup_key'])
                && str_contains((string) ($data['payload']['summary'] ?? ''), 'Database unreachable');
        });
    }

    public function test_pagerduty_resolve_maps_to_the_resolve_action(): void
    {
        Http::fake();
        config()->set('warden.alerts.pagerduty.routing_key', 'R0UTING');

        $this->app->make(PagerDutyAlertChannel::class)->send($this->incident(), 'resolved');

        Http::assertSent(fn ($request) => ($request->data()['event_action'] ?? null) === 'resolve');
    }

    public function test_pagerduty_is_silent_without_a_routing_key(): void
    {
        Http::fake();
        config()->set('warden.alerts.pagerduty.routing_key', '');

        $this->app->make(PagerDutyAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertNothingSent();
    }

    public function test_opsgenie_triggers_with_a_geniekey_header(): void
    {
        Http::fake();
        config()->set('warden.alerts.opsgenie.api_key', 'GENIE');

        $this->app->make(OpsgenieAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.opsgenie.com/v2/alerts'
                && $request->hasHeader('Authorization', 'GenieKey GENIE')
                && ! empty($data['alias'])
                && ($data['priority'] ?? null) === 'P1'
                && str_contains((string) ($data['message'] ?? ''), 'Database unreachable');
        });
    }

    public function test_opsgenie_resolve_closes_the_alert_by_alias(): void
    {
        Http::fake();
        config()->set('warden.alerts.opsgenie.api_key', 'GENIE');

        $this->app->make(OpsgenieAlertChannel::class)->send($this->incident(), 'resolved');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/close')
            && str_contains($request->url(), 'identifierType=alias'));
    }

    public function test_opsgenie_is_silent_without_an_api_key(): void
    {
        Http::fake();
        config()->set('warden.alerts.opsgenie.api_key', '');

        $this->app->make(OpsgenieAlertChannel::class)->send($this->incident(), 'opened');

        Http::assertNothingSent();
    }

    public function test_paging_channels_respect_the_severity_floor(): void
    {
        Http::fake();
        config()->set('warden.alerts.pagerduty.routing_key', 'R0UTING');
        config()->set('warden.alerts.pagerduty.min_severity', 'critical');

        // 'warning' is below the default 'critical' floor → suppressed.
        $this->app->make(PagerDutyAlertChannel::class)->send($this->incident('warning'), 'opened');

        Http::assertNothingSent();
    }

    public function test_paging_delivery_never_throws_when_the_endpoint_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('network down'));
        config()->set('warden.alerts.pagerduty.routing_key', 'R0UTING');

        $this->app->make(PagerDutyAlertChannel::class)->send($this->incident(), 'opened');

        $this->assertTrue(true);
    }
}
