<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use VictorStochero\Warden\Http\Middleware\TraceRequests;
use VictorStochero\Warden\Models\Event;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Frente 1 — the parent observes itself, writing events straight into the local
 * database (no HTTP, no outbox).
 */
class SelfMonitorTest extends TestCase
{
    protected bool $selfMonitor = true;

    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.self_monitor', $this->selfMonitor);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/ping', fn () => 'pong');
    }

    public function test_a_self_monitored_request_writes_an_event_to_the_self_project(): void
    {
        $project = $this->app->make(ProjectManager::class)->ensureSelfProject('parent');

        $this->get('/ping')->assertOk();

        $this->assertSame(1, Event::where('project_id', $project->id)->where('type', 'request')->count());

        // Local delivery: nothing went through the outbox.
        $this->assertSame(0, OutboxEntry::count());
    }

    public function test_self_monitor_registers_the_trace_middleware_and_local_delivery(): void
    {
        $this->assertTrue($this->app->make(HttpKernel::class)->hasMiddleware(TraceRequests::class));
        $this->assertTrue($this->app->make(Warden::class)->capturing());
    }

    public function test_ensure_self_project_is_idempotent(): void
    {
        $manager = $this->app->make(ProjectManager::class);

        $first = $manager->ensureSelfProject('parent');
        $second = $manager->ensureSelfProject('parent');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Project::where('slug', 'parent')->count());
        $this->assertNotEmpty($first->token);
    }
}
