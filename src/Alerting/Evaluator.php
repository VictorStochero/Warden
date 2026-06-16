<?php

namespace VictorStochero\Warden\Alerting;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\AlertChannel;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\AlertRule;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Models\Heartbeat;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Warden;

/**
 * Turns state into incidents and fires internal alerts (M8):
 *
 *  - Issues: a freshly opened/reopened issue opens an incident; resolving or
 *    ignoring it resolves the incident.
 *  - Heartbeats: registered from scheduled-task events; a key silent beyond
 *    expected_interval + grace opens an incident, and recovers when it beats
 *    again — this is how a dead scheduler is detected (§15).
 *
 * Alerts respect a per-subject cooldown and run suppressed (§18.3).
 */
class Evaluator
{
    public function __construct(
        protected Warden $observer,
        protected Container $app,
        protected Repository $config,
    ) {}

    /**
     * Open incidents for the project under evaluation, keyed by subject — loaded
     * once per run so open/resolve are in-memory lookups, not a SELECT per issue.
     *
     * @var array<string, Incident>
     */
    protected array $openIncidents = [];

    protected ?AlertSetting $alertSetting = null;

    public function evaluate(int $projectId): void
    {
        $this->observer->withoutRecording(function () use ($projectId) {
            $this->loadState($projectId);
            $this->registerHeartbeats($projectId);
            $this->evaluateHeartbeats($projectId);
            $this->evaluateIssues($projectId);
            $this->evaluateRules($projectId);
        });
    }

    /** Preload the per-run state both issue and heartbeat evaluation rely on. */
    protected function loadState(int $projectId): void
    {
        /** @var array<string, Incident> $bySubject */
        $bySubject = Incident::query()
            ->where('project_id', $projectId)
            ->where('status', 'open')
            ->get()
            ->keyBy('subject')
            ->all();

        $this->openIncidents = $bySubject;
        $this->alertSetting = AlertSetting::current();
    }

    // --------------------------------------------------------- heartbeats

    protected function registerHeartbeats(int $projectId): void
    {
        $db = Schema::db();

        $events = $db->table('wdn_events')
            ->where('project_id', $projectId)
            ->where('type', 'schedule')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['payload', 'occurred_at']);

        /** @var array<string, list<int>> $seen */
        $seen = [];
        foreach ($events as $event) {
            $payload = Json::decode($event->payload ?? null);
            $key = $payload['heartbeat'] ?? null;
            if (! is_string($key)) {
                continue;
            }
            $seen[$key][] = Carbon::parse(Cast::str($event->occurred_at))->getTimestamp();
        }

        foreach ($seen as $key => $timestamps) {
            rsort($timestamps);
            $lastSeen = Carbon::createFromTimestamp($timestamps[0]);

            // Infer the cadence from the *median* positive gap between runs. The
            // median is robust to outliers: an occasional bunched pair (e.g. a
            // manual `schedule:run` next to the cron) no longer collapses the
            // interval and triggers false "missed" heartbeats.
            $gaps = [];
            for ($i = 1; $i < count($timestamps); $i++) {
                $gap = $timestamps[$i - 1] - $timestamps[$i];
                if ($gap > 0) {
                    $gaps[] = $gap;
                }
            }
            $interval = $this->median($gaps);

            $heartbeat = Heartbeat::query()->firstOrNew(['project_id' => $projectId, 'key' => $key]);
            $heartbeat->expected_interval = $interval ?? $heartbeat->expected_interval ?? 3600;
            $heartbeat->grace = $heartbeat->grace ?: 60;
            if ($heartbeat->last_seen_at === null || $lastSeen->gt($heartbeat->last_seen_at)) {
                $heartbeat->last_seen_at = $lastSeen;
            }
            $heartbeat->save();
        }
    }

    /** @param list<int> $values */
    protected function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    protected function evaluateHeartbeats(int $projectId): void
    {
        foreach (Heartbeat::query()->where('project_id', $projectId)->get() as $heartbeat) {
            if ($heartbeat->last_seen_at === null) {
                continue;
            }

            $deadline = $heartbeat->last_seen_at->copy()
                ->addSeconds($heartbeat->expected_interval + $heartbeat->grace);

            $missed = Carbon::now()->gt($deadline);
            $subject = 'heartbeat:'.$heartbeat->key;

            if ($missed && ! $heartbeat->alerted) {
                $this->openIncident($projectId, $subject, 'critical',
                    "No heartbeat for '{$heartbeat->key}' since {$heartbeat->last_seen_at->toDateTimeString()}");
                $heartbeat->forceFill(['alerted' => true])->save();
            } elseif (! $missed && $heartbeat->alerted) {
                $this->resolveIncident($projectId, $subject);
                $heartbeat->forceFill(['alerted' => false])->save();
            }
        }
    }

    // ------------------------------------------------------------- issues

    protected function evaluateIssues(int $projectId): void
    {
        // Open issues without an open incident -> open + alert. A snoozed issue
        // stays open but is muted until its window passes (§5.3), so it raises
        // no fresh incident in the meantime.
        $open = Issue::query()
            ->where('project_id', $projectId)
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', Carbon::now()))
            ->get();

        foreach ($open as $issue) {
            $this->openIncident($projectId, 'issue:'.$issue->fingerprint, $this->severityFor($issue),
                "{$issue->class}: ".mb_substr((string) $issue->message, 0, 140), [
                    'issue_id' => $issue->id,
                    'count' => $issue->count,
                ]);
        }

        // Issues no longer open -> resolve their incident.
        $closed = Issue::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['resolved', 'ignored'])
            ->get();

        foreach ($closed as $issue) {
            $this->resolveIncident($projectId, 'issue:'.$issue->fingerprint);
        }
    }

    // --------------------------------------------------------------- rules

    /**
     * Configurable threshold rules (§5.5): each compares a KPI over a window
     * against a threshold and opens/resolves a `rule:<name>` incident through the
     * same channel pipeline. Empty by default — inert until the host defines
     * warden.alerts.rules.
     */
    protected function evaluateRules(int $projectId): void
    {
        $rules = $this->rules();

        if ($rules === []) {
            return;
        }

        $repo = $this->app->make(DashboardRepository::class);

        foreach ($rules as $rule) {
            $name = trim(Cast::str($rule['name'] ?? ''));
            $metric = trim(Cast::str($rule['metric'] ?? ''));

            if ($name === '' || $metric === '') {
                continue;
            }

            $window = Cast::str($rule['window'] ?? '1h', '1h');
            $op = Cast::str($rule['op'] ?? '>', '>');
            $threshold = (float) Cast::str($rule['threshold'] ?? '0', '0');
            $subject = 'rule:'.$name;

            // Anomaly rules compare the latest window against a moving baseline of
            // the preceding windows, rather than against a fixed threshold (§5.5).
            if ($op === 'anomaly') {
                $this->evaluateAnomaly($repo, $projectId, $name, $metric, $threshold, Cast::str($rule['severity'] ?? 'warning', 'warning'), $window);

                continue;
            }

            $value = $this->metricValue($repo->kpis($projectId, $window), $metric);

            if ($value === null) {
                continue;
            }

            if ($this->breached($value, $op, $threshold)) {
                $this->openIncident(
                    $projectId,
                    $subject,
                    Cast::str($rule['severity'] ?? 'warning', 'warning'),
                    sprintf('%s %s %s over %s (now %s)', $metric, $op, $threshold, $window, $value),
                    ['rule' => $name, 'metric' => $metric, 'value' => $value, 'op' => $op, 'threshold' => $threshold, 'window' => $window],
                );
            } else {
                $this->resolveIncident($projectId, $subject);
            }
        }
    }

    /**
     * The threshold rules to evaluate: config-defined (warden.alerts.rules)
     * merged with the enabled UI-managed rules (wdn_alert_rules).
     *
     * @return list<array<array-key, mixed>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach (Cast::arr($this->config->get('warden.alerts.rules', [])) as $rule) {
            if (is_array($rule)) {
                $rules[] = $rule;
            }
        }

        foreach (AlertRule::query()->where('enabled', true)->get() as $rule) {
            $rules[] = [
                'name' => $rule->name,
                'metric' => $rule->metric,
                'op' => $rule->op,
                'threshold' => $rule->threshold,
                'window' => $rule->window,
                'severity' => $rule->severity,
            ];
        }

        return $rules;
    }

    /** Minimum baseline windows required before an anomaly rule can fire. */
    protected const ANOMALY_MIN_SAMPLES = 5;

    /**
     * Anomaly evaluation (§5.5): build a per-bucket series for the metric, treat
     * the latest bucket as "current" and the preceding ones as the baseline, and
     * open/resolve the rule's incident when the current value sits more than
     * `$sigmas` standard deviations above the baseline mean. Only request-derived
     * metrics (throughput/errors/p95/error_rate) are supported; others no-op.
     */
    protected function evaluateAnomaly(DashboardRepository $repo, int $projectId, string $name, string $metric, float $sigmas, string $severity, string $window): void
    {
        $subject = 'rule:'.$name;
        $sigmas = $sigmas > 0 ? $sigmas : 3.0;

        $points = [];
        foreach ($repo->requestSeries($projectId, $window) as $point) {
            $value = $this->metricFromSeriesPoint($point, $metric);
            if ($value !== null) {
                $points[] = ['bucket' => Cast::str($point['bucket']), 'value' => $value];
            }
        }

        usort($points, fn (array $a, array $b): int => strcmp($a['bucket'], $b['bucket']));
        $values = array_map(fn (array $p): float => $p['value'], $points);

        // Need the baseline samples plus the current bucket.
        if (count($values) < self::ANOMALY_MIN_SAMPLES + 1) {
            $this->resolveIncident($projectId, $subject);

            return;
        }

        /** @var float $current */
        $current = array_pop($values);

        if ($this->anomalyBreached($values, $current, $sigmas)) {
            $mean = array_sum($values) / count($values);
            $this->openIncident($projectId, $subject, $severity,
                sprintf('%s anomaly: %.2f vs baseline μ=%.2f (>%.1fσ)', $metric, $current, $mean, $sigmas),
                ['rule' => $name, 'metric' => $metric, 'value' => $current, 'baseline_mean' => round($mean, 2), 'sigmas' => $sigmas, 'window' => $window],
            );
        } else {
            $this->resolveIncident($projectId, $subject);
        }
    }

    /**
     * Whether `$current` is an upward anomaly against the baseline samples: more
     * than `$sigmas` standard deviations above the mean. A perfectly flat
     * baseline (σ≈0) falls back to a 50%-jump rule so a tiny bump doesn't read as
     * infinite sigmas. Only spikes above the mean count.
     *
     * @param  list<float>  $baseline
     */
    protected function anomalyBreached(array $baseline, float $current, float $sigmas): bool
    {
        $n = count($baseline);

        if ($n < self::ANOMALY_MIN_SAMPLES) {
            return false;
        }

        $mean = array_sum($baseline) / $n;

        if ($current <= $mean) {
            return false;
        }

        $variance = 0.0;
        foreach ($baseline as $x) {
            $variance += ($x - $mean) ** 2;
        }
        $sd = sqrt($variance / $n);

        if ($sd <= 1e-9) {
            return $current >= $mean * 1.5;
        }

        return (($current - $mean) / $sd) >= $sigmas;
    }

    /**
     * Extract a comparable numeric value for `$metric` from a request-series
     * bucket point, or null when the metric isn't request-derived.
     *
     * @param  array<string, mixed>  $point
     */
    protected function metricFromSeriesPoint(array $point, string $metric): ?float
    {
        $count = Cast::int($point['count'] ?? 0);
        $errors = Cast::int($point['errors'] ?? 0);

        return match ($metric) {
            'throughput' => (float) $count,
            'errors' => (float) $errors,
            'p95' => (float) Cast::int($point['p95'] ?? 0),
            'error_rate' => $count > 0 ? round($errors / $count * 100, 2) : 0.0,
            default => null,
        };
    }

    /**
     * Pull a comparable numeric KPI by name, or null when it isn't available
     * (e.g. p95 / cache hit-rate with no data) so the rule simply doesn't fire.
     *
     * @param  array<string, mixed>  $kpis
     */
    protected function metricValue(array $kpis, string $metric): ?float
    {
        $candidates = [
            'error_rate' => $kpis['error_rate'] ?? null,
            'p95' => $kpis['p95'] ?? null,
            'throughput' => $kpis['throughput'] ?? null,
            'errors' => $kpis['errors'] ?? null,
            'slow' => $kpis['slow'] ?? null,
            'failed_jobs' => $kpis['failed_jobs'] ?? null,
            'cache_hit_rate' => $kpis['cache_hit_rate'] ?? null,
        ];

        $value = $candidates[$metric] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    protected function breached(float $value, string $op, float $threshold): bool
    {
        return match ($op) {
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            default => $value > $threshold,
        };
    }

    protected function severityFor(Issue $issue): string
    {
        if ($issue->priority === 'high' || $issue->count >= 100) {
            return 'critical';
        }

        return 'warning';
    }

    // ---------------------------------------------------------- incidents

    /** @param array<string, mixed> $meta */
    protected function openIncident(int $projectId, string $subject, string $severity, string $summary, array $meta = []): void
    {
        $incident = $this->openIncidents[$subject] ?? null;

        if ($incident === null) {
            $incident = Incident::query()->create([
                'project_id' => $projectId,
                'subject' => $subject,
                'severity' => $severity,
                'status' => 'open',
                'started_at' => Carbon::now(),
                'summary' => $summary,
                'meta' => $meta,
            ]);

            $this->openIncidents[$subject] = $incident;
            $this->dispatch($incident, 'opened');

            return;
        }

        // Existing incident: re-alert only after the cooldown elapses. The
        // cooldown is resolved from the database (Settings -> Alerts) with the
        // config default as the fallback.
        $cooldown = $this->cooldown();
        $last = $incident->last_alerted_at;

        if ($last === null || Carbon::now()->diffInSeconds($last) >= $cooldown) {
            $this->dispatch($incident, 'reminder');
        }
    }

    protected function resolveIncident(int $projectId, string $subject): void
    {
        $incident = $this->openIncidents[$subject] ?? null;

        if ($incident === null) {
            return;
        }

        $incident->forceFill(['status' => 'resolved', 'resolved_at' => Carbon::now()])->save();
        unset($this->openIncidents[$subject]);
        $this->dispatch($incident, 'resolved');
    }

    /**
     * Seconds between repeat alerts for the same incident. Resolved from the
     * global alert settings row, falling back to the config default.
     */
    protected function cooldown(): int
    {
        $configured = Cast::int($this->config->get('warden.alerts.cooldown', 300), 300);
        $setting = $this->alertSetting ?? AlertSetting::current();

        return $setting->cooldown ?: $configured;
    }

    protected function dispatch(Incident $incident, string $event): void
    {
        foreach (Cast::arr($this->config->get('warden.alerts.channels', [])) as $class) {
            if (! is_string($class)) {
                continue;
            }

            $channel = $this->app->make($class);
            if ($channel instanceof AlertChannel) {
                $channel->send($incident, $event);
            }
        }
    }
}
