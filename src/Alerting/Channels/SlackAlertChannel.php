<?php

namespace VictorStochero\Warden\Alerting\Channels;

use VictorStochero\Warden\Models\Incident;

/**
 * Slack incoming-webhook channel (§5.5). Posts a plain `{"text": …}` payload to
 * warden.alerts.slack.webhook_url; registered unconditionally and self-silences
 * when no URL is configured.
 */
class SlackAlertChannel extends AbstractWebhookChannel
{
    protected function key(): string
    {
        return 'slack';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Incident $incident, string $event): array
    {
        return ['text' => $this->text($incident, $event)];
    }
}
