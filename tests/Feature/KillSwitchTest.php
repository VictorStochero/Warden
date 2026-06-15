<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class KillSwitchTest extends TestCase
{
    public function test_kill_switch_disables_capture_entirely(): void
    {
        $this->app['config']->set('warden.enabled', false);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        $this->assertFalse($observer->capturing(), 'A configured child must not capture when disabled');

        $observer->startTrace('request');
        $observer->keep();                 // força manter, para isolar o efeito do kill-switch
        $observer->record('query', ['sql' => 'select 1']);
        $observer->flush();

        $this->assertSame(0, OutboxEntry::count(), 'Nothing is buffered or shipped when disabled');
    }

    public function test_kill_switch_is_read_at_runtime_without_a_rebuild(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $this->assertTrue($observer->capturing());

        // Flip em runtime simula mudar o env em produção sem deploy.
        $this->app['config']->set('warden.enabled', false);
        $this->assertFalse($observer->capturing(), 'The flag must take effect live, not be cached at boot');
    }

    public function test_host_request_still_works_with_warden_disabled(): void
    {
        $this->app['config']->set('warden.enabled', false);

        $this->app['router']->get('/_probe', fn () => response('ok', 200));

        $this->get('/_probe')->assertOk()->assertSee('ok');
    }
}
