<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * SSE opt-in transport (§5.4): the same cursor-based JSON payload as the polling
 * endpoint, pushed over a single text/event-stream connection. Gated by config
 * (default off) and bounded so it terminates deterministically under test.
 */
class SseTransportTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // Bound the stream so the test doesn't loop forever.
        $app['config']->set('warden.dashboard.sse.max_ticks', 1);
        $app['config']->set('warden.dashboard.sse.interval_ms', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 's', 'secret' => 's', 'active' => true]);
    }

    public function test_sse_is_disabled_by_default(): void
    {
        config()->set('warden.dashboard.transport', 'poll');

        $this->get(route('warden.project.sse', 'demo'))->assertNotFound();
    }

    public function test_sse_streams_the_same_payload_shape(): void
    {
        config()->set('warden.dashboard.transport', 'sse');

        $response = $this->get(route('warden.project.sse', 'demo'));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('data:', $body);
        // Same payload contract as the polling endpoint: cursor + kpis.
        $this->assertStringContainsString('"cursor"', $body);
        $this->assertStringContainsString('"kpis"', $body);
    }
}
