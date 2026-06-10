<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;

class DemoCommandTest extends TestCase
{
    public function test_it_emits_each_capturable_event_type_to_the_outbox(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('warden:demo')->assertSuccessful();

        $this->assertSame(1, OutboxEntry::count(), 'the demo trace is flushed as one batch');

        $types = collect(OutboxEntry::first()->batch['events'])
            ->pluck('type')
            ->unique()
            ->values()
            ->all();

        foreach (['query', 'cache', 'log', 'exception', 'mail', 'notification', 'http', 'job'] as $type) {
            $this->assertContains($type, $types, "expected a [{$type}] event to be captured");
        }
    }

    public function test_only_filter_restricts_the_emitted_types(): void
    {
        $this->artisan('warden:demo', ['--only' => 'log,cache'])->assertSuccessful();

        $types = collect(OutboxEntry::first()->batch['events'])->pluck('type')->unique()->values()->all();

        $this->assertContains('log', $types);
        $this->assertContains('cache', $types);
        $this->assertNotContains('mail', $types);
        $this->assertNotContains('http', $types);
    }

    public function test_count_generates_one_batch_per_trace(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('warden:demo', ['--count' => 3, '--only' => 'log'])->assertSuccessful();

        $this->assertSame(3, OutboxEntry::count());
    }

    public function test_it_fails_when_the_child_is_not_configured(): void
    {
        $this->app['config']->set('warden.child.parent_url', '');
        $this->app['config']->set('warden.child.token', '');

        $this->artisan('warden:demo')->assertFailed();

        $this->assertSame(0, OutboxEntry::count());
    }
}
