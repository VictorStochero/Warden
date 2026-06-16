<?php

namespace VictorStochero\Warden\Tests\Unit;

use Illuminate\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;
use VictorStochero\Warden\Http\LivewireOrigin;

class LivewireOriginTest extends TestCase
{
    public function test_is_livewire_detects_the_technical_endpoint_by_route_name(): void
    {
        $this->assertTrue(LivewireOrigin::isLivewire('livewire.update', '/livewire/update'));
    }

    public function test_is_livewire_detects_the_technical_endpoint_by_path(): void
    {
        // Sem nome de rota, mas o path delata o endpoint Livewire.
        $this->assertTrue(LivewireOrigin::isLivewire(null, '/livewire/message/x'));
    }

    public function test_is_livewire_is_false_for_a_regular_route(): void
    {
        $this->assertFalse(LivewireOrigin::isLivewire('orders.show', '/orders/42'));
    }

    public function test_resolve_returns_null_when_referer_is_null(): void
    {
        $this->assertNull(LivewireOrigin::resolve(new RouteCollection, null));
    }

    public function test_resolve_returns_null_when_referer_is_empty(): void
    {
        $this->assertNull(LivewireOrigin::resolve(new RouteCollection, ''));
    }

    public function test_resolve_returns_null_when_no_route_matches(): void
    {
        // Best-effort: sem rota casável, devolve null em vez de lançar.
        $this->assertNull(LivewireOrigin::resolve(new RouteCollection, 'http://localhost/orders/42'));
    }
}
