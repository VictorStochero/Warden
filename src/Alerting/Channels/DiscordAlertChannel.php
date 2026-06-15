<?php

namespace VictorStochero\Warden\Alerting\Channels;

use VictorStochero\Warden\Models\Incident;

/**
 * Discord webhook channel (§5.5). Posts a `{"content": …}` payload to
 * warden.alerts.discord.webhook_url; self-silences when no URL is configured.
 */
class DiscordAlertChannel extends AbstractWebhookChannel
{
    protected function key(): string
    {
        return 'discord';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Incident $incident, string $event): array
    {
        return ['content' => $this->text($incident, $event)];
    }
}
