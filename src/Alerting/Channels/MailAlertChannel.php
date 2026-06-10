<?php

namespace VictorStochero\Warden\Alerting\Channels;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Throwable;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;

/**
 * Opt-in e-mail channel. Sends incident transitions through the parent app's
 * configured mailer (config/mail.php). Best-effort: never throws, so a mail
 * misconfiguration can't break the evaluate run (§18.3).
 *
 * Configuration is resolved from the database (Settings -> Alerts) — a single
 * global row, optionally overridden per project on the incident's project. The
 * channel is registered unconditionally and self-silences when disabled, has no
 * recipients, or the incident severity is below the resolved minimum.
 */
class MailAlertChannel implements AlertChannel
{
    /** Severity ranking used to compare against the resolved minimum. */
    private const SEVERITY_RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    public function __construct(
        protected Mailer $mailer,
        protected Repository $config,
    ) {}

    public function send(Incident $incident, string $event): void
    {
        $global = AlertSetting::current();
        $project = Project::query()->find($incident->project_id);

        if (! $this->enabled($global, $project)) {
            return;
        }

        $recipients = $this->recipients($global, $project);
        if ($recipients === []) {
            return;
        }

        if (! $this->severityAllowed($incident->severity, $this->minSeverity($global, $project))) {
            return;
        }

        $subject = "[Warden] incident {$event}: {$incident->subject}";
        $body = implode("\n", [
            "Event:    {$event}",
            "Severity: {$incident->severity}",
            "Subject:  {$incident->subject}",
            'Summary:  '.(string) $incident->summary,
            'Project:  #'.$incident->project_id,
            'Started:  '.($incident->started_at?->toDateTimeString() ?? 'n/a'),
        ]);

        try {
            $this->mailer->raw($body, function (Message $message) use ($recipients, $subject): void {
                $message->to($recipients)->subject($subject);
            });
        } catch (Throwable) {
            // best-effort: a mailer failure must not break evaluate().
        }
    }

    /** Project override wins when set; otherwise the global toggle decides. */
    private function enabled(AlertSetting $global, ?Project $project): bool
    {
        if ($project !== null && $project->alert_email_enabled !== null) {
            return $project->alert_email_enabled;
        }

        return $global->email_enabled;
    }

    /**
     * Resolve recipients: a non-empty project override replaces the global list;
     * otherwise the global list (falling back to the legacy config list).
     *
     * @return list<string>
     */
    private function recipients(AlertSetting $global, ?Project $project): array
    {
        if ($project !== null) {
            $override = $this->clean($project->alert_recipients);
            if ($override !== []) {
                return $override;
            }
        }

        $resolved = $this->clean($global->recipients);
        if ($resolved !== []) {
            return $resolved;
        }

        return $this->clean(Cast::arr($this->config->get('warden.alerts.mail.to', [])));
    }

    /** Project override wins when set; otherwise the global minimum severity. */
    private function minSeverity(AlertSetting $global, ?Project $project): string
    {
        if ($project !== null && $project->alert_min_severity !== null && $project->alert_min_severity !== '') {
            return $project->alert_min_severity;
        }

        return $global->min_severity;
    }

    private function severityAllowed(string $severity, string $minimum): bool
    {
        $rank = self::SEVERITY_RANK[$severity] ?? 0;
        $floor = self::SEVERITY_RANK[$minimum] ?? self::SEVERITY_RANK['warning'];

        return $rank >= $floor;
    }

    /**
     * Normalise a recipients value (array or NULL) to a clean list of addresses.
     *
     * @param  array<array-key, mixed>|null  $value
     * @return list<string>
     */
    private function clean(?array $value): array
    {
        $out = [];
        foreach ($value ?? [] as $address) {
            $address = trim(Cast::str($address));
            if ($address !== '') {
                $out[] = $address;
            }
        }

        return $out;
    }
}
