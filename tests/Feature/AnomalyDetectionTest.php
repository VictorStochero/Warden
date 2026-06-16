<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Baseline anomaly detection (§5.5): besides fixed thresholds, an `anomaly` rule
 * opens an incident when the latest window deviates more than N standard
 * deviations above the moving baseline of the preceding windows, and resolves
 * once it falls back into the band.
 */
class AnomalyDetectionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    /** Seed `count` request events into the per-minute bucket `minutesAgo` ago. */
    private function seedBucket(Project $project, int $minutesAgo, int $count): void
    {
        $at = now()->subMinutes($minutesAgo)->startOfMinute()->format('Y-m-d H:i:s.u');

        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $events[] = ['type' => 'request', 'trace_id' => uniqid('t', true), 'span_id' => uniqid('s', true), 'occurred_at' => $at, 'duration_us' => 1000, 'payload' => ['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 200]];
        }
        $this->app->make(Ingestor::class)->ingest($project->slug, [['id' => 'b'.uniqid('', true), 'events' => $events]]);
        $this->app->make(Aggregator::class)->rollup($project->id, 'request');
    }

    private function anomalyRule(float $sigmas = 3.0): void
    {
        $this->app['config']->set('warden.alerts.rules', [[
            'name' => 'traffic-spike',
            'metric' => 'throughput',
            'op' => 'anomaly',
            'threshold' => $sigmas,
            'window' => '24h',
            'severity' => 'warning',
        ]]);
    }

    public function test_a_spike_above_the_baseline_opens_an_incident(): void
    {
        $project = $this->project();

        // Steady baseline across six minutes, alternating 2/3 requests.
        foreach ([6, 5, 4, 3, 2, 1] as $i => $m) {
            $this->seedBucket($project, $m, $i % 2 === 0 ? 2 : 3);
        }
        // Current minute: a large spike.
        $this->seedBucket($project, 0, 50);

        $this->anomalyRule();
        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(1, Incident::query()
            ->where('project_id', $project->id)
            ->where('subject', 'rule:traffic-spike')
            ->where('status', 'open')
            ->count());
    }

    public function test_a_value_within_the_band_opens_nothing(): void
    {
        $project = $this->project();

        foreach ([6, 5, 4, 3, 2, 1] as $i => $m) {
            $this->seedBucket($project, $m, $i % 2 === 0 ? 2 : 3);
        }
        // Current minute in line with the baseline.
        $this->seedBucket($project, 0, 3);

        $this->anomalyRule();
        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::query()
            ->where('project_id', $project->id)
            ->where('subject', 'rule:traffic-spike')
            ->count());
    }

    public function test_not_enough_history_does_not_fire(): void
    {
        $project = $this->project();

        $this->seedBucket($project, 1, 2);
        $this->seedBucket($project, 0, 80); // a spike, but no baseline to compare against

        $this->anomalyRule();
        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::query()
            ->where('subject', 'rule:traffic-spike')
            ->count());
    }
}
