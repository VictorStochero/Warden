<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use VictorStochero\Warden\Http\Middleware\TraceRequests;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Boots a configured child with the global kill-switch OFF to prove that the
 * capture pipeline (trace middleware + recorders) is never even wired up — not
 * just inert at flush. Disabled means zero overhead, not "registered but quiet".
 */
class KillSwitchBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Disabled at boot, while still a fully configured child.
        $app['config']->set('warden.enabled', false);
    }

    public function test_trace_middleware_is_not_registered_when_disabled(): void
    {
        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        $this->assertFalse(
            $kernel->hasMiddleware(TraceRequests::class),
            'The trace middleware must not be wired when Warden is disabled'
        );
    }
}
