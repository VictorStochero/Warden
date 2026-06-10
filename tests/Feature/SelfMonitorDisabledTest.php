<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use VictorStochero\Warden\Http\Middleware\TraceRequests;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * With self-monitoring off, a parent stays inert: no trace middleware, no
 * capture — it only ingests what children ship.
 */
class SelfMonitorDisabledTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', false);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/ping', fn () => 'pong');
    }

    public function test_parent_without_self_monitor_does_not_capture(): void
    {
        $project = $this->app->make(ProjectManager::class)->ensureSelfProject('parent');

        $this->get('/ping')->assertOk();

        $this->assertFalse($this->app->make(Warden::class)->capturing());
        $this->assertFalse($this->app->make(HttpKernel::class)->hasMiddleware(TraceRequests::class));
        $this->assertSame(0, Event::where('project_id', $project->id)->count());
    }
}
