<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use VictorStochero\Warden\Http\Middleware\TraceRequests;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * A child that has no parent URL + token (e.g. freshly installed, not yet
 * configured) must stay fully inert: no capture middleware, no shipping
 * schedule. This keeps a just-installed package from doing anything until the
 * operator finishes configuring it.
 */
class UnconfiguredChildTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.child.parent_url', null);
        $app['config']->set('warden.child.token', null);
        $app['config']->set('warden.child.secret', null);
    }

    public function test_is_child_configured_reports_false(): void
    {
        $this->assertFalse($this->app->make(Warden::class)->isChildConfigured());
    }

    public function test_unconfigured_child_does_not_attach_the_trace_middleware(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $this->assertFalse($kernel->hasMiddleware(TraceRequests::class));
    }

    public function test_unconfigured_child_does_not_schedule_ship(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $commands = array_map(fn ($event) => $event->command, $schedule->events());

        $shipScheduled = (bool) array_filter(
            $commands,
            fn ($c) => is_string($c) && str_contains($c, 'warden:ship')
        );

        $this->assertFalse($shipScheduled);
    }
}
