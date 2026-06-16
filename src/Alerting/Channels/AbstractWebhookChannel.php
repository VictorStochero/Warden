<?php

namespace VictorStochero\Warden\Alerting\Channels;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Http;
use Throwable;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

/**
 * Shared base for the chat/webhook alert channels (§5.5). Each subclass names a
 * config key (slack | discord | webhook) and shapes the JSON body; this base
 * resolves the webhook URL, gates on a severity floor, and POSTs best-effort.
 *
 * Two invariants matter here: the outbound POST runs inside withoutRecording so
 * a self-monitoring parent never observes its own alert traffic (§18.3), and the
 * call is wrapped in try/catch so a dead webhook can never break the evaluate
 * run (the same best-effort contract the e-mail channel honours).
 */
abstract class AbstractWebhookChannel implements AlertChannel
{
    /** Severity ranking used to compare against the resolved minimum. */
    private const SEVERITY_RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    public function __construct(
        protected Warden $observer,
        protected Repository $config,
    ) {}

    /** Config key under warden.alerts (e.g. 'slack' → warden.alerts.slack.*). */
    abstract protected function key(): string;

    /**
     * The JSON body to POST for this incident transition.
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(Incident $incident, string $event): array;

    public function send(Incident $incident, string $event): void
    {
        if (! $this->configured()) {
            return;
        }

        if (! $this->severityAllowed($incident->severity, $this->minSeverity())) {
            return;
        }

        $endpoint = $this->endpoint($incident, $event);

        if ($endpoint === '') {
            return;
        }

        // §18.3: suppress self-observation around the outbound alert POST so a
        // self-monitoring parent's HTTP recorder doesn't capture its own alerts.
        $this->observer->withoutRecording(function () use ($endpoint, $incident, $event): void {
            try {
                Http::withHeaders($this->headers())
                    ->timeout(5)
                    ->connectTimeout(3)
                    ->asJson()
                    ->post($endpoint, $this->payload($incident, $event));
            } catch (Throwable) {
                // Best-effort: an alert sink failure must not break evaluate().
            }
        });
    }

    /**
     * Whether this channel has enough configuration to deliver. The chat/webhook
     * channels are configured by a webhook URL; the paging channels override this
     * to check their credential (routing key / GenieKey) instead.
     */
    protected function configured(): bool
    {
        return $this->resolvedUrl() !== '';
    }

    /** The webhook URL configured under warden.alerts.{key}.webhook_url|url. */
    protected function resolvedUrl(): string
    {
        return trim(Cast::str(
            $this->config->get("warden.alerts.{$this->key()}.webhook_url")
            ?? $this->config->get("warden.alerts.{$this->key()}.url")
        ));
    }

    /**
     * The URL to POST to for this transition. Defaults to the configured webhook
     * URL; the paging channels override it with a fixed API endpoint (and may
     * vary it by event, e.g. Opsgenie's close-by-alias route on resolve).
     */
    protected function endpoint(Incident $incident, string $event): string
    {
        return $this->resolvedUrl();
    }

    /**
     * Extra request headers (e.g. Opsgenie's Authorization: GenieKey). Empty for
     * the plain webhook channels.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [];
    }

    protected function minSeverity(): string
    {
        return Cast::str($this->config->get("warden.alerts.{$this->key()}.min_severity", 'warning'), 'warning');
    }

    private function severityAllowed(string $severity, string $minimum): bool
    {
        $rank = self::SEVERITY_RANK[$severity] ?? 0;
        $floor = self::SEVERITY_RANK[$minimum] ?? self::SEVERITY_RANK['warning'];

        return $rank >= $floor;
    }

    /** One-line human summary shared by the chat channels. */
    protected function text(Incident $incident, string $event): string
    {
        $line = sprintf(
            '[Warden] incident %s — %s (severity: %s) · project #%d',
            $event,
            $incident->subject,
            $incident->severity,
            $incident->project_id,
        );

        $summary = trim((string) $incident->summary);

        return $summary !== '' ? $line."\n".$summary : $line;
    }
}
