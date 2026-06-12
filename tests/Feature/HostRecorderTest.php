<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use VictorStochero\Warden\Recording\Recorders\HostRecorder;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class HostRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The recorder keeps in-process fallbacks in statics; clear them so the
        // assertions exercise the cross-process state file, not a leak from a
        // previous test in this same PHPUnit process.
        foreach (['lastSample', 'lastState'] as $prop) {
            (new \ReflectionProperty(HostRecorder::class, $prop))->setValue(null, null);
        }
    }

    /**
     * CPU% needs the delta between two /proc/stat snapshots. The previous
     * snapshot lives in a cross-process state file (so it also works under
     * PHP-FPM, where statics reset every request): the second sample must
     * produce a numeric CPU plus the detailed memory/disk/process payload.
     */
    public function test_it_captures_detailed_metrics_and_cpu_from_the_second_sample(): void
    {
        if (! is_readable('/proc/stat') || ! is_readable('/proc/meminfo')) {
            $this->markTestSkipped('host detail requires a readable /proc (Linux)');
        }

        config()->set('warden.child.host_interval', 0);

        $warden = $this->app->make(Warden::class);
        $warden->startTrace('request');

        $statePath = sys_get_temp_dir().'/warden-host-test-'.bin2hex(random_bytes(6)).'.json';
        $recorder = $this->recorderWithStatePath($warden, $statePath);

        try {
            $recorder->sample();
            usleep(200_000); // a real tick delta between the two /proc/stat reads
            $recorder->sample();

            $this->assertFileExists($statePath, 'the cross-process snapshot file must be written');
        } finally {
            @unlink($statePath);
        }

        $events = array_values(array_filter(
            $warden->buffer()->all(),
            fn (array $e): bool => $e['type'] === 'host',
        ));

        $this->assertCount(2, $events);

        $payload = $events[1]['payload'];

        $this->assertIsFloat($payload['cpu'], 'second sample must compute CPU% from the persisted snapshot');
        $this->assertGreaterThanOrEqual(0.0, $payload['cpu']);
        $this->assertLessThanOrEqual(100.0, $payload['cpu']);
        $this->assertGreaterThan(0, $payload['cores']);

        $this->assertGreaterThan(0, $payload['memory']['total']);
        $this->assertGreaterThanOrEqual(0, $payload['memory']['used']);
        $this->assertArrayHasKey('available', $payload['memory']);
        $this->assertArrayHasKey('swap_total', $payload['memory']);

        $this->assertGreaterThan(0, $payload['disk']['total']);
        $this->assertArrayHasKey('used', $payload['disk']);
        $this->assertArrayHasKey('free', $payload['disk']);

        $this->assertNotEmpty($payload['processes']);
        $proc = $payload['processes'][0];
        $this->assertArrayHasKey('pid', $proc);
        $this->assertArrayHasKey('name', $proc);
        $this->assertArrayHasKey('cpu', $proc);
        $this->assertArrayHasKey('memory', $proc);
    }

    public function test_the_interval_throttle_holds_across_processes_via_the_state_file(): void
    {
        if (! is_readable('/proc/stat')) {
            $this->markTestSkipped('requires a readable /proc (Linux)');
        }

        config()->set('warden.child.host_interval', 3600);

        $warden = $this->app->make(Warden::class);
        $warden->startTrace('request');

        $statePath = sys_get_temp_dir().'/warden-host-test-'.bin2hex(random_bytes(6)).'.json';

        try {
            // Both samples read the same state file — as two FPM requests would.
            $this->recorderWithStatePath($warden, $statePath)->sample();
            $this->recorderWithStatePath($warden, $statePath)->sample();
        } finally {
            @unlink($statePath);
        }

        $events = array_values(array_filter(
            $warden->buffer()->all(),
            fn (array $e): bool => $e['type'] === 'host',
        ));

        $this->assertCount(1, $events, 'the second sample inside the interval must be throttled');
    }

    protected function recorderWithStatePath(Warden $warden, string $statePath): HostRecorder
    {
        return new class($warden, $this->app->make(Dispatcher::class), $this->app->make(Repository::class), $statePath) extends HostRecorder
        {
            public function __construct(
                Warden $observer,
                Dispatcher $events,
                Repository $config,
                protected string $testStatePath,
            ) {
                parent::__construct($observer, $events, $config);
            }

            protected function statePath(): string
            {
                return $this->testStatePath;
            }
        };
    }
}
