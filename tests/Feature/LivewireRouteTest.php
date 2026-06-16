<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;

class LivewireRouteTest extends TestCase
{
    /** Register the origin page route and the Livewire technical endpoint. */
    private function defineLivewireRoutes(): void
    {
        $this->app['router']->get('/orders/{id}', fn () => '')->name('orders.show');
        $this->app['router']->post('/livewire/update', fn () => '')->name('livewire.update');
    }

    /** Pull the captured `request` event's `route` from the single outbox batch. */
    private function capturedRequestRoute(): ?string
    {
        $entry = OutboxEntry::first();
        $this->assertNotNull($entry, 'A request batch should have been flushed to the outbox');

        $request = collect($entry->batch['events'])->firstWhere('type', 'request');
        $this->assertNotNull($request, 'The batch should carry a request event');

        return $request['payload']['route'] ?? null;
    }

    public function test_livewire_request_is_relabelled_to_the_origin_route_via_referer(): void
    {
        $this->defineLivewireRoutes();

        $this->post('/livewire/update', [], ['Referer' => 'http://localhost/orders/42'])
            ->assertOk();

        $this->assertSame('orders.show (via Livewire)', $this->capturedRequestRoute());
    }

    public function test_livewire_request_keeps_the_technical_route_without_a_referer(): void
    {
        $this->defineLivewireRoutes();

        $this->post('/livewire/update')->assertOk();

        // Graceful degradation: no Referer → the technical endpoint stays as-is.
        $this->assertSame('livewire.update', $this->capturedRequestRoute());
    }

    public function test_livewire_request_keeps_the_technical_route_when_referer_does_not_match(): void
    {
        $this->defineLivewireRoutes();

        $this->post('/livewire/update', [], ['Referer' => 'http://localhost/no/such/page'])
            ->assertOk();

        $this->assertSame('livewire.update', $this->capturedRequestRoute());
    }
}
