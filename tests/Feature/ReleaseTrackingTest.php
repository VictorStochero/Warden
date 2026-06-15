<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Release/deploy tracking (§5.6), child side: every shipped event carries the
 * configured release marker, so the parent can later slice "errors since this
 * deploy" and reopen a regression that returns after one.
 */
class ReleaseTrackingTest extends TestCase
{
    public function test_child_stamps_the_release_on_every_event(): void
    {
        $this->app['config']->set('warden.child.release', 'v1.2.3');

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('query', ['sql' => 'select 1']);
        $observer->record('cache', ['action' => 'hit', 'key' => 'k', 'hit' => true]);
        $observer->flush();

        $events = OutboxEntry::first()->batch['events'];

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame('v1.2.3', $event['release']);
        }
    }

    public function test_release_is_null_when_unconfigured(): void
    {
        $this->app['config']->set('warden.child.release', null);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $observer->record('query', ['sql' => 'select 1']);
        $observer->flush();

        $events = OutboxEntry::first()->batch['events'];

        $this->assertNull($events[0]['release']);
    }
}
