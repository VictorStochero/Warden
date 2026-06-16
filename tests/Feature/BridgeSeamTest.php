<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Event;
use VictorStochero\Warden\Contracts\EventForwarder;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Events\EventsIngested;
use VictorStochero\Warden\Models\Event as WardenEvent;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Warden Bridge seam (§9.2): a no-op-by-default extension point fired after the
 * parent persists a batch, so a future OTLP forwarder can plug in without
 * touching the core. The default forwarder does nothing and zero overhead is
 * added; a forwarder that throws can never break the ingest (RNF-2).
 */
class BridgeSeamTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function batches(): array
    {
        return [[
            'id' => 'batch-1',
            'events' => [
                ['type' => 'request', 'trace_id' => 't1', 'occurred_at' => now()->toDateTimeString(), 'payload' => ['route' => '/']],
            ],
        ]];
    }

    public function test_forwarder_receives_the_persisted_events(): void
    {
        $spy = new class implements EventForwarder
        {
            /** @var list<array<string, mixed>> */
            public array $events = [];

            public string $project = '';

            public function forward(string $projectSlug, array $events): void
            {
                $this->project = $projectSlug;
                $this->events = $events;
            }
        };
        $this->app->instance(EventForwarder::class, $spy);

        $accepted = $this->app->make(Ingestor::class)->ingest('demo', $this->batches());

        $this->assertSame(1, $accepted);
        $this->assertSame('demo', $spy->project);
        $this->assertCount(1, $spy->events);
        $this->assertSame('request', $spy->events[0]['type'] ?? null);
    }

    public function test_default_forwarder_is_a_noop_and_ingest_still_works(): void
    {
        // No binding override → the NullEventForwarder default is used.
        $accepted = $this->app->make(Ingestor::class)->ingest('demo', $this->batches());

        $this->assertSame(1, $accepted);
        $this->assertSame(1, WardenEvent::query()->count());
    }

    public function test_a_throwing_forwarder_never_breaks_the_ingest(): void
    {
        $this->app->instance(EventForwarder::class, new class implements EventForwarder
        {
            public function forward(string $projectSlug, array $events): void
            {
                throw new \RuntimeException('bridge down');
            }
        });

        $accepted = $this->app->make(Ingestor::class)->ingest('demo', $this->batches());

        $this->assertSame(1, $accepted);
        $this->assertSame(1, WardenEvent::query()->count());
    }

    public function test_events_ingested_is_dispatched_with_the_events(): void
    {
        Event::fake([EventsIngested::class]);

        $this->app->make(Ingestor::class)->ingest('demo', $this->batches());

        Event::assertDispatched(EventsIngested::class, function (EventsIngested $event) {
            return $event->projectSlug === 'demo' && count($event->events) === 1;
        });
    }
}
