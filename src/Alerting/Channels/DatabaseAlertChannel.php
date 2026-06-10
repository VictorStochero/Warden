<?php

namespace VictorStochero\Warden\Alerting\Channels;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Models\Incident;

/**
 * Default internal channel: the incident row itself is the delivery (it surfaces
 * in the dashboard). Here we stamp the delivery time and append a lightweight
 * audit trail to the incident's meta. No external service involved (RNF-3).
 */
class DatabaseAlertChannel implements AlertChannel
{
    public function send(Incident $incident, string $event): void
    {
        $meta = $incident->meta ?? [];
        $deliveries = isset($meta['deliveries']) && is_array($meta['deliveries']) ? $meta['deliveries'] : [];
        $deliveries[] = ['channel' => 'database', 'event' => $event, 'at' => Carbon::now()->toIso8601String()];
        $meta['deliveries'] = $deliveries;

        $incident->forceFill([
            'meta' => $meta,
            'last_alerted_at' => Carbon::now(),
        ])->save();
    }
}
