<?php

namespace VictorStochero\Warden\Alerting\Channels;

use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Support\Cast;

/**
 * Opsgenie channel (§5.5) using the Alerts API. An opened/reminder incident
 * creates an alert keyed by a stable alias; a resolved one closes that alias.
 * Authenticated with a GenieKey header from warden.alerts.opsgenie.api_key;
 * self-silences without one.
 */
class OpsgenieAlertChannel extends AbstractWebhookChannel
{
    private const ENDPOINT = 'https://api.opsgenie.com/v2/alerts';

    protected function key(): string
    {
        return 'opsgenie';
    }

    protected function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    protected function minSeverity(): string
    {
        return Cast::str($this->config->get('warden.alerts.opsgenie.min_severity', 'critical'), 'critical');
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['Authorization' => 'GenieKey '.$this->apiKey()];
    }

    protected function endpoint(Incident $incident, string $event): string
    {
        $base = trim(Cast::str($this->config->get('warden.alerts.opsgenie.endpoint', self::ENDPOINT), self::ENDPOINT));

        if ($event === 'resolved') {
            return $base.'/'.rawurlencode($this->alias($incident)).'/close?identifierType=alias';
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Incident $incident, string $event): array
    {
        // Closing an alert takes no body; a note keeps the timeline readable.
        if ($event === 'resolved') {
            return ['note' => 'Resolved by Warden'];
        }

        return [
            'message' => $this->text($incident, $event),
            'alias' => $this->alias($incident),
            'priority' => $this->priority($incident->severity),
            'description' => trim((string) $incident->summary),
        ];
    }

    private function apiKey(): string
    {
        return trim(Cast::str($this->config->get('warden.alerts.opsgenie.api_key')));
    }

    /** A deduplicating alias so repeats fold into one alert and resolve closes it. */
    private function alias(Incident $incident): string
    {
        return 'warden-'.$incident->project_id.'-'.md5($incident->subject);
    }

    /** Map Warden severity to an Opsgenie priority (P1 highest … P5 lowest). */
    private function priority(string $severity): string
    {
        return match ($severity) {
            'critical' => 'P1',
            'warning' => 'P3',
            default => 'P5',
        };
    }
}
