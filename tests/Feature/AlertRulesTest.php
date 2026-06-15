<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Configurable threshold alert rules (§5.5): a rule compares a KPI over a window
 * against a threshold and opens/resolves a `rule:<name>` incident through the
 * existing channel pipeline. Config-driven (UI management is a follow-up).
 */
class AlertRulesTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function seedRequests(int $n): Project
    {
        $project = Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );

        $at = now()->format('Y-m-d H:i:s.u');
        $events = [];
        for ($i = 0; $i < $n; $i++) {
            $events[] = ['type' => 'request', 'trace_id' => 't'.$i, 'span_id' => 's'.$i, 'occurred_at' => $at, 'duration_us' => 1000, 'payload' => ['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 200]];
        }
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b'.uniqid(), 'events' => $events]]);
        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        return $project;
    }

    private function rule(string $op, float $threshold): void
    {
        $this->app['config']->set('warden.alerts.rules', [[
            'name' => 'traffic',
            'metric' => 'throughput',
            'op' => $op,
            'threshold' => $threshold,
            'window' => '24h',
            'severity' => 'warning',
        ]]);
    }

    public function test_a_breached_rule_opens_an_incident(): void
    {
        $project = $this->seedRequests(3);
        $this->rule('>', 0);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(1, Incident::query()
            ->where('project_id', $project->id)
            ->where('subject', 'rule:traffic')
            ->where('status', 'open')
            ->count());
    }

    public function test_a_satisfied_rule_opens_nothing(): void
    {
        $project = $this->seedRequests(3);
        $this->rule('>', 1000);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::query()
            ->where('project_id', $project->id)
            ->where('subject', 'rule:traffic')
            ->count());
    }

    public function test_a_rule_resolves_its_incident_once_back_to_normal(): void
    {
        $project = $this->seedRequests(3);

        $this->rule('>', 0);
        $this->app->make(Evaluator::class)->evaluate($project->id);
        $this->assertSame('open', Incident::query()->where('subject', 'rule:traffic')->value('status'));

        // Threshold lifted out of reach → next evaluation resolves it.
        $this->rule('>', 1000);
        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame('resolved', Incident::query()->where('subject', 'rule:traffic')->value('status'));
    }

    public function test_no_rules_configured_opens_nothing(): void
    {
        $project = $this->seedRequests(3);
        $this->app['config']->set('warden.alerts.rules', []);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::query()->where('subject', 'like', 'rule:%')->count());
    }
}
