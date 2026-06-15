<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Facades\Warden as WardenFacade;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Custom instrumentation API (§35): Warden::measure() lets the host time its own
 * business spans, turning automatic capture into an extensible platform.
 */
class CustomInstrumentationTest extends TestCase
{
    public function test_measure_records_a_custom_event_and_returns_the_value(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $result = $observer->measure('checkout.total', fn (): int => 40 + 2, ['order' => 7]);

        $this->assertSame(42, $result);

        $observer->flush();

        $custom = collect(OutboxEntry::first()->batch['events'])->firstWhere('type', 'custom');

        $this->assertNotNull($custom);
        $this->assertSame('checkout.total', $custom['payload']['name']);
        $this->assertSame(7, $custom['payload']['order']);
        $this->assertNotNull($custom['duration_us']);
    }

    public function test_measure_runs_the_callback_even_when_not_capturing(): void
    {
        $this->app['config']->set('warden.enabled', false);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        $this->assertSame('ok', $observer->measure('x', fn (): string => 'ok'));
        $this->assertSame(0, OutboxEntry::count());
    }

    public function test_measure_works_through_the_facade(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $value = WardenFacade::measure('report', fn (): string => 'done');

        $this->assertSame('done', $value);

        $observer->flush();
        $this->assertNotNull(collect(OutboxEntry::first()->batch['events'])->firstWhere('type', 'custom'));
    }
}
