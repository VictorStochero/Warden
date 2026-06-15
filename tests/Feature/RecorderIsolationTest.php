<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Recording\RecorderHealth;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * A recorder double whose listener always throws — used to prove that a faulty
 * recorder is isolated structurally (RNF-2) and that the breaker trips.
 */
class BoomRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'boom';
    }

    public function register(): void
    {
        // Goes through the guarded chokepoint, exactly like a real recorder.
        $this->listen('warden.test.boom', fn () => throw new RuntimeException('recorder exploded'));
    }
}

class RecorderIsolationTest extends TestCase
{
    public function test_a_throwing_recorder_never_propagates_into_the_host(): void
    {
        /** @var BoomRecorder $recorder */
        $recorder = $this->app->make(BoomRecorder::class);
        $recorder->register();

        // The host dispatches the event and MUST keep going — reaching the line
        // after dispatch is the assertion: no exception escaped the recorder.
        $this->app['events']->dispatch('warden.test.boom');

        $this->assertTrue(true);
    }

    public function test_other_recorders_keep_working_after_one_fails(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        // Recorder A explodes...
        $this->app->make(BoomRecorder::class)->register();
        $this->app['events']->dispatch('warden.test.boom');

        // ...the normal capture path still records.
        $observer->record('cache', ['action' => 'hit', 'key' => 'k', 'hit' => true]);
        $observer->flush();

        $batch = OutboxEntry::first()->batch;
        $types = collect($batch['events'])->pluck('type')->all();

        $this->assertContains('cache', $types);
    }

    public function test_recorder_trips_and_goes_silent_after_threshold(): void
    {
        $this->app['config']->set('warden.child.recorder_breaker_threshold', 3);

        $this->app->make(BoomRecorder::class)->register();

        $wardenLogs = 0;
        Log::listen(function ($message) use (&$wardenLogs): void {
            if (($message->context['warden'] ?? false) === true) {
                $wardenLogs++;
            }
        });

        for ($i = 0; $i < 50; $i++) {
            $this->app['events']->dispatch('warden.test.boom');
        }

        // Logs exactly the first failure and the trip — then stays silent.
        // Never a storm, no matter how many times the event fires.
        $this->assertLessThanOrEqual(2, $wardenLogs, 'Breaker must stop logging after it trips');

        $health = $this->app->make(RecorderHealth::class);
        $this->assertTrue($health->isTripped('boom'), 'Breaker must be open after the threshold');
        $this->assertSame(['failures' => 50, 'tripped' => true], $health->snapshot()['boom']);
    }
}
