<?php

namespace VictorStochero\Warden\Alerting\Channels;

use Illuminate\Support\Facades\Log;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Models\Incident;

/**
 * Default internal channel: writes the transition to the log. The context is
 * tagged warden=true so the Log recorder ignores it — the package never
 * observes its own alerts (§18.3).
 */
class LogAlertChannel implements AlertChannel
{
    public function send(Incident $incident, string $event): void
    {
        $level = match ($incident->severity) {
            'critical' => 'critical',
            'warning' => 'warning',
            default => 'info',
        };

        Log::log($level, "[warden] incident {$event}: {$incident->subject}", [
            'warden' => true,
            'project_id' => $incident->project_id,
            'severity' => $incident->severity,
            'summary' => $incident->summary,
        ]);
    }
}
