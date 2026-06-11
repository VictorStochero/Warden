<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The CSS is served through PHP now (not a static file), so the global
 * TraceRequests middleware sees the asset hit. The controller drops the trace so a
 * self-monitoring parent never records its own stylesheet request as throughput.
 */
class AssetSelfMonitorTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
    }

    public function test_serving_the_stylesheet_is_not_recorded_as_a_request(): void
    {
        $project = $this->app->make(ProjectManager::class)->ensureSelfProject('parent');

        $this->get(route('warden.asset.css'))->assertOk();

        $this->assertSame(0, Event::where('project_id', $project->id)->where('type', 'request')->count());
    }
}
