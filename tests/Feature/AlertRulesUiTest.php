<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\AlertRule;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * UI-managed alert rules (§5.5): rules stored in wdn_alert_rules are evaluated
 * alongside the config ones, and can be created/removed from the settings page.
 */
class AlertRulesUiTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    private function project(): Project
    {
        return Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    public function test_a_db_rule_is_evaluated_and_opens_an_incident(): void
    {
        $project = $this->project();

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b1', 'events' => [
            ['type' => 'request', 'trace_id' => 't1', 'span_id' => 's1', 'occurred_at' => $at, 'duration_us' => 1000, 'payload' => ['method' => 'GET', 'route' => '/x', 'path' => '/x', 'status' => 200]],
        ]]]);
        $this->app->make(Aggregator::class)->rollup($project->id, 'request');

        AlertRule::create(['name' => 'any-traffic', 'metric' => 'throughput', 'op' => '>', 'threshold' => 0, 'window' => '24h', 'severity' => 'warning', 'enabled' => true]);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(1, Incident::query()->where('subject', 'rule:any-traffic')->where('status', 'open')->count());
    }

    public function test_a_disabled_db_rule_is_skipped(): void
    {
        $project = $this->project();
        AlertRule::create(['name' => 'off', 'metric' => 'throughput', 'op' => '>', 'threshold' => 0, 'window' => '24h', 'severity' => 'warning', 'enabled' => false]);

        $this->app->make(Evaluator::class)->evaluate($project->id);

        $this->assertSame(0, Incident::query()->where('subject', 'rule:off')->count());
    }

    public function test_rule_can_be_created_and_deleted_from_settings(): void
    {
        $this->post(route('warden.admin.settings.rules.store'), [
            'name' => 'High error rate', 'metric' => 'error_rate', 'op' => '>', 'threshold' => '5', 'window' => '1h', 'severity' => 'critical',
        ])->assertRedirect(route('warden.admin.settings'));

        $rule = AlertRule::query()->where('name', 'High error rate')->first();
        $this->assertNotNull($rule);
        $this->assertSame('error_rate', $rule->metric);
        $this->assertEqualsWithDelta(5.0, $rule->threshold, 0.001);

        $this->post(route('warden.admin.settings.rules.delete', $rule->id))
            ->assertRedirect(route('warden.admin.settings'));

        $this->assertNull(AlertRule::query()->find($rule->id));
    }

    public function test_invalid_metric_is_rejected(): void
    {
        $this->post(route('warden.admin.settings.rules.store'), [
            'name' => 'Bad', 'metric' => 'not_a_metric', 'op' => '>', 'threshold' => '1', 'window' => '1h', 'severity' => 'warning',
        ]);

        $this->assertSame(0, AlertRule::query()->where('name', 'Bad')->count());
    }
}
