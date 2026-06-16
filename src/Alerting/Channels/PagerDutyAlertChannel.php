<?php

namespace VictorStochero\Warden\Alerting\Channels;

use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Support\Cast;

/**
 * PagerDuty channel (§5.5) using the Events API v2. An opened/reminder incident
 * triggers an alert and a resolved one resolves it, correlated by a stable
 * dedup_key so PagerDuty de-duplicates repeats. Configured by a routing (integration)
 * key under warden.alerts.pagerduty.routing_key; self-silences without one.
 */
class PagerDutyAlertChannel extends AbstractWebhookChannel
{
    private const ENDPOINT = 'https://events.pagerduty.com/v2/enqueue';

    protected function key(): string
    {
        return 'pagerduty';
    }

    protected function configured(): bool
    {
        return $this->routingKey() !== '';
    }

    protected function minSeverity(): string
    {
        return Cast::str($this->config->get('warden.alerts.pagerduty.min_severity', 'critical'), 'critical');
    }

    protected function endpoint(Incident $incident, string $event): string
    {
        return trim(Cast::str($this->config->get('warden.alerts.pagerduty.endpoint', self::ENDPOINT), self::ENDPOINT));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Incident $incident, string $event): array
    {
        return [
            'routing_key' => $this->routingKey(),
            'event_action' => $event === 'resolved' ? 'resolve' : 'trigger',
            'dedup_key' => 'warden-'.$incident->project_id.'-'.md5($incident->subject),
            'payload' => [
                'summary' => $this->text($incident, $event),
                'severity' => $this->pdSeverity($incident->severity),
                'source' => 'warden:project:'.$incident->project_id,
                'group' => $incident->subject,
            ],
        ];
    }

    private function routingKey(): string
    {
        return trim(Cast::str($this->config->get('warden.alerts.pagerduty.routing_key')));
    }

    /** Map Warden severities to PagerDuty's accepted set. */
    private function pdSeverity(string $severity): string
    {
        return in_array($severity, ['critical', 'error', 'warning', 'info'], true) ? $severity : 'warning';
    }
}
