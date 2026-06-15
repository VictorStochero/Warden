<?php

namespace VictorStochero\Warden\Alerting\Channels;

use VictorStochero\Warden\Models\Incident;

/**
 * Generic JSON webhook channel (§5.5) — the vendor-neutral escape hatch for
 * PagerDuty/Opsgenie/Zapier/n8n or any HTTP sink. POSTs a structured incident
 * body to warden.alerts.webhook.url; self-silences when no URL is configured.
 */
class WebhookAlertChannel extends AbstractWebhookChannel
{
    protected function key(): string
    {
        return 'webhook';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Incident $incident, string $event): array
    {
        return [
            'event' => $event,
            'severity' => $incident->severity,
            'subject' => $incident->subject,
            'summary' => $incident->summary,
            'status' => $incident->status,
            'project_id' => $incident->project_id,
            'started_at' => $incident->started_at?->toIso8601String(),
        ];
    }
}
